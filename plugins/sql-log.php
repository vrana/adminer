<?php

/** Log all queries to SQL file
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlLog extends Adminer\Plugin {
	protected $filename;

	/**
	* @param string $filename defaults to "adminer-$database.sql" in the temp directory
	*/
	function __construct($filename = "") {
		$this->filename = $filename;
	}

	function messageQuery($query, $time, $failed = false) {
		$this->log($query);
	}

	function sqlCommandQuery($query) {
		$this->log($query);
	}

	private function log($query) {
		if ($this->filename == "") {
			// the directory of the script should not be writable by the web server at all and a relative name would create the log there, where anyone could download it
			// no database goes to "adminer-.sql" to avoid collisions
			$this->filename = Adminer\get_temp_dir() . "/adminer-" . urlencode(Adminer\adminer()->database() . ($_GET["ns"] != "" ? ".$_GET[ns]" : "")) . ".sql";
		}
		$fp = Adminer\file_open_lock($this->filename); // it also refuses a symlink and doesn't make the file readable by everyone
		if ($fp) {
			fseek($fp, 0, SEEK_END);
			fwrite($fp, "$query\n\n");
			Adminer\file_unlock($fp);
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Zaznamenává všechny příkazy do souboru SQL'),
		'de' => array('' => 'Protokollieren Sie alle Abfragen in einer SQL-Datei'),
		'pl' => array('' => 'Rejestruj wszystkie zapytania do pliku SQL'),
		'ro' => array('' => 'Logați toate interogările în fișierul SQL'),
		'ja' => array('' => '全クエリを SQL ファイルに記録'),
		'hr' => array('' => 'Bilježi sve upite u SQL datoteku'),
	);
}
