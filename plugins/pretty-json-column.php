<?php

/** Pretty print JSON values in edit
* @link https://www.adminer.org/plugins/#use
* @author Christopher Chen
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPrettyJsonColumn extends Adminer\Plugin {
	private function testJson($value) {
		if ((substr($value, 0, 1) == '{' || substr($value, 0, 1) == '[') && ($json = json_decode($value, true))) {
			return $json;
		}
		return $value;
	}

	function editInput($table, $field, $attrs, $value) {
		$json = $this->testJson($value);
		if ($json !== $value) {
			$jsonText = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			return "<textarea$attrs cols='50' rows='20' class='jush-js'>" . Adminer\h($jsonText) . "</textarea>";
		}
	}

	function processInput($field, $value, $function = '') {
		if ($function === '') {
			$json = $this->testJson($value);
			if ($json !== $value) {
				$value = json_encode($json);
			}
		}
	}

	protected $translations = array(
		'cs' => array('' => 'V editaci zobrazí syntaxi u JSONu'),
		'de' => array('' => 'JSON-Werte in der Bearbeitung hübsch drucken'),
		'pl' => array('' => 'Ładnie drukuj wartości JSON w edycji'),
		'ro' => array('' => 'Afisare frumoasa a valorilor JSON în editare'),
		'ja' => array('' => '編集時に JSON 文字列を見易く表示'),
	);
}
