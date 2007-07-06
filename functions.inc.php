<?php
function idf_escape($idf) {
	return "`" . str_replace("`", "``", $idf) . "`";
}

function idf_unescape($idf) {
	return str_replace("``", "`", $idf);
}

function bracket_escape($idf, $back = false) {
	static $trans = array(':' => ':1', ']' => ':2');
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
	while ($row = mysql_fetch_assoc($result)) {
		preg_match('~^([^(]+)(?:\\((.+)\\))?( unsigned)?( zerofill)?$~', $row["Type"], $match);
		$return[$row["Field"]] = array(
			"field" => $row["Field"],
			"type" => $match[1],
			"length" => $match[2],
			"unsigned" => ltrim($match[3] . $match[4]),
			"default" => $row["Default"],
			"null" => ($row["Null"] != "NO"),
			"extra" => $row["Extra"],
			"collation" => $row["Collation"],
		);
	}
	mysql_free_result($result);
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
	$create_table = mysql_result(mysql_query("SHOW CREATE TABLE " . idf_escape($table)), 0, 1);
	preg_match_all('~FOREIGN KEY \\((.+)\\) REFERENCES (?:`(.+)`\\.)?`(.+)` \\((.+)\\)~', $create_table, $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		preg_match_all($pattern, $match[1], $source);
		preg_match_all($pattern, $match[4], $target);
		$return[] = array(idf_unescape($match[2]), idf_unescape($match[3]), array_map('idf_unescape', $source[1]), array_map('idf_unescape', $target[1]));
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
				$return[] = urlencode("where[$key]") . "=" . urlencode($row[$key]);
			}
			return $return;
		}
	}
	$return = array();
	foreach ($row as $key => $val) {
		$return[] = (isset($val) ? urlencode("where[$key]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
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

function redirect($location, $message = null) {
	if (isset($message)) {
		$_SESSION["message"] = $message;
	}
	if (strlen(SID)) {
		$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
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
