<?php

/** Select foreign key in edit form
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditForeign {
	var $_limit;
	
	function __construct($limit = 0) {
		$this->_limit = $limit;
	}
	
	function editInput($table, $field, $attrs, $value) {
		static $foreignTables = array();
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
					$column = idf_escape($id);
					if (preg_match('~binary~', $field["type"])) {
						$column = "HEX($column)";
					}
					$options = array("" => "") + get_vals("SELECT $column FROM " . table($target) . " ORDER BY 1" . ($this->_limit ? " LIMIT " . ($this->_limit + 1) : ""));
					if ($this->_limit && count($options) - 1 > $this->_limit) {
						return;
					}
				}
				return "<select$attrs>" . optionlist($options, $value) . "</select>";
			}
		}
	}
	
}
