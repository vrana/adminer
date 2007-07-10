<?php
$index_types = array("PRIMARY", "UNIQUE", "INDEX", "FULLTEXT");
$indexes = indexes($_GET["indexes"]);
$fields = array_keys(fields($_GET["indexes"]));
if ($_POST && !$error && !$_POST["add"]) {
	$alter = array();
	foreach ($_POST["indexes"] as $index) {
		if (in_array($index["type"], $index_types)) {
			$columns = array();
			ksort($index["columns"]);
			foreach ($index["columns"] as $column) {
				if (in_array($column, $fields, true)) {
					$columns[count($columns) + 1] = $column;
				}
			}
			if ($columns) {
				foreach ($indexes as $name => $existing) {
					if ($index["type"] == $existing["type"] && $existing["columns"] == $columns) {
						unset($indexes[$name]);
						continue 2;
					}
				}
				$alter[] = "ADD $index[type]" . ($index["type"] == "PRIMARY" ? " KEY" : "") . " (" . implode(", ", array_map('idf_escape', $columns)) . ")";
			}
		}
	}
	foreach ($indexes as $name => $existing) {
		$alter[] = "DROP INDEX " . idf_escape($name);
	}
	if (!$alter || $mysql->query("ALTER TABLE " . idf_escape($_GET["indexes"]) . " " . implode(", ", $alter))) {
		redirect($SELF . "table=" . urlencode($_GET["indexes"]), ($alter ? lang('Indexes has been altered.') : null));
	}
	$error = $mysql->error;
}
page_header(lang('Indexes') . ': ' . htmlspecialchars($_GET["indexes"]));

if ($_POST) {
	if (!$_POST["add"]) {
		echo "<p class='error'>" . lang('Unable to operate indexes') . ": " . htmlspecialchars($error) . "</p>\n";
	}
	$row = $_POST;
} else {
	$row = array("indexes" => $indexes);
}
?>

<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<?php
$j = 0;
foreach ($row["indexes"] as $index) {
	if ($index["type"] || array_filter($index["columns"], 'strlen')) {
		echo "<tr><td><select name='indexes[$j][type]'><option></option>" . optionlist($index_types, $index["type"], "not_vals") . "</select></td><td>";
		ksort($index["columns"]);
		foreach ($index["columns"] as $i => $column) {
			if (strlen($column)) {
				echo "<select name='indexes[$j][columns][$i]'><option></option>" . optionlist($fields, $column, "not_vals") . "</select>";
			}
		}
		echo "<select name='indexes[$j][columns][" . ($i+1) . "]'><option></option>" . optionlist($fields, array(), "not_vals") . "</select>";
		echo "</td></tr>\n";
		$j++;
	}
}
//! JavaScript for adding more indexes and columns
?>
<tr><td><select name="indexes[<?php echo $j; ?>][type]"><option></option><?php echo optionlist($index_types, array(), "not_vals"); ?></select></td><td><select name="indexes[<?php echo $j; ?>][columns][1]"><option></option><?php echo optionlist($fields, array(), "not_vals"); ?></select></td></tr>
</table>
<p><input type="hidden" name="token" value="<?php echo $token; ?>" /><input type="submit" value="<?php echo lang('Alter indexes'); ?>" /></p>
<p><input type="submit" name="add" value="<?php echo lang('Add next'); ?>" /></p>
</form>
