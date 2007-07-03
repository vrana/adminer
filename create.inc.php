<?php
$types = array("int"); //!
if ($_POST) {
	if ($_POST["drop"]) {
		$query = "DROP TABLE " . idf_escape($_GET["create"]);
		$message = lang('Table has been dropped.');
	} else {
		$fields = array();
		ksort($_POST["fields"]);
		foreach ($_POST["fields"] as $key => $field) {
			if (strlen($field["name"]) && in_array($field["type"], $types)) {
				$length = ($field["length"] ? "(" . intval($field["length"]) . ")" : ""); //! decimal, enum and set lengths
				$fields[] = idf_escape($field["name"]) . " " . $field["type"] . $length . ($field["not_null"] ? " NOT NULL" : "") . ($field["auto_increment"] ? " AUTO_INCREMENT" : "");
			}
		}
		$status = ($_POST["Engine"] ? " ENGINE='" . mysql_real_escape_string($_POST["Engine"]) . "'" : "") . ($_POST["Collation"] ? " COLLATE '" . mysql_real_escape_string($_POST["Collation"]) . "'" : "");
		if (strlen($_GET["create"])) {
			$query = "ALTER TABLE " . idf_escape($_GET["create"]) . " RENAME TO " . idf_escape($_POST["name"]) . ", $status";
			$message = lang('Table has been altered.');
		} else {
			$query = "CREATE TABLE " . idf_escape($_POST["name"]) . " (" . implode(", ", $fields) . ")$status";
			$message = lang('Table has been created.');
		}
	}
	if (mysql_query($query)) {
		redirect(($_POST["drop"] ? substr($SELF, 0, -1) : $SELF . "table=" . urlencode($_POST["name"])), $message);
	}
	$error = mysql_error();
}
page_header(strlen($_GET["create"]) ? lang('Alter table') . ': ' . htmlspecialchars($_GET["create"]) : lang('Create table'));
echo "<h2>" . (strlen($_GET["create"]) ? lang('Alter table') . ': ' . htmlspecialchars($_GET["create"]) : lang('Create table')) . "</h2>\n";

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to operate table') . ": " . htmlspecialchars($error) . "</p>\n";
	$row = $_POST;
	//! prefill fields
} elseif (strlen($_GET["create"])) {
	$row = mysql_fetch_assoc(mysql_query("SHOW TABLE STATUS LIKE '" . mysql_real_escape_string($_GET["create"]) . "'"));
	$row["name"] = $_GET["create"];
	//! prefill fields
}
//! collate columns, references, indexes, unsigned
?>
<form action="" method="post">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>" />
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist(engines(), $row["Engine"], "not_vals"); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist(collations(), $row["Collation"], "not_vals"); ?></select>
</p>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Name'); ?></th><td><?php echo lang('Type'); ?></td><td><?php echo lang('Length'); ?></td><td><?php echo lang('NOT NULL'); ?></td><td><?php echo lang('AUTO_INCREMENT'); ?></td></tr></thead>
<tr>
<th><input name="fields[0][name]" maxlength="64" /></th>
<td><select name="fields[0][type]"><?php echo optionlist($types, array(), "not_vals"); ?></select></td>
<td><input name="fields[0][length]" size="3" /></td>
<td><input type="checkbox" name="fields[0][not_null]" value="1" /></td>
<td><input type="checkbox" name="fields[0][auto_increment]" value="1" /></td>
</tr>
<?php //! JavaScript for next rows ?>
</table>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["create"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</p>
</form>
