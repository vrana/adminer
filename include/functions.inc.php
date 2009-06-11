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

function optionlist($options, $selected = null) {
	$return = "";
	foreach ($options as $k => $v) {
		if (is_array($v)) {
			$return .= '<optgroup label="' . htmlspecialchars($k) . '">';
		}
		foreach ((is_array($v) ? $v : array($v)) as $val) {
			$return .= '<option' . ($val === $selected ? ' selected="selected"' : '') . '>' . htmlspecialchars($val) . '</option>';
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

function get_vals($query, $column = 0) {
	global $dbh;
	$return = array();
	$result = $dbh->query($query);
	if ($result) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
		$result->free();
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

function where($where) {
	global $dbh;
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " = BINARY '" . $dbh->escape_string($val) . "'"; //! enum and set, columns looking like functions
	}
	foreach ((array) $where["null"] as $key) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " IS NULL";
	}
	return $return;
}

function where_check($val) {
	parse_str($val, $check);
	return where($check);
}

function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches) ? implode(",", $matches[0]) : preg_replace('~[^0-9,+-]~', '', $length));
}

function redirect($location, $message = null) {
	if (isset($message)) {
		$_SESSION["messages"][] = $message;
	}
	if (strlen(SID)) {
		$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
}

function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false) {
	global $dbh, $error, $SELF;
	$id = "sql-" . count($_SESSION["messages"]);
	$sql = "";
	if ($query) {
		$sql = " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><span id='$id' class='hidden'><br /><code class='jush-sql'>" . htmlspecialchars($query) . '</code><br /><a href="' . htmlspecialchars($SELF) . 'sql=&amp;history=' . count($_SESSION["history"][$_GET["server"]][$_GET["db"]]) . '">' . lang('Edit') . '</a></span>';
		$_SESSION["history"][$_GET["server"]][$_GET["db"]][] = $query;
	}
	if ($execute) {
		$failed = !$dbh->query($query);
	}
	if ($failed) {
		$error = htmlspecialchars($dbh->error) . $sql;
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

function queries($query = null) {
	global $dbh;
	static $queries = array();
	if (!isset($query)) {
		return implode(";\n", $queries);
	}
	$queries[] = $query;
	return $dbh->query($query);
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

function odd($s = ' class="odd"') {
	static $i = 0;
	if (!$s) { // reset counter
		$i = -1;
	}
	return ($i++ % 2 ? $s : '');
}

function select($result, $dbh2 = null) {
	global $SELF;
	if (!$result->num_rows) {
		echo "<p class='message'>" . lang('No rows.') . "</p>\n";
	} else {
		echo "<table cellspacing='0'>\n";
		$links = array();
		$indexes = array();
		$columns = array();
		$blobs = array();
		$types = array();
		odd('');
		for ($i=0; $row = $result->fetch_row(); $i++) {
			if (!$i) {
				echo "<thead><tr>";
				for ($j=0; $j < count($row); $j++) {
					$field = $result->fetch_field();
					if (strlen($field->orgtable)) {
						if (!isset($indexes[$field->orgtable])) {
							$indexes[$field->orgtable] = array();
							foreach (indexes($field->orgtable, $dbh2) as $index) {
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
			echo "<tr" . odd() . ">";
			foreach ($row as $key => $val) {
				if (!isset($val)) {
					$val = "<i>NULL</i>";
				} else {
					if ($blobs[$key] && !is_utf8($val)) {
						$val = "<i>" . lang('%d byte(s)', strlen($val)) . "</i>"; //! link to download
					} else {
						$val = nl2br(htmlspecialchars($val));
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

function is_utf8($val) {
	return (preg_match('~~u', $val) && !preg_match('~[\\0-\\x8\\xB\\xC\\xE-\\x1F]~', $val));
}

function shorten_utf8($string, $length) {
	preg_match("~^(.{0,$length})(.?)~su", $string, $match);
	return nl2br(htmlspecialchars($match[1])) . ($match[2] ? "<em>...</em>" : "");
}

function friendly_url($val) {
	return preg_replace('~[^a-z0-9_]~i', '-', $val);
}

function hidden_fields($process, $ignore = array()) {
	while (list($key, $val) = each($process)) {
		if (is_array($val)) {
			foreach ($val as $k => $v) {
				$process[$key . "[$k]"] = $v;
			}
		} elseif (!in_array($key, $ignore)) {
			echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
		}
	}
}
