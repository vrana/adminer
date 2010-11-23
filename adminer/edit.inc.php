<?php
$TABLE = $_GET["edit"];
$where = (isset($_GET["select"]) ? (count($_POST["check"]) == 1 ? where_check($_POST["check"][0]) : "") : where($_GET));
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
$fields = fields($TABLE);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || $adminer->fieldName($field) == "") {
		unset($fields[$name]);
	}
}
if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_POST["referer"];
	if ($_POST["insert"]) { // continue edit or insert
		$location = ($update ? null : $_SERVER["REQUEST_URI"]);
	} elseif (!ereg('^.+&select=.+$', $location)) {
		$location = ME . "select=" . urlencode($TABLE);
	}
	if (isset($_POST["delete"])) {
		query_redirect("DELETE" . limit1("FROM " . table($TABLE), " WHERE $where"), $location, lang('Item has been deleted.'));
	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = ($update ? "\n" . idf_escape($name) . " = $val" : $val);
			}
		}
		if ($update) {
			if (!$set) {
				redirect($location);
			}
			query_redirect("UPDATE" . limit1(table($TABLE) . " SET" . implode(",", $set), "\nWHERE $where"), $location, lang('Item has been updated.'));
		} else {
			$result = insert_into($TABLE, $set);
			$last_id = ($result ? last_id() : 0);
			queries_redirect($location, lang('Item%s has been inserted.', ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$table_name = $adminer->tableName(table_status($TABLE));
page_header(
	($update ? lang('Edit') : lang('Insert')),
	$error,
	array("select" => array($TABLE, $table_name)),
	$table_name
);

$row = null;
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
		$rows = get_rows("SELECT" . limit(implode(", ", $select) . " FROM " . table($TABLE), " WHERE $where", (isset($_GET["select"]) ? 2 : 1)));
		$row = (isset($_GET["select"]) && count($rows) != 1 ? null : reset($rows));
	}
}
?>

<form action="" method="post" enctype="multipart/form-data">
<?php
if ($fields) {
	echo "<table cellspacing='0'>\n";
	foreach ($fields as $name => $field) {
		echo "<tr><th>" . $adminer->fieldName($field);
		$default = $_GET["set"][bracket_escape($name)];
		$value = (isset($row)
			? ($row[$name] != "" && ereg("enum|set", $field["type"]) ? +$row[$name] : $row[$name])
			: (!$update && $field["auto_increment"] ? "" : (isset($_GET["select"]) ? false : (isset($default) ? $default : $field["default"])))
		);
		if (!$_POST["save"] && is_string($value)) {
			$value = $adminer->editVal($value, $field);
		}
		$function = ($_POST["save"] ? (string) $_POST["function"][$name] : ($where && $field["on_update"] == "CURRENT_TIMESTAMP" ? "now" : ($value === false ? null : (isset($value) ? '' : 'NULL'))));
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
<input type="hidden" name="referer" value="<?php echo h(isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]); ?>">
<input type="hidden" name="save" value="1">
<?php
if (isset($_GET["select"])) {
	hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
}
if ($fields) {
	echo "<input type='submit' value='" . lang('Save') . "'>\n";
	if (!isset($_GET["select"])) {
		echo '<input type="submit" name="insert" value="' . ($update ? lang('Save and continue edit') : lang('Save and insert next')) . "\">\n";
	}
}
if ($update) {
	echo "<input type='submit' name='delete' value='" . lang('Delete') . "' onclick=\"return confirm('" . lang('Are you sure?') . "');\">\n";
}
?>
</form>
