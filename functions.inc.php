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

function optionlist($options, $selected = array(), $not_vals = false) {
	$return = "";
	foreach ($options as $k => $v) {
		if (is_array($v)) {
			$return .= '<optgroup label="' . htmlspecialchars($k) . '">';
		}
		foreach ((is_array($v) ? $v : array($k => $v)) as $key => $val) {
			$checked = in_array(($not_vals ? $val : $key), (array) $selected, true);
			$return .= '<option' . ($not_vals ? '' : ' value="' . htmlspecialchars($key) . '"') . ($checked ? ' selected="selected"' : '') . '>' . htmlspecialchars($val) . '</option>';
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

function fields($table) {
	$return = array();
	$result = mysql_query("SHOW FULL COLUMNS FROM " . idf_escape($table));
	if ($result) {
		while ($row = mysql_fetch_assoc($result)) {
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
		mysql_free_result($result);
	}
	return $return;
}

function indexes($table) {
	$return = array();
	$result = mysql_query("SHOW INDEX FROM " . idf_escape($table));
	while ($row = mysql_fetch_assoc($result)) {
		$return[$row["Key_name"]]["type"] = ($row["Key_name"] == "PRIMARY" ? "PRIMARY" : ($row["Index_type"] == "FULLTEXT" ? "FULLTEXT" : ($row["Non_unique"] ? "INDEX" : "UNIQUE")));
		$return[$row["Key_name"]]["columns"][$row["Seq_in_index"]] = $row["Column_name"];
	}
	mysql_free_result($result);
	return $return;
}

function foreign_keys($table) {
	static $pattern = '~`((?:[^`]*|``)+)`~';
	$return = array();
	$result = mysql_query("SHOW CREATE TABLE " . idf_escape($table));
	if ($result) {
		$create_table = mysql_result($result, 0, 1);
		mysql_free_result($result);
		preg_match_all('~FOREIGN KEY \\((.+)\\) REFERENCES (?:`(.+)`\\.)?`(.+)` \\((.+)\\)~', $create_table, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			preg_match_all($pattern, $match[1], $source);
			preg_match_all($pattern, $match[4], $target);
			$return[] = array(idf_unescape($match[2]), idf_unescape($match[3]), array_map('idf_unescape', $source[1]), array_map('idf_unescape', $target[1]));
		}
	}
	return $return;
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
	$return = array();
	foreach ((array) $_GET["where"] as $key => $val) {
		$return[] = idf_escape(bracket_escape($key, "back")) . " = BINARY '" . mysql_real_escape_string($val) . "'"; //! enum and set
	}
	foreach ((array) $_GET["null"] as $key) {
		$return[] = idf_escape(bracket_escape($key, "back")) . " IS NULL";
	}
	return $return;
}

function collations() {
	$return = array();
	$result = mysql_query("SHOW COLLATION");
	while ($row = mysql_fetch_assoc($result)) {
		$return[$row["Charset"]][] = $row["Collation"];
	}
	mysql_free_result($result);
	return $return;
}

function engines() {
	$return = array();
	$result = mysql_query("SHOW ENGINES");
	while ($row = mysql_fetch_assoc($result)) {
		if ($row["Support"] == "YES" || $row["Support"] == "DEFAULT") {
			$return[] = $row["Engine"];
		}
	}
	mysql_free_result($result);
	return $return;
}

function types() {
	return array(
		"tinyint" => 3, "smallint" => 5, "mediumint" => 8, "int" => 10, "bigint" => 20,
		"float" => 12, "double" => 21, "decimal" => 66,
		"date" => 10, "datetime" => 19, "timestamp" => 19, "time" => 10, "year" => 4,
		"char" => 255, "varchar" => 65535,
		"binary" => 255, "varbinary" => 65535,
		"tinytext" => 255, "text" => 65535, "mediumtext" => 16777215, "longtext" => 4294967295,
		"tinyblob" => 255, "blob" => 65535, "mediumblob" => 16777215, "longblob" => 4294967295,
		"enum" => 65535, "set" => 64,
	);
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
	if (!mysql_num_rows($result)) {
		echo "<p class='message'>" . lang('No rows.') . "</p>\n";
	} else {
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		for ($i=0; $row = mysql_fetch_row($result); $i++) {
			if (!$i) {
				echo "<thead><tr>";
				$links = array();
				$indexes = array();
				$columns = array();
				$blobs = array();
				for ($j=0; $j < count($row); $j++) {
					$field = mysql_fetch_field($result, $j);
					if (strlen($field->table) && $field->primary_key) {
						$links[$j] = $field->table;
						if (!isset($indexes[$field->table])) {
							$indexes[$field->table] = array();
							foreach (indexes($field->table) as $index) {
								if ($index["type"] == "PRIMARY") {
									$indexes[$field->table] = array_flip($index["columns"]);
									break;
								}
							}
							$columns[$field->table] = $indexes[$field->table];
						}
						unset($columns[$field->table][$field->name]);
						$indexes[$field->table][$field->name] = $j;
						$links[$j] = $field->table;
					}
					if ($field->blob) {
						$blobs[$j] = true;
					}
					echo "<th>" . htmlspecialchars($field->name) . "</th>";
				}
				echo "</tr></thead>\n";
			}
			echo "<tr>";
			foreach ($row as $key => $val) {
				if (!isset($val)) {
					$val = "<i>NULL</i>";
				} else {
					$val = ($blobs[$key] && preg_match('~[\\x80-\\xFF]~', $val) ? "<i>" . lang('%d byte(s)', strlen($val)) . "</i>" : (trim($val) ? nl2br(htmlspecialchars($val)) : "&nbsp;"));
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
	mysql_free_result($result);
}

function input($name, $field, $value) {
	static $types;
	if (!isset($types)) {
		$types = types();
	}
	$name = htmlspecialchars(bracket_escape($name));
	if ($field["type"] == "enum") {
		if (!isset($_GET["default"])) {
			echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value === 0 ? ' checked="checked"' : '') . ' />';
		}
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = str_replace("''", "'", $val);
			$id = "field-$name-" . ($i+1);
			$checked = (is_int($value) ? $value == $i+1 : $value === $val); //! '' collide with NULL in $_GET["default"]
			echo ' <input type="radio" name="fields[' . $name . ']" id="' . $id . '" value="' . (isset($_GET["default"]) ? htmlspecialchars($val) : $i+1) . '"' . ($checked ? ' checked="checked"' : '') . ' /><label for="' . $id . '">' . htmlspecialchars($val) . '</label>';
		}
		if ($field["null"]) {
			$id = "field-$name-";
			echo '<input type="radio" name="fields[' . $name . ']" id="' . $id . '" value=""' . (strlen($value) ? '' : ' checked="checked"') . ' /><label for="' . $id . '">' . lang('NULL') . '</label> ';
		}
	} elseif ($field["type"] == "set") { //! 64 bits
		preg_match_all("~'((?:[^']*|'')+)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = str_replace("''", "'", $val);
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
		echo '<input type="checkbox" name="null[' . $name . ']" value="1" id="' . $id . '"' . (isset($value) ? '' : ' checked="checked"') . ' /><label for="' . $id . '">' . lang('NULL') . '</label>';
	}
}

function process_input($name, $field) {
	$name = bracket_escape($name);
	$return = $_POST["fields"][$name];
	if (preg_match('~char|text|set|binary|blob~', $field["type"]) ? $_POST["null"][$name] : !strlen($return)) {
		$return = "NULL";
	} elseif ($field["type"] == "enum") {
		$return = (isset($_GET["default"]) ? "'" . mysql_real_escape_string($return) . "'" : intval($return));
	} elseif ($field["type"] == "set") {
		$return = (isset($_GET["default"]) ? "'" . implode(",", array_map('mysql_real_escape_string', (array) $return)) . "'" : array_sum((array) $return));
	} elseif (preg_match('~binary|blob~', $field["type"])) {
		$file = get_file($name);
		if (!is_string($file) && !$field["null"]) {
			return false; //! report errors, also empty $_POST (too big POST data, not only FILES)
		}
		$return = "_binary'" . (is_string($file) ? mysql_real_escape_string($file) : "") . "'";
	} else {
		$return = "'" . mysql_real_escape_string($return) . "'";
	}
	return $return;
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
