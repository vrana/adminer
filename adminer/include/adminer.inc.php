<?php
class AdminerBase {
	
	function table_list($row) {
		global $SELF;
		echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . lang('select') . '</a> ';
		echo '<a href="' . htmlspecialchars($SELF) . (isset($row["Rows"]) ? 'table' : 'view') . '=' . urlencode($row["Name"]) . '">' . $this->table_name($row) . "</a><br />\n";
	}
	
	function table_name($row) {
		return htmlspecialchars($row["Name"]);
	}
	
	function field_name($fields, $key) {
		return htmlspecialchars($key);
	}
	
}
