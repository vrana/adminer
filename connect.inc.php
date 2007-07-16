<?php
if (!(strlen($_GET["db"]) ? $mysql->select_db($_GET["db"]) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]))) {
	unset($_SESSION["databases"][$_GET["server"]]);
	page_header(lang('Select database'));
	if (strlen($_GET["db"])) {
		echo "<p class='error'>" . lang('Invalid database.') . "</p>\n";
	} else {
		echo '<p><a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Create new database') . "</a></p>\n";
		echo '<p><a href="' . htmlspecialchars($SELF) . 'processlist=">' . lang('Process list') . "</a></p>\n";
	}
	page_footer("db");
	exit;
}
$mysql->query("SET CHARACTER SET utf8");
