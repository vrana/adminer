<?php
if ($_POST && !$error) {
	if ($_POST["drop"]) {
		if ($mysql->query("DROP DATABASE " . idf_escape($_GET["db"]))) {
			unset($_SESSION["databases"][$_GET["server"]]);
			redirect(substr(preg_replace('~db=[^&]*&~', '', $SELF), 0, -1), lang('Database has been dropped.'));
		}
	} elseif ($_GET["db"] !== $_POST["name"]) {
		if ($mysql->query("CREATE DATABASE " . idf_escape($_POST["name"]) . ($_POST["collation"] ? " COLLATE '" . $mysql->escape_string($_POST["collation"]) . "'" : ""))) {
			unset($_SESSION["databases"][$_GET["server"]]);
			if (!strlen($_GET["db"])) {
				redirect(preg_replace('~db=[^&]*&~', '', $SELF) . "db=" . urlencode($_POST["name"]), lang('Database has been created.'));
			}
			$result = $mysql->query("SHOW TABLES");
			while ($row = $result->fetch_row()) {
				if (!$mysql->query("RENAME TABLE " . idf_escape($row[0]) . " TO " . idf_escape($_POST["name"]) . "." . idf_escape($row[0]))) {
					break;
				}
			}
			$result->free();
			if (!$row) {
				$mysql->query("DROP DATABASE " . idf_escape($_GET["db"]));
				redirect(preg_replace('~db=[^&]*&~', '', $SELF) . "db=" . urlencode($_POST["name"]), lang('Database has been renamed.'));
			}
		}
	} elseif (!$_POST["collation"] || $mysql->query("ALTER DATABASE " . idf_escape($_POST["name"]) . " COLLATE '" . $mysql->escape_string($_POST["collation"]) . "'")) {
		redirect(substr($SELF, 0, -1), ($_POST["collation"] ? lang('Database has been altered.') : null));
	}
	$error = $mysql->error;
}

page_header(strlen($_GET["db"]) ? lang('Alter database') : lang('Create database'), array(), $_GET["db"]);
$collations = collations();

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to operate database') . ": " . htmlspecialchars($error) . "</p>\n";
	$name = $_POST["name"];
	$collate = $_POST["collation"];
} else {
	$name = $_GET["db"];
	$collate = array();
	if (!strlen($_GET["db"])) {
		$result = $mysql->query("SHOW GRANTS");
		while ($row = $result->fetch_row()) {
			if (preg_match('~ ON (`(([^\\\\`]+|``|\\\\.)*)%`\\.\\*)?~', $row[0], $match) && $match[1]) {
				$name = stripcslashes(idf_unescape($match[2]));
				break;
			}
		}
		$result->free();
	} elseif (($result = $mysql->query("SHOW CREATE DATABASE " . idf_escape($_GET["db"])))) {
		$create = $mysql->result($result, 1);
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
<?php if (strlen($_GET["db"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
