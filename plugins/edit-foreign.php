<?php

/** Select foreign key in edit form
 * @link https://www.adminer.org/plugins/#use
 * @author Jakub Vrana, https://www.vrana.cz/
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class AdminerEditForeign
{
	private $limit;
	private $foreignTables = [];
	private $foreignOptions = [];

	/**
	 * @param int $limit
	 */
	function __construct($limit = 0)
	{
		$this->limit = $limit;
	}

	/**
	 * @param string $table
	 * @param array $field
	 * @param string $attrs
	 * @param string|null $value
	 *
	 * @return string
	 */
	function editInput($table, array $field, $attrs, $value)
	{
		if (!isset($this->foreignTables[$table])) {
			$this->foreignTables[$table] = column_foreign_keys($table);
		}
		$foreignKeys = $this->foreignTables[$table];

		if (empty($foreignKeys[$field["field"]])) {
			return "";
		}

		foreach ($foreignKeys[$field["field"]] as $foreignKey) {
			if (count($foreignKey["source"]) != 1) {
				continue;
			}

			$target = $foreignKey["table"];
			$id = $foreignKey["target"][0];

			if (!isset($this->foreignOptions[$target][$id])) {
				$column = idf_escape($id);
				if (preg_match('~binary~', $field["type"])) {
					$column = "HEX($column)";
				}

				$values = get_vals("SELECT $column FROM " . table($target) . " ORDER BY 1" .
					($this->limit ? " LIMIT " . ($this->limit + 1) : ""));

				if ($this->limit && count($values) > $this->limit) {
					$this->foreignOptions[$target][$id] = false;
				} else {
					$this->foreignOptions[$target][$id] = ["" => ""] + $values;
				}
			}

			if ($options = $this->foreignOptions[$target][$id]) {
				return "<select$attrs>" . optionlist($options, $value) . "</select>";
			} else {
				return "";
			}
		}

		return "";
	}
}
