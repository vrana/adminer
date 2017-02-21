<?php
function adminer_object() {
	include_once "../plugins/plugin.php";
	include_once "../plugins/login-sqlite.php";
	return new AdminerPlugin(array(new AdminerLoginSqlite));
}

include "./index.php";
