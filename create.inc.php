<?php
if (strlen($_GET["create"])) {
	$orig_fields = fields($_GET["create"]);
}

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	if ($_POST["drop"]) {
		query_redirect("DROP TABLE " . idf_escape($_GET["create"]), substr($SELF, 0, -1), lang('Table has been dropped.'));
	} else {
		$auto_increment_index = " PRIMARY KEY";
		if (strlen($_GET["create"]) && strlen($_POST["fields"][$_POST["auto_increment_col"]]["orig"])) {
			foreach (indexes($_GET["create"]) as $index) {
				foreach ($index["columns"] as $column) {
					if ($column === $_POST["fields"][$_POST["auto_increment_col"]]["orig"]) {
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
					. idf_escape($field["field"]) . process_type($field)
					. ($field["null"] ? " NULL" : " NOT NULL") // NULL for timestamp
					. (strlen($_GET["create"]) && strlen($field["orig"]) && strlen($orig_fields[$field["orig"]]["default"]) && $field["type"] != "timestamp" ? " DEFAULT '" . $mysql->escape_string($orig_fields[$field["orig"]]["default"]) . "'" : "")
					. ($key == $_POST["auto_increment_col"] ? " AUTO_INCREMENT$auto_increment_index" : "")
					. " COMMENT '" . $mysql->escape_string($field["comment"]) . "'"
					. (strlen($_GET["create"]) ? " $after" : "")
				;
				$after = "AFTER " . idf_escape($field["field"]);
			} elseif (strlen($field["orig"])) {
				$fields[] = "DROP " . idf_escape($field["orig"]);
			}
		}
		$status = ($_POST["Engine"] ? " ENGINE='" . $mysql->escape_string($_POST["Engine"]) . "'" : "")
			. ($_POST["Collation"] ? " COLLATE '" . $mysql->escape_string($_POST["Collation"]) . "'" : "")
			. (strlen($_POST["Auto_increment"]) ? " AUTO_INCREMENT=" . intval($_POST["Auto_increment"]) : "")
			. " COMMENT='" . $mysql->escape_string($_POST["Comment"]) . "'"
		;
		$location = $SELF . "table=" . urlencode($_POST["name"]);
		if (strlen($_GET["create"])) {
			query_redirect("ALTER TABLE " . idf_escape($_GET["create"]) . " " . implode(", ", $fields) . ", RENAME TO " . idf_escape($_POST["name"]) . ", $status", $location, lang('Table has been altered.'));
		} else {
			query_redirect("CREATE TABLE " . idf_escape($_POST["name"]) . " (" . implode(", ", $fields) . ")$status", $location, lang('Table has been created.'));
		}
	}
}
page_header((strlen($_GET["create"]) ? lang('Alter table') : lang('Create table')), $error, array("table" => $_GET["create"]), $_GET["create"]);

$engines = array();
$result = $mysql->query("SHOW ENGINES");
while ($row = $result->fetch_assoc()) {
	if ($row["Support"] == "YES" || $row["Support"] == "DEFAULT") {
		$engines[] = $row["Engine"];
	}
}
$result->free();

if ($_POST) {
	$row = $_POST;
	if ($row["auto_increment_col"]) {
		$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
	}
	process_fields($row["fields"]);
} elseif (strlen($_GET["create"])) {
	$row = table_status($_GET["create"]);
	table_comment($row);
	$row["name"] = $_GET["create"];
	$row["fields"] = array_values($orig_fields);
} else {
	$row = array("fields" => array(array("field" => "")));
}
$collations = collations();
?>

<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>" />
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist($engines, $row["Engine"]); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $row["Collation"]); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>" />
</p>
<table border="0" cellspacing="0" cellpadding="2">
<?php $column_comments = edit_fields($row["fields"], $collations); ?>
</table>
<?php echo type_change(count($row["fields"])); ?>
<p>
<?php echo lang('Auto Increment'); ?>: <input name="Auto_increment" size="4" value="<?php echo intval($row["Auto_increment"]); ?>" />
<?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo htmlspecialchars($row["Comment"]); ?>" maxlength="60" />
<script type="text/javascript">// <![CDATA[
document.write('<label><input type="checkbox"<?php if ($column_comments) { ?> checked="checked"<?php } ?> onclick="column_comments_click(this.checked);" /><?php echo lang('Show column comments'); ?></label>');
function column_comments_click(checked) {
	var trs = document.getElementsByTagName('tr');
	for (var i=0; i < trs.length; i++) {
		trs[i].getElementsByTagName('td')[5].style.display = (checked ? '' : 'none');
	}
}
<?php if (!$column_comments) { ?>column_comments_click(false);<?php } ?>
// ]]></script>
</p>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (strlen($_GET["create"])) { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?>');" /><?php } ?>
</p>
</form>
