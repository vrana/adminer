<?php
function adminer_object() {
	
	class AdminerCds extends Adminer {
		
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
			return 'adminer_test';
		}
		
		function login($login, $password) {
			// username: 'admin', password: anything
			return ($login == 'admin');
		}
		
		function tableName($row) {
			// tables without comments would return empty string and will be ignored by Adminer
			return htmlspecialchars($row["Comment"]);
		}
		
		function fieldName($field) {
			// fields without comments will be ignored
			return ($field ? htmlspecialchars($field["comment"]) : "*");
		}
		
	}
	
	return new AdminerCds;
}

include "./index.php";
