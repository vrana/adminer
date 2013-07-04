<?php
$TABLE = $_GET["indexes"];
$index_types = array("PRIMARY", "UNIQUE", "INDEX");
$table_status = table_status($TABLE, true);
if (eregi("MyISAM|M?aria" . ($connection->server_info >= 5.6 ? "|InnoDB" : ""), $table_status["Engine"])) {
	$index_types[] = "FULLTEXT";
}
$indexes = indexes($TABLE);
if ($jush == "sqlite") { // doesn't support primary key
	unset($index_types[0]);
	unset($indexes[""]);
}
$row = $_POST;

if ($_POST && !$error && !$_POST["add"]) {
	$alter = array();
	foreach ($row["indexes"] as $index) {
		$name = $index["name"];
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$descs = array();
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if ($column != "") {
					$length = $index["lengths"][$key];
					$desc = $index["descs"][$key];
					$set[] = idf_escape($column) . ($length ? "(" . (+$length) . ")" : "") . ($desc ? " DESC" : "");
					$columns[] = $column;
					$lengths[] = ($length ? $length : null);
					$descs[] = $desc;
				}
			}
			
			if ($columns) {
				$existing = $indexes[$name];
				if ($existing) {
					ksort($existing["columns"]);
					ksort($existing["lengths"]);
					ksort($existing["descs"]);
					if ($index["type"] == $existing["type"]
						&& array_values($existing["columns"]) === $columns
						&& (!$existing["lengths"] || array_values($existing["lengths"]) === $lengths)
						&& array_values($existing["descs"]) === $descs
					) {
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
if ($_POST["add"]) {
	foreach ($row["indexes"] as $key => $index) {
		if ($index["columns"][count($index["columns"])] != "") {
			$row["indexes"][$key]["columns"][] = "";
		}
	}
	$index = end($row["indexes"]);
	if ($index["type"]
		|| array_filter($index["columns"], 'strlen')
		|| array_filter($index["lengths"], 'strlen')
		|| array_filter($index["descs"])
	) {
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
		echo ($jush == "sql" || $jush == "mssql" ? "<input type='number' name='indexes[$j][lengths][$i]' class='size' value='" . h($index["lengths"][$key]) . "'>" : "");
		echo ($jush != "sql" ? checkbox("indexes[$j][descs][$i]", 1, $index["descs"][$key], lang('descending')) : "");
		echo " </span>";
		$i++;
	}
	
	echo "<td><input name='indexes[$j][name]' value='" . h($index["name"]) . "' autocapitalize='off'>\n";
	$j++;
}
?>
</table>
<p>
<input type="submit" value="<?php echo lang('Save'); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add next'); ?>"></noscript>
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
