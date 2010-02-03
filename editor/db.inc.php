<?php
page_header(lang('Server'), "", null);

?>
<form action=""><p>
<?php if (SID) { ?><input type="hidden" name="<?php echo session_name(); ?>" value="<?php echo h(session_id()); ?>"><?php } ?>
<input name="where[][val]" value="<?php echo h($_GET["where"][0]["val"]); ?>">
<input type="submit" value="<?php echo lang('Search'); ?>" />
</form>
<?php
if ($_GET["where"]) {
	$found = false;
	foreach (table_status() as $table => $table_status) {
		$name = $adminer->tableName($table_status);
		if (isset($table_status["Engine"]) && $name != "") {
			$result = $connection->query("SELECT 1 FROM " . idf_escape($table) . " WHERE " . implode(" AND ", $adminer->selectSearchProcess(fields($table), array())) . " LIMIT 1");
			if ($result->num_rows) {
				if (!$found) {
					echo "<ul>\n";
					$found = true;
				}
				echo "<li><a href='" . h(ME . "select=" . urlencode($table) . "&where[][val]=" . urlencode($_GET["where"][0]["val"])) . "'>" . h($name) . "</a>\n";
			}
		}
	}
	echo ($found ? "</ul>" : "<p class='message'>" . lang('No tables.')) . "\n";
}
