<?php

/** Execute writes on master and reads on slave
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMasterSlave {
	private $masters = array();
	
	/**
	* @param array ($slave => $master)
	*/
	function AdminerMasterSlave($masters) {
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

	function messageQuery($query) {
		//! doesn't work with sql.inc.php
		$connection = connection();
		$result = $connection->query('SHOW MASTER STATUS');
		if ($result) {
			restart_session();
			$_SESSION["master"] = $result->fetch_assoc();
		}
	}

}
