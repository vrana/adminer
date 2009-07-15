<?php
function idf_escape($idf) {
	return "`" . str_replace("`", "``", $idf) . "`";
}

function idf_unescape($idf) {
	return str_replace("``", "`", $idf);
}

function bracket_escape($idf, $back = false) {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

function optionlist($options, $selected = null) {
	$return = "";
	foreach ($options as $k => $v) {
		if (is_array($v)) {
			$return .= '<optgroup label="' . htmlspecialchars($k) . '">';
		}
		foreach ((is_array($v) ? $v : array($k => $v)) as $key => $val) {
			$return .= '<option' . (is_string($key) ? ' value="' . htmlspecialchars($key) . '"' : '') . ((is_string($key) ? $key : $val) === $selected ? ' selected="selected"' : '') . '>' . htmlspecialchars($val);
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
				if (!isset($row[$key])) { // NULL is ambiguous
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
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " = BINARY " . $dbh->quote($val); //! enum and set, columns looking like functions
	}
	foreach ((array) $where["null"] as $key) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]+|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " IS NULL";
	}
	return implode(" AND ", $return);
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
		// append SID if session cookies are disabled
		$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
}

function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false) {
	global $dbh, $error, $SELF;
	$sql = "";
	if ($query) {
		$sql = adminer_message_query($query);
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
		// return executed queries without parameter
		return implode(";\n", $queries);
	}
	$queries[] = $query;
	return $dbh->query($query);
}

function remove_from_uri($param = "") {
	$param = "($param|" . session_name() . ")";
	return preg_replace("~\\?$param=[^&]*&~", '?', preg_replace("~\\?$param=[^&]*\$|&$param=[^&]*~", '', $_SERVER["REQUEST_URI"]));
}

function pagination($page) {
	return " " . ($page == $_GET["page"] ? $page + 1 : '<a href="' . htmlspecialchars(remove_from_uri("page") . ($page ? "&page=$page" : "")) . '">' . ($page + 1) . "</a>");
}

function get_file($key) {
	// returns int for error, string otherwise
	if (isset($_POST["files"][$key])) {
		// get the file from hidden field if the user was logged out
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
		echo "<p class='message'>" . lang('No rows.') . "\n";
	} else {
		echo "<table cellspacing='0' class='nowrap'>\n";
		$links = array(); // colno => orgtable - create links from these columns
		$indexes = array(); // orgtable => array(column => colno) - primary keys
		$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
		$blobs = array(); // colno => bool - display bytes for blobs
		$types = array(); // colno => type - display char in <code>
		odd(''); // reset odd for each result
		for ($i=0; $row = $result->fetch_row(); $i++) {
			if (!$i) {
				echo "<thead><tr>";
				for ($j=0; $j < count($row); $j++) {
					$field = $result->fetch_field();
					if (strlen($field->orgtable)) {
						if (!isset($indexes[$field->orgtable])) {
							// find primary key in each table
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
					echo "<th>" . htmlspecialchars($field->name);
				}
				echo "</thead>\n";
			}
			echo "<tr" . odd() . ">";
			foreach ($row as $key => $val) {
				if (!isset($val)) {
					$val = "<i>NULL</i>";
				} else {
					if ($blobs[$key] && !is_utf8($val)) {
						$val = "<i>" . lang('%d byte(s)', strlen($val)) . "</i>"; //! link to download
					} elseif (!strlen(trim($val, " \t"))) {
						$val = "&nbsp;"; // some content to print a border
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
				echo "<td>$val";
			}
		}
		echo "</table>\n";
	}
	$result->free();
}

function is_utf8($val) {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\\0-\\x8\\xB\\xC\\xE-\\x1F]~', $val));
}

function shorten_utf8($string, $length = 80, $suffix = "") {
	preg_match("~^(.{0,$length})(.?)~su", $string, $match);
	return htmlspecialchars($match[1]) . $suffix . ($match[2] ? "<em>...</em>" : "");
}

function friendly_url($val) {
	// used for blobs and export
	return preg_replace('~[^a-z0-9_]~i', '-', $val);
}

function hidden_fields($process, $ignore = array()) {
	while (list($key, $val) = each($process)) {
		if (is_array($val)) {
			foreach ($val as $k => $v) {
				$process[$key . "[$k]"] = $v;
			}
		} elseif (!in_array($key, $ignore)) {
			echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
		}
	}
}

function input($name, $field, $value, $function) {
	global $types;
	$name = htmlspecialchars(bracket_escape($name));
	echo "<td class='function'>";
	if ($field["type"] == "enum") {
		echo "&nbsp;<td>" . (isset($_GET["select"]) ? ' <label><input type="radio" name="fields[' . $name . ']" value="-1" checked="checked"><em>' . lang('original') . '</em></label>' : "");
		if ($field["null"] || isset($_GET["default"])) {
			echo ' <label><input type="radio" name="fields[' . $name . ']" value=""' . (($field["null"] ? isset($value) : strlen($value)) || isset($_GET["select"]) ? '' : ' checked="checked"') . '>' . ($field["null"] ? '<em>NULL</em>' : '') . '</label>';
		}
		if (!isset($_GET["default"])) {
			echo '<input type="radio" name="fields[' . $name . ']" value="0"' . ($value === 0 ? ' checked="checked"' : '') . '>';
		}
		preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = stripcslashes(str_replace("''", "'", $val));
			$checked = (is_int($value) ? $value == $i+1 : $value === $val);
			echo ' <label><input type="radio" name="fields[' . $name . ']" value="' . (isset($_GET["default"]) ? (strlen($val) ? htmlspecialchars($val) : " ") : $i+1) . '"' . ($checked ? ' checked="checked"' : '') . '>' . htmlspecialchars($val) . '</label>';
		}
	} else {
		$first = ($field["null"] || isset($_GET["default"])) + isset($_GET["select"]);
		$onchange = ($first ? ' onchange="var f = this.form[\'function[' . addcslashes($name, "\r\n'\\") . ']\']; if (' . $first . ' > f.selectedIndex) f.selectedIndex = ' . $first . ';"' : '');
		$options = array("");
		if (!isset($_GET["default"])) {
			if (ereg('char|date|time', $field["type"])) {
				$options = (ereg('char', $field["type"]) ? array("", "md5", "sha1", "password", "uuid") : array("", "now")); //! JavaScript for disabling maxlength
			}
			if (!isset($_GET["call"]) && (isset($_GET["select"]) || where($_GET))) {
				// relative functions
				if (ereg('int|float|double|decimal', $field["type"])) {
					$options = array("", "+", "-");
				}
				if (ereg('date', $field["type"])) {
					$options[] = "+ interval";
					$options[] = "- interval";
				}
				if (ereg('time', $field["type"])) {
					$options[] = "addtime";
					$options[] = "subtime";
				}
			}
		}
		if ($field["null"] || isset($_GET["default"])) {
			array_unshift($options, "NULL");
		}
		echo (count($options) > 1 || isset($_GET["select"]) ? '<select name="function[' . $name . ']">' . (isset($_GET["select"]) ? '<option value="orig">' . lang('original') : '') . optionlist($options, $function) . '</select>' : "&nbsp;") . '<td>';
		if ($field["type"] == "set") { //! 64 bits
			preg_match_all("~'((?:[^']+|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $i => $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$checked = (is_int($value) ? ($value >> $i) & 1 : in_array($val, explode(",", $value), true));
				echo ' <label><input type="checkbox" name="fields[' . $name . '][' . $i . ']" value="' . (isset($_GET["default"]) ? htmlspecialchars($val) : 1 << $i) . '"' . ($checked ? ' checked="checked"' : '') . $onchange . '>' . htmlspecialchars($val) . '</label>';
			}
		} elseif (strpos($field["type"], "text") !== false) {
			echo '<textarea name="fields[' . $name . ']" cols="50" rows="12"' . $onchange . '>' . htmlspecialchars($value) . '</textarea>';
		} elseif (ereg('binary|blob', $field["type"])) {
			echo (ini_get("file_uploads") ? '<input type="file" name="' . $name . '"' . $onchange . '>' : lang('File uploads are disabled.') . ' ');
		} else {
			// int(3) is only a display hint
			$maxlength = (!ereg('int', $field["type"]) && preg_match('~^([0-9]+)(,([0-9]+))?$~', $field["length"], $match) ? ($match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0)) : ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0));
			echo '<input name="fields[' . $name . ']" value="' . htmlspecialchars($value) . '"' . ($maxlength ? " maxlength='$maxlength'" : "") . $onchange . '>';
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
		return (isset($_GET["default"]) ? $dbh->quote($value) : intval($value));
	} elseif ($field["type"] == "set") {
		return (isset($_GET["default"]) ? "'" . implode(",", array_map('escape_string', (array) $value)) . "'" : array_sum((array) $value));
	} elseif (ereg('binary|blob', $field["type"])) {
		$file = get_file($idf);
		if (!is_string($file)) {
			return false; //! report errors
		}
		return "_binary" . $dbh->quote($file);
	} elseif ($field["type"] == "timestamp" && $value == "CURRENT_TIMESTAMP") {
		return $value;
	} elseif (ereg('^(now|uuid)$', $function)) {
		return "$function()";
	} elseif (ereg('^[+-]$', $function)) {
		return idf_escape($name) . " $function " . $dbh->quote($value);
	} elseif (ereg('^[+-] interval$', $function)) {
		return idf_escape($name) . " $function " . (preg_match("~^([0-9]+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : $dbh->quote($value));
	} elseif (ereg('^(addtime|subtime)$', $function)) {
		return "$function(" . idf_escape($name) . ", " . $dbh->quote($value) . ")";
	} elseif (ereg('^(md5|sha1|password)$', $function)) {
		return "$function(" . $dbh->quote($value) . ")";
	} else {
		return $dbh->quote($value);
	}
}

function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match("~[\"\n,]~", $val) || (isset($val) && !strlen($val))) {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(",", $row) . "\n";
}

function is_email($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	return eregi("^$atom+(\\.$atom+)*@($domain?\\.)+$domain\$", $email);
}

function email_header($header) {
	// iconv_mime_encode requires PHP 5, imap_8bit requires IMAP extension
	return "=?UTF-8?B?" . base64_encode($header) . "?="; //! split long lines
}

function call_adminer($method, $default, $arg1 = null, $arg2 = null) {
	// maintains original method name in minification
	if (method_exists('Adminer', $method)) { // user defined class
		// can use func_get_args() and call_user_func_array()
		return Adminer::$method($arg1, $arg2);
	}
	return $default; //! $default is evaluated even if not neccessary
}
