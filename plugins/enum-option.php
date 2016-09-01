<?php

/** Use <select><option> for enum edit instead of <input type="radio">
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEnumOption {
	
	function editInput($table, $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			$options = array("" => array());
			$selected = $value;
			if (isset($_GET["select"])) {
				$options[""][-1] = lang('original');
			}
			if ($field["null"]) {
				$options[""][""] = "NULL";
				if ($value === null && !isset($_GET["select"])) {
					$selected = "";
				}
			}
			$options[""][0] = lang('empty');
			preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $i => $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$options[$i + 1] = $val;
				if ($value === $val) {
					$selected = $i + 1;
				}
			}
			return "<select$attrs>" . optionlist($options, (string) $selected, 1) . "</select>"; // 1 - use keys
		}
	}
	
}
