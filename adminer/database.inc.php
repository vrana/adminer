<?php
if ($_POST && !$error) {
	if ($_POST["drop"]) {
		unset($_SESSION["databases"][$_GET["server"]]);
		query_redirect("DROP DATABASE " . idf_escape($_GET["db"]), substr(preg_replace('~db=[^&]*&~', '', $SELF), 0, -1), lang('Database has been dropped.'));
	} elseif ($_GET["db"] !== $_POST["name"]) {
		unset($_SESSION["databases"][$_GET["server"]]);
		$dbs = explode("\n", str_replace("\r", "", $_POST["name"]));
		$failed = false;
		foreach ($dbs as $db) {
			if (count($dbs) == 1 || strlen($db)) {
				if (!queries("CREATE DATABASE " . idf_escape($db) . ($_POST["collation"] ? " COLLATE '" . $dbh->escape_string($_POST["collation"]) . "'" : ""))) {
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
		if (!$_POST["collation"]) {
			redirect(substr($SELF, 0, -1));
		}
		query_redirect("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE '" . $dbh->escape_string($_POST["collation"]) . "'", substr($SELF, 0, -1), lang('Database has been altered.'));
	}
}
page_header(strlen($_GET["db"]) ? lang('Alter database') : lang('Create database'), $error, array(), $_GET["db"]);

$collations = collations();
$name = $_GET["db"];
$collate = array();
if ($_POST) {
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} else {
	if (!strlen($_GET["db"])) {
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
			$collate = $collations[$match[1]][0];
		}
		$result->free();
	}
}
?>

<form action="" method="post">
<p>
<input name="name" value="<?php echo htmlspecialchars($name); ?>" maxlength="64" />
<select name="collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $collate); ?></select>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["db"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
</form>
