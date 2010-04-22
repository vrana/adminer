<?php
if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	restart_session();
	if ($_POST["drop"]) {
		set_session("databases", null);
		queries_redirect(remove_from_uri("db|database"), lang('Database has been dropped.'), drop_databases(array(DB)));
	} elseif (DB !== $_POST["name"]) {
		// create or rename database
		set_session("databases", null); // clear cache
		if (DB != "") {
			queries_redirect(preg_replace('~db=[^&]*&~', '', ME) . "db=" . urlencode($_POST["name"]), lang('Database has been renamed.'), rename_database($_POST["name"], $_POST["collation"]));
		} else {
			$dbs = explode("\n", str_replace("\r", "", $_POST["name"]));
			$success = true;
			$last = "";
			foreach ($dbs as $db) {
				if (count($dbs) == 1 || $db != "") { // ignore empty lines but always try to create single database
					if (!create_database($db, $_POST["collation"])) {
						$success = false;
					}
					$last = $db;
				}
			}
			queries_redirect(ME . "db=" . urlencode($last), lang('Database has been created.'), $success);
		}
	} else {
		// alter database
		if (!$_POST["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE " . $connection->quote($_POST["collation"]), substr(ME, 0, -1), lang('Database has been altered.'));
	}
}

page_header(DB != "" ? lang('Alter database') : lang('Create database'), $error, array(), DB);

$collations = collations();
$name = DB;
$collate = null;
if ($_POST) {
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} elseif (DB != "") {
	$collate = db_collation(DB, $collations);
} elseif ($driver == "sql") {
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
	? '<textarea name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" value="' . h($name) . '" maxlength="64">'
) . "\n";
if ($collations) {
	html_select("collation", array("" => "(" . lang('collation') . ")") + $collations, $collate);
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if (strlen(DB)) {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'$confirm>\n";
} elseif (!$_POST["add_x"] && $_GET["db"] == "") {
	echo "<input type='image' name='add' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'>\n";
}
?>
</form>
