<?php
if ($_POST && !$error && !isset($_POST["add_x"])) { // add is an image and PHP changes add.x to add_x
	if ($_POST["drop"]) {
		unset($_SESSION["databases"][$_GET["server"]]);
		query_redirect("DROP DATABASE " . idf_escape($_GET["db"]), substr(preg_replace('~db=[^&]*&~', '', $SELF), 0, -1), lang('Database has been dropped.'));
	} elseif ($_GET["db"] !== $_POST["name"]) {
		// create or rename database
		unset($_SESSION["databases"][$_GET["server"]]); // clear cache
		$dbs = explode("\n", str_replace("\r", "", $_POST["name"]));
		$failed = false;
		foreach ($dbs as $db) {
			if (count($dbs) == 1 || strlen($db)) { // ignore empty lines but always try to create single database
				if (!queries("CREATE DATABASE " . idf_escape($db) . ($_POST["collation"] ? " COLLATE " . $dbh->quote($_POST["collation"]) : ""))) {
					$failed = true;
				}
				$last = $db;
			}
		}
		if (query_redirect(queries(), $SELF . "db=" . urlencode($last), lang('Database has been created.'), !strlen($_GET["db"]), false, $failed)) {
			$result = $dbh->query("SHOW TABLES");
			while ($row = $result->fetch_row()) {
				if (!queries("RENAME TABLE " . idf_escape($row[0]) . " TO " . idf_escape($_POST["name"]) . "." . idf_escape($row[0]))) {
					break;
				}
			}
			$result->free();
			if (!$row) {
				queries("DROP DATABASE " . idf_escape($_GET["db"]));
			}
			query_redirect(queries(), preg_replace('~db=[^&]*&~', '', $SELF) . "db=" . urlencode($_POST["name"]), lang('Database has been renamed.'), !$row, false, $row);
		}
	} else {
		// alter database
		if (!$_POST["collation"]) {
			redirect(substr($SELF, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE " . $dbh->quote($_POST["collation"]), substr($SELF, 0, -1), lang('Database has been altered.'));
	}
}
page_header(strlen($_GET["db"]) ? lang('Alter database') : lang('Create database'), $error, array(), $_GET["db"]);

$collations = collations();
$name = $_GET["db"];
$collate = array();
if ($_POST) {
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} elseif (!strlen($_GET["db"])) {
	// propose database name with limited privileges
	$result = $dbh->query("SHOW GRANTS");
	while ($row = $result->fetch_row()) {
		if (preg_match('~ ON (`(([^\\\\`]+|``|\\\\.)*)%`\\.\\*)?~', $row[0], $match) && $match[1]) {
			$name = stripcslashes(idf_unescape($match[2]));
			break;
		}
	}
	$result->free();
} elseif (($result = $dbh->query("SHOW CREATE DATABASE " . idf_escape($_GET["db"])))) {
	$create = $dbh->result($result, 1);
	if (preg_match('~ COLLATE ([^ ]+)~', $create, $match)) {
		$collate = $match[1];
	} elseif (preg_match('~ CHARACTER SET ([^ ]+)~', $create, $match)) {
		// default collation
		$collate = $collations[$match[1]][0];
	}
	$result->free();
}
?>

<form action="" method="post">
<p>
<?php echo ($_POST["add_x"] ? '<textarea name="name" rows="10" cols="40">' . htmlspecialchars($name) . '</textarea><br />' : '<input name="name" value="' . htmlspecialchars($name) . '" maxlength="64" />') . "\n"; ?>
<select name="collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $collate); ?></select>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php
if (strlen($_GET["db"])) {
	echo "<input type='submit' name='drop' value='" . lang('Drop') . "'$confirm />\n";
} elseif (!$_POST["add_x"]) {
	echo "<input type='image' name='add' src='../adminer/plus.gif' alt='+' title='" . lang('Add next') . "' />\n";
}
?>
</p>
</form>
