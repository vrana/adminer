<?php
$index_types = array("PRIMARY", "UNIQUE", "INDEX", "FULLTEXT");
if ($_POST) {
}

page_header(lang('Indexes') . ': ' . htmlspecialchars($_GET["indexes"]));
echo "<h2>" . lang('Indexes') . ': ' . htmlspecialchars($_GET["indexes"]) . "</h2>\n";

if ($_POST) {
	echo "<p class='error'>" . lang('Unable to operate indexes') . ": " . htmlspecialchars($error) . "</p>\n";
	$row = $_POST;
} else {
	$row = array("indexes" => indexes($_GET["indexes"]));
}
?>
<form action="" method="post">
<table border="0" cellspacing="0" cellpadding="2">
<?php
$fields = array_keys(fields($_GET["indexes"]));
$j = 0;
foreach ($row["indexes"] as $type => $index) {
	foreach ($index as $columns) {
		echo "<tr><td><select name='indexes[$j][type]'><option></option>" . optionlist($index_types, $type, "not_vals") . "</select></td><td>";
		sort($columns);
		foreach ($columns as $i => $column) {
			echo "<select name='indexes[$j][columns][$i]'><option></option>" . optionlist($fields, $column, "not_vals") . "</select>";
		}
		echo "<select name='indexes[$j][columns][" . ($i+1) . "]'><option></option>" . optionlist($fields, array(), "not_vals") . "</select>";
		echo "</td></tr>\n";
		$j++;
	}
}
//! JavaScript for adding more indexes and columns
?>
<tr><td><select name="indexes[<?php echo $j; ?>][type]"><option></option><?php echo optionlist($index_types, array(), "not_vals"); ?></select></td><td><select name="indexes[<?php echo $j; ?>][columns][1]"><option></option><?php echo optionlist($fields, array(), "not_vals"); ?></select></td></tr>
</table>
<p><input type="submit" value="<?php echo lang('Alter indexes'); ?>" /></p>
</form>
