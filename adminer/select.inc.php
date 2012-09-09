<?php
$TABLE = $_GET["select"];
$table_status = table_status($TABLE);
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$foreign_keys = column_foreign_keys($TABLE);
$oid = "";
if ($table_status["Oid"] == "t") {
	$oid = ($jush == "sqlite" ? "rowid" : "oid");
	$indexes[] = array("type" => "PRIMARY", "columns" => array($oid));
}
parse_str($_COOKIE["adminer_import"], $adminer_import);

$rights = array(); // privilege => 0
$columns = array(); // selectable columns
$text_length = null;
foreach ($fields as $key => $field) {
	$name = $adminer->fieldName($field);
	if (isset($field["privileges"]["select"]) && $name != "") {
		$columns[$key] = html_entity_decode(strip_tags($name));
		if (ereg('text|lob|geometry|point|linestring|polygon', $field["type"])) {
			$text_length = $adminer->selectLengthProcess();
		}
	}
	$rights += $field["privileges"];
}

list($select, $group) = $adminer->selectColumnsProcess($columns, $indexes);
$is_group = count($group) < count($select);
$where = $adminer->selectSearchProcess($fields, $indexes);
$order = $adminer->selectOrderProcess($fields, $indexes);
$limit = $adminer->selectLimitProcess();
$from = ($select ? implode(", ", $select) : "*" . ($oid ? ", $oid" : ""));
if ($jush == "sql") {
	foreach ($columns as $key => $val) {
		$as = convert_field($fields[$key]);
		if ($as) {
			$from .= ", $as AS " . idf_escape($key);
		}
	}
}
$from .= "\nFROM " . table($TABLE);
$group_by = ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : "");

if ($_GET["val"] && is_ajax()) {
	header("Content-Type: text/plain; charset=utf-8");
	foreach ($_GET["val"] as $unique_idf => $row) {
		$as = convert_field($fields[key($row)]);
		echo $connection->result("SELECT" . limit(($as ? $as : idf_escape(key($row))) . " FROM " . table($TABLE), " WHERE " . where_check($unique_idf) . ($where ? " AND " . implode(" AND ", $where) : "") . ($order ? " ORDER BY " . implode(", ", $order) : ""), 1));
	}
	exit;
}

if ($_POST && !$error) {
	$where_check = "(" . implode(") OR (", array_map('where_check', (array) $_POST["check"])) . ")";
	$primary = $unselected = null;
	foreach ($indexes as $index) {
		if ($index["type"] == "PRIMARY") {
			$primary = array_flip($index["columns"]);
			$unselected = ($select ? $primary : array());
			break;
		}
	}
	foreach ((array) $unselected as $key => $val) {
		if (in_array(idf_escape($key), $select)) {
			unset($unselected[$key]);
		}
	}
	if ($_POST["export"]) {
		cookie("adminer_import", "output=" . urlencode($_POST["output"]) . "&format=" . urlencode($_POST["format"]));
		dump_headers($TABLE);
		$adminer->dumpTable($TABLE, "");
		if (!is_array($_POST["check"]) || $unselected === array()) {
			$where2 = $where;
			if (is_array($_POST["check"])) {
				$where2[] = "($where_check)";
			}
			$query = "SELECT $from" . ($where2 ? "\nWHERE " . implode(" AND ", $where2) : "") . $group_by;
		} else {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT" . limit($from, "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val) . $group_by, 1) . ")";
			}
			$query = implode(" UNION ALL ", $union);
		}
		$adminer->dumpData($TABLE, "table", $query);
		exit;
	}
	if (!$adminer->selectEmailProcess($where, $foreign_keys)) {
		if ($_POST["save"] || $_POST["delete"]) { // edit
			$result = true;
			$affected = 0;
			$query = table($TABLE);
			$set = array();
			if (!$_POST["delete"]) {
				foreach ($columns as $name => $val) { //! should check also for edit or insert privileges
					$val = process_input($fields[$name]);
					if ($val !== null) {
						if ($_POST["clone"]) {
							$set[idf_escape($name)] = ($val !== false ? $val : idf_escape($name));
						} elseif ($val !== false) {
							$set[] = idf_escape($name) . " = $val";
						}
					}
				}
				$query .= ($_POST["clone"] ? " (" . implode(", ", array_keys($set)) . ")\nSELECT " . implode(", ", $set) . "\nFROM " . table($TABLE) : " SET\n" . implode(",\n", $set));
			}
			if ($_POST["delete"] || $set) {
				$command = "UPDATE";
				if ($_POST["delete"]) {
					$command = "DELETE";
					$query = "FROM $query";
				}
				if ($_POST["clone"]) {
					$command = "INSERT";
					$query = "INTO $query";
				}
				if ($_POST["all"] || ($unselected === array() && $_POST["check"]) || $is_group) {
					$result = queries("$command $query" . ($_POST["all"] ? ($where ? "\nWHERE " . implode(" AND ", $where) : "") : "\nWHERE $where_check"));
					$affected = $connection->affected_rows;
				} else {
					foreach ((array) $_POST["check"] as $val) {
						// where is not unique so OR can't be used
						$result = queries($command . limit1($query, "\nWHERE " . where_check($val)));
						if (!$result) {
							break;
						}
						$affected += $connection->affected_rows;
					}
				}
			}
			$message = lang('%d item(s) have been affected.', $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id();
				if ($last_id) {
					$message = lang('Item%s has been inserted.', " $last_id");
				}
			}
			queries_redirect(remove_from_uri("page"), $message, $result);
			//! display edit page in case of an error
		} elseif (!$_POST["import"]) { // modify
			if (!$_POST["val"]) {
				$error = lang('Double click on a value to modify it.');
			} else {
				$result = true;
				$affected = 0;
				foreach ($_POST["val"] as $unique_idf => $row) {
					$set = array();
					foreach ($row as $key => $val) {
						$key = bracket_escape($key, 1); // 1 - back
						$set[] = idf_escape($key) . " = " . (ereg('char|text', $fields[$key]["type"]) || $val != "" ? $adminer->processInput($fields[$key], $val) : "NULL");
					}
					$query = table($TABLE) . " SET " . implode(", ", $set);
					$where2 = " WHERE " . where_check($unique_idf) . ($where ? " AND " . implode(" AND ", $where) : "");
					$result = queries("UPDATE" . ($is_group ? " $query$where2" : limit1($query, $where2))); // can change row on a different page without unique key
					if (!$result) {
						break;
					}
					$affected += $connection->affected_rows;
				}
				queries_redirect(remove_from_uri(), lang('%d item(s) have been affected.', $affected), $result);
			}
		} elseif (is_string($file = get_file("csv_file", true))) {
			//! character set
			cookie("adminer_import", "output=" . urlencode($adminer_import["output"]) . "&format=" . urlencode($_POST["separator"]));
			$result = true;
			$cols = array_keys($fields);
			preg_match_all('~(?>"[^"]*"|[^"\\r\\n]+)+~', $file, $matches);
			$affected = count($matches[0]);
			begin();
			$separator = ($_POST["separator"] == "csv" ? "," : ($_POST["separator"] == "tsv" ? "\t" : ";"));
			foreach ($matches[0] as $key => $val) {
				preg_match_all("~((\"[^\"]*\")+|[^$separator]*)$separator~", $val . $separator, $matches2);
				if (!$key && !array_diff($matches2[1], $cols)) { //! doesn't work with column names containing ",\n
					// first row corresponds to column names - use it for table structure
					$cols = $matches2[1];
					$affected--;
				} else {
					$set = array();
					foreach ($matches2[1] as $i => $col) {
						$set[idf_escape($cols[$i])] = ($col == "" && $fields[$cols[$i]]["null"] ? "NULL" : q(str_replace('""', '"', preg_replace('~^"|"$~', '', $col))));
					}
					$result = insert_update($TABLE, $set, $primary);
					if (!$result) {
						break;
					}
				}
			}
			if ($result) {
				queries("COMMIT");
			}
			queries_redirect(remove_from_uri("page"), lang('%d row(s) have been imported.', $affected), $result);
			queries("ROLLBACK"); // after queries_redirect() to not overwrite error
		} else {
			$error = upload_error($file);
		}
	}
}

$table_name = $adminer->tableName($table_status);
if (is_ajax()) {
	// needs to send headers
	ob_start();
}
page_header(lang('Select') . ": $table_name", $error);

$set = null;
if (isset($rights["insert"])) {
	$set = "";
	foreach ((array) $_GET["where"] as $val) {
		if (count($foreign_keys[$val["col"]]) == 1 && ($val["op"] == "="
			|| (!$val["op"] && !ereg('[_%]', $val["val"])) // LIKE in Editor
		)) {
			$set .= "&set" . urlencode("[" . bracket_escape($val["col"]) . "]") . "=" . urlencode($val["val"]);
		}
	}
}
$adminer->selectLinks($table_status, $set);

if (!$columns) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "." : ": " . error()) . "\n";
} else {
	echo "<form action='' id='form'>\n";
	echo "<div style='display: none;'>";
	hidden_fields_get();
	echo (DB != "" ? '<input type="hidden" name="db" value="' . h(DB) . '">' . (isset($_GET["ns"]) ? '<input type="hidden" name="ns" value="' . h($_GET["ns"]) . '">' : "") : ""); // not used in Editor
	echo '<input type="hidden" name="select" value="' . h($TABLE) . '">';
	echo "</div>\n";
	$adminer->selectColumnsPrint($select, $columns);
	$adminer->selectSearchPrint($where, $columns, $indexes);
	$adminer->selectOrderPrint($order, $columns, $indexes);
	$adminer->selectLimitPrint($limit);
	$adminer->selectLengthPrint($text_length);
	$adminer->selectActionPrint($indexes);
	echo "</form>\n";
	
	$page = $_GET["page"];
	if ($page == "last") {
		$found_rows = $connection->result("SELECT COUNT(*) FROM " . table($TABLE) . ($where ? " WHERE " . implode(" AND ", $where) : ""));
		$page = floor(max(0, $found_rows - 1) / $limit);
	}

	$query = $adminer->selectQueryBuild($select, $where, $group, $order, $limit, $page);
	if (!$query) {
		$query = "SELECT" . limit(
			(+$limit && $group && $is_group && $jush == "sql" ? "SQL_CALC_FOUND_ROWS " : "") . $from,
			($where ? "\nWHERE " . implode(" AND ", $where) : "") . $group_by,
			($limit != "" ? +$limit : null),
			($page ? $limit * $page : 0),
			"\n"
		);
	}
	echo $adminer->selectQuery($query);
	
	$result = $connection->query($query);
	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		if ($jush == "mssql") {
			$result->seek($limit * $page);
		}
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			if ($page && $jush == "oracle") {
				unset($row["RNUM"]);
			}
			$rows[] = $row;
		}
		// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
		if ($_GET["page"] != "last") {
			$found_rows = (+$limit && $group && $is_group
				? ($jush == "sql" ? $connection->result(" SELECT FOUND_ROWS()") : $connection->result("SELECT COUNT(*) FROM ($query) x")) // space to allow mysql.trace_mode
				: count($rows)
			);
		}
		
		if (!$rows) {
			echo "<p class='message'>" . lang('No rows.') . "\n";
		} else {
			$backward_keys = $adminer->backwardKeys($TABLE, $table_name);
			
			echo "<table id='table' cellspacing='0' class='nowrap checkable' onclick='tableClick(event);' onkeydown='return editingKeydown(event);'>\n";
			echo "<thead><tr>" . (!$group && $select ? "" : "<td><input type='checkbox' id='all-page' onclick='formCheck(this, /check/);'> <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . lang('edit') . "</a>");
			$names = array();
			$functions = array();
			reset($select);
			$rank = 1;
			foreach ($rows[0] as $key => $val) {
				if ($key != $oid) {
					$val = $_GET["columns"][key($select)];
					$field = $fields[$select ? ($val ? $val["col"] : current($select)) : $key];
					$name = ($field ? $adminer->fieldName($field, $rank) : "*");
					if ($name != "") {
						$rank++;
						$names[$key] = $name;
						$column = idf_escape($key);
						$href = remove_from_uri('(order|desc)[^=]*|page') . '&order%5B0%5D=' . urlencode($key);
						$desc = "&desc%5B0%5D=1";
						echo '<th onmouseover="columnMouse(this);" onmouseout="columnMouse(this, \' hidden\');">';
						echo '<a href="' . h($href . ($order[0] == $column || $order[0] == $key || (!$order && $is_group && $group[0] == $column) ? $desc : '')) . '">'; // $order[0] == $key - COUNT(*)
						echo (!$select || $val ? apply_sql_function($val["fun"], $name) : h(current($select))) . "</a>"; //! columns looking like functions
						echo "<span class='column hidden'>";
						echo "<a href='" . h($href . $desc) . "' title='" . lang('descending') . "' class='text'> ↓</a>";
						if (!$val["fun"]) {
							echo '<a href="#fieldset-search" onclick="selectSearch(\'' . h(js_escape($key)) . '\'); return false;" title="' . lang('Search') . '" class="text jsonly"> =</a>';
						}
						echo "</span>";
					}
					$functions[$key] = $val["fun"];
					next($select);
				}
			}
			$lengths = array();
			if ($_GET["modify"]) {
				foreach ($rows as $row) {
					foreach ($row as $key => $val) {
						$lengths[$key] = max($lengths[$key], min(40, strlen(utf8_decode($val))));
					}
				}
			}
			echo ($backward_keys ? "<th>" . lang('Relations') : "") . "</thead>\n";
			if (is_ajax()) {
				if ($limit % 2 == 1 && $page % 2 == 1) {
					odd();
				}
				ob_end_clean();
			}
			foreach ($adminer->rowDescriptions($rows, $foreign_keys) as $n => $row) {
				$unique_array = unique_array($rows[$n], $indexes);
				$unique_idf = "";
				foreach ($unique_array as $key => $val) {
					$unique_idf .= "&" . ($val !== null ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val) : "null%5B%5D=" . urlencode($key));
				}
				echo "<tr" . odd() . ">" . (!$group && $select ? "" : "<td>" . checkbox("check[]", substr($unique_idf, 1), in_array(substr($unique_idf, 1), (array) $_POST["check"]), "", "this.form['all'].checked = false; formUncheck('all-page');") . ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "'>" . lang('edit') . "</a>"));
				foreach ($row as $key => $val) {
					if (isset($names[$key])) {
						$field = $fields[$key];
						if ($val != "" && (!isset($email_fields[$key]) || $email_fields[$key] != "")) {
							$email_fields[$key] = (is_mail($val) ? $names[$key] : ""); //! filled e-mails can be contained on other pages
						}
						$link = "";
						$val = $adminer->editVal($val, $field);
						if ($val !== null) {
							if (ereg('blob|bytea|raw|file', $field["type"]) && $val != "") {
								$link = h(ME . 'download=' . urlencode($TABLE) . '&field=' . urlencode($key) . $unique_idf);
							}
							if ($val === "") { // === - may be int
								$val = "&nbsp;";
							} elseif (is_utf8($val)) {
								if ($text_length != "" && ereg('text|lob|geometry|point|linestring|polygon', $field["type"])) {
									$val = shorten_utf8($val, max(0, +$text_length)); // usage of LEFT() would reduce traffic but complicate query - expected average speedup: .001 s VS .01 s on local network
								} else {
									$val = h($val);
								}
							}
							
							if (!$link) { // link related items
								foreach ((array) $foreign_keys[$key] as $foreign_key) {
									if (count($foreign_keys[$key]) == 1 || end($foreign_key["source"]) == $key) {
										$link = "";
										foreach ($foreign_key["source"] as $i => $source) {
											$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
										}
										$link = h(($foreign_key["db"] != "" ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($foreign_key["db"]), ME) : ME) . 'select=' . urlencode($foreign_key["table"]) . $link); // InnoDB supports non-UNIQUE keys
										if (count($foreign_key["source"]) == 1) {
											break;
										}
									}
								}
							}
							if ($key == "COUNT(*)") { //! columns looking like functions
								$link = h(ME . "select=" . urlencode($TABLE));
								$i = 0;
								foreach ((array) $_GET["where"] as $v) {
									if (!array_key_exists($v["col"], $unique_array)) {
										$link .= h(where_link($i++, $v["col"], $v["val"], $v["op"]));
									}
								}
								foreach ($unique_array as $k => $v) {
									$link .= h(where_link($i++, $k, $v));
								}
							}
						}
						if (!$link) {
							if (is_mail($val)) {
								$link = "mailto:$val";
							}
							if ($protocol = is_url($row[$key])) {
								$link = ($protocol == "http" && $HTTPS
									? $row[$key] // HTTP links from HTTPS pages don't receive Referer automatically
									: "$protocol://www.adminer.org/redirect/?url=" . urlencode($row[$key]) // intermediate page to hide Referer, may be changed to rel="noreferrer" in HTML5
								);
							}
						}
						$id = h("val[$unique_idf][" . bracket_escape($key) . "]");
						$value = $_POST["val"][$unique_idf][bracket_escape($key)];
						$h_value = h($value !== null ? $value : $row[$key]);
						$long = strpos($val, "<i>...</i>");
						$editable = is_utf8($val) && $rows[$n][$key] == $row[$key] && !$functions[$key];
						$text = ereg('text|lob', $field["type"]);
						echo (($_GET["modify"] && $editable) || $value !== null
							? "<td>" . ($text ? "<textarea name='$id' cols='30' rows='" . (substr_count($row[$key], "\n") + 1) . "'>$h_value</textarea>" : "<input name='$id' value='$h_value' size='$lengths[$key]'>")
							: "<td id='$id' ondblclick=\"" . ($editable ? "selectDblClick(this, event" . ($long ? ", 2" : ($text ? ", 1" : "")) . ")" : "alert('" . h(lang('Use edit link to modify this value.')) . "')") . ";\">" . $adminer->selectVal($val, $link, $field)
						);
					}
				}
				if ($backward_keys) {
					echo "<td>";
				}
				$adminer->backwardKeysPrint($backward_keys, $rows[$n]);
				echo "</tr>\n"; // close to allow white-space: pre
			}
			if (is_ajax()) {
				exit;
			}
			echo "</table>\n";
			echo (!$group && $select ? "" : "<script type='text/javascript'>tableCheck();</script>\n");
		}
		
		if (($rows || $page) && !is_ajax()) {
			$exact_count = true;
			if ($_GET["page"] != "last" && +$limit && !$is_group && ($found_rows >= $limit || $page)) {
				$found_rows = found_rows($table_status, $where);
				if ($found_rows < max(1e4, 2 * ($page + 1) * $limit)) {
					// slow with big tables
					$found_rows = reset(slow_query("SELECT COUNT(*) FROM " . table($TABLE) . ($where ? " WHERE " . implode(" AND ", $where) : "")));
				} else {
					$exact_count = false;
				}
			}
			echo "<p class='pages'>";
			if (+$limit && ($found_rows === false || $found_rows > $limit)) {
				// display first, previous 4, next 4 and last page
				$max_page = ($found_rows === false
					? $page + (count($rows) >= $limit ? 2 : 1)
					: floor(($found_rows - 1) / $limit)
				);
				echo '<a href="' . h(remove_from_uri("page")) . "\" onclick=\"pageClick(this.href, +prompt('" . lang('Page') . "', '" . ($page + 1) . "'), event); return false;\">" . lang('Page') . "</a>:";
				echo pagination(0, $page) . ($page > 5 ? " ..." : "");
				for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
					echo pagination($i, $page);
				}
				echo ($page + 5 < $max_page ? " ..." : "") . ($exact_count && $found_rows !== false ? pagination($max_page, $page) : ' <a href="' . h(remove_from_uri("page") . "&page=last") . '">' . lang('last') . "</a>");
			}
			echo ($found_rows !== false ? " (" . ($exact_count ? "" : "~ ") . lang('%d row(s)', $found_rows) . ")" : "");
			echo (+$limit && ($found_rows === false ? count($rows) + 1 : $found_rows - $page * $limit) > $limit ? ' <a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" onclick="return !selectLoadMore(this, ' . (+$limit) . ', \'' . lang('Loading') . '\');">' . lang('Load more data') . '</a>' : '');
			echo " " . checkbox("all", 1, 0, lang('whole result')) . "\n";
			
			if ($adminer->selectCommandPrint()) {
				?>
<fieldset><legend><?php echo lang('Edit'); ?></legend><div>
<input type="submit" value="<?php echo lang('Save'); ?>"<?php echo ($_GET["modify"] ? '' : ' title="' . lang('Double click on a value to modify it.') . '" class="jsonly"'); ?>>
<input type="submit" name="edit" value="<?php echo lang('Edit'); ?>">
<input type="submit" name="clone" value="<?php echo lang('Clone'); ?>">
<input type="submit" name="delete" value="<?php echo lang('Delete'); ?>" onclick="return confirm('<?php echo lang('Are you sure?'); ?> (' + (this.form['all'].checked ? <?php echo $found_rows; ?> : formChecked(this, /check/)) + ')');">
</div></fieldset>
<?php
			}
			$format = $adminer->dumpFormat();
			if ($format) {
				print_fieldset("export", lang('Export'));
				$output = $adminer->dumpOutput();
				echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
				echo html_select("format", $format, $adminer_import["format"]);
				echo " <input type='submit' name='export' value='" . lang('Export') . "'>\n";
				echo "</div></fieldset>\n";
			}
		}
		if ($adminer->selectImportPrint()) {
			print_fieldset("import", lang('Import'), !$rows);
			echo "<input type='file' name='csv_file'> ";
			echo html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"], 1); // 1 - select
			echo " <input type='submit' name='import' value='" . lang('Import') . "'>";
			echo "<input type='hidden' name='token' value='$token'>\n";
			echo "</div></fieldset>\n";
		}
		
		$adminer->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
		
		echo "</form>\n";
	}
}

if (is_ajax()) {
	ob_end_clean();
	exit;
}
