<?php

/** Select foreign key in edit form
* @link http://www.adminer.org/plugins/#use
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

	function editInput($table, $field, $attrs, $value) {
		static $foreignTables = array();
		static $values = array();
		static $foreignTablesFields = array();

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
					$foreignFields = &$foreignTablesFields[$target];
					$key_column = $value_column = idf_escape($id);

					if ($foreignFields === null) {
						$foreignFields = array_keys(fields($target));
					}

					$column_index = array_search($id, $foreignFields);

					if ($column_index !== false) {
						if (isset($foreignFields[$column_index + 1])) {
							$value_column = idf_escape($foreignFields[$column_index + 1]);
						}
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
