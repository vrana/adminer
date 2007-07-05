<?php
$fields = fields($_GET["edit"]);
if ($_POST) {
	if (isset($_POST["delete"])) {
		$query = "DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
		$message = lang('Item has been deleted.');
	} else {
		$set = array();
		foreach ($fields as $key => $field) {
			if (preg_match('~char|text|set~', $field["type"]) ? $_POST["null"][$key] : !strlen($_POST["fields"][$key])) {
				$value = "NULL";
			} elseif ($field["type"] == "enum") {
				$value = intval($_POST["fields"][$key]);
			} elseif ($field["type"] == "set") {
				$value = array_sum((array) $_POST["fields"][$key]);
			} else {
				$value = "'" . mysql_real_escape_string($_POST["fields"][$key]) . "'";
			}
			$set[] = idf_escape(bracket_escape($key, "back")) . " = $value";
		}
		if ($where) {
			$query = "UPDATE " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
			$message = lang('Item has been updated.');
		} else {
			$query = "INSERT INTO " . idf_escape($_GET["edit"]) . " SET " . implode(", ", $set);
			$message = lang('Item has been inserted.');
		}
	}
	if (mysql_query($query)) {
		redirect($SELF . ($_POST["insert"] ? "edit=" : "select=") . urlencode($_GET["edit"]), $message);
	}
	$error = mysql_error();
}
page_header(($_GET["where"] ? lang('Edit') : lang('Insert')) . ": " . htmlspecialchars($_GET["edit"]));

$where = array();
if (is_array($_GET["where"])) {
	foreach ($_GET["where"] as $key => $val) {
		$where[] = idf_escape($key) . " = BINARY '" . mysql_real_escape_string($val) . "'";
	}
}
if (is_array($_GET["null"])) {
	foreach ($_GET["null"] as $key) {
		$where[] = idf_escape($key) . " IS NULL";
	}
}
if ($_POST) {
	echo "<p class='error'>" . lang('Error during saving') . ": " . htmlspecialchars($error) . "</p>\n";
	$data = $_POST["fields"];
	foreach ($_POST["fields"] as $key => $val) {
		$data[$key] = null;
	}
} elseif ($where) {
	$select = array("*");
	foreach ($fields as $name => $field) {
		if ($field["type"] == "enum" || $field["type"] == "set") {
			$select[] = "1*" . idf_escape($name) . " AS " . idf_escape($name);
		}
	}
	$data = mysql_fetch_assoc(mysql_query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1"));
} else {
	$data = array();
}
?>
<form action="" method="post">
<table border='1' cellspacing='0' cellpadding='2'>
<?php
$types = types();
foreach ($fields as $name => $field) {
	echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
	$value = ($data ? $data[$name] : $field["default"]);
	$name = htmlspecialchars(bracket_escape($name));
	if ($field["type"] == "enum") {
		echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value == "0" ? ' checked="checked"' : '') . ' />';
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$id = "field-$name-" . ($i+1);
			echo ' <input type="radio" name="fields[' . $name . ']" id="' . $id . '" value="' . ($i+1) . '"' . ($value == $i+1 ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
		}
		if ($field["null"]) {
			$id = "field-$name-";
			echo '<input type="radio" name="fields[' . $name . ']" id="' . $id . '" value=""' . (strlen($value) ? '' : ' checked="checked"') . ' /><label for="' . $id . '">' . lang('NULL') . '</label> ';
		}
	} elseif ($field["type"] == "set") { //! 64 bits
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$id = "$name-" . ($i+1);
			echo ' <input type="checkbox" name="fields[' . $name . '][]" id="' . $id . '" value="' . (1 << $i) . '"' . (($value >> $i) & 1 ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
		}
	} elseif (strpos($field["type"], "text") !== false) {
		echo '<textarea name="fields[' . $name . ']" cols="50" rows="12">' . htmlspecialchars($value) . '</textarea>';
	} else { //! numbers, date, binary
		echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (strlen($field["length"]) ? " maxlength='$field[length]'" : ($types[$field["type"]] ? " maxlength='" . $types[$field["type"]] . "'" : '')) . ' />';
	}
	if ($field["null"] && preg_match('~char|text|set~', $field["type"])) {
		echo '<input type="checkbox" name="null[' . $name . ']" value="1" id="null-' . $name . '"' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="null-' . $name . '">' . lang('NULL') . '</label>';
	}
	echo "</td></tr>\n";
}
echo "<tr><th></th><td><input type='submit' value='" . lang('Save') . "' /> <input type='submit' name='insert' value='" . lang('Save and insert') . "' />" . ($where ? " <input type='submit' name='delete' value='" . lang('Delete') . "' />" : "") . "</td></tr>\n";
?>
</table>
</form>
