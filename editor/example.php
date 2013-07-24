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
			if ($order && preg_match('~_(md5|sha1)$~', $field["field"])) {
				return ""; // hide hashes in select
			}
			// display only column with comments, first five of them plus searched columns
			if ($order < 5) {
				return h($field["comment"]);
			}
			foreach ((array) $_GET["where"] as $key => $where) {
				if ($where["col"] == $field["field"] && ($key >= 0 || $where["val"] != "")) {
					return h($field["comment"]);
				}
			}
			return "";
		}

	}

	return new AdminerCds;
}

include "./index.php";
