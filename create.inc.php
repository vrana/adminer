<?php
$types = types();
$unsigned = array("", "unsigned", "zerofill", "unsigned zerofill");
if ($_POST && !$_POST["add"]) {
	if ($_POST["drop"]) {
		$query = "DROP TABLE " . idf_escape($_GET["create"]);
		$message = lang('Table has been dropped.');
	} else {
		$auto_increment_index = " PRIMARY KEY";
		if (strlen($_GET["create"]) && strlen($_POST["fields"][$_POST["auto_increment"]]["orig"])) {
			foreach (indexes($_GET["create"]) as $index) {
				foreach ($index["columns"] as $column) {
					if ($column == $_POST["fields"][$_POST["auto_increment"]]["orig"]) {
						$auto_increment_index = "";
						break 2;
					}
				}
				if ($index["type"] == "PRIMARY") {
					$auto_increment_index = " UNIQUE";
				}
			}
		}
		$fields = array();
		ksort($_POST["fields"]);
		foreach ($_POST["fields"] as $key => $field) {
			if (strlen($field["field"]) && isset($types[$field["type"]])) {
				$fields[] = (!strlen($_GET["create"]) ? "" : (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) . " " : "ADD "))
					. idf_escape($field["field"]) . " $field[type]"
					. ($field["length"] ? "($field[length])" : "")
					. (preg_match('~int|float|double|decimal~', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
					. (preg_match('~char|text~', $field["type"]) && $field["collation"] ? " COLLATE '" . mysql_real_escape_string($field["collation"]) . "'" : "")
					. ($field["null"] ? "" : " NOT NULL")
					. ($key == $_POST["auto_increment"] ? " AUTO_INCREMENT$auto_increment_index" : "")
				;
			} elseif (strlen($field["orig"])) {
				$fields[] = "DROP " . idf_escape($field["orig"]);
			}
		}
		$status = ($_POST["Engine"] ? " ENGINE='" . mysql_real_escape_string($_POST["Engine"]) . "'" : "") . ($_POST["Collation"] ? " COLLATE '" . mysql_real_escape_string($_POST["Collation"]) . "'" : "");
		if (strlen($_GET["create"])) {
			$query = "ALTER TABLE " . idf_escape($_GET["create"]) . " " . implode(", ", $fields) . ", RENAME TO " . idf_escape($_POST["name"]) . ", $status";
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

if ($_POST) {
	if (!$_POST["add"]) {
		echo "<p class='error'>" . lang('Unable to operate table') . ": " . htmlspecialchars($error) . "</p>\n";
	}
	$row = $_POST;
} elseif (strlen($_GET["create"])) {
	$row = mysql_fetch_assoc(mysql_query("SHOW TABLE STATUS LIKE '" . mysql_real_escape_string($_GET["create"]) . "'"));
	$row["name"] = $_GET["create"];
	$row["fields"] = fields($_GET["create"]);
} else {
	$row = array("fields" => array());
}
//! default, comments
$collations = collations();
?>
<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>" />
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist(engines(), $row["Engine"], "not_vals"); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $row["Collation"], "not_vals"); ?></select>
</p>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Name'); ?></th><td><?php echo lang('Type'); ?></td><td><?php echo lang('Length'); ?></td><td><?php echo lang('Options'); ?></td><td><?php echo lang('NULL'); ?></td><td><input type="radio" name="auto_increment" value="" /><?php echo lang('Auto-increment'); ?></td></tr></thead>
<?php
$i=1;
foreach ($row["fields"] as $field) {
	if (strlen($field["field"]) || strlen($field["orig"])) {
		?>
<tr>
<th><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /></th>
<td><select name="fields[<?php echo $i; ?>][type]" onchange="type_change(this);"><?php echo optionlist(array_keys($types), $field["type"], "not_vals"); ?></select></td>
<td><input name="fields[<?php echo $i; ?>][length]" value="<?php echo htmlspecialchars($field["length"]); ?>" size="3" /></td>
<td><select name="fields[<?php echo $i; ?>][collation]"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $field["collation"], "not_vals"); ?></select> <select name="fields[<?php echo $i; ?>][unsigned]"><?php echo optionlist($unsigned, $field["unsigned"], "not_vals"); ?></select></td>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked="checked"<?php } ?> /></td>
<td><input type="radio" name="auto_increment" value="<?php echo $i; ?>"<?php if ($row["auto_increment"] == $i || $field["extra"] == "auto_increment") { ?> checked="checked"<?php } ?> /></td>
</tr>
<?php
		$i++;
	}
}
//! JavaScript for next rows
?>
<tr>
<th><input name="fields[<?php echo $i; ?>][field]" maxlength="64" /></th>
<td><select name="fields[<?php echo $i; ?>][type]" onchange="type_change(this);"><?php echo optionlist(array_keys($types), array(), "not_vals"); ?></select></td>
<td><input name="fields[<?php echo $i; ?>][length]" size="3" /></td>
<td><select name="fields[<?php echo $i; ?>][collation]"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, array(), "not_vals"); ?></select> <select name="fields[<?php echo $i; ?>][unsigned]"><?php echo optionlist($unsigned, array(), "not_vals"); ?></select></td>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1" /></td>
<td><input type="radio" name="auto_increment" value="<?php echo $i; ?>" /></td>
</tr>
</table>
<script type="text/javascript">
function type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	type.form[name + '[collation]'].style.display = (/char|text/.test(type.form[name + '[type]'].value) ? '' : 'none');
	type.form[name + '[unsigned]'].style.display = (/int|float|double|decimal/.test(type.form[name + '[type]'].value) ? '' : 'none');
}
for (var i=1; <?php echo $i; ?> >= i; i++) {
	document.getElementById('form')['fields[' + i + '][type]'].onchange();
}
</script>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["create"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</p>
<p><input type="submit" name="add" value="<?php echo lang('Add row'); ?>" /></p>
</form>
