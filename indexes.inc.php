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
					if ($index["type"] == $existing["type"] && $existing["columns"] === $columns && $existing["lengths"] === $lengths) {
						unset($indexes[$name]);
						continue 2;
					}
				}
				$alter[] = "ADD $index[type]" . ($index["type"] == "PRIMARY" ? " KEY" : "") . " (" . implode(", ", $set) . ")";
			}
		}
	}
	foreach ($indexes as $name => $existing) {
		$alter[] = "DROP INDEX " . idf_escape($name));
	}
	if (!$alter || $mysql->query("ALTER TABLE " . idf_escape($_GET["indexes"]) . " " . implode(", ", $alter))) {
		redirect($SELF . "table=" . urlencode($_GET["indexes"]), ($alter ? lang('Indexes has been altered.') : null));
	}
	$error = $mysql->error;
}
page_header(lang('Indexes') . ': ' . htmlspecialchars($_GET["indexes"]));

$fields = array_keys(fields($_GET["indexes"]));
if ($_POST) {
	$row = $_POST;
	if (!$_POST["add"]) {
		echo "<p class='error'>" . lang('Unable to operate indexes') . ": " . htmlspecialchars($error) . "</p>\n";
	} else {
		foreach ($row["indexes"] as $key => $index) {
			if (strlen($index["columns"][count($index["columns"])])) {
				$row["indexes"][$key]["columns"][] = "";
			}
		}
		$index = $row["indexes"][count($row["indexes"]) - 1];
		if ($index["type"] || array_filter($index["columns"], 'strlen') || array_filter($index["columns"], 'length')) {
			$row["indexes"][] = array("columns" => array(1 => ""));
		}
	}
} else {
	$row = array("indexes" => $indexes);
	foreach ($row["indexes"] as $key => $index) {
		$row["indexes"][$key]["columns"][] = "";
	}
	$row["indexes"][] = array("columns" => array(1 => ""));
}
?>

<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Index Type'); ?></th><td><?php echo lang('Column (length)'); ?></td></tr></thead>
<?php
$j = 0;
foreach ($row["indexes"] as $index) {
	echo "<tr><td><select name='indexes[$j][type]'><option></option>" . optionlist($index_types, $index["type"]) . "</select></td><td>";
	ksort($index["columns"]);
	foreach ($index["columns"] as $i => $column) {
		echo "<select name='indexes[$j][columns][$i]'><option></option>" . optionlist($fields, $column) . "</select>";
		echo "<input name='indexes[$j][lengths][$i]' size='2' value=\"" . htmlspecialchars($index["lengths"][$i]) . "\" />\n";
	}
	echo "</td></tr>\n";
	$j++;
}
//! JavaScript for adding more indexes and columns
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Alter indexes'); ?>" />
<input type="submit" name="add" value="<?php echo lang('Add next'); ?>" />
</p>
</form>
