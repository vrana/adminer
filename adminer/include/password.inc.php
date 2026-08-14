<?php
namespace Adminer;

/** Require a password verified by Adminer
* Instantiate it in adminer-plugins.php, it must not extend Plugin - Plugins would ask to configure it.
*/
class Password {
	private string $password_hash;
	private ?bool $password_matches = null;

	/** @param string $password_hash result of password_hash() */
	function __construct(string $password_hash) {
		$this->password_hash = $password_hash;
	}

	/** Get plain text description printed in the list of plugins */
	function description(): string {
		return lang('Require a password verified by Adminer');
	}

	/** @return array{string, string, string} */
	function credentials(): array {
		$password = get_password();
		// the server doesn't know our password so don't send it - unless the server requires a password, it can be even the same one
		return array(SERVER, $_GET["username"], ($this->passwordMatches($password) && !password_required() ? "" : $password));
	}

	/** @return true|void */
	function login(string $login, string $password) {
		if ($this->passwordMatches($password)) {
			return true; // we have verified the password ourselves
		}
	}

	/** Check if the password is the one allowed by this class
	* @param string|false|null $password result of get_password()
	*/
	protected function passwordMatches($password): bool {
		if ($this->password_matches === null) {
			// password_verify() is PHP 5.5 and slow by design, the compiled file supports 5.3
			$this->password_matches = (function_exists('password_verify') && password_verify(strval($password), $this->password_hash));
		}
		return $this->password_matches;
	}
}
