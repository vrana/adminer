<?php

/** Display the first string column of the referenced row instead of a foreign key value, same as in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectForeign extends Adminer\Plugin {
	protected $columns;

	/**
	* @param string[] $columns table name in key, SQL expression describing its row in value, empty string means no replacement
	*/
	function __construct(array $columns = array()) {
		$this->columns = $columns;
	}

	// this is copy-pasted from Adminer Editor
	protected $described = array();

	function rowDescription($table) {
		$column = $this->columns[$table];
		if ($column !== null) {
			return $column;
		}
		// first string column
		foreach (Adminer\fields($table) as $field) {
			if (preg_match("~char|text~", $field["type"])) {
				return Adminer\idf_escape($field["field"]);
			}
		}
		return "";
	}

	function rowDescriptions($rows, $foreignKeys) {
		$return = $rows;
		foreach ($rows[0] as $key => $val) {
			foreach ((array) $foreignKeys[$key] as $foreignKey) {
				$table = $foreignKey["table"];
				if (
					count($foreignKey["source"]) == 1
					&& ($foreignKey["db"] == "" || $foreignKey["db"] == Adminer\DB) // Oracle fills the current owner
				) {
					$column = $this->columns[$table];
					if ($column != "" && Adminer\idf_unescape($column) == $foreignKey["target"][0]) {
						break; // the foreign key value is the description, print it as without this plugin
					}
					$schema = $_GET["ns"];
					$otherSchema = ($foreignKey["ns"] != "" && $foreignKey["ns"] != $schema);
					if ($otherSchema) {
						Adminer\set_schema($foreignKey["ns"]); // rowDescription() and table() work in the current schema
					}
					$name = $this->rowDescription($table);
					if ($name != "") {
						$this->described[$key] = true;
						// find all used ids
						$ids = array();
						foreach ($rows as $row) {
							if (isset($row[$key])) {
								$ids[$row[$key]] = Adminer\q($row[$key]);
							}
						}
						if ($ids) {
							// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
							$id = Adminer\idf_escape($foreignKey["target"][0]);
							$descriptions = Adminer\get_key_vals("SELECT $id, $name FROM " . Adminer\table($table) . " WHERE $id IN (" . implode(", ", $ids) . ")");
							foreach ($rows as $n => $row) {
								if (isset($row[$key]) && isset($descriptions[$row[$key]])) {
									$return[$n][$key] = $descriptions[$row[$key]];
								}
							}
						}
					}
					if ($otherSchema) {
						Adminer\set_schema($schema);
					}
					if ($name != "") {
						break;
					}
				}
			}
		}
		return $return;
	}

	function selectVal($val, $link, $field, $original) {
		if (isset($this->described[$field["field"]])) {
			if ($val === null) {
				return "";
			}
			if ($link != "") {
				// the original value is not passed, it is in the link to the referenced row
				parse_str(parse_url($link, PHP_URL_QUERY), $params);
				$id = $params["where"][0]["val"];
				if ($id !== null && $id !== $original) {
					// the foreign key column is usually a number which is not shortened
					$length = Adminer\adminer()->selectLengthProcess();
					if ($length != "") {
						$val = Adminer\shorten_utf8($original, max(0, +$length));
					}
					return "<a href='" . Adminer\h($link) . "' title='" . Adminer\h($id) . "'>$val</a>";
				}
			}
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Místo hodnoty cizího klíče zobrazí první řetězcový sloupec odkazovaného řádku, stejně jako Adminer Editor'),
		'de' => array('' => 'Zeigt statt des Fremdschlüsselwerts die erste Zeichenkettenspalte der referenzierten Zeile an, wie im Adminer Editor'), // Claude Opus 5
		'pl' => array('' => 'Zamiast wartości klucza obcego wyświetla pierwszą kolumnę znakową wskazywanego wiersza, tak samo jak Adminer Editor'), // Claude Opus 5
		'ro' => array('' => 'Afișează prima coloană de tip șir a rândului referit în locul valorii cheii străine, la fel ca în Adminer Editor'), // Claude Opus 5
		'ja' => array('' => '外部キーの値の代わりに参照先の行の最初の文字列型の列を表示、Adminer Editor と同様'), // Claude Opus 5
		'sk' => array('' => 'Zobrazí namiesto hodnoty cudzieho kľúča prvý reťazcový stĺpec odkazovaného riadku, rovnako ako Adminer Editor'), // Claude Opus 5
		'zh' => array('' => '显示被引用行的第一个字符串列而不是外键值，与 Adminer Editor 中相同'), // Claude Opus 5
	);
}
