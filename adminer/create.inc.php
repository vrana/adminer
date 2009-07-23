<?php
$partition_by = array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST');

$referencable_primary = referencable_primary($_GET["create"]);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[idf_escape($table_name) . "." . idf_escape($field["field"])] = $table_name;
}

if (strlen($_GET["create"])) {
	$orig_fields = fields($_GET["create"]);
}

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	$auto_increment_index = " PRIMARY KEY";
	// don't overwrite primary key by auto_increment
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
		$type_field = (isset($types[$field["type"]]) ? $field : $referencable_primary[$foreign_keys[$field["type"]]]);
		if (strlen($field["field"]) && $type_field) {
			$fields[] = "\n" . (strlen($_GET["create"]) ? (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) . " " : "ADD ") : "  ")
				. idf_escape($field["field"]) . process_type($type_field)
				. ($field["null"] ? " NULL" : " NOT NULL") // NULL for timestamp
				. (strlen($_GET["create"]) && strlen($field["orig"]) && isset($orig_fields[$field["orig"]]["default"]) && $field["type"] != "timestamp" ? " DEFAULT " . $dbh->quote($orig_fields[$field["orig"]]["default"]) : "") //! timestamp
				. ($key == $_POST["auto_increment_col"] ? " AUTO_INCREMENT$auto_increment_index" : "")
				. " COMMENT " . $dbh->quote($field["comment"])
				. (strlen($_GET["create"]) ? " $after" : "")
			;
			$after = "AFTER " . idf_escape($field["field"]);
			if (!isset($types[$field["type"]])) {
				$fields[] = (strlen($_GET["create"]) ? " ADD" : "") . " FOREIGN KEY (" . idf_escape($field["field"]) . ") REFERENCES " . idf_escape($foreign_keys[$field["type"]]) . " (" . idf_escape($type_field["field"]) . ")";
			}
		} elseif (strlen($field["orig"])) {
			$fields[] = "\nDROP " . idf_escape($field["orig"]);
		}
	}
	$status = ($_POST["Engine"] ? "ENGINE=" . $dbh->quote($_POST["Engine"]) : "")
		. ($_POST["Collation"] ? " COLLATE " . $dbh->quote($_POST["Collation"]) : "")
		. (strlen($_POST["Auto_increment"]) ? " AUTO_INCREMENT=" . intval($_POST["Auto_increment"]) : "")
		. " COMMENT=" . $dbh->quote($_POST["Comment"])
	;
	if (in_array($_POST["partition_by"], $partition_by)) {
		$partitions = array();
		if ($_POST["partition_by"] == 'RANGE' || $_POST["partition_by"] == 'LIST') {
			foreach (array_filter($_POST["partition_names"]) as $key => $val) {
				$value = $_POST["partition_values"][$key];
				$partitions[] = "\nPARTITION " . idf_escape($val) . " VALUES " . ($_POST["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . (strlen($value) ? " ($value)" : " MAXVALUE"); //! SQL injection
			}
		}
		$status .= "\nPARTITION BY $_POST[partition_by]($_POST[partition])" . ($partitions // $_POST["partition"] can be expression, not only column
			? " (" . implode(",", $partitions) . "\n)"
			: ($_POST["partitions"] ? " PARTITIONS " . intval($_POST["partitions"]) : "")
		);
	} elseif ($dbh->server_info >= 5.1 && strlen($_GET["create"])) {
		$status .= "\nREMOVE PARTITIONING";
	}
	$location = $SELF . "table=" . urlencode($_POST["name"]);
	if (strlen($_GET["create"])) {
		query_redirect("ALTER TABLE " . idf_escape($_GET["create"]) . implode(",", $fields) . ",\nRENAME TO " . idf_escape($_POST["name"]) . ",\n$status", $location, lang('Table has been altered.'));
	} else {
		$path = preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]);
		setcookie("adminer_engine", $_POST["Engine"], gmmktime(0, 0, 0, gmdate("n") + 1), $path);
		query_redirect("CREATE TABLE " . idf_escape($_POST["name"]) . " (" . implode(",", $fields) . "\n) $status", $location, lang('Table has been created.'));
	}
}

page_header((strlen($_GET["create"]) ? lang('Alter table') : lang('Create table')), $error, array("table" => $_GET["create"]), $_GET["create"]);

$engines = array();
$result = $dbh->query("SHOW ENGINES");
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
	if ($dbh->server_info >= 5.1) {
		$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . $dbh->quote($_GET["db"]) . " AND TABLE_NAME = " . $dbh->quote($_GET["create"]);
		$result = $dbh->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
		list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
		$result->free();
		$row["partition_names"] = array();
		$row["partition_values"] = array();
		$result = $dbh->query("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
		while ($row1 = $result->fetch_assoc()) {
			$row["partition_names"][] = $row1["PARTITION_NAME"];
			$row["partition_values"][] = $row1["PARTITION_DESCRIPTION"];
		}
		$result->free();
		$row["partition_names"][] = "";
	}
} else {
	$row = array(
		"Engine" => $_COOKIE["adminer_engine"],
		"fields" => array(array("field" => "")),
		"partition_names" => array(""),
	);
}
$collations = collations();

$suhosin = floor(extension_loaded("suhosin") ? (min(ini_get("suhosin.request.max_vars"), ini_get("suhosin.post.max_vars")) - 13) / 8 : 0);
if ($suhosin && count($row["fields"]) > $suhosin) {
	echo "<p class='error'>" . htmlspecialchars(lang('Maximum number of allowed fields exceeded. Please increase %s and %s.', 'suhosin.post.max_vars', 'suhosin.request.max_vars')) . "\n";
}
?>

<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo htmlspecialchars($row["name"]); ?>">
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)<?php echo optionlist($engines, $row["Engine"]); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)<?php echo optionlist($collations, $row["Collation"]); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>">
<table cellspacing="0" id="edit-fields">
<?php $column_comments = edit_fields($row["fields"], $collations, "TABLE", $suhosin, $foreign_keys); ?>
</table>
<p>
<?php echo lang('Auto Increment'); ?>: <input name="Auto_increment" size="6" value="<?php echo intval($row["Auto_increment"]); ?>">
<?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo htmlspecialchars($row["Comment"]); ?>" maxlength="60">
<script type="text/javascript">// <![CDATA[
document.write('<label><input type="checkbox"<?php if ($column_comments) { ?> checked="checked"<?php } ?> onclick="column_comments_click(this.checked);"><?php echo lang('Show column comments'); ?></label>');
// ]]></script>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php
if ($dbh->server_info >= 5.1) {
	$partition_table = ereg('RANGE|LIST', $row["partition_by"]);
	?>
<fieldset><legend><?php echo lang('Partition by'); ?></legend>
<p>
<select name="partition_by" onchange="partition_by_change(this);"><option><?php echo optionlist($partition_by, $row["partition_by"]); ?></select>
(<input name="partition" value="<?php echo htmlspecialchars($row["partition"]); ?>">)
<?php echo lang('Partitions'); ?>: <input name="partitions" size="2" value="<?php echo htmlspecialchars($row["partitions"]); ?>"<?php echo ($partition_table || !$row["partition_by"] ? " class='hidden'" : ""); ?>>
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th><?php echo lang('Partition name'); ?><th><?php echo lang('Values'); ?></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr>';
	echo '<td><input name="partition_names[]" value="' . htmlspecialchars($val) . '"' . ($key == count($row["partition_names"]) - 1 ? ' onchange="partition_name_change(this);"' : '') . '>';
	echo '<td><input name="partition_values[]" value="' . htmlspecialchars($row["partition_values"][$key]) . '">';
}
?>
</table>
</fieldset>
<?php } ?>
</form>
