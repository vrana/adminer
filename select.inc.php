<?php
page_header(lang('Select') . ": " . htmlspecialchars($_GET["select"]));
$fields = fields($_GET["select"]);
$rights = array();
$columns = array();
foreach ($fields as $key => $field) {
	if (isset($field["privileges"]["select"])) {
		$columns[] = $key;
	}
	$rights += $field["privileges"];
}

if (isset($rights["insert"])) {
	//! pass search values forth and back
	echo '<p><a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '">' . lang('New item') . "</a></p>\n";
}

if (!$columns) {
	echo "<p class='error'>" . lang('Unable to select the table') . ($fields ? "" : ": " . $mysql->error) . ".</p>\n";
} else {
	$indexes = indexes($_GET["select"]);
	echo "<form action='' id='form'>\n<fieldset><legend>" . lang('Search') . "</legend>\n";
	if (strlen($_GET["server"])) {
		echo '<input type="hidden" name="server" value="' . htmlspecialchars($_GET["server"]) . '" />';
	}
	echo '<input type="hidden" name="db" value="' . htmlspecialchars($_GET["db"]) . '" />';
	echo '<input type="hidden" name="select" value="' . htmlspecialchars($_GET["select"]) . '" />';
	echo "\n";
	
	$where = array();
	foreach ($indexes as $i => $index) {
		if ($index["type"] == "FULLTEXT") {
			if (strlen($_GET["fulltext"][$i])) {
				$where[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST ('" . $mysql->escape_string($_GET["fulltext"][$i]) . "'" . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
			echo "(<i>" . implode("</i>, <i>", array_map('htmlspecialchars', $index["columns"])) . "</i>) AGAINST";
			echo ' <input name="fulltext[' . $i . ']" value="' . htmlspecialchars($_GET["fulltext"][$i]) . '" />';
			echo "<label for='boolean-$i'><input type='checkbox' name='boolean[$i]' value='1' id='boolean-$i'" . (isset($_GET["boolean"][$i]) ? " checked='checked'" : "") . " />" . lang('BOOL') . "</label>";
			echo "<br />\n";
		}
	}
	$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IN", "IS NULL");
	$i = 0;
	foreach ((array) $_GET["where"] as $val) {
		if (strlen($val["col"]) && in_array($val["op"], $operators)) {
			if ($val["op"] == "IN") {
				$in = process_length($val["val"]);
				if (!strlen($in)) {
					$in = "NULL";
				}
			}
			$where[] = idf_escape($val["col"]) . " $val[op]" . ($val["op"] == "IS NULL" ? "" : ($val["op"] == "IN" ? " ($in)" : " '" . $mysql->escape_string($val["val"]) . "'"));
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

function add_row(field) {
	var row = field.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/[a-z]\[[0-9]+/, '$&1');
	}
	var input = row.getElementsByTagName('input')[0];
	input.name = input.name.replace(/[a-z]\[[0-9]+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}
</script>
<?php
	echo "<div><select name='where[$i][col]' onchange='add_row(this);'><option></option>" . optionlist($columns, array()) . "</select>";
	echo "<select name='where[$i][op]' onchange=\"where_change(this);\">" . optionlist($operators, array()) . "</select>";
	echo "<input name='where[$i][val]' /></div>\n";
	echo "</fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Sort') . "</legend>\n";
	$order = array();
	$i = 0;
	foreach ((array) $_GET["order"] as $key => $val) {
		if (in_array($val, $columns, true)) {
			$order[] = idf_escape($val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
			echo "<div><select name='order[$i]'><option></option>" . optionlist($columns, $val) . "</select>";
			echo "<label><input type='checkbox' name='desc[$i]' value='1'" . (isset($_GET["desc"][$key]) ? " checked='checked'" : "") . " />" . lang('DESC') . "</label></div>\n";
			$i++;
		}
	}
	echo "<div><select name='order[$i]' onchange='add_row(this);'><option></option>" . optionlist($columns, array()) . "</select>";
	echo "<label><input type='checkbox' name='desc[$i]' value='1' />" . lang('DESC') . "</label></div>\n";
	echo "</fieldset>\n";
	
	echo "<fieldset><legend>" . lang('Limit') . "</legend>\n";
	$limit = (isset($_GET["limit"]) ? $_GET["limit"] : "30");
	echo '<div><input name="limit" size="3" value="' . htmlspecialchars($limit) . '" /></div>';
	echo "</fieldset>\n";
	
	$select = array();
	unset($text_length);
	foreach ($columns as $column) {
		if (preg_match('~text|blob~', $fields[$column]["type"])) {
			$text_length = (isset($_GET["text_length"]) ? $_GET["text_length"] : "100");
			$select[] = (intval($text_length) ? "LEFT(" . idf_escape($column) . ", " . intval($text_length) . ") AS " : "") . idf_escape($column);
		} else {
			$select[] = idf_escape($column);
		}
	}
	if (isset($text_length)) {
		echo "<fieldset><legend>" . lang('Text length') . "</legend>\n";
		echo '<div><input name="text_length" size="3" value="' . htmlspecialchars($text_length) . '" /></div>';
		echo "</fieldset>\n";
	}
	
	echo "<fieldset><legend>" . lang('Action') . "</legend><div><input type='submit' value='" . lang('Select') . "' /></div></fieldset>\n";
	echo "</form>\n";
	echo "<div style='clear: left;'>&nbsp;</div>\n";
	
	$result = $mysql->query("SELECT SQL_CALC_FOUND_ROWS " . implode(", ", $select) . " FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "") . ($order ? " ORDER BY " . implode(", ", $order) : "") . (strlen($limit) ? " LIMIT " . intval($limit) . " OFFSET " . ($limit * $_GET["page"]) : ""));
	if (!$result->num_rows) {
		echo "<p class='message'>" . lang('No rows.') . "</p>\n";
	} else {
		$found_rows = $mysql->result($mysql->query(" SELECT FOUND_ROWS()")); // space for mysql.trace_mode
		$foreign_keys = array();
		foreach (foreign_keys($_GET["select"]) as $foreign_key) {
			foreach ($foreign_key["source"] as $val) {
				$foreign_keys[$val][] = $foreign_key;
			}
		}
		
		echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
		for ($j=0; $row = $result->fetch_assoc(); $j++) {
			if (!$j) {
				echo "<thead><tr><th>" . implode("</th><th>", array_map('htmlspecialchars', array_keys($row))) . "</th><th>&nbsp;</th></tr></thead>\n";
			}
			echo "<tr>";
			$unique_idf = '&amp;' . implode('&amp;', unique_idf($row, $indexes));
			//! multiple delete by checkboxes
			foreach ($row as $key => $val) {
				if (!isset($val)) {
					$val = "<i>NULL</i>";
				} elseif (preg_match('~blob|binary~', $fields[$key]["type"]) && preg_match('~[\\x80-\\xFF]~', $val)) {
					$val = '<a href="' . htmlspecialchars($SELF) . 'download=' . urlencode($_GET["select"]) . '&amp;field=' . urlencode($key) . $unique_idf . '">' . lang('%d byte(s)', strlen($val)) . '</a>';
				} else {
					$val = (strlen(trim($val)) ? nl2br(htmlspecialchars($val)) : "&nbsp;");
					if ($fields[$key]["type"] == "char") {
						$val = "<code>$val</code>";
					}
					foreach ((array) $foreign_keys[$key] as $foreign_key) {
						if (count($foreign_keys[$key]) == 1 || count($foreign_key["source"]) == 1) {
							$val = '">' . "$val</a>";
							foreach ($foreign_key["source"] as $i => $source) {
								$val = "&amp;where%5B$i%5D%5Bcol%5D=" . urlencode($foreign_key["target"][$i]) . "&amp;where%5B$i%5D%5Bop%5D=%3D&amp;where%5B$i%5D%5Bval%5D=" . urlencode($row[$source]) . $val;
							}
							$val = '<a href="' . htmlspecialchars(strlen($foreign_key["db"]) ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($foreign_key["db"]), $SELF) : $SELF) . 'select=' . htmlspecialchars($foreign_key["table"]) . $val; // InnoDB support non-UNIQUE keys
							break;
						}
					}
				}
				echo "<td>$val</td>";
			}
			echo '<td><a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . $unique_idf . '">' . lang('edit') . '</a></td>';
			echo "</tr>\n";
		}
		echo "</table>\n";
		if (intval($limit) && $found_rows > $limit) {
			$max_page = floor($found_rows / $limit);
			function print_page($page) {
				echo " " . ($page == $_GET["page"] ? $page + 1 : '<a href="' . htmlspecialchars(preg_replace('~(\\?)page=[^&]*&|&page=[^&]*~', '\\1', $_SERVER["REQUEST_URI"]) . ($page ? "&page=$page" : "")) . '">' . ($page + 1) . "</a>");
			}
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
