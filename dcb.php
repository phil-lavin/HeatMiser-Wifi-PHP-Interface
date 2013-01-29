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

	public function __construct($raw) {
		$this->raw = $raw;

		$this->parse();
	}

	protected function parse() {
		// Stuff
	}

	public function get_raw() {
		// Stuff
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
