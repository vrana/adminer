<?php
function connect_error() {
	global $dbh, $VERSION;
	if (strlen($_GET["db"])) {
		page_header(lang('Database') . ": " . h($_GET["db"]), lang('Invalid database.'), false);
	} else {
		page_header(lang('Select database'), "", null);
		foreach (array(
			'database' => lang('Create new database'),
			'privileges' => lang('Privileges'),
			'processlist' => lang('Process list'),
			'variables' => lang('Variables'),
		) as $key => $val) {
			echo '<p><a href="' . h(ME) . "$key=\">$val</a>\n";
		}
		echo "<p>" . lang('MySQL version: %s through PHP extension %s', "<b" . ($dbh->server_info < 4.1 ? " class='binary'" : "") . ">$dbh->server_info</b>", "<b>$dbh->extension</b>") . "\n";
		echo "<p>" . lang('Logged as: %s', "<b>" . h($dbh->result($dbh->query("SELECT USER()"))) . "</b>") . "\n";
	}
	page_footer("db");
}

if (!(strlen($_GET["db"]) ? $dbh->select_db($_GET["db"]) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"]))) {
	if (strlen($_GET["db"])) {
		unset($_SESSION["databases"][$_GET["server"]]);
	}
	connect_error(); // separate function to catch SQLite error
	exit;
}
