<?php
$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status($TABLE);
if (eregi("MyISAM|M?aria", $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
$indexes = indexes($TABLE);
if ($jush == "sqlite") { // doesn't support primary key
	unset($index_types[0]);
	unset($indexes[""]);
}
if ($_POST && !$error && !$_POST["add"]) {
	$alter = array();
	foreach ($_POST["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = $index["lengths"][$key];
					$set[] = idf_escape($column) . ($length ? "(" . (+$length) . ")" : "");
					$columns[] = $column;
					$lengths[] = ($length ? $length : null);
				}
			}
			if ($columns) {
				$existing = $indexes[$name];
				if ($existing) {
					ksort($existing["columns"]);
					ksort($existing["lengths"]);
					if ($index["type"] == $existing["type"] && array_values($existing["columns"]) === $columns && (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)) {
						// skip existing index
						unset($indexes[$name]);
						continue;
					}
				}
				$alter[] = array($index["type"], $name, "(" . implode(", ", $set) . ")");
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

page_header(lang('Indexes'), $error, array("table" => $TABLE), $TABLE);

$fields = array_keys(fields($TABLE));
$row = array("indexes" => $indexes);
if ($_POST) {
	$row = $_POST;
	if ($_POST["add"]) {
		foreach ($row["indexes"] as $key => $index) {
			if ($index["columns"][count($index["columns"])] != "") {
				$row["indexes"][$key]["columns"][] = "";
			}
		}
		$index = end($row["indexes"]);
		if ($index["type"] || array_filter($index["columns"], 'strlen') || array_filter($index["lengths"], 'strlen')) {
			$row["indexes"][] = array("columns" => array(1 => ""));
		}
	}
} else {
	foreach ($row["indexes"] as $key => $index) {
		$row["indexes"][$key]["name"] = $key;
		$row["indexes"][$key]["columns"][] = "";
	}
	$row["indexes"][] = array("columns" => array(1 => ""));
}
?>

<form action="" method="post">
<table cellspacing="0" class="nowrap">
<thead><tr><th><?php echo lang('Index Type'); ?><th><?php echo lang('Column (length)'); ?><th><?php echo lang('Name'); ?></thead>
<?php
$j = 1;
foreach ($row["indexes"] as $index) {
	echo "<tr><td>" . html_select("indexes[$j][type]", array(-1 => "") + $index_types, $index["type"], ($j == count($row["indexes"]) ? "indexesAddRow(this);" : 1)) . "<td>";
	ksort($index["columns"]);
	$i = 1;
	foreach ($index["columns"] as $key => $column) {
		echo "<span>" . html_select("indexes[$j][columns][$i]", array(-1 => "") + $fields, $column, ($i == count($index["columns"]) ? "indexesAddColumn" : "indexesChangeColumn") . "(this, '" . js_escape($jush == "sql" ? "" : $_GET["indexes"] . "_") . "');");
		echo "<input name='indexes[$j][lengths][$i]' size='2' value='" . h($index["lengths"][$key]) . "'> </span>"; //! hide for non-MySQL drivers, add ASC|DESC
		$i++;
	}
	echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "'>\n";
	$j++;
}
?>
</table>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add next'); ?>"></noscript>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
