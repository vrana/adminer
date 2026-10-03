<?php

/** Select a foreign key in the edit form
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditForeign extends Adminer\Plugin {
	protected $limit;
	protected $display;

	/**
	* @param int $limit maximum number of rows in <select>, more rows are searched by AJAX in <datalist>, 0 means unlimited
	* @param ''|'id'|'both' $display see config() for explanation
	*/
	function __construct($limit = 100, $display = '') {
		$this->limit = $limit;
		$this->display = $display;
	}

	function config() {
		$options = array(
			'' => $this->lang('Description'),
			'id' => $this->lang('ID (description)'),
			'both' => $this->lang('Description (ID)'),
		);
		$display = Adminer\get_setting("edit_foreign", "adminer_config", $this->display);
		return array($this->lang('Foreign keys in edit form') => Adminer\html_radios('config[edit_foreign]', $options, $display, "<br>"));
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
				$display = Adminer\get_setting("edit_foreign", "adminer_config", $this->display);
				$options = $this->options("SELECT" . Adminer\limit("$id, $name FROM $target", "", ($this->limit ? $this->limit + 1 : 0)), $display);
				if ($this->limit && count($options) > $this->limit) {
					$options = false;
				} else {
					asort($options, SORT_NATURAL); // natural - "ID (description)" by the number
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
	* @param ''|'id'|'both' $display not used in <datalist> which shows also the ID
	* @return string[] the ID in place of a NULL description
	*/
	protected function options($query, $display = '') {
		$return = array();
		foreach (Adminer\get_key_vals($query) as $id => $name) {
			$return[$id] = ($name === null || "$name" === "$id" ? $id // the same also without a description column
				: ($display == 'id' ? "$id ($name)"
				: ($display == 'both' ? "$name ($id)"
				: $name
			)));
		}
		return $return;
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Výběr cizího klíče v editačním formuláři',
			'Foreign keys in edit form' => 'Cizí klíče v editačním formuláři',
			'Description' => 'Popis',
			'ID (description)' => 'ID (popis)',
			'Description (ID)' => 'Popis (ID)',
		),
		'de' => array(
			'' => 'Wählen Sie im Bearbeitungsformular den Fremdschlüssel aus',
			'Foreign keys in edit form' => 'Fremdschlüssel im Bearbeitungsformular', // Claude Opus 5.5
			'Description' => 'Beschreibung', // Claude Opus 5.5
			'ID (description)' => 'ID (Beschreibung)', // Claude Opus 5.5
			'Description (ID)' => 'Beschreibung (ID)', // Claude Opus 5.5
		),
		'hr' => array(
			'' => 'Odabir stranog ključa u obrascu za uređivanje',
			'Foreign keys in edit form' => 'Strani ključevi u obrascu za uređivanje', // Claude Opus 5.5
			'Description' => 'Opis', // Claude Opus 5.5
			'ID (description)' => 'ID (opis)', // Claude Opus 5.5
			'Description (ID)' => 'Opis (ID)', // Claude Opus 5.5
		),
		'ja' => array(
			'' => '外部キーを編集フォームで選択',
			'Foreign keys in edit form' => '編集フォームの外部キー', // Claude Opus 5.5
			'Description' => '説明', // Claude Opus 5.5
			'ID (description)' => 'ID (説明)', // Claude Opus 5.5
			'Description (ID)' => '説明 (ID)', // Claude Opus 5.5
		),
		'pl' => array(
			'' => 'Wybierz klucz obcy w formularzu edycji',
			'Foreign keys in edit form' => 'Klucze obce w formularzu edycji', // Claude Opus 5.5
			'Description' => 'Opis', // Claude Opus 5.5
			'ID (description)' => 'ID (opis)', // Claude Opus 5.5
			'Description (ID)' => 'Opis (ID)', // Claude Opus 5.5
		),
		'ro' => array(
			'' => 'Selectați cheia străină în formularul de editare',
			'Foreign keys in edit form' => 'Chei externe în formularul de editare', // Claude Opus 5.5
			'Description' => 'Descriere', // Claude Opus 5.5
			'ID (description)' => 'ID (descriere)', // Claude Opus 5.5
			'Description (ID)' => 'Descriere (ID)', // Claude Opus 5.5
		),
		'sk' => array(
			'' => 'Výber cudzieho kľúča v editačnom formulári', // Claude Opus 5
			'Foreign keys in edit form' => 'Cudzie kľúče v editačnom formulári', // Claude Opus 5.5
			'Description' => 'Popis', // Claude Opus 5.5
			'ID (description)' => 'ID (popis)', // Claude Opus 5.5
			'Description (ID)' => 'Popis (ID)', // Claude Opus 5.5
		),
		'zh' => array(
			'' => '在编辑表单中选择外键', // Claude Opus 5
			'Foreign keys in edit form' => '编辑表单中的外键', // Claude Opus 5.5
			'Description' => '描述', // Claude Opus 5.5
			'ID (description)' => 'ID（描述）', // Claude Opus 5.5
			'Description (ID)' => '描述（ID）', // Claude Opus 5.5
		),
	);
}
