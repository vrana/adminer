<?php
class AdminerBase {
	
	function name() {
		return lang('Adminer');
	}
	
	function server() {
		return $_GET["server"];
	}
	
	function username() {
		return $_SESSION["usernames"][$_GET["server"]];
	}
	
	function password() {
		return $_SESSION["passwords"][$_GET["server"]];
	}
	
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

$adminer = (class_exists("Adminer") ? new Adminer : new AdminerBase);
