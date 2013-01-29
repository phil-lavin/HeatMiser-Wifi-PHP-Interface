<?php

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
