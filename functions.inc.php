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
		foreach ((is_array($v) ? $v : array($v)) as $val) {
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
	$return = array();
	$result = $mysql->query($query);
	if ($result) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[0];
		}
		$result->free();
	}
	return $return;
}

function get_databases() {
	$return = &$_SESSION["databases"][$_GET["server"]];
	if (!isset($return)) {
		flush();
		$return = get_vals("SHOW DATABASES");
	}
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
				"primary" => ($row["Key"] == "PRI"),
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
	return array("select" => preg_replace('~^(?:[^`]+|`[^`]*`)* AS ~U', '', $mysql->result($mysql->query("SHOW CREATE VIEW " . idf_escape($name)), 1)));
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

function where($where) {
	global $mysql;
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " = BINARY '" . $mysql->escape_string($val) . "'"; //! enum and set, columns looking like functions
	}
	foreach ((array) $where["null"] as $key) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " IS NULL";
	}
	return $return;
}

function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches) ? implode(",", $matches[0]) : preg_replace('~[^0-9,]~', '', $length));
}

function collations() {
	global $mysql;
	$return = array();
	$result = $mysql->query("SHOW COLLATION");
	while ($row = $result->fetch_assoc()) {
		if ($row["Default"] && $return[$row["Charset"]]) {
			array_unshift($return[$row["Charset"]], $row["Collation"]);
		} else {
			$return[$row["Charset"]][] = $row["Collation"];
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
		$_SESSION["messages"][] = $message;
	}
	token_delete();
	if (strlen(SID)) {
		$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
}

function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false) {
	global $mysql, $error, $SELF;
	$id = "sql-" . count($_SESSION["messages"]);
	$sql = ($query ? " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><span id='$id' class='hidden'><br /><code class='jush-sql'>" . htmlspecialchars($query) . '</code> <a href="' . htmlspecialchars($SELF) . 'sql=' . urlencode($query) . '">' . lang('Edit') . '</a></span>' : "");
	if ($execute) {
		$failed = !$mysql->query($query);
	}
	if ($failed) {
		$error = htmlspecialchars($mysql->error) . $sql;
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

function queries($query = null) {
	global $mysql;
	static $queries = array();
	if (!isset($query)) {
		return implode(";\n", $queries);
	}
	$queries[] = $query;
	return $mysql->query($query);
}

function remove_from_uri($param = "") {
	$param = "($param|" . session_name() . ")";
	return preg_replace("~\\?$param=[^&]*&~", '?', preg_replace("~\\?$param=[^&]*\$|&$param=[^&]*~", '', $_SERVER["REQUEST_URI"]));
}

function print_page($page) {
	echo " " . ($page == $_GET["page"] ? $page + 1 : '<a href="' . htmlspecialchars(remove_from_uri("page") . ($page ? "&page=$page" : "")) . '">' . ($page + 1) . "</a>");
}

function get_file($key) {
	if (isset($_POST["files"][$key])) {
		$length = strlen($_POST["files"][$key]);
		return ($length && $length < 4 ? intval($_POST["files"][$key]) : base64_decode($_POST["files"][$key]));
	}
	return (!$_FILES[$key] || $_FILES[$key]["error"] ? $_FILES[$key]["error"] : file_get_contents($_FILES[$key]["tmp_name"]));
}

function select($result) {
	global $SELF;
	if (!$result->num_rows) {
		echo "<p class='message'>" . lang('No rows.') . "</p>\n";
	} else {
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		$links = array();
		$indexes = array();
		$columns = array();
		$blobs = array();
		$types = array();
		for ($i=0; $row = $result->fetch_row(); $i++) {
			if (!$i) {
				echo "<thead><tr>";
				for ($j=0; $j < count($row); $j++) {
					$field = $result->fetch_field();
					if (strlen($field->orgtable)) {
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

function shorten_utf8($string, $length) {
	for ($i=0; $i < strlen($string); $i++) {
		if (ord($string[$i]) >= 192) {
			while (ord($string[$i+1]) >= 128 && ord($string[$i+1]) < 192) {
				$i++;
			}
		}
		$length--;
		if ($length == 0) {
			return nl2br(htmlspecialchars(substr($string, 0, $i+1))) . "<em>...</em>";
		}
	}
	return nl2br(htmlspecialchars($string));
}
