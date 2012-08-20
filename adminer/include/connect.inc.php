<?php
function connect_error() {
	global $adminer, $connection, $token, $error, $drivers;
	$databases = array();
	if (DB != "") {
		page_header(lang('Database') . ": " . h(DB), lang('Invalid database.'), true);
	} else {
		if ($_POST["db"] && !$error) {
			queries_redirect(substr(ME, 0, -1), lang('Databases have been dropped.'), drop_databases($_POST["db"]));
		}
		
		page_header(lang('Select database'), $error, false);
		echo "<p><a href='" . h(ME) . "database='>" . lang('Create new database') . "</a>\n";
		foreach (array(
			'privileges' => lang('Privileges'),
			'processlist' => lang('Process list'),
			'variables' => lang('Variables'),
			'status' => lang('Status'),
		) as $key => $val) {
			if (support($key)) {
				echo "<a href='" . h(ME) . "$key='>$val</a>\n";
			}
		}
		echo "<p>" . lang('%s version: %s through PHP extension %s', $drivers[DRIVER], "<b>$connection->server_info</b>", "<b>$connection->extension</b>") . "\n";
		echo "<p>" . lang('Logged as: %s', "<b>" . h(logged_user()) . "</b>") . "\n";
		$refresh = "<a href='" . h(ME) . "refresh=1'>" . lang('Refresh') . "</a>\n";
		$databases = $adminer->databases();
		if ($databases) {
			$scheme = support("scheme");
			$collations = collations();
			echo "<form action='' method='post'>\n";
			echo "<table cellspacing='0' class='checkable' onclick='tableClick(event);'>\n";
			echo "<thead><tr><td>&nbsp;<th>" . lang('Database') . "<td>" . lang('Collation') . "<td>" . lang('Tables') . "</thead>\n";
			foreach ($databases as $db) {
				$root = h(ME) . "db=" . urlencode($db);
				echo "<tr" . odd() . "><td>" . checkbox("db[]", $db, in_array($db, (array) $_POST["db"]));
				echo "<th><a href='$root'>" . h($db) . "</a>";
				echo "<td><a href='$root" . ($scheme ? "&amp;ns=" : "") . "&amp;database=' title='" . lang('Alter database') . "'>" . nbsp(db_collation($db, $collations)) . "</a>";
				echo "<td align='right'><a href='$root&amp;schema=' id='tables-" . h($db) . "' title='" . lang('Database schema') . "'>?</a>";
				echo "\n";
			}
			echo "</table>\n";
			echo "<script type='text/javascript'>tableCheck();</script>\n";
			echo "<p><input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm("formChecked(this, /db/)") . ">\n";
			echo "<input type='hidden' name='token' value='$token'>\n";
			echo $refresh;
			echo "</form>\n";
		} else {
			echo "<p>$refresh";
		}
	}
	page_footer("db");
	if ($databases) {
		echo "<script type='text/javascript'>ajaxSetHtml('" . js_escape(ME) . "script=connect');</script>\n";
	}
}

if (isset($_GET["status"])) {
	$_GET["variables"] = $_GET["status"];
}
if (!(DB != "" ? $connection->select_db(DB) : isset($_GET["sql"]) || isset($_GET["dump"]) || isset($_GET["database"]) || isset($_GET["processlist"]) || isset($_GET["privileges"]) || isset($_GET["user"]) || isset($_GET["variables"]) || $_GET["script"] == "connect" || $_GET["script"] == "kill")) {
	if (DB != "" || $_GET["refresh"]) {
		restart_session();
		set_session("dbs", null);
	}
	connect_error(); // separate function to catch SQLite error
	exit;
}

if (support("scheme") && DB != "" && $_GET["ns"] !== "") {
	if (!isset($_GET["ns"])) {
		redirect(preg_replace('~ns=[^&]*&~', '', ME) . "ns=" . get_schema());
	}
	if (!set_schema($_GET["ns"])) {
		page_header(lang('Schema') . ": " . h($_GET["ns"]), lang('Invalid schema.'), true);
		page_footer("ns");
		exit;
	}
}
