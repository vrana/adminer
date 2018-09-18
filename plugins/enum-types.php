<?php

/** Use <select><option> for enum edit instead of regular input text on enum type in PostgreSQL
 * @link https://www.adminer.org/plugins/#use
 * @author Adam Kuśmierz, http://kusmierz.be/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerEnumTypes {
	var $_types = null;

	function editInput($table, $field, $attrs, $value) {
		// PostgreSQL only
		if (!in_array(strtolower(connection()->extension), array('pgsql', 'pdo_pgsql'))) {
			return;
		}

		// read types and "cache" it
		if (is_null($this->_types)) {
			$types = types();
			$this->_types = array();

			foreach ($types as $type) {
				$values = get_vals("SELECT unnest(enum_range(NULL::$type))::text AS value");
				if (!empty($values) && is_array($values)) {
					$this->_types[$type] = $values;
				}
			}
		}

		if (array_key_exists($field["type"], $this->_types)) {
			$options = $this->_types[$field["type"]];
			$options = array_combine($options, $options);
			$selected = $value;

			if ($field["null"]) {
				$options = array("" => array("" => "NULL")) + $options;
				if ($value === null && !isset($_GET["select"])) {
					$selected = "";
				}
			}
			if (isset($_GET["select"])) {
				$options = array("" => array(-1 => lang('original'))) + $options;
			}

			return "<select$attrs>" . optionlist($options, (string) $selected, 1) . "</select>";
		}
	}

}
