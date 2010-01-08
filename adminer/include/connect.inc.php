<?php
function connect_error() {
	global $connection, $VERSION, $token, $error;
	if (strlen(DB)) {
		page_header(lang('Database') . ": " . h(DB), lang('Invalid database.'), false);
	} else {
		if ($_POST["db"] && !$error) {
			unset($_SESSION["databases"][$_GET["server"]]);
			foreach ($_POST["db"] as $db) {
				if (!queries("DROP DATABASE " . idf_escape($db))) {
					break;
				}
			}
			queries_redirect(substr(ME, 0, -1), lang('Database has been dropped.'), !$connection->error);
		}
		
		page_header(lang('Select database'), "", null);
		echo "<p>";
		foreach (array(
			'database' => lang('Create new database'),
			'privileges' => lang('Privileges'),
			'processlist' => lang('Process list'),
			'variables' => lang('Variables'),
			'status' => lang('Status'),
		) as $key => $val) {
			echo "<a href='" . h(ME) . "$key='>$val</a>\n";
		}
		echo "<p>" . lang('MySQL version: %s through PHP extension %s', "<b" . ($connection->server_info < 4.1 ? " class='binary'" : "") . ">$connection->server_info</b>", "<b>$connection->extension</b>") . "\n";
		echo "<p>" . lang('Logged as: %s', "<b>" . h($connection->result($connection->query("SELECT USER()"))) . "</b>") . "\n";
		$databases = get_databases();
		if ($databases) {
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table cellspacing='0' onclick='tableClick(event);'>\n";
			echo "<thead><tr><td><input type='hidden' name='token' value='$token'>&nbsp;<th>" . lang('Database') . "<td>" . lang('Collation') . "</thead>\n";
			foreach ($databases as $db) {
				$root = h(ME) . "db=" . urlencode($db);
				echo "<tr" . odd() . "><td>" . checkbox("db[]", $db, false);
				echo "<th><a href='$root'>" . h($db) . "</a>";
				echo "<td><a href='$root&amp;database='>" . nbsp(db_collation($db, $collations)) . "</a>";
				echo "\n";
			}
			echo "</table>\n";
			echo "<p><input type='submit' name='drop' value='" . lang('Drop') . "' onclick=\"return confirm('" . lang('Are you sure?') . " (' + formChecked(this, /db/) + ')');\">\n";
			echo "</form>\n";
		}
	}
	page_footer("db");
}

if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (!(strlen(DB) ? $connection->select_db(DB) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"]))) {
	if (strlen(DB)) {
		unset($_SESSION["databases"][$_GET["server"]]);
	}
	connect_error(); // separate function to catch SQLite error
	exit;
}
