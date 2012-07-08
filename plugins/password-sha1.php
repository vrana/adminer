<?php

/** Store password's SHA1 to session
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPasswordSha1 {
	/** @access protected */
	var $login, $passwordSha1, $credentials;
	
	/**
	* @param string
	* @param string
	* @param array returned by credentials()
	*/
	function AdminerPasswordSha1($login, $passwordSha1, $credentials) {
		$this->login = $login;
		$this->passwordSha1 = $passwordSha1;
		$this->credentials = $credentials;
		if (isset($_POST["auth"])) {
			$_POST["auth"]["password"] = sha1($_POST["auth"]["password"]);
		}
	}
	
	function login($login, $password) {
		return ($login == $this->login && $password == $this->passwordSha1);
	}
	
	function credentials() {
		return $this->credentials;
	}
	
	function permanentLogin() {
		//! should save original $_POST["auth"]["password"] and hash after load
	}
	
}
