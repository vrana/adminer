<?php

class TmpFile {
	var $handler;
	var $size;
	
	function __construct() {
		if (function_exists('tmpfile'))
			$this->handler = tmpfile();
		else {
			$temp_file_template = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$temp_file='';
			for($i=0;$i<20;$i++)
				$temp_file .= $temp_file_template[rand(0,strlen($temp_file_template)-1)];
			$this->handler = fopen($temp_file, "w+");	
		}
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
