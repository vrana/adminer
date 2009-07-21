<?php
// try to compile current version
$_SERVER["argv"] = array("", "editor");
ob_start();
include "../../compile.php";
ob_end_clean();

class Adminer {
	
	function name() {
		return 'CDs';
	}
	
	function credentials() {
		return array('localhost', 'ODBC', '');
	}
	
	function database() {
		return 'cds';
	}
	
	function login($login, $password) {
		return ($login == 'admin');
	}
	
}

include "./editor.php";
