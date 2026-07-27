<?php

/** Enable login without password
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginPasswordLess extends Adminer\Plugin {
	protected $password_hash;
	protected $password_matches;

	/** Set allowed password
	* @param string $password_hash result of password_hash()
	*/
	function __construct($password_hash) {
		$this->password_hash = $password_hash;
	}

	function credentials() {
		$password = Adminer\get_password();
		// the server doesn't know our password so don't send it - unless the server requires a password, it can be even the same one
		return array(Adminer\SERVER, $_GET["username"], ($this->passwordMatches($password) && !Adminer\password_required() ? "" : $password));
	}

	function login($login, $password) {
		if ($this->passwordMatches($password)) {
			return true; // we have verified the password ourselves
		}
	}

	/** Check if the password is the one allowed by this plugin
	* @param string $password
	* @return bool
	*/
	protected function passwordMatches($password) {
		if ($this->password_matches === null) {
			$this->password_matches = password_verify($password, $this->password_hash); // password_verify() is slow by design
		}
		return $this->password_matches;
	}

	protected $translations = array(
		'cs' => array('' => 'Povolí přihlášení bez hesla'),
		'de' => array('' => 'Ermöglicht die Anmeldung ohne Passwort'),
		'pl' => array('' => 'Włącz logowanie bez hasła'),
		'ro' => array('' => 'Activați autentificarea fără parolă'),
		'ja' => array('' => 'パスワードなしのログインを許可'),
		'hr' => array('' => 'Omogućuje prijavu bez lozinke'),
	);
}
