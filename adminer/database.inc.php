<?php
$row = $_POST;

if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	$name = trim($row["name"]);
	if ($_POST["drop"]) {
		$_GET["db"] = ""; // to save in global history
		queries_redirect(remove_from_uri("db|database"), lang('Database has been dropped.'), drop_databases(array(DB)));
	} elseif (DB !== $name) {
		// create or rename database
		if (DB != "") {
			$_GET["db"] = $name;
			queries_redirect(preg_replace('~\bdb=[^&]*&~', '', ME) . "db=" . urlencode($name), lang('Database has been renamed.'), rename_database($name, $row["collation"]));
		} else {
			$databases = explode("\n", str_replace("\r", "", $name));
			$success = true;
			$last = "";
			foreach ($databases as $db) {
				if (count($databases) == 1 || $db != "") { // ignore empty lines but always try to create single database
					if (!create_database($db, $row["collation"])) {
						$success = false;
					}
					$last = $db;
				}
			}
			restart_session();
			set_session("dbs", null);
			queries_redirect(ME . "db=" . urlencode($last), lang('Database has been created.'), $success);
		}
	} else {
		// alter database
		if (!$row["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($name) . (preg_match('~^[a-z0-9_]+$~i', $row["collation"]) ? " COLLATE $row[collation]" : ""), substr(ME, 0, -1), lang('Database has been altered.'));
	}
}

page_header(DB != "" ? lang('Alter database') : lang('Create database'), $error, array(), h(DB));

$collations = collations();
$name = DB;
if ($_POST) {
	$name = $row["name"];
} elseif (DB != "") {
	$row["collation"] = db_collation(DB, $collations);
} elseif ($jush == "sql") {
	// propose database name with limited privileges
	foreach (get_vals("SHOW GRANTS") as $grant) {
		if (preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\\.\\*)?~', $grant, $match) && $match[1]) {
			$name = stripcslashes(idf_unescape("`$match[2]`"));
			break;
		}
	}
}
?>

<form action="" method="post">
<p>
<?php
echo ($_POST["add_x"] || strpos($name, "\n")
	? '<textarea id="name" name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" id="name" value="' . h($name) . '" maxlength="64" autocapitalize="off">'
) . "\n" . ($collations ? html_select("collation", array("" => "(" . lang('collation') . ")") + $collations, $row["collation"]) . doc_link(array(
	'sql' => "charset-charsets.html",
	'mssql' => "ms187963.aspx",
)) : "");
?>
<script type='text/javascript'>focus(document.getElementById('name'));</script>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if (DB != "") {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'" . confirm() . ">\n";
} elseif (!$_POST["add_x"] && $_GET["db"] == "") {
	echo "<input type='image' class='icon' name='add' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'>\n";
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
