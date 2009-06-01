<?php
function connect_error() {
	global $dbh, $SELF, $VERSION;
	if (strlen($_GET["db"])) {
		page_header(lang('Database') . ": " . htmlspecialchars($_GET["db"]), lang('Invalid database.'), false);
	} else {
		page_header(lang('Select database'), "", null);
		echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Create new database') . "</a></p>\n";
		echo '<p><a href="' . htmlspecialchars($SELF) . 'privileges=">' . lang('Privileges') . "</a></p>\n";
		echo '<p><a href="' . htmlspecialchars($SELF) . 'processlist=">' . lang('Process list') . "</a></p>\n";
		echo "<p>" . lang('MySQL version: %s through PHP extension %s', "<b" . ($dbh->server_info < 4.1 ? " class='binary'" : "") . ">$dbh->server_info</b>", "<b>$dbh->extension</b>") . "</p>\n";
		echo "<p>" . lang('Logged as: %s', "<b>" . htmlspecialchars($dbh->result($dbh->query("SELECT USER()"))) . "</b>") . "</p>\n";
	}
	page_footer("db");
}

$dbh->query("SET SQL_QUOTE_SHOW_CREATE=1");
if (!(strlen($_GET["db"]) ? $dbh->select_db($_GET["db"]) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]))) {
	if (strlen($_GET["db"])) {
		unset($_SESSION["databases"][$_GET["server"]]);
	}
	connect_error();
	exit;
}
$dbh->query("SET CHARACTER SET utf8");
