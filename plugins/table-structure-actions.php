<?php

/** 
* Expansion on the existing tableStructurePrint to mimic phpMyAdmin's actions column.
* The biggest feature of phpMyAdmin that I miss in Adminer is having the ability to group by count- quickly!
*/
class AdminerTableStructureActions {

	/** Print table structure in tabular format
	* @param array data about individual fields
	* @return bool
	*/
	function tableStructurePrint($fields) {
		echo "<table cellspacing='0'>\n";
		echo "<thead><tr><th>" . lang('Column') . "<th>" . lang('Type') . "<th>" . lang('Nullable') . "<th>" . lang('Default') . (support("comment") ? "<th>" . lang('Comment') : "") . "<th>" . lang('Action') . "</thead>\n";
		foreach ($fields as $field) {
			echo "<tr" . odd() . "><th>" . h($field["field"]) . ($field["primary"] ? " (PRIMARY)" : "");
			echo "<td><span>" . h($field["full_type"]) . "</span>";
			echo ($field["auto_increment"] ? " <i>" . lang('Auto Increment') . "</i>" : "");
			echo ($field["collation"] ? " <i>" . h($field["collation"]) . "</i>" : "");
			echo "<td>" . ($field["null"] ? lang('Yes') : lang('No'));
			echo "<td>" . (isset($field["default"]) ? h($field["default"]) : "&nbsp;");
			echo (support("comment") ? "<td>" . h($field["comment"]) : "");

			echo "<td>";
			echo "    <a href='".$_SERVER["SCRIPT_NAME"]."?server=".$_GET["server"]."&username=".$_GET["username"]."&db=".$_GET["db"]."&select=".$_GET["table"]."&columns[0][fun]=count&columns[0][col]=".h($field["field"])."&columns[1][fun]=&columns[1][col]=".h($field["field"])."&limit=50&text_length=100'>";
			echo          "[".lang('Distinct')." ".lang('Values')."]";
			echo "    </a>";
			echo "</td>";
		}
		echo "</table>\n";
		return true;
	}
}
