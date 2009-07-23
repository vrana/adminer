<?php
// try to compile current version
$_SERVER["argv"] = array("", "editor");
ob_start();
include "../../compile.php";
ob_end_clean();

class Adminer {
	
	function name() {
		// custom name in title and heading
		return 'CDs';
	}
	
	function credentials() {
		// ODBC user without password on localhost
		return array('localhost', 'ODBC', '');
	}
	
	function database() {
		// will be escaped by Adminer
		return 'cds';
	}
	
	function login($login, $password) {
		// username: admin, password: anything
		return ($login == 'admin');
	}
	
	function table_name($row) {
		// tables without comments would return empty string and will be ignored by Adminer
		return htmlspecialchars($row["Comment"]);
	}
	
	function field_name($field) {
		// fields without comments will be ignored
		return ($field ? htmlspecialchars($field["comment"]) : "*");
	}
	
}

include "./editor.php";
