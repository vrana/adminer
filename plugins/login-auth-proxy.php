<?php

/** Log in automatically to a fixed server behind an authenticating proxy which sends the user in a header
* Adminer must be reachable only through the proxy, which must set or remove the header on every request, including its variants with underscores.
* A server variable set by the web server, such as REMOTE_USER, can't come from the client.
* All users share the database account, so limit it by GRANT; $database only limits the list of databases and the URL.
* Log out through the proxy, e.g. /oauth2/sign_out; Adminer's logout logs in again.
* Use it as the only login plugin.
* @link https://www.adminer.org/plugins/#use
* @author Olof Mattsson, https://github.com/omattsson
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginAuthProxy extends Adminer\Plugin {
	protected $key, $driver, $server, $username, $password, $database;
	private $posted = false;

	/**
	* @param string $header request header with the user, e.g. "X-Auth-Request-Email" from oauth2-proxy, or a server variable, e.g. "REMOTE_USER"
	* @param string $driver note that the driver for MySQL is called 'server'
	* @param string $server
	* @param string $username
	* @param string $password
	* @param string $database "" to list all databases
	*/
	function __construct($header, $driver, $server, $username, $password, $database = "") {
		$this->key = (preg_match('~^[A-Z][A-Z0-9_]*$~', $header) ? $header
			: (preg_match('~^[A-Za-z0-9-]+$~', $header) ? "HTTP_" . strtoupper(str_replace("-", "_", $header)) : "")
		);
		$this->driver = $driver;
		$this->server = $server;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		unset($_POST["auth"]); // this plugin prints no login form, so only another website could post it
		$user = $this->proxyUser();
		if ($user != "") {
			if ($_SESSION["login_auth_proxy"] !== $user) {
				$_SESSION = array("login_auth_proxy" => $user); // another user in the same browser doesn't get the session of the previous one
			}
			// isset() tells the stored "" of a login from the null of a failed one
			if (!$_POST && ($_GET["username"] !== $username || !isset($_SESSION["pwds"][$driver][$server][$username]))) {
				// the same request as the login form; credentials() adds the password, so the session doesn't keep it
				$_POST["auth"] = array(
					"driver" => $driver,
					"server" => $server,
					"username" => $username,
					"password" => "",
					"db" => $database,
				);
				$this->posted = true;
			}
		}
	}

	function credentials() {
		// the URL can name another driver or server, send the password only to the configured one
		return array($this->server, $this->username, ($this->configured($_GET["username"]) ? $this->password : ""));
	}

	function bruteForceKey() {
		return $_SERVER["REMOTE_ADDR"] . " " . $this->proxyUser(); // all users come from the address of the proxy
	}

	function verifyLoginToken() {
		if ($this->posted) {
			return false; // the automatic login has no token
		}
	}

	function login($login, $password) {
		// called on every request, so a session doesn't outlive the proxy
		if ($this->proxyUser() == "") {
			return $this->lang('Log in through the authenticating proxy.');
		}
		return ($this->configured($login) && ($this->database == "" || Adminer\DB == "" || Adminer\DB === $this->database));
	}

	function databases($flush = true) {
		if ($this->database != "") {
			return array($this->database);
		}
	}

	function loginForm() {
		echo "<p>" . ($this->proxyUser() == "" ? $this->lang('Log in through the authenticating proxy.') : $this->lang('The automatic login failed.')) . "\n";
		return true;
	}

	/** Check if the request uses the configured driver, server and user */
	private function configured($login) {
		return (Adminer\DRIVER === $this->driver && Adminer\SERVER === $this->server && $login === $this->username);
	}

	/** Get the user sent by the proxy, "" without the proxy */
	private function proxyUser() {
		return ($this->key != "" ? trim(strval($_SERVER[$this->key])) : "");
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Automaticky přihlásí k pevně danému serveru za ověřující proxy, která posílá uživatele v hlavičce', // Claude Opus 5.5
			'Log in through the authenticating proxy.' => 'Přihlaste se přes ověřující proxy.', // Claude Opus 5.5
			'The automatic login failed.' => 'Automatické přihlášení selhalo.', // Claude Opus 5.5
		),
		'de' => array(
			'' => 'Automatische Anmeldung an einem festen Server hinter einem authentifizierenden Proxy, der den Benutzer in einem Header sendet', // Claude Opus 5.5
			'Log in through the authenticating proxy.' => 'Melden Sie sich über den authentifizierenden Proxy an.', // Claude Opus 5.5
			'The automatic login failed.' => 'Die automatische Anmeldung ist fehlgeschlagen.', // Claude Opus 5.5
		),
	);
}
