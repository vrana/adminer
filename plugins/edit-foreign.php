<?php

/** Select foreign key in edit form
* @link http://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEditForeign {
	var $_limit;
	
	function AdminerEditForeign($limit = 50) {
		$this->_limit = $limit;
	}
	
	const LIMIT = 50;
	
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
					$count = get_vals(count_rows($target, array(), false, null));
					if($count[0] > self::LIMIT) {
						return;
					}
					$options = array("" => "") + get_vals("SELECT " . idf_escape($id) . " FROM " . table($target) . " ORDER BY 1");
					if (count($options) - 1 > $this->_limit) {
						return;
					}
				}
				return "<select$attrs>" . optionlist($options, $value) . "</select>";
			}
		}
	}
	
}
