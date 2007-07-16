<?php
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
			//! detect changes
			if (strlen($field["field"]) && isset($types[$field["type"]])) {
				$fields[] = (!strlen($_GET["create"]) ? "" : (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) . " " : "ADD "))
					. idf_escape($field["field"]) . process_type($field)
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
	ksort($row["fields"]);
	if (!$_POST["add"]) {
		echo "<p class='error'>" . lang('Unable to operate table') . ": " . htmlspecialchars($error) . "</p>\n";
		$row["fields"] = array_values($row["fields"]);
	} else {
		array_splice($row["fields"], key($_POST["add"]), 0, array(array()));
	}
	if ($row["auto_increment"]) {
		$row["fields"][$row["auto_increment"] - 1]["auto_increment"] = true;
	}
} elseif (strlen($_GET["create"])) {
	$row = table_status($_GET["create"]);
	if ($row["Engine"] == "InnoDB") {
		$row["Comment"] = preg_replace('~(?:(.+); )?InnoDB free: .*~', '\\1', $row["Comment"]);
	}
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
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)</option><?php echo optionlist($engines, $row["Engine"]); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $row["Collation"]); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>" />
</p>
<?php $column_comments = edit_fields($row["fields"], $collations); ?>
<p><?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo htmlspecialchars($row["Comment"]); ?>" maxlength="60" />
<script type="text/javascript">
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
