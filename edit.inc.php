<?php
$where = where();
$fields = array();
foreach (fields($_GET["edit"]) as $name => $field) {
	if (isset($_GET["default"]) ? !$field["auto_increment"] : isset($field["privileges"][$where ? "update" : "insert"])) {
		$fields[$name] = $field;
	}
}
if ($_POST && !$error) {
	if (isset($_POST["delete"])) {
		$set = true;
		$query = "DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
		$message = lang('Item has been deleted.');
	} else {
		$set = array();
		foreach ($fields as $name => $field) {
			$val = process_input($name, $field);
			if ($val !== false) {
				$set[] = idf_escape($name) . (isset($_GET["default"]) ? ($val == "NULL" ? " DROP DEFAULT" : " SET DEFAULT $val") : " = $val");
			}
		}
		if (isset($_GET["default"])) {
			$query = "ALTER TABLE " . idf_escape($_GET["edit"]) . " ALTER " . implode(", ALTER ", $set);
			$message = lang('Default values has been set.');
		} elseif ($where) {
			$query = "UPDATE " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
			$message = lang('Item has been updated.');
		} else {
			$query = "INSERT INTO " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set);
			$message = lang('Item has been inserted.');
		}
	}
	if (!$set || $mysql->query($query)) {
		redirect($SELF . (isset($_GET["default"]) ? "table=" : ($_POST["insert"] ? "edit=" : "select=")) . urlencode($_GET["edit"]), ($set ? $message : null));
	}
	$error = $mysql->error;
}
page_header((isset($_GET["default"]) ? lang('Default values') : ($_GET["where"] ? lang('Edit') : lang('Insert'))) . ": " . htmlspecialchars($_GET["edit"]));

if ($_POST) {
	echo "<p class='error'>" . lang('Error during saving') . ": " . htmlspecialchars($error) . "</p>\n";
	$data = (array) $_POST["fields"];
	foreach ((array) $_POST["null"] as $key => $val) {
		$data[$key] = null;
	}
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (isset($field["privileges"]["select"]) && !preg_match('~binary|blob~', $field["type"])) {
			$select[] = ($field["type"] == "enum" || $field["type"] == "set" ? "1*" . idf_escape($name) . " AS " : "") . idf_escape($name);
		}
	}
	if ($select) {
		$result = $mysql->query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1");
		$data = $result->fetch_assoc();
	} else {
		$data = array();
	}
} else {
	unset($data);
}
?>

<form action="" method="post" enctype="multipart/form-data">
<?php
echo ($fields ? "<table border='0' cellspacing='0' cellpadding='2'>\n" : "");
foreach ($fields as $name => $field) {
	echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
	if (!isset($data)) {
		$value = $field["default"];
	} elseif (strlen($data[$name]) && ($field["type"] == "enum" || $field["type"] == "set")) {
		$value = intval($data[$name]);
	} else {
		$value = $data[$name];
	}
	input($name, $field, $value);
	echo "</td></tr>\n";
}
echo ($fields ? "</table>\n" : "");
?>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($fields) { ?>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (!isset($_GET["default"])) { ?><input type="submit" name="insert" value="<?php echo lang('Save and insert'); ?>" /><?php } ?>
<?php } ?>
<?php if ($where) { ?> <input type="submit" name="delete" value="<?php echo lang('Delete'); ?>" /><?php } ?>
</p>
</form>
