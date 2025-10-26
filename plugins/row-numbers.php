<?php

/** Display row numbers in select
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerRowNumbers extends Adminer\Plugin {

	function backwardKeys($table, $tableName) {
		return array(1);
	}

	function backwardKeysPrint($backwardKeys, $row) {
		static $n;
		if (!$n) {
			$n = $_GET["page"] * Adminer\adminer()->selectLimitProcess();
		}
		$n++;
		echo "$n.\n";
	}

	protected $translations = array(
		'cs' => array('' => 'Zobrazí čísla řádek ve výpisu'),
	);
}
