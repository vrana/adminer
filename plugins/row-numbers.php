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
		'de' => array('' => 'Zeigt Zeilennummern im Select an'), // Claude Opus 5
		'hr' => array('' => 'Prikazuje brojeve redaka u ispisu'),
		'ja' => array('' => '一覧に行番号を表示'), // Claude Opus 5
		'pl' => array('' => 'Wyświetla numery wierszy w wyniku'), // Claude Opus 5
		'ro' => array('' => 'Afișează numerele rândurilor în select'), // Claude Opus 5
		'sk' => array('' => 'Zobrazí čísla riadkov vo výpise'), // Claude Opus 5
		'zh' => array('' => '在选择数据时显示行号'), // Claude Opus 5
	);
}
