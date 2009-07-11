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
	$join = "";
	$i = 1;
	foreach (foreign_keys($_GET["select"]) as $foreign_key) {
		$on = array();
		foreach ($foreign_key["source"] as $key => $val) {
			$on[] = "`t0`." . idf_escape($val) . " = `t$i`." . idf_escape($foreign_key["target"][$key]);
		}
		//~ $join .= "\nLEFT JOIN " . idf_escape($foreign_key["table"]) . " AS `t$i` ON " . implode(" AND ", $on);
		//! use in select
		$i++;
	}
	$query = preg_replace("~((?:[^'`]*|'(?:[^'\\\\]*|\\\\.)+')+)(`((?:[^`]+|``)*)`)~", '\\1`t0`.\\2', $query); // don't match ` inside ''
	$query = preg_replace('~ FROM `t0`.(`((?:[^`]+|``)*)`) ?~', "\nFROM \\1 AS `t0`" . addcslashes($join, '\\$') . "\n", $query);
	$return = call_adminer('select_query', "", $query);
	if (!$return) {
		echo "<!-- " . str_replace("--", " --><!-- ", $query) . " -->\n";
		return $query;
	}
	return $return;
}

function adminer_message_query($query) {
	return call_adminer('message_query', "<!-- " . str_replace("--", " --><!-- ", $query) . " -->", $query);
}

function adminer_navigation($missing) {
	global $SELF;
	if (call_adminer('navigation', true, $missing) && $missing != "auth") {
		?>
<form action="" method="post">
<p>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>" />
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" />
</p>
</form>
<?php
		if ($missing != "db") {
			$table_status = table_status();
			if (!$table_status) {
				echo "<p class='message'>" . lang('No tables.') . "</p>\n";
			} else {
				echo "<p>\n";
				foreach ($table_status as $row) {
					if (isset($row["Engine"])) { // ignore views
						echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . adminer_table_name($row) . "</a><br />\n";
					}
				}
				echo "</p>\n";
			}
		}
	}
}
