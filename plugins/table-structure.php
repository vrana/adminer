<?php

/** Expanded table structure output
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTableStructure {

	/** Print table structure in tabular format
	 * @param array data about individual fields
	 * @return null
	 */
	function tableStructurePrint($fields) {
		$comment = support("comment");
		echo "<table cellspacing='0'>\n";
		echo "<thead><tr><th>" . lang('Column') . "<th>" . lang('Type') . "<th>" . lang('Nullable') . "<th>" . lang('Default') . ($comment ? "<th>" . lang('Comment') : "") . "</thead>\n";
		foreach ($fields as $field) {
			echo "<tr" . odd() . "><td>" . h($field["field"]) . ($field["primary"] ? " (PRI)" : "");
			echo "<td><span>" . h($field["full_type"]) . "</span>";
			echo ($field["auto_increment"] ? " <i>" . lang('Auto Increment') . "</i>" : "");
			echo ($field["collation"] ? " <i>" . h($field["collation"]) . "</i>" : "");
			echo "<td>" . ($field["null"] ? lang('Yes') : lang('No'));
			echo "<td>" . (isset($field["default"]) ? h($field["default"]) : "<i>None</i>");
			echo ($comment ? "<td>" . nbsp($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		return true;
	}
}
