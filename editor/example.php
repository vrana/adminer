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
		
		function tableName($tableStatus) {
			// tables without comments would return empty string and will be ignored by Adminer
			return h($tableStatus["Comment"]);
		}
		
		function fieldName($field, $order = 0) {
			if ($order && ereg('_(md5|sha1)$', $field["field"])) {
				return ""; // hide hashes in select
			}
			// only columns with comments will be displayed and only the first five in select
			return ($order <= 5 ? h($field["comment"]) : "");
		}
		
	}
	
	return new AdminerCds;
}

include "./index.php";
