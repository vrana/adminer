<?php
function adminer_name() {
	return call_adminer('name', lang('Editor'));
}

function adminer_credentials() {
	return call_adminer('credentials', array()); // default INI settings
}

function adminer_database() {
	$dbs = get_databases();
	return call_adminer('database', (count($dbs) == 1 ? $dbs[0] : (count($dbs) == 2 && information_schema($dbs[0]) ? $dbs[1] : 'test')));
}

function adminer_table_name($row) {
	return call_adminer('table_name', htmlspecialchars(strlen($row["Comment"]) ? $row["Comment"] : $row["Name"]), $row);
}

function adminer_field_name($fields, $key) {
	return call_adminer('field_name', htmlspecialchars(strlen($fields[$key]["comment"]) ? $fields[$key]["comment"] : $key), $fields, $key);
}

function adminer_select_links($table_status) {
	return call_adminer('select_links', "", $table_status);
}

function adminer_select_query($query) {
	return call_adminer('select_query', "<!-- " . str_replace("--", "--><!--", $query) . " -->\n", $query);
}

function adminer_select_val($val, $link) {
	return call_adminer('select_val', ($link ? '<a href="' . $link . '">' . $val . '</a>' : $val), $val, $link);
}

function adminer_message_query($query) {
	return call_adminer('message_query', "<!-- " . str_replace("--", "--><!--", $query) . " -->", $query);
}

function adminer_navigation($missing) {
	global $SELF;
	if (call_adminer('navigation', true, $missing) && $missing != "auth") {
		?>
<form action="" method="post">
<p>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</form>
<?php
		if ($missing != "db") {
			$table_status = table_status();
			if (!$table_status) {
				echo "<p class='message'>" . lang('No tables.') . "\n";
			} else {
				echo "<p>\n";
				foreach ($table_status as $row) {
					if (isset($row["Engine"])) { // ignore views
						echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . adminer_table_name($row) . "</a><br>\n";
					}
				}
			}
		}
	}
}
