<?php

/** Enable login for password-less database
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginPasswordLess {
	/** @access protected */
	var $password_hash;
	
	/** Set allowed password
	* @param string result of password_hash
	*/
	function __construct($password_hash) {
		$this->password_hash = $password_hash;
	}

	function credentials() {
		$password = get_password();
		return array(SERVER, $_GET["username"], (password_verify($password, $this->password_hash) ? "" : $password));
	}
	
	function login($login, $password) {
		if ($password != "") {
			return true;
		}
	}

}
