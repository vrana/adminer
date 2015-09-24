<?php

/** Select foreign key in edit form
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @author Martin Zeman (Zemistr), http://www.zemistr.eu/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditForeign {
	var $_limit;

	function AdminerEditForeign($limit = 0) {
		$this->_limit = $limit;
	}

	function _foreignColumn($table) {
		// first varchar column
		foreach (fields($table) as $field) {
			if (preg_match("~varchar|character varying~", $field["type"])) {
				return idf_escape($field["field"]);
			}
		}

		return null;
	}

	function editInput($table, $field, $attrs, $value) {
		static $foreignTables = array();
		static $foreignColumns = array();
		static $values = array();

		$foreignKeys = &$foreignTables[$table];

		if ($foreignKeys === null) {
			$foreignKeys = column_foreign_keys($table);
		}

		foreach ((array) $foreignKeys[$field["field"]] as $foreignKey) {
			if (count($foreignKey["source"]) == 1) {
				$target = $foreignKey["table"];
				$id = $foreignKey["target"][0];
				$options = &$values[$target][$id];

				if (!$options) {
					$key_column = idf_escape($id);
					$value_column = &$foreignColumns[$target];

					if($value_column === null){
						$value_column = $this->_foreignColumn($target);
					}

					if($value_column === null){
						$value_column = $key_column;
					}

					$options = array("" => "") + get_key_vals("SELECT " . $key_column . ',' . $value_column . " FROM " . table($target) . " ORDER BY 1");

					array_walk(
						$options,
						function (&$value, $key) {
							if ($key !== '') {
								$value = "[$key] $value";
							}
						}
					);

					if ($this->_limit && count($options) - 1 > $this->_limit) {
						return;
					}
				}
				return "<select$attrs>" . optionlist($options, $value, true) . "</select>";
			}
		}
	}
}
