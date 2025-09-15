<?php
namespace Adminer;

$TABLE = $_GET["select"];
$table_status = table_status1($TABLE);
$indexes = indexes($TABLE);
$fields = fields($TABLE);
$foreign_keys = column_foreign_keys($TABLE);
$oid = $table_status["Oid"];
$adminer_import = get_settings("adminer_import");

$rights = array(); // privilege => 0
$columns = array(); // selectable columns
$search_columns = array(); // searchable columns
$order_columns = array(); // searchable columns
$text_length = "";
foreach ($fields as $key => $field) {
	$name = adminer()->fieldName($field);
	$name_plain = html_entity_decode(strip_tags($name), ENT_QUOTES);
	if (isset($field["privileges"]["select"]) && $name != "") {
		$columns[$key] = $name_plain;
		if (is_shortable($field)) {
			$text_length = adminer()->selectLengthProcess();
		}
	}
	if (isset($field["privileges"]["where"]) && $name != "") {
		$search_columns[$key] = $name_plain;
	}
	if (isset($field["privileges"]["order"]) && $name != "") {
		$order_columns[$key] = $name_plain;
	}
	$rights += $field["privileges"];
}

list($select, $group) = adminer()->selectColumnsProcess($columns, $indexes);
$select = array_unique($select);
$group = array_unique($group);
$is_group = count($group) < count($select);
$where = adminer()->selectSearchProcess($fields, $indexes);
$order = adminer()->selectOrderProcess($fields, $indexes);
$limit = adminer()->selectLimitProcess();

if ($_GET["val"] && is_ajax()) {
	header("Content-Type: text/plain; charset=utf-8");
	foreach ($_GET["val"] as $unique_idf => $row) {
		$as = convert_field($fields[key($row)]);
		$select = array($as ?: idf_escape(key($row)));
		$where[] = where_check($unique_idf, $fields);
		$return = driver()->select($TABLE, $select, $where, $select);
		if ($return) {
			echo first($return->fetch_row());
		}
	}
	exit;
}

$primary = $unselected = array();
foreach ($indexes as $index) {
	if ($index["type"] == "PRIMARY") {
		$primary = array_flip($index["columns"]);
		$unselected = ($select ? $primary : array());
		foreach ($unselected as $key => $val) {
			if (in_array(idf_escape($key), $select)) {
				unset($unselected[$key]);
			}
		}
		break;
	}
}
if ($oid && !$primary) {
	$primary = $unselected = array($oid => 0);
	$indexes[] = array("type" => "PRIMARY", "columns" => array($oid));
}

if ($_POST && !$error) {
	$where_check = $where;
	if (!$_POST["all"] && is_array($_POST["check"])) {
		$checks = array();
		foreach ($_POST["check"] as $check) {
			$checks[] = where_check($check, $fields);
		}
		$where_check[] = "((" . implode(") OR (", $checks) . "))";
	}
	$where_check = ($where_check ? "\nWHERE " . implode(" AND ", $where_check) : "");
	if ($_POST["export"]) {
		save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
		dump_headers($TABLE);
		adminer()->dumpTable($TABLE, "");
		$from = ($select ? implode(", ", $select) : "*")
			. convert_fields($columns, $fields, $select)
			. "\nFROM " . table($TABLE);
		$group_by = ($group && $is_group ? "\nGROUP BY " . implode(", ", $group) : "") . ($order ? "\nORDER BY " . implode(", ", $order) : "");
		$query = "SELECT $from$where_check$group_by";
		if (is_array($_POST["check"]) && !$primary) {
			$union = array();
			foreach ($_POST["check"] as $val) {
				// where is not unique so OR can't be used
				$union[] = "(SELECT" . limit($from, "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields) . $group_by, 1) . ")";
			}
			$query = implode(" UNION ALL ", $union);
		}
		adminer()->dumpData($TABLE, "table", $query);
		adminer()->dumpFooter();
		exit;
	}

	if (!adminer()->selectEmailProcess($where, $foreign_keys)) {
		if ($_POST["save"] || $_POST["delete"]) { // edit
			$result = true;
			$affected = 0;
			$set = array();
			if (!$_POST["delete"]) {
				foreach ($_POST["fields"] as $name => $val) {
					$val = process_input($fields[$name]);
					if ($val !== null && ($_POST["clone"] || $val !== false)) {
						$set[idf_escape($name)] = ($val !== false ? $val : idf_escape($name));
					}
				}
			}
			if ($_POST["delete"] || $set) {
				$query = ($_POST["clone"] ? "INTO " . table($TABLE) . " (" . implode(", ", array_keys($set)) . ")\nSELECT " . implode(", ", $set) . "\nFROM " . table($TABLE) : "");
				if ($_POST["all"] || ($primary && is_array($_POST["check"])) || $is_group) {
					$result = ($_POST["delete"]
						? driver()->delete($TABLE, $where_check)
						: ($_POST["clone"]
							? queries("INSERT $query$where_check" . driver()->insertReturning($TABLE))
							: driver()->update($TABLE, $set, $where_check)
						)
					);
					$affected = connection()->affected_rows;
					if (is_object($result)) { // PostgreSQL with RETURNING fills num_rows
						$affected += $result->num_rows;
					}
				} else {
					foreach ((array) $_POST["check"] as $val) {
						// where is not unique so OR can't be used
						$where2 = "\nWHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($val, $fields);
						$result = ($_POST["delete"]
							? driver()->delete($TABLE, $where2, 1)
							: ($_POST["clone"]
								? queries("INSERT" . limit1($TABLE, $query, $where2))
								: driver()->update($TABLE, $set, $where2, 1)
							)
						);
						if (!$result) {
							break;
						}
						$affected += connection()->affected_rows;
					}
				}
			}
			$message = lang('%d item(s) have been affected.', $affected);
			if ($_POST["clone"] && $result && $affected == 1) {
				$last_id = last_id($result);
				if ($last_id) {
					$message = lang('Item%s has been inserted.', " $last_id");
				}
			}
			queries_redirect(remove_from_uri($_POST["all"] && $_POST["delete"] ? "page" : ""), $message, $result);
			if (!$_POST["delete"]) {
				$post_fields = (array) $_POST["fields"];
				edit_form($TABLE, array_intersect_key($fields, $post_fields), $post_fields, !$_POST["clone"], $error);
				page_footer();
				exit;
			}

		} elseif (!$_POST["import"]) { // modify
			if (!$_POST["val"]) {
				$error = lang('Ctrl+click on a value to modify it.');
			} else {
				$result = true;
				$affected = 0;
				foreach ($_POST["val"] as $unique_idf => $row) {
					$set = array();
					foreach ($row as $key => $val) {
						$key = bracket_escape($key, true); // true - back
						$set[idf_escape($key)] = (preg_match('~char|text~', $fields[$key]["type"]) || $val != "" ? adminer()->processInput($fields[$key], $val) : "NULL");
					}
					$result = driver()->update(
						$TABLE,
						$set,
						" WHERE " . ($where ? implode(" AND ", $where) . " AND " : "") . where_check($unique_idf, $fields),
						($is_group || $primary ? 0 : 1),
						" "
					);
					if (!$result) {
						break;
					}
					$affected += connection()->affected_rows;
				}
				queries_redirect(remove_from_uri(), lang('%d item(s) have been affected.', $affected), $result);
			}

		} elseif (!is_string($file = get_file("csv_file", true))) {
			$error = upload_error($file);
		} elseif (!preg_match('~~u', $file)) {
			$error = lang('File must be in UTF-8 encoding.');
		} else {
			save_settings(array("output" => $adminer_import["output"], "format" => $_POST["separator"]), "adminer_import");
			$result = true;
			$cols = array_keys($fields);
			preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~', $file, $matches);
			$affected = count($matches[0]);
			driver()->begin();
			$separator = ($_POST["separator"] == "csv" ? "," : ($_POST["separator"] == "tsv" ? "\t" : ";"));
			$rows = array();
			foreach ($matches[0] as $key => $val) {
				preg_match_all("~((?>\"[^\"]*\")+|[^$separator]*)$separator~", $val . $separator, $matches2);
				if (!$key && !array_diff($matches2[1], $cols)) { //! doesn't work with column names containing ",\n
					// first row corresponds to column names - use it for table structure
					$cols = $matches2[1];
					$affected--;
				} else {
					$set = array();
					foreach ($matches2[1] as $i => $col) {
						$set[idf_escape($cols[$i])] = ($col == "" && $fields[$cols[$i]]["null"] ? "NULL" : q(preg_match('~^".*"$~s', $col) ? str_replace('""', '"', substr($col, 1, -1)) : $col));
					}
					$rows[] = $set;
				}
			}
			$result = (!$rows || driver()->insertUpdate($TABLE, $rows, $primary));
			if ($result) {
				driver()->commit();
			}
			queries_redirect(remove_from_uri("page"), lang('%d row(s) have been imported.', $affected), $result);
			driver()->rollback(); // after queries_redirect() to not overwrite error

		}
	}
}

$table_name = adminer()->tableName($table_status);
if (is_ajax()) {
	page_headers();
	ob_start();
} else {
	page_header(lang('Select') . ": $table_name", $error);
}

$set = null;
if (isset($rights["insert"]) || !support("table")) {
	$params = array();
	foreach ((array) $_GET["where"] as $val) {
		if (
			isset($foreign_keys[$val["col"]]) && count($foreign_keys[$val["col"]]) == 1
			&& ($val["op"] == "=" || (!$val["op"] && (is_array($val["val"]) || !preg_match('~[_%]~', $val["val"])))) // LIKE in Editor
		) {
			$params["set" . "[" . bracket_escape($val["col"]) . "]"] = $val["val"];
		}
	}

	$set = $params ? "&" . http_build_query($params) : "";
}
adminer()->selectLinks($table_status, $set);

if (!$columns && support("table")) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "." : ": " . error()) . "\n";
} else {
	echo "<form action='' id='form'>\n";
	echo "<div style='display: none;'>";
	hidden_fields_get();
	echo (DB != "" ? input_hidden("db", DB) . (isset($_GET["ns"]) ? input_hidden("ns", $_GET["ns"]) : "") : ""); // not used in Editor
	echo input_hidden("select", $TABLE);
	echo "</div>\n";
	adminer()->selectColumnsPrint($select, $columns);
	adminer()->selectSearchPrint($where, $search_columns, $indexes);
	adminer()->selectOrderPrint($order, $order_columns, $indexes);
	adminer()->selectLimitPrint($limit);
	adminer()->selectLengthPrint($text_length);
	adminer()->selectActionPrint($indexes);
	echo "</form>\n";

	$page = $_GET["page"];
	$found_rows = null;
	if ($page == "last") {
		$found_rows = get_val(count_rows($TABLE, $where, $is_group, $group));
		$page = floor(max(0, intval($found_rows) - 1) / $limit);
	}

	$select2 = $select;
	$group2 = $group;
	if (!$select2) {
		$select2[] = "*";
		$convert_fields = convert_fields($columns, $fields, $select);
		if ($convert_fields) {
			$select2[] = substr($convert_fields, 2);
		}
	}
	foreach ($select as $key => $val) {
		$field = $fields[idf_unescape($val)];
		if ($field && ($as = convert_field($field))) {
			$select2[$key] = "$as AS $val";
		}
	}
	if (!$is_group && $unselected) {
		foreach ($unselected as $key => $val) {
			$select2[] = idf_escape($key);
			if ($group2) {
				$group2[] = idf_escape($key);
			}
		}
	}
	$result = driver()->select($TABLE, $select2, $where, $group2, $order, $limit, $page, true);

	if (!$result) {
		echo "<p class='error'>" . error() . "\n";
	} else {
		if (JUSH == "mssql" && $page) {
			$result->seek($limit * $page);
		}
		$email_fields = array();
		echo "<form action='' method='post' enctype='multipart/form-data'>\n";
		$rows = array();
		while ($row = $result->fetch_assoc()) {
			if ($page && JUSH == "oracle") {
				unset($row["RNUM"]);
			}
			$rows[] = $row;
		}

		// use count($rows) without LIMIT, COUNT(*) without grouping, FOUND_ROWS otherwise (slowest)
		if ($_GET["page"] != "last" && $limit && $group && $is_group && JUSH == "sql") {
			$found_rows = get_val(" SELECT FOUND_ROWS()"); // space to allow mysql.trace_mode
		}

		if (!$rows) {
			echo "<p class='message'>" . lang('No rows.') . "\n";
		} else {
			$backward_keys = adminer()->backwardKeys($TABLE, $table_name);

			echo "<div class='scrollable'>";
			echo "<table id='table' class='nowrap checkable odds'>";
			echo script("mixin(qs('#table'), {onclick: tableClick, ondblclick: partialArg(tableClick, true), onkeydown: editingKeydown});");
			echo "<thead><tr>" . (!$group && $select
				? ""
				: "<td><input type='checkbox' id='all-page' class='jsonly'>" . script("qs('#all-page').onclick = partial(formCheck, /check/);", "")
					. " <a href='" . h($_GET["modify"] ? remove_from_uri("modify") : $_SERVER["REQUEST_URI"] . "&modify=1") . "'>" . lang('Modify') . "</a>");
			$names = array();
			$functions = array();
			reset($select);
			$rank = 1;
			foreach ($rows[0] as $key => $val) {
				if (!isset($unselected[$key])) {
					/** @var array{fun?:string, col?:string} */
					$val = idx($_GET["columns"], key($select)) ?: array();
					$field = $fields[$select ? ($val ? $val["col"] : current($select)) : $key];
					$name = ($field ? adminer()->fieldName($field, $rank) : ($val["fun"] ? "*" : h($key)));
					if ($name != "") {
						$rank++;
						$names[$key] = $name;
						$column = idf_escape($key);
						$href = remove_from_uri('(order|desc)[^=]*|page') . '&order%5B0%5D=' . urlencode($key);
						$desc = "&desc%5B0%5D=1";
						echo "<th id='th[" . h(bracket_escape($key)) . "]'>" . script("mixin(qsl('th'), {onmouseover: partial(columnMouse), onmouseout: partial(columnMouse, ' hidden')});", "");
						$fun = apply_sql_function($val["fun"], $name); //! columns looking like functions
						$sortable = isset($field["privileges"]["order"]) || $fun;
						echo ($sortable ? "<a href='" . h($href . ($order[0] == $column || $order[0] == $key ? $desc : '')) . "'>$fun</a>" : $fun); // $order[0] == $key - COUNT(*)
						echo "<span class='column hidden'>";
						if ($sortable) {
							echo "<a href='" . h($href . $desc) . "' title='" . lang('descending') . "' class='text'> ↓</a>";
						}
						if (!$val["fun"] && isset($field["privileges"]["where"])) {
							echo '<a href="#fieldset-search" title="' . lang('Search') . '" class="text jsonly"> =</a>';
							echo script("qsl('a').onclick = partial(selectSearch, '" . js_escape($key) . "');");
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
				ob_end_clean();
			}

			foreach (adminer()->rowDescriptions($rows, $foreign_keys) as $n => $row) {
				$unique_array = unique_array($rows[$n], $indexes);
				if (!$unique_array) {
					$unique_array = array();
					reset($select);
					foreach ($rows[$n] as $key => $val) {
						if (!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~', current($select))) {
							$unique_array[$key] = $val;
						}
						next($select);
					}
				}
				$unique_idf = "";
				foreach ($unique_array as $key => $val) {
					$field = (array) $fields[$key];
					if ((JUSH == "sql" || JUSH == "pgsql") && preg_match('~char|text|enum|set~', $field["type"]) && strlen($val) > 64) {
						$key = (strpos($key, '(') ? $key : idf_escape($key)); //! columns looking like functions
						$key = "MD5(" . (JUSH != 'sql' || preg_match("~^utf8~", $field["collation"]) ? $key : "CONVERT($key USING " . charset(connection()) . ")") . ")";
						$val = md5($val);
					}
					$unique_idf .= "&" . ($val !== null ? urlencode("where[" . bracket_escape($key) . "]") . "=" . urlencode($val === false ? "f" : $val) : "null%5B%5D=" . urlencode($key));
				}
				echo "<tr>" . (!$group && $select ? "" : "<td>"
					. checkbox("check[]", substr($unique_idf, 1), in_array(substr($unique_idf, 1), (array) $_POST["check"]))
					. ($is_group || information_schema(DB) ? "" : " <a href='" . h(ME . "edit=" . urlencode($TABLE) . $unique_idf) . "' class='edit'>" . lang('edit') . "</a>")
				);

				reset($select);
				foreach ($row as $key => $val) {
					if (isset($names[$key])) {
						$column = current($select);
						$field = (array) $fields[$key];
						$val = driver()->value($val, $field);
						if ($val != "" && (!isset($email_fields[$key]) || $email_fields[$key] != "")) {
							$email_fields[$key] = (is_mail($val) ? $names[$key] : ""); //! filled e-mails can be contained on other pages
						}

						$link = "";
						if (is_blob($field) && $val != "") {
							$link = ME . 'download=' . urlencode($TABLE) . '&field=' . urlencode($key) . $unique_idf;
						}
						if (!$link && $val !== null) { // link related items
							foreach ((array) $foreign_keys[$key] as $foreign_key) {
								if (count($foreign_keys[$key]) == 1 || end($foreign_key["source"]) == $key) {
									$link = "";
									foreach ($foreign_key["source"] as $i => $source) {
										$link .= where_link($i, $foreign_key["target"][$i], $rows[$n][$source]);
									}
									$link = ($foreign_key["db"] != "" ? preg_replace('~([?&]db=)[^&]+~', '\1' . urlencode($foreign_key["db"]), ME) : ME) . 'select=' . urlencode($foreign_key["table"]) . $link; // InnoDB supports non-UNIQUE keys
									if ($foreign_key["ns"]) {
										$link = preg_replace('~([?&]ns=)[^&]+~', '\1' . urlencode($foreign_key["ns"]), $link);
									}
									if (count($foreign_key["source"]) == 1) {
										break;
									}
								}
							}
						}
						if ($column == "COUNT(*)") {
							$link = ME . "select=" . urlencode($TABLE);
							$i = 0;
							foreach ((array) $_GET["where"] as $v) {
								if (!array_key_exists($v["col"], $unique_array)) {
									$link .= where_link($i++, $v["col"], $v["val"], $v["op"]);
								}
							}
							foreach ($unique_array as $k => $v) {
								$link .= where_link($i++, $k, $v);
							}
						}

						$html = select_value($val, $link, $field, $text_length);
						$id = h("val[$unique_idf][" . bracket_escape($key) . "]");
						$posted = idx(idx($_POST["val"], $unique_idf), bracket_escape($key));
						$editable = !is_array($row[$key]) && is_utf8($html) && $rows[$n][$key] == $row[$key] && !$functions[$key] && !$field["generated"];
						$type = (preg_match('~^(AVG|MIN|MAX)\((.+)\)~', $column, $match) ? $fields[idf_unescape($match[2])]["type"] : $field["type"]);
						$text = preg_match('~text|json|lob~', $type);
						$is_number = preg_match(number_type(), $type) || preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|TIME_TO_SEC|COUNT|SUM)\(~', $column);
						echo "<td id='$id'" . ($is_number && ($val === null || is_numeric(strip_tags($html)) || $type == "money") ? " class='number'" : "");
						if (($_GET["modify"] && $editable && $val !== null) || $posted !== null) {
							$h_value = h($posted !== null ? $posted : $row[$key]);
							echo ">" . ($text ? "<textarea name='$id' cols='30' rows='" . (substr_count($row[$key], "\n") + 1) . "'>$h_value</textarea>" : "<input name='$id' value='$h_value' size='$lengths[$key]'>");
						} else {
							$long = strpos($html, "<i>…</i>");
							echo " data-text='" . ($long ? 2 : ($text ? 1 : 0)) . "'"
								. ($editable ? "" : " data-warning='" . h(lang('Use edit link to modify this value.')) . "'")
								. ">$html"
							;
						}
					}
					next($select);
				}

				if ($backward_keys) {
					echo "<td>";
				}
				adminer()->backwardKeysPrint($backward_keys, $rows[$n]);
				echo "</tr>\n"; // close to allow white-space: pre
			}

			if (is_ajax()) {
				exit;
			}
			echo "</table>\n";
			echo "</div>\n";
		}

		if (!is_ajax()) {
			if ($rows || $page) {
				$exact_count = true;
				if ($_GET["page"] != "last") {
					if (!$limit || (count($rows) < $limit && ($rows || !$page))) {
						$found_rows = ($page ? $page * $limit : 0) + count($rows);
					} elseif (JUSH != "sql" || !$is_group) {
						$found_rows = ($is_group ? false : found_rows($table_status, $where));
						if (intval($found_rows) < max(1e4, 2 * ($page + 1) * $limit)) {
							// slow with big tables
							$found_rows = first(slow_query(count_rows($TABLE, $where, $is_group, $group)));
						} else {
							$exact_count = false;
						}
					}
				}

				$pagination = ($limit && ($found_rows === false || $found_rows > $limit || $page));
				if ($pagination) {
					echo (($found_rows === false ? count($rows) + 1 : $found_rows - $page * $limit) > $limit
						? '<p><a href="' . h(remove_from_uri("page") . "&page=" . ($page + 1)) . '" class="loadmore">' . lang('Load more data') . '</a>'
							. script("qsl('a').onclick = partial(selectLoadMore, $limit, '" . lang('Loading') . "…');", "")
						: ''
					);
					echo "\n";
				}

				echo "<div class='footer'><div>\n";
				if ($pagination) {
					// display first, previous 4, next 4 and last page
					$max_page = ($found_rows === false
						? $page + (count($rows) >= $limit ? 2 : 1)
						: floor(($found_rows - 1) / $limit)
					);
					echo "<fieldset>";
					if (JUSH != "simpledb") {
						echo "<legend><a href='" . h(remove_from_uri("page")) . "'>" . lang('Page') . "</a></legend>";
						echo script("qsl('a').onclick = function () { pageClick(this.href, +prompt('" . lang('Page') . "', '" . ($page + 1) . "')); return false; };");
						echo pagination(0, $page) . ($page > 5 ? " …" : "");
						for ($i = max(1, $page - 4); $i < min($max_page, $page + 5); $i++) {
							echo pagination($i, $page);
						}
						if ($max_page > 0) {
							echo ($page + 5 < $max_page ? " …" : "");
							echo ($exact_count && $found_rows !== false
								? pagination($max_page, $page)
								: " <a href='" . h(remove_from_uri("page") . "&page=last") . "' title='~$max_page'>" . lang('last') . "</a>"
							);
						}
					} else {
						echo "<legend>" . lang('Page') . "</legend>";
						echo pagination(0, $page) . ($page > 1 ? " …" : "");
						echo ($page ? pagination($page, $page) : "");
						echo ($max_page > $page ? pagination($page + 1, $page) . ($max_page > $page + 1 ? " …" : "") : "");
					}
					echo "</fieldset>\n";
				}

				echo "<fieldset>";
				echo "<legend>" . lang('Whole result') . "</legend>";
				$display_rows = ($exact_count ? "" : "~ ") . $found_rows;
				$onclick = "const checked = formChecked(this, /check/); selectCount('selected', this.checked ? '$display_rows' : checked); selectCount('selected2', this.checked || !checked ? '$display_rows' : checked);";
				echo checkbox("all", 1, 0, ($found_rows !== false ? ($exact_count ? "" : "~ ") . lang('%d row(s)', $found_rows) : ""), $onclick) . "\n";
				echo "</fieldset>\n";

				if (adminer()->selectCommandPrint()) {
					?>
<fieldset<?php echo ($_GET["modify"] ? '' : ' class="jsonly"'); ?>><legend><?php echo lang('Modify'); ?></legend><div>
<input type="submit" value="<?php echo lang('Save'); ?>"<?php echo ($_GET["modify"] ? '' : ' title="' . lang('Ctrl+click on a value to modify it.') . '"'); ?>>
</div></fieldset>
<fieldset><legend><?php echo lang('Selected'); ?> <span id="selected"></span></legend><div>
<input type="submit" name="edit" value="<?php echo lang('Edit'); ?>">
<input type="submit" name="clone" value="<?php echo lang('Clone'); ?>">
<input type="submit" name="delete" value="<?php echo lang('Delete'); ?>"><?php echo confirm(); ?>
</div></fieldset>
<?php
				}

				$format = adminer()->dumpFormat();
				foreach ((array) $_GET["columns"] as $column) {
					if ($column["fun"]) {
						unset($format['sql']);
						break;
					}
				}
				if ($format) {
					print_fieldset("export", lang('Export') . " <span id='selected2'></span>");
					$output = adminer()->dumpOutput();
					echo ($output ? html_select("output", $output, $adminer_import["output"]) . " " : "");
					echo html_select("format", $format, $adminer_import["format"]);
					echo " <input type='submit' name='export' value='" . lang('Export') . "'>\n";
					echo "</div></fieldset>\n";
				}

				adminer()->selectEmailPrint(array_filter($email_fields, 'strlen'), $columns);
				echo "</div></div>\n";
			}

			if (adminer()->selectImportPrint()) {
				echo "<p>";
				echo "<a href='#import'>" . lang('Import') . "</a>";
				echo script("qsl('a').onclick = partial(toggle, 'import');", "");
				echo "<span id='import'" . ($_POST["import"] ? "" : " class='hidden'") . ">: ";
				echo file_input("<input type='file' name='csv_file'> "
					. html_select("separator", array("csv" => "CSV,", "csv;" => "CSV;", "tsv" => "TSV"), $adminer_import["format"])
					. " <input type='submit' name='import' value='" . lang('Import') . "'>")
				;
				echo "</span>";
			}

			echo input_token();
			echo "</form>\n";
			echo (!$group && $select ? "" : script("tableCheck();"));
		}
	}
}

if (is_ajax()) {
	ob_end_clean();
	exit;
}
