<?php
namespace Adminer;

class TmpFile {
	/** @var resource */ private $handler;
	/** @var int @visibility protected(set) */ public $size;

	function __construct() {
		$this->handler = tmpfile();
	}

	/**
	* @param string $contents
	* @return void
	*/
	function write($contents) {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}

	/** @return void */
	function send() {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
}
