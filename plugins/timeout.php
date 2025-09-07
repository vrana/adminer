<?php

/** Specify timeout for running every query
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTimeout extends Adminer\Plugin {
	private $seconds;

	/**
	* @param int $seconds
	*/
	function __construct($seconds = 5) {
		$this->seconds = $seconds;
	}

	function afterConnect() {
		$seconds = Adminer\get_setting("timeout", "adminer_config", $this->seconds);
		if ($seconds != '') {
			$ms = $seconds * 1000;
			$conn = Adminer\connection();
			switch (Adminer\JUSH) {
				case 'sql':
					$conn->query("SET max_execution_time = $ms");
					break;
				case 'pgsql':
					$conn->query("SET statement_timeout = $ms");
					break;
				case 'mssql':
					$conn->query("SET LOCK_TIMEOUT $ms");
					break;
				default:
					if (method_exists($conn, 'timeout')) {
						$conn->timeout($ms);
					}
			}
		}
	}

	function config() {
		$seconds = Adminer\get_setting("timeout", "adminer_config", $this->seconds);
		return array($this->lang('Queries timeout') => '<input type="number" name="config[timeout]" min="0" value="' . Adminer\h($seconds) . '" class="size"> ' . $this->lang('seconds'));
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Nastaví timeout pro spouštění každého dotazu',
			'Queries timeout' => 'Timeout dotazů',
			'seconds' => 'sekund',
		),
	);
}
