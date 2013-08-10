<?php
$TABLE = $_GET["edit"];
$fields = fields($TABLE);
$where = (isset($_GET["select"]) ? (count($_POST["check"]) == 1 ? where_check($_POST["check"][0], $fields) : "") : where($_GET, $fields));
$update = (isset($_GET["select"]) ? $_POST["edit"] : $where);
foreach ($fields as $name => $field) {
	if (!isset($field["privileges"][$update ? "update" : "insert"]) || $adminer->fieldName($field) == "") {
		unset($fields[$name]);
	}
}

if ($_POST && !$error && !isset($_GET["select"])) {
	$location = $_POST["referer"];
	if ($_POST["insert"]) { // continue edit or insert
		$location = ($update ? null : $_SERVER["REQUEST_URI"]);
	} elseif (!preg_match('~^.+&select=.+$~', $location)) {
		$location = ME . "select=" . urlencode($TABLE);
	}

	$indexes = indexes($TABLE);
	$unique_array = unique_array($_GET["where"], $indexes);
	$query_where = "\nWHERE $where";

	if (isset($_POST["delete"])) {
		queries_redirect(
			$location,
			lang('Item has been deleted.'),
			$driver->delete($TABLE, $query_where, !$unique_array)
		);

	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($field);
			if ($val !== false && $val !== null) {
				$set[idf_escape($name)] = $val;
			}
		}

		if ($update) {
			if (!$set) {
				redirect($location);
			}
			queries_redirect(
				$location,
				lang('Item has been updated.'),
				$driver->update($TABLE, $set, $query_where, !$unique_array)
			);
			if (is_ajax()) {
				page_headers();
				page_messages($error);
				exit;
			}
		} else {
			$result = $driver->insert($TABLE, $set);
			$last_id = ($result ? last_id() : 0);
			queries_redirect($location, lang('Item%s has been inserted.', ($last_id ? " $last_id" : "")), $result); //! link
		}
	}
}

$table_name = $adminer->tableName(table_status1($TABLE, true));
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
			$as = convert_field($field);
			if ($_POST["clone"] && $field["auto_increment"]) {
				$as = "''";
			}
			if ($jush == "sql" && preg_match("~enum|set~", $field["type"])) {
				$as = "1*" . idf_escape($name);
			}
			$select[] = ($as ? "$as AS " : "") . idf_escape($name);
		}
	}
	$row = array();
	if (!support("table")) {
		$select = array("*");
	}
	if ($select) {
		$result = $driver->select($TABLE, $select, array($where), $select, array(), (isset($_GET["select"]) ? 2 : 1), 0);
		$row = $result->fetch_assoc();
		if (isset($_GET["select"]) && (!$row || $result->fetch_assoc())) { // $result->num_rows != 1 isn't available in all drivers
			$row = null;
		}
	}
}

if (!support("table") && !$fields) {
	if (!$where) { // insert
		$row = reset(get_rows("SELECT * FROM " . table($TABLE) . " LIMIT 1"));
		if (!$row) {
			$row = array("itemName()" => "");
		}
	}
	if ($row) {
		foreach ($row as $key => $val) {
			if (!$where) {
				$row[$key] = null;
			}
			$fields[$key] = array("field" => $key, "null" => ($key != "itemName()"), "auto_increment" => ($key == "itemName()"));
		}
	}
}

if ($row === false) {
	echo "<p class='error'>" . lang('No rows.') . "\n";
}
?>

<div id="message"></div>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
if (!$fields) {
	echo "<p class='error'>" . lang('You have no privileges to update this table.') . "\n";
} else {
	echo "<table cellspacing='0' onkeydown='return editingKeydown(event);'>\n";

	foreach ($fields as $name => $field) {
		echo "<tr><th>" . $adminer->fieldName($field);
		$default = $_GET["set"][bracket_escape($name)];
		if ($default === null) {
			$default = $field["default"];
			if ($field["type"] == "bit" && preg_match("~^b'([01]*)'\$~", $default, $regs)) {
				$default = $regs[1];
			}
		}
		$value = ($row !== null
			? ($row[$name] != "" && $jush == "sql" && preg_match("~enum|set~", $field["type"])
				? (is_array($row[$name]) ? array_sum($row[$name]) : +$row[$name])
				: $row[$name]
			)
			: (!$update && $field["auto_increment"]
				? ""
				: (isset($_GET["select"]) ? false : $default)
			)
		);
		if (!$_POST["save"] && is_string($value)) {
			$value = $adminer->editVal($value, $field);
		}
		$function = ($_POST["save"] ? (string) $_POST["function"][$name] : ($update && $field["on_update"] == "CURRENT_TIMESTAMP" ? "now" : ($value === false ? null : ($value !== null ? '' : 'NULL'))));
		if (preg_match("~time~", $field["type"]) && $value == "CURRENT_TIMESTAMP") {
			$value = "";
			$function = "now";
		}
		input($field, $value, $function);
		echo "\n";
	}

	if (!support("table")) {
		echo "<tr>"
			. "<th><input name='field_keys[]' value='" . h($_POST["field_keys"][0]) . "'>"
			. "<td class='function'>" . html_select("field_funs[]", $adminer->editFunctions(array()), $_POST["field_funs"][0])
			. "<td><input name='field_vals[]' value='" . h($_POST["field_vals"][0]) . "'>"
			. "\n"
		;
	}

	echo "</table>\n";
}
?>
<p>
<?php
if ($fields) {
	echo "<input type='submit' value='" . lang('Save') . "'>\n";
	if (!isset($_GET["select"])) {
		echo "<input type='submit' name='insert' value='" . ($update
			? lang('Save and continue edit') . "' onclick='return !ajaxForm(this.form, \"" . lang('Loading') . '", this)'
			: lang('Save and insert next')
		) . "' title='Ctrl+Shift+Enter'>\n";
	}
}
echo ($update ? "<input type='submit' name='delete' value='" . lang('Delete') . "'" . confirm() . ">\n"
	: ($_POST || !$fields ? "" : "<script type='text/javascript'>focus(document.getElementById('form').getElementsByTagName('td')[1].firstChild);</script>\n")
);
if (isset($_GET["select"])) {
	hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
}
?>
<input type="hidden" name="referer" value="<?php echo h(isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]); ?>">
<input type="hidden" name="save" value="1">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</form>
