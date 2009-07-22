<?php
$functions = array("char_length", "from_unixtime", "hex", "lower", "round", "sec_to_time", "time_to_sec", "unix_timestamp", "upper");
$grouping = array("avg", "count", "distinct", "group_concat", "max", "min", "sum"); // distinct is short for COUNT(DISTINCT)
$table_status = table_status($_GET["select"]);
$indexes = indexes($_GET["select"]);
$primary = null; // empty array means that all primary fields are selected
foreach ($indexes as $index) {
	if ($index["type"] == "PRIMARY") {
		$primary = array_flip($index["columns"]);
	}
}
$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL");
if (eregi('^(MyISAM|Maria)$', $table_status["Engine"])) {
	$operators[] = "AGAINST";
}
$fields = fields($_GET["select"]);
$rights = array(); // privilege => 0
$columns = array(); // selectable columns
unset($text_length);
foreach ($fields as $key => $field) {
	$name = adminer_field_name($fields, $key);
	if (isset($field["privileges"]["select"]) && strlen($name)) {
		$columns[$key] = html_entity_decode(strip_tags($name)); //! numeric $key is problematic in optionlist()
		if (ereg('text|blob', $field["type"])) {
			$text_length = (isset($_GET["text_length"]) ? $_GET["text_length"] : "100");
		}
		if (!$_GET["columns"]) {
			unset($primary[$key]);
		}
	}
	$rights += $field["privileges"];
}

$select = array(); // select expressions, empty for *
$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
foreach ((array) $_GET["columns"] as $key => $val) {
	if ($val["fun"] == "count" || (isset($columns[$val["col"]]) && (!$val["fun"] || in_array($val["fun"], $functions) || in_array($val["fun"], $grouping)))) {
		$select[$key] = (isset($columns[$val["col"]]) ? ($val["fun"] ? ($val["fun"] == "distinct" ? "COUNT(DISTINCT " : strtoupper("$val[fun](")) . idf_escape($val["col"]) . ")" : idf_escape($val["col"])) : "COUNT(*)");
		if (!in_array($val["fun"], $grouping)) {
			$group[] = $select[$key];
		}
		if (!$val["fun"]) {
			unset($primary[$val["col"]]);
		}
	}
}

$where = array(); // where expressions - will be joined by AND
foreach ($indexes as $i => $index) {
	if ($index["type"] == "FULLTEXT" && strlen($_GET["fulltext"][$i])) {
		$where[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . $dbh->quote($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
	}
}
foreach ((array) $_GET["where"] as $val) {
	if (strlen("$val[col]$val[val]") && in_array($val["op"], $operators)) {
		if ($val["op"] == "AGAINST") {
			$where[] = "MATCH (" . idf_escape($val["col"]) . ") AGAINST (" . $dbh->quote($val["val"]) . " IN BOOLEAN MODE)";
		} else {
			$in = process_length($val["val"]);
			$cond = " $val[op]" . (ereg('NULL$', $val["op"]) ? "" : (ereg('IN$', $val["op"]) ? " (" . (strlen($in) ? $in : "NULL") . ")" : " " . $dbh->quote($val["val"])));
			if (strlen($val["col"])) {
				$where[] = idf_escape($val["col"]) . $cond;
			} else {
				// find anywhere
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

$order = array(); // order expressions - will be joined by comma
foreach ((array) $_GET["order"] as $key => $val) {
	if (isset($columns[$val]) || in_array($val, $select, true)) {
		$order[] = idf_escape($val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
	}
}

$limit = (isset($_GET["limit"]) ? $_GET["limit"] : "30");
$from = ($select ? implode(", ", $select) : "*") . " FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "");
$group_by = ($group && count($group) < count($select) ? " GROUP BY " . implode(", ", $group) : "") . ($order ? " ORDER BY " . implode(", ", $order) : "");

if ($_POST && !$error) {
	$where_check = "(" . implode(") OR (", array_map('where_check', (array) $_POST["check"])) . ")";
	if ($_POST["export"]) {
		dump_headers($_GET["select"]);
		dump_table($_GET["select"], "");
		if (!is_array($_POST["check"]) || $primary === array()) {
			dump_data($_GET["select"], "INSERT", "SELECT $from" . (is_array($_POST["check"]) ? ($where ? " AND " : " WHERE ") . "($where_check)" : "") . $group_by);
		} else {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT $from " . ($where ? "AND " : "WHERE ") . where_check($val) . $group_by . " LIMIT 1)";
			}
			dump_data($_GET["select"], "INSERT", implode(" UNION ALL ", $union));
		}
		exit;
	}
	if ($_POST["email"]) {
		$sent = 0;
		if ($_POST["all"] || $_POST["check"]) {
			$field = idf_escape($_POST["email_field"]);
			$result = $dbh->query("SELECT DISTINCT $field FROM " . idf_escape($_GET["select"]) . " WHERE $field IS NOT NULL AND $field != ''" . ($where ? " AND " . implode(" AND ", $where) : "") . ($_POST["all"] ? "" : " AND ($where_check)"));
			while ($row = $result->fetch_row()) {
				$sent += mail($row[0], email_header($_POST["email_subject"]), $_POST["email_message"], "MIME-Version: 1.0\nContent-Type: text/plain; charset=utf-8\nContent-Transfer-Encoding: 8bit" . ($_POST["email_from"] ? "\nFrom: " . email_header($_POST["email_from"]) : ""));
			}
			$result->free();
		}
		redirect(remove_from_uri(), lang('%d e-mail(s) have been sent.', $sent));
	} elseif (!$_POST["import"]) { // edit
		$result = true;
		$affected = 0;
		$command = ($_POST["delete"] ? ($_POST["all"] && !$where ? "TRUNCATE " : "DELETE FROM ") : ($_POST["clone"] ? "INSERT INTO " : "UPDATE ")) . idf_escape($_GET["select"]);
		if (!$_POST["delete"]) {
			$set = array();
			foreach ($columns as $name => $val) { //! should check also for edit or insert privileges
				$val = process_input($name, $fields[$name]);
				if ($_POST["clone"]) {
					$set[] = ($val !== false ? $val : idf_escape($name));
				} elseif ($val !== false) {
					$set[] = idf_escape($name) . " = $val";
				}
			}
			$command .= ($_POST["clone"] ? "\nSELECT " . implode(", ", $set) . "\nFROM " . idf_escape($_GET["select"]) : " SET\n" . implode(",\n", $set));
		}
		if ($_POST["delete"] || $set) {
			if ($_POST["all"] || ($primary === array() && $_POST["check"])) {
				$result = queries($command . ($_POST["all"] ? ($where ? "\nWHERE " . implode(" AND ", $where) : "") : "\nWHERE $where_check"));
				$affected = $dbh->affected_rows;
			} else {
				foreach ((array) $_POST["check"] as $val) {
					// where is not unique so OR can't be used
					$result = queries($command . "\nWHERE " . where_check($val) . "\nLIMIT 1");
					if (!$result) {
						break;
					}
					$affected += $dbh->affected_rows;
				}
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
				// first row corresponds to column names - use it for table structure
				$cols = " (" . implode(", ", array_map('idf_escape', $matches2[1])) . ")";
			} else {
				foreach ($matches2[1] as $col) {
					$row[] = (!strlen($col) ? "NULL" : $dbh->quote(str_replace('""', '"', preg_replace('~^"|"$~', '', $col))));
				}
				$rows[] = "\n(" . implode(", ", $row) . ")";
			}
		}
		$result = queries("INSERT INTO " . idf_escape($_GET["select"]) . "$cols VALUES" . implode(",", $rows));
		query_redirect(queries(), remove_from_uri("page"), lang('%d row(s) has been imported.', $dbh->affected_rows), $result, false, !$result);
	} else {
		$error = upload_error($file);
	}
}

page_header(lang('Select') . ": " . adminer_table_name($table_status), $error);

echo "<p>";
if (isset($rights["insert"])) {
	//! pass search values forth and back
	echo '<a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '">' . lang('New item') . '</a> ';
}
echo adminer_select_links($table_status);

if (!$columns) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "" : ": " . htmlspecialchars($dbh->error)) . ".\n";
} else {
	echo "<form action='' id='form'>\n";
	echo '<fieldset><legend><a href="#fieldset-select" onclick="return !toggle(\'fieldset-select\');">' . lang('Select') . "</a></legend><div id='fieldset-select'" . ($select ? "" : " class='hidden'") . ">\n";
	if (strlen($_GET["server"])) {
		echo '<input type="hidden" name="server" value="' . htmlspecialchars($_GET["server"]) . '">';
	}
	echo '<input type="hidden" name="db" value="' . htmlspecialchars($_GET["db"]) . '">';
	echo '<input type="hidden" name="select" value="' . htmlspecialchars($_GET["select"]) . '">';
	echo "\n";
	$i = 0;
	$fun_group = array(lang('Functions') => $functions, lang('Aggregation') => $grouping);
	foreach ($select as $key => $val) {
		$val = $_GET["columns"][$key];
		echo "<div><select name='columns[$i][fun]'><option>" . optionlist($fun_group, $val["fun"]) . "</select>";
		echo "<select name='columns[$i][col]'><option>" . optionlist($columns, $val["col"]) . "</select></div>\n";
		$i++;
	}
	echo "<div><select name='columns[$i][fun]' onchange='this.nextSibling.onchange();'><option>" . optionlist($fun_group) . "</select>";
	echo "<select name='columns[$i][col]' onchange='select_add_row(this);'><option>" . optionlist($columns) . "</select></div>\n";
	echo "</div></fieldset>\n";
	
	echo '<fieldset><legend><a href="#fieldset-search" onclick="return !toggle(\'fieldset-search\');">' . lang('Search') . "</a></legend><div id='fieldset-search'" . ($where ? "" : " class='hidden'") . ">\n";
	foreach ($indexes as $i => $index) {
		if ($index["type"] == "FULLTEXT") {
			echo "(<i>" . implode("</i>, <i>", array_map('htmlspecialchars', $index["columns"])) . "</i>) AGAINST";
			echo ' <input name="fulltext[' . $i . ']" value="' . htmlspecialchars($_GET["fulltext"][$i]) . '">';
			echo "<label><input type='checkbox' name='boolean[$i]' value='1'" . (isset($_GET["boolean"][$i]) ? " checked='checked'" : "") . ">" . lang('BOOL') . "</label>";
			echo "<br>\n";
		}
	}
	$i = 0;
	foreach ((array) $_GET["where"] as $val) {
		if (strlen("$val[col]$val[val]") && in_array($val["op"], $operators)) {
			echo "<div><select name='where[$i][col]'><option value=''>" . lang('(anywhere)') . optionlist($columns, $val["col"]) . "</select>";
			echo "<select name='where[$i][op]'>" . optionlist($operators, $val["op"]) . "</select>";
			echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . "\"></div>\n";
			$i++;
		}
	}
	echo "<div><select name='where[$i][col]' onchange='select_add_row(this);'><option value=''>" . lang('(anywhere)') . optionlist($columns) . "</select>";
	echo "<select name='where[$i][op]'>" . optionlist($operators) . "</select>";
	echo "<input name='where[$i][val]'></div>\n";
	echo "</div></fieldset>\n";
	
	echo '<fieldset><legend><a href="#fieldset-sort" onclick="return !toggle(\'fieldset-sort\');">' . lang('Sort') . "</a></legend><div id='fieldset-sort'" . (count($order) > 1 ? "" : " class='hidden'") . ">\n";
	$i = 0;
	foreach ((array) $_GET["order"] as $key => $val) {
		if (isset($columns[$val])) {
			echo "<div><select name='order[$i]'><option>" . optionlist($columns, $val) . "</select>";
			echo "<label><input type='checkbox' name='desc[$i]' value='1'" . (isset($_GET["desc"][$key]) ? " checked='checked'" : "") . ">" . lang('descending') . "</label></div>\n";
			$i++;
		}
	}
	echo "<div><select name='order[$i]' onchange='select_add_row(this);'><option>" . optionlist($columns) . "</select>";
	echo "<label><input type='checkbox' name='desc[$i]' value='1'>" . lang('descending') . "</label></div>\n";
	echo "</div></fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
	echo "<input name='limit' size='3' value=\"" . htmlspecialchars($limit) . "\">";
	echo "</div></fieldset>\n";
	
	if (isset($text_length)) {
		echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
		echo "<input name='text_length' size='3' value=\"" . htmlspecialchars($text_length) . "\">";
		echo "</div></fieldset>\n";
	}
	
	echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
	echo "<input type='submit' value='" . lang('Select') . "'>";
	echo "</div></fieldset>\n";
	echo "</form>\n";
	
	$query = "SELECT " . (count($group) < count($select) ? "SQL_CALC_FOUND_ROWS " : "") . $from . $group_by . (strlen($limit) ? " LIMIT " . intval($limit) . (intval($_GET["page"]) ? " OFFSET " . ($limit * $_GET["page"]) : "") : "");
	echo adminer_select_query($query);
	
	$result = $dbh->query($query);
	if (!$result) {
		echo "<p class='error'>" . htmlspecialchars($dbh->error) . "\n";
	} else {
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		if (!$result->num_rows) {
			echo "<p class='message'>" . lang('No rows.') . "\n";
		} else {
			$rows = array();
			while ($row = $result->fetch_assoc()) {
				$rows[] = $row;
			}
			$result->free();
			// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
			$found_rows = (intval($limit) && count($group) < count($select)
				? $dbh->result($dbh->query(" SELECT FOUND_ROWS()")) // space to allow mysql.trace_mode
				: count($rows)
			);
			
			$foreign_keys = array();
			foreach (foreign_keys($_GET["select"]) as $foreign_key) {
				foreach ($foreign_key["source"] as $val) {
					$foreign_keys[$val][] = $foreign_key;
				}
			}
			$descriptions = adminer_row_descriptions($rows, $foreign_keys);
			
			$backward_keys = adminer_backward_keys($_GET["select"]);
			$table_names = array_keys($backward_keys);
			if ($table_names) {
				$table_names = array_combine($table_names, array_map('adminer_table_name', array_map('table_status', $table_names)));
			}
			
			echo "<table cellspacing='0' class='nowrap'>\n";
			echo "<thead><tr><td><input type='checkbox' id='all-page' onclick='form_check(this, /check/);'>";
			foreach ($rows[0] as $key => $val) {
				$name = adminer_field_name($fields, $key);
				if (strlen($name)) {
					echo '<th><a href="' . htmlspecialchars(remove_from_uri('(order|desc)[^=]*') . '&order%5B0%5D=' . urlencode($key) . ($_GET["order"] == array($key) && !$_GET["desc"][0] ? '&desc%5B0%5D=1' : '')) . "\">$name</a>";
				}
			}
			echo ($backward_keys ? "<th>" . lang('Relations') : "") . "</thead>\n";
			foreach ($descriptions as $n => $row) {
				$unique_idf = implode('&amp;', unique_idf($row, $indexes)); //! don't use aggregation functions
				echo '<tr' . odd() . '><td><input type="checkbox" name="check[]" value="' . $unique_idf . '" onclick="this.form[\'all\'].checked = false; form_uncheck(\'all-page\');">' . (count($select) != count($group) || information_schema($_GET["db"]) ? '' : ' <a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '&amp;' . $unique_idf . '">' . lang('edit') . '</a>');
				foreach ($row as $key => $val) {
					if (strlen(adminer_field_name($fields, $key))) {
						if (strlen($val) && (!isset($email_fields[$key]) || $email_fields[$key])) {
							$email_fields[$key] = is_email($val); //! filled e-mails may be contained on other pages
						}
						$link = "";
						if (!isset($val)) {
							$val = "<i>NULL</i>";
						} elseif (ereg('blob|binary', $fields[$key]["type"]) && !is_utf8($val)) { //! download link may be printed even with is_utf8
							$link = htmlspecialchars($SELF . 'download=' . urlencode($_GET["select"]) . '&field=' . urlencode($key) . '&') . $unique_idf;
							$val = lang('%d byte(s)', strlen($val));
						} else {
							if (!strlen(trim($val, " \t"))) {
								$val = "&nbsp;";
							} elseif (intval($text_length) > 0 && ereg('blob|text', $fields[$key]["type"])) {
								$val = nl2br(shorten_utf8($val, intval($text_length))); // usage of LEFT() would reduce traffic but complicates query
							} else {
								$val = nl2br(htmlspecialchars($val));
								if ($fields[$key]["type"] == "char") {
									$val = "<code>$val</code>";
								}
							}
							
							// link related items
							foreach ((array) $foreign_keys[$key] as $foreign_key) {
								if (count($foreign_keys[$key]) == 1 || count($foreign_key["source"]) == 1) {
									foreach ($foreign_key["source"] as $i => $source) {
										$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
									}
									$link = htmlspecialchars((strlen($foreign_key["db"]) ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($foreign_key["db"]), $SELF) : $SELF) . 'select=' . urlencode($foreign_key["table"])) . $link; // InnoDB supports non-UNIQUE keys
									break;
								}
							}
						}
						$val = adminer_select_val($val, $link);
						echo "<td>$val";
					}
				}
				if ($backward_keys) {
					echo "<td>";
					foreach ($backward_keys as $table => $keys) {
						foreach ($keys as $columns) {
							echo ' <a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($table);
							$i = 0;
							foreach ($columns as $column => $val) {
								echo where_link($i, $column, $rows[$n][$val]);
								$i++;
							}
							echo "\">$table_names[$table]</a>";
						}
					}
				}
				echo "\n";
			}
			echo "</table>\n";
			
			if (intval($limit) && count($group) >= count($select)) {
				// slow with big tables
				ob_flush();
				flush();
				$found_rows = $dbh->result($dbh->query("SELECT COUNT(*) FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")));
			}
			echo "<p>";
			if (intval($limit) && $found_rows > $limit) {
				// display first, previous 3, next 3 and last page
				$max_page = floor(($found_rows - 1) / $limit);
				echo lang('Page') . ":" . pagination(0) . ($_GET["page"] > 3 ? " ..." : "");
				for ($i = max(1, $_GET["page"] - 2); $i < min($max_page, $_GET["page"] + 3); $i++) {
					echo pagination($i);
				}
				echo ($_GET["page"] + 3 < $max_page ? " ..." : "") . pagination($max_page);
			}
			echo " (" . lang('%d row(s)', $found_rows) . ') <label><input type="checkbox" name="all" value="1">' . lang('whole result') . "</label>\n";
			
			echo (information_schema($_GET["db"]) ? "" : "<fieldset><legend>" . lang('Edit') . "</legend><div><input type='submit' name='edit' value='" . lang('Edit') . "'> <input type='submit' name='clone' value='" . lang('Clone') . "'> <input type='submit' name='delete' value='" . lang('Delete') . "'$confirm></div></fieldset>\n");
			echo "<fieldset><legend>" . lang('Export') . "</legend><div>$dump_output $dump_format <input type='submit' name='export' value='" . lang('Export') . "'></div></fieldset>\n";
		}
		echo "<fieldset><legend>" . lang('CSV Import') . "</legend><div><input type='hidden' name='token' value='$token'><input type='file' name='csv_file'> <input type='submit' name='import' value='" . lang('Import') . "'></div></fieldset>\n";
		
		//! Editor only
		$email_fields = array_filter($email_fields);
		if ($email_fields) {
			echo '<fieldset><legend><a href="#fieldset-email" onclick="return !toggle(\'fieldset-email\');">' . lang('E-mail') . "</a></legend><div id='fieldset-email' class='hidden'>\n";
			echo "<p>" . lang('From') . ": <input name='email_from'>\n";
			echo lang('Subject') . ": <input name='email_subject'>\n";
			echo "<p><textarea name='email_message' rows='15' cols='60'></textarea>\n";
			echo (count($email_fields) == 1 ? '<input type="hidden" name="email_field" value="' . htmlspecialchars(key($email_fields)) . '">' : '<select name="email_field">' . optionlist(array_keys($email_fields)) . '</select>');
			echo "<input type='submit' name='email' value='" . lang('Send') . "'$confirm>\n";
			echo "</div></fieldset>\n";
		}
		
		echo "</form>\n";
	}
}
