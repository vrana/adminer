<?php

/** Execute writes on master and reads on slave
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMasterSlave extends Adminer\Plugin {
	private $masters = array();

	/**
	* @param string[] $masters [$slave => $master]
	*/
	function __construct(array $masters) {
		$this->masters = $masters;
	}

	function credentials() {
		if ($_POST && isset($this->masters[Adminer\SERVER])) {
			return array($this->masters[Adminer\SERVER], $_GET["username"], Adminer\get_session("pwds"));
		}
	}

	function login($login, $password) {
		if (!$_POST && ($master = &$_SESSION["master"])) {
			Adminer\connection()->query("DO MASTER_POS_WAIT('" . Adminer\q($master['File']) . "', $master[Position])");
			$master = null;
		}
	}

	function messageQuery($query, $time, $failed = false) {
		//! doesn't work with sql.inc.php
		$result = Adminer\connection()->query('SHOW MASTER STATUS');
		if ($result) {
			Adminer\restart_session();
			$_SESSION["master"] = $result->fetch_assoc();
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Zápisy provádět na masteru a čtení na slave'),
		'de' => array('' => 'Schreibvorgänge auf dem Master und Lesevorgänge auf dem Slave ausführen'),
		'pl' => array('' => 'Wykonuje zapisy na komputerze głównym i odczyty na komputerze podrzędnym'),
		'ro' => array('' => 'Executarea scrierilor pe master și a citirilor pe slave'),
		'ja' => array('' => 'マスタ書込みとスレーブ読込みの有効化'),
	);
}
