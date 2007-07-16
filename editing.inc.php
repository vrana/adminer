<?php
function input($name, $field, $value) {
	global $types;
	$name = htmlspecialchars(bracket_escape($name));
	if ($field["type"] == "enum") {
		if (!isset($_GET["default"])) {
			echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value === 0 ? ' checked="checked"' : '') . ' />';
		}
		preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = stripcslashes(str_replace("''", "'", $val));
			$id = "field-$name-" . ($i+1);
			$checked = (is_int($value) ? $value == $i+1 : $value === $val); //! '' collide with NULL in $_GET["default"]
			echo ' <label for="' . $id . '"><input type="radio" name="fields[' . $name . ']" id="' . $id . '" value="' . (isset($_GET["default"]) ? htmlspecialchars($val) : $i+1) . '"' . ($checked ? ' checked="checked"' : '') . ' />' . htmlspecialchars($val) . '</label>';
		}
		if ($field["null"]) {
			$id = "field-$name-";
			echo ' <label for="' . $id . '"><input type="radio" name="fields[' . $name . ']" id="' . $id . '" value=""' . (strlen($value) ? '' : ' checked="checked"') . ' />' . lang('NULL') . '</label>';
		}
	} elseif ($field["type"] == "set") { //! 64 bits
		preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = stripcslashes(str_replace("''", "'", $val));
			$id = "field-$name-" . ($i+1);
			$checked = (is_int($value) ? ($value >> $i) & 1 : in_array($val, explode(",", $value), true));
			echo ' <input type="checkbox" name="fields[' . $name . '][' . $i . ']" id="' . $id . '" value="' . (isset($_GET["default"]) ? htmlspecialchars($val) : 1 << $i) . '"' . ($checked ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars($val) . '</label>';
		}
	} elseif (strpos($field["type"], "text") !== false) {
		echo '<textarea name="fields[' . $name . ']" cols="50" rows="12">' . htmlspecialchars($value) . '</textarea>';
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		echo (ini_get("file_uploads") ? '<input type="file" name="' . $name . '" />' : lang('File uploads are disabled.') . ' ');
	} else {
		echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (strlen($field["length"]) ? " maxlength='$field[length]'" : ($types[$field["type"]] ? " maxlength='" . $types[$field["type"]] . "'" : '')) . ' />';
	}
	if ($field["null"] && preg_match('~char|text|set|binary|blob~', $field["type"])) {
		$id = "null-$name";
		echo '<label for="' . $id . '"><input type="checkbox" name="null[' . $name . ']" value="1" id="' . $id . '"' . (isset($value) ? '' : ' checked="checked"') . ' />' . lang('NULL') . '</label>';
	}
}

function process_input($name, $field) {
	global $mysql;
	$name = bracket_escape($name);
	$value = $_POST["fields"][$name];
	if (preg_match('~char|text|set|binary|blob~', $field["type"]) ? $_POST["null"][$name] : !strlen($value)) {
		return "NULL";
	} elseif ($field["type"] == "enum") {
		return (isset($_GET["default"]) ? "'" . $mysql->escape_string($value) . "'" : intval($value));
	} elseif ($field["type"] == "set") {
		return (isset($_GET["default"]) ? "'" . implode(",", array_map(array($mysql, 'escape_string'), (array) $value)) . "'" : array_sum((array) $value));
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		$file = get_file($name);
		if (!is_string($file) && !$field["null"]) {
			return false; //! report errors, also empty $_POST (too big POST data, not only FILES)
		}
		return "_binary'" . (is_string($file) ? $mysql->escape_string($file) : "") . "'";
	} else {
		return "'" . $mysql->escape_string($value) . "'";
	}
}

function edit_type($key, $field, $collations) {
	global $types, $unsigned, $inout;
?>
<td><select name="<?php echo $key; ?>[type]" onchange="type_change(this);"><?php echo optionlist(array_keys($types), $field["type"]); ?></select></td>
<td><input name="<?php echo $key; ?>[length]" value="<?php echo htmlspecialchars($field["length"]); ?>" size="3" /></td>
<td><select name="<?php echo $key; ?>[collation]"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $field["collation"]); ?></select> <select name="<?php echo $key; ?>[unsigned]"><?php echo optionlist($unsigned, $field["unsigned"]); ?></select></td>
<?php
}

function process_type($field) {
	global $mysql, $enum_length, $unsigned;
	return " $field[type]"
		. ($field["length"] ? "(" . (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $field["length"]) && preg_match_all("~$enum_length~", $field["length"], $matches) ? implode(",", $matches[0]) : intval($field["length"])) . ")" : "")
		. (preg_match('~int|float|double|decimal~', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " COLLATE '" . $mysql->escape_string($field["collation"]) . "'" : "")
	;
}

function edit_fields($fields, $collations, $type = "TABLE") {
	global $inout;
?>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr>
<?php if ($type == "PROCEDURE") { ?><td><?php echo lang('In-Out'); ?></td><?php } ?>
<th><?php echo lang('Column name'); ?></th>
<td><?php echo lang('Type'); ?></td>
<td><?php echo lang('Length'); ?></td>
<td><?php echo lang('Options'); ?></td>
<?php if ($type == "TABLE") { ?>
<td><?php echo lang('NULL'); ?></td>
<td><input type="radio" name="auto_increment" value="" /><?php echo lang('Auto Increment'); ?></td>
<td id="comment-0"><?php echo lang('Comment'); ?></td>
<?php } ?>
<td><input type="submit" name="add[0]" value="<?php echo lang('Add next'); ?>" /></td>
</tr></thead>
<?php
$column_comments = false;
foreach ($fields as $i => $field) {
	$i++;
	?>
<tr>
<?php if ($type == "PROCEDURE") { ?><td><select name="inout"><?php echo optionlist($inout, $field["inout"]); ?></select></td><?php } ?>
<th><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /></th>
<?php edit_type("fields[$i]", $field, $collations); ?>
<?php if ($type == "TABLE") { ?>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked="checked"<?php } ?> /></td>
<td><input type="radio" name="auto_increment" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked="checked"<?php } ?> /></td>
<td id="comment-<?php echo $i; ?>"><input name="fields[<?php echo $i; ?>][comment]" value="<?php echo htmlspecialchars($field["comment"]); ?>" maxlength="255" /></td>
<?php } ?>
<td><input type="submit" name="add[<?php echo $i; ?>]" value="<?php echo lang('Add next'); ?>" /></td>
</tr>
<?php
	if (strlen($field["comment"])) {
		$column_comments = true;
	}
}
//! JavaScript for next rows
?>
</table>
<script type="text/javascript">
function type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	type.form[name + '[collation]'].style.display = (/char|text|enum|set/.test(type.form[name + '[type]'].value) ? '' : 'none');
	type.form[name + '[unsigned]'].style.display = (/int|float|double|decimal/.test(type.form[name + '[type]'].value) ? '' : 'none');
}
for (var i=1; <?php echo count($fields); ?> >= i; i++) {
	document.getElementById('form')['fields[' + i + '][type]'].onchange();
}
</script>
<?php
	return $column_comments;
}

function routine($name, $type) {
	global $mysql, $enum_length, $inout;
	$type_pattern = "([a-z]+)(?:\\s*\\(((?:[^'\")]*|$enum_length)+)\\))?\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?";
	$pattern = "\\s*(" . ($type == "FUNCTION" ? "" : implode("|", $inout)) . ")?\\s*(?:`((?:[^`]+|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
	$create = $mysql->result($mysql->query("SHOW CREATE $type " . idf_escape($name)), 2);
	preg_match("~\\(($pattern(?:\\s*,$pattern)*)\\)" . ($type == "FUNCTION" ? "\\s*RETURNS\\s+$type_pattern" : "") . "\\s*(.*)~is", $create, $match);
	$fields = array();
	preg_match_all("~$pattern~is", $match[1], $matches, PREG_SET_ORDER);
	foreach ($matches as $i => $param) {
		$fields[$i] = array(
			"field" => str_replace("``", "`", $param[2]) . $param[3],
			"type" => $param[4], //! type aliases
			"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $param[5]),
			"unsigned" => strtolower(preg_replace('~\\s+~', ' ', trim("$param[7] $param[6]"))),
			"null" => true,
			"inout" => strtoupper($param[1]),
			//! detect character set
		);
	}
	if ($type != "FUNCTION") {
		return array("fields" => $fields, "definition" => $match[16]);
	}
	$returns = array(
		"type" => $match[16],
		"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $match[17]),
		"unsigned" => strtolower(preg_replace('~\\s+~', ' ', trim("$match[19] $match[18]"))),
	);
	return array("fields" => $fields, "returns" => $returns, "definition" => $match[20]);
}
