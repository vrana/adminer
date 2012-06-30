<?php

/** Log all queries to SQL file (manual queries through SQL command are not logged)
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlLog {
	/** @access protected */
	var $filename;
	
	/**
	* @param string defaults to "$database.sql"
	*/
	function AdminerSqlLog($filename = "") {
		$this->filename = $filename;
	}
	
	function messageQuery($query) {
		if ($this->filename == "") {
			$adminer = adminer();
			$this->filename = $adminer->database() . ".sql"; // no database goes to ".sql" to avoid collisions
		}
		$fp = fopen($this->filename, "a");
		flock($fp, LOCK_EX);
		fwrite($fp, $query);
		fwrite($fp, "\n\n");
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	
}
