<?php
$TABLE = $_GET["create"];
$partition_by = array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST');

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$orig_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$orig_status = table_status($TABLE);
}
if ($_POST && !$_POST["fields"]) {
	$_POST["fields"] = array();
}

if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"] && !$_POST["up"] && !$_POST["down"]) {
	if ($_POST["drop"]) {
		query_redirect("DROP TABLE " . table($TABLE), substr(ME, 0, -1), lang('Table has been dropped.'));
	} else {
		$fields = array();
		$all_fields = array();
		$use_all_fields = false;
		$foreign = array();
		ksort($_POST["fields"]);
		$orig_field = reset($orig_fields);
		$after = " FIRST";
		foreach ($_POST["fields"] as $key => $field) {
			$foreign_key = $foreign_keys[$field["type"]];
			$type_field = ($foreign_key !== null ? $referencable_primary[$foreign_key] : $field); //! can collide with user defined type
			if ($field["field"] != "") {
				if (!$field["has_default"]) {
					$field["default"] = null;
				}
				$default = eregi_replace(" *on update CURRENT_TIMESTAMP", "", $field["default"]);
				if ($default != $field["default"]) { // preg_replace $count is available since PHP 5.1.0
					$field["on_update"] = "CURRENT_TIMESTAMP";
					$field["default"] = $default;
				}
				if ($key == $_POST["auto_increment_col"]) {
					$field["auto_increment"] = true;
				}
				$process_field = process_field($field, $type_field);
				$all_fields[] = array($field["orig"], $process_field, $after);
				if ($process_field != process_field($orig_field, $orig_field)) {
					$fields[] = array($field["orig"], $process_field, $after);
					if ($field["orig"] != "" || $after) {
						$use_all_fields = true;
					}
				}
				if ($foreign_key !== null) {
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && $jush != "sqlite" ? "ADD" : " ") . " FOREIGN KEY (" . idf_escape($field["field"]) . ") REFERENCES " . table($foreign_keys[$field["type"]]) . " (" . idf_escape($type_field["field"]) . ")" . (ereg("^($on_actions)\$", $field["on_delete"]) ? " ON DELETE $field[on_delete]" : "");
				}
				$after = " AFTER " . idf_escape($field["field"]);
			} elseif ($field["orig"] != "") {
				$use_all_fields = true;
				$fields[] = array($field["orig"]);
			}
			if ($field["orig"] != "") {
				$orig_field = next($orig_fields);
				if (!$orig_field) {
					$after = "";
				}
			}
		}
		$partitioning = "";
		if (in_array($_POST["partition_by"], $partition_by)) {
			$partitions = array();
			if ($_POST["partition_by"] == 'RANGE' || $_POST["partition_by"] == 'LIST') {
				foreach (array_filter($_POST["partition_names"]) as $key => $val) {
					$value = $_POST["partition_values"][$key];
					$partitions[] = "\nPARTITION " . idf_escape($val) . " VALUES " . ($_POST["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . ($value != "" ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			$partitioning .= "\nPARTITION BY $_POST[partition_by]($_POST[partition])" . ($partitions // $_POST["partition"] can be expression, not only column
				? " (" . implode(",", $partitions) . "\n)"
				: ($_POST["partitions"] ? " PARTITIONS " . (+$_POST["partitions"]) : "")
			);
		} elseif (support("partitioning") && ereg("partitioned", $orig_status["Create_options"])) {
			$partitioning .= "\nREMOVE PARTITIONING";
		}
		$message = lang('Table has been altered.');
		if ($TABLE == "") {
			cookie("adminer_engine", $_POST["Engine"]);
			$message = lang('Table has been created.');
		}
		$name = trim($_POST["name"]);
		queries_redirect(ME . "table=" . urlencode($name), $message, alter_table(
			$TABLE,
			$name,
			($jush == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
			$foreign,
			$_POST["Comment"],
			($_POST["Engine"] && $_POST["Engine"] != $orig_status["Engine"] ? $_POST["Engine"] : ""),
			($_POST["Collation"] && $_POST["Collation"] != $orig_status["Collation"] ? $_POST["Collation"] : ""),
			($_POST["Auto_increment"] != "" ? +$_POST["Auto_increment"] : ""),
			$partitioning
		));
	}
}

page_header(($TABLE != "" ? lang('Alter table') : lang('Create table')), $error, array("table" => $TABLE), $TABLE);

$row = array(
	"Engine" => $_COOKIE["adminer_engine"],
	"fields" => array(array("field" => "", "type" => (isset($types["int"]) ? "int" : (isset($types["integer"]) ? "integer" : "")))),
	"partition_names" => array(""),
);
if ($_POST) {
	$row = $_POST;
	if ($row["auto_increment_col"]) {
		$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
	}
	process_fields($row["fields"]);
} elseif ($TABLE != "") {
	$row = $orig_status;
	$row["name"] = $TABLE;
	$row["fields"] = array();
	if (!$_GET["auto_increment"]) { // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids
		$row["Auto_increment"] = "";
	}
	foreach ($orig_fields as $field) {
		$field["has_default"] = isset($field["default"]);
		if ($field["on_update"]) {
			$field["default"] .= " ON UPDATE $field[on_update]"; // CURRENT_TIMESTAMP
		}
		$row["fields"][] = $field;
	}
	if (support("partitioning")) {
		$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . q(DB) . " AND TABLE_NAME = " . q($TABLE);
		$result = $connection->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
		list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
		$row["partition_names"] = array();
		$row["partition_values"] = array();
		foreach (get_rows("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION") as $row1) {
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

$engines = engines();
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
<?php if ($TABLE == "" && !$_POST) { ?><script type='text/javascript'>document.getElementById('form')['name'].focus();</script><?php } ?>
<?php echo ($engines ? html_select("Engine", array("" => "(" . lang('engine') . ")") + $engines, $row["Engine"]) : ""); ?>
 <?php echo ($collations && !ereg("sqlite|mssql", $jush) ? html_select("Collation", array("" => "(" . lang('collation') . ")") + $collations, $row["Collation"]) : ""); ?>
 <input type="submit" value="<?php echo lang('Save'); ?>">
<table cellspacing="0" id="edit-fields" class="nowrap">
<?php
$comments = ($_POST ? $_POST["comments"] : $row["Comment"] != "");
if (!$_POST && !$comments) {
	foreach ($row["fields"] as $field) {
		if ($field["comment"] != "") {
			$comments = true;
			break;
		}
	}
}
edit_fields($row["fields"], $collations, "TABLE", $suhosin, $foreign_keys, $comments);
?>
</table>
<p>
<?php echo lang('Auto Increment'); ?>: <input name="Auto_increment" size="6" value="<?php echo h($row["Auto_increment"]); ?>">
<label class="jsonly"><input type="checkbox" name="defaults" value="1"<?php echo ($_POST["defaults"] ? " checked" : ""); ?> onclick="columnShow(this.checked, 5);"><?php echo lang('Default values'); ?></label>
<?php echo (support("comment") ? checkbox("comments", 1, $comments, lang('Comment'), "columnShow(this.checked, 6); toggle('Comment'); if (this.checked) this.form['Comment'].focus();", true) . ' <input id="Comment" name="Comment" value="' . h($row["Comment"]) . '" maxlength="60"' . ($comments ? '' : ' class="hidden"') . '>' : ''); ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php if ($_GET["create"] != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"<?php echo confirm(); ?>><?php } ?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<?php
if (support("partitioning")) {
	$partition_table = ereg('RANGE|LIST', $row["partition_by"]);
	print_fieldset("partition", lang('Partition by'), $row["partition_by"]);
	?>
<p>
<?php echo html_select("partition_by", array(-1 => "") + $partition_by, $row["partition_by"], "partitionByChange(this);"); ?>
(<input name="partition" value="<?php echo h($row["partition"]); ?>">)
<?php echo lang('Partitions'); ?>: <input name="partitions" size="2" value="<?php echo h($row["partitions"]); ?>"<?php echo ($partition_table || !$row["partition_by"] ? " class='hidden'" : ""); ?>>
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th><?php echo lang('Partition name'); ?><th><?php echo lang('Values'); ?></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr>';
	echo '<td><input name="partition_names[]" value="' . h($val) . '"' . ($key == count($row["partition_names"]) - 1 ? ' onchange="partitionNameChange(this);"' : '') . '>';
	echo '<td><input name="partition_values[]" value="' . h($row["partition_values"][$key]) . '">';
}
?>
</table>
</div></fieldset>
<?php
}
?>
</form>
