<?php
$TABLE = $_GET["edit"];
$where = (isset($_GET["select"]) ? (count($_POST["check"]) == 1 ? where_check($_POST["check"][0]) : "") : where($_GET));
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
$fields = fields($TABLE);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || !strlen($adminer->fieldName($field))) {
		unset($fields[$name]);
	}
}
if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_SERVER["REQUEST_URI"]; // continue edit or insert
	if (!$_POST["insert"]) {
		$location = ME . "select=" . urlencode($TABLE);
		$i = 0; // append &set converted to &where
		foreach ((array) $_GET["set"] as $key => $val) {
			if ($val == $_POST["fields"][$key]) {
				$location .= where_link($i++, bracket_escape($key, "back"), $val);
			}
		}
	}
	$set = array();
	foreach ($fields as $name => $field) {
		$val = process_input($field);
		if (!$update) {
			$set[idf_escape($name)] = ($val !== false ? $val : "''");
		} elseif ($val !== false) {
			$set[] = "\n" . idf_escape($name) . " = $val";
		}
	}
	if (!$set) {
		redirect($location);
	}
	if ($update) {
		query_redirect("UPDATE " . idf_escape($TABLE) . " SET" . implode(",", $set) . "\nWHERE $where\nLIMIT 1", $location, lang('Item has been updated.'));
	} else {
		query_redirect("INSERT INTO " . idf_escape($TABLE) . " (" . implode(", ", array_keys($set)) . ")\nVALUES (" . implode(", ", $set) . ")", $location, lang('Item has been inserted.'));
	}
}

$table_name = $adminer->tableName(table_status($TABLE));
page_header(
	($update ? lang('Edit') : lang('Insert')),
	$error,
	array("select" => array($TABLE, $table_name)),
	$table_name
);

unset($row);
if ($_POST["save"]) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$select[] = ($_POST["clone"] && $field["auto_increment"] ? "'' AS " : (ereg("enum|set", $field["type"]) ? "1*" . idf_escape($name) . " AS " : "")) . idf_escape($name);
		}
	}
	$row = array();
	if ($select) {
		$result = $connection->query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($TABLE) . " WHERE $where " . (isset($_GET["select"]) ? "HAVING COUNT(*) = 1" : "LIMIT 1"));
		$row = $result->fetch_assoc();
	}
}
?>

<form action="" method="post" enctype="multipart/form-data">
<?php
if ($fields) {
	unset($create);
	echo "<table cellspacing='0'>\n";
	foreach ($fields as $name => $field) {
		echo "<tr><th>" . $adminer->fieldName($field);
		$default = $_GET["set"][bracket_escape($name)];
		$value = (isset($row)
			? (strlen($row[$name]) && ereg("enum|set", $field["type"]) ? intval($row[$name]) : $row[$name])
			: ($_POST["clone"] && $field["auto_increment"] ? "" : (isset($_GET["select"]) ? false : (isset($default) ? $default : $field["default"])))
		);
		if (!$_POST["save"] && is_string($value)) {
			$value = $adminer->editVal($value, $field);
		}
		$function = ($_POST["save"] ? (string) $_POST["function"][$name] : ($update && $field["on_update"] == "CURRENT_TIMESTAMP" ? "now" : ($value === false ? null : (isset($value) ? '' : 'NULL'))));
		if ($field["type"] == "timestamp" && $value == "CURRENT_TIMESTAMP") {
			$value = "";
			$function = "now";
		}
		input($field, $value, $function);
		echo "\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="hidden" name="save" value="1">
<?php
if (isset($_GET["select"])) {
	hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
}
if ($fields) {
	echo "<input type='submit' value='" . lang('Save') . "'>\n";
	if (!isset($_GET["select"])) {
		echo "<input type='submit' name='insert' value='" . ($update ? lang('Save and continue edit') : lang('Save and insert next')) . "'>\n";
	}
}
?>
</form>
