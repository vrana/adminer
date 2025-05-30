<?php
namespace Adminer;

$TABLE = $_GET["create"];
$partition_by = driver()->partitionBy;
$partitions_info = ($partition_by ? driver()->partitionsInfo($TABLE) : array());

$referencable_primary = referencable_primary($TABLE);
$foreign_keys = array();
foreach ($referencable_primary as $table_name => $field) {
	$foreign_keys[str_replace("`", "``", $table_name) . "`" . str_replace("`", "``", $field["field"])] = $table_name; // not idf_escape() - used in JS
}

$orig_fields = array();
$table_status = array();
if ($TABLE != "") {
	$orig_fields = fields($TABLE);
	$table_status = table_status1($TABLE);
	if (count($table_status) < 2) { // there's only the Name field
		$error = lang('No tables.');
	}
}

$row = $_POST;
$row["fields"] = (array) $row["fields"];
if ($row["auto_increment_col"]) {
	$row["fields"][$row["auto_increment_col"]]["auto_increment"] = true;
}

if ($_POST) {
	save_settings(array("comments" => $_POST["comments"], "defaults" => $_POST["defaults"]));
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
				if (!$field["generated"]) {
					$field["default"] = null;
				}
				$process_field = process_field($field, $type_field);
				$all_fields[] = array($field["orig"], $process_field, $after);
				if (!$orig_field || $process_field !== process_field($orig_field, $orig_field)) {
					$fields[] = array($field["orig"], $process_field, $after);
					if ($field["orig"] != "" || $after) {
						$use_all_fields = true;
					}
				}
				if ($foreign_key !== null) {
					$foreign[idf_escape($field["field"])] = ($TABLE != "" && JUSH != "sqlite" ? "ADD" : " ") . format_foreign_key(array(
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

		$partitioning = array();
		if (in_array($row["partition_by"], $partition_by)) {
			foreach ($row as $key => $val) {
				if (preg_match('~^partition~', $key)) {
					$partitioning[$key] = $val;
				}
			}
			foreach ($partitioning["partition_names"] as $key => $name) {
				if ($name == "") {
					unset($partitioning["partition_names"][$key]);
					unset($partitioning["partition_values"][$key]);
				}
			}
			$partitioning["partition_names"] = array_values($partitioning["partition_names"]);
			$partitioning["partition_values"] = array_values($partitioning["partition_values"]);
			if ($partitioning == $partitions_info) {
				$partitioning = array();
			}
		} elseif (preg_match("~partitioned~", $table_status["Create_options"])) {
			$partitioning = null;
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
			(JUSH == "sqlite" && ($use_all_fields || $foreign) ? $all_fields : $fields),
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
	$types = driver()->types();
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
			$field["generated"] = $field["generated"] ?: (isset($field["default"]) ? "DEFAULT" : "");
			$row["fields"][] = $field;
		}

		if ($partition_by) {
			$row += $partitions_info;
			$row["partition_names"][] = "";
			$row["partition_values"][] = "";
		}
	}
}

$collations = collations();
if (is_array(reset($collations))) {
	$collations = call_user_func_array('array_merge', array_values($collations));
}
$engines = driver()->engines();
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
<?php
if (support("columns") || $TABLE == "") {
	echo lang('Table name') . ": <input name='name'" . ($TABLE == "" && !$_POST ? " autofocus" : "") . " data-maxlength='64' value='" . h($row["name"]) . "' autocapitalize='off'>\n";
	echo ($engines ? html_select("Engine", array("" => "(" . lang('engine') . ")") + $engines, $row["Engine"]) . on_help("event.target.value", 1) . script("qsl('select').onchange = helpClose;") . "\n" : "");
	if ($collations) {
		echo "<datalist id='collations'>" . optionlist($collations) . "</datalist>\n";
		echo (preg_match("~sqlite|mssql~", JUSH) ? "" : "<input list='collations' name='Collation' value='" . h($row["Collation"]) . "' placeholder='(" . lang('collation') . ")'>\n");
	}
	echo "<input type='submit' value='" . lang('Save') . "'>\n";
}

if (support("columns")) {
	echo "<div class='scrollable'>\n";
	echo "<table id='edit-fields' class='nowrap'>\n";
	edit_fields($row["fields"], $collations, "TABLE", $foreign_keys);
	echo "</table>\n";
	echo script("editFields();");
	echo "</div>\n<p>\n";
	echo lang('Auto Increment') . ": <input type='number' name='Auto_increment' class='size' value='" . h($row["Auto_increment"]) . "'>\n";
	echo checkbox("defaults", 1, ($_POST ? $_POST["defaults"] : get_setting("defaults")), lang('Default values'), "columnShow(this.checked, 5)", "jsonly");
	$comments = ($_POST ? $_POST["comments"] : get_setting("comments"));
	echo (support("comment")
		? checkbox("comments", 1, $comments, lang('Comment'), "editingCommentsClick(this, true);", "jsonly")
			. ' ' . (preg_match('~\n~', $row["Comment"])
				? "<textarea name='Comment' rows='2' cols='20'" . ($comments ? "" : " class='hidden'") . ">" . h($row["Comment"]) . "</textarea>"
				: '<input name="Comment" value="' . h($row["Comment"]) . '" data-maxlength="' . (min_version(5.5) ? 2048 : 60) . '"' . ($comments ? "" : " class='hidden'") . '>'
			)
		: '')
	;
	?>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php } ?>

<?php if ($TABLE != "") { ?>
<input type="submit" name="drop" value="<?php echo lang('Drop'); ?>"><?php echo confirm(lang('Drop %s?', $TABLE)); ?>
<?php } ?>
<?php
if ($partition_by && (JUSH == 'sql' || $TABLE == "")) {
	$partition_table = preg_match('~RANGE|LIST~', $row["partition_by"]);
	print_fieldset("partition", lang('Partition by'), $row["partition_by"]);
	echo "<p>" . html_select("partition_by", array_merge(array(""), $partition_by), $row["partition_by"]) . on_help("event.target.value.replace(/./, 'PARTITION BY \$&')", 1) . script("qsl('select').onchange = partitionByChange;");
	echo "(<input name='partition' value='" . h($row["partition"]) . "'>)\n";
	echo lang('Partitions') . ": <input type='number' name='partitions' class='size" . ($partition_table || !$row["partition_by"] ? " hidden" : "") . "' value='" . h($row["partitions"]) . "'>\n";
	echo "<table id='partition-table'" . ($partition_table ? "" : " class='hidden'") . ">\n";
	echo "<thead><tr><th>" . lang('Partition name') . "<th>" . lang('Values') . "</thead>\n";
	foreach ($row["partition_names"] as $key => $val) {
		echo '<tr>';
		echo '<td><input name="partition_names[]" value="' . h($val) . '" autocapitalize="off">';
		echo ($key == count($row["partition_names"]) - 1 ? script("qsl('input').oninput = partitionNameChange;") : '');
		echo '<td><input name="partition_values[]" value="' . h(idx($row["partition_values"], $key)) . '">';
	}
	echo "</table>\n</div></fieldset>\n";
}
echo input_token();
?>
</form>
