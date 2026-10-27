<?php

/** Log all queries to SQL file
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlLog extends Adminer\Plugin {
	protected $filename, $all;
	private $pending = array(); // log entries waiting for the file name
	private $resolving = false;

	/**
	* @param string $filename defaults to "adminer-$database.sql" in the temp directory
	* @param bool $all log every query sent to the server with its duration including the internal ones, PHP 7.3+
	*/
	function __construct($filename = "", $all = false) {
		$this->filename = $filename;
		$this->all = $all;
	}

	function executeQuery($query, $callback) {
		if (!$this->all) {
			return null;
		}
		$start = hrtime(true);
		try {
			$return = $callback();
		} catch (Throwable $ex) { // plugin drivers may throw
			$this->logTime($query, $start, true);
			throw $ex;
		}
		$this->logTime($query, $start, !$return);
		return $return;
	}

	function messageQuery($query, $time, $failed = false) {
		if (!$this->all) {
			$this->log($query);
		}
	}

	function sqlCommandQuery($query) {
		if (!$this->all) {
			$this->log($query);
		}
	}

	private function logTime($query, $start, $failed) {
		$query = rtrim($query);
		$this->log((preg_match('~;$~', $query) ? $query : "$query;") . sprintf(" -- %.3f ms", (hrtime(true) - $start) / 1e6) . ($failed ? ", failed" : ""));
	}

	private function log($query) {
		$this->pending[] = "$query\n\n";
		if ($this->filename == "") {
			if ($this->resolving) {
				return; // database() in Adminer Editor sends queries
			}
			$this->resolving = true;
			$database = Adminer\adminer()->database();
			$this->resolving = false;
			if ($database === null) {
				return; // Adminer Editor knows the database only after connecting
			}
			// the directory of the script should not be writable by the web server at all and a relative name would create the log there, where anyone could download it
			// no database goes to "adminer-.sql" to avoid collisions
			$this->filename = Adminer\get_temp_dir() . "/adminer-" . urlencode($database . ($_GET["ns"] != "" ? ".$_GET[ns]" : "")) . ".sql";
		}
		$fp = Adminer\file_open_lock($this->filename); // it also refuses a symlink and doesn't make the file readable by everyone
		if ($fp) {
			fseek($fp, 0, SEEK_END);
			fwrite($fp, implode($this->pending));
			Adminer\file_unlock($fp);
		}
		$this->pending = array();
	}

	protected $translations = array(
		'cs' => array('' => 'Zaznamenává všechny příkazy do souboru SQL'),
		'de' => array('' => 'Protokollieren Sie alle Abfragen in einer SQL-Datei'),
		'hr' => array('' => 'Bilježi sve upite u SQL datoteku'),
		'ja' => array('' => '全クエリを SQL ファイルに記録'),
		'pl' => array('' => 'Rejestruj wszystkie zapytania do pliku SQL'),
		'ro' => array('' => 'Logați toate interogările în fișierul SQL'),
		'sk' => array('' => 'Zaznamenáva všetky príkazy do súboru SQL'), // Claude Opus 5
		'zh' => array('' => '把所有查询记录到 SQL 文件'), // Claude Opus 5
	);
}
