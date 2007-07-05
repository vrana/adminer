<?php
$indexes = indexes($_GET["select"]);
page_header(lang('Select') . ": " . htmlspecialchars($_GET["select"]));

echo '<p><a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '">' . lang('New item') . "</a></p>\n";
echo "<form action='' id='form'><div>\n";
if (strlen($_GET["server"])) {
	echo '<input type="hidden" name="server" value="' . htmlspecialchars($_GET["server"]) . '" />';
}
echo '<input type="hidden" name="db" value="' . htmlspecialchars($_GET["db"]) . '" />';
echo '<input type="hidden" name="select" value="' . htmlspecialchars($_GET["select"]) . '" />';

$where = array();
$columns = array();
foreach (fields($_GET["select"]) as $name => $field) {
	$columns[] = $name;
}
$operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IS NULL");
$i = 0;
foreach ((array) $_GET["where"] as $val) {
	if ($val["col"] && in_array($val["op"], $operators)) {
		$where[] = idf_escape($val["col"]) . " $val[op]" . ($val["op"] != "IS NULL" ? " '" . mysql_real_escape_string($val["val"]) . "'" : "");
		echo "<select name='where[$i][col]'><option></option>" . optionlist($columns, $val["col"], "not_vals") . "</select>";
		echo "<select name='where[$i][op]' onchange=\"where_change(this);\">" . optionlist($operators, $val["op"], "not_vals") . "</select>";
		echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . "\" /><br />\n";
		$i++;
	}
}
?>
<script type="text/javascript">
function where_change(op) {
	op.form[op.name.substr(0, op.name.length - 4) + '[val]'].style.display = (op.value == 'IS NULL' ? 'none' : '');
}
<?php if ($i) { ?>
	for (var i=0; <?php echo $i; ?> > i; i++) document.getElementById('form')['where[' + i + '][op]'].onchange();
<?php } ?>
</script>
<?php
echo "<select name='where[$i][col]'><option></option>" . optionlist($columns, array(), "not_vals") . "</select>";
echo "<select name='where[$i][op]' onchange=\"where_change(this);\">" . optionlist($operators, array(), "not_vals") . "</select>";
echo "<input name='where[$i][val]' /><br />\n"; //! JavaScript for adding next
//! fulltext search

//! sort, limit
$limit = 30;

echo "<input type='submit' value='" . lang('Search') . "' />\n";
echo "</div></form>\n";
$result = mysql_query("SELECT SQL_CALC_FOUND_ROWS * FROM " . idf_escape($_GET["select"]) . ($where ? " WHERE " . implode(" AND ", $where) : "") . " LIMIT $limit OFFSET " . ($limit * $_GET["page"]));
$found_rows = mysql_result(mysql_query(" SELECT FOUND_ROWS()"), 0); // space for mysql.trace_mode
if (!mysql_num_rows($result)) {
	echo "<p class='message'>" . lang('No rows.') . "</p>\n";
} else {
	$foreign_keys = foreign_keys($_GET["select"]);
	$childs = array();
	$result1 = mysql_query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = '" . mysql_real_escape_string($_GET["db"]) . "' AND REFERENCED_TABLE_NAME = '" . mysql_real_escape_string($_GET["select"]) . "' ORDER BY ORDINAL_POSITION");
	while ($row1 = mysql_fetch_assoc($result1)) {
		$childs[$row1["CONSTRAINT_NAME"]][0] = $row1["TABLE_SCHEMA"];
		$childs[$row1["CONSTRAINT_NAME"]][1] = $row1["TABLE_NAME"];
		$childs[$row1["CONSTRAINT_NAME"]][2][] = $row1["REFERENCED_COLUMN_NAME"];
		$childs[$row1["CONSTRAINT_NAME"]][3][] = $row1["COLUMN_NAME"];
	}
	mysql_free_result($result1);
	
	echo "<table border='1' cellspacing='0' cellpadding='2'>\n";
	for ($j=0; $row = mysql_fetch_assoc($result); $j++) {
		if (!$j) {
			echo "<thead><tr><th>" . implode("</th><th>", array_map('htmlspecialchars', array_keys($row))) . "</th><th>" . lang('Action') . "</th></tr></thead>\n";
		}
		echo "<tr>";
		foreach ($row as $key => $val) {
			if (!isset($val)) {
				$val = "<i>NULL</i>";
			} else {
				$val = (strlen(trim($val)) ? htmlspecialchars($val) : "&nbsp;");
				foreach ((array) $foreign_keys[$key] as $foreign_key) {
					if (count($foreign_keys[$key]) == 1 || count($foreign_key[2]) == 1) {
						$val = '">' . "$val</a>";
						foreach ($foreign_key[2] as $i => $source) {
							$val = "&amp;where[$i][col]=" . urlencode($foreign_key[3][$i]) . "&amp;where[$i][op]=%3D&amp;where[$i][val]=" . urlencode($row[$source]) . $val;
						}
						$val = '<a href="' . htmlspecialchars(strlen($foreign_key[0]) ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($foreign_key[0]), $SELF) : $SELF) . 'select=' . htmlspecialchars($foreign_key[1]) . $val; // InnoDB support non-UNIQUE keys
						break;
					}
				}
			}
			echo "<td>$val</td>";
		}
		echo '<td><a href="' . htmlspecialchars($SELF) . 'edit=' . urlencode($_GET['select']) . '&amp;' . implode('&amp;', unique_idf($row, $indexes)) . '">' . lang('edit') . '</a>'; //! views can be unupdatable
		foreach ($childs as $child) {
			echo ' <a href="' . htmlspecialchars(strlen($child[0]) ? preg_replace('~([?&]db=)[^&]+~', '\\1' . urlencode($child[0]), $SELF) : $SELF) . 'select=' . urlencode($child[1]);
			foreach ($child[2] as $i => $source) {
				echo "&amp;where[$i][col]=" . urlencode($child[3][$i]) . "&amp;where[$i][op]=%3D&amp;where[$i][val]=" . urlencode($row[$source]);
			}
			echo '">' . htmlspecialchars($child[1]) . '</a>';
		}
		echo "</td>";
		echo "</tr>\n";
	}
	echo "</table>\n";
	if ($found_rows > $limit) {
		echo "<p>" . lang('Page') . ":\n";
		for ($i=0; $i < $found_rows / $limit; $i++) {
			echo ($i == $_GET["page"] ? $i + 1 : '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($_GET['select']) . ($i ? "&amp;page=$i" : "") . '">' . ($i + 1) . "</a>") . "\n";
		}
		echo "</p>\n";
	}
}
mysql_free_result($result);
