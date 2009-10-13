<?php
if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	if ($_POST["drop"]) {
		unset($_SESSION["databases"][$_GET["server"]]);
		query_redirect("DROP DATABASE " . idf_escape(DB), substr(preg_replace('~db=[^&]*&~', '', ME), 0, -1), lang('Database has been dropped.'));
	} elseif (DB !== $_POST["name"]) {
		// create or rename database
		unset($_SESSION["databases"][$_GET["server"]]); // clear cache
		$dbs = explode("\n", str_replace("\r", "", $_POST["name"]));
		$failed = false;
		$last = "";
		foreach ($dbs as $db) {
			if (count($dbs) == 1 || strlen($db)) { // ignore empty lines but always try to create single database
				if (!queries("CREATE DATABASE " . idf_escape($db) . ($_POST["collation"] ? " COLLATE " . $connection->quote($_POST["collation"]) : ""))) {
					$failed = true;
				}
				$last = $db;
			}
		}
		if (query_redirect(queries(), ME . "db=" . urlencode($last), lang('Database has been created.'), !strlen(DB), false, $failed)) {
			$result = $connection->query("SHOW TABLES");
			while ($row = $result->fetch_row()) {
				if (!queries("RENAME TABLE " . idf_escape($row[0]) . " TO " . idf_escape($_POST["name"]) . "." . idf_escape($row[0]))) {
					break;
				}
			}
			if (!$row) {
				queries("DROP DATABASE " . idf_escape(DB));
			}
			queries_redirect(preg_replace('~db=[^&]*&~', '', ME) . "db=" . urlencode($_POST["name"]), lang('Database has been renamed.'), !$row);
		}
	} else {
		// alter database
		if (!$_POST["collation"]) {
			redirect(substr(ME, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE " . $connection->quote($_POST["collation"]), substr(ME, 0, -1), lang('Database has been altered.'));
	}
}

page_header(strlen(DB) ? lang('Alter database') : lang('Create database'), $error, array(), DB);

$collations = collations();
$name = DB;
$collate = array();
if ($_POST) {
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} elseif (!strlen(DB)) {
	// propose database name with limited privileges
	$result = $connection->query("SHOW GRANTS");
	while ($row = $result->fetch_row()) {
		if (preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\\.\\*)?~', $row[0], $match) && $match[1]) {
			$name = stripcslashes(idf_unescape($match[2]));
			break;
		}
	}
} elseif (($result = $connection->query("SHOW CREATE DATABASE " . idf_escape(DB)))) {
	$create = $connection->result($result, 1);
	if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
		$collate = $match[1];
	} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
		// default collation
		$collate = $collations[$match[1]][0];
	}
}
?>

<form action="" method="post">
<p>
<?php echo ($_POST["add_x"] || strpos($name, "\n")
	? '<textarea name="name" rows="10" cols="40">' . h($name) . '</textarea><br>'
	: '<input name="name" value="' . h($name) . '" maxlength="64">'
) . "\n"; ?>
<?php echo html_select("collation", array("" => "(" . lang('collation') . ")") + $collations, $collate); ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if (strlen(DB)) {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'$confirm>\n";
} elseif (!$_POST["add_x"]) {
	echo "<input type='image' name='add' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'>\n";
}
?>
</form>
