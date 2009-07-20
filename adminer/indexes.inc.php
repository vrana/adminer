<?php
$index_types = array("PRIMARY", "UNIQUE", "INDEX", "FULLTEXT");
$indexes = indexes($_GET["indexes"]);
if ($_POST && !$error && !$_POST["add"]) {
	$alter = array();
	foreach ($_POST["indexes"] as $index) {
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			$lengths = array();
			$set = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $key => $column) {
				if (strlen($column)) {
					$length = $index["lengths"][$key];
					$set[] = idf_escape($column) . ($length ? "(" . intval($length) . ")" : "");
					$columns[count($columns) + 1] = $column;
					$lengths[count($lengths) + 1] = ($length ? $length : null);
				}
			}
			if ($columns) {
				foreach ($indexes as $name => $existing) {
					ksort($existing["columns"]);
					ksort($existing["lengths"]);
					if ($index["type"] == $existing["type"] && $existing["columns"] === $columns && $existing["lengths"] === $lengths) {
						unset($indexes[$name]);
						continue 2;
					}
				}
				$alter[] = "\nADD $index[type]" . ($index["type"] == "PRIMARY" ? " KEY" : "") . " (" . implode(", ", $set) . ")";
			}
		}
	}
	foreach ($indexes as $name => $existing) {
		$alter[] = "\nDROP INDEX " . idf_escape($name);
	}
	if (!$alter) {
		redirect($SELF . "table=" . urlencode($_GET["indexes"]));
	}
	query_redirect("ALTER TABLE " . idf_escape($_GET["indexes"]) . implode(",", $alter), $SELF . "table=" . urlencode($_GET["indexes"]), lang('Indexes has been altered.'));
}

page_header(lang('Indexes'), $error, array("table" => $_GET["indexes"]), $_GET["indexes"]);

$fields = array_keys(fields($_GET["indexes"]));
$row = array("indexes" => $indexes);
if ($_POST) {
	$row = $_POST;
	if ($_POST["add"]) {
		foreach ($row["indexes"] as $key => $index) {
			if (strlen($index["columns"][count($index["columns"])])) {
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
		$row["indexes"][$key]["columns"][] = "";
	}
	$row["indexes"][] = array("columns" => array(1 => ""));
}
?>

<form action="" method="post">
<table cellspacing="0">
<thead><tr><th><?php echo lang('Index Type'); ?><th><?php echo lang('Column (length)'); ?></thead>
<?php
$j = 0;
foreach ($row["indexes"] as $index) {
	echo "<tr><td><select name='indexes[$j][type]'" . ($j == count($row["indexes"]) - 1 ? " onchange='indexes_add_row(this);'" : "") . "><option>" . optionlist($index_types, $index["type"]) . "</select><td>\n";
	ksort($index["columns"]);
	foreach ($index["columns"] as $i => $column) {
		echo "<span><select name='indexes[$j][columns][$i]'" . ($i == count($index["columns"]) ? " onchange='indexes_add_column(this);'" : "") . "><option>" . optionlist($fields, $column) . "</select>";
		echo "<input name='indexes[$j][lengths][$i]' size='2' value=\"" . htmlspecialchars($index["lengths"][$i]) . "\"> </span>\n";
	}
	echo "\n";
	$j++;
}
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" value="<?php echo lang('Save'); ?>">
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add next'); ?>"></noscript>
</form>
