<?php
$where = (isset($_GET["select"]) ? array() : where($_GET));
$update = ($where && !$_GET["clone"]);
$fields = fields($_GET["edit"]);
foreach ($fields as $name => $field) {
	if (isset($_GET["default"]) ? $field["auto_increment"] || preg_match('~text|blob~', $field["type"]) : !isset($field["privileges"][$update ? "update" : "insert"])) {
		unset($fields[$name]);
	}
}
if ($_POST && !$error && !isset($_GET["select"])) {
	$location = ($_POST["insert"] ? $_SERVER["REQUEST_URI"] : $SELF . (isset($_GET["default"]) ? "table=" : "select=") . urlencode($_GET["edit"]));
	if (isset($_POST["delete"])) {
		query_redirect("DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1", $location, lang('Item has been deleted.'));
	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($name, $field);
			if (!isset($_GET["default"])) {
				if ($val !== false || !$update) {
					$set[] = idf_escape($name) . " = " . ($val !== false ? $val : "''");
				}
			} elseif ($val !== false) {
				if ($field["type"] == "timestamp" && $val != "NULL") { //! doesn't allow DEFAULT NULL and no ON UPDATE
					$set[] = " MODIFY " . idf_escape($name) . " timestamp" . ($field["null"] ? " NULL" : "") . " DEFAULT $val" . ($_POST["on_update"][bracket_escape($name)] ? " ON UPDATE CURRENT_TIMESTAMP" : "");
				} else {
					$set[] = " ALTER " . idf_escape($name) . ($val == "NULL" ? " DROP DEFAULT" : " SET DEFAULT $val");
				}
			}
		}
		if (!$set) {
			redirect($location);
		}
		if (isset($_GET["default"])) {
			query_redirect("ALTER TABLE " . idf_escape($_GET["edit"]) . implode(",", $set), $location, lang('Default values has been set.'));
		} elseif ($update) {
			query_redirect("UPDATE " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $where) . " LIMIT 1", $location, lang('Item has been updated.'));
		} else {
			query_redirect("INSERT INTO " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set), $location, lang('Item has been inserted.'));
		}
	}
}
page_header((isset($_GET["default"]) ? lang('Default values') : ($_GET["where"] || isset($_GET["select"]) ? lang('Edit') : lang('Insert'))), $error, array((isset($_GET["default"]) ? "table" : "select") => $_GET["edit"]), $_GET["edit"]);

unset($row);
if ($_POST) {
	$row = (array) $_POST["fields"];
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"]) && (!$_GET["clone"] || !$field["auto_increment"])) {
			$select[] = ($field["type"] == "enum" || $field["type"] == "set" ? "1*" . idf_escape($name) . " AS " : "") . idf_escape($name);
		}
	}
	$row = array();
	if ($select) {
		$result = $mysql->query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1");
		$row = $result->fetch_assoc();
		$result->free();
	}
}
?>

<form action="" method="post" enctype="multipart/form-data">
<?php
if ($fields) {
	unset($create);
	echo "<table border='0' cellspacing='0' cellpadding='2'>\n";
	foreach ($fields as $name => $field) {
		echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
		$value = (!isset($row) ? $field["default"] :
			(strlen($row[$name]) && ($field["type"] == "enum" || $field["type"] == "set") ? intval($row[$name]) :
			($_POST["clone"] && $field["auto_increment"] ? "" :
			$row[$name]
		)));
		input($name, $field, $value);
		if (isset($_GET["default"]) && $field["type"] == "timestamp") {
			if (!isset($create) && !$_POST) {
				//! disable sql_mode NO_FIELD_OPTIONS
				$create = $mysql->result($mysql->query("SHOW CREATE TABLE " . idf_escape($_GET["edit"])), 1);
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
<?php
if (isset($_GET["select"])) {
	hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
	echo "<input type='hidden' name='save' value='1' />\n";
}
if ($fields) {
	?>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (!isset($_GET["default"]) && !isset($_GET["select"])) { ?><input type="submit" name="insert" value="<?php echo ($update ? lang('Save and continue edit') : lang('Save and insert next')); ?>" /><?php } ?>
<?php } ?>
<?php if ($update) { ?> <input type="submit" name="delete" value="<?php echo lang('Delete'); ?>"<?php echo $confirm; ?> /><?php } ?>
</p>
</form>
