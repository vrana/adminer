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
			$key = bracket_escape($name);
			$val = $_POST["fields"][$key];
			if (preg_match('~char|text|set|binary|blob~', $field["type"]) ? $_POST["null"][$key] : !strlen($val)) {
				$val = "NULL";
			} elseif ($field["type"] == "enum") {
				$val = (isset($_GET["default"]) && preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches) ? "'" . $matches[1][$val-1] . "'" : intval($val));
			} elseif ($field["type"] == "set") {
				if (!isset($_GET["default"])) {
					$val = array_sum((array) $val);
				} else {
					preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
					$value = array();
					foreach ((array) $val as $key => $v) {
						$value[] = $matches[1][$key];
					}
					$val = "'" . implode(",", $value) . "'";
				}
			} elseif (preg_match('~binary|blob~', $field["type"])) {
				$file = get_file($key);
				if (!is_string($file) && !$field["null"]) {
					continue; //! report errors, also empty $_POST - not only because of file upload
				}
				$val = "_binary'" . (is_string($file) ? mysql_real_escape_string($file) : "") . "'";
			} else {
				$val = "'" . mysql_real_escape_string($val) . "'";
			}
			$set[] = idf_escape($name) . (isset($_GET["default"]) ? ($val == "NULL" ? " DROP DEFAULT" : " SET DEFAULT $val") : " = $val");
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
	if (!$set || mysql_query($query)) {
		redirect($SELF . (isset($_GET["default"]) ? "table=" : ($_POST["insert"] ? "edit=" : "select=")) . urlencode($_GET["edit"]), ($set ? $message : null));
	}
	$error = mysql_error();
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
	$data = ($select ? mysql_fetch_assoc(mysql_query("SELECT " . implode(", ", $select) . " FROM " . idf_escape($_GET["edit"]) . " WHERE " . implode(" AND ", $where) . " LIMIT 1")) : array());
} else {
	unset($data);
}
?>
<form action="" method="post" enctype="multipart/form-data">
<table border="0" cellspacing="0" cellpadding="2">
<?php
$types = types();
$save_possible = false;
foreach ($fields as $name => $field) {
	$save_possible = true;
	echo "<tr><th>" . htmlspecialchars($name) . "</th><td>";
	$value = (isset($data) ? $data[$name] : $field["default"]);
	$name = htmlspecialchars($_POST ? $name : bracket_escape($name));
	if ($field["type"] == "enum") {
		if (!isset($_GET["default"])) {
			echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value == "0" ? ' checked="checked"' : '') . ' />';
		}
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$id = "field-$name-" . ($i+1);
			$checked = (isset($data) ? $value == $i+1 : $val === $field["default"]);
			echo ' <input type="radio" name="fields[' . $name . ']" id="' . $id . '" value="' . ($i+1) . '"' . ($checked ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
		}
		if ($field["null"]) {
			$id = "field-$name-";
			echo '<input type="radio" name="fields[' . $name . ']" id="' . $id . '" value=""' . (strlen($value) ? '' : ' checked="checked"') . ' /><label for="' . $id . '">' . lang('NULL') . '</label> ';
		}
	} elseif ($field["type"] == "set") { //! 64 bits
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$id = "$name-" . ($i+1);
			$checked = (isset($data) ? ($value >> $i) & 1 : in_array(str_replace("''", "'", $val), explode(",", $field["default"]), true));
			echo ' <input type="checkbox" name="fields[' . $name . '][' . $i . ']" id="' . $id . '" value="' . (1 << $i) . '"' . ($checked ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars(str_replace("''", "'", $val)) . '</label>';
		}
	} elseif (strpos($field["type"], "text") !== false) {
		echo '<textarea name="fields[' . $name . ']" cols="50" rows="12">' . htmlspecialchars($value) . '</textarea>';
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		echo (ini_get("file_uploads") ? '<input type="file" name="' . $name . '" />' : lang('File uploads are disabled.') . ' ');
	} else { //! binary
		echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (strlen($field["length"]) ? " maxlength='$field[length]'" : ($types[$field["type"]] ? " maxlength='" . $types[$field["type"]] . "'" : '')) . ' />';
	}
	if ($field["null"] && preg_match('~char|text|set|binary|blob~', $field["type"])) {
		echo '<input type="checkbox" name="null[' . $name . ']" value="1" id="null-' . $name . '"' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="null-' . $name . '">' . lang('NULL') . '</label>';
	}
	echo "</td></tr>\n";
}
?>
</table>
<p>
<input type="hidden" name="token" value="<?php echo $token; ?>" />
<?php if ($save_possible) { ?>
<input type="submit" value="<?php echo lang('Save'); ?>" />
<?php if (!isset($_GET["default"])) { ?><input type="submit" name="insert" value="<?php echo lang('Save and insert'); ?>" /><?php } ?>
<?php } ?>
<?php if ($where) { ?> <input type="submit" name="delete" value="<?php echo lang('Delete'); ?>" /><?php } ?>
</p>
</form>
