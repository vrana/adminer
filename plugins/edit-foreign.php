<?php

/** Select a foreign key in the edit form
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditForeign extends Adminer\Plugin {
	protected $limit;

	/**
	* @param int $limit maximum number of rows in <select>, more rows are searched by AJAX in <datalist>, 0 means unlimited
	*/
	function __construct($limit = 100) {
		$this->limit = $limit;
	}

	function afterConnect() {
		if (isset($_GET["edit-foreign"])) {
			$fields = Adminer\fields($_GET["edit-foreign"]);
			$field = $fields[$_GET["field"]];
			if ($field && (list($target, $id, $name) = $this->foreignColumn($_GET["edit-foreign"], $field)) && $name != $id) {
				$value = $_GET["value"];
				$exact = (preg_match('~^[0-9]+$~', $value) ? "$id = " . Adminer\q($value) : "");
				$where = ($exact ? "$exact OR " : "") . "$name LIKE " . Adminer\q("$value%");
				// the row with the typed ID first, the limit would cut it off otherwise; CASE - MS SQL and Oracle can't order by ($exact) DESC
				$order = ($exact ? "CASE WHEN $exact THEN 0 ELSE 1 END, " : "") . "2";
				echo Adminer\optionlist($this->options("SELECT" . Adminer\limit("$id, $name FROM $target", " WHERE $where ORDER BY $order", $this->limit)), null, true);
			}
			exit;
		}
	}

	function editInput($table, $field, $attrs, $value) {
		static $values = array();
		static $lists = 0;
		if (list($target, $id, $name) = $this->foreignColumn($table, $field)) {
			$options = &$values[$target][$id];
			if ($options === null) {
				// no ORDER BY - a big table stops after the limit instead of sorting all rows
				$options = $this->options("SELECT" . Adminer\limit("$id, $name FROM $target", "", ($this->limit ? $this->limit + 1 : 0)));
				if ($this->limit && count($options) > $this->limit) {
					$options = false;
				} else {
					asort($options);
					$options = array("" => "") + $options;
				}
			}
			if ($options) {
				return "<select$attrs>" . Adminer\optionlist($options, $value, true) . "</select>";
			}
			if ($name != $id) { // a datalist of bare IDs would add nothing to the default input
				$lists++;
				return "<input value='" . Adminer\h($value) . "' list='edit-foreign-$lists'"
					. Adminer\on('input', 'editForeignInput', Adminer\ME . "edit-foreign=" . Adminer\url_escape($table) . "&field=" . Adminer\url_escape($field["field"]) . "&value=")
					. "$attrs><datalist id='edit-foreign-$lists'></datalist>"
					. ($lists > 1 ? "" : Adminer\script("function editForeignInput(url) {
	const input = this;
	const value = input.value;
	ajax(url + urlEscape(value), request => {
		if (input.value == value) { // ignore old responses
			input.list.innerHTML = request.responseText;
		}
	});
}"));
			}
		}
	}

	/** Get the referenced table, the referenced column and the first string column describing its row
	* @return array{string, string, string}|void the referenced column also in place of a missing description
	*/
	protected function foreignColumn($table, array $field) {
		static $foreignTables = array();
		$foreignKeys = &$foreignTables[$table];
		if ($foreignKeys === null) {
			$foreignKeys = Adminer\column_foreign_keys($table);
		}
		foreach ((array) $foreignKeys[$field["field"]] as $foreignKey) {
			if (count($foreignKey["source"]) == 1) {
				$otherDb = ($foreignKey["db"] != "" && $foreignKey["db"] != Adminer\DB); // Oracle fills the current owner
				$target = ($otherDb ? Adminer\idf_escape($foreignKey["db"]) . "." : "")
					. ($foreignKey["ns"] != "" ? Adminer\idf_escape($foreignKey["ns"]) . "." : "")
					. Adminer\idf_escape($foreignKey["table"])
				;
				$id = Adminer\idf_escape($foreignKey["target"][0]);
				if (preg_match('~binary~', $field["type"])) {
					$id = "HEX($id)";
				}
				$name = $id;
				// fields() works in the current database and schema, Oracle's in the current owner
				if (!$otherDb || (Adminer\JUSH == "sql" && Adminer\connection()->select_db($foreignKey["db"]))) {
					$schema = $_GET["ns"];
					$otherSchema = ($foreignKey["ns"] != "" && $foreignKey["ns"] != $schema);
					if ($otherSchema) {
						Adminer\set_schema($foreignKey["ns"]);
					}
					foreach (Adminer\fields($foreignKey["table"]) as $column) {
						if (preg_match('~char|text~', $column["type"])) {
							if ($column["field"] != $foreignKey["target"][0]) {
								$name = Adminer\idf_escape($column["field"]);
							}
							break;
						}
					}
					if ($otherSchema) {
						Adminer\set_schema($schema);
					}
					if ($otherDb) {
						Adminer\connection()->select_db(Adminer\DB);
					}
				}
				return array($target, $id, $name);
			}
		}
	}

	/** Get options from a query selecting an ID and its description
	* @return string[] the ID in place of a NULL description
	*/
	protected function options($query) {
		$return = array();
		foreach (Adminer\get_key_vals($query) as $id => $name) {
			$return[$id] = ($name !== null ? $name : $id);
		}
		return $return;
	}

	protected $translations = array(
		'cs' => array('' => 'Výběr cizího klíče v editačním formuláři'),
		'de' => array('' => 'Wählen Sie im Bearbeitungsformular den Fremdschlüssel aus'),
		'hr' => array('' => 'Odabir stranog ključa u obrascu za uređivanje'),
		'ja' => array('' => '外部キーを編集フォームで選択'),
		'pl' => array('' => 'Wybierz klucz obcy w formularzu edycji'),
		'ro' => array('' => 'Selectați cheia străină în formularul de editare'),
		'sk' => array('' => 'Výber cudzieho kľúča v editačnom formulári'), // Claude Opus 5
		'zh' => array('' => '在编辑表单中选择外键'), // Claude Opus 5
	);
}
