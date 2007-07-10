<?php
$types = types();
$unsigned = array("", "unsigned", "zerofill", "unsigned zerofill");
if ($_POST && !$error && !$_POST["add"]) {
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
		$after = "FIRST";
		foreach ($_POST["fields"] as $key => $field) {
			if (strlen($field["field"]) && isset($types[$field["type"]])) {
				$fields[] = (!strlen($_GET["create"]) ? "" : (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) . " " : "ADD "))
					. idf_escape($field["field"]) . " $field[type]"
					. ($field["length"] ? "(" . (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $field["length"]) && preg_match_all("~$enum_length~", $field["length"], $matches) ? implode(",", $matches[0]) : intval($field["length"])) . ")" : "")
					. (preg_match('~int|float|double|decimal~', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
					. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " COLLATE '" . $mysql->escape_string($field["collation"]) . "'" : "")
					. ($field["null"] ? "" : " NOT NULL")
					. ($key == $_POST["auto_increment"] ? " AUTO_INCREMENT$auto_increment_index" : "")
					. " COMMENT '" . $mysql->escape_string($field["comment"]) . "'"
					. (strlen($_GET["create"]) && !strlen($field["orig"]) ? $after : "")
				;
				$after = "AFTER " . idf_escape($field["field"]);
			} elseif (strlen($field["orig"])) {
				$fields[] = "DROP " . idf_escape($field["orig"]);
			}
		}
		$status = ($_POST["Engine"] ? " ENGINE='" . $mysql->escape_string($_POST["Engine"]) . "'" : "")
			. ($_POST["Collation"] ? " COLLATE '" . $mysql->escape_string($_POST["Collation"]) . "'" : "")
			. " COMMENT='" . $mysql->escape_string($_POST["Comment"]) . "'"
		;
		if (strlen($_GET["create"])) {
			$query = "ALTER TABLE " . idf_escape($_GET["create"]) . " " . implode(", ", $fields) . ", RENAME TO " . idf_escape($_POST["name"]) . ", $status";
			$message = lang('Table has been altered.');
		} else {
			$query = "CREATE TABLE " . idf_escape($_POST["name"]) . " (" . implode(", ", $fields) . ")$status";
			$message = lang('Table has been created.');
		}
	}
	if ($mysql->query($query)) {
		redirect(($_POST["drop"] ? substr($SELF, 0, -1) : $SELF . "table=" . urlencode($_POST["name"])), $message);
	}
	$error = $mysql->error;
}
page_header(strlen($_GET["create"]) ? lang('Alter table') . ': ' . htmlspecialchars($_GET["create"]) : lang('Create table'));

if ($_POST) {
	$row = $_POST;
	ksort($row["fields"]);
	if (!$_POST["add"]) {
		echo "<p class='error'>" . lang('Unable to operate table') . ": " . htmlspecialchars($error) . "</p>\n";
		$row["fields"] = array_values($row["fields"]);
	} else {
		array_splice($row["fields"], key($_POST["add"]), 0, array(array()));
	}
	if ($row["auto_increment"]) {
		$row["fields"][$row["auto_increment"]]["auto_increment"] = true;
	}
} elseif (strlen($_GET["create"])) {
	$result = $mysql->query("SHOW TABLE STATUS LIKE '" . $mysql->escape_string($_GET["create"]) . "'");
	$row = $result->fetch_assoc();
	$row["name"] = $_GET["create"];
	$row["fields"] = array_values(fields($_GET["create"]));
} else {
	$row = array("fields" => array(array()));
}
$collations = collations();
?>

<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>" />
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist(engines(), $row["Engine"], "not_vals"); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $row["Collation"], "not_vals"); ?></select>
</p>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Name'); ?></th><td><?php echo lang('Type'); ?></td><td><?php echo lang('Length'); ?></td><td><?php echo lang('Options'); ?></td><td><?php echo lang('NULL'); ?></td><td><input type="radio" name="auto_increment" value="" /><?php echo lang('Auto Increment'); ?></td><td id="comment-0"><?php echo lang('Comment'); ?></td><td><input type="submit" name="add[0]" value="<?php echo lang('Add row'); ?>" /></td></tr></thead>
<?php
$column_comments = false;
foreach ($row["fields"] as $i => $field) {
	$i++;
	?>
<tr>
<th><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /></th>
<td><select name="fields[<?php echo $i; ?>][type]" onchange="type_change(this);"><?php echo optionlist(array_keys($types), $field["type"], "not_vals"); ?></select></td>
<td><input name="fields[<?php echo $i; ?>][length]" value="<?php echo htmlspecialchars($field["length"]); ?>" size="3" /></td>
<td><select name="fields[<?php echo $i; ?>][collation]"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $field["collation"], "not_vals"); ?></select> <select name="fields[<?php echo $i; ?>][unsigned]"><?php echo optionlist($unsigned, $field["unsigned"], "not_vals"); ?></select></td>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked="checked"<?php } ?> /></td>
<td><input type="radio" name="auto_increment" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked="checked"<?php } ?> /></td>
<td id="comment-<?php echo $i; ?>"><input name="fields[<?php echo $i; ?>][comment]" value="<?php echo htmlspecialchars($field["comment"]); ?>" maxlength="255" /></td>
<td><input type="submit" name="add[<?php echo $i; ?>]" value="<?php echo lang('Add row'); ?>" /></td>
</tr>
<?php
	if (strlen($field["comment"])) {
		$column_comments = true;
	}
}
//! JavaScript for next rows
?>
</table>
<p><?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo htmlspecialchars($row["Comment"]); ?>" maxlength="60" />
<script type="text/javascript">
function type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	type.form[name + '[collation]'].style.display = (/char|text|enum|set/.test(type.form[name + '[type]'].value) ? '' : 'none');
	type.form[name + '[unsigned]'].style.display = (/int|float|double|decimal/.test(type.form[name + '[type]'].value) ? '' : 'none');
}
for (var i=1; <?php echo count($row["fields"]); ?> >= i; i++) {
	document.getElementById('form')['fields[' + i + '][type]'].onchange();
}

document.write('<label for="column_comments"><input type="checkbox" id="column_comments"<?php if ($column_comments) { ?> checked="checked"<?php } ?> onclick="column_comments_click(this.checked);" /><?php echo lang('Show column comments'); ?></label>');
function column_comments_click(checked) {
	for (var i=0; <?php echo count($row["fields"]); ?> >= i; i++) {
		document.getElementById('comment-' + i).style.display = (checked ? '' : 'none');
	}
}
<?php if (!$column_comments) { ?>column_comments_click(false);<?php } ?>

</script>
</p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["create"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" /><?php } ?>
</p>
</form>
