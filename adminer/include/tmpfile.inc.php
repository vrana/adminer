<?php
namespace Adminer;

class TmpFile {
	/** @var resource */ private $handler;
	/** @visibility protected(set) */ public int $size;

	function __construct() {
		$this->handler = tmpfile();
	}

	function write(string $contents): void {
		$this->size += strlen($contents);
		fwrite($this->handler, $contents);
	}

	function send(): void {
		fseek($this->handler, 0);
		fpassthru($this->handler);
		fclose($this->handler);
	}
}
