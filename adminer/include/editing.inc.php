<?php
function referencable_primary($self) {
	$return = array(); // table_name => field
	foreach (table_status_referencable() as $table_name => $table) {
		if ($table_name != $self) {
			foreach (fields($table_name) as $field) {
				if ($field["primary"]) {
					if ($return[$table_name]) { // multi column primary key
						unset($return[$table_name]);
						break;
					}
					$return[$table_name] = $field;
				}
			}
		}
	}
	return $return;
}

function edit_type($key, $field, $collations, $foreign_keys = array()) {
	global $structured_types, $unsigned, $inout;
	?>
<td><select name="<?php echo $key; ?>[type]" onchange="editing_type_change(this);"><?php echo optionlist($structured_types + ($foreign_keys ? array(lang('Foreign keys') => $foreign_keys) : array()), $field["type"]); ?></select>
<td><input name="<?php echo $key; ?>[length]" value="<?php echo h($field["length"]); ?>" size="3">
<td><?php
echo "<select name='$key" . "[collation]'" . (ereg('(char|text|enum|set)$', $field["type"]) ? "" : " class='hidden'") . '><option value="">(' . lang('collation') . ')' . optionlist($collations, $field["collation"]) . '</select>';
echo ($unsigned ? " <select name='$key" . "[unsigned]'" . (!$field["type"] || ereg('(int|float|double|decimal)$', $field["type"]) ? "" : " class='hidden'") . '><option>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : '');
?>
<?php
}

function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches) ? implode(",", $matches[0]) : preg_replace('~[^0-9,+-]~', '', $length));
}

function process_type($field, $collate = "COLLATE") {
	global $dbh, $enum_length, $unsigned;
	return " $field[type]"
		. ($field["length"] && !ereg('^date|time$', $field["type"]) ? "(" . process_length($field["length"]) . ")" : "")
		. (ereg('int|float|double|decimal', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (ereg('char|text|enum|set', $field["type"]) && $field["collation"] ? " $collate " . $dbh->quote($field["collation"]) : "")
	;
}

function type_class($type) {
	if (ereg('char|text', $type)) {
		return " class='char'";
	} elseif (ereg('date|time|year', $type)) {
		return " class='date'";
	} elseif (ereg('binary|blob', $type)) {
		return " class='binary'";
	} elseif (ereg('enum|set', $type)) {
		return " class='enum'";
	}
}

function edit_fields($fields, $collations, $type = "TABLE", $allowed = 0, $foreign_keys = array()) {
	global $inout;
	$column_comments = false;
	foreach ($fields as $field) {
		if (strlen($field["comment"])) {
			$column_comments = true;
		}
	}
	?>
<thead><tr>
<?php if ($type == "PROCEDURE") { ?><td><?php echo lang('IN-OUT'); ?><?php } ?>
<th><?php echo ($type == "TABLE" ? lang('Column name') : lang('Parameter name')); ?>
<td><?php echo lang('Type'); ?>
<td><?php echo lang('Length'); ?>
<td><?php echo lang('Options'); ?>
<?php if ($type == "TABLE") { ?>
<td><?php echo lang('NULL'); ?>
<td><input type="radio" name="auto_increment_col" value=""><?php echo lang('Auto Increment'); ?>
<td<?php echo ($column_comments ? "" : " class='hidden'"); ?>><?php echo lang('Comment'); ?>
<?php } ?>
<td><?php echo "<input type='image' name='add[0]' src='../adminer/plus.gif' alt='+' title='" . lang('Add next') . "'>"; ?><script type="text/javascript">row_count = <?php echo count($fields); ?>;</script>
</thead>
<?php
	foreach ($fields as $i => $field) {
		$i++;
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i]));
		?>
<tr<?php echo ($display ? "" : " style='display: none;'"); ?>>
<?php if ($type == "PROCEDURE") { ?><td><select name="fields[<?php echo $i; ?>][inout]"><?php echo optionlist($inout, $field["inout"]); ?></select><?php } ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo h($field["field"]); ?>" onchange="<?php echo (strlen($field["field"]) || count($fields) > 1 ? "" : "editing_add_row(this, $allowed); "); ?>editing_name_change(this);" maxlength="64"><?php } ?><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo h($field[($_POST ? "orig" : "field")]); ?>">
<?php edit_type("fields[$i]", $field, $collations, $foreign_keys); ?>
<?php if ($type == "TABLE") { ?>
<td><input type="checkbox" name="fields[<?php echo $i; ?>][null]" value="1"<?php if ($field["null"]) { ?> checked<?php } ?>>
<td><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked<?php } ?>>
<td<?php echo ($column_comments ? "" : " class='hidden'"); ?>><input name="fields[<?php echo $i; ?>][comment]" value="<?php echo h($field["comment"]); ?>" maxlength="255">
<?php } ?>
<?php
		echo "<td class='nowrap'><input type='image' name='add[$i]' src='../adminer/plus.gif' alt='+' title='" . lang('Add next') . "' onclick='var x = editing_add_row(this, $allowed); if (x) { x.focus(); x.onchange = function () { }; } return !x;'>";
		echo "&nbsp;<input type='image' name='drop_col[$i]' src='../adminer/cross.gif' alt='x' title='" . lang('Remove') . "' onclick='return !editing_remove_row(this);'>";
		echo "&nbsp;<input type='image' name='up[$i]' src='../adminer/up.gif' alt='^' title='" . lang('Move up') . "'>";
		echo "&nbsp;<input type='image' name='down[$i]' src='../adminer/down.gif' alt='v' title='" . lang('Move down') . "'>";
		echo "\n\n";
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
	$pattern = "\\s*(" . ($type == "FUNCTION" ? "" : implode("|", $inout)) . ")?\\s*(?:`((?:[^`]|``)*)`\\s*|\\b(\\S+)\\s+)$type_pattern";
	$create = $dbh->result($dbh->query("SHOW CREATE $type " . idf_escape($name)), 2);
	preg_match("~\\(((?:$pattern\\s*,?)*)\\)" . ($type == "FUNCTION" ? "\\s*RETURNS\\s+$type_pattern" : "") . "\\s*(.*)~is", $create, $match);
	$fields = array();
	preg_match_all("~$pattern\\s*,?~is", $match[1], $matches, PREG_SET_ORDER);
	foreach ($matches as $param) {
		$name = str_replace("``", "`", $param[2]) . $param[3];
		$data_type = strtolower($param[4]);
		$fields[$name] = array(
			"field" => $name,
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
