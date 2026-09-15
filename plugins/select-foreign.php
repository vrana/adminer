<?php

/** Display the first string column of the referenced row instead of a foreign key value, same as in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectForeign extends Adminer\Plugin {
	// this is copy-pasted from Adminer Editor
	protected $described = array();

	function rowDescription($table) {
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
		'cs' => array('' => 'Zobrazí první řetězcový sloupec odkazovaného řádku místo hodnoty cizího klíče, stejně jako Adminer Editor'),
	);
}
