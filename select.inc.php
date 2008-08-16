<?php
include "./export.inc.php";

$table_status = table_status($_GET["select"]);
$indexes = indexes($_GET["select"]);
$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IN", "IS NULL");
if ($table_status["Engine"] == "MyISAM") {
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
		$where[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST ('" . $mysql->escape_string($_GET["fulltext"][$i]) . "'" . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
	}
}
foreach ((array) $_GET["where"] as $val) {
	if (strlen($val["col"]) && in_array($val["op"], $operators)) {
		if ($val["op"] == "IN") {
			$in = process_length($val["val"]);
			$where[] = (strlen($in) ? idf_escape($val["col"]) . " IN ($in)" : "0");
		} elseif ($val["op"] == "AGAINST") {
			$where[] = "MATCH (" . idf_escape($val["col"]) . ") AGAINST ('" . $mysql->escape_string($val["val"]) . "' IN BOOLEAN MODE)";
		} else {
			$where[] = idf_escape($val["col"]) . " $val[op]" . ($val["op"] == "IS NULL" ? "" : " '" . $mysql->escape_string($val["val"]) . "'");
		}
	}
}
$order = array();
foreach ((array) $_GET["order"] as $key => $val) {
	if (in_array($val, $columns, true)) {
		$order[] = idf_escape($val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
	} elseif (preg_match('(^(' . strtoupper(implode('|', $functions) . '|' . implode('|', $grouping)) . ')\\((' . implode('|', array_map('preg_quote', array_map('idf_escape', $columns))) . ')\\)$)', $val)) {
		$order[] = $val . (isset($_GET["desc"][$key]) ? " DESC" : "");
	}
}
$limit = (isset($_GET["limit"]) ? $_GET["limit"] : "30");
$from = "FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "") . ($group && count($group) < count($select) ? " GROUP BY " . implode(", ", $group) : "") . ($order ? " ORDER BY " . implode(", ", $order) : "") . (strlen($limit) ? " LIMIT " . intval($limit) . (intval($_GET["page"]) ? " OFFSET " . ($limit * $_GET["page"]) : "") : "");

if ($_POST && !$error) {
	$result = true;
	$deleted = 0;
	if ($_POST["export"] || $_POST["export_result"]) {
		header("Content-Type: text/plain; charset=utf-8");
		header("Content-Disposition: inline; filename=" . preg_replace('~[^a-z0-9_]~i', '-', $_GET["select"]) . "." . ($_POST["format"] == "sql" ? "sql" : "csv"));
	}
	if (isset($_POST["truncate"])) {
		$result = $mysql->query($where ? "DELETE FROM " . idf_escape($_GET["select"]) . " WHERE " . implode(" AND ", $where) : "TRUNCATE " . idf_escape($_GET["select"]));
		$deleted = $mysql->affected_rows;
	} elseif ($_POST["export_result"]) {
		dump_data($_GET["select"], "INSERT", ($where ? "FROM " . idf_escape($_GET["select"]) . " WHERE " . implode(" AND ", $where) : ""));
	} elseif (is_array($_POST["delete"])) {
		foreach ($_POST["delete"] as $val) {
			parse_str($val, $delete);
			if ($_POST["export"]) {
				dump_data($_GET["select"], "INSERT", "FROM " . idf_escape($_GET["select"]) . " WHERE " . implode(" AND ", where($delete)) . " LIMIT 1");
			} else {
				$result = $mysql->query("DELETE FROM " . idf_escape($_GET["select"]) . " WHERE " . implode(" AND ", where($delete)) . " LIMIT 1");
				if (!$result) {
					break;
				}
				$deleted += $mysql->affected_rows;
			}
		}
	} elseif ($_POST["delete_selected"]) {
		if ($_POST["export"]) {
			dump_data($_GET["select"], "INSERT", $from);
		} else {
			$result1 = $mysql->query("SELECT * $from");
			while ($row1 = $result1->fetch_assoc()) {
				parse_str(implode("&", unique_idf($row1, $indexes)), $delete);
				$result = $mysql->query("DELETE FROM " . idf_escape($_GET["select"]) . " WHERE " . implode(" AND ", where($delete)) . " LIMIT 1");
				if (!$result) {
					break;
				}
				$deleted += $mysql->affected_rows;
			}
			$result1->free();
		}
	}
	if ($_POST["export"] || $_POST["export_result"]) {
		exit;
	}
	if ($result) {
		redirect(remove_from_uri("page"), lang('%d item(s) have been deleted.', $deleted));
	}
	$error = $mysql->error;
}
page_header(lang('Select') . ": " . htmlspecialchars($_GET["select"]), ($error ? lang('Error during deleting') . ": $error" : ""));

if (isset($rights["insert"])) {
	//! pass search values forth and back
	echo '<p><a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '">' . lang('New item') . "</a></p>\n";
}

if (!$columns) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "" : ": " . htmlspecialchars($mysql->error)) . ".</p>\n";
} else {
	echo "<form action='' id='form'>\n";
	?>
<script type="text/javascript">
function add_row(field) {
	var row = field.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/[a-z]\[[0-9]+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = row.getElementsByTagName('input');
	if (inputs.length) {
		inputs[0].name = inputs[0].name.replace(/[a-z]\[[0-9]+/, '$&1');
		inputs[0].value = '';
	}
	field.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}
</script>
<?php
	echo "<fieldset><legend>" . lang('Select') . "</legend>\n";
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
	echo "<select name='columns[$i][col]' onchange='add_row(this);'><option></option>" . optionlist($columns) . "</select></div>\n";
	echo "</fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Search') . "</legend>\n";
	foreach ($indexes as $i => $index) {
		if ($index["type"] == "FULLTEXT") {
			echo "(<i>" . implode("</i>, <i>", array_map('htmlspecialchars', $index["columns"])) . "</i>) AGAINST";
			echo ' <input name="fulltext[' . $i . ']" value="' . htmlspecialchars($_GET["fulltext"][$i]) . '" />';
			echo "<label for='boolean-$i'><input type='checkbox' name='boolean[$i]' value='1' id='boolean-$i'" . (isset($_GET["boolean"][$i]) ? " checked='checked'" : "") . " />" . lang('BOOL') . "</label>";
			echo "<br />\n";
		}
	}
	$i = 0;
	foreach ((array) $_GET["where"] as $val) {
		if (strlen($val["col"]) && in_array($val["op"], $operators)) {
			echo "<div><select name='where[$i][col]'><option></option>" . optionlist($columns, $val["col"]) . "</select>";
			echo "<select name='where[$i][op]' onchange=\"where_change(this);\">" . optionlist($operators, $val["op"]) . "</select>";
			echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . "\" /></div>\n";
			$i++;
		}
	}
	?>
<script type="text/javascript">
function where_change(op) {
	op.form[op.name.substr(0, op.name.length - 4) + '[val]'].style.display = (op.value == 'IS NULL' ? 'none' : '');
}
<?php if ($i) { ?>
for (var i=0; <?php echo $i; ?> > i; i++) {
	document.getElementById('form')['where[' + i + '][op]'].onchange();
}
<?php } ?>
</script>
<?php
	echo "<div><select name='where[$i][col]' onchange='add_row(this);'><option></option>" . optionlist($columns) . "</select>";
	echo "<select name='where[$i][op]' onchange=\"where_change(this);\">" . optionlist($operators) . "</select>";
	echo "<input name='where[$i][val]' /></div>\n";
	echo "</fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Sort') . "</legend>\n";
	$i = 0;
	foreach ((array) $_GET["order"] as $key => $val) {
		if (in_array($val, $columns, true)) {
			echo "<div><select name='order[$i]'><option></option>" . optionlist($columns, $val) . "</select>";
			echo "<label><input type='checkbox' name='desc[$i]' value='1'" . (isset($_GET["desc"][$key]) ? " checked='checked'" : "") . " />" . lang('DESC') . "</label></div>\n";
			$i++;
		}
	}
	echo "<div><select name='order[$i]' onchange='add_row(this);'><option></option>" . optionlist($columns) . "</select>";
	echo "<label><input type='checkbox' name='desc[$i]' value='1' />" . lang('DESC') . "</label></div>\n";
	echo "</fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Limit') . "</legend>\n";
	echo '<div><input name="limit" size="3" value="' . htmlspecialchars($limit) . '" /></div>';
	echo "</fieldset>\n";
	
	if (isset($text_length)) {
		echo "<fieldset><legend>" . lang('Text length') . "</legend>\n";
		echo '<div><input name="text_length" size="3" value="' . htmlspecialchars($text_length) . '" /></div>';
		echo "</fieldset>\n";
	}
	
	echo "<fieldset><legend>" . lang('Action') . "</legend><div><input type='submit' value='" . lang('Select') . "' /></div></fieldset>\n";
	echo "</form>\n";
	echo "<div style='clear: left;'>&nbsp;</div>\n";
	
	$result = $mysql->query("SELECT " . ($select ? (count($group) < count($select) ? "SQL_CALC_FOUND_ROWS " : "") . implode(", ", $select) : "*") . " $from");
	if (!$result) {
		echo "<p class='error'>" . htmlspecialchars($mysql->error) . "</p>\n";
	} else {
		if (!$result->num_rows) {
			echo "<p class='message'>" . lang('No rows.') . "</p>\n";
		} else {
			$foreign_keys = array();
			foreach (foreign_keys($_GET["select"]) as $foreign_key) {
				foreach ($foreign_key["source"] as $val) {
					$foreign_keys[$val][] = $foreign_key;
				}
			}
			
			echo "<form action='' method='post'>\n";
			echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
			for ($j=0; $row = $result->fetch_assoc(); $j++) {
				if (!$j) {
					echo '<thead><tr>' . (count($select) == count($group) ? '<td><label><input type="checkbox" name="delete_selected" value="1" onclick="var elems = this.form.elements; for (var i=0; i < elems.length; i++) if (elems[i].name == \'delete[]\') elems[i].checked = this.checked;" />' . lang('all') . '</label></td>' : '');
					foreach ($row as $key => $val) {
						echo '<th><a href="' . remove_from_uri('(order|desc)[^=]*') . '&amp;order%5B0%5D=' . htmlspecialchars($key) . ($_GET["order"][0] === $key && !$_GET["desc"][0] ? '&amp;desc%5B0%5D=1' : '') . '">' . htmlspecialchars($key) . "</a></th>";
					}
					echo "</tr></thead>\n";
				}
				$unique_idf = implode('&amp;', unique_idf($row, $indexes));
				echo '<tr>' . (count($select) == count($group) ? '<td><input type="checkbox" name="delete[]" value="' . $unique_idf . '" /> <a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '&amp;' . $unique_idf . '">' . lang('edit') . '</a></td>' : '');
				foreach ($row as $key => $val) {
					if (!isset($val)) {
						$val = "<i>NULL</i>";
					} elseif (preg_match('~blob|binary~', $fields[$key]["type"]) && preg_match('~[\\x80-\\xFF]~', $val)) {
						$val = '<a href="' . htmlspecialchars($SELF) . 'download=' . urlencode($_GET["select"]) . '&amp;field=' . urlencode($key) . '&amp;' . $unique_idf . '">' . lang('%d byte(s)', strlen($val)) . '</a>';
					} else {
						if (!strlen(trim($val))) {
							$val = "&nbsp;";
						} elseif (intval($text_length) > 0 && preg_match('~blob|text~', $fields[$key]["type"]) && strlen($val) > intval($text_length)) {
							$val = (preg_match('~blob~', $fields[$key]["type"]) ? nl2br(htmlspecialchars(substr($val, 0, intval($text_length)))) . "<em>...</em>" : shorten_utf8($val, intval($text_length)));
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
			echo "<fieldset><legend>" . lang('Delete') . "</legend><input type='hidden' name='token' value='$token' />" . (count($group) == count($select) ? "<input type='submit' value='" . lang('Delete selected') . "' /> " : "") . "<input type='submit' name='truncate' value='" . lang('Truncate result') . "' onclick=\"return confirm('" . lang('Are you sure?') . "');\" /></fieldset>\n";
			echo "<fieldset><legend>" . lang('Export') . "</legend>$dump_options " . (count($group) == count($select) ? "<input type='submit' name='export' value='" . lang('Export selected') . "' /> " : "") . "<input type='submit' name='export_result' value='" . lang('Export result') . "' /></fieldset>\n"; //! output, format
			echo "</form>\n";
			if (intval($limit) && ($found_rows = $mysql->result($mysql->query(count($group) < count($select) ? " SELECT FOUND_ROWS()" : "SELECT COUNT(*) FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "")))) > $limit) {
				$max_page = floor(($found_rows - 1) / $limit);
				echo "<p>" . lang('Page') . ":";
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
				echo "</p>\n";
			}
		}
		$result->free();
	}
}
