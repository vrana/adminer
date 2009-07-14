<?php
/** Name in title and navigation
* @return string
*/
function adminer_name() {
	return call_adminer('name', lang('Adminer'));
}

/** Connection parameters
* @return array ($server, $username, $password)
*/
function adminer_credentials() {
	return call_adminer('credentials', array($_GET["server"], $_SESSION["usernames"][$_GET["server"]], $_SESSION["passwords"][$_GET["server"]]));
}

/** Identifier of selected database
* @return string
*/
function adminer_database() {
	// should be used everywhere instead of $_GET["db"]
	return call_adminer('database', $_GET["db"]);
}

/** Table caption used in navigation and headings
* @param array result of SHOW TABLE STATUS
* @return string
*/
function adminer_table_name($row) {
	return call_adminer('table_name', htmlspecialchars($row["Name"]), $row);
}

/** Field caption used in select and edit
* @param array all fields in table, result of fields()
* @param string column identifier, function calls are not contained in $fields 
* @return string
*/
function adminer_field_name($fields, $key) {
	return call_adminer('field_name', '<span title="' . htmlspecialchars($fields[$key]["full_type"]) . '">' . htmlspecialchars($key) . '</span>', $fields, $key);
}

/** Links after select heading
* @param array result of SHOW TABLE STATUS
* @return string
*/
function adminer_select_links($table_status) {
	global $SELF;
	return call_adminer('select_links', '<a href="' . htmlspecialchars($SELF) . (isset($table_status["Engine"]) ? 'table=' : 'view=') . urlencode($_GET['select']) . '">' . lang('Table structure') . '</a>', $table_status);
}

/** Query printed in select before execution
* @param string query to be executed
* @return string
*/
function adminer_select_query($query) {
	global $SELF;
	// it would be nice if $query can be passed by reference and printed value would be returned but call_user() doesn't allow reference parameters
	return call_adminer('select_query', "<p><code class='jush-sql'>" . htmlspecialchars($query) . "</code> <a href='" . htmlspecialchars($SELF) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a>\n", $query);
}

/** Value printed in select table
* @param string escaped value to print
* @return string link to foreign key
*/
function adminer_select_val($val, $link) {
	return call_adminer('select_val', ($link ? '<a href="' . $link . '">' . $val . '</a>' : $val), $val, $link);
}

/** Query printed after execution in the message
* @param string executed query
* @return string
*/
function adminer_message_query($query) {
	global $SELF;
	$id = "sql-" . count($_SESSION["messages"]);
	return call_adminer('message_query', " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-sql'>" . htmlspecialchars($query) . '</pre><a href="' . htmlspecialchars($SELF . 'sql=&history=' . count($_SESSION["history"][$_GET["server"]][$_GET["db"]])) . '">' . lang('Edit') . '</a></div>', $query);
}

/** Prints navigation after Adminer title
* @param string can be "auth" if there is no database connection or "db" if there is no database selected
* @return bool true if default navigation should be printed
*/
function adminer_navigation($missing) {
	global $SELF;
	if (call_adminer('navigation', true, $missing) && $missing != "auth") {
		$databases = get_databases();
		?>
<form action="" method="post">
<p>
<a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo lang('Dump'); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<form action="">
<p><?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>"><?php } ?>
<?php if ($databases) { ?>
<select name="db" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)<?php echo optionlist($databases, $_GET["db"]); ?></select>
<?php } else { ?>
<input name="db" value="<?php echo htmlspecialchars($_GET["db"]); ?>">
<?php } ?>
<?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value=""><?php } ?>
<?php if (isset($_GET["schema"])) { ?><input type="hidden" name="schema" value=""><?php } ?>
<?php if (isset($_GET["dump"])) { ?><input type="hidden" name="dump" value=""><?php } ?>
<input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?>>
</p>
</form>
<?php
		if ($missing != "db" && strlen($_GET["db"])) {
			$table_status = table_status();
			if (!$table_status) {
				echo "<p class='message'>" . lang('No tables.') . "\n";
			} else {
				echo "<p>\n";
				foreach ($table_status as $row) {
					echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . lang('select') . '</a> ';
					echo '<a href="' . htmlspecialchars($SELF) . (isset($row["Rows"]) ? 'table' : 'view') . '=' . urlencode($row["Name"]) . '">' . adminer_table_name($row) . "</a><br>\n";
				}
			}
			echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a>\n";
		}
	}
}
