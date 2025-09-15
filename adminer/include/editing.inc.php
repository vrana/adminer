<?php
namespace Adminer;

// This file is not used in Adminer Editor.

/** Print select result
* @param Result $result
* @param string[] $orgtables
* @param int|numeric-string $limit
* @return string[] $orgtables
*/
function print_select_result($result, ?Db $connection2 = null, array $orgtables = array(), $limit = 0): array {
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	for ($i=0; (!$limit || $i < $limit) && ($row = $result->fetch_row()); $i++) {
		if (!$i) {
			echo "<div class='scrollable'>\n";
			echo "<table class='nowrap odds'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = (isset($field->orgtable) ? $field->orgtable : "");
				$orgname = (isset($field->orgname) ? $field->orgname : $name);
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$links[$j] = ($name == "table" ? "table=" : ($name == "possible_keys" ? "indexes=" : null));
				} elseif ($orgtable != "") {
					if (isset($field->table)) {
						$return[$field->table] = $orgtable;
					}
					if (!isset($indexes[$orgtable])) {
						// find primary key in each table
						$indexes[$orgtable] = array();
						foreach (indexes($orgtable, $connection2) as $index) {
							if ($index["type"] == "PRIMARY") {
								$indexes[$orgtable] = array_flip($index["columns"]);
								break;
							}
						}
						$columns[$orgtable] = $indexes[$orgtable];
					}
					if (isset($columns[$orgtable][$orgname])) {
						unset($columns[$orgtable][$orgname]);
						$indexes[$orgtable][$orgname] = $j;
						$links[$j] = $orgtable;
					}
				}
				if ($field->charsetnr == 63) { // 63 - binary
					$blobs[$j] = true;
				}
				$types[$j] = $field->type;
				echo "<th" . ($orgtable != "" || $field->name != $orgname ? " title='" . h(($orgtable != "" ? "$orgtable." : "") . $orgname) . "'" : "") . ">" . h($name)
					. ($orgtables ? doc_link(array(
						'sql' => "explain-output.html#explain_" . strtolower($name),
						'mariadb' => "explain/#the-columns-in-explain-select",
					)) : "")
				;
			}
			echo "</thead>\n";
		}
		echo "<tr>";
		foreach ($row as $key => $val) {
			$link = "";
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($orgtables && JUSH == "sql") { // MySQL EXPLAIN
					$table = $row[array_search("table=", $links)];
					$link = ME . $links[$key] . urlencode($orgtables[$table] != "" ? $orgtables[$table] : $table);
				} else {
					$link = ME . "edit=" . urlencode($links[$key]);
					foreach ($indexes[$links[$key]] as $col => $j) {
						if ($row[$j] === null) {
							$link = "";
							break;
						}
						$link .= "&where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
					}
				}
			} elseif (is_url($val)) {
				$link = $val;
			}
			if ($val === null) {
				$val = "<i>NULL</i>";
			} elseif ($blobs[$key] && !is_utf8($val)) {
				$val = "<i>" . lang('%d byte(s)', strlen($val)) . "</i>"; //! link to download
			} else {
				$val = h($val);
				if ($types[$key] == 254) { // 254 - char
					$val = "<code>$val</code>";
				}
			}
			if ($link) {
				$val = "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : '') . ">$val</a>";
			}
			// https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html
			echo "<td" . ($types[$key] <= 9 || $types[$key] == 246 ? " class='number'" : "") . ">$val";
		}
	}
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . lang('No rows.')) . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @return array<string, Field> [$table_name => $field]
*/
function referencable_primary(string $self): array {
	$return = array(); // table_name => field
	foreach (table_status('', true) as $table_name => $table) {
		if ($table_name != $self && fk_support($table)) {
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

/** Print SQL <textarea> tag
* @param string|list<array{string}> $value
*/
function textarea(string $name, $value, int $rows = 10, int $cols = 80): void {
	echo "<textarea name='" . h($name) . "' rows='$rows' cols='$cols' class='sqlarea jush-" . JUSH . "' spellcheck='false' wrap='off'>";
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time, $elapsed)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Generate HTML <select> or <input> if $options are empty
* @param string[] $options
*/
function select_input(string $attrs, array $options, ?string $value = "", string $onchange = "", string $placeholder = ""): string {
	$tag = ($options ? "select" : "input");
	return "<$tag$attrs" . ($options
		? "><option value=''>$placeholder" . optionlist($options, $value, true) . "</select>"
		: " size='10' value='" . h($value) . "' placeholder='$placeholder'>"
	) . ($onchange ? script("qsl('$tag').onchange = $onchange;", "") : ""); //! use oninput for input
}

/** Print one row in JSON object
* @param string $key or "" to close the object
* @param string|int $val
*/
function json_row(string $key, $val = null, bool $escape = true): void {
	static $first = true;
	if ($first) {
		echo "{";
	}
	if ($key != "") {
		echo ($first ? "" : ",") . "\n\t\"" . addcslashes($key, "\r\n\t\"\\/") . '": ' . ($val !== null ? ($escape ? '"' . addcslashes($val, "\r\n\"\\/") . '"' : $val) : 'null');
		$first = false;
	} else {
		echo "\n}\n";
		$first = true;
	}
}

/** Print table columns for type edit
* @param Field $field
* @param list<string> $collations
* @param string[] $foreign_keys
* @param list<string> $extra_types extra types to prepend
*/
function edit_type(string $key, array $field, array $collations, array $foreign_keys = array(), array $extra_types = array()): void {
	$type = $field["type"];
	echo "<td><select name='" . h($key) . "[type]' class='type' aria-labelledby='label-type'>";
	if ($type && !array_key_exists($type, driver()->types()) && !isset($foreign_keys[$type]) && !in_array($type, $extra_types)) {
		$extra_types[] = $type;
	}
	$structured_types = driver()->structuredTypes();
	if ($foreign_keys) {
		$structured_types[lang('Foreign keys')] = $foreign_keys;
	}
	echo optionlist(array_merge($extra_types, $structured_types), $type);
	echo "</select><td>";
	echo "<input name='" . h($key) . "[length]' value='" . h($field["length"]) . "' size='3'"
		. (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : "") //! type="number" with enabled JavaScript
		. " aria-labelledby='label-length'>";
	echo "<td class='options'>";
	echo ($collations
		? "<input list='collations' name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . " value='" . h($field["collation"]) . "' placeholder='(" . lang('collation') . ")'>"
		: ''
	);
	echo (driver()->unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist(driver()->unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>'
		. optionlist(array("" => "(" . lang('ON UPDATE') . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"]))
		. '</select>' : ''
	);
	echo ($foreign_keys
		? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . lang('ON DELETE') . ")" . optionlist(explode("|", driver()->onActions), $field["on_delete"]) . "</select> "
		: " " // space for IE
	);
}

/** Filter length value including enums */
function process_length(?string $length): string {
	$enum_length = driver()->enumLength;
	return (preg_match("~^\\s*\\(?\\s*$enum_length(?:\\s*,\\s*$enum_length)*+\\s*\\)?\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches)
		? "(" . implode(",", $matches[0]) . ")"
		: preg_replace('~^[0-9].*~', '(\0)', preg_replace('~[^-0-9,+()[\]]~', '', $length))
	);
}

/** Create SQL string from field type
* @param FieldType $field
*/
function process_type(array $field, string $collate = "COLLATE"): string {
	return " $field[type]"
		. process_length($field["length"])
		. (preg_match(number_type(), $field["type"]) && in_array($field["unsigned"], driver()->unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate " . (JUSH == "mssql" ? $field["collation"] : q($field["collation"])) : "")
	;
}

/** Create SQL string from field
* @param Field $field basic field information
* @param Field $type_field information about field type
* @return list<string> ["field", "type", "NULL", "DEFAULT", "ON UPDATE", "COMMENT", "AUTO_INCREMENT"]
*/
function process_field(array $field, array $type_field): array {
	// MariaDB exports CURRENT_TIMESTAMP as a function.
	if ($field["on_update"]) {
		$field["on_update"] = str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", $field["on_update"]);
	}
	return array(
		idf_escape(trim($field["field"])),
		process_type($type_field),
		($field["null"] ? " NULL" : " NOT NULL"), // NULL for timestamp
		default_value($field),
		(preg_match('~timestamp|datetime~', $field["type"]) && $field["on_update"] ? " ON UPDATE $field[on_update]" : ""),
		(support("comment") && $field["comment"] != "" ? " COMMENT " . q($field["comment"]) : ""),
		($field["auto_increment"] ? auto_increment() : null),
	);
}

/** Get default value clause
* @param Field $field
*/
function default_value(array $field): string {
	$default = $field["default"];
	$generated = $field["generated"];
	return ($default === null ? "" : (in_array($generated, driver()->generated)
		? (JUSH == "mssql" ? " AS ($default)" . ($generated == "VIRTUAL" ? "" : " $generated") . "" : " GENERATED ALWAYS AS ($default) $generated")
		: " DEFAULT " . (!preg_match('~^GENERATED ~i', $default) && (preg_match('~char|binary|text|json|enum|set~', $field["type"]) || preg_match('~^(?![a-z])~i', $default))
			? (JUSH == "sql" && preg_match('~text|json~', $field["type"]) ? "(" . q($default) . ")" : q($default)) // MySQL requires () around default value of text column
			: str_ireplace("current_timestamp()", "CURRENT_TIMESTAMP", (JUSH == "sqlite" ? "($default)" : $default))
		)
	));
}

/** Get type class to use in CSS
* @return string|void class=''
*/
function type_class(string $type) {
	foreach (
		array(
			'char' => 'text',
			'date' => 'time|year',
			'binary' => 'blob',
			'enum' => 'set',
		) as $key => $val
	) {
		if (preg_match("~$key|$val~", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param (Field|RoutineField)[] $fields
* @param list<string> $collations
* @param 'TABLE'|'PROCEDURE' $type
* @param string[] $foreign_keys
*/
function edit_fields(array $fields, array $collations, $type = "TABLE", array $foreign_keys = array()): void {
	$fields = array_values($fields);
	$default_class = (($_POST ? $_POST["defaults"] : get_setting("defaults")) ? "" : " class='hidden'");
	$comment_class = (($_POST ? $_POST["comments"] : get_setting("comments")) ? "" : " class='hidden'");
	echo "<thead><tr>\n";
	echo ($type == "PROCEDURE" ? "<td>" : "");
	echo "<th id='label-name'>" . ($type == "TABLE" ? lang('Column name') : lang('Parameter name'));
	echo "<td id='label-type'>" . lang('Type') . "<textarea id='enum-edit' rows='4' cols='12' wrap='off' style='display: none;'></textarea>" . script("qs('#enum-edit').onblur = editingLengthBlur;");
	echo "<td id='label-length'>" . lang('Length');
	echo "<td>" . lang('Options'); // no label required, options have their own label
	if ($type == "TABLE") {
		echo "<td id='label-null'>NULL\n";
		echo "<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='" . lang('Auto Increment') . "'>AI</abbr>";
		echo doc_link(array(
			'sql' => "example-auto-increment.html",
			'mariadb' => "auto_increment/",
			'sqlite' => "autoinc.html",
			'pgsql' => "datatype-numeric.html#DATATYPE-SERIAL",
			'mssql' => "t-sql/statements/create-table-transact-sql-identity-property",
		));
		echo "<td id='label-default'$default_class>" . lang('Default value');
		echo (support("comment") ? "<td id='label-comment'$comment_class>" . lang('Comment') : "");
	}
	echo "<td>" . icon("plus", "add[" . (support("move_col") ? 0 : count($fields)) . "]", "+", lang('Add next'));
	echo "</thead>\n<tbody>\n";
	echo script("mixin(qsl('tbody'), {onclick: editingClick, onkeydown: editingKeydown, oninput: editingInput});");
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !idx($_POST["drop_col"], $i))) && (support("drop_col") || $orig == "");
		echo "<tr" . ($display ? "" : " style='display: none;'") . ">\n";
		echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", driver()->inout), $field["inout"]) : "") . "<th>";
		if ($display) {
			echo "<input name='fields[$i][field]' value='" . h($field["field"]) . "' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'" . (isset($_POST["add"][$i-1]) ? " autofocus" : "") . ">";
		}
		echo input_hidden("fields[$i][orig]", $orig);
		edit_type("fields[$i]", $field, $collations, $foreign_keys);
		if ($type == "TABLE") {
			echo "<td>" . checkbox("fields[$i][null]", 1, $field["null"], "", "", "block", "label-null");
			echo "<td><label class='block'><input type='radio' name='auto_increment_col' value='$i'" . ($field["auto_increment"] ? " checked" : "") . " aria-labelledby='label-ai'></label>";
			echo "<td$default_class>" . (driver()->generated
				? html_select("fields[$i][generated]", array_merge(array("", "DEFAULT"), driver()->generated), $field["generated"]) . " "
				: checkbox("fields[$i][generated]", 1, $field["generated"], "", "", "", "label-default")
			);
			echo "<input name='fields[$i][default]' value='" . h($field["default"]) . "' aria-labelledby='label-default'>";
			echo (support("comment") ? "<td$comment_class><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' data-maxlength='" . (min_version(5.5) ? 1024 : 255) . "' aria-labelledby='label-comment'>" : "");
		}
		echo "<td>";
		echo (support("move_col") ?
			icon("plus", "add[$i]", "+", lang('Add next')) . " "
			. icon("up", "up[$i]", "↑", lang('Move up')) . " "
			. icon("down", "down[$i]", "↓", lang('Move down')) . " "
		: "");
		echo ($orig == "" || support("drop_col") ? icon("cross", "drop_col[$i]", "x", lang('Remove')) : "");
	}
}

/** Move fields up and down or add field
* @param Field[] $fields
*/
function process_fields(array &$fields): bool {
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
	} elseif ($_POST["down"]) {
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
	} elseif ($_POST["add"]) {
		$fields = array_values($fields);
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	} elseif (!$_POST["drop_col"]) {
		return false;
	}
	return true;
}

/** Callback used in routine()
* @param list<string> $match
*/
function normalize_enum(array $match): string {
	$val = $match[0];
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($val[0] . $val[0], $val[0], substr($val, 1, -1))), '\\')) . "'";
}

/** Issue grant or revoke commands
* @param 'GRANT'|'REVOKE' $grant
* @param list<string> $privileges
* @return Result|bool
*/
function grant(string $grant, array $privileges, ?string $columns, string $on) {
	if (!$privileges) {
		return true;
	}
	if ($privileges == array("ALL PRIVILEGES", "GRANT OPTION")) {
		// can't be granted or revoked together
		return ($grant == "GRANT"
			? queries("$grant ALL PRIVILEGES$on WITH GRANT OPTION")
			: queries("$grant ALL PRIVILEGES$on") && queries("$grant GRANT OPTION$on")
		);
	}
	return queries("$grant " . preg_replace('~(GRANT OPTION)\([^)]*\)~', '\1', implode("$columns, ", $privileges) . $columns) . $on);
}

/** Drop old object and create a new one
* @param string $drop drop old object query
* @param string $create create new object query
* @param string $drop_created drop new object query
* @param string $test create test object query
* @param string $drop_test drop test object query
* @return void redirect on success
*/
function drop_create(string $drop, string $create, string $drop_created, string $test, string $drop_test, string $location, string $message_drop, string $message_alter, string $message_create, string $old_name, string $new_name): void {
	if ($_POST["drop"]) {
		query_redirect($drop, $location, $message_drop);
	} elseif ($old_name == "") {
		query_redirect($create, $location, $message_create);
	} elseif ($old_name != $new_name) {
		$created = queries($create);
		queries_redirect($location, $message_alter, $created && queries($drop));
		if ($created) {
			queries($drop_created);
		}
	} else {
		queries_redirect(
			$location,
			$message_alter,
			queries($test) && queries($drop_test) && queries($drop) && queries($create)
		);
	}
}

/** Generate SQL query for creating trigger
* @param Trigger $row
*/
function create_trigger(string $on, array $row): string {
	$timing_event = " $row[Timing] $row[Event]" . (preg_match('~ OF~', $row["Event"]) ? " $row[Of]" : ""); // SQL injection
	return "CREATE TRIGGER "
		. idf_escape($row["Trigger"])
		. (JUSH == "mssql" ? $on . $timing_event : $timing_event . $on)
		. rtrim(" $row[Type]\n$row[Statement]", ";")
		. ";"
	;
}

/** Generate SQL query for creating routine
* @param 'PROCEDURE'|'FUNCTION' $routine
* @param Routine $row
*/
function create_routine($routine, array $row): string {
	$set = array();
	$fields = (array) $row["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (preg_match("~^(" . driver()->inout . ")\$~", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$definition = rtrim($row["definition"], ";");
	return "CREATE $routine "
		. idf_escape(trim($row["name"]))
		. " (" . implode(", ", $set) . ")"
		. ($routine == "FUNCTION" ? " RETURNS" . process_type($row["returns"], "CHARACTER SET") : "")
		. ($row["language"] ? " LANGUAGE $row[language]" : "")
		. (JUSH == "pgsql" ? " AS " . q($definition) : "\n$definition;")
	;
}

/** Remove current user definer from SQL command */
function remove_definer(string $query): string {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', logged_user()) . '`~', '\1', $query); //! proper escaping of user
}

/** Format foreign key to use in SQL query
* @param ForeignKey $foreign_key
*/
function format_foreign_key(array $foreign_key): string {
	$db = $foreign_key["db"];
	$ns = $foreign_key["ns"];
	return " FOREIGN KEY (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["source"])) . ") REFERENCES "
		. ($db != "" && $db != $_GET["db"] ? idf_escape($db) . "." : "")
		. ($ns != "" && $ns != $_GET["ns"] ? idf_escape($ns) . "." : "")
		. idf_escape($foreign_key["table"])
		. " (" . implode(", ", array_map('Adminer\idf_escape', $foreign_key["target"])) . ")" //! reuse $name - check in older MySQL versions
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_delete"]) ? " ON DELETE $foreign_key[on_delete]" : "")
		. (preg_match("~^(" . driver()->onActions . ")\$~", $foreign_key["on_update"]) ? " ON UPDATE $foreign_key[on_update]" : "")
	;
}

/** Add a file to TAR
* @param TmpFile $tmp_file
* @return void prints the output
*/
function tar_file(string $filename, $tmp_file): void {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct($tmp_file->size), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return[$i]);
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	echo $return;
	echo str_repeat("\0", 512 - strlen($return));
	$tmp_file->send();
	echo str_repeat("\0", 511 - ($tmp_file->size + 511) % 512);
}

/** Create link to database documentation
* @param string[] $paths JUSH => $path
* @param string $text HTML code
* @return string HTML code
*/
function doc_link(array $paths, string $text = "<sup>?</sup>"): string {
	$server_info = connection()->server_info;
	$version = preg_replace('~^(\d\.?\d).*~s', '\1', $server_info); // two most significant digits
	$urls = array(
		'sql' => "https://dev.mysql.com/doc/refman/$version/en/",
		'sqlite' => "https://www.sqlite.org/",
		'pgsql' => "https://www.postgresql.org/docs/" . (connection()->flavor == 'cockroach' ? "current" : $version) . "/",
		'mssql' => "https://learn.microsoft.com/en-us/sql/",
		'oracle' => "https://www.oracle.com/pls/topic/lookup?ctx=db" . preg_replace('~^.* (\d+)\.(\d+)\.\d+\.\d+\.\d+.*~s', '\1\2', $server_info) . "&id=",
	);
	if (connection()->flavor == 'maria') {
		$urls['sql'] = "https://mariadb.com/kb/en/";
		$paths['sql'] = (isset($paths['mariadb']) ? $paths['mariadb'] : str_replace(".html", "/", $paths['sql']));
	}
	return ($paths[JUSH] ? "<a href='" . h($urls[JUSH] . $paths[JUSH] . (JUSH == 'mssql' ? "?view=sql-server-ver$version" : "")) . "'" . target_blank() . ">$text</a>" : "");
}

/** Compute size of database
* @return string formatted
*/
function db_size(string $db): string {
	if (!connection()->select_db($db)) {
		return "?";
	}
	$return = 0;
	foreach (table_status() as $table_status) {
		$return += $table_status["Data_length"] + $table_status["Index_length"];
	}
	return format_number($return);
}

/** Print SET NAMES if utf8mb4 might be needed */
function set_utf8mb4(string $create): void {
	static $set = false;
	if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
		$set = true;
		echo "SET NAMES " . charset(connection()) . ";\n\n";
	}
}
