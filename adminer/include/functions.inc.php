<?php
/** Get database connection
* @return Min_DB
*/
function connection() {
	// can be used in customization, $connection is minified
	global $connection;
	return $connection;
}

/** Escape database identifier
* @param string
* @return string
*/
function idf_escape($idf) {
	return "`" . str_replace("`", "``", $idf) . "`";
}

/** Unescape database identifier
* @param string text inside ``
* @return string
*/
function idf_unescape($idf) {
	return str_replace("``", "`", $idf);
}

/** Escape or unescape string to use inside form []
* @param string
* @param bool
* @return string
*/
function bracket_escape($idf, $back = false) {
	// escape brackets inside name="x[]"
	static $trans = array(':' => ':1', ']' => ':2', '[' => ':3');
	return strtr($idf, ($back ? array_flip($trans) : $trans));
}

/** Escape for HTML
* @param string
* @return string
*/
function h($string) {
	return htmlspecialchars($string, ENT_QUOTES);
}

/** Convert text whitespace to HTML
* @param string
* @return string
*/
function whitespace($string) {
	return nl2br(preg_replace('~(^| ) ~m', '\\1&nbsp;', str_replace("\t", "    ", $string)));
}

/** Escape for TD
* @param string
* @return string
*/
function nbsp($string) {
	return (strlen(trim($string)) ? h($string) : "&nbsp;");
}

/** Generate HTML checkbox
* @param string
* @param string
* @param bool
* @param string
* @param string
* @return string
*/
function checkbox($name, $value, $checked, $label = "", $onclick = "") {
	static $id = 0;
	$id++;
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'" . ($checked ? " checked" : "") . ($onclick ? " onclick=\"$onclick\"" : "") . " id='checkbox-$id'>";
	return (strlen($label) ? "<label for='checkbox-$id'>$return" . h($label) . "</label>" : $return);
}

/** Generate HTML radio list
* @param string
* @param array
* @param string
* @param bool generate select (otherwise radio)
* @return string
*/
function html_select($name, $options, $value, $select = true) {
	if ($select) {
		return "<select name='" . h($name) . "'>" . optionlist($options, $value) . "</select>";
	}
	$return = "";
	foreach ($options as $key => $val) {
		$return .= "<label><input type='radio' name='" . h($name) . "' value='" . h($key) . "'" . ($key == $value ? " checked" : "") . ">" . h($val) . "</label>";
	}
	return $return;
}

/** Generate list of HTML options
* @param array array of strings or arrays (creates optgroup)
* @param mixed
* @param bool always use array keys for value="", otherwise only string keys are used
* @return string
*/
function optionlist($options, $selected = null, $use_keys = false) {
	$return = "";
	foreach ($options as $k => $v) {
		if (is_array($v)) {
			$return .= '<optgroup label="' . h($k) . '">';
		}
		foreach ((is_array($v) ? $v : array($k => $v)) as $key => $val) {
			$return .= '<option' . ($use_keys || is_string($key) ? ' value="' . h($key) . '"' : '') . (($use_keys || is_string($key) ? (string) $key : $val) === $selected ? ' selected' : '') . '>' . h($val);
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

/** Get list of values from database
* @param string
* @param mixed
* @return array
*/
function get_vals($query, $column = 0) {
	global $connection;
	$return = array();
	$result = $connection->query($query);
	if ($result) {
		while ($row = $result->fetch_row()) {
			$return[] = $row[$column];
		}
	}
	return $return;
}

/** Find unique identifier of a row
* @param array
* @param array result of indexes()
* @return string query string
*/
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
		if (!preg_match('~^(COUNT\\((\\*|(DISTINCT )?`(?:[^`]|``)+`)\\)|(AVG|GROUP_CONCAT|MAX|MIN|SUM)\\(`(?:[^`]|``)+`\\))$~', $key)) { //! columns looking like functions
			$return[] = (isset($val) ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
		}
	}
	return $return;
}

/** Create SQL condition from parsed query string
* @param array parsed query string
* @return string
*/
function where($where) {
	global $connection;
	$return = array();
	foreach ((array) $where["where"] as $key => $val) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " = BINARY " . $connection->quote($val); //! enum and set, columns looking like functions
	}
	foreach ((array) $where["null"] as $key) {
		$key = bracket_escape($key, "back");
		$return[] = (preg_match('~^[A-Z0-9_]+\\(`(?:[^`]|``)+`\\)$~', $key) ? $key : idf_escape($key)) . " IS NULL";
	}
	return implode(" AND ", $return);
}

/** Create SQL condition from query string
* @param string
* @return string
*/
function where_check($val) {
	parse_str($val, $check);
	return where($check);
}

/** Create query string where condition from value
* @param int condition order
* @param string column identifier
* @param string
* @return string
*/
function where_link($i, $column, $value) {
	return "&where%5B$i%5D%5Bcol%5D=" . urlencode($column) . "&where%5B$i%5D%5Bop%5D=%3D&where%5B$i%5D%5Bval%5D=" . urlencode($value);
}

/** Set cookie valid for 1 month
* @param string
* @param string
* @return bool
*/
function cookie($name, $value) {
	return setcookie($name, $value, time() + 2592000, preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"])); // 2592000 = 30 * 24 * 60 * 60
}

/** Send Location header and exit
* @param string
* @param string
* @return null
*/
function redirect($location, $message = null) {
	if (isset($message)) {
		$_SESSION["messages"][] = $message;
	}
	header("Location: " . (strlen($location) ? $location : "."));
	exit;
}

/** Execute query and redirect if successful
* @param string
* @param string
* @param string
* @param bool
* @param bool
* @param bool
* @return bool
*/
function query_redirect($query, $location, $message, $redirect = true, $execute = true, $failed = false) {
	global $connection, $error, $adminer;
	$sql = "";
	if ($query) {
		$sql = $adminer->messageQuery($query);
	}
	if ($execute) {
		$failed = !$connection->query($query);
	}
	if ($failed) {
		$error = h($connection->error) . $sql;
		return false;
	}
	if ($redirect) {
		redirect($location, $message . $sql);
	}
	return true;
}

/** Execute and remember query
* @param string null to return remembered queries
* @return Min_Result
*/
function queries($query = null) {
	global $connection;
	static $queries = array();
	if (!isset($query)) {
		// return executed queries without parameter
		return implode(";\n", $queries);
	}
	$queries[] = $query;
	return $connection->query($query);
}

/** Remove parameter from query string
* @param string
* @return string
*/
function remove_from_uri($param = "") {
	$param = "($param|" . session_name() . ")";
	return preg_replace("~\\?$param=[^&]*&~", '?', preg_replace("~\\?$param=[^&]*\$|&$param=[^&]*~", '', $_SERVER["REQUEST_URI"]));
}

/** Generate page number for pagination
* @param int
* @return string
*/
function pagination($page) {
	return " " . ($page == $_GET["page"] ? $page + 1 : '<a href="' . h(remove_from_uri("page") . ($page ? "&page=$page" : "")) . '">' . ($page + 1) . "</a>");
}

/** Get file contents from $_FILES or $_POST["files"]
* @param string
* @param bool
* @return string
*/
function get_file($key, $decompress = false) {
	// returns int for error, string otherwise
	$file = $_POST["files"][$key];
	if (isset($file)) {
		// get the file from hidden field if the user was logged out
		$length = strlen($file);
		if ($length && $length < 4) {
			return intval($file);
		}
		return base64_decode($file);
	}
	$file = $_FILES[$key];
	if (!$file || $file["error"]) {
		return $file["error"];
	}
	return file_get_contents($decompress && ereg('\\.gz$', $file["name"]) ? "compress.zlib://$file[tmp_name]"
		: ($decompress && ereg('\\.bz2$', $file["name"]) ? "compress.bzip2://$file[tmp_name]"
		: $file["tmp_name"]
	)); //! may not be reachable because of open_basedir
}

/** Determine upload error
* @param int
* @return string
*/
function upload_error($error) {
	$max_size = ($error == UPLOAD_ERR_INI_SIZE ? ini_get("upload_max_filesize") : null); // post_max_size is checked in index.php
	return ($error ? lang('Unable to upload a file.') . ($max_size ? " " . lang('Maximum allowed file size is %sB.', $max_size) : "") : lang('File does not exist.'));
}

/** Generate class for odd rows
* @param string return this for odd rows, empty to reset counter
* @return string
*/
function odd($return = ' class="odd"') {
	static $i = 0;
	if (!$return) { // reset counter
		$i = -1;
	}
	return ($i++ % 2 ? $return : '');
}

/** Check whether the string is in UTF-8
* @param string
* @return bool
*/
function is_utf8($val) {
	// don't print control chars except \t\r\n
	return (preg_match('~~u', $val) && !preg_match('~[\\0-\\x8\\xB\\xC\\xE-\\x1F]~', $val));
}

/** Shorten UTF-8 string
* @param string
* @param int
* @param string
* @return string escaped string with appended ...
*/
function shorten_utf8($string, $length = 80, $suffix = "") {
	if (!preg_match("(^([\t\r\n -\x{FFFF}]{0,$length})($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^([\t\r\n -~]{0,$length})($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<em>...</em>");
}

/** Generate friendly URL
* @param string
* @return string
*/
function friendly_url($val) {
	// used for blobs and export
	return preg_replace('~[^a-z0-9_]~i', '-', $val);
}

/** Print hidden fields
* @param array
* @param array
* @return null
*/
function hidden_fields($process, $ignore = array()) {
	while (list($key, $val) = each($process)) {
		if (is_array($val)) {
			foreach ($val as $k => $v) {
				$process[$key . "[$k]"] = $v;
			}
		} elseif (!in_array($key, $ignore)) {
			echo '<input type="hidden" name="' . h($key) . '" value="' . h($val) . '">';
		}
	}
}

/** Find out foreign keys for each column
* @param string
* @return array array($col => array())
*/
function column_foreign_keys($table) {
	$return = array();
	foreach (foreign_keys($table) as $foreign_key) {
		foreach ($foreign_key["source"] as $val) {
			$return[$val][] = $foreign_key;
		}
	}
	return $return;
}

/** Print edit input field
* @param array one field from fields()
* @param mixed
* @param string
* @return null
*/
function input($field, $value, $function) {
	global $types, $adminer;
	$name = h(bracket_escape($field["field"]));
	echo "<td class='function'>";
	if ($field["type"] == "enum") {
		echo "&nbsp;<td>" . (isset($_GET["select"]) ? " <label><input type='radio' name='fields[$name]' value='-1' checked><em>" . lang('original') . "</em></label>" : "");
		if ($field["null"]) {
			echo " <label><input type='radio' name='fields[$name]' value=''" . (($field["null"] ? isset($value) : strlen($value)) || isset($_GET["select"]) ? '' : ' checked') . '>' . ($field["null"] ? '<em>NULL</em>' : '') . '</label>';
		}
		echo "<input type='radio' name='fields[$name]' value='0'" . ($value === 0 ? ' checked' : '') . '>';
		preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
		foreach ($matches[1] as $i => $val) {
			$val = stripcslashes(str_replace("''", "'", $val));
			$checked = (is_int($value) ? $value == $i+1 : $value === $val);
			echo " <label><input type='radio' name='fields[$name]' value='" . ($i+1) . "'" . ($checked ? ' checked' : '') . '>' . h($val) . '</label>';
		}
	} else {
		$functions = (isset($_GET["select"]) ? array("orig" => lang('original')) : array()) + $adminer->editFunctions($field);
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		$onchange = ($first ? " onchange=\"var f = this.form['function[" . addcslashes($name, "\r\n'\\") . "]']; if ($first > f.selectedIndex) f.selectedIndex = $first;\"" : "");
		echo (count($functions) > 1 ? "<select name='function[$name]'>" . optionlist($functions, !isset($function) || in_array($function, $functions) ? $function : "") . "</select>" : nbsp(reset($functions))) . '<td>';
		$input = $adminer->editInput($_GET["edit"], $field, " name='fields[$name]'$onchange", $value); // usage in call is without a table
		if (strlen($input)) {
			echo $input;
		} elseif ($field["type"] == "set") { //! 64 bits
			preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
			foreach ($matches[1] as $i => $val) {
				$val = stripcslashes(str_replace("''", "'", $val));
				$checked = (is_int($value) ? ($value >> $i) & 1 : in_array($val, explode(",", $value), true));
				echo " <label><input type='checkbox' name='fields[$name][$i]' value='" . (1 << $i) . "'" . ($checked ? ' checked' : '') . "$onchange>" . h($val) . '</label>';
			}
		} elseif (strpos($field["type"], "text") !== false) {
			echo "<textarea name='fields[$name]' cols='50' rows='12'$onchange>" . h($value) . '</textarea>';
		} elseif (ereg('binary|blob', $field["type"])) {
			echo (ini_get("file_uploads") ? "<input type='file' name='$name'$onchange>" : lang('File uploads are disabled.'));
		} else {
			// int(3) is only a display hint
			$maxlength = (!ereg('int', $field["type"]) && preg_match('~^([0-9]+)(,([0-9]+))?$~', $field["length"], $match) ? ($match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0)) : ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0));
			echo "<input name='fields[$name]' value='" . h($value) . "'" . ($maxlength ? " maxlength='$maxlength'" : "") . "$onchange>";
		}
	}
}

/** Process edit input field
* @param one field from fields()
* @return string
*/
function process_input($field) {
	global $connection, $adminer;
	$idf = bracket_escape($field["field"]);
	$function = $_POST["function"][$idf];
	$value = $_POST["fields"][$idf];
	if ($field["type"] == "enum" ? $value == -1 : $function == "orig") {
		return false;
	} elseif ($field["type"] == "enum" || $field["auto_increment"] ? !strlen($value) : $function == "NULL") {
		return "NULL";
	} elseif ($field["type"] == "enum") {
		return intval($value);
	} elseif ($field["type"] == "set") {
		return array_sum((array) $value);
	} elseif (ereg('binary|blob', $field["type"])) {
		$file = get_file($idf);
		if (!is_string($file)) {
			return false; //! report errors
		}
		return "_binary" . $connection->quote($file);
	} else {
		return $adminer->processInput($field, $value, $function);
	}
}

/** Print CSV row
* @param array
* @return null
*/
function dump_csv($row) {
	foreach ($row as $key => $val) {
		if (preg_match("~[\"\n,]~", $val) || (isset($val) && !strlen($val))) {
			$row[$key] = '"' . str_replace('"', '""', $val) . '"';
		}
	}
	echo implode(",", $row) . "\n";
}

/** Apply SQL function
* @param string
* @param string escaped column identifier
* @return string
*/
function apply_sql_function($function, $column) {
	return ($function ? ($function == "count distinct" ? "COUNT(DISTINCT " : strtoupper("$function(")) . "$column)" : $column);
}

/** Check whether the string is e-mail address
* @param string
* @return bool
*/
function is_email($email) {
	$atom = '[-a-z0-9!#$%&\'*+/=?^_`{|}~]'; // characters of local-name
	$domain = '[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])'; // one domain component
	return eregi("^$atom+(\\.$atom+)*@($domain?\\.)+$domain\$", $email);
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param string
* @param string
* @param bool
* @return null
*/
function print_fieldset($id, $legend, $visible = false) {
	echo "<fieldset><legend><a href='#fieldset-$id' onclick=\"return !toggle('fieldset-$id');\">$legend</a></legend><div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}
