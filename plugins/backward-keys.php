<?php

/** Display links to tables referencing the current row, same as in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerBackwardKeys extends Adminer\Plugin {
	// this is copy-pasted from Adminer Editor

	function backwardKeys($table, $tableName) {
		$return = array();
		if (Adminer\JUSH == "pgsql") { // information_schema is very slow in PostgreSQL with many tables
			$query = "SELECT n.nspname AS ns, r.relname AS table_name, c.conname AS constraint_name, a.attname AS column_name, t.attname AS referenced_column_name
FROM (
	SELECT conrelid, confrelid, conname, conkey, confkey, generate_subscripts(conkey, 1) AS i
	FROM pg_constraint
	WHERE contype = 'f' AND confrelid = " . Adminer\driver()->tableOid($table) . "
) c
JOIN pg_class r ON r.oid = c.conrelid
JOIN pg_namespace n ON n.oid = r.relnamespace
JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[c.i]
JOIN pg_attribute t ON t.attrelid = c.confrelid AND t.attnum = c.confkey[c.i]
ORDER BY c.i";
		} else {
			// we couldn't use the same query in MySQL and MS SQL because unique_constraint_name is not table-specific in MySQL and referenced_table_name is not available in MS SQL
			$query = "SELECT s.table_name table_name, s.constraint_name constraint_name, s.column_name column_name,
	" . (Adminer\JUSH == "sql" ? "referenced_column_name" : "t.column_name") . " referenced_column_name" . (Adminer\JUSH == "sql" ? "" : ", s.table_schema ns") . "
FROM information_schema.key_column_usage s" . (Adminer\JUSH == "sql" ? "
WHERE table_schema = " . Adminer\q(Adminer\DB) . "
AND referenced_table_schema = " . Adminer\q(Adminer\DB) . "
AND referenced_table_name" : "
JOIN information_schema.referential_constraints r USING (constraint_catalog, constraint_schema, constraint_name)
JOIN information_schema.key_column_usage t ON r.unique_constraint_catalog = t.constraint_catalog
	AND r.unique_constraint_schema = t.constraint_schema
	AND r.unique_constraint_name = t.constraint_name
	AND r.constraint_catalog = t.constraint_catalog
	AND r.unique_constraint_name = t.constraint_name
	AND s.position_in_unique_constraint = t.ordinal_position
WHERE t.table_catalog = " . Adminer\q(Adminer\DB) . " AND t.table_schema = " . Adminer\q("$_GET[ns]") . "
AND t.table_name") . " = " . Adminer\q($table) . "
ORDER BY s.ordinal_position";
		}
		foreach (Adminer\get_rows($query, null, "") as $row) {
			$ns = ($row["ns"] != $_GET["ns"] ? $row["ns"] : ""); // ns is not selected in MySQL
			$key = Adminer\idf_escape($ns) . "." . Adminer\idf_escape($row["table_name"]); // the same table name can be in several schemas
			$return[$key]["table"] = $row["table_name"];
			$return[$key]["ns"] = $ns;
			$return[$key]["keys"][$row["constraint_name"]][$row["column_name"]] = $row["referenced_column_name"];
		}
		foreach ($return as $key => $val) {
			// table_status1() looks only in the current schema
			$name = Adminer\adminer()->tableName($val["ns"] != "" ? array("Name" => $val["table"]) : Adminer\table_status1($val["table"], true));
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
		foreach ($backwardKeys as $backwardKey) {
			$table = $backwardKey["table"];
			$ns = $backwardKey["ns"];
			$me = ($ns != "" ? preg_replace('~ns=[^&]*~', "ns=" . Adminer\url_escape($ns), Adminer\ME) : Adminer\ME);
			foreach ($backwardKey["keys"] as $cols) {
				$link = $me . 'select=' . Adminer\url_escape($table);
				$i = 0;
				foreach ($cols as $column => $val) {
					if (!isset($row[$val])) {
						continue 2;
					}
					$link .= Adminer\where_link($i++, $column, $row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "'>"
					. ($ns != "" ? "<b>" . Adminer\h($ns) . "</b>." : "")
					. Adminer\h(preg_replace('(^' . preg_quote($_GET["select"]) . (substr($_GET["select"], -1) == 's' ? '?' : '') . '_)', '_', $backwardKey["name"]))
					. "</a>";
				$link = $me . 'edit=' . Adminer\url_escape($table);
				foreach ($cols as $column => $val) {
					$link .= "&set[" . Adminer\url_escape(Adminer\bracket_escape($column)) . "]=" . Adminer\url_escape($row[$val]);
				}
				echo "<a href='" . Adminer\h($link) . "' title='" . $this->lang('New item') . "'>+</a> ";
			}
		}
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/backward-keys.png";
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Zobrazí odkazy na tabulky odkazující na aktuální řádek, stejně jako Adminer Editor',
			'New item' => 'Nová položka',
		),
		'de' => array(
			'' => 'Links zu Tabellen anzeigen die auf die aktuelle Zeile verweisen, wie im Adminer Editor',
			'New item' => 'Neuer Datensatz',
		),
		'ja' => array(
			'' => 'Adminer Editor と同様に、カレント行を参照しているテーブルへのリンクを表示',
			'New item' => '新規レコードを挿入',
		),
		'pl' => array(
			'' => 'Wyświetlaj linki do tabel odnoszących się do bieżącego wiersza, tak samo jak w Adminer Editorze', // Claude Opus 5
			'New item' => 'Nowy rekord',
		),
		'ro' => array(
			'' => 'Afișează link-uri către tabelele care fac referire la rândul curent, la fel ca în Adminer Editor', // Claude Opus 5
		),
		'sk' => array(
			'' => 'Zobrazí odkazy na tabuľky odkazujúce na aktuálny riadok, rovnako ako Adminer Editor', // Claude Opus 5
		),
		'hr' => array(
			'' => 'Prikazuje veze na tablice koje referenciraju trenutni redak, kao u Adminer Editoru',
			'New item' => 'Nova stavka',
		),
	);
}
