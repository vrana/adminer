<?php
$TABLE = $_GET["create"];
$partition_by = array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST');

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[idf_escape($table_name) . "." . idf_escape($field["field"])] = $table_name;
}

$orig_fields = array();
$orig_status = array();
if (strlen($TABLE)) {
	$orig_fields = fields($TABLE);
	$orig_status = table_status($TABLE);
}

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	$auto_increment_index = " PRIMARY KEY";
	// don't overwrite primary key by auto_increment
	if (strlen($TABLE) && $_POST["auto_increment_col"]) {
		foreach (indexes($TABLE) as $index) {
			if (in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"], $index["columns"], true)) {
				$auto_increment_index = "";
				break;
			}
			if ($index["type"] == "PRIMARY") {
				$auto_increment_index = " UNIQUE";
			}
		}
	}
	$fields = "";
	ksort($_POST["fields"]);
	$orig_field = reset($orig_fields);
	$after = "FIRST";
	foreach ($_POST["fields"] as $key => $field) {
		$type_field = (isset($types[$field["type"]]) ? $field : $referencable_primary[$foreign_keys[$field["type"]]]);
		if (strlen($field["field"])) {
			if ($type_field) {
				if (!$field["has_default"]) {
					$field["default"] = null;
				}
				$process_field = process_field($field, $type_field);
				$auto_increment = ($key == $_POST["auto_increment_col"]);
				if ($process_field != process_field($orig_field, $orig_field) || $orig_field["auto_increment"] != $auto_increment) {
					$fields .= "\n" . (strlen($TABLE) ? (strlen($field["orig"]) ? "CHANGE " . idf_escape($field["orig"]) : "ADD") : " ")
						. " $process_field"
						. ($auto_increment ? " AUTO_INCREMENT$auto_increment_index" : "")
						. (strlen($TABLE) ? " $after" : "") . ","
					;
				}
				if (!isset($types[$field["type"]])) {
					$fields .= (strlen($TABLE) ? "\nADD" : "") . " FOREIGN KEY (" . idf_escape($field["field"]) . ") REFERENCES " . idf_escape($foreign_keys[$field["type"]]) . " (" . idf_escape($type_field["field"]) . "),";
				}
			}
			$after = "AFTER " . idf_escape($field["field"]);
			//! drop and create foreign keys with renamed columns
		} elseif (strlen($field["orig"])) {
			$fields .= "\nDROP " . idf_escape($field["orig"]) . ",";
		}
		if (strlen($field["orig"])) {
			$orig_field = next($orig_fields);
		}
	}
	$status = "COMMENT=" . $dbh->quote($_POST["Comment"])
		. ($_POST["Engine"] && $_POST["Engine"] != $orig_status["Engine"] ? " ENGINE=" . $dbh->quote($_POST["Engine"]) : "")
		. ($_POST["Collation"] && $_POST["Collation"] != $orig_status["Collation"] ? " COLLATE " . $dbh->quote($_POST["Collation"]) : "")
		. (strlen($_POST["auto_increment"]) ? " AUTO_INCREMENT=" . intval($_POST["auto_increment"]) : "")
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
	} elseif ($dbh->server_info >= 5.1 && strlen($TABLE)) {
		$status .= "\nREMOVE PARTITIONING";
	}
	$location = ME . "table=" . urlencode($_POST["name"]);
	if (strlen($TABLE)) {
		query_redirect("ALTER TABLE " . idf_escape($TABLE) . "$fields\nRENAME TO " . idf_escape($_POST["name"]) . ",\n$status", $location, lang('Table has been altered.'));
	} else {
		cookie("adminer_engine", $_POST["Engine"]);
		query_redirect("CREATE TABLE " . idf_escape($_POST["name"]) . " (" . substr($fields, 0, -1) . "\n) $status", $location, lang('Table has been created.'));
	}
}

page_header((strlen($TABLE) ? lang('Alter table') : lang('Create table')), $error, array("table" => $TABLE), $TABLE);

$engines = array();
$result = $dbh->query("SHOW ENGINES");
while ($row = $result->fetch_assoc()) {
	if ($row["Support"] == "YES" || $row["Support"] == "DEFAULT") {
		$engines[] = $row["Engine"];
	}
}

$row = array(
	"Engine" => $_COOKIE["adminer_engine"],
	"fields" => array(array("field" => "")),
	"partition_names" => array(""),
);
if ($_POST) {
	$row = $_POST;
	if ($row["auto_increment_col"]) {
		$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
	}
	process_fields($row["fields"]);
} elseif (strlen($TABLE)) {
	$row = $orig_status;
	$row["name"] = $TABLE;
	$row["fields"] = array();
	foreach ($orig_fields as $field) {
		$field["has_default"] = isset($field["default"]);
		if ($field["on_update"]) {
			$field["default"] .= " ON UPDATE $field[on_update]"; // CURRENT_TIMESTAMP
		}
		$row["fields"][] = $field;
	}
	if ($dbh->server_info >= 5.1) {
		$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . $dbh->quote(DB) . " AND TABLE_NAME = " . $dbh->quote($TABLE);
		$result = $dbh->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
		list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
		$row["partition_names"] = array();
		$row["partition_values"] = array();
		$result = $dbh->query("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
		while ($row1 = $result->fetch_assoc()) {
			$row["partition_names"][] = $row1["PARTITION_NAME"];
			$row["partition_values"][] = $row1["PARTITION_DESCRIPTION"];
		}
		$row["partition_names"][] = "";
	}
}
$collations = collations();

$suhosin = floor(extension_loaded("suhosin") ? (min(ini_get("suhosin.request.max_vars"), ini_get("suhosin.post.max_vars")) - 13) / 10 : 0); // 10 - number of fields per row, 13 - number of other fields
if ($suhosin && count($row["fields"]) > $suhosin) {
	echo "<p class='error'>" . h(lang('Maximum number of allowed fields exceeded. Please increase %s and %s.', 'suhosin.post.max_vars', 'suhosin.request.max_vars')) . "\n";
}
// case of engine may differ
foreach ($engines as $engine) {
	if (!strcasecmp($engine, $row["Engine"])) {
		$row["Engine"] = $engine;
		break;
	}
}
?>

<form action="" method="post" id="form">
<p>
<?php echo lang('Table name'); ?>: <input name="name" maxlength="64" value="<?php echo h($row["name"]); ?>">
<select name="Engine"><option value="">(<?php echo lang('engine'); ?>)<?php echo optionlist($engines, $row["Engine"]); ?></select>
<select name="Collation"><option value="">(<?php echo lang('collation'); ?>)<?php echo optionlist($collations, $row["Collation"]); ?></select>
<input type="submit" value="<?php echo lang('Save'); ?>">
</p>
<table cellspacing="0" id="edit-fields">
<?php $column_comments = edit_fields($row["fields"], $collations, "TABLE", $suhosin, $foreign_keys); ?>
</table>
<p>
<?php echo lang('Auto Increment'); ?>: <input name="auto_increment" size="6" value="<?php echo h($row["auto_increment"]); // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids ?>">
<?php echo lang('Comment'); ?>: <input name="Comment" value="<?php echo h($row["Comment"]); ?>" maxlength="60">
<script type="text/javascript">
document.write('<label><input type="checkbox" onclick="column_show(this.checked, 5);"><?php echo lang('Default values'); ?><\/label>');
document.write('<label><input type="checkbox"<?php if ($column_comments) { ?> checked<?php } ?> onclick="column_show(this.checked, 6);"><?php echo lang('Show column comments'); ?><\/label>');
</script>
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
(<input name="partition" value="<?php echo h($row["partition"]); ?>">)
<?php echo lang('Partitions'); ?>: <input name="partitions" size="2" value="<?php echo h($row["partitions"]); ?>"<?php echo ($partition_table || !$row["partition_by"] ? " class='hidden'" : ""); ?>>
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th><?php echo lang('Partition name'); ?><th><?php echo lang('Values'); ?></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr>';
	echo '<td><input name="partition_names[]" value="' . h($val) . '"' . ($key == count($row["partition_names"]) - 1 ? ' onchange="partition_name_change(this);"' : '') . '>';
	echo '<td><input name="partition_values[]" value="' . h($row["partition_values"][$key]) . '">';
}
?>
</table>
</fieldset>
<?php } ?>
</form>
