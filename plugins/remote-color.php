<?php

/** Warn with a red strip if Adminer or the database does not run on the local machine
* @link https://github.com/dg/adminer/blob/master/adminer-plugins/remoteColor.php original idea by David Grudl
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerRemoteColor extends Adminer\Plugin {
	static $optional = true;

	/** @var list<string> */ private $localHosts;
	/** @var string */ private $color;
	/** @var ?bool */ private $remote;

	/**
	* @param list<string> $localHosts hosts to also consider local, e.g. a development database or a Docker gateway; configurable with AdminerConfig
	* @param string $color configurable with AdminerConfig if in the #rrggbb format
	*/
	function __construct(array $localHosts = array(), $color = '#dd1818') {
		$this->localHosts = $localHosts;
		$this->color = $color;
	}

	function config() {
		// AdminerConfig prints the rows in the reverse order
		$return = array(
			$this->lang('Hosts considered local') => "<textarea name='config[remote_hosts]' rows='4'>" . Adminer\h(implode("\n", $this->localHosts())) . "\n</textarea>",
		);
		$color = $this->color();
		if (preg_match('~^#[0-9a-f]{6}$~i', $color)) { // <input type="color"> would save another color as black
			$return[$this->lang('Color of the remote strip')] = '<input type="color" name="config[remote_color]" value="' . Adminer\h($color) . '">';
		}
		return $return;
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
body.remote, body.remote #breadcrumb { border-top: 5px solid " . $this->color() . "; }
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
		return in_array(strtolower($host), array_merge(array('', 'localhost', '::1'), array_map('strtolower', $this->localHosts())))
			|| preg_match('~^(127\.|/)~', $host) // loopback, socket directory
		;
	}

	/** Get the hosts to also consider local
	* @return list<string>
	*/
	private function localHosts() {
		$hosts = Adminer\get_setting("remote_hosts", "adminer_config");
		return (is_string($hosts) ? preg_split('~\s*\n~', trim($hosts)) : $this->localHosts);
	}

	/** Get the color of the strip
	* @return string
	*/
	private function color() {
		$color = Adminer\get_setting("remote_color", "adminer_config");
		return (is_string($color) && preg_match('~^#[0-9a-f]{6}$~i', $color) ? $color : $this->color); // the cookie can't inject CSS
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Upozorní červeným pruhem, pokud Adminer nebo databáze neběží na lokálním počítači',
			'Color of the remote strip' => 'Barva pruhu u vzdáleného připojení',
			'Hosts considered local' => 'Adresy považované za lokální',
		),
		'de' => array(
			'' => 'Warnt mit einem roten Streifen, wenn Adminer oder die Datenbank nicht auf dem lokalen Rechner läuft', // Claude Opus 5
			'Color of the remote strip' => 'Farbe des Streifens bei einer entfernten Verbindung', // Claude Opus 5.5
			'Hosts considered local' => 'Als lokal betrachtete Hosts', // Claude Opus 5.5
		),
		'ja' => array(
			'' => 'Adminer またはデータベースがローカルマシンで動作していない場合に赤い帯で警告', // Claude Opus 5
			'Color of the remote strip' => 'リモート接続時の帯の色', // Claude Opus 5.5
			'Hosts considered local' => 'ローカルとみなすホスト', // Claude Opus 5.5
		),
		'pl' => array(
			'' => 'Ostrzega czerwonym paskiem, jeśli Adminer lub baza danych nie działa na lokalnym komputerze', // Claude Opus 5
			'Color of the remote strip' => 'Kolor paska przy zdalnym połączeniu', // Claude Opus 5.5
			'Hosts considered local' => 'Hosty uznawane za lokalne', // Claude Opus 5.5
		),
		'ro' => array(
			'' => 'Avertizează printr-o bandă roșie dacă Adminer sau baza de date nu rulează pe mașina locală', // Claude Opus 5
			'Color of the remote strip' => 'Culoarea benzii la o conexiune la distanță', // Claude Opus 5.5
			'Hosts considered local' => 'Gazde considerate locale', // Claude Opus 5.5
		),
		'sk' => array(
			'' => 'Upozorní červeným pruhom, ak Adminer alebo databáza nebeží na lokálnom počítači', // Claude Opus 5
			'Color of the remote strip' => 'Farba pruhu pri vzdialenom pripojení', // Claude Opus 5.5
			'Hosts considered local' => 'Adresy považované za lokálne', // Claude Opus 5.5
		),
		'zh' => array(
			'' => 'Adminer 或数据库不在本机运行时，用红色条提示', // Claude Opus 5
			'Color of the remote strip' => '远程连接时提示条的颜色', // Claude Opus 5.5
			'Hosts considered local' => '视为本地的主机', // Claude Opus 5.5
		),
	);
}
