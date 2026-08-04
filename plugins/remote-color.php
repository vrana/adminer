<?php

/** Warn by a red strip if Adminer or the database doesn't run on the local machine
* @link https://github.com/dg/adminer/blob/master/adminer-plugins/remoteColor.php original idea by David Grudl
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerRemoteColor extends Adminer\Plugin {
	/** @var list<string> */ private $localHosts = array('', 'localhost', '::1');
	/** @var ?bool */ private $remote;

	/** @param list<string> $localHosts hosts to also consider local, e.g. a development database or a Docker gateway */
	function __construct(array $localHosts = array()) {
		$this->localHosts = array_merge($this->localHosts, array_map('strtolower', $localHosts));
	}

	function bodyClass() {
		if ($this->isRemote()) {
			echo " remote";
		}
	}

	function head($dark = null) {
		if ($this->isRemote()) {
			// #breadcrumb and #menuopen are positioned over the body border
			echo "<style>
body.remote, body.remote #breadcrumb { border-top: 5px solid #dd1818; }
body.remote #menuopen { margin-top: 5px; }
</style>
";
		}
	}

	/** Check if the browser or the database is somewhere else than on the machine running Adminer
	* @return bool
	*/
	private function isRemote() {
		if ($this->remote === null) {
			$this->remote = !$this->isLocal($_SERVER["REMOTE_ADDR"])
				|| $_SERVER["HTTP_X_FORWARDED_FOR"] != "" // the request passed through a proxy
			;
			if (!$this->remote && Adminer\connection()) { // credentials() can be useless before connecting
				$credentials = Adminer\adminer()->credentials();
				$this->remote = !$this->isLocal(Adminer\first(Adminer\host_port($credentials[0])));
			}
		}
		return $this->remote;
	}

	/** @return bool */
	private function isLocal($host) {
		$host = preg_replace('~^[-+.\w]+://~', '', strval($host)); // scheme is used by some plugin drivers
		return in_array(strtolower($host), $this->localHosts)
			|| preg_match('~^(127\.|/)~', $host) // loopback, socket directory
		;
	}

	protected $translations = array(
		'cs' => array('' => 'Upozorní červeným pruhem, pokud Adminer nebo databáze neběží na lokálním počítači'),
	);
}
