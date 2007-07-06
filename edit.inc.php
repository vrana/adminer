<?php
$fields = fields($_GET["edit"]);
$where = array();
foreach ((array) $_GET["where"] as $key => $val) {
	$where[] = idf_escape($key) . " = BINARY '" . mysql_real_escape_string($val) . "'"; //! enum and set
}
foreach ((array) $_GET["null"] as $key) {
	$where[] = idf_escape($key) . " IS NULL";
}
if ($_POST) {
	if (isset($_POST["delete"])) {
		$query = "DELETE FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1";
		$message = lang('Item has been deleted.');
	} else {
		$set = array();
		foreach ($_POST["fields"] as $key => $val) {
			$name = bracket_escape($key, "back");
			$field = $fields[$name];
			if (preg_match('~char|text|set~', $field["type"]) ? $_POST["null"][$key] : !strlen($val)) {
				$val = "NULL";
			} elseif ($field["type"] == "enum") {
				$val = intval($val);
			} elseif ($field["type"] == "set") {
				$val = array_sum((array) $val);
			} else {
				$val = "'" . mysql_real_escape_string($val) . "'";
			}
			$set[] = idf_escape($name) . " = $val";
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

if ($_POST) {
	echo "<p class='error'>" . lang('Error during saving') . ": " . htmlspecialchars($error) . "</p>\n";
	$data = $_POST["fields"];
	foreach ($_POST["null"] as $key => $val) {
		$data[$key] = null;
	}
} elseif ($where) {
	$select = array();
	foreach ($fields as $name => $field) {
		if (in_array("select", $field["privileges"]) && in_array(($where ? "update" : "insert"), $field["privileges"])) {
			$select[] = ($field["type"] == "enum" || $field["type"] == "set" ? "1*" . idf_escape($name) . " AS " : "") . idf_escape($name);
		}
	}
	$data = ($select ? mysql_fetch_assoc(mysql_query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1")) : array());
} else {
	$data = array();
}
?>
<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<?php
$types = types();
foreach ($fields as $name => $field) {
	if (in_array(($where ? "update" : "insert"), $field["privileges"])) {
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
		} else { //! binary
			echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (strlen($field["length"]) ? " maxlength='$field[length]'" : ($types[$field["type"]] ? " maxlength='" . $types[$field["type"]] . "'" : '')) . ' />';
		}
		if ($field["null"] && preg_match('~char|text|set~', $field["type"])) {
			echo '<input type="checkbox" name="null[' . $name . ']" value="1" id="null-' . $name . '"' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="null-' . $name . '">' . lang('NULL') . '</label>';
		}
		echo "</td></tr>\n";
	}
}
?>
</table>
<p><input type="hidden" name="sent" value="1" /></th><td><input type="submit" value="<?php echo lang('Save'); ?>" /> <input type="submit" name="insert" value="<?php echo lang('Save and insert'); ?>" /><?php if ($where) { ?> <input type="submit" name="delete" value="<?php echo lang('Delete'); ?>" /><?php } ?></p>
</form>
