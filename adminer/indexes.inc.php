<?php
namespace Adminer;

$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status1($TABLE, true);
if (preg_match('~MyISAM|M?aria' . (min_version(5.6, '10.0.5') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
if (preg_match('~MyISAM|M?aria' . (min_version(5.7, '10.2.2') ? '|InnoDB' : '') . '~i', $table_status["Engine"])) {
	$index_types[] = "SPATIAL";
}
$indexes = indexes($TABLE);
$primary = array();
if (JUSH == "mongo") { // doesn't support primary key
	$primary = $indexes["_id_"];
	unset($index_types[0]);
	unset($indexes["_id_"]);
}
$row = $_POST;
if ($row) {
	save_settings(array("index_options" => $row["options"]));
}
if ($_POST && !$error && !$_POST["add"] && !$_POST["drop_col"]) {
	$alter = array();
	foreach ($row["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$descs = array();
			$index_condition = support("partial_indexes") ? $index["partial_index_condition"] : "";
			$index_algorithm = (in_array($index["algorithm"], driver()->indexMethods()) ? $index["algorithm"] : "");
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = idx($index["lengths"], $key);
					$desc = idx($index["descs"], $key);
					$set[] = idf_escape($column) . ($length ? "(" . (+$length) . ")" : "") . ($desc ? " DESC" : "");
					$columns[] = $column;
					$lengths[] = ($length ?: null);
					$descs[] = $desc;
				}
			}

			$existing = $indexes[$name];
			if ($existing) {
				ksort($existing["columns"]);
				ksort($existing["lengths"]);
				ksort($existing["descs"]);
				if (
					$index["type"] == $existing["type"]
					&& array_values($existing["columns"]) === $columns
					&& (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)
					&& array_values($existing["descs"]) === $descs
					&& $existing["algorithm"] === $index_algorithm
					&& $existing["partial_index_condition"] === $index_condition
				) {
					// skip existing index
					unset($indexes[$name]);
					continue;
				}
			}
			if ($columns) {
				$alter[] = array($index["type"], $name, $set, $index_algorithm, $index_condition);
			}
		}
	}

	// drop removed indexes
	foreach ($indexes as $name => $existing) {
		$alter[] = array($existing["type"], $name, "DROP");
	}
	if (!$alter) {
		redirect(ME . "table=" . urlencode($TABLE));
	}
	queries_redirect(ME . "table=" . urlencode($TABLE), lang('Indexes have been altered.'), alter_indexes($TABLE, $alter));
}

page_header(lang('Indexes'), $error, array("table" => $TABLE), h($TABLE));

$fields = array_keys(fields($TABLE));
if ($_POST["add"]) {
	foreach ($row["indexes"] as $key => $index) {
		if ($index["columns"][count($index["columns"])] != "") {
			$row["indexes"][$key]["columns"][] = "";
		}
	}
	$index = end($row["indexes"]);
	if ($index["type"] || array_filter($index["columns"], 'strlen')) {
		$row["indexes"][] = array("columns" => array(1 => ""));
	}
}
if (!$row) {
	foreach ($indexes as $key => $index) {
		$indexes[$key]["name"] = $key;
		$indexes[$key]["columns"][] = "";
	}
	$indexes[] = array("columns" => array(1 => ""));
	$row["indexes"] = $indexes;
}
$lengths = (JUSH == "sql" || JUSH == "mssql");
$show_options = ($_POST ? $_POST["options"] : get_setting("index_options"));
?>

<form action="" method="post">
<div class="scrollable">
<table class="nowrap">
<thead><tr>
<th id="label-type"><?php echo lang('Index Type'); ?>
<?php
if (driver()->indexMethods()) {
	echo "<th id='label-method' class='idxopts" .  ($show_options ? "" : " hidden") . "'>" . lang('Algorithm');
}
?>
<th><input type="submit" class="wayoff"><?php
echo lang('Columns') . ($lengths ? "<span class='idxopts" . ($show_options ? "" : " hidden") . "'> (" . lang('length') . ")</span>" : "");
if ($lengths || support("descidx")) {
	echo checkbox("options", 1, $show_options, lang('Options'), "indexOptionsShow(this.checked)", "jsonly") . "\n";
}
?>
<th id="label-name"><?php echo lang('Name'); ?>
<?php
if (support("partial_indexes")) {
	echo "<th id='label-condition' class='idxopts" .  ($show_options ? "" : " hidden") . "'>" . lang('Condition');
}
?>
<th><noscript><?php echo icon("plus", "add[0]", "+", lang('Add next')); ?></noscript>
</thead>
<?php
if ($primary) {
	echo "<tr><td>PRIMARY<td>";
	foreach ($primary["columns"] as $key => $column) {
		echo select_input(" disabled", $fields, $column);
		echo "<label><input disabled type='checkbox'>" . lang('descending') . "</label> ";
	}
	echo "<td><td>\n";
}
$j = 1;
foreach ($row["indexes"] as $index) {
	if (!$_POST["drop_col"] || $j != key($_POST["drop_col"])) {
		echo "<tr><td>" . html_select("indexes[$j][type]", array(-1 => "") + $index_types, $index["type"], ($j == count($row["indexes"]) ? "indexesAddRow.call(this);" : ""), "label-type");

		if (driver()->indexMethods()) {
			echo "<td class='idxopts" .  ($show_options ? "" : " hidden") . "'>" . html_select("indexes[$j][algorithm]", array_merge(array(""), driver()->indexMethods()), $index['algorithm'], "label-method");
		}

		echo "<td>";
		ksort($index["columns"]);
		$i = 1;
		foreach ($index["columns"] as $key => $column) {
			echo "<span>" . select_input(
				" name='indexes[$j][columns][$i]' title='" . lang('Column') . "'",
				($fields ? array_combine($fields, $fields) : $fields),
				$column,
				"partial(" . ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . ", '" . js_escape(JUSH == "sql" ? "" : $_GET["indexes"] . "_") . "')"
			);
			echo "<span class='idxopts" . ($show_options ? "" : " hidden") . "'>";
			echo ($lengths ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h(idx($index["lengths"], $key)) . "' title='" . lang('Length') . "'>" : "");
			echo (support("descidx") ? checkbox("indexes[$j][descs][$i]", 1, idx($index["descs"], $key), lang('descending')) : "");
			echo "</span> </span>";
			$i++;
		}

		echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off' aria-labelledby='label-name'>\n";
		if (support("partial_indexes")) {
			echo "<td class='idxopts" .  ($show_options ? "" : " hidden") . "'><input name='indexes[$j][partial_index_condition]' value='" . h($index["partial_index_condition"]) . "' autocapitalize='off' aria-labelledby='label-condition'>\n";
		}
		echo "<td>" . icon("cross", "drop_col[$j]", "x", lang('Remove')) . script("qsl('button').onclick = partial(editingRemoveRow, 'indexes\$1[type]');");
	}
	$j++;
}
?>
</table>
</div>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<?php echo input_token(); ?>
</form>
