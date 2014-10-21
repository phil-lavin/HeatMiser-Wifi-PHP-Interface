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

namespace PhilLavin\HeatMiser;

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
			$this->set_caloffset(Bin::b2w(array_slice($dcb, 8, 2)));
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
				$t = Bin::b2w($t);

				return $t == 0xffff ? null : $t / 10;
			};

			$this->set_remote_temperature($temp([$dcb[33], $dcb[34]]));
			$this->set_floor_temperature($temp([$dcb[35], $dcb[36]]));
			$this->set_internal_temperature($temp([$dcb[37], $dcb[38]]));

			$this->set_heating_on($dcb[40]);
			$this->set_heating_target($dcb[18]);
			$this->set_heating_hold(Bin::b2w([$dcb[31], $dcb[32]]));

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
				Error::write("DCB is longer than expected. There's ".count($prog)." octets left");
		}
	}

	// Returns the changes since the last commit() as a [key=>[value]] set for use in raw writing
	public function get_changes() {
		$out = array();
		$failed = array();

		foreach ($this->new_data as $key=>$value) {
			switch ($key) {
				case 'time':
					$out[43] = $this->from_sql_datetime($value);
					break;
				case 'enabled':
					$out[21] = [(int)$value];
					break;
				case 'keylock':
					$out[22] = [(int)$value];
					break;
				case 'holiday_enabled':
					if ( ! $value) // So it seems setting holiday is implicit of enabling it
						$out[24] = [(int)$value];
					break;
				case 'holiday':
					$out[24] = $this->from_sql_datetime($value, false);
					break;
				case 'runmode':
					if ($this['model'] != 'TM1')
						$out[23] = [(int)$value];
					break;
				case 'frostprotect_target':
					if ($this['model'] != 'TM1')
						$out[17] = [(int)$value];
					break;
				case 'floorlimit_floormax':
					if (substr($this['model'], -2) == '-E')
						$out[19] = [(int)$value];
					break;
				case 'heating_target':
					if ($this['model'] != 'TM1')
						$out[18] = [(int)$value];
					break;
				case 'heating_hold':
					if ($this['model'] != 'TM1')
						$out[32] = Bin::w2b($value);
					break;
				case 'hotwater_on':
					// Hotwater models
					if (preg_match('/(HW|TM1)$/', $this['model']))
						$out[42] = [($value ? 2 : 1)];
					break;
				case 'heat_data':
					if (preg_match('/^PRT/', $this['model'])) {
						$i = 0;

						foreach ($value as $day) {
							$heat_data = array();

							foreach ($day as $entry) {
								// Disabled entry
								if ( ! $entry) {
									$heat_data = array_merge($heat_data, [24, 0, 16]);
								}
								else {
									$heat_data = array_merge(
												$heat_data,
												$this->from_sql_time($entry['time'], false),
												[$entry['target']]
											);
								}
							}

							$index = (count($value) == 2 ? 47 : 103) + $i++ * 12;
							$out[$index] = $heat_data;
						}
					}
					break;
				case 'water_data':
					if ($this['model'] == 'PRTHW' || $this['model'] == 'TM1') {
						$i = 0;

						foreach ($value as $day) {
							$water_data = array();

							foreach ($day as $entry) {
								// Disabled entry
								if ( ! $entry) {
									$water_data = array_merge($water_data, [24, 0, 24, 0]);
								}
								else {
									$water_data = array_merge(
												$water_data,
												$this->from_sql_time($entry['on'], false),
												$this->from_sql_time($entry['off'], false)
											);
								}
							}

							$index = (count($value) == 2 ? 71 : 187) + $i++ * 16;
							$out[$index] = $water_data;
						}
					}
					break;
				default:
					$failed[] = $key;
					break;
			}
		}

		return $out;
	}

	protected function sql_datetime($data) {
		list($year, $month, $day, $wday, $hour, $minute, $second) = $data;

		return sprintf(
			'%04d-%02d-%02d %02d:%02d:%02d',
			2000+$year, $month, $day, $hour, $minute, $second
		);
	}

	// Will convert anything strtotime can do
	protected function from_sql_datetime($data, $dow = true) {
		if ( ($ts = strtotime($data)) === false) {
			throw new InvalidDataException("Date format provided is not valid");
		}

		$out = explode(' ', date('y n j'.($dow?' w':'').' G i s', $ts));
		return array_map('intval', $out);
	}

	protected function sql_time($data) {
		list($hour, $minute, $second) = $data;

		return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
	}

	// Will also convert anything strtotime can do
	protected function from_sql_time($data, $secs = true) {
		if ( ($ts = strtotime($data)) === false) {
			throw new InvalidDataException("Date format provided is not valid");
		}

		$out = explode(' ', date('G i'.($secs?' s':''), $ts));
		return array_map('intval', $out);
	}

	public function get_raw() {
		return $this->raw;
	}

	protected function set_attr($name, $val) {
		$this[$name] = $val;
		$this->new_data[$name] = $val;
	}

	// Somewhat magic-from-a-distance
	// Allows the use of set_thing() functions to set data
	// Designed to allow complex sets to override using their own function
	public function __call($name, $args) {
		if (substr($name, 0, 4) == 'set_') {
			$attr = substr($name, 4);
			$this->set_attr($attr, $args[0]);

			return $args[0];
		}

		throw new InvalidMethodException("Invalid method '{$name}' called");
	}

	// set_*() overrides
	public function set_holiday($val) {
		$this->set_attr('holiday', $val);
		$this->set_attr('holiday_enabled', 1);
	}

	public function set_heat_data(array $val) {
		// Validate number
		if (count($val) != ($this['progmode'] == '5/2' ? 2 : 7))
			throw new InvalidDataException("Invalid quantity of data provided given a programming mode of {$this['progmode']}");

		// Validate structure
		foreach ($val as $day) {
			if (!is_array($day))
				throw new InvalidDataException("Invalid structure of heat data days");

			foreach ($day as $entry) {
				if ($entry && (!isset($entry['time']) || !isset($entry['target'])))
					throw new InvalidDataException("Invalid structure of heat data entries");
			}
		}

		$this->set_attr('heat_data', $val);
	}

	public function set_water_data(array $val) {
		// Validate number
		if (count($val) != ($this['progmode'] == '5/2' ? 2 : 7))
			throw new InvalidDataException("Invalid quantity of data provided given a programming mode of {$this['progmode']}");

		// Validate structure
		foreach ($val as $day) {
			if (!is_array($day))
				throw new InvalidDataException("Invalid structure of hot water data days");

			foreach ($day as $entry) {
				if ($entry && (!isset($entry['on']) || !isset($entry['off'])))
					throw new InvalidDataException("Invalid structure of hot water data entries");
			}
		}

		$this->set_attr('water_data', $val);
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
class InvalidDataException extends \Exception {}
