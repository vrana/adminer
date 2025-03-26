<?php

/** Display links to tables referencing current row, same as in Adminer Editor
* @link https://www.adminer.org/static/plugins/backward-keys.png
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerBackwardKeys {
	// this is copy-pasted from Adminer Editor

	function backwardKeys($table, $tableName) {
		$return = array();
		foreach (
			Adminer\get_rows($q = "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = " . Adminer\q(Adminer\DB) . "
AND REFERENCED_TABLE_SCHEMA = " . Adminer\q(Adminer\DB) . "
AND REFERENCED_TABLE_NAME = " . Adminer\q($table) . "
ORDER BY ORDINAL_POSITION", null, "") as $row
		) {
			$return[$row["TABLE_NAME"]]["keys"][$row["CONSTRAINT_NAME"]][$row["COLUMN_NAME"]] = $row["REFERENCED_COLUMN_NAME"];
		}
		foreach ($return as $key => $val) {
			$name = Adminer\adminer()->tableName(Adminer\table_status1($key, true));
			if ($name != "") {
				$search = preg_quote($tableName);
				$separator = '(:|\s*-)?\s+';
				$return[$key]["name"] = (preg_match("(^$search$separator(.+)|^(.+?)$separator$search\$)iu", $name, $match) ? $match[2] . $match[3] : $name);
			} else {
				unset($return[$key]);
			}
		}
		return $return;
	}

	function backwardKeysPrint($backwardKeys, $row) {
		foreach ($backwardKeys as $table => $backwardKey) {
			foreach ($backwardKey["keys"] as $cols) {
				$link = Adminer\ME . 'select=' . urlencode($table);
				$i = 0;
				foreach ($cols as $column => $val) {
					$link .= Adminer\where_link($i++, $column, $row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "'>" . Adminer\h($backwardKey["name"]) . "</a>";
				$link = Adminer\ME . 'edit=' . urlencode($table);
				foreach ($cols as $column => $val) {
					$link .= "&set" . urlencode("[" . Adminer\bracket_escape($column) . "]") . "=" . urlencode($row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "' title='" . Adminer\lang('New item') . "'>+</a> ";
			}
		}
	}
}
