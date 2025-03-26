<?php
namespace Adminer;

class TmpFile {
	private $handler;
	public $size; ///< @visibility protected(set)

	function __construct() {
		$this->handler = tmpfile();
	}

	function write($contents) {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}

	function send() {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
}
