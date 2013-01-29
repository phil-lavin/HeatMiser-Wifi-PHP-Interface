<?php

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
