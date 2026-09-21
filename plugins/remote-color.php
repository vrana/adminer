<?php

/** Warn with a red strip if Adminer or the database does not run on the local machine
* @link https://github.com/dg/adminer/blob/master/adminer-plugins/remoteColor.php original idea by David Grudl
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerRemoteColor extends Adminer\Plugin {
	/** @var list<string> */ private $localHosts = array('', 'localhost', '::1');
	/** @var string */ private $color;
	/** @var ?bool */ private $remote;

	/**
	* @param list<string> $localHosts hosts to also consider local, e.g. a development database or a Docker gateway
	* @param string $color
	*/
	function __construct(array $localHosts = array(), $color = '#dd1818') {
		$this->localHosts = array_merge($this->localHosts, array_map('strtolower', $localHosts));
		$this->color = $color;
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
body.remote, body.remote #breadcrumb { border-top: 5px solid $this->color; }
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
			if (!$this->remote && class_exists('Adminer\Db') && Adminer\connection()) { // credentials() can be useless before connecting
				$credentials = Adminer\adminer()->credentials();
				$parts = Adminer\parse_server($credentials[0]);
				$this->remote = !$this->isLocal($parts ? $parts["host"] : "");
			}
		}
		return $this->remote;
	}

	/** @return bool */
	private function isLocal($host) {
		$host = strval($host);
		return in_array(strtolower($host), $this->localHosts)
			|| preg_match('~^(127\.|/)~', $host) // loopback, socket directory
		;
	}

	protected $translations = array(
		'cs' => array('' => 'Upozorní červeným pruhem, pokud Adminer nebo databáze neběží na lokálním počítači'),
		'de' => array('' => 'Warnt mit einem roten Streifen, wenn Adminer oder die Datenbank nicht auf dem lokalen Rechner läuft'), // Claude Opus 5
		'pl' => array('' => 'Ostrzega czerwonym paskiem, jeśli Adminer lub baza danych nie działa na lokalnym komputerze'), // Claude Opus 5
		'ro' => array('' => 'Avertizează printr-o bandă roșie dacă Adminer sau baza de date nu rulează pe mașina locală'), // Claude Opus 5
		'ja' => array('' => 'Adminer またはデータベースがローカルマシンで動作していない場合に赤い帯で警告'), // Claude Opus 5
		'sk' => array('' => 'Upozorní červeným pruhom, ak Adminer alebo databáza nebeží na lokálnom počítači'), // Claude Opus 5
		'zh' => array('' => 'Adminer 或数据库不在本机运行时，用红色条提示'), // Claude Opus 5
	);
}
