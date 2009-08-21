<?php
$where = (isset($_GET["select"]) ? "" : where($_GET));
$update = ($where || $_POST["edit"]);
$fields = fields($_GET["edit"]);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || !strlen($adminer->fieldName($field))) {
		unset($fields[$name]);
	}
}
if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_SERVER["REQUEST_URI"]; // continue edit or insert
	if (!$_POST["insert"]) {
		$location = ME . "select=" . urlencode($_GET["edit"]);
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
		if ($val !== false || !$update) {
			$set[] = "\n" . idf_escape($name) . " = " . ($val !== false ? $val : "''");
		}
	}
	if (!$set) {
		redirect($location);
	}
	if ($update) {
		query_redirect("UPDATE " . idf_escape($_GET["edit"]) . " SET" . implode(",", $set) . "\nWHERE $where\nLIMIT 1", $location, lang('Item has been updated.'));
	} else {
		query_redirect("INSERT INTO " . idf_escape($_GET["edit"]) . " SET" . implode(",", $set), $location, lang('Item has been inserted.'));
	}
}

$table_name = $adminer->tableName(table_status($_GET["edit"]));
page_header(
	($update ? lang('Edit') : lang('Insert')),
	$error,
	array("select" => array($_GET["edit"], $table_name)),
	$table_name
);

unset($row);
if ($_POST["save"]) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"])) {
			$select[] = ($_POST["clone"] && $field["auto_increment"] ? "'' AS " : ($field["type"] == "enum" || $field["type"] == "set" ? "1*" . idf_escape($name) . " AS " : "")) . idf_escape($name);
		}
	}
	$row = array();
	if ($select) {
		$result = $dbh->query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE $where LIMIT 1");
		$row = $result->fetch_assoc();
		$result->free();
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
			? (strlen($row[$name]) && ($field["type"] == "enum" || $field["type"] == "set") ? intval($row[$name]) : $row[$name])
			: ($_POST["clone"] && $field["auto_increment"] ? "" : (isset($_GET["select"]) ? false : (isset($default) ? $default : $field["default"])))
		);
		if (!$_POST["save"] && is_string($value)) {
			$value = $adminer->editVal($value, $field);
		}
		$function = ($_POST["save"] ? (string) $_POST["function"][$name] : ($where && $field["on_update"] == "CURRENT_TIMESTAMP" ? "now" : ($value === false ? null : (isset($value) ? '' : 'NULL'))));
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
