<?php
/* Requires this table:
CREATE TABLE login (
	id int NOT NULL AUTO_INCREMENT, -- optional
	login varchar(30) NOT NULL, -- any length
	password_sha1 char(40) NOT NULL,
	UNIQUE (login),
	PRIMARY KEY (id)
);
*/

/** Authenticate a user from the login table
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginTable {
	/** @access protected */
	var $database;
	
	/** Set database of login table
	* @param string
	*/
	function __construct($database) {
		$this->database = $database;
	}
	
	function login($login, $password) {
		$connection = connection();
		return (bool) $connection->result("SELECT COUNT(*) FROM " . idf_escape($this->database) . ".login WHERE login = " . q($login) . " AND password_sha1 = " . q(sha1($password)));
	}
	
}
