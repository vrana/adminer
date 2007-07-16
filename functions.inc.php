<?php
function idf_escape($idf) {
	return "`" . str_replace("`", "``", $idf) . "`";
}

function idf_unescape($idf) {
	return str_replace("``", "`", $idf);
}

function bracket_escape($idf, $back = false) {
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

function optionlist($options, $selected = array()) {
	$return = "";
	foreach ($options as $k => $v) {
		if (is_array($v)) {
			$return .= '<optgroup label="' . htmlspecialchars($k) . '">';
		}
		foreach ((is_array($v) ? $v : array($k => $v)) as $key => $val) {
			$checked = in_array($val, (array) $selected, true);
			$return .= '<option' . ($checked ? ' selected="selected"' : '') . '>' . htmlspecialchars($val) . '</option>';
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

function get_vals($query) {
	global $mysql;
	$result = $mysql->query($query);
	$return = array();
	while ($row = $result->fetch_row()) {
		$return[] = $row[0];
	}
	$result->free();
	return $return;
}

function table_status($table) {
	global $mysql;
	$result = $mysql->query("SHOW TABLE STATUS LIKE '" . $mysql->escape_string(addcslashes($table, "%_")) . "'");
	return $result->fetch_assoc();
}

function fields($table) {
	global $mysql;
	$return = array();
	$result = $mysql->query("SHOW FULL COLUMNS FROM " . idf_escape($table));
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			preg_match('~^([^(]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
			$return[$row["Field"]] = array(
				"field" => $row["Field"],
				"type" => $match[1],
				"length" => $match[2],
				"unsigned" => ltrim($match[3] . $match[4]),
				"default" => $row["Default"],
				"null" => ($row["Null"] == "YES"),
				"auto_increment" => ($row["Extra"] == "auto_increment"),
				"collation" => $row["Collation"],
				"privileges" => array_flip(explode(",", $row["Privileges"])),
				"comment" => $row["Comment"],
			);
		}
		$result->free();
	}
	return $return;
}

function indexes($table) {
	global $mysql;
	$return = array();
	$result = $mysql->query("SHOW INDEX FROM " . idf_escape($table));
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$return[$row["Key_name"]]["type"] = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
			$return[$row["Key_name"]]["columns"][$row["Seq_in_index"]] = $row["Column_name"];
			$return[$row["Key_name"]]["lengths"][$row["Seq_in_index"]] = $row["Sub_part"];
		}
		$result->free();
	}
	return $return;
}

function foreign_keys($table) {
	global $mysql, $on_actions;
	static $pattern = '(?:[^`]+|``)+';
	$return = array();
	$result = $mysql->query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		$create_table = $mysql->result($result, 1);
		$result->free();
		preg_match_all("~CONSTRAINT `($pattern)` FOREIGN KEY \\(((?:`$pattern`,? ?)+)\\) REFERENCES `($pattern)`(?:\\.`($pattern)`)? \\(((?:`$pattern`,? ?)+)\\)(?: ON DELETE (" . implode("|", $on_actions) . "))?(?: ON UPDATE (" . implode("|", $on_actions) . "))?~", $create_table, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			preg_match_all("~`($pattern)`~", $match[2], $source);
			preg_match_all("~`($pattern)`~", $match[5], $target);
			$return[$match[1]] = array(
				"db" => idf_unescape(strlen($match[4]) ? $match[3] : $match[4]),
				"table" => idf_unescape(strlen($match[4]) ? $match[4] : $match[3]),
				"source" => array_map('idf_unescape', $source[1]),
				"target" => array_map('idf_unescape', $target[1]),
				"on_delete" => $match[6],
				"on_update" => $match[7],
			);
		}
	}
	return $return;
}

function view($name) {
	global $mysql;
	return array("name" => $name, "select" => preg_replace('~^(?:[^`]+|`[^`]*`)* AS ~U', '', $mysql->result($mysql->query("SHOW CREATE VIEW " . idf_escape($name)), 1)));
}

function normalize_enum($match) {
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($match[0]{0} . $match[0]{0}, $match[0]{0}, substr($match[0], 1, -1))), '\\')) . "'";
}

function routine($name, $type = "PROCEDURE") {
	global $mysql, $enum_length;
	$pattern = "\\s*(IN|OUT|INOUT)?\\s*(?:`((?:[^`]+|``)*)`\\s*|\\b(\\S+)\\s+)([a-z]+)(?:\\s*\\(((?:[^'\")]*|$enum_length)+)\\))?\\s*(zerofill\\s+)?(unsigned(?:\\s+zerofill)?)?";
	$create = $mysql->result($mysql->query("SHOW CREATE $type " . idf_escape($name)), 2);
	preg_match("~\\($pattern(?:\\s*,$pattern)*~is", $create, $match);
	$params = array();
	preg_match_all("~$pattern~is", $match[0], $matches, PREG_SET_ORDER);
	foreach ($matches as $i => $match) {
		$field = array(
			"field" => str_replace("``", "`", $match[2]) . $match[3],
			"type" => $match[4], //! type aliases
			"length" => preg_replace_callback("~$enum_length~s", 'normalize_enum', $match[5]),
			"unsigned" => strtolower(preg_replace('~\\s+~', ' ', trim("$match[7] $match[6]"))),
			"null" => true,
			"inout" => $match[1],
		);
		$params[$i] = $field;
	}
	return array("fields" => $params);
}

function unique_idf($row, $indexes) {
	foreach ($indexes as $index) {
		if ($index["type"] == "PRIMARY" || $index["type"] == "UNIQUE") {
			$return = array();
			foreach ($index["columns"] as $key) {
				if (!isset($row[$key])) {
					continue 2;
				}
				$return[] = urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($row[$key]);
			}
			return $return;
		}
	}
	$return = array();
	foreach ($row as $key => $val) {
		$return[] = (isset($val) ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
	}
	return $return;
}

function where() {
	global $mysql;
	$return = array();
	foreach ((array) $_GET["where"] as $key => $val) {
		$return[] = idf_escape(bracket_escape($key, "back")) . " = BINARY '" . $mysql->escape_string($val) . "'"; //! enum and set
	}
	foreach ((array) $_GET["null"] as $key) {
		$return[] = idf_escape(bracket_escape($key, "back")) . " IS NULL";
	}
	return $return;
}

function collations() {
	global $mysql;
	$return = array();
	$result = $mysql->query("SHOW COLLATION");
	while ($row = $result->fetch_assoc()) {
		$return[$row["Charset"]][] = $row["Collation"];
	}
	$result->free();
	return $return;
}

function engines() {
	global $mysql;
	$return = array();
	$result = $mysql->query("SHOW ENGINES");
	while ($row = $result->fetch_assoc()) {
		if ($row["Support"] == "YES" || $row["Support"] == "DEFAULT") {
			$return[] = $row["Engine"];
		}
	}
	$result->free();
	return $return;
}

function token() {
	return ($GLOBALS["TOKENS"][] = rand(1, 1e6));
}

function token_delete() {
	if ($_POST["token"] && ($pos = array_search($_POST["token"], (array) $GLOBALS["TOKENS"])) !== false) {
		unset($GLOBALS["TOKENS"][$pos]);
		return true;
	}
	return false;
}

function redirect($location, $message = null) {
	if (isset($message)) {
		$_SESSION["message"] = $message;
	}
	token_delete();
	if (strlen(SID)) {
		$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
}

function get_file($key) {
	if (isset($_POST["files"][$key])) {
		$length = strlen($_POST["files"][$key]);
		return ($length & $length < 4 ? intval($_POST["files"][$key]) : base64_decode($_POST["files"][$key]));
	}
	return (!$_FILES[$key] || $_FILES[$key]["error"] ? $_FILES[$key]["error"] : file_get_contents($_FILES[$key]["tmp_name"]));
}

function select($result) {
	global $SELF;
	if (!$result->num_rows) {
		echo "<p class='message'>" . lang('No rows.') . "</p>\n";
	} else {
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		for ($i=0; $row = $result->fetch_row(); $i++) {
			if (!$i) {
				echo "<thead><tr>";
				$links = array();
				$indexes = array();
				$columns = array();
				$blobs = array();
				$types = array();
				for ($j=0; $j < count($row); $j++) {
					$field = $result->fetch_field();
					if (strlen($field->orgtable) && $field->flags & 2) {
						if (!isset($indexes[$field->orgtable])) {
							$indexes[$field->orgtable] = array();
							foreach (indexes($field->orgtable) as $index) {
								if ($index["type"] == "PRIMARY") {
									$indexes[$field->orgtable] = array_flip($index["columns"]);
									break;
								}
							}
							$columns[$field->orgtable] = $indexes[$field->orgtable];
						}
						if (isset($columns[$field->orgtable][$field->orgname])) {
							unset($columns[$field->orgtable][$field->orgname]);
							$indexes[$field->orgtable][$field->orgname] = $j;
							$links[$j] = $field->orgtable;
						}
					}
					if ($field->charsetnr == 63) {
						$blobs[$j] = true;
					}
					$types[$j] = $field->type;
					echo "<th>" . htmlspecialchars($field->name) . "</th>";
				}
				echo "</tr></thead>\n";
			}
			echo "<tr>";
			foreach ($row as $key => $val) {
				if (!isset($val)) {
					$val = "<i>NULL</i>";
				} else {
					if ($blobs[$key] && preg_match('~[\\x80-\\xFF]~', $val)) {
						$val = "<i>" . lang('%d byte(s)', strlen($val)) . "</i>";
					} else {
						$val = (strlen(trim($val)) ? nl2br(htmlspecialchars($val)) : "&nbsp;");
						if ($types[$key] == 254) {
							$val = "<code>$val</code>";
						}
					}
					if (isset($links[$key]) && !$columns[$links[$key]]) {
						$link = "edit=" . urlencode($links[$key]);
						foreach ($indexes[$links[$key]] as $col => $j) {
							$link .= "&amp;where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
						}
						$val = '<a href="' . htmlspecialchars($SELF) . $link . '">' . $val . '</a>';
					}
				}
				echo "<td>$val</td>";
			}
			echo "</tr>\n";
		}
		echo "</table>\n";
	}
	$result->free();
}

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

function edit_fields($fields, $collations, $type = "table") {
	global $types, $unsigned;
?>
<table border="0" cellspacing="0" cellpadding="2">
<thead><tr>
<th><?php echo lang('Column name'); ?></th>
<td><?php echo lang('Type'); ?></td>
<td><?php echo lang('Length'); ?></td>
<td><?php echo lang('Options'); ?></td>
<?php if ($type == "table") { ?>
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
<th><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo htmlspecialchars($field[($_POST ? "orig" : "field")]); ?>" /><input name="fields[<?php echo $i; ?>][field]" value="<?php echo htmlspecialchars($field["field"]); ?>" maxlength="64" /></th>
<td><select name="fields[<?php echo $i; ?>][type]" onchange="type_change(this);"><?php echo optionlist(array_keys($types), $field["type"]); ?></select></td>
<td><input name="fields[<?php echo $i; ?>][length]" value="<?php echo htmlspecialchars($field["length"]); ?>" size="3" /></td>
<td><select name="fields[<?php echo $i; ?>][collation]"><option value="">(<?php echo lang('collation'); ?>)</option><?php echo optionlist($collations, $field["collation"]); ?></select> <select name="fields[<?php echo $i; ?>][unsigned]"><?php echo optionlist($unsigned, $field["unsigned"]); ?></select></td>
<?php if ($type == "table") { ?>
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

if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}
