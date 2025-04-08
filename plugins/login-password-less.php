<?php

/** Enable login without password
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginPasswordLess extends Adminer\Plugin {
	protected $password_hash;

	/** Set allowed password
	* @param string $password_hash result of password_hash()
	*/
	function __construct(string $password_hash) {
		$this->password_hash = $password_hash;
	}

	function credentials() {
		$password = Adminer\get_password();
		return array(Adminer\SERVER, $_GET["username"], (password_verify($password, $this->password_hash) ? "" : $password));
	}

	function login($login, $password) {
		if ($password != "") {
			return true;
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Povolí přihlášení bez hesla'),
		'de' => array('' => 'Ermöglicht die Anmeldung ohne Passwort'),
		'pl' => array('' => 'Włącz logowanie bez hasła'),
		'ro' => array('' => 'Activați autentificarea fără parolă'),
		'ja' => array('' => 'パスワードなしのログインを許可'),
	);
}
