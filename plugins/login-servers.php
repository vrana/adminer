<?php

/** Display constant list of servers in login form
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerLoginServers {
	protected $servers;

	/** Set supported servers
	* @param array{server:string, driver:string}[] $servers [$description => ["server" => , "driver" => "server|pgsql|sqlite|..."]]
	*/
	function __construct(array $servers) {
		$this->servers = $servers;
		if ($_POST["auth"]) {
			$key = $_POST["auth"]["server"];
			$_POST["auth"]["driver"] = $this->servers[$key]["driver"];
		}
	}

	function credentials() {
		return array($this->servers[Adminer\SERVER]["server"], $_GET["username"], Adminer\get_password());
	}

	function login($login, $password) {
		if (!$this->servers[Adminer\SERVER]) {
			return false;
		}
	}

	function loginFormField($name, $heading, $value) {
		if ($name == 'driver') {
			return '';
		} elseif ($name == 'server') {
			return $heading . Adminer\html_select("auth[server]", array_keys($this->servers), Adminer\SERVER) . "\n";
		}
	}
}
