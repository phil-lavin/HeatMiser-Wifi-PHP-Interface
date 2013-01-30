<?php

/**
* PHP interface to Heatmiser wifi thermostats - Main interface class
* by Phil Lavin <phil@lavin.me.uk>.
*
* Released under the BSD license.
*
* References from and thanks to:
*     http://code.google.com/p/heatmiser-wifi/
*     http://www.heatmiser.com/web/index.php/support/manuals-and-documents/finish/27-network-protocol/25-v3-9-protocol-document
*/


// Register error handler
require_once 'error.php';
Error::register();

// Binary functions
require_once 'bin.php';

// DCB
require_once 'dcb.php';

// Main class
class Heatmiser_Wifi {
	protected $ip;
	protected $port;
	protected $pin;

	protected $sock;

	public function __construct($ip, $pin, $port = 8068) {
		$this->ip = $ip;
		$this->pin = $pin;
		$this->port = $port;

		// Connect to thermostat
		$this->connect();

		// Shutdown stuff
		register_shutdown_function(function() {
			$this->disconnect();
		});
	}

	public function connect() {
		Error::set_enabled(false);

		if ( ! $this->sock = fsockopen($this->ip, $this->port, $errno, $errstr, 10)) {
			throw new ConnectionFailedException($errstr);
		}

		Error::set_enabled(true);
	}

	public function disconnect() {
		$this->sock and fclose($this->sock);
	}

	// Low level command input
	// data should come as an array of octets or a string
	protected function command($op, $data) {
		!is_array($data) and $data = \Bin::zero_unpack('C*', $response);

		$len = 7 + count($data);
		$cmd = array_merge([$op], Bin::w2b($len), Bin::w2b($this->pin), $data);
		$cmd = array_merge($cmd, Bin::w2b($this->crc16($cmd)));

		$bin = Bin::array_pack('C*', $cmd);

		if (fputs($this->sock, $bin) === false) {
			throw new SendFailedException("Failed to write command to thermostat");
		}
	}

	// Low level fetch and decode
	protected function response() {
		if (($response = fread($this->sock, 65536)) === false) {
			throw new ReadFailedException("Failed to read data from the thermostat");
		}

		// Split the response into octets
		$response = \Bin::zero_unpack('C*', $response);

		// Extract interesting fields
		$op = $response[0];
		$len = \Bin::b2w($response[1], $response[2]);
		$data = array_slice($response, 3, -2);
		$crc = \Bin::b2w(array_slice($response, -2));

		// Check len
		if ($len != count($response)) {
			throw new InvalidResponseException("Length of response does not match length header");
		}

		// Check crc
		$actual_crc = $this->crc16(array_slice($response, 0, -2));

		if ($actual_crc != $crc) {
			throw new InvalidResponseException("CRC of response is not valid");
		}

		// Return interesting fields
		return ['op'=>$op, 'data'=>$data];
	}

	protected function read_dcb() {
		$this->command(0x93, [0x00, 0x00, 0xff, 0xff]);
		$response = $this->response();
		$data = $response['data'];

		// Check op code
		if ($response['op'] != 0x94) {
			throw new InvalidDCBResponseException("Opcode incorrect in response");
		}

		// Check start
		if (\Bin::b2w($data[0], $data[1]) != 0x0000) {
			throw new InvalidDCBResponseException("Start is incorrect in response");
		}

		// Check len - no len suggest incorrect pin
		if ( ! ($length = \Bin::b2w($data[2], $data[3]))) {
			throw new InvalidPinException("Thermostat returned a 0 length response. This suggests the pin is wrong");
		}

		// Check len
		if ($length + 4 != count($data)) {
			throw new InvalidDCBResponseException("Response length is not correct");
		}

		// Check other len
		if (\Bin::b2w($data[4], $data[5]) != count($data) - 4) {
			throw new InvalidDCBResponseException("Response length 2 is not correct");
		}

		return array_slice($data, 4);
	}

	public function get_dcb() {
		// Get it
		$raw = $this->read_dcb();

		// Make DCB obj
		return new DCB($raw);
	}

	// Generate the CRC
	protected static function crc16(array $octets) {
		$crc16_4bits = function($crc, $nibble) {
			$lookup =
				[
					0x0000, 0x1021, 0x2042, 0x3063,
					0x4084, 0x50A5, 0x60C6, 0x70E7,
					0x8108, 0x9129, 0xA14A, 0xB16B,
					0xC18C, 0xD1AD, 0xE1CE, 0xF1EF
				];

			return (($crc << 4) & 0xffff) ^ $lookup[($crc >> 12) ^ $nibble];
		};

		$crc = 0xffff;
		foreach ($octets as $octet) {
			$crc = $crc16_4bits($crc, $octet >> 4);
			$crc = $crc16_4bits($crc, $octet & 0x0f);
		}

		return $crc;
	}
}

// Exceptions
class ConnectionFailedException extends \Exception {}
class SendFailedException extends \Exception {}
class ReadFailedException extends \Exception {}
class InvalidResponseException extends \Exception {}
class InvalidDCBResponseException extends InvalidResponseException {}
class InvalidPinException extends InvalidResponseException {}
