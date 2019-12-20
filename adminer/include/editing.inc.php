<?php
/** Print select result
* @param Min_Result
* @param Min_DB connection to examine indexes
* @param array
* @param int
* @return array $orgtables
*/
function select($result, $connection2 = null, $orgtables = array(), $limit = 0) {
	global $jush;
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	odd(''); // reset odd for each result
	for ($i=0; (!$limit || $i < $limit) && ($row = $result->fetch_row()); $i++) {
		if (!$i) {
			echo "<div class='scrollable'>\n";
			echo "<table cellspacing='0' class='nowrap'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = $field->orgtable;
				$orgname = $field->orgname;
				$return[$field->table] = $orgtable;
				if ($orgtables && $jush == "sql") { // MySQL EXPLAIN
					$links[$j] = ($name == "table" ? "table=" : ($name == "possible_keys" ? "indexes=" : null));
				} elseif ($orgtable != "") {
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
		echo "<tr" . odd() . ">";
		foreach ($row as $key => $val) {
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
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($orgtables && $jush == "sql") { // MySQL EXPLAIN
					$table = $row[array_search("table=", $links)];
					$link = $links[$key] . urlencode($orgtables[$table] != "" ? $orgtables[$table] : $table);
				} else {
					$link = "edit=" . urlencode($links[$key]);
					foreach ($indexes[$links[$key]] as $col => $j) {
						$link .= "&where" . urlencode("[" . bracket_escape($col) . "]") . "=" . urlencode($row[$j]);
					}
				}
				$val = "<a href='" . h(ME . $link) . "'>$val</a>";
			}
			echo "<td>$val";
		}
	}
	echo ($i ? "</table>\n</div>" : "<p class='message'>" . lang('No rows.')) . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @param string
* @return array ($table_name => $field)
*/
function referencable_primary($self) {
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

/** Get settings stored in a cookie
* @return array
*/
function adminer_settings() {
	parse_str($_COOKIE["adminer_settings"], $settings);
	return $settings;
}

/** Get setting stored in a cookie
* @param string
* @return array
*/
function adminer_setting($key) {
	$settings = adminer_settings();
	return $settings[$key];
}

/** Store settings to a cookie
* @param array
* @return bool
*/
function set_adminer_settings($settings) {
	return cookie("adminer_settings", http_build_query($settings + adminer_settings()));
}

/** Print SQL <textarea> tag
* @param string
* @param string or array in which case [0] of every element is used
* @param int
* @param int
* @return null
*/
function textarea($name, $value, $rows = 10, $cols = 80) {
	global $jush;
	echo "<textarea name='$name' rows='$rows' cols='$cols' class='sqlarea jush-$jush' spellcheck='false' wrap='off'>";
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time, $elapsed)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Print table columns for type edit
* @param string
* @param array
* @param array
* @param array returned by referencable_primary()
* @param array extra types to prepend
* @return null
*/
function edit_type($key, $field, $collations, $foreign_keys = array(), $extra_types = array()) {
	global $structured_types, $types, $unsigned, $on_actions;
	$type = $field["type"];
	?>
<td><select name="<?php echo h($key); ?>[type]" class="type" aria-labelledby="label-type"><?php
if ($type && !isset($types[$type]) && !isset($foreign_keys[$type]) && !in_array($type, $extra_types)) {
	$extra_types[] = $type;
}
if ($foreign_keys) {
	$structured_types[lang('Foreign keys')] = $foreign_keys;
}
echo optionlist(array_merge($extra_types, $structured_types), $type);
?></select><td><input name="<?php echo h($key); ?>[length]" value="<?php echo h($field["length"]); ?>" size="3"<?php echo (!$field["length"] && preg_match('~var(char|binary)$~', $type) ? " class='required'" : ""); //! type="number" with enabled JavaScript ?> aria-labelledby="label-length"><td class="options"><?php
	echo "<select name='" . h($key) . "[collation]'" . (preg_match('~(char|text|enum|set)$~', $type) ? "" : " class='hidden'") . '><option value="">(' . lang('collation') . ')' . optionlist($collations, $field["collation"]) . '</select>';
	echo ($unsigned ? "<select name='" . h($key) . "[unsigned]'" . (!$type || preg_match(number_type(), $type) ? "" : " class='hidden'") . '><option>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : '');
	echo (isset($field['on_update']) ? "<select name='" . h($key) . "[on_update]'" . (preg_match('~timestamp|datetime~', $type) ? "" : " class='hidden'") . '>' . optionlist(array("" => "(" . lang('ON UPDATE') . ")", "CURRENT_TIMESTAMP"), (preg_match('~^CURRENT_TIMESTAMP~i', $field["on_update"]) ? "CURRENT_TIMESTAMP" : $field["on_update"])) . '</select>' : '');
	echo ($foreign_keys ? "<select name='" . h($key) . "[on_delete]'" . (preg_match("~`~", $type) ? "" : " class='hidden'") . "><option value=''>(" . lang('ON DELETE') . ")" . optionlist(explode("|", $on_actions), $field["on_delete"]) . "</select> " : " "); // space for IE
}

/** Filter length value including enums
* @param string
* @return string
*/
function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*\\(?\\s*$enum_length(?:\\s*,\\s*$enum_length)*+\\s*\\)?\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches)
		? "(" . implode(",", $matches[0]) . ")"
		: preg_replace('~^[0-9].*~', '(\0)', preg_replace('~[^-0-9,+()[\]]~', '', $length))
	);
}

/** Create SQL string from field type
* @param array
* @param string
* @return string
*/
function process_type($field, $collate = "COLLATE") {
	global $unsigned;
	return " $field[type]"
		. process_length($field["length"])
		. (preg_match(number_type(), $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (preg_match('~char|text|enum|set~', $field["type"]) && $field["collation"] ? " $collate " . q($field["collation"]) : "")
	;
}

/** Create SQL string from field
* @param array basic field information
* @param array information about field type
* @return array array("field", "type", "NULL", "DEFAULT", "ON UPDATE", "COMMENT", "AUTO_INCREMENT")
*/
function process_field($field, $type_field) {
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
* @param array
* @return string
*/
function default_value($field) {
	$default = $field["default"];
	return ($default === null ? "" : " DEFAULT " . (preg_match('~char|binary|text|enum|set~', $field["type"]) || preg_match('~^(?![a-z])~i', $default) ? q($default) : $default));
}

/** Get type class to use in CSS
* @param string
* @return string class=''
*/
function type_class($type) {
	foreach (array(
		'char' => 'text',
		'date' => 'time|year',
		'binary' => 'blob',
		'enum' => 'set',
	) as $key => $val) {
		if (preg_match("~$key|$val~", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param array
* @param array
* @param string TABLE or PROCEDURE
* @param array returned by referencable_primary()
* @return null
*/
function edit_fields($fields, $collations, $type = "TABLE", $foreign_keys = array()) {
	global $inout;
	$fields = array_values($fields);
	$default_class = (($_POST ? $_POST["defaults"] : adminer_setting("defaults")) ? "" : " class='hidden'");
	$comment_class = (($_POST ? $_POST["comments"] : adminer_setting("comments")) ? "" : " class='hidden'");
	?>
<thead><tr>
<?php if ($type == "PROCEDURE") { ?><td><?php } ?>
<th id="label-name"><?php echo ($type == "TABLE" ? lang('Column name') : lang('Parameter name')); ?>
<td id="label-type"><?php echo lang('Type'); ?><textarea id="enum-edit" rows="4" cols="12" wrap="off" style="display: none;"></textarea><?php echo script("qs('#enum-edit').onblur = editingLengthBlur;"); ?>
<td id="label-length"><?php echo lang('Length'); ?>
<td><?php echo lang('Options'); /* no label required, options have their own label */ ?>
<?php if ($type == "TABLE") { ?>
<td id="label-null">NULL
<td><input type="radio" name="auto_increment_col" value=""><acronym id="label-ai" title="<?php echo lang('Auto Increment'); ?>">AI</acronym><?php echo doc_link(array(
	'sql' => "example-auto-increment.html",
	'mariadb' => "auto_increment/",
	'sqlite' => "autoinc.html",
	'pgsql' => "datatype.html#DATATYPE-SERIAL",
	'mssql' => "ms186775.aspx",
)); ?>
<td id="label-default"<?php echo $default_class; ?>><?php echo lang('Default value'); ?>
<?php echo (support("comment") ? "<td id='label-comment'$comment_class>" . lang('Comment') : ""); ?>
<?php } ?>
<td><?php echo "<input type='image' class='icon' name='add[" . (support("move_col") ? 0 : count($fields)) . "]' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'>" . script("row_count = " . count($fields) . ";"); ?>
</thead>
<tbody>
<?php
	echo script("mixin(qsl('tbody'), {onclick: editingClick, onkeydown: editingKeydown, oninput: editingInput});");
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i])) && (support("drop_col") || $orig == "");
		?>
<tr<?php echo ($display ? "" : " style='display: none;'"); ?>>
<?php echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", $inout), $field["inout"]) : ""); ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo h($field["field"]); ?>" data-maxlength="64" autocapitalize="off" aria-labelledby="label-name"><?php } ?>
<input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo h($orig); ?>"><?php edit_type("fields[$i]", $field, $collations, $foreign_keys); ?>
<?php if ($type == "TABLE") { ?>
<td><?php echo checkbox("fields[$i][null]", 1, $field["null"], "", "", "block", "label-null"); ?>
<td><label class="block"><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked<?php } ?> aria-labelledby="label-ai"></label><td<?php echo $default_class; ?>><?php
			echo checkbox("fields[$i][has_default]", 1, $field["has_default"], "", "", "", "label-default"); ?><input name="fields[<?php echo $i; ?>][default]" value="<?php echo h($field["default"]); ?>" aria-labelledby="label-default"><?php
			echo (support("comment") ? "<td$comment_class><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' data-maxlength='" . (min_version(5.5) ? 1024 : 255) . "' aria-labelledby='label-comment'>" : "");
		}
		echo "<td>";
		echo (support("move_col") ?
			"<input type='image' class='icon' name='add[$i]' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'> "
			. "<input type='image' class='icon' name='up[$i]' src='../adminer/static/up.gif' alt='↑' title='" . lang('Move up') . "'> "
			. "<input type='image' class='icon' name='down[$i]' src='../adminer/static/down.gif' alt='↓' title='" . lang('Move down') . "'> "
		: "");
		echo ($orig == "" || support("drop_col") ? "<input type='image' class='icon' name='drop_col[$i]' src='../adminer/static/cross.gif' alt='x' title='" . lang('Remove') . "'>" : "");
	}
}

/** Move fields up and down or add field
* @param array
* @return bool
*/
function process_fields(&$fields) {
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
* @param array
* @return string
*/
function normalize_enum($match) {
	return "'" . str_replace("'", "''", addcslashes(stripcslashes(str_replace($match[0][0] . $match[0][0], $match[0][0], substr($match[0], 1, -1))), '\\')) . "'";
}

/** Issue grant or revoke commands
* @param string GRANT or REVOKE
* @param array
* @param string
* @param string
* @return bool
*/
function grant($grant, $privileges, $columns, $on) {
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
* @param string drop old object query
* @param string create new object query
* @param string drop new object query
* @param string create test object query
* @param string drop test object query
* @param string
* @param string
* @param string
* @param string
* @param string
* @param string
* @return null redirect in success
*/
function drop_create($drop, $create, $drop_created, $test, $drop_test, $location, $message_drop, $message_alter, $message_create, $old_name, $new_name) {
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
* @param string
* @param array result of trigger()
* @return string
*/
function create_trigger($on, $row) {
	global $jush;
	$timing_event = " $row[Timing] $row[Event]" . ($row["Event"] == "UPDATE OF" ? " " . idf_escape($row["Of"]) : "");
	return "CREATE TRIGGER "
		. idf_escape($row["Trigger"])
		. ($jush == "mssql" ? $on . $timing_event : $timing_event . $on)
		. rtrim(" $row[Type]\n$row[Statement]", ";")
		. ";"
	;
}

/** Generate SQL query for creating routine
* @param string "PROCEDURE" or "FUNCTION"
* @param array result of routine()
* @return string
*/
function create_routine($routine, $row) {
	global $inout, $jush;
	$set = array();
	$fields = (array) $row["fields"];
	ksort($fields); // enforce fields order
	foreach ($fields as $field) {
		if ($field["field"] != "") {
			$set[] = (preg_match("~^($inout)\$~", $field["inout"]) ? "$field[inout] " : "") . idf_escape($field["field"]) . process_type($field, "CHARACTER SET");
		}
	}
	$definition = rtrim("\n$row[definition]", ";");
	return "CREATE $routine "
		. idf_escape(trim($row["name"]))
		. " (" . implode(", ", $set) . ")"
		. (isset($_GET["function"]) ? " RETURNS" . process_type($row["returns"], "CHARACTER SET") : "")
		. ($row["language"] ? " LANGUAGE $row[language]" : "")
		. ($jush == "pgsql" ? " AS " . q($definition) : "$definition;")
	;
}

/** Remove current user definer from SQL command
* @param string
* @return string
*/
function remove_definer($query) {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\1)', logged_user()) . '`~', '\1', $query); //! proper escaping of user
}

/** Format foreign key to use in SQL query
* @param array ("db" => string, "ns" => string, "table" => string, "source" => array, "target" => array, "on_delete" => one of $on_actions, "on_update" => one of $on_actions)
* @return string
*/
function format_foreign_key($foreign_key) {
	global $on_actions;
	$db = $foreign_key["db"];
	$ns = $foreign_key["ns"];
	return " FOREIGN KEY (" . implode(", ", array_map('idf_escape', $foreign_key["source"])) . ") REFERENCES "
		. ($db != "" && $db != $_GET["db"] ? idf_escape($db) . "." : "")
		. ($ns != "" && $ns != $_GET["ns"] ? idf_escape($ns) . "." : "")
		. table($foreign_key["table"])
		. " (" . implode(", ", array_map('idf_escape', $foreign_key["target"])) . ")" //! reuse $name - check in older MySQL versions
		. (preg_match("~^($on_actions)\$~", $foreign_key["on_delete"]) ? " ON DELETE $foreign_key[on_delete]" : "")
		. (preg_match("~^($on_actions)\$~", $foreign_key["on_update"]) ? " ON UPDATE $foreign_key[on_update]" : "")
	;
}

/** Add a file to TAR
* @param string
* @param TmpFile
* @return null prints the output
*/
function tar_file($filename, $tmp_file) {
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

/** Get INI bytes value
* @param string
* @return int
*/
function ini_bytes($ini) {
	$val = ini_get($ini);
	switch (strtolower(substr($val, -1))) {
		case 'g': $val *= 1024; // no break
		case 'm': $val *= 1024; // no break
		case 'k': $val *= 1024;
	}
	return $val;
}

/** Create link to database documentation
* @param array $jush => $path
* @param string HTML code
* @return string HTML code
*/
function doc_link($paths, $text = "<sup>?</sup>") {
	global $jush, $connection;
	$server_info = $connection->server_info;
	$version = preg_replace('~^(\d\.?\d).*~s', '\1', $server_info); // two most significant digits
	$urls = array(
		'sql' => "https://dev.mysql.com/doc/refman/$version/en/",
		'sqlite' => "https://www.sqlite.org/",
		'pgsql' => "https://www.postgresql.org/docs/$version/",
		'mssql' => "https://msdn.microsoft.com/library/",
		'oracle' => "https://www.oracle.com/pls/topic/lookup?ctx=db" . preg_replace('~^.* (\d+)\.(\d+)\.\d+\.\d+\.\d+.*~s', '\1\2', $server_info) . "&id=",
	);
	if (preg_match('~MariaDB~', $server_info)) {
		$urls['sql'] = "https://mariadb.com/kb/en/library/";
		$paths['sql'] = (isset($paths['mariadb']) ? $paths['mariadb'] : str_replace(".html", "/", $paths['sql']));
	}
	return ($paths[$jush] ? "<a href='$urls[$jush]$paths[$jush]'" . target_blank() . ">$text</a>" : "");
}

/** Wrap gzencode() for usage in ob_start()
* @param string
* @return string
*/
function ob_gzencode($string) {
	// ob_start() callback recieves an optional parameter $phase but gzencode() accepts optional parameter $level
	return gzencode($string);
}

/** Compute size of database
* @param string
* @return string formatted
*/
function db_size($db) {
	global $connection;
	if (!$connection->select_db($db)) {
		return "?";
	}
	$return = 0;
	foreach (table_status() as $table_status) {
		$return += $table_status["Data_length"] + $table_status["Index_length"];
	}
	return format_number($return);
}

/** Print SET NAMES if utf8mb4 might be needed
* @param string
* @return null
*/
function set_utf8mb4($create) {
	global $connection;
	static $set = false;
	if (!$set && preg_match('~\butf8mb4~i', $create)) { // possible false positive
		$set = true;
		echo "SET NAMES " . charset($connection) . ";\n\n";
	}
}
