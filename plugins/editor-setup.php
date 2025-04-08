<?php

/** Set up driver, server and database to use with Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditorSetup extends Adminer\Plugin {
	private $driver;
	private $server;
	private $database;

	/**
	* @param string $driver 'server' is MySQL, 'pgsql' is PostgreSQL, ...
	* @param string $server null means the default host, usually localhost
	* @param string $database null is the first available database
	*/
	function __construct($driver = 'server', $server = null, $database = null) {
		$this->driver = $driver;
		$this->server = $server;
		$this->database = $database;
	}

	function loginFormField($name, $heading, $value) {
		if ($name == 'username') {
			return $heading . str_replace("value='server'", "value='$this->driver'", $value) . "\n";
		}
	}

	function credentials() {
		return array($this->server, $_GET["username"], Adminer\get_password());
	}

	function database() {
		if ($this->database) {
			return $this->database;
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Nastavit ovladač, server a databázi pro použití s Adminer Editorem'),
		'de' => array('' => 'Treiber, Server und Datenbank für die Verwendung mit Adminer Editor einrichten'),
		'ja' => array('' => 'Adminer Editor で使用するドライバ、サーバ、データベースを設定'),
		'pl' => array('' => 'Konfiguruj sterownik, serwer i bazę danych do użycia z Adminer Editorem'),
	);
}
