<?php

/** Use <select><option> for enum edit instead of <input type="radio">
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEnumOption extends Adminer\Plugin {

	function editInput($table, $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			$options = array();
			$selected = $value;
			if (isset($_GET["select"])) {
				$options[-1] = Adminer\lang('original');
				if ($selected === null) {
					$selected = -1;
				}
			}
			if ($field["null"]) {
				$options[""] = "NULL";
				if ($selected === null) {
					$selected = "";
				}
			}
			preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$options[$val] = $val;
			}
			return "<select$attrs>" . Adminer\optionlist($options, $selected, 1) . "</select>"; // 1 - use keys
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Editace políčka enum pomocí <select><option> místo <input type="radio">'),
		'de' => array('' => 'Verwenden Sie <select><option> für die enum-Bearbeitung anstelle von <input type="radio">'),
		'pl' => array('' => 'Użyj <select><option> do edycji enum zamiast <input type="radio">'),
		'ro' => array('' => 'Utilizați <select><option> pentru editarea enum în loc de <input type="radio">'),
		'ja' => array('' => '列挙型の編集に <input type="radio"> ではなく <select><option> を使用'),
	);
}
