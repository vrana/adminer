<?php
if (!(strlen($_GET["db"]) ? mysql_select_db($_GET["db"]) : isset($_GET["sql"]) || isset($_GET["dump"]))) {
	page_header((isset($_GET["db"]) ? lang('Invalid database') : lang('Select database')), "db");
	if (strlen($_GET["db"])) {
		echo "<p class='error'>" . lang('Invalid database.') . "</p>\n";
	}
	page_footer();
	exit;
}
mysql_query("SET CHARACTER SET utf8");
