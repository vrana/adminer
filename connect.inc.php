<?php
if (!(strlen($_GET["db"]) ? mysql_select_db($_GET["db"]) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]))) {
	page_header(lang('Select database'));
	if (strlen($_GET["db"])) {
		echo "<p class='error'>" . lang('Invalid database.') . "</p>\n";
	} else {
		echo '<a href="' . htmlspecialchars($SELF) . 'database=">' . lang('Create new database') . '</a>';
	}
	page_footer("db");
	exit;
}
mysql_query("SET CHARACTER SET utf8");
mysql_query("SET SQL_QUOTE_SHOW_CREATE=1");
