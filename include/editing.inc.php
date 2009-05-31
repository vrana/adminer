<?php
function input($name, $field, $value, $separator = "</td><td>") { //! pass empty separator if there are no functions in the whole table
	global $types;
	$name = htmlspecialchars(bracket_escape($name));
	echo "<td" . ($separator ? " class='function'" : "") . ">";
	if ($field["type"] == "enum") {
		echo $separator . (isset($_GET["select"]) ? ' <label><input type="radio" name="fields[' . $name . ']" value="-1" checked="checked" /><em>' . lang('original') . '</em></label>' : "");
		if ($field["null"] || isset($_GET["default"])) {
			echo ' <label><input type="radio" name="fields[' . $name . ']" value=""' . (($field["null"] ? isset($value) : strlen($value)) || isset($_GET["select"]) ? '' : ' checked="checked"') . ' />' . ($field["null"] ? '<em>NULL</em>' : '') . '</label>';
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
		$first = ($field["null"] || isset($_GET["default"])) + isset($_GET["select"]);
		$onchange = ($first ? ' onchange="var f = this.form[\'function[' . addcslashes($name, "\r\n'\\") . ']\']; if (' . $first . ' > f.selectedIndex) f.selectedIndex = ' . $first . ';"' : '');
		$options = array("");
		if (!isset($_GET["default"])) {
			if (preg_match('~char|date|time~', $field["type"])) {
				$options = (preg_match('~char~', $field["type"]) ? array("", "md5", "sha1", "password", "uuid") : array("", "now"));
			}
			if (!isset($_GET["clone"]) && !isset($_GET["call"])) {
				if (preg_match('~int|float|double|decimal~', $field["type"])) {
					$options = array("", "+", "-");
				}
				if (preg_match('~date~', $field["type"])) {
					$options[] = "+ interval";
					$options[] = "- interval";
				}
				if (preg_match('~time~', $field["type"])) {
					$options[] = "addtime";
					$options[] = "subtime";
				}
			}
		}
		if ($field["null"] || isset($_GET["default"])) {
			array_unshift($options, "NULL");
		}
		echo (count($options) > 1 || isset($_GET["select"]) ? '<select name="function[' . $name . ']">' . (isset($_GET["select"]) ? '<option value="orig">' . lang('original') . '</option>' : '') . optionlist($options, (isset($value) ? (string) $_POST["function"][$name] : null)) . '</select>' : "") . $separator;
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
			$maxlength = (!ereg('int', $field["type"]) && preg_match('~^([0-9]+)(,([0-9]+))?$~', $field["length"], $match) ? ($match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0)) : ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0));
			echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . ($maxlength ? " maxlength='$maxlength'" : "") . $onchange . ' />';
		}
	}
}

function process_input($name, $field) {
	global $dbh;
	$idf = bracket_escape($name);
	$function = $_POST["function"][$idf];
	$value = $_POST["fields"][$idf];
	if ($field["type"] == "enum" ? $value == -1 : $function == "orig") {
		return false;
	} elseif ($field["type"] == "enum" || $field["auto_increment"] ? !strlen($value) : $function == "NULL") {
		return "NULL";
	} elseif ($field["type"] == "enum") {
		return (isset($_GET["default"]) ? "'" . $dbh->escape_string($value) . "'" : intval($value));
	} elseif ($field["type"] == "set") {
		return (isset($_GET["default"]) ? "'" . implode(",", array_map(array($dbh, 'escape_string'), (array) $value)) . "'" : array_sum((array) $value));
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		$file = get_file($idf);
		if (!is_string($file)) {
			return false; //! report errors
		}
		return "_binary'" . (is_string($file) ? $dbh->escape_string($file) : "") . "'";
	} elseif ($field["type"] == "timestamp" && $value == "CURRENT_TIMESTAMP") {
		return $value;
	} elseif (preg_match('~^(now|uuid)$~', $function)) {
		return "$function()";
	} elseif (preg_match('~^[+-]$~', $function)) {
		return idf_escape($name) . " $function '" . $dbh->escape_string($value) . "'";
	} elseif (preg_match('~^[+-] interval$~', $function)) {
		return idf_escape($name) . " $function " . (preg_match("~^([0-9]+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : "'" . $dbh->escape_string($value) . "'") . "";
	} elseif (preg_match('~^(addtime|subtime)$~', $function)) {
		return "$function(" . idf_escape($name) . ", '" . $dbh->escape_string($value) . "')";
	} elseif (preg_match('~^(md5|sha1|password)$~', $function)) {
		return "$function('" . $dbh->escape_string($value) . "')";
	} else {
		return "'" . $dbh->escape_string($value) . "'";
	}
}

function edit_type($key, $field, $collations) {
	global $types, $unsigned, $inout;
	?>
<td><select name="<?php echo $key; ?>[type]" onchange="editing_type_change(this);"><?php echo optionlist(array_keys($types), $field["type"]); ?></select></td>
<td><input name="<?php echo $key; ?>[length]" value="<?php echo htmlspecialchars($field["length"]); ?>" size="3" /></td>
<td><?php echo "<select name=\"$key" . '[collation]"' . (preg_match('~char|text|enum|set~', $field["type"]) ? "" : " class='hidden'") . '><option value="">(' . lang('collation') . ')</option>' . optionlist($collations, $field["collation"]) . '</select>' . ($unsigned ? " <select name=\"$key" . '[unsigned]"' . (!$field["type"] || preg_match('~int|float|double|decimal~', $field["type"]) ? "" : " class='hidden'") . '>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : ''); ?></td>
<?php
}

function process_type($field, $collate = "COLLATE") {
	global $dbh, $enum_length, $unsigned;
	return " $field[type]"
		. ($field["length"] && !preg_match('~^date|time$~', $field["type"]) ? "(" . process_length($field["length"]) . ")" : "")
		. (preg_match('~int|float|double|decimal~', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate '" . $dbh->escape_string($field["collation"]) . "'" : "")
	;
}

function edit_fields($fields, $collations, $type = "TABLE", $allowed = 0) {
	global $inout;
	$column_comments = false;
	foreach ($fields as $field) {
		if (strlen($field["comment"])) {
			$column_comments = true;
		}
	}
	?>
<thead><tr>
<?php if ($type == "PROCEDURE") { ?><td><?php echo lang('IN-OUT'); ?></td><?php } ?>
<th><?php echo ($type == "TABLE" ? lang('Column name') : lang('Parameter name')); ?></th>
<td><?php echo lang('Type'); ?></td>
<td><?php echo lang('Length'); ?></td>
<td><?php echo lang('Options'); ?></td>
<?php if ($type == "TABLE") { ?>
<td><?php echo lang('NULL'); ?></td>
<td><input type="radio" name="auto_increment_col" value="" /><?php echo lang('Auto Increment'); ?></td>
<td<?php echo ($column_comments ? "" : " class='hidden'"); ?>><?php echo lang('Comment'); ?></td>
<?php } ?>
<td><input type="image" name="add[0]" src="plus.gif" title="<?php echo lang('Add next'); ?>" /><script type="text/javascript">row_count = <?php echo count($fields); ?>;</script></td>
</tr></thead>
<?php
	foreach ($fields as $i => $field) {
		$i++;
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i]));
		?>
<tr<?php echo ($display ? odd() : " style='display: none;'"); ?>>
<?php if ($type == "PROCEDURE") { ?><td><select name="fields[<?php echo $i; ?>][inout]"><?php echo optionlist($inout, $field["inout"]); ?></select></td><?php } ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /><?php } ?><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /></th>
<?php edit_type("fields[$i]", $field, $collations); ?>
<?php if ($type == "TABLE") { ?>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked="checked"<?php } ?> /></td>
<td><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked="checked"<?php } ?> /></td>
<td<?php echo ($column_comments ? "" : " class='hidden'"); ?>><input name="fields[<?php echo $i; ?>][comment]" value="<?php echo htmlspecialchars($field["comment"]); ?>" maxlength="255" /></td>
<?php } ?>
<td class="nowrap">
<input type="image" name="add[<?php echo $i; ?>]" src="plus.gif" title="<?php echo lang('Add next'); ?>" onclick="return !editing_add_row(this, <?php echo $allowed; ?>);" />
<input type="image" name="drop_col[<?php echo $i; ?>]" src="cross.gif" title="<?php echo lang('Remove'); ?>" onclick="return !editing_remove_row(this);" />
<input type="image" name="up[<?php echo $i; ?>]" src="up.gif" title="<?php echo lang('Move up'); ?>" />
<input type="image" name="down[<?php echo $i; ?>]" src="down.gif" title="<?php echo lang('Move down'); ?>" />
</td>
</tr>
<?php
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

function normalize_enum($match) {
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($match[0]{0} . $match[0]{0}, $match[0]{0}, substr($match[0], 1, -1))), '\\')) . "'";
}

function routine($name, $type) {
	global $dbh, $enum_length, $inout;
	$aliases = array("bit" => "tinyint", "bool" => "tinyint", "boolean" => "tinyint", "integer" => "int", "double precision" => "float", "real" => "float", "dec" => "decimal", "numeric" => "decimal", "fixed" => "decimal", "national char" => "char", "national varchar" => "varchar");
	$type_pattern = "([a-z]+)(?:\\s*\\(((?:[^'\")]*|$enum_length)+)\\))?\\s*(zerofill\\s*)?(unsigned(?:\\s+zerofill)?)?(?:\\s*(?:CHARSET|CHARACTER\\s+SET)\\s*['\"]?([^'\"\\s]+)['\"]?)?";
	$pattern = "\\s*(" . ($type == "FUNCTION" ? "" : implode("|", $inout)) . ")?\\s*(?:`((?:[^`]+|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
	$create = $dbh->result($dbh->query("SHOW CREATE $type " . idf_escape($name)), 2);
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
