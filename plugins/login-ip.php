<?php

/** Check the IP address and allow an empty password
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginIp extends Adminer\Plugin {
	protected $ips, $forwarded_for;

	/** Set allowed IP addresses
	* @param list<string> $ips IP address prefixes
	* @param list<string> $forwarded_for X-Forwarded-For prefixes if IP address matches, empty array means that the request must not be proxied
	*/
	function __construct(array $ips = array('127.', '::1'), array $forwarded_for = array()) {
		$this->ips = $ips;
		$this->forwarded_for = $forwarded_for;
	}

	function login($login, $password) {
		// a proxy appends the client to X-Forwarded-For, the preceding values are sent by the client itself
		$forwarded_for = preg_replace('~.*, *~', '', strval($_SERVER["HTTP_X_FORWARDED_FOR"]));
		return $this->matchPrefix($_SERVER["REMOTE_ADDR"], $this->ips)
			&& ($this->forwarded_for
				? $this->matchPrefix($forwarded_for, $this->forwarded_for)
				: $forwarded_for == "" // an unexpected proxy would pass requests from anywhere
			);
	}

	/** Check if the value begins with one of the prefixes
	* @param list<string> $prefixes
	* @return bool
	*/
	private function matchPrefix($value, array $prefixes) {
		foreach ($prefixes as $prefix) {
			if (strncasecmp(strval($value), $prefix, strlen($prefix)) == 0) {
				return true;
			}
		}
		return false;
	}

	protected $translations = array(
		'cs' => array('' => 'Zkontroluje IP adresu a povolí prázdné heslo'),
		'de' => array('' => 'Überprüft die IP-Adresse und lässt ein leeres Passwort zu'),
		'pl' => array('' => 'Sprawdzaj adres IP i zezwakaj na puste hasło'),
		'ro' => array('' => 'Verificați adresa IP și permiteți parola goală'),
		'ja' => array('' => 'IP アドレスの確認、及び空パスワードの許可'),
		'hr' => array('' => 'Provjerava IP adresu i dopušta praznu lozinku'),
	);
}
