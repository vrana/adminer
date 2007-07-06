<?php
if ($_POST) {
	if ($_POST["drop"]) {
		if (mysql_query("DROP DATABASE " . idf_escape($_GET["db"]))) {
			redirect(substr(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF), 0, -1), lang('Database has been dropped.'));
		}
	} elseif ($_GET["db"] !== $_POST["name"]) {
		if (mysql_query("CREATE DATABASE " . idf_escape($_POST["name"]) . ($_POST["collation"] ? " COLLATE '" . mysql_real_escape_string($_POST["collation"]) . "'" : ""))) {
			if (!strlen($_GET["db"])) {
				redirect(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF) . "db=" . urlencode($_POST["name"]), lang('Database has been created.'));
			}
			$result = mysql_query("SHOW TABLES");
			while ($row = mysql_fetch_row($result)) {
				if (!mysql_query("RENAME TABLE " . idf_escape($row[0]) . " TO " . idf_escape($_POST["name"]) . "." . idf_escape($row[0]))) {
					break;
				}
			}
			mysql_free_result($result);
			if (!$row) {
				mysql_query("DROP DATABASE " . idf_escape($_GET["db"]));
				redirect(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF) . "db=" . urlencode($_POST["name"]), lang('Database has been renamed.'));
			}
		}
	} elseif (!$_POST["collation"] || mysql_query("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE '" . mysql_real_escape_string($_POST["collation"]) . "'")) {
		redirect(substr($SELF, 0, -1), ($_POST["collation"] ? lang('Database has been altered.') : null));
	}
	$error = mysql_error();
}

page_header(strlen($_GET["db"]) ? lang('Alter database') . ": " . htmlspecialchars($_GET["db"]) : lang('Create database'));

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to operate database') . ": " . htmlspecialchars($error) . "</p>\n";
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} else {
	$name = $_GET["db"];
	$collate = array();
	if (strlen($_GET["db"]) && ($result = mysql_query("SHOW CREATE DATABASE " . idf_escape($_GET["db"])))) {
		if (preg_match('~ COLLATE ([^ ]+)~', mysql_result($result, 0, 1), $match)) {
			$collate = $match[1];
		}
		mysql_free_result($result);
	}
}
?>
<form action="" method="post"><div>
<input name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="64" />
<select name="collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist(collations(), $collate, "not_vals"); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["db"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</div></form>
