<?php
/** Print select result
* @param Min_Result
* @param Min_DB connection to examine indexes
* @param string base link for <th> fields
* @param array 
* @return null
*/
function select($result, $connection2 = null, $href = "", $orgtables = array()) {
	$links = array(); // colno => orgtable - create links from these columns
	$indexes = array(); // orgtable => array(column => colno) - primary keys
	$columns = array(); // orgtable => array(column => ) - not selected columns in primary key
	$blobs = array(); // colno => bool - display bytes for blobs
	$types = array(); // colno => type - display char in <code>
	$return = array(); // table => orgtable - mapping to use in EXPLAIN
	odd(''); // reset odd for each result
	for ($i=0; $row = $result->fetch_row(); $i++) {
		if (!$i) {
			echo "<table cellspacing='0' class='nowrap'>\n";
			echo "<thead><tr>";
			for ($j=0; $j < count($row); $j++) {
				$field = $result->fetch_field();
				$name = $field->name;
				$orgtable = $field->orgtable;
				$orgname = $field->orgname;
				$return[$field->table] = $orgtable;
				if ($href) { // MySQL EXPLAIN
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
				$name = h($name);
				echo "<th" . ($orgtable != "" || $field->name != $orgname ? " title='" . h(($orgtable != "" ? "$orgtable." : "") . $orgname) . "'" : "") . ">" . ($href ? "<a href='$href" . strtolower($name) . "' target='_blank' rel='noreferrer'>$name</a>" : $name);
			}
			echo "</thead>\n";
		}
		echo "<tr" . odd() . ">";
		foreach ($row as $key => $val) {
			if ($val === null) {
				$val = "<i>NULL</i>";
			} elseif ($blobs[$key] && !is_utf8($val)) {
				$val = "<i>" . lang('%d byte(s)', strlen($val)) . "</i>"; //! link to download
			} elseif (!strlen($val)) { // strlen - SQLite can return int
				$val = "&nbsp;"; // some content to print a border
			} else {
				$val = h($val);
				if ($types[$key] == 254) { // 254 - char
					$val = "<code>$val</code>";
				}
			}
			if (isset($links[$key]) && !$columns[$links[$key]]) {
				if ($href) { // MySQL EXPLAIN
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
	echo ($i ? "</table>" : "<p class='message'>" . lang('No rows.')) . "\n";
	return $return;
}

/** Get referencable tables with single column primary key except self
* @param string
* @return array ($table_name => $field)
*/
function referencable_primary($self) {
	$return = array(); // table_name => field
	foreach (table_status() as $table_name => $table) {
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
* @param string
* @param int
* @param int
* @param string
* @return null
*/
function textarea($name, $value, $rows = 10, $cols = 80) {
	echo "<textarea name='$name' rows='$rows' cols='$cols' class='sqlarea' spellcheck='false' wrap='off' onkeydown='return textareaKeydown(this, event);'>"; // spellcheck, wrap - not valid before HTML5
	if (is_array($value)) {
		foreach ($value as $val) { // not implode() to save memory
			echo h($val[0]) . "\n\n\n"; // $val == array($query, $time)
		}
	} else {
		echo h($value);
	}
	echo "</textarea>";
}

/** Format time difference
* @param string output of microtime()
* @param string output of microtime()
* @return string HTML code
*/
function format_time($start, $end) {
	return " <span class='time'>(" . lang('%.3f s', max(0, array_sum(explode(" ", $end)) - array_sum(explode(" ", $start)))) . ")</span>";
}

/** Print table columns for type edit
* @param string
* @param array
* @param array
* @param array returned by referencable_primary()
* @return null
*/
function edit_type($key, $field, $collations, $foreign_keys = array()) {
	global $structured_types, $types, $unsigned, $on_actions;
	?>
<td><select name="<?php echo $key; ?>[type]" class="type" onfocus="lastType = selectValue(this);" onchange="editingTypeChange(this);"><?php echo optionlist((!$field["type"] || isset($types[$field["type"]]) ? array() : array($field["type"])) + $structured_types + ($foreign_keys ? array(lang('Foreign keys') => $foreign_keys) : array()), $field["type"]); ?></select>
<td><input name="<?php echo $key; ?>[length]" value="<?php echo h($field["length"]); ?>" size="3" onfocus="editingLengthFocus(this);"><td class="options"><?php
	echo "<select name='$key" . "[collation]'" . (ereg('(char|text|enum|set)$', $field["type"]) ? "" : " class='hidden'") . '><option value="">(' . lang('collation') . ')' . optionlist($collations, $field["collation"]) . '</select>';
	echo ($unsigned ? "<select name='$key" . "[unsigned]'" . (!$field["type"] || ereg('(int|float|double|decimal)$', $field["type"]) ? "" : " class='hidden'") . '><option>' . optionlist($unsigned, $field["unsigned"]) . '</select>' : '');
	echo ($foreign_keys ? "<select name='$key" . "[on_delete]'" . (ereg("`", $field["type"]) ? "" : " class='hidden'") . "><option value=''>(" . lang('ON DELETE') . ")" . optionlist(explode("|", $on_actions), $field["on_delete"]) . "</select> " : " "); // space for IE
}

/** Filter length value including enums
* @param string
* @return string
*/
function process_length($length) {
	global $enum_length;
	return (preg_match("~^\\s*(?:$enum_length)(?:\\s*,\\s*(?:$enum_length))*\\s*\$~", $length) && preg_match_all("~$enum_length~", $length, $matches) ? implode(",", $matches[0]) : preg_replace('~[^0-9,+-]~', '', $length));
}

/** Create SQL string from field type
* @param array
* @param string
* @return string
*/
function process_type($field, $collate = "COLLATE") {
	global $unsigned;
	return " $field[type]"
		. ($field["length"] != "" ? "(" . process_length($field["length"]) . ")" : "")
		. (ereg('int|float|double|decimal', $field["type"]) && in_array($field["unsigned"], $unsigned) ? " $field[unsigned]" : "")
		. (ereg('char|text|enum|set', $field["type"]) && $field["collation"] ? " $collate " . q($field["collation"]) : "")
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
		(isset($field["default"]) ? " DEFAULT " . (($field["type"] == "timestamp" && eregi('^CURRENT_TIMESTAMP$', $field["default"])) || ($field["type"] == "bit" && ereg("^([0-9]+|b'[0-1]+')\$", $field["default"])) ? $field["default"] : q($field["default"])) : ""),
		($field["on_update"] ? " ON UPDATE $field[on_update]" : ""),
		(support("comment") && $field["comment"] != "" ? " COMMENT " . q($field["comment"]) : ""),
		($field["auto_increment"] ? auto_increment() : null),
	);
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
		if (ereg("$key|$val", $type)) {
			return " class='$key'";
		}
	}
}

/** Print table interior for fields editing
* @param array
* @param array
* @param string TABLE or PROCEDURE
* @param int number of fields allowed by Suhosin
* @param array returned by referencable_primary()
* @param bool display comments column
* @return null
*/
function edit_fields($fields, $collations, $type = "TABLE", $allowed = 0, $foreign_keys = array(), $comments = false) {
	global $inout;
	?>
<thead><tr class="wrap">
<?php if ($type == "PROCEDURE") { ?><td>&nbsp;<?php } ?>
<th><?php echo ($type == "TABLE" ? lang('Column name') : lang('Parameter name')); ?>
<td><?php echo lang('Type'); ?><textarea id="enum-edit" rows="4" cols="12" wrap="off" style="display: none;" onblur="editingLengthBlur(this);"></textarea>
<td><?php echo lang('Length'); ?>
<td><?php echo lang('Options'); ?>
<?php if ($type == "TABLE") { ?>
<td>NULL
<td><input type="radio" name="auto_increment_col" value=""><acronym title="<?php echo lang('Auto Increment'); ?>">AI</acronym>
<td<?php echo ($_POST["defaults"] ? "" : " class='hidden'"); ?>><?php echo lang('Default values'); ?>
<?php echo (support("comment") ? "<td" . ($comments ? "" : " class='hidden'") . ">" . lang('Comment') : ""); ?>
<?php } ?>
<td><?php echo "<input type='image' class='icon' name='add[" . (support("move_col") ? 0 : count($fields)) . "]' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "'>"; ?><script type="text/javascript">row_count = <?php echo count($fields); ?>;</script>
</thead>
<tbody onkeydown="return editingKeydown(event);">
<?php
	foreach ($fields as $i => $field) {
		$i++;
		$orig = $field[($_POST ? "orig" : "field")];
		$display = (isset($_POST["add"][$i-1]) || (isset($field["field"]) && !$_POST["drop_col"][$i])) && (support("drop_col") || $orig == "");
		?>
<tr<?php echo ($display ? "" : " style='display: none;'"); ?>>
<?php echo ($type == "PROCEDURE" ? "<td>" . html_select("fields[$i][inout]", explode("|", $inout), $field["inout"]) : ""); ?>
<th><?php if ($display) { ?><input name="fields[<?php echo $i; ?>][field]" value="<?php echo h($field["field"]); ?>" onchange="<?php echo ($field["field"] != "" || count($fields) > 1 ? "" : "editingAddRow(this, $allowed); "); ?>editingNameChange(this);" maxlength="64"><?php } ?><input type="hidden" name="fields[<?php echo $i; ?>][orig]" value="<?php echo h($orig); ?>">
<?php edit_type("fields[$i]", $field, $collations, $foreign_keys); ?>
<?php if ($type == "TABLE") { ?>
<td><?php echo checkbox("fields[$i][null]", 1, $field["null"]); ?>
<td><input type="radio" name="auto_increment_col" value="<?php echo $i; ?>"<?php if ($field["auto_increment"]) { ?> checked<?php } ?> onclick="var field = this.form['fields[' + this.value + '][field]']; if (!field.value) { field.value = 'id'; field.onchange(); }">
<td<?php echo ($_POST["defaults"] ? "" : " class='hidden'"); ?>><?php echo checkbox("fields[$i][has_default]", 1, $field["has_default"]); ?><input name="fields[<?php echo $i; ?>][default]" value="<?php echo h($field["default"]); ?>" onchange="this.previousSibling.checked = true;">
<?php echo (support("comment") ? "<td" . ($comments ? "" : " class='hidden'") . "><input name='fields[$i][comment]' value='" . h($field["comment"]) . "' maxlength='255'>" : ""); ?>
<?php } ?>
<?php
		echo "<td>";
		echo (support("move_col") ?
			"<input type='image' class='icon' name='add[$i]' src='../adminer/static/plus.gif' alt='+' title='" . lang('Add next') . "' onclick='return !editingAddRow(this, $allowed, 1);'>&nbsp;"
			. "<input type='image' class='icon' name='up[$i]' src='../adminer/static/up.gif' alt='^' title='" . lang('Move up') . "'>&nbsp;"
			. "<input type='image' class='icon' name='down[$i]' src='../adminer/static/down.gif' alt='v' title='" . lang('Move down') . "'>&nbsp;"
		: "");
		echo ($orig == "" || support("drop_col") ? "<input type='image' class='icon' name='drop_col[$i]' src='../adminer/static/cross.gif' alt='x' title='" . lang('Remove') . "' onclick='return !editingRemoveRow(this);'>" : "");
		echo "\n";
	}
}

/** Move fields up and down or add field
* @param array
* @return null
*/
function process_fields(&$fields) {
	ksort($fields);
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
	}
	if ($_POST["down"]) {
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
	}
	$fields = array_values($fields);
	if ($_POST["add"]) {
		array_splice($fields, key($_POST["add"]), 0, array(array()));
	}
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
	return queries("$grant " . preg_replace('~(GRANT OPTION)\\([^)]*\\)~', '\\1', implode("$columns, ", $privileges) . $columns) . $on);
}

/** Drop old object and create a new one
* @param string drop query
* @param string create query
* @param string
* @param string
* @param string
* @param string
* @param string
* @return bool dropped
*/
function drop_create($drop, $create, $location, $message_drop, $message_alter, $message_create, $name) {
	if ($_POST["drop"]) {
		return query_redirect($drop, $location, $message_drop, true, !$_POST["dropped"]);
	}
	$dropped = $name != "" && ($_POST["dropped"] || queries($drop));
	$created = queries($create);
	if (!queries_redirect($location, ($name != "" ? $message_alter : $message_create), $created) && $dropped) {
		redirect(null, $message_drop);
	}
	return $dropped;
}

/** Remove current user definer from SQL command
 * @param string
 * @return string
 */
function remove_definer($query) {
	return preg_replace('~^([A-Z =]+) DEFINER=`' . preg_replace('~@(.*)~', '`@`(%|\\1)', logged_user()) . '`~', '\\1', $query); //! proper escaping of user
}

/** Get string to add a file in TAR
* @param string
* @param string
* @return string
*/
function tar_file($filename, $contents) {
	$return = pack("a100a8a8a8a12a12", $filename, 644, 0, 0, decoct(strlen($contents)), decoct(time()));
	$checksum = 8*32; // space for checksum itself
	for ($i=0; $i < strlen($return); $i++) {
		$checksum += ord($return[$i]);
	}
	$return .= sprintf("%06o", $checksum) . "\0 ";
	return $return . str_repeat("\0", 512 - strlen($return)) . $contents . str_repeat("\0", 511 - (strlen($contents) + 511) % 512);
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
