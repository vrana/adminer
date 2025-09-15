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
	return str_replace("\0", "&#0;", htmlspecialchars($string, ENT_QUOTES, 'utf-8'));
}

/** Convert \n to <br> */
function nl_br(string $string): string {
	return str_replace("\n", "<br>", $string); // nl2br() uses XHTML before PHP 5.3
}

/** Generate HTML checkbox
* @param string|int $value
*/
function checkbox(string $name, $value, ?bool $checked, string $label = "", string $onclick = "", string $class = "", string $labelled_by = ""): string {
	$return = "<input type='checkbox' name='$name' value='" . h($value) . "'"
		. ($checked ? " checked" : "")
		. ($labelled_by ? " aria-labelledby='$labelled_by'" : "")
		. ">"
		. ($onclick ? script("qsl('input').onclick = function () { $onclick };", "") : "")
	;
	return ($label != "" || $class ? "<label" . ($class ? " class='$class'" : "") . ">$return" . h($label) . "</label>" : $return);
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
function html_select(string $name, array $options, ?string $value = "", string $onchange = "", string $labelled_by = ""): string {
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
		. ">" . $label_option . optionlist($options, $value) . "</select>"
		. ($onchange ? script("qsl('select').onchange = function () { $onchange };", "") : "")
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

/** Get onclick confirmation */
function confirm(string $message = "", string $selector = "qsl('input')"): string {
	return script("$selector.onclick = () => confirm('" . ($message ? js_escape($message) : lang('Are you sure?')) . "');", "");
}

/** Print header for hidden fieldset (close by </div></fieldset>)
* @param bool $visible
*/
function print_fieldset(string $id, string $legend, $visible = false): void {
	echo "<fieldset><legend>";
	echo "<a href='#fieldset-$id'>$legend</a>";
	echo script("qsl('a').onclick = partial(toggle, 'fieldset-$id');", "");
	echo "</legend>";
	echo "<div id='fieldset-$id'" . ($visible ? "" : " class='hidden'") . ">\n";
}

/** Return class='active' if $bold is true */
function bold(bool $bold, string $class = ""): string {
	return ($bold ? " class='active $class'" : ($class ? " class='$class'" : ""));
}

/** Escape string for JavaScript apostrophes */
function js_escape(string $string): string {
	return addcslashes($string, "\r\n'\\/"); // slash for <script>
}

/** Generate page number for pagination */
function pagination(int $page, ?int $current): string {
	return " " . ($page == $current
		? $page + 1
		: '<a href="' . h(remove_from_uri("page") . ($page ? "&page=$page" . ($_GET["next"] ? "&next=" . urlencode($_GET["next"]) : "") : "")) . '">' . ($page + 1) . "</a>"
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
	echo (SERVER !== null ? input_hidden(DRIVER, SERVER) : "");
	echo input_hidden("username", $_GET["username"]);
}

/** Get <input type='file'> */
function file_input(string $input): string {
	$max_file_uploads = "max_file_uploads";
	$max_file_uploads_value = ini_get($max_file_uploads);
	$upload_max_filesize = "upload_max_filesize";
	$upload_max_filesize_value = ini_get($upload_max_filesize);
	return (ini_bool("file_uploads")
		? $input . script("qsl('input[type=\"file\"]').onchange = partialArg(fileChange, "
				. "$max_file_uploads_value, '" . lang('Increase %s.', "$max_file_uploads = $max_file_uploads_value") . "', " // ignore post_max_size because it is for all form fields together and bytes computing would be necessary
				. ini_bytes("upload_max_filesize") . ", '" . lang('Increase %s.', "$upload_max_filesize = $upload_max_filesize_value") . "')")
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
		$value = json_encode($value, 128 | 64 | 256); // 128 - JSON_PRETTY_PRINT, 64 - JSON_UNESCAPED_SLASHES, 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
		$function = "json";
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
	$disabled = stripos($field["default"], "GENERATED ALWAYS AS ") === 0 ? " disabled=''" : "";
	$attrs = " name='fields[$name]" . ($field["type"] == "enum" || $field["type"] == "set" ? "[]" : "") . "'$disabled" . ($autofocus ? " autofocus" : "");
	echo driver()->unconvertFunction($field) . " ";
	$table = $_GET["edit"] ?: $_GET["select"];
	if ($field["type"] == "enum") {
		echo h($functions[""]) . "<td>" . adminer()->editInput($table, $field, $attrs, $value);
	} else {
		$has_function = (in_array($function, $functions) || isset($functions[$function]));
		echo (count($functions) > 1
			? "<select name='function[$name]'$disabled>" . optionlist($functions, $function === null || $has_function ? $function : "") . "</select>"
				. on_help("event.target.value.replace(/^SQL\$/, '')", 1)
				. script("qsl('select').onchange = functionChange;", "")
			: h(reset($functions))
		) . '<td>';
		$input = adminer()->editInput($table, $field, $attrs, $value); // usage in call is without a table
		if ($input != "") {
			echo $input;
		} elseif (preg_match('~bool~', $field["type"])) {
			echo "<input type='hidden'$attrs value='0'>"
				. "<input type='checkbox'" . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? " checked='checked'" : "") . "$attrs value='1'>";
		} elseif ($field["type"] == "set") {
			echo enum_input("checkbox", $attrs, $field, (is_string($value) ? explode(",", $value) : $value));
		} elseif (is_blob($field) && ini_bool("file_uploads")) {
			echo "<input type='file' name='fields-$name'>";
		} elseif ($function == "json" || preg_match('~^jsonb?$~', $field["type"])) {
			echo "<textarea$attrs cols='50' rows='12' class='jush-js'>" . h($value) . '</textarea>';
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
				. ((!$has_function || $function === "") && preg_match('~(?<!o)int(?!er)~', $field["type"]) && !preg_match('~\[\]~', $field["full_type"]) ? " type='number'" : "")
				. " value='" . h($value) . "'" . ($maxlength ? " data-maxlength='$maxlength'" : "")
				. (preg_match('~char|binary~', $field["type"]) && $maxlength > 20 ? " size='" . ($maxlength > 99 ? 60 : 40) . "'" : "")
				. "$attrs>"
			;
		}
		echo adminer()->editHint($table, $field, $value);
		// skip 'original'
		$first = 0;
		foreach ($functions as $key => $val) {
			if ($key === "" || !$val) {
				break;
			}
			$first++;
		}
		if ($first && count($functions) > 1) {
			echo script("qsl('td').oninput = partial(skipOriginal, $first);");
		}
	}
}

/** Process edit input field
* @param Field|RoutineField $field
* @return mixed false to leave the original value
*/
function process_input(array $field) {
	if (stripos($field["default"], "GENERATED ALWAYS AS ") === 0) {
		return;
	}
	$idf = bracket_escape($field["field"]);
	$function = idx($_POST["function"], $idf);
	$value = idx($_POST["fields"], $idf);
	if ($field["type"] == "enum" || driver()->enumLength($field)) {
		$value = $value[0];
		if ($value == "orig") {
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
	if ($function == "orig") {
		return (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? idf_escape($field["field"]) : false);
	}
	if ($function == "NULL") {
		return "NULL";
	}
	if ($field["type"] == "set") {
		$value = implode(",", (array) $value);
	}
	if ($function == "json") {
		$function = "";
		$value = json_decode($value, true);
		if (!is_array($value)) {
			return false; //! report errors
		}
		return $value;
	}
	if (is_blob($field) && ini_bool("file_uploads")) {
		$file = get_file("fields-$idf");
		if (!is_string($file)) {
			return false; //! report errors
		}
		return driver()->quoteBinary($file);
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
				$print = "<a href='" . h(ME . "select=" . urlencode($table) . "&where[0][op]=" . urlencode($_GET["where"][0]["op"]) . "&where[0][val]=" . urlencode($_GET["where"][0]["val"])) . "'>$name</a>";
				echo "$sep<li>" . ($result ? $print : "<p class='error'>$print: " . error()) . "\n";
				$sep = "";
			}
		}
	}
	echo ($sep ? "<p class='message'>" . lang('No tables.') : "</ul>") . "\n";
}

/** Return events to display help on mouse over
* @param string $command JS expression
* @param int $side 0 top, 1 left
*/
function on_help(string $command, int $side = 0): string {
	return script("mixin(qsl('select, input'), {onmouseover: function (event) { helpMouseover.call(this, event, $command, $side) }, onmouseout: helpMouseout});", "");
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
	if (!$fields) {
		echo "<p class='error'>" . lang('You have no privileges to update this table.') . "\n";
	} else {
		echo "<table class='layout'>" . script("qsl('table').onkeydown = editingKeydown;");
		$autofocus = !$_POST;
		foreach ($fields as $name => $field) {
			echo "<tr><th>" . adminer()->fieldName($field);
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
			$function = ($_POST["save"]
				? idx($_POST["function"], $name, "")
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
			echo "\n";
		}
		if (!support("table") && !fields($table)) {
			echo "<tr>"
				. "<th><input name='field_keys[]'>"
				. script("qsl('input').oninput = fieldChange;")
				. "<td class='function'>" . html_select("field_funs[]", adminer()->editFunctions(array("null" => isset($_GET["select"]))))
				. "<td><input name='field_vals[]'>"
				. "\n"
			;
		}
		echo "</table>\n";
	}
	echo "<p>\n";
	if ($fields) {
		echo "<input type='submit' value='" . lang('Save') . "'>\n";
		if (!isset($_GET["select"])) {
			echo "<input type='submit' name='insert' value='" . ($update
				? lang('Save and continue edit')
				: lang('Save and insert next')
			) . "' title='Ctrl+Shift+Enter'>\n";
			echo ($update ? script("qsl('input').onclick = function () { return !ajaxForm(this.form, '" . lang('Saving') . "…', this); };") : "");
		}
	}
	echo ($update ? "<input type='submit' name='delete' value='" . lang('Delete') . "'>" . confirm() . "\n" : "");
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
function icon(string $icon, string $name, string $html, string $title): string {
	return "<button type='submit' name='$name' title='" . h($title) . "' class='icon icon-$icon'><span>$html</span></button>";
}
