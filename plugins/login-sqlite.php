<?php

/** Enable login for SQLite
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginSqlite {
	/** @access protected */
	var $login, $password_hash, $known_dbs;
	
	/** Set allowed credentials
	* @param string
	* @param string result of password_hash
	* @param array list of paths to known databases
	*/
	function __construct($login, $password_hash, $known_dbs) {
		$this->login = $login;
		$this->password_hash = $password_hash;
		$this->known_dbs = $known_dbs;
	}
	
	function login($login, $password) {
		if (DRIVER != "sqlite" && DRIVER != "sqlite2") {
			return true;
		}
		return $this->login == $login && password_verify($password, $this->password_hash);
	}
	
	function databases($flush = true) {
		if (isset($_GET['sqlite']) && isset($this->known_dbs)) {
			return $this->known_dbs;
		}
		return get_databases($flush);
	}

}
