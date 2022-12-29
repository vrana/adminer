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

	function __destruct() {
		if (!function_exists('tmpfile')) {
			$temp_file_metadata = stream_get_meta_data ( $this->handler );
			unlink ($temp_file_metadata['uri']);
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
