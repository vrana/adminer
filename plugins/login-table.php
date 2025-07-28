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

/** Authenticate a user from the "login" table
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginTable extends Adminer\Plugin {
	protected $database;

	/** Set database of login table */
	function __construct($database) {
		$this->database = $database;
	}

	function login($login, $password) {
		return (bool) Adminer\get_val("SELECT COUNT(*) FROM " . Adminer\idf_escape($this->database) . ".login WHERE login = " . Adminer\q($login) . " AND password_sha1 = " . Adminer\q(sha1($password)));
	}

	protected $translations = array(
		'cs' => array('' => 'Autentizace uživatele podle tabulky "login"'),
		'de' => array('' => 'Authentifizieren Sie einen Benutzer über die Tabelle "login"'),
		'pl' => array('' => 'Uwierzytelnij użytkownika z tabeli "login"'),
		'ro' => array('' => 'Autentificați un utilizator din tabelul "login"'),
		'ja' => array('' => '"login" テーブルによるユーザ認証'),
	);
}
