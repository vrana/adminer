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
				$alter[] = "ADD $index[type]" . ($index["type"] == "PRIMARY" ? " KEY" : "") . " (" . implode(", ", $set) . ")";
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

<script type="text/javascript">
function add_row(field) {
	var row = field.parentNode.parentNode.cloneNode(true);
	var spans = row.getElementsByTagName('span');
	row.getElementsByTagName('td')[1].innerHTML = '<span>' + spans[spans.length - 1].innerHTML + '</span>';
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/indexes\[[0-9]+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var input = row.getElementsByTagName('input')[0];
	input.name = input.name.replace(/indexes\[[0-9]+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}

function add_column(field) {
	var column = field.parentNode.cloneNode(true);
	var select = column.getElementsByTagName('select')[0];
	select.name = select.name.replace(/\]\[[0-9]+/, '$&1');
	select.selectedIndex = 0;
	var input = column.getElementsByTagName('input')[0];
	input.name = input.name.replace(/\]\[[0-9]+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.appendChild(column);
	field.onchange = function () { };
}
</script>

<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr><th><?php echo lang('Index Type'); ?></th><td><?php echo lang('Column (length)'); ?></td></tr></thead>
<?php
$j = 0;
foreach ($row["indexes"] as $index) {
	echo "<tr><td><select name='indexes[$j][type]'" . ($j == count($row["indexes"]) - 1 ? " onchange='add_row(this);'" : "") . "><option></option>" . optionlist($index_types, $index["type"]) . "</select></td><td>\n";
	ksort($index["columns"]);
	foreach ($index["columns"] as $i => $column) {
		echo "<span><select name='indexes[$j][columns][$i]'" . ($i == count($index["columns"]) ? " onchange='add_column(this);'" : "") . "><option></option>" . optionlist($fields, $column) . "</select>";
		echo "<input name='indexes[$j][lengths][$i]' size='2' value=\"" . htmlspecialchars($index["lengths"][$i]) . "\" /></span>\n";
	}
	echo "</td></tr>\n";
	$j++;
}
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="submit" value="<?php echo lang('Alter indexes'); ?>" />
</p>
<noscript><p><input type="submit" name="add" value="<?php echo lang('Add next'); ?>" /></p></noscript>
</form>
