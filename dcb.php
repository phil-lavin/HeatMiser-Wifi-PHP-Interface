<?php

/**
* PHP interface to Heatmiser wifi thermostats - OO representation of a DCB
* by Phil Lavin <phil@lavin.me.uk>.
*
* Released under the BSD license.
*
* References from and thanks to:
*     http://code.google.com/p/heatmiser-wifi/
*     http://www.heatmiser.com/web/index.php/support/manuals-and-documents/finish/27-network-protocol/25-v3-9-protocol-document
*/

class DCB implements \ArrayAccess {
	protected $raw;
	protected $data = array();
	protected $new_data = array();

	public function __construct($raw) {
		$this->raw = $raw;

		$this->parse();
	}

	// Wipes new_data such that we can diff from the beginning
	public function commit() {
		$this->new_data = array();
	}

	// Parses the raw DCB
	protected function parse() {
		$lookup = function($key, $vals) {
			if (array_key_exists($key, $vals))
				return $vals[$key];

			throw new LookupFailedException("Cannot find key ({$key}) in the provided values");
		};

		$dcb = $this->raw;

		$this->set_vendor($dcb[2] ? 'OEM' : 'Heatmiser');
		$this->set_version(($dcb[3] & 0x7f) / 10);
		$model = $this->set_model($lookup($dcb[4], [
					0 => 'DT', 1 => 'DT-E',
					2 => 'PRT', 3 => 'PRT-E',
					4 => 'PRTHW', 5 => 'TM1'
				]));

		$timebase = ($model == 'PRTHW' || $model == 'TM1') ? 44 : 41;
		$this->set_time($this->sql_datetime(array_slice($dcb, $timebase, 7)));

		$this->set_enabled($dcb[21]);
		$this->set_keylock($dcb[22]);

		$this->set_holiday($this->sql_datetime([$dcb[25],$dcb[26],$dcb[27],null,$dcb[28],$dcb[29],0]));
		$this->set_holiday_enabled($dcb[30]);

		// Models with thermostats
		if ($model != 'TM1') {
			$this->set_units($dcb[5] ? 'F' : 'C');
			$this->set_switchdiff($dcb[6] / 2);
			$this->set_caloffset(\Bin::b2w(array_slice($dcb, 8, 2)));
			$this->set_outputdelay($dcb[10]);
			$this->set_locklimit($dcb[12]);
			$this->set_sensor($lookup($dcb[13], [
					0 => 'internal', 1 => 'remote',
					2 => 'floor', 3 => 'internal + floor',
					4 => 'remote + floor'
				]));
			$this->set_optimumstart($dcb[14]);
			$this->set_runmode($dcb[23] ? 'frost' : 'heating');
			$this->set_frostprotect_enabled($dcb[7]);
			$this->set_frostprotect_target($dcb[17]);

			if (substr($model, -2) == '-E') {
				$this->set_floorlimit_limiting($dcb[3] >> 7);
				$this->set_floorlimit_floormax($dcb[20]);
			}

			$temp = function($t) {
				$t = \Bin::b2w($t);

				return $t == 0xffff ? null : $t / 10;
			};

			$this->set_remote_temperature($temp([$dcb[33], $dcb[34]]));
			$this->set_floor_temperature($temp([$dcb[35], $dcb[36]]));
			$this->set_internal_temperature($temp([$dcb[37], $dcb[38]]));

			$this->set_heating_on($dcb[40]);
			$this->set_heating_target($dcb[18]);
			$this->set_heating_hold(\Bin::b2w([$dcb[31], $dcb[32]]));

			$this->set_rateofchange($dcb[15]); // Mins per unit change
			$this->set_errorcode($lookup($dcb[39],
						[
							0 => null, 0xE0 => 'internal', 0xE1 => 'floor',
							0xE2 => 'remote'
						]));
		}

		// Hotwater models
		if (preg_match('/(HW|TM1)$/', $model))
			$this->set_hotwater_on($dcb[43]);

		$progmode = $this->set_progmode($dcb[16] ? '7' : '5/2');

		// Programmable thermostats
		if ( ! preg_match('/^DT/', $model)) {
			$days = ($progmode == '5/2' ? 2 : 7);
			$progbase = ($model == 'PRTHW' || $model == 'TM1') ? 51 : 48;

			if ($days == 7) {
				if (preg_match('/^PRT/', $model))
					$progbase += 24;
				if ($model == 'PRTHW' || $model == 'TM1')
					$progbase += 32;
			}

			$prog = array_slice($dcb, $progbase);

			// Heating programme
			if (preg_match('/^PRT/', $model)) {
				$heat_data = array();

				for ($day = 0; $day < $days; $day++) {
					for ($entry = 0; $entry <= 3; $entry++) {
						$key = ($days == 7 ? $day : ($day ? '6-7' : '1-5'));

						if ($prog[0] < 24) {
							$heat_data[$key][$entry] = [
								'time' => $this->sql_time([$prog[0], $prog[1], 0]),
								'target' => $prog[2],
							];
						}
						else {
							$heat_data[$key][$entry] = null;
						}

						$prog = array_slice($prog, 3);
					}
				}

				$this->set_heat_data($heat_data);
			}

			// Hot water programme
			if ($model == 'PRTHW' || $model == 'TM1') {
				$water_data = array();

				for ($day = 0; $day < $days; $day++) {
					for ($entry = 0; $entry <= 3; $entry++) {
						$key = ($days == 7 ? $day : ($day ? '6-7' : '1-5'));

						if ($prog[0] < 24) {
							$water_data[$key][$entry] = [
								'on' => $this->sql_time([$prog[0], $prog[1], 0]),
								'off' => $this->sql_time([$prog[2], $prog[3], 0]),
							];
						}
						else {
							$water_data[$key][$entry] = null;
						}

						$prog = array_slice($prog, 4);
					}
				}

				$this->set_water_data($water_data);
			}

			// Commit to wipe the new data record
			$this->commit();

			if ($prog)
				\Error::write("DCB is longer than expected. There's ".count($prog)." octets left");
		}
	}

	protected function sql_datetime($data) {
		list($year, $month, $day, $wday, $hour, $minute, $second) = $data;

		return sprintf(
			'%04d-%02d-%02d %02d:%02d:%02d',
			2000+$year, $month, $day, $hour, $minute, $second
		);
	}

	protected function sql_time($data) {
		list($hour, $minute, $second) = $data;

		return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
	}

	public function get_raw() {
		return $this->raw;
	}

	// Somewhat magic-from-a-distance
	// Allows the use of set_thing() functions to set data
	// Designed to allow complex sets to override using their own function
	public function __call($name, $args) {
		if (substr($name, 0, 4) == 'set_') {
			$attr = substr($name, 4);
			$this[$attr] = $args[0];
			$this->new_data[$attr] = $args[0];

			return $args[0];
		}

		throw new InvalidMethodException("Invalid method '{$name}' called");
	}

	// ArrayAccess
	public function offsetExists($offset) {
		return array_key_exists($offset, $this->data);
	}

	public function offsetGet($offset) {
		return $this->offsetExists($offset) ? $this->data[$offset] : null;
	}

	public function offsetSet($offset, $data) {
		if (is_null($offset)) {
			$this->data[] = $data;
		}
		else {
			$this->data[$offset] = $data;
		}
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}
}

class InvalidMethodException extends \Exception {}
class LookupFailedException extends \Exception {}
