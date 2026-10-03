<?php

/** Display the first string column of the referenced row instead of a foreign key value, same as in Adminer Editor
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSelectForeign extends Adminer\Plugin {
	protected $columns;
	protected $display;

	/**
	* @param string[] $columns table name in key, SQL expression describing its row in value, empty string means no replacement
	* @param ''|'id'|'both' $display see config() for explanation
	*/
	function __construct(array $columns = array(), $display = '') {
		$this->columns = $columns;
		$this->display = $display;
	}

	function config() {
		$options = array(
			'' => $this->lang('Description, ID on hover'),
			'id' => $this->lang('ID, description on hover'),
			'both' => $this->lang('Description (ID)'),
		);
		$display = Adminer\get_setting("foreign", "adminer_config", $this->display);
		return array($this->lang('Foreign keys') => Adminer\html_radios('config[foreign]', $options, $display, "<br>"));
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
					&& ($foreignKey["db"] == "" || $foreignKey["db"] == Adminer\DB || Adminer\JUSH == "sql") // Oracle fills the current owner, its fields() look only into the current owner
				) {
					$column = $this->columns[$table];
					if ($column != "" && Adminer\idf_unescape($column) == $foreignKey["target"][0]) {
						break; // the foreign key value is the description, print it as without this plugin
					}
					$otherDb = ($foreignKey["db"] != "" && $foreignKey["db"] != Adminer\DB);
					if ($otherDb && !Adminer\connection()->select_db($foreignKey["db"])) { // rowDescription() and table() work in the current database
						continue;
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
					if ($otherDb) {
						Adminer\connection()->select_db(Adminer\DB);
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
					$display = Adminer\get_setting("foreign", "adminer_config", $this->display);
					return ($display == 'id' ? "<a href='" . Adminer\h($link) . "' title='" . Adminer\h($original) . "'>" . Adminer\h($id) . "</a>"
						: ($display == 'both' ? "<a href='" . Adminer\h($link) . "'>$val (" . Adminer\h($id) . ")</a>"
						: "<a href='" . Adminer\h($link) . "' title='" . Adminer\h($id) . "'>$val</a>"
					));
				}
			}
		}
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Místo hodnoty cizího klíče zobrazí první řetězcový sloupec odkazovaného řádku, stejně jako Adminer Editor',
			'Description, ID on hover' => 'Popis, ID při najetí myší',
			'ID, description on hover' => 'ID, popis při najetí myší',
			'Description (ID)' => 'Popis (ID)',
			'Foreign keys' => 'Cizí klíče', // copied from adminer/lang/
		),
		'de' => array(
			'' => 'Zeigt statt des Fremdschlüsselwerts die erste Zeichenkettenspalte der referenzierten Zeile an, wie im Adminer Editor', // Claude Opus 5
			'Description, ID on hover' => 'Beschreibung, ID beim Überfahren mit der Maus', // Claude Opus 5.5
			'ID, description on hover' => 'ID, Beschreibung beim Überfahren mit der Maus', // Claude Opus 5.5
			'Description (ID)' => 'Beschreibung (ID)', // Claude Opus 5.5
			'Foreign keys' => 'Fremdschlüssel', // copied from adminer/lang/
		),
		'ja' => array(
			'' => '外部キーの値の代わりに参照先の行の最初の文字列型の列を表示、Adminer Editor と同様', // Claude Opus 5
			'Description, ID on hover' => '説明 (ID はマウスオーバー時)', // Claude Opus 5.5
			'ID, description on hover' => 'ID (説明はマウスオーバー時)', // Claude Opus 5.5
			'Description (ID)' => '説明 (ID)', // Claude Opus 5.5
			'Foreign keys' => '外部キー', // copied from adminer/lang/
		),
		'pl' => array(
			'' => 'Zamiast wartości klucza obcego wyświetla pierwszą kolumnę znakową wskazywanego wiersza, tak samo jak Adminer Editor', // Claude Opus 5
			'Description, ID on hover' => 'Opis, ID po najechaniu myszą', // Claude Opus 5.5
			'ID, description on hover' => 'ID, opis po najechaniu myszą', // Claude Opus 5.5
			'Description (ID)' => 'Opis (ID)', // Claude Opus 5.5
			'Foreign keys' => 'Klucze obce', // copied from adminer/lang/
		),
		'ro' => array(
			'' => 'Afișează prima coloană de tip șir a rândului referit în locul valorii cheii străine, la fel ca în Adminer Editor', // Claude Opus 5
			'Description, ID on hover' => 'Descriere, ID la trecerea mouse-ului', // Claude Opus 5.5
			'ID, description on hover' => 'ID, descriere la trecerea mouse-ului', // Claude Opus 5.5
			'Description (ID)' => 'Descriere (ID)', // Claude Opus 5.5
			'Foreign keys' => 'Chei externe', // copied from adminer/lang/
		),
		'sk' => array(
			'' => 'Zobrazí namiesto hodnoty cudzieho kľúča prvý reťazcový stĺpec odkazovaného riadku, rovnako ako Adminer Editor', // Claude Opus 5
			'Description, ID on hover' => 'Popis, ID pri prejdení myšou', // Claude Opus 5.5
			'ID, description on hover' => 'ID, popis pri prejdení myšou', // Claude Opus 5.5
			'Description (ID)' => 'Popis (ID)', // Claude Opus 5.5
			'Foreign keys' => 'Cudzie kľúče', // copied from adminer/lang/
		),
		'zh' => array(
			'' => '显示被引用行的第一个字符串列而不是外键值，与 Adminer Editor 中相同', // Claude Opus 5
			'Description, ID on hover' => '描述，悬停时显示 ID', // Claude Opus 5.5
			'ID, description on hover' => 'ID，悬停时显示描述', // Claude Opus 5.5
			'Description (ID)' => '描述（ID）', // Claude Opus 5.5
			'Foreign keys' => '外键', // copied from adminer/lang/
		),
	);
}
