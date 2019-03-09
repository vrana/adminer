<?php

/** Execute writes on master and reads on slave
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMasterSlave {
	private $masters = array();
	
	/**
	* @param array ($slave => $master)
	*/
	function __construct($masters) {
		$this->masters = $masters;
	}
	
	function credentials() {
		if ($_POST && isset($this->masters[SERVER])) {
			return array($this->masters[SERVER], $_GET["username"], get_session("pwds"));
		}
	}
	
	function login($login, $password) {
		if (!$_POST && ($master = &$_SESSION["master"])) {
			$connection = connection();
			$connection->query("DO MASTER_POS_WAIT('" . q($master['File']) . "', $master[Position])");
			$master = null;
		}
	}

	function messageQuery($query, $time, $failed = false) {
		//! doesn't work with sql.inc.php
		$connection = connection();
		$result = $connection->query('SHOW MASTER STATUS');
		if ($result) {
			restart_session();
			$_SESSION["master"] = $result->fetch_assoc();
		}
	}

}
