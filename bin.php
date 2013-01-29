<?php

/**
* PHP interface to Heatmiser wifi thermostats - Binary utility class
* by Phil Lavin <phil@lavin.me.uk>.
*
* Released under the BSD license.
*
* References from and thanks to:
*     http://code.google.com/p/heatmiser-wifi/
*     http://www.heatmiser.com/web/index.php/support/manuals-and-documents/finish/27-network-protocol/25-v3-9-protocol-document
*/

class Bin {
	// Convert a 16-bit word to octets in little endian format
	public static function w2b($word) {
		return [($word & 0xFF), ($word >> 8)];
	}

	// Convert octets in little endian format to a 16-bit word
	// lsb can be the lsb of an array of [lsb,msb]
	public static function b2w($lsb, $msb = null) {
		if (is_array($lsb)) {
			$msb = $lsb[1];
			$lsb = $lsb[0];
		}

		return $lsb + ($msb << 8);
	}

	// Pack an array
	public static function array_pack($format, array $array) {
		array_unshift($array, $format);

		return call_user_func_array('pack', $array);
	}
}
