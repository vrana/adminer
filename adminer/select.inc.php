<?php
$functions = array("char_length", "from_unixtime", "hex", "lower", "round", "sec_to_time", "time_to_sec", "unix_timestamp", "upper");
$grouping = array("avg", "count", "distinct", "group_concat", "max", "min", "sum");
$table_status = table_status($_GET["select"]);
$indexes = indexes($_GET["select"]);
$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL");
if (eregi('^(MyISAM|Maria)$', $table_status["Engine"])) {
	$operators[] = "AGAINST";
}
$fields = fields($_GET["select"]);
$rights = array();
$columns = array();
unset($text_length);
foreach ($fields as $key => $field) {
	if (isset($field["privileges"]["select"])) {
		$columns[] = $key;
		if (preg_match('~text|blob~', $field["type"])) {
			$text_length = (isset($_GET["text_length"]) ? $_GET["text_length"] : "100");
		}
	}
	$rights += $field["privileges"];
}

$select = array();
$group = array();
foreach ((array) $_GET["columns"] as $key => $val) {
	if ($val["fun"] == "count" || (in_array($val["col"], $columns, true) && (!$val["fun"] || in_array($val["fun"], $functions) || in_array($val["fun"], $grouping)))) {
		$select[$key] = (in_array($val["col"], $columns, true) ? (!$val["fun"] ? idf_escape($val["col"]) : ($val["fun"] == "distinct" ? "COUNT(DISTINCT " : strtoupper("$val[fun](")) . idf_escape($val["col"]) . ")") : "COUNT(*)");
		if (!in_array($val["fun"], $grouping)) {
			$group[] = $select[$key];
		}
	}
}
$where = array();
foreach ($indexes as $i => $index) {
	if ($index["type"] == "FULLTEXT" && strlen($_GET["fulltext"][$i])) {
		$where[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST ('" . $dbh->escape_string($_GET["fulltext"][$i]) . "'" . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
	}
}
foreach ((array) $_GET["where"] as $val) {
	if (strlen("$val[col]$val[val]") && in_array($val["op"], $operators)) {
		if ($val["op"] == "AGAINST") {
			$where[] = "MATCH (" . idf_escape($val["col"]) . ") AGAINST ('" . $dbh->escape_string($val["val"]) . "' IN BOOLEAN MODE)";
		} elseif (ereg('IN$', $val["op"]) && !strlen($in = process_length($val["val"]))) {
			$where[] = "0";
		} else {
			$cond = " $val[op]" . (ereg('NULL$', $val["op"]) ? "" : (ereg('IN$', $val["op"]) ? " ($in)" : " '" . $dbh->escape_string($val["val"]) . "'")); //! this searches in numeric values too
			if (strlen($val["col"])) {
				$where[] = idf_escape($val["col"]) . $cond;
			} else {
				$cols = array();
				foreach ($fields as $name => $field) {
					if (is_numeric($val["val"]) || !ereg('int|float|double|decimal', $field["type"])) {
						$cols[] = $name;
					}
				}
				$where[] = ($cols ? "(" . implode("$cond OR ", array_map('idf_escape', $cols)) . "$cond)" : "0");
			}
		}
	}
}
$order = array();
foreach ((array) $_GET["order"] as $key => $val) {
	if (in_array($val, $columns, true) || in_array($val, $select, true)) {
		$order[] = idf_escape($val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
	}
}
$limit = (isset($_GET["limit"]) ? $_GET["limit"] : "30");
$from = "FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "") . ($group && count($group) < count($select) ? " GROUP BY " . implode(", ", $group) : "") . ($order ? " ORDER BY " . implode(", ", $order) : "") . (strlen($limit) ? " LIMIT " . intval($limit) . (intval($_GET["page"]) ? " OFFSET " . ($limit * $_GET["page"]) : "") : "");

if ($_POST && !$error) {
	if ($_POST["export"]) {
		dump_headers($_GET["select"]);
		dump_table($_GET["select"], "");
		$query = "SELECT " . ($select ? implode(", ", $select) : "*") . " FROM " . idf_escape($_GET["select"]);
		if (is_array($_POST["check"])) {
			$union = array();
			foreach ($_POST["check"] as $val) {
				$union[] = "($query WHERE " . implode(" AND ", where_check($val)) . " LIMIT 1)";
			}
			dump_data($_GET["select"], "INSERT", implode(" UNION ALL ", $union));
		} else {
			dump_data($_GET["select"], "INSERT", $query . ($where ? " WHERE " . implode(" AND ", $where) : ""));
		}
		exit;
	}
	if (!$_POST["import"]) { // edit
		$result = true;
		$affected = 0;
		$command = ($_POST["delete"] ? ($_POST["all"] && !$where ? "TRUNCATE " : "DELETE FROM ") : ($_POST["clone"] ? "INSERT INTO " : "UPDATE ")) . idf_escape($_GET["select"]);
		if (!$_POST["delete"]) {
			$set = array();
			foreach ($fields as $name => $field) {
				$val = process_input($name, $field);
				if ($_POST["clone"]) {
					$set[] = ($val !== false ? $val : idf_escape($name));
				} elseif ($val !== false) {
					$set[] = "\n" . idf_escape($name) . " = $val";
				}
			}
			$command .= ($_POST["clone"] ? "\nSELECT " . implode(", ", $set) . " FROM " . idf_escape($_GET["select"]) : " SET" . implode(",", $set));
		}
		if (!$_POST["delete"] && !$set) {
			// nothing
		} elseif ($_POST["all"]) {
			$result = queries($command . ($where ? " WHERE " . implode(" AND ", $where) : ""));
			$affected = $dbh->affected_rows;
		} else {
			foreach ((array) $_POST["check"] as $val) {
				parse_str($val, $check);
				$result = queries($command . " WHERE " . implode(" AND ", where($check)) . " LIMIT 1");
				if (!$result) {
					break;
				}
				$affected += $dbh->affected_rows;
			}
		}
		query_redirect(queries(), remove_from_uri("page"), lang('%d item(s) have been affected.', $affected), $result, false, !$result);
		//! display edit page in case of an error
	} elseif (is_string($file = get_file("csv_file"))) {
		$file = preg_replace("~^\xEF\xBB\xBF~", '', $file); //! character set
		$cols = "";
		$rows = array(); //! packet size
		preg_match_all('~("[^"]*"|[^"\\n]+)+~', $file, $matches);
		foreach ($matches[0] as $key => $val) {
			$row = array();
			preg_match_all('~(("[^"]*")+|[^,]*),~', "$val,", $matches2);
			if (!$key && !array_diff($matches2[1], array_keys($fields))) { //! doesn't work with column names containing ",\n
				$cols = " (" . implode(", ", array_map('idf_escape', $matches2[1])) . ")";
			} else {
				foreach ($matches2[1] as $col) {
					$row[] = (!strlen($col) ? "NULL" : "'" . $dbh->escape_string(str_replace('""', '"', preg_replace('~^".*"$~s', '', $col))) . "'");
				}
				$rows[] = "(" . implode(", ", $row) . ")";
			}
		}
		$result = queries("INSERT INTO " . idf_escape($_GET["select"]) . "$cols VALUES " . implode(", ", $rows));
		query_redirect(queries(), remove_from_uri("page"), lang('%d row(s) has been imported.', $dbh->affected_rows), $result, false, !$result);
	} else {
		$error = lang('Unable to upload a file.');
	}
}
page_header(lang('Select') . ": " . htmlspecialchars($_GET["select"]), $error);

echo "<p>";
if (isset($rights["insert"])) {
	//! pass search values forth and back
	echo '<a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '">' . lang('New item') . '</a> ';
}
echo '<a href="' . htmlspecialchars($SELF) . (isset($table_status["Engine"]) ? 'table=' : 'view=') . urlencode($_GET['select']) . '">' . lang('Table structure') . '</a>';
echo "</p>\n";

if (!$columns) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "" : ": " . htmlspecialchars($dbh->error)) . ".</p>\n";
} else {
	echo "<form action='' id='form'>\n";
	echo '<fieldset><legend><a href="#fieldset-select" onclick="return !toggle(\'fieldset-select\');">' . lang('Select') . "</a></legend><div id='fieldset-select'" . ($select ? "" : " class='hidden'") . ">\n";
	if (strlen($_GET["server"])) {
		echo '<input type="hidden" name="server" value="' . htmlspecialchars($_GET["server"]) . '" />';
	}
	echo '<input type="hidden" name="db" value="' . htmlspecialchars($_GET["db"]) . '" />';
	echo '<input type="hidden" name="select" value="' . htmlspecialchars($_GET["select"]) . '" />';
	echo "\n";
	$i = 0;
	$fun_group = array(lang('Functions') => $functions, lang('Aggregation') => $grouping);
	foreach ($select as $key => $val) {
		$val = $_GET["columns"][$key];
		echo "<div><select name='columns[$i][fun]'><option></option>" . optionlist($fun_group, $val["fun"]) . "</select>";
		echo "<select name='columns[$i][col]'><option></option>" . optionlist($columns, $val["col"]) . "</select></div>\n";
		$i++;
	}
	echo "<div><select name='columns[$i][fun]' onchange='this.nextSibling.onchange();'><option></option>" . optionlist($fun_group) . "</select>";
	echo "<select name='columns[$i][col]' onchange='select_add_row(this);'><option></option>" . optionlist($columns) . "</select></div>\n";
	echo "</div></fieldset>\n";
	
	echo '<fieldset><legend><a href="#fieldset-search" onclick="return !toggle(\'fieldset-search\');">' . lang('Search') . "</a></legend><div id='fieldset-search'" . ($where ? "" : " class='hidden'") . ">\n";
	foreach ($indexes as $i => $index) {
		if ($index["type"] == "FULLTEXT") {
			echo "(<i>" . implode("</i>, <i>", array_map('htmlspecialchars', $index["columns"])) . "</i>) AGAINST";
			echo ' <input name="fulltext[' . $i . ']" value="' . htmlspecialchars($_GET["fulltext"][$i]) . '" />';
			echo "<label><input type='checkbox' name='boolean[$i]' value='1'" . (isset($_GET["boolean"][$i]) ? " checked='checked'" : "") . " />" . lang('BOOL') . "</label>";
			echo "<br />\n";
		}
	}
	$i = 0;
	foreach ((array) $_GET["where"] as $val) {
		if (strlen("$val[col]$val[val]") && in_array($val["op"], $operators)) {
			echo "<div><select name='where[$i][col]'><option value=''>" . lang('(anywhere)') . "</option>" . optionlist($columns, $val["col"]) . "</select>";
			echo "<select name='where[$i][op]' onchange='where_change(this);'>" . optionlist($operators, $val["op"]) . "</select>";
			echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . '"' . (ereg('NULL$', $val["op"]) ? " class='hidden'" : "") . " /></div>\n";
			$i++;
		}
	}
	echo "<div><select name='where[$i][col]' onchange='select_add_row(this);'><option value=''>" . lang('(anywhere)') . "</option>" . optionlist($columns) . "</select>";
	echo "<select name='where[$i][op]' onchange='where_change(this);'>" . optionlist($operators) . "</select>";
	echo "<input name='where[$i][val]' /></div>\n";
	echo "</div></fieldset>\n";
	
	echo '<fieldset><legend><a href="#fieldset-sort" onclick="return !toggle(\'fieldset-sort\');">' . lang('Sort') . "</a></legend><div id='fieldset-sort'" . (count($order) > 1 ? "" : " class='hidden'") . ">\n";
	$i = 0;
	foreach ((array) $_GET["order"] as $key => $val) {
		if (in_array($val, $columns, true)) {
			echo "<div><select name='order[$i]'><option></option>" . optionlist($columns, $val) . "</select>";
			echo "<label><input type='checkbox' name='desc[$i]' value='1'" . (isset($_GET["desc"][$key]) ? " checked='checked'" : "") . " />" . lang('descending') . "</label></div>\n";
			$i++;
		}
	}
	echo "<div><select name='order[$i]' onchange='select_add_row(this);'><option></option>" . optionlist($columns) . "</select>";
	echo "<label><input type='checkbox' name='desc[$i]' value='1' />" . lang('descending') . "</label></div>\n";
	echo "</div></fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Limit') . "</legend><div>";
	echo "<input name='limit' size='3' value=\"" . htmlspecialchars($limit) . "\" />";
	echo "</div></fieldset>\n";
	
	if (isset($text_length)) {
		echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
		echo "<input name='text_length' size='3' value=\"" . htmlspecialchars($text_length) . "\" />";
		echo "</div></fieldset>\n";
	}
	
	echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
	echo "<input type='submit' value='" . lang('Select') . "' />";
	echo "</div></fieldset>\n";
	echo "</form>\n";
	
	$query = "SELECT " . ($select ? (count($group) < count($select) ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) : "*") . " $from";
	echo "<p><code class='jush-sql'>" . htmlspecialchars($query) . "</code> <a href='" . htmlspecialchars($SELF) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a></p>\n";
	
	$result = $dbh->query($query);
	if (!$result) {
		echo "<p class='error'>" . htmlspecialchars($dbh->error) . "</p>\n";
	} else {
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		if (!$result->num_rows) {
			echo "<p class='message'>" . lang('No rows.') . "</p>\n";
		} else {
			$foreign_keys = array();
			foreach (foreign_keys($_GET["select"]) as $foreign_key) {
				foreach ($foreign_key["source"] as $val) {
					$foreign_keys[$val][] = $foreign_key;
				}
			}
			
			echo "<table cellspacing='0' class='nowrap'>\n";
			for ($j=0; $row = $result->fetch_assoc(); $j++) {
				if (!$j) {
					echo '<thead><tr><td><input type="checkbox" id="all-page" onclick="form_check(this, /check/);" /></td>';
					foreach ($row as $key => $val) {
						echo '<th><a href="' . htmlspecialchars(remove_from_uri('(order|desc)[^=]*') . '&order%5B0%5D=' . urlencode($key) . ($_GET["order"] == array($key) && !$_GET["desc"][0] ? '&desc%5B0%5D=1' : '')) . '">' . htmlspecialchars($key) . '</a></th>';
					}
					echo "</tr></thead>\n";
				}
				$unique_idf = implode('&amp;', unique_idf($row, $indexes));
				echo '<tr' . odd() . '><td><input type="checkbox" name="check[]" value="' . $unique_idf . '" onclick="this.form[\'all\'].checked = false; form_uncheck(\'all-page\');" />' . (count($select) == count($group) && $_GET["db"] != "information_schema" ? ' <a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '&amp;' . $unique_idf . '">' . lang('edit') . '</a></td>' : '');
				foreach ($row as $key => $val) {
					if (!isset($val)) {
						$val = "<i>NULL</i>";
					} elseif (preg_match('~blob|binary~', $fields[$key]["type"]) && !is_utf8($val)) {
						$val = '<a href="' . htmlspecialchars($SELF) . 'download=' . urlencode($_GET["select"]) . '&amp;field=' . urlencode($key) . '&amp;' . $unique_idf . '">' . lang('%d byte(s)', strlen($val)) . '</a>';
					} else {
						if (!strlen(trim($val))) {
							$val = "&nbsp;";
						} elseif (intval($text_length) > 0 && preg_match('~blob|text~', $fields[$key]["type"])) {
							$val = nl2br(shorten_utf8($val, intval($text_length)));
						} else {
							$val = nl2br(htmlspecialchars($val));
							if ($fields[$key]["type"] == "char") {
								$val = "<code>$val</code>";
							}
						}
						foreach ((array) $foreign_keys[$key] as $foreign_key) {
							if (count($foreign_keys[$key]) == 1 || count($foreign_key["source"]) == 1) {
								$val = "\">$val</a>";
								foreach ($foreign_key["source"] as $i => $source) {
									$val = "&amp;where%5B$i%5D%5Bcol%5D=" . urlencode($foreign_key["target"][$i]) . "&amp;where%5B$i%5D%5Bop%5D=%3D&amp;where%5B$i%5D%5Bval%5D=" . urlencode($row[$source]) . $val;
								}
								$val = '<a href="' . htmlspecialchars(strlen($foreign_key["db"]) ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($foreign_key["db"]), $SELF) : $SELF) . 'select=' . htmlspecialchars($foreign_key["table"]) . $val; // InnoDB supports non-UNIQUE keys
								break;
							}
						}
					}
					echo "<td>$val</td>";
				}
				echo "</tr>\n";
			}
			echo "</table>\n";
			
			echo "<p>";
			$found_rows = (intval($limit) ? $dbh->result($dbh->query(count($group) < count($select)
				? " SELECT FOUND_ROWS()" // space to allow mysql.trace_mode
				: "SELECT COUNT(*) FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")
			)) : $result->num_rows);
			if (intval($limit) && $found_rows > $limit) {
				$max_page = floor(($found_rows - 1) / $limit);
				echo lang('Page') . ":";
				print_page(0);
				if ($_GET["page"] > 3) {
					echo " ...";
				}
				for ($i = max(1, $_GET["page"] - 2); $i < min($max_page, $_GET["page"] + 3); $i++) {
					print_page($i);
				}
				if ($_GET["page"] + 3 < $max_page) {
					echo " ...";
				}
				print_page($max_page);
			}
			echo " (" . lang('%d row(s)', $found_rows) . ') <label><input type="checkbox" name="all" value="1" />' . lang('whole result') . "</label></p>\n";
			
			echo ($_GET["db"] != "information_schema" ? "<fieldset><legend>" . lang('Edit') . "</legend><div><input type='submit' value='" . lang('Edit') . "' /> <input type='submit' name='clone' value='" . lang('Clone') . "' /> <input type='submit' name='delete' value='" . lang('Delete') . "'$confirm /></div></fieldset>\n" : "");
			echo "<fieldset><legend>" . lang('Export') . "</legend><div>$dump_output $dump_format <input type='submit' name='export' value='" . lang('Export') . "' /></div></fieldset>\n";
		}
		$result->free();
		echo "<fieldset><legend>" . lang('CSV Import') . "</legend><div><input type='hidden' name='token' value='$token' /><input type='file' name='csv_file' /> <input type='submit' name='import' value='" . lang('Import') . "' /></div></fieldset>\n";
		echo "</form>\n";
	}
}
