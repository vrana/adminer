<?php
$where = (isset($_GET["select"]) ? (count($_POST["check"]) == 1 ? where_check($_POST["check"][0]) : "") : where($_GET));
$update = ($where && !$_POST["clone"]);
$fields = fields($_GET["edit"]);
foreach ($fields as $name => $field) {
	if (isset($_GET["default"]) ? $field["auto_increment"] || preg_match('~text|blob~', $field["type"]) : !isset($field["privileges"][$update ? "update" : "insert"])) {
		unset($fields[$name]);
	}
}
if ($_POST && !$error && !isset($_GET["select"])) {
	$location = ($_POST["insert"] ? $_SERVER["REQUEST_URI"] : $SELF . (isset($_GET["default"]) ? "table=" : "select=") . urlencode($_GET["edit"])); // "insert" to continue edit or insert
	if (isset($_POST["delete"])) {
		query_redirect("DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE $where LIMIT 1", $location, lang('Item has been deleted.'));
	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($name, $field);
			if (!isset($_GET["default"])) {
				if ($val !== false || !$update) {
					$set[] = "\n" . idf_escape($name) . " = " . ($val !== false ? $val : "''");
				}
			} elseif ($val !== false) {
				if ($field["type"] == "timestamp" && $val != "NULL") { //! doesn't allow DEFAULT NULL and no ON UPDATE
					$set[] = "\nMODIFY " . idf_escape($name) . " timestamp" . ($field["null"] ? " NULL" : "") . " DEFAULT $val" . ($_POST["on_update"][bracket_escape($name)] ? " ON UPDATE CURRENT_TIMESTAMP" : "");
				} else {
					$set[] = "\nALTER " . idf_escape($name) . ($val == "NULL" ? " DROP DEFAULT" : " SET DEFAULT $val");
				}
			}
		}
		if (!$set) {
			redirect($location);
		}
		if (isset($_GET["default"])) {
			query_redirect("ALTER TABLE " . idf_escape($_GET["edit"]) . implode(",", $set), $location, lang('Default values has been set.'));
		} elseif ($update) {
			query_redirect("UPDATE " . idf_escape($_GET["edit"]) . " SET" . implode(",", $set) . "\nWHERE $where LIMIT 1", $location, lang('Item has been updated.'));
		} else {
			query_redirect("INSERT INTO " . idf_escape($_GET["edit"]) . " SET" . implode(",", $set), $location, lang('Item has been inserted.'));
		}
	}
}

$table_name = adminer_table_name(table_status($_GET["edit"]));
page_header(
	(isset($_GET["default"]) ? lang('Default values') : ($_GET["where"] || (isset($_GET["select"]) && !$_POST["clone"]) ? lang('Edit') : lang('Insert'))),
	$error,
	array((isset($_GET["default"]) ? "table" : "select") => array($_GET["edit"], $table_name)),
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
		echo "<tr><th>" . adminer_field_name($fields, $name) . "</th>";
		$value = (isset($row)
			? (strlen($row[$name]) && ($field["type"] == "enum" || $field["type"] == "set") ? intval($row[$name]) : $row[$name])
			: ($_POST["clone"] && $field["auto_increment"] ? "" : ($where ? $field["default"] : false))
		);
		input($name, $field, $value);
		if (isset($_GET["default"]) && $field["type"] == "timestamp") {
			if (!isset($create) && !$_POST) {
				//! disable sql_mode NO_FIELD_OPTIONS
				$create = $dbh->result($dbh->query("SHOW CREATE TABLE " . idf_escape($_GET["edit"])), 1);
			}
			$checked = ($_POST ? $_POST["on_update"][bracket_escape($name)] : preg_match("~\n\\s*" . preg_quote(idf_escape($name), '~') . " timestamp.* on update CURRENT_TIMESTAMP~i", $create));
			echo '<label><input type="checkbox" name="on_update[' . htmlspecialchars(bracket_escape($name)) . ']" value="1"' . ($checked ? ' checked="checked"' : '') . ' />' . lang('ON UPDATE CURRENT_TIMESTAMP') . '</label>';
		}
		echo "</td></tr>\n";
	}
	echo "</table>\n";
}
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<input type="hidden" name="save" value="1" />
<?php
if (isset($_GET["select"])) {
	hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
}
if ($fields) {
	echo "<input type='submit' value='" . lang('Save') . "' />\n";
	if (!isset($_GET["default"]) && !isset($_GET["select"])) {
		echo "<input type='submit' name='insert' value='" . ($update ? lang('Save and continue edit') : lang('Save and insert next')) . "' />\n";
	}
}
if ($update) {
	echo "<input type='submit' name='delete' value='" . lang('Delete') . "'$confirm />\n";
}
?>
</p>
</form>
