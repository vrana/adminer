<?php

/** Log all queries to SQL file
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlLog {
	/** @var string @access protected */
	var $filename;
	
	/**
	* @param string
	*/
	function AdminerSqlLog($filename = "adminer.sql") {
		$this->filename = $filename;
	}
	
	function messageQuery($query) {
		$fp = fopen($this->filename, "a");
		flock($fp, LOCK_EX);
		fwrite($fp, $query);
		fwrite($fp, "\n\n");
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	
}
