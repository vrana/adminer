<?php
function input($name, $field, $value) {
	global $types;
	$name = htmlspecialchars(bracket_escape($name));
	if ($field["type"] == "enum") {
		if (isset($_GET["select"])) {
			echo ' <label><input type="radio" name="fields[' . $name . ']" value="-1" checked="checked" /><em>' . lang('original') . '</em></label>';
		}
		if ($field["null"]) {
			echo ' <label><input type="radio" name="fields[' . $name . ']" value=""' . (isset($value) || isset($_GET["select"]) ? '' : ' checked="checked"') . ' /><em>NULL</em></label>';
		}
		if (!isset($_GET["default"])) {
			echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value === 0 ? ' checked="checked"' : '') . ' />';
		}
		preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = stripcslashes(str_replace("''", "'", $val));
			$checked = (is_int($value) ? $value == $i+1 : $value === $val);
			echo ' <label><input type="radio" name="fields[' . $name . ']" value="' . (isset($_GET["default"]) ? (strlen($val) ? htmlspecialchars($val) : " ") : $i+1) . '"' . ($checked ? ' checked="checked"' : '') . ' />' . htmlspecialchars($val) . '</label>';
		}
	} else {
		$first = (isset($_GET["select"]) ? 2 : 1);
		$onchange = ($field["null"] || isset($_GET["select"]) ? ' onchange="var f = this.form[\'function[' . addcslashes($name, "\r\n'\\") . ']\']; f.selectedIndex = Math.max(f.selectedIndex, ' . $first . ');"' : '');
		$options = (preg_match('~char~', $field["type"]) ? array("", "md5", "sha1", "password", "uuid") : (preg_match('~date|time~', $field["type"]) ? array("", "now") : array("")));
		if ($field["null"]) {
			array_unshift($options, "NULL");
		}
		if (count($options) > 1 || isset($_GET["select"])) {
			echo '<select name="function[' . $name . ']">' . (isset($_GET["select"]) ? '<option value="orig">' . lang('original') . '</option>' : '') . optionlist($options, (isset($value) ? (string) $_POST["function"][$name] : null)) . '</select>';
		}
		if ($field["type"] == "set") { //! 64 bits
			preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $i => $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$checked = (is_int($value) ? ($value >> $i) & 1 : in_array($val, explode(",", $value), true));
				echo ' <label><input type="checkbox" name="fields[' . $name . '][' . $i . ']" value="' . (isset($_GET["default"]) ? htmlspecialchars($val) : 1 << $i) . '"' . ($checked ? ' checked="checked"' : '') . $onchange . ' />' . htmlspecialchars($val) . '</label>';
			}
		} elseif (strpos($field["type"], "text") !== false) {
			echo '<textarea name="fields[' . $name . ']" cols="50" rows="12"' . $onchange . '>' . htmlspecialchars($value) . '</textarea>';
		} elseif (preg_match('~binary|blob~', $field["type"])) {
			echo (ini_get("file_uploads") ? '<input type="file" name="' . $name . '"' . $onchange . ' />' : lang('File uploads are disabled.') . ' ');
		} else {
			echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . (preg_match('~^([0-9]+)(,([0-9]+))?$~', $field["length"], $match) ? " maxlength='" . ($match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0)) . "'" : ($types[$field["type"]] ? " maxlength='" . $types[$field["type"]] . "'" : '')) . $onchange . ' />';
		}
	}
}

function process_input($name, $field) {
	global $mysql;
	$idf = bracket_escape($name);
	$value = $_POST["fields"][$idf];
	if ($field["type"] == "enum" ? $value == -1 : $_POST["function"][$idf] == "orig") {
		return false;
	} elseif ($field["type"] == "enum" || $field["auto_increment"] ? !strlen($value) : $_POST["function"][$idf] == "NULL") {
		return "NULL";
	} elseif ($field["type"] == "enum") {
		return (isset($_GET["default"]) ? "'" . $mysql->escape_string($value) . "'" : intval($value));
	} elseif ($field["type"] == "set") {
		return (isset($_GET["default"]) ? "'" . implode(",", array_map(array($mysql, 'escape_string'), (array) $value)) . "'" : array_sum((array) $value));
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		$file = get_file($idf);
		if (!is_string($file) && ($file != UPLOAD_ERR_NO_FILE || !$field["null"])) {
			return false; //! report errors
		}
		return "_binary'" . (is_string($file) ? $mysql->escape_string($file) : "") . "'";
	} elseif ($field["type"] == "timestamp" && $value == "CURRENT_TIMESTAMP") {
		return $value;
	} elseif (preg_match('~^(now|uuid)$~', $_POST["function"][$idf])) {
		return $_POST["function"][$idf] . "()";
	} elseif (preg_match('~^(md5|sha1|password)$~', $_POST["function"][$idf])) {
		return $_POST["function"][$idf] . "('" . $mysql->escape_string($value) . "')";
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

function process_type($field, $collate = "COLLATE") {
	global $mysql, $enum_length, $unsigned;
	return " $field[type]"
		. ($field["length"] && !preg_match('~^date|time$~', $field["type"]) ? "(" . process_length($field["length"]) . ")" : "")
		. (preg_match('~int|float|double|decimal~', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate '" . $mysql->escape_string($field["collation"]) . "'" : "")
	;
}

function edit_fields($fields, $collations, $type = "TABLE") {
	global $inout;
	?>
<tr>
<?php if ($type == "PROCEDURE") { ?><td><?php echo lang('IN-OUT'); ?></td><?php } ?>
<th><?php echo ($type == "TABLE" ? lang('Column name') : lang('Parameter name')); ?></th>
<td><?php echo lang('Type'); ?></td>
<td><?php echo lang('Length'); ?></td>
<td><?php echo lang('Options'); ?></td>
<?php if ($type == "TABLE") { ?>
<td><?php echo lang('NULL'); ?></td>
<td><input type="radio" name="auto_increment_col" value="" /><?php echo lang('Auto Increment'); ?></td>
<td><?php echo lang('Comment'); ?></td>
<?php } ?>
<td><input type="image" name="add[0]" src="plus.gif" title="<?php echo lang('Add next'); ?>" /></td>
</tr>
<?php
	$column_comments = false;
	foreach ($fields as $i => $field) {
		$i++;
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i]));
		?>
<tr<?php echo ($display ? "" : " style='display: none;'"); ?>>
<?php if ($type == "PROCEDURE") { ?><td><select name="fields[<?php echo $i; ?>][inout]"><?php echo optionlist($inout, $field["inout"]); ?></select></td><?php } ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /><?php } ?><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /></th>
<?php edit_type("fields[$i]", $field, $collations); ?>
<?php if ($type == "TABLE") { ?>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked="checked"<?php } ?> /></td>
<td><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked="checked"<?php } ?> /></td>
<td><input name="fields[<?php echo $i; ?>][comment]" value="<?php echo htmlspecialchars($field["comment"]); ?>" maxlength="255" /></td>
<?php } ?>
<td class="nowrap">
<input type="image" name="add[<?php echo $i; ?>]" src="plus.gif" title="<?php echo lang('Add next'); ?>" onclick="return !add_row(this);" />
<input type="image" name="drop_col[<?php echo $i; ?>]" src="minus.gif" title="<?php echo lang('Remove'); ?>" onclick="return !remove_row(this);" />
<input type="image" name="up[<?php echo $i; ?>]" src="up.gif" title="<?php echo lang('Move up'); ?>" />
<input type="image" name="down[<?php echo $i; ?>]" src="down.gif" title="<?php echo lang('Move down'); ?>" />
</td>
</tr>
<?php
		if (strlen($field["comment"])) {
			$column_comments = true;
		}
	}
	return $column_comments;
}

function process_fields(&$fields) {
	ksort($fields);
	$offset = 0;
	if ($_POST["up"]) {
		$last = 0;
		foreach ($fields as $key => $field) {
			if (key($_POST["up"]) == $key) {
				unset($fields[$key]);
				array_splice($fields, $last, 0, array($field));
				break;
			}
			if (isset($field["field"])) {
				$last = $offset;
			}
			$offset++;
		}
	}
	if ($_POST["down"]) {
		$found = false;
		foreach ($fields as $key => $field) {
			if (isset($field["field"]) && $found) {
				unset($fields[key($_POST["down"])]);
				array_splice($fields, $offset, 0, array($found));
				break;
			}
			if (key($_POST["down"]) == $key) {
				$found = $field;
			}
			$offset++;
		}
	}
	$fields = array_values($fields);
	if ($_POST["add"]) {
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	}
}

function type_change($count) {
	?>
<script type="text/javascript">
var added = '.';
function add_row(button) {
	var match = /([0-9]+)(\.[0-9]+)?/.exec(button.name)
	var x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	var row = button.parentNode.parentNode;
	var row2 = row.cloneNode(true);
	var tags = row.getElementsByTagName('select');
	var tags2 = row2.getElementsByTagName('select');
	for (var i=0; tags.length > i; i++) {
		tags[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		tags2[i].selectedIndex = tags[i].selectedIndex;
	}
	tags = row.getElementsByTagName('input');
	for (var i=0; tags.length > i; i++) {
		if (tags[i].name == 'auto_increment_col') {
			tags[i].value = x;
			tags[i].checked = false;
		}
		tags[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		if (/\[(orig|field|comment)/.test(tags[i].name)) {
			tags[i].value = '';
		}
	}
	row.parentNode.insertBefore(row2, row);
	tags[0].focus();
	added += '0';
	return true;
}
function remove_row(button) {
	var field = button.form[button.name.replace(/drop_col(.+)/, 'fields$1[field]')];
	field.parentNode.removeChild(field);
	button.parentNode.parentNode.style.display = 'none';
	return true;
}
function type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	type.form[name + '[collation]'].style.display = (/char|text|enum|set/.test(type.options[type.selectedIndex].text) ? '' : 'none');
	type.form[name + '[unsigned]'].style.display = (/int|float|double|decimal/.test(type.options[type.selectedIndex].text) ? '' : 'none');
}
for (var i=1; <?php echo $count; ?> >= i; i++) {
	document.getElementById('form')['fields[' + i + '][type]'].onchange();
}
</script>
<?php
}

function normalize_enum($match) {
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($match[0]{0} . $match[0]{0}, $match[0]{0}, substr($match[0], 1, -1))), '\\')) . "'";
}

function routine($name, $type) {
	global $mysql, $enum_length, $inout;
	$aliases = array("bit" => "tinyint", "bool" => "tinyint", "boolean" => "tinyint", "integer" => "int", "double precision" => "float", "real" => "float", "dec" => "decimal", "numeric" => "decimal", "fixed" => "decimal", "national char" => "char", "national varchar" => "varchar");
	$type_pattern = "([a-z]+)(?:\\s*\\(((?:[^'\")]*|$enum_length)+)\\))?\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?(?:\\s*(?:CHARSET|CHARACTER\\s+SET)\\s*['\"]?([^'\"\\s]+)['\"]?)?";
	$pattern = "\\s*(" . ($type == "FUNCTION" ? "" : implode("|", $inout)) . ")?\\s*(?:`((?:[^`]+|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
	$create = $mysql->result($mysql->query("SHOW CREATE $type " . idf_escape($name)), 2);
	preg_match("~\\(((?:$pattern\\s*,?)*)\\)" . ($type == "FUNCTION" ? "\\s*RETURNS\\s+$type_pattern" : "") . "\\s*(.*)~is", $create, $match);
	$fields = array();
	preg_match_all("~$pattern\\s*,?~is", $match[1], $matches, PREG_SET_ORDER);
	foreach ($matches as $i => $param) {
		$data_type = strtolower($param[4]);
		$fields[$i] = array(
			"field" => str_replace("``", "`", $param[2]) . $param[3],
			"type" => (isset($aliases[$data_type]) ? $aliases[$data_type] : $data_type),
			"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $param[5]),
			"unsigned" => strtolower(preg_replace('~\\s+~', ' ', trim("$param[7] $param[6]"))),
			"inout" => strtoupper($param[1]),
			"collation" => strtolower($param[8]),
		);
	}
	if ($type != "FUNCTION") {
		return array("fields" => $fields, "definition" => $match[10]);
	}
	$returns = array("type" => $match[10], "length" => $match[11], "unsigned" => $match[13], "collation" => $match[14]);
	return array("fields" => $fields, "returns" => $returns, "definition" => $match[15]);
}
