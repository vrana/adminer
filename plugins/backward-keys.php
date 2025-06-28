<?php

/** Display links to tables referencing current row, same as in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerBackwardKeys extends Adminer\Plugin {
	// this is copy-pasted from Adminer Editor

	function backwardKeys($table, $tableName) {
		$return = array();
		// we couldn't use the same query in MySQL and PostgreSQL because unique_constraint_name is not table-specific in MySQL and referenced_table_name is not available in PostgreSQL
		foreach (
			Adminer\get_rows("SELECT s.table_name table_name, s.constraint_name constraint_name, s.column_name column_name, " . (Adminer\JUSH == "sql" ? "referenced_column_name" : "t.column_name") . " referenced_column_name
FROM information_schema.key_column_usage s" . (Adminer\JUSH == "sql" ? "
WHERE table_schema = " . Adminer\q(Adminer\DB) . "
AND referenced_table_schema = " . Adminer\q(Adminer\DB) . "
AND referenced_table_name" : "
JOIN information_schema.referential_constraints r USING (constraint_catalog, constraint_schema, constraint_name)
JOIN information_schema.key_column_usage t ON r.unique_constraint_catalog = t.constraint_catalog
	AND r.unique_constraint_schema = t.constraint_schema
	AND r.unique_constraint_name = t.constraint_name
	AND r.constraint_catalog = t.constraint_catalog
	AND r.constraint_schema = t.constraint_schema
	AND r.unique_constraint_name = t.constraint_name
	AND s.position_in_unique_constraint = t.ordinal_position
WHERE t.table_catalog = " . Adminer\q(Adminer\DB) . " AND t.table_schema = " . Adminer\q("$_GET[ns]") . "
AND t.table_name") . " = " . Adminer\q($table) . "
ORDER BY s.ordinal_position", null, "") as $row
		) {
			$return[$row["table_name"]]["keys"][$row["constraint_name"]][$row["column_name"]] = $row["referenced_column_name"];
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
					if (!isset($row[$val])) {
						continue 2;
					}
					$link .= Adminer\where_link($i++, $column, $row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "'>" . Adminer\h(preg_replace('(^' . preg_quote($_GET["select"]) . (substr($_GET["select"], -1) == 's' ? '?' : '') . '_)', '_', $backwardKey["name"])) . "</a>";
				$link = Adminer\ME . 'edit=' . urlencode($table);
				foreach ($cols as $column => $val) {
					$link .= "&set" . urlencode("[" . Adminer\bracket_escape($column) . "]") . "=" . urlencode($row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "' title='" . Adminer\lang('New item') . "'>+</a> ";
			}
		}
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/backward-keys.png";
	}

	protected $translations = array(
		'cs' => array('' => 'Zobrazí odkazy na tabulky odkazující aktuální řádek, stejně jako Adminer Editor'),
		'de' => array('' => 'Links zu Tabellen anzeigen die auf die aktuelle Zeile verweisen, wie im Adminer Editor'),
		'ja' => array('' => 'Adminer Editor と同様に、カレント行を参照しているテーブルへのリンクを表示'),
		'pl' => array('' => 'Wyświetlaj linki do tabel odnoszących się do bieżącego wiersza, tak samo jak w Edytorze administratora'),
	);
}
