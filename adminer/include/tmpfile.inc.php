<?php

class TmpFile {
	var $handler;
	var $size;
	
	function TmpFile() {
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
