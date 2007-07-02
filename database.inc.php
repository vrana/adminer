<?php
if ($_POST) {
	if ($_POST["drop"]) {
		if (mysql_query("DROP DATABASE " . idf_escape($_GET["db"]))) {
			$_SESSION["message"] = lang('Database has been dropped.');
			$location = substr(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF) . (SID ? SID . "&" : ""), 0, -1);
			header("Location: " . (strlen($location) ? $location : "."));
			exit;
		}
	} elseif ($_GET["db"] !== $_POST["name"]) {
		if (mysql_query("CREATE DATABASE " . idf_escape($_POST["name"]) . ($_POST["collation"] ? " COLLATE '" . mysql_real_escape_string($_POST["collation"]) . "'" : ""))) {
			if (!strlen($_GET["db"])) {
				$_SESSION["message"] = lang('Database has been created.');
				header("Location: " . substr(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF) . "db=" . urlencode($_POST["name"]) . "&" . (SID ? SID . "&" : ""), 0, -1));
				exit;
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
				$_SESSION["message"] = lang('Database has been renamed.');
				header("Location: " . substr(preg_replace('~(\\?)db=[^&]*&|&db=[^&]*~', '\\1', $SELF) . "db=" . urlencode($_POST["name"]) . "&" . (SID ? SID . "&" : ""), 0, -1));
				exit;
			}
		}
	} elseif (!$_POST["collation"] || mysql_query("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE '" . mysql_real_escape_string($_POST["collation"]) . "'")) {
		$_SESSION["message"] = ($_POST["collation"] ? lang('Database has been altered.') : '');
		header("Location: " . substr($SELF . (SID ? SID . "&" : ""), 0, -1));
		exit;
	}
	$eror = mysql_error();
}

page_header(strlen($_GET["db"]) ? lang('Alter database') . ": " . htmlspecialchars($_GET["db"]) : lang('Create database'));
echo "<h2>" . (strlen($_GET["db"]) ? lang('Alter database') . ": " . htmlspecialchars($_GET["db"]) : lang('Create database')) . "</h2>\n";

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to operate database') . ": " . htmlspecialchars($error) . "</p>\n";
	$name = $_POST["name"];
	$collate = $_POST["collate"];
} else {
	$name = $_GET["db"];
	$collate = array(); //! take from SHOW CREATE DATABASE
}
?>
<form action="" method="post"><div>
<input name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="64" />
<select name="collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist(collations(), $collate, "not_vals"); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["db"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</div></form>
