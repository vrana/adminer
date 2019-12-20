<?php
$TABLE = $_GET["create"];
$partition_by = array();
foreach (array('HASH', 'LINEAR HASH', 'KEY', 'LINEAR KEY', 'RANGE', 'LIST') as $key) {
	$partition_by[$key] = $key;
}

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$table_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$table_status = table_status($TABLE);
	if (!$table_status) {
		$error = lang('No tables.');
	}
}

$row = $_POST;
$row["fields"] = (array) $row["fields"];
if ($row["auto_increment_col"]) {
	$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
}

if ($_POST) {
	set_adminer_settings(array("comments" => $_POST["comments"], "defaults" => $_POST["defaults"]));
}

if ($_POST && !process_fields($row["fields"]) && !$error) {
	if ($_POST["drop"]) {
		queries_redirect(substr(ME, 0, -1), lang('Table has been dropped.'), drop_tables(array($TABLE)));
	} else {
		$fields = array();
		$all_fields = array();
		$use_all_fields = false;
		$foreign = array();
		$orig_field = reset($orig_fields);
		$after = " FIRST";

		foreach ($row["fields"] as $key => $field) {
			$foreign_key = $foreign_keys[$field["type"]];
			$type_field = ($foreign_key !== null ? $referencable_primary[$foreign_key] : $field); //! can collide with user defined type
			if ($field["field"] != "") {
				if (!$field["has_default"]) {
					$field["default"] = null;
				}
				if ($key == $row["auto_increment_col"]) {
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
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && $jush != "sqlite" ? "ADD" : " ") . format_foreign_key(array(
						'table' => $foreign_keys[$field["type"]],
						'source' => array($field["field"]),
						'target' => array($type_field["field"]),
						'on_delete' => $field["on_delete"],
					));
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
		if ($partition_by[$row["partition_by"]]) {
			$partitions = array();
			if ($row["partition_by"] == 'RANGE' || $row["partition_by"] == 'LIST') {
				foreach (array_filter($row["partition_names"]) as $key => $val) {
					$value = $row["partition_values"][$key];
					$partitions[] = "\n  PARTITION " . idf_escape($val) . " VALUES " . ($row["partition_by"] == 'RANGE' ? "LESS THAN" : "IN") . ($value != "" ? " ($value)" : " MAXVALUE"); //! SQL injection
				}
			}
			$partitioning .= "\nPARTITION BY $row[partition_by]($row[partition])" . ($partitions // $row["partition"] can be expression, not only column
				? " (" . implode(",", $partitions) . "\n)"
				: ($row["partitions"] ? " PARTITIONS " . (+$row["partitions"]) : "")
			);
		} elseif (support("partitioning") && preg_match("~partitioned~", $table_status["Create_options"])) {
			$partitioning .= "\nREMOVE PARTITIONING";
		}

		$message = lang('Table has been altered.');
		if ($TABLE == "") {
			cookie("adminer_engine", $row["Engine"]);
			$message = lang('Table has been created.');
		}
		$name = trim($row["name"]);

		queries_redirect(ME . (support("table") ? "table=" : "select=") . urlencode($name), $message, alter_table(
			$TABLE,
			$name,
			($jush == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
			$foreign,
			($row["Comment"] != $table_status["Comment"] ? $row["Comment"] : null),
			($row["Engine"] && $row["Engine"] != $table_status["Engine"] ? $row["Engine"] : ""),
			($row["Collation"] && $row["Collation"] != $table_status["Collation"] ? $row["Collation"] : ""),
			($row["Auto_increment"] != "" ? number($row["Auto_increment"]) : ""),
			$partitioning
		));
	}
}

page_header(($TABLE != "" ? lang('Alter table') : lang('Create table')), $error, array("table" => $TABLE), h($TABLE));

if (!$_POST) {
	$row = array(
		"Engine" => $_COOKIE["adminer_engine"],
		"fields" => array(array("field" => "", "type" => (isset($types["int"]) ? "int" : (isset($types["integer"]) ? "integer" : "")), "on_update" => "")),
		"partition_names" => array(""),
	);

	if ($TABLE != "") {
		$row = $table_status;
		$row["name"] = $TABLE;
		$row["fields"] = array();
		if (!$_GET["auto_increment"]) { // don't prefill by original Auto_increment for the sake of performance and not reusing deleted ids
			$row["Auto_increment"] = "";
		}
		foreach ($orig_fields as $field) {
			$field["has_default"] = isset($field["default"]);
			$row["fields"][] = $field;
		}

		if (support("partitioning")) {
			$from = "FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = " . q(DB) . " AND TABLE_NAME = " . q($TABLE);
			$result = $connection->query("SELECT PARTITION_METHOD, PARTITION_ORDINAL_POSITION, PARTITION_EXPRESSION $from ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");
			list($row["partition_by"], $row["partitions"], $row["partition"]) = $result->fetch_row();
			$partitions = get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $from AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");
			$partitions[""] = "";
			$row["partition_names"] = array_keys($partitions);
			$row["partition_values"] = array_values($partitions);
		}
	}
}

$collations = collations();
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
<?php if (support("columns") || $TABLE == "") { ?>
<?php echo lang('Table name'); ?>: <input name="name" data-maxlength="64" value="<?php echo h($row["name"]); ?>" autocapitalize="off">
<?php if ($TABLE == "" && !$_POST) { echo script("focus(qs('#form')['name']);"); } ?>
<?php echo ($engines ? "<select name='Engine'>" . optionlist(array("" => "(" . lang('engine') . ")") + $engines, $row["Engine"]) . "</select>" . on_help("getTarget(event).value", 1) . script("qsl('select').onchange = helpClose;") : ""); ?>
 <?php echo ($collations && !preg_match("~sqlite|mssql~", $jush) ? html_select("Collation", array("" => "(" . lang('collation') . ")") + $collations, $row["Collation"]) : ""); ?>
 <input type="submit" value="<?php echo lang('Save'); ?>">
<?php } ?>

<?php if (support("columns")) { ?>
<div class="scrollable">
<table cellspacing="0" id="edit-fields" class="nowrap">
<?php
edit_fields($row["fields"], $collations, "TABLE", $foreign_keys);
?>
</table>
<?php echo script("editFields();"); ?>
</div>
<p>
<?php echo lang('Auto Increment'); ?>: <input type="number" name="Auto_increment" size="6" value="<?php echo h($row["Auto_increment"]); ?>">
<?php echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : adminer_setting("defaults")), lang('Default values'), "columnShow(this.checked, 5)", "jsonly"); ?>
<?php echo (support("comment")
	? checkbox("comments", 1, ($_POST ? $_POST["comments"] : adminer_setting("comments")), lang('Comment'), "editingCommentsClick(this, true);", "jsonly")
		. ' <input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '">'
	: '')
; ?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php } ?>

<?php if ($TABLE != "") { ?><input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $TABLE)); ?><?php } ?>
<?php
if (support("partitioning")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", lang('Partition by'), $row["partition_by"]);
	?>
<p>
<?php echo "<select name='partition_by'>" . optionlist(array("" => "") + $partition_by, $row["partition_by"]) . "</select>" . on_help("getTarget(event).value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;"); ?>
(<input name="partition" value="<?php echo h($row["partition"]); ?>">)
<?php echo lang('Partitions'); ?>: <input type="number" name="partitions" class="size<?php echo ($partition_table || !$row["partition_by"] ? " hidden" : ""); ?>" value="<?php echo h($row["partitions"]); ?>">
<table cellspacing="0" id="partition-table"<?php echo ($partition_table ? "" : " class='hidden'"); ?>>
<thead><tr><th><?php echo lang('Partition name'); ?><th><?php echo lang('Values'); ?></thead>
<?php
foreach ($row["partition_names"] as $key => $val) {
	echo '<tr>';
	echo '<td><input name="partition_names[]" value="' . h($val) . '" autocapitalize="off">';
	echo ($key == count($row["partition_names"]) - 1 ? script("qsl('input').oninput = partitionNameChange;") : '');
	echo '<td><input name="partition_values[]" value="' . h($row["partition_values"][$key]) . '">';
}
?>
</table>
</div></fieldset>
<?php
}
?>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
