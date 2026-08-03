<?php
namespace Adminer;

/** Return <script> element */
function script(string $source, string $trailing = "\n"): string {
	return "<script" . nonce() . ">$source</script>$trailing";
}

/** Return <script src> element */
function script_src(string $url, bool $defer = false): string {
	return "<script src='" . h($url) . "'" . nonce() . ($defer ? " defer" : "") . "></script>\n";
}

/** Get a nonce="" attribute with CSP nonce */
function nonce(): string {
	return ' nonce="' . get_nonce() . '"';
}

/** Get an attribute registering a JS event handler
* @param string $event event name without "on"
* @param string $handler name of a JavaScript function defined by Adminer or by a plugin
* @param mixed $arg argument passed to the handler before the event object
* @return string HTML attribute including the leading space
*/
function on(string $event, string $handler, $arg = null): string {
	$args = array();
	foreach (array_slice(func_get_args(), 2) as $val) { // not ...$args - variadics are available since PHP 5.6
		$args[] = json_encode($val, 256); // 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
	}
	return " data-on$event='" . str_replace( // h() would escape " to &quot; but the value is printed in a single-quoted attribute so only ' matters
		array('&', '<', "'"),
		array('&amp;', '&lt;', '&#039;'),
		"$handler(" . implode(", ", $args) . ")"
	) . "'";
}

/** Get <input type="hidden">
* @param string|int $value
* @return string HTML
*/
function input_hidden(string $name, $value = ""): string {
	return "<input type='hidden' name='" . h($name) . "' value='" . h($value) . "'>\n";
}

/** Get CSRF <input type="hidden" name="token">
* @return string HTML
*/
function input_token(): string {
	return input_hidden("token", get_token());
}

/** Get a target="_blank" attribute */
function target_blank(): string {
	return ' target="_blank" rel="noreferrer noopener"';
}

/** Escape for HTML */
function h(?string $string): string {
	return str_replace( // this is 50× faster than htmlspecialchars()
		array('&', '<', '"', "'", "\0"),
		array('&amp;', '&lt;', '&quot;', '&#039;', '&#0;'),
		$string
	);
}

/** Convert \n to <br> */
function nl_br(string $string): string {
	return str_replace("\n", "<br>", $string); // nl2br() uses XHTML before PHP 5.3
}

/** Generate HTML checkbox
* @param string|int $value
* @param string $attrs additional attributes, e.g. an event handler from on()
* @param string $class class of the label, of the input if there's no label
*/
function checkbox(string $name, $value, ?bool $checked, string $label = "", string $attrs = "", string $class = "", string $labelled_by = ""): string {
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'"
		. ($checked ? " checked" : "")
		. ($label == "" && $class ? " class='$class'" : "")
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. $attrs
		. ">"
	;
	return ($label != "" ? "<label" . ($class ? " class='$class'" : "") . ">$return" . h($label) . "</label>" : $return);
}

/** Generate list of HTML options
* @param string[]|string[][] $options array of strings or arrays (creates optgroup)
* @param mixed $selected
* @param bool $use_keys always use array keys for value="", otherwise only string keys are used
*/
function optionlist($options, $selected = null, bool $use_keys = false): string {
	$return = "";
	foreach ($options as $k => $v) {
		$opts = array($k => $v);
		if (is_array($v)) {
			$return .= '<optgroup label="' . h($k) . '">';
			$opts = $v;
		}
		foreach ($opts as $key => $val) {
			$return .= '<option'
				. ($use_keys || is_string($key) ? ' value="' . h($key) . '"' : '')
				. ($selected !== null && ($use_keys || is_string($key) ? (string) $key : $val) === $selected ? ' selected' : '')
				. '>' . h($val)
			;
		}
		if (is_array($v)) {
			$return .= '</optgroup>';
		}
	}
	return $return;
}

/** Generate HTML <select>
* @param string[] $options
*/
function html_select(string $name, array $options, ?string $value = "", string $attrs = "", string $labelled_by = ""): string {
	static $label = 0;
	$label_option = "";
	if (!$labelled_by && substr($options[""], 0, 1) == "(") {
		$label++;
		$labelled_by = "label-$label";
		$label_option = "<option value='' id='$labelled_by'>" . h($options[""]);
		unset($options[""]);
	}
	return "<select name='" . h($name) . "'"
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. "$attrs>" . $label_option . optionlist($options, $value) . "</select>"
	;
}

/** Generate HTML radio list
* @param string[] $options
*/
function html_radios(string $name, array $options, ?string $value = "", string $separator = ""): string {
	$return = "";
	foreach ($options as $key => $val) {
		$return .= "<label><input type='radio' name='" . h($name) . "' value='" . h($key) . "'" . ($key == $value ? " checked" : "") . ">" . h($val) . "</label>$separator";
	}
	return $return;
}

/** Get an attribute asking for confirmation before submit */
function confirm(string $message = ""): string {
	return on('click', 'confirmClick', $message ?: lang('Are you sure?'));
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param bool $visible
*/
function print_fieldset(string $id, string $legend, $visible = false): void {
	echo "<fieldset><legend>";
	echo "<a href='#fieldset-$id' class='toggle'>$legend</a>";
	echo "</legend>";
	echo "<div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}

/** Return class='active' if $bold is true */
function bold(bool $bold, string $class = ""): string {
	return ($bold ? " class='active $class'" : ($class ? " class='$class'" : ""));
}

/** Escape string for JavaScript apostrophes */
function js_escape(string $string): string {
	// the HTML parser doesn't understand JS escaping so < must not stay in the string at all,
	// otherwise <!-- would start the script data escaped state and the following </script> wouldn't end the element
	return str_replace("<", "\\x3C", addcslashes($string, "\r\n'\\"));
}

/** Escape string to use inside a JavaScript regular expression literal
*/
function js_escape_re(string $string): string {
	return addcslashes(preg_quote($string, "/"), "\r\n"); // preg_quote() escapes also < ! - so the HTML parser doesn't see <!-- or </script>
}

/** Generate page number for pagination */
function pagination(int $page, ?int $current): string {
	return " " . ($page == $current
		? ($page ? "<b>" . ($page + 1) . "</b>" : $page + 1) // the first page is not highlighted
		: '<a href="' . h(remove_from_uri("page|next") . ($page ? "&page=$page" . ($_GET["next"] ? "&next=" . urlencode($_GET["next"]) : "") : "")) . '">' . ($page + 1) . "</a>"
	);
}

/** Print hidden fields
* @param mixed[] $process
* @param list<string> $ignore
*/
function hidden_fields(array $process, array $ignore = array(), string $prefix = ''): bool {
	$return = false;
	foreach ($process as $key => $val) {
		if (!in_array($key, $ignore)) {
			if (is_array($val)) {
				hidden_fields($val, array(), $key);
			} else {
				$return = true;
				echo input_hidden(($prefix ? $prefix . "[$key]" : $key), $val);
			}
		}
	}
	return $return;
}

/** Print hidden fields for GET forms */
function hidden_fields_get(): void {
	echo (sid() ? input_hidden(session_name(), session_id()) : '');
	echo ($_GET["ext"] ? input_hidden("ext", $_GET["ext"]) : "");
	echo (SERVER !== null ? input_hidden(DRIVER, SERVER) : "");
	echo input_hidden("username", $_GET["username"]);
}

/** Get <input type='file'>
* @param string $attrs attributes including the leading space
* @param string $rest HTML printed after the input, dropped together with it if the uploads are disabled
*/
function file_input(string $attrs, string $rest = ""): string {
	$max_file_uploads = "max_file_uploads";
	$max_file_uploads_value = ini_get($max_file_uploads);
	$upload_max_filesize = "upload_max_filesize";
	$upload_max_filesize_value = ini_get($upload_max_filesize);
	return (ini_bool("file_uploads")
		? "<input type='file'$attrs" . on(
			'change',
			'fileChange',
			// ignore post_max_size because it is for all form fields together and bytes computing would be necessary
			(int) $max_file_uploads_value,
			lang('Increase %s.', "$max_file_uploads = $max_file_uploads_value"),
			ini_bytes($upload_max_filesize),
			lang('Increase %s.', "$upload_max_filesize = $upload_max_filesize_value")
		) . ">$rest"
		: lang('File uploads are disabled.')
	);
}

/** Print enum or set input field
* @param 'radio'|'checkbox' $type
* @param Field $field
* @param string|string[]|false|null $value false means original value
*/
function enum_input(string $type, string $attrs, array $field, $value, string $empty = ""): string {
	preg_match_all("~'((?:[^']|'')*)'~", $field["length"], $matches);
	$prefix = ($field["type"] == "enum" ? "val-" : "");
	$checked = (is_array($value) ? in_array("null", $value) : $value === null);
	$return = ($field["null"] && $prefix ? "<label><input type='$type'$attrs value='null'" . ($checked ? " checked" : "") . "><i>$empty</i></label>" : "");
	foreach ($matches[1] as $val) {
		$val = stripcslashes(str_replace("''", "'", $val));
		$checked = (is_array($value) ? in_array($prefix . $val, $value) : $value === $val);
		$return .= " <label><input type='$type'$attrs value='" . h($prefix . $val) . "'" . ($checked ? ' checked' : '') . '>' . h(adminer()->editVal($val, $field)) . '</label>';
	}
	return $return;
}

/** Print edit input field
* @param Field|RoutineField $field
* @param mixed $value
*/
function input(array $field, $value, ?string $function, ?bool $autofocus = false): void {
	$name = h(bracket_escape($field["field"]));
	echo "<td class='function'>";
	if (is_array($value) && !$function) {
		$function = "json";
	}
	$json = ($function == "json" || preg_match('~^jsonb?$~', $field["full_type"]));
	if ($json && $value != '' && (JUSH != "pgsql" || $field["type"] != "json")) {
		// 128 - JSON_PRETTY_PRINT, 64 - JSON_UNESCAPED_SLASHES, 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
		$value = json_encode(is_array($value) ? $value : json_decode($value), 128 | 64 | 256);
	}
	$reset = (JUSH == "mssql" && $field["auto_increment"]);
	if ($reset && !$_POST["save"]) {
		$function = null;
	}
	$functions = (isset($_GET["select"]) || $reset ? array("orig" => lang('original')) : array()) + adminer()->editFunctions($field);
	$enums = driver()->enumLength($field);
	if ($enums) {
		$field["type"] = "enum";
		$field["length"] = $enums;
	}
	$attrs = " name='fields[$name]" . ($field["type"] == "enum" || $field["type"] == "set" ? "[]" : "") . "'" . ($autofocus ? " autofocus" : "");
	echo driver()->unconvertFunction($field) . " ";
	$table = $_GET["edit"] ?: $_GET["select"];
	if ($field["type"] == "enum") {
		echo h($functions[""]) . "<td>" . adminer()->editInput($table, $field, $attrs, $value);
	} else {
		$has_function = (in_array($function, $functions) || isset($functions[$function]));
		// skip 'original'
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		echo (count($functions) > 1
			? "<select name='function[$name]'" . on('change', 'functionChange') . on_help_value('^SQL$') . ">"
				. optionlist($functions, $function === null || $has_function ? $function : "") . "</select>"
			: h(reset($functions))
		) . "<td" . ($first && count($functions) > 1 ? on('input', 'skipOriginal', $first) : "") . ">";
		$input = adminer()->editInput($table, $field, $attrs, $value); // usage in call is without a table
		if ($input != "") {
			echo $input;
		} elseif (preg_match('~bool~', $field["type"])) {
			echo "<input type='hidden'$attrs value='0'>"
				. "<input type='checkbox'" . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? " checked" : "") . "$attrs value='1'>";
		} elseif ($field["type"] == "set") {
			echo enum_input("checkbox", $attrs, $field, (is_string($value) ? explode(",", $value) : $value));
		} elseif (is_blob($field) && ini_bool("file_uploads")) {
			echo "<input type='file' name='fields-$name'>";
		} elseif ($json) {
			echo "<textarea$attrs cols='50' rows='12' class='jush-json'>" . h($value) . '</textarea>';
		} elseif (($text = preg_match('~text|lob|memo~i', $field["type"])) || preg_match("~\n~", $value)) {
			if ($text && JUSH != "sqlite") {
				$attrs .= " cols='50' rows='12'";
			} else {
				$rows = min(12, substr_count($value, "\n") + 1);
				$attrs .= " cols='30' rows='$rows'";
			}
			echo "<textarea$attrs>" . h($value) . '</textarea>';
		} else {
			// int(3) is only a display hint
			$types = driver()->types();
			$maxlength = (!preg_match('~int~', $field["type"]) && preg_match('~^(\d+)(,(\d+))?$~', $field["length"], $match)
				? ((preg_match("~binary~", $field["type"]) ? 2 : 1) * $match[1] + ($match[3] ? 1 : 0) + ($match[2] && !$field["unsigned"] ? 1 : 0))
				: ($types[$field["type"]] ? $types[$field["type"]] + ($field["unsigned"] ? 0 : 1) : 0)
			);
			if (JUSH == 'sql' && min_version(5.6) && preg_match('~time~', $field["type"])) {
				$maxlength += 7; // microtime
			}
			// type='date' and type='time' display localized value which may be confusing, type='datetime' uses 'T' as date and time separator
			echo "<input"
				. ((!$has_function || $function === "") && preg_match('~(?<!o)int(?!er)~', $field["type"]) && !preg_match('~\[]~', $field["full_type"]) ? " type='number'" : "")
				. " value='" . h($value) . "'" . ($maxlength ? " data-maxlength='$maxlength'" : "")
				. (preg_match('~char|binary~', $field["type"]) && $maxlength > 20 ? " size='" . ($maxlength > 99 ? 60 : 40) . "'" : "")
				. "$attrs>"
			;
		}
		echo adminer()->editHint($table, $field, $value);
		echo (count($functions) > 1 ? script("fire(qs('select', qsl('td').previousSibling), 'change');", "") : ""); // apply the initially selected function (e.g. hide the input for now())
	}
}

/** Process edit input field
* @param Field|RoutineField $field
* @return mixed false to leave the original value
*/
function process_input(array $field) {
	$idf = bracket_escape($field["field"]);
	$function = idx($_POST["function"], $idf);
	if ($function == "orig") {
		return (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? idf_escape($field["field"]) : false);
	}
	if ($function == "NULL") {
		return "NULL";
	}
	if (is_blob($field) && ini_bool("file_uploads")) {
		$file = get_file("fields-$idf");
		if (!is_string($file)) {
			return false; //! report errors
		}
		return driver()->quoteBinary($file);
	}
	$value = idx($_POST["fields"], $idf);
	if ($value === null) {
		return false;
	}
	if ($field["type"] == "enum" || driver()->enumLength($field)) {
		$value = idx($value, 0);
		if ($value == "orig" || !$value) {
			return false;
		}
		if ($value == "null") {
			return "NULL";
		}
		$value = substr($value, 4); // 4 - strlen("val-")
	}
	if ($field["auto_increment"] && $value == "") {
		return null;
	}
	if ($field["type"] == "set") {
		$value = implode(",", (array) $value);
	}
	if ($function == "json") {
		$value = json_decode($value, true);
		if (!is_array($value)) {
			return false; //! report errors
		}
		return $value;
	}
	return adminer()->processInput($field, $value, $function);
}

/** Print results of search in all tables
* @uses $_GET["where"][0]
* @uses $_POST["tables"]
*/
function search_tables(): void {
	$_GET["where"][0]["val"] = $_POST["query"];
	$sep = "<ul>\n";
	foreach (table_status('', true) as $table => $table_status) {
		$name = adminer()->tableName($table_status);
		if (isset($table_status["Engine"]) && $name != "" && (!$_POST["tables"] || in_array($table, $_POST["tables"]))) {
			$result = connection()->query("SELECT" . limit("1 FROM " . table($table), " WHERE " . implode(" AND ", adminer()->selectSearchProcess(fields($table), array())), 1));
			if (!$result || $result->fetch_row()) {
				$print = "<a href='" . h(ME . "select=" . urlencode($table)
					. "&where[0][op]=" . urlencode($_GET["where"][0]["op"])
					. "&where[0][val]=" . urlencode($_GET["where"][0]["val"])) . "'>$name</a>";
				echo "$sep<li>" . ($result ? $print : "<p class='error'>$print: " . error()) . "\n";
				$sep = "";
			}
		}
	}
	echo ($sep ? "<p class='message'>" . lang('No tables.') : "</ul>") . "\n";
}

/** Get attributes to display help on mouse over
* @param string $text SQL command
* @param int $side 0 top, 1 left
* @return string HTML attributes including the leading space
*/
function on_help(string $text, int $side = 0): string {
	return on('mouseover', 'helpMouseover', $text, $side) . on('mouseout', 'helpMouseout');
}

/** Get attributes to display help with the hovered value on mouse over
* @param string $regexp regular expression transforming the value, empty to display the value itself
* @param string $replacement can use $& and $1
* @return string HTML attributes including the leading space
*/
function on_help_value(string $regexp = "", string $replacement = ""): string {
	return on('mouseover', 'helpValueMouseover', $regexp, $replacement) . on('mouseout', 'helpMouseout');
}

/** Print edit data form
* @param Field[] $fields
* @param mixed $row
*/
function edit_form(string $table, array $fields, $row, ?bool $update, string $error = ''): void {
	$table_name = adminer()->tableName(table_status1($table, true));
	page_header(
		($update ? lang('Edit') : lang('Insert')),
		$error,
		array("select" => array($table, $table_name)),
		$table_name
	);
	adminer()->editRowPrint($table, $fields, $row, $update);
	if ($row === false) {
		echo "<p class='error'>" . lang('No rows.') . "\n";
		return;
	}
	echo "<form action='' method='post' enctype='multipart/form-data' id='form'>\n";
	$editable = false;
	// the WHERE condition in the URL is not updated after saving so changing these values would break Save and continue edit
	$where_columns = ($update && !isset($_GET["select"]) ? where_columns($fields) : array());
	$continue_edit = (count($where_columns) != count($fields)); // without a unique key the condition uses all columns so the button would be always disabled
	if (!$continue_edit) {
		$where_columns = array();
	}
	if (!$fields) {
		echo "<p class='error'>" . lang('You have no privileges to update this table.') . "\n";
	} else {
		echo "<table class='layout nowrap'" . on('keydown', 'editingKeydown') . ">\n";
		$autofocus = !$_POST;
		foreach ($fields as $name => $field) {
			echo "<tr" . ($where_columns[$name] ? on('change', 'whereChange') : "") . "><th>" . adminer()->fieldName($field);
			$default = idx($_GET["set"], bracket_escape($name));
			if ($default === null) {
				$default = $field["default"];
				if ($field["type"] == "bit" && preg_match("~^b'([01]*)'\$~", $default, $regs)) {
					$default = $regs[1];
				}
				if (JUSH == "sql" && preg_match('~binary~', $field["type"])) {
					$default = bin2hex($default); // same as UNHEX
				}
			}
			$value = ($row !== null
				? ($row[$name] != "" && JUSH == "sql" && preg_match("~enum|set~", $field["type"]) && is_array($row[$name])
					? implode(",", $row[$name])
					: (is_bool($row[$name]) ? +$row[$name] : $row[$name])
				)
				: (!$update && $field["auto_increment"]
					? ""
					: (isset($_GET["select"]) ? false : $default)
				)
			);
			if (!$_POST["save"] && is_string($value)) {
				$value = adminer()->editVal($value, $field);
			}
			if (($update && !isset($field["privileges"]["update"])) || $field["generated"]) {
				echo "<td class='function'><td>" . select_value($value, '', $field, null);
			} else {
				$editable = true;
				$function = ($_POST["save"]
					? idx($_POST["function"], bracket_escape($name), "")
					: ($update && preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"])
						? "now"
						: ($value === false ? null : ($value !== null ? '' : 'NULL'))
					)
				);
				if (!$_POST && !$update && $value == $field["default"] && preg_match('~^[\w.]+\(~', $value)) {
					$function = "SQL";
				}
				if (preg_match("~time~", $field["type"]) && preg_match('~^CURRENT_TIMESTAMP~i', $value)) {
					$value = "";
					$function = "now";
				}
				if ($field["type"] == "uuid" && $value == "uuid()") {
					$value = "";
					$function = "uuid";
				}
				if ($autofocus !== false) {
					$autofocus = ($field["auto_increment"] || $function == "now" || $function == "uuid" ? null : true); // null - don't autofocus this input but check the next one
				}
				input($field, $value, $function, $autofocus);
				if ($autofocus) {
					$autofocus = false;
				}
			}
		}
		if (!support("table") && !fields($table)) {
			echo "<tr>"
				. "<th><input name='field_keys[]'" . on('input', 'fieldChange') . ">"
				. "<td class='function'>" . html_select("field_funs[]", adminer()->editFunctions(array("null" => isset($_GET["select"]))))
				. "<td><input name='field_vals[]'>"
			;
		}
		echo "</table>\n";
	}
	echo "<p>\n";
	if ($editable) {
		echo "<input type='submit' value='" . lang('Save') . "'>\n";
		if (!isset($_GET["select"]) && $continue_edit) {
			// the printed values were not saved so they can differ from the WHERE condition in the URL and no change event fires for them
			$disabled = ($where_columns && ($error != "" || adminer()->error != "") ? " disabled" : "");
			echo "<input type='submit' name='insert' value='" . ($update
				? lang('Save and continue edit')
				: lang('Save and insert next')
			) . "' title='Ctrl+Shift+Enter'$disabled" . ($update ? on('click', 'ajaxForm', lang('Saving…')) : "") . ">\n";
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . lang('Delete') . "'" . confirm() . ">\n" : "");
	if (isset($_GET["select"])) {
		hidden_fields(array("check" => (array) $_POST["check"], "clone" => $_POST["clone"], "all" => $_POST["all"]));
	}
	echo input_hidden("referer", (isset($_POST["referer"]) ? $_POST["referer"] : $_SERVER["HTTP_REFERER"]));
	echo input_hidden("save", 1);
	echo input_token();
	echo "</form>\n";
}

/** Shorten UTF-8 string
* @return string escaped string with appended ...
*/
function shorten_utf8(string $string, int $length = 80, string $suffix = ""): string {
	if (!preg_match("(^(" . repeat_pattern("[\t\r\n -\x{10FFFF}]", $length) . ")($)?)u", $string, $match)) { // ~s causes trash in $match[2] under some PHP versions, (.|\n) is slow
		preg_match("(^(" . repeat_pattern("[\t\r\n -~]", $length) . ")($)?)", $string, $match);
	}
	return h($match[1]) . $suffix . (isset($match[2]) ? "" : "<i>…</i>");
}

/** Get button with icon */
function icon(string $icon, string $name, string $html, string $title, string $attrs = ""): string {
	// tabindex - a drag handle can't be used by keyboard so it would be just a dead stop in every row
	return "<button " . ($name ? "type='submit' name='$name'" : "draggable='true' tabindex='-1'")
		. " title='" . h($title) . "' class='icon icon-$icon" . ($name ? "" : " jsonly") . "'$attrs><span>$html</span></button>"
	;
}

/** Get link copying the adjacent <code> to clipboard */
function copy_icon(): string {
	$copy = lang('Copy');
	return "<a href='' class='jsonly icon-copy' title='$copy'><span>$copy</span></a>"; // not icon() - a submit button would send the surrounding form
}
