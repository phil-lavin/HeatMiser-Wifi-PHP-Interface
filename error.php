<?php

/**
* PHP interface to Heatmiser wifi thermostats - Basic error handling class
* by Phil Lavin <phil@lavin.me.uk>.
*
* Released under the BSD license.
*
* References from and thanks to:
*     http://code.google.com/p/heatmiser-wifi/
*     http://www.heatmiser.com/web/index.php/support/manuals-and-documents/finish/27-network-protocol/25-v3-9-protocol-document
*/

class Error {
	protected static $enabled = true;

	public static function register() {
		set_error_handler(array('Error', 'error_handler'));
	}

	public static function error_handler($errno, $errstr, $errfile, $errline) {
		if (!(error_reporting() & $errno)) {
			return;
		}

		if ( ! static::$enabled) {
			return;
		}

		switch ($errno) {
			case E_ERROR:
				static::write("Fatal Error: {$errstr} on line {$errline} of {$errfile}");
				exit;
			default:
				static::write("Error: {$errstr} on line {$errline} of {$errfile}");
		}
	}

	public static function set_enabled($enabled) {
		static::$enabled = $enabled;
	}

	public static function write($msg) {
		$handle = fopen('php://stderr', 'w');
		fwrite($handle, $msg."\n");
		fclose($handle);
	}
}
