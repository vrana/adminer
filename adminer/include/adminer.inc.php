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

/** Print login form
* @param string
* @return bool whether to display default login form
*/
function adminer_login_form($username) {
	if (call_adminer('login_form', true, $username)) {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Server'); ?><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>">
<tr><th><?php echo lang('Username'); ?><td><input name="username" value="<?php echo htmlspecialchars($username); ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<?php
	}
}

/** Authorize the user
* @param string
* @param string
* @return bool
*/
function adminer_login($login, $password) {
	return call_adminer('login', true, $login, $password);
}

/** Table caption used in navigation and headings
* @param array result of SHOW TABLE STATUS
* @return string
*/
function adminer_table_name($table_status) {
	return call_adminer('table_name', htmlspecialchars($table_status["Name"]), $table_status);
}

/** Field caption used in select and edit
* @param array single field returned from fields()
* @return string
*/
function adminer_field_name($field) {
	return call_adminer('field_name', '<span title="' . htmlspecialchars($field["full_type"]) . '">' . htmlspecialchars($field["field"]) . '</span>', $field);
}

/** Links after select heading
* @param array result of SHOW TABLE STATUS
* @return string
*/
function adminer_select_links($table_status) {
	global $SELF;
	return call_adminer('select_links', '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($_GET['select']) . '">' . lang('Table structure') . '</a>', $table_status);
}

/** Find backward keys for table
* @param string
* @return array $return[$target_table][$key_name][$target_column] = $source_column;
*/
function adminer_backward_keys($table) {
	return call_adminer('backward_keys', array(), $table);
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

/** Description of a row in a table
* @param string
* @return string SQL expression, empty string for no description
*/
function adminer_row_description($table) {
	return call_adminer('row_description', "", $table);
}

/** Get descriptions of selected data
* @param array all data to print
* @param array
* @return array
*/
function adminer_row_descriptions($rows, $foreign_keys) {
	return call_adminer('row_descriptions', $rows, $rows, $foreign_keys);
}

/** Value printed in select table
* @param string escaped value to print
* @param string link to foreign key
* @param array single field returned from fields()
* @return string
*/
function adminer_select_val($val, $link, $field) {
	$return = ($field["type"] == "char" ? "<code>$val</code>" : $val);
	if (ereg('blob|binary', $field["type"]) && !is_utf8($val)) {
		$return = lang('%d byte(s)', strlen($val));
	}
	return call_adminer('select_val', ($link ? "<a href=\"$link\">$return</a>" : $return), $val, $link);
}

/** Query printed after execution in the message
* @param string executed query
* @return string
*/
function adminer_message_query($query) {
	global $SELF;
	$id = "sql-" . count($_SESSION["messages"]);
	$_SESSION["history"][$_GET["server"]][$_GET["db"]][] = $query;
	return call_adminer('message_query', " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-sql'>" . htmlspecialchars($query) . '</pre><a href="' . htmlspecialchars($SELF . 'sql=&history=' . (count($_SESSION["history"][$_GET["server"]][$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a></div>', $query);
}

/** Functions displayed in edit form
* @param array single field from fields()
* @return array
*/
function adminer_edit_functions($field) {
	$return = array("");
	if (!isset($_GET["default"])) {
		if (ereg('char|date|time', $field["type"])) {
			$return = (ereg('char', $field["type"]) ? array("", "md5", "sha1", "password", "uuid") : array("", "now")); //! JavaScript for disabling maxlength
		}
		if (!isset($_GET["call"]) && (isset($_GET["select"]) || where($_GET))) {
			// relative functions
			if (ereg('int|float|double|decimal', $field["type"])) {
				$return = array("", "+", "-");
			}
			if (ereg('date', $field["type"])) {
				$return[] = "+ interval";
				$return[] = "- interval";
			}
			if (ereg('time', $field["type"])) {
				$return[] = "addtime";
				$return[] = "subtime";
			}
		}
	}
	if ($field["null"] || isset($_GET["default"])) {
		array_unshift($return, "NULL");
	}
	return call_adminer('edit_functions', (isset($_GET["select"]) ? array("orig" => lang('original')) : array()) + $return, $field);
}

/** Get options to display edit field
* @param string table name
* @param array single field from fields()
* @return array options for <select> or empty to display <input>
*/
function adminer_edit_input($table, $field) {
	return call_adminer('edit_input', false, $table, $field);
}

/** Process sent input
* @param string field name
* @param array single field from fields()
* @return string expression to use in a query
*/
function adminer_process_input($name, $field) {
	global $dbh;
	$idf = bracket_escape($name);
	$function = $_POST["function"][$idf];
	$value = $_POST["fields"][$idf];
	$return = $dbh->quote($value);
	if (ereg('^(now|uuid)$', $function)) {
		$return = "$function()";
	} elseif (ereg('^[+-]$', $function)) {
		$return = idf_escape($name) . " $function $return";
	} elseif (ereg('^[+-] interval$', $function)) {
		$return = idf_escape($name) . " $function " . (preg_match("~^([0-9]+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : $return);
	} elseif (ereg('^(addtime|subtime)$', $function)) {
		$return = "$function(" . idf_escape($name) . ", $return)";
	} elseif (ereg('^(md5|sha1|password)$', $function)) {
		$return = "$function($return)";
	} elseif (ereg('date|time', $field["type"]) && $value == "CURRENT_TIMESTAMP") {
		$return = $value;
	}
	return call_adminer('process_input', $return, $name, $field);
}

/** Prints navigation after Adminer title
* @param string can be "auth" if there is no database connection or "db" if there is no database selected
* @return bool true if default navigation should be printed
*/
function adminer_navigation($missing) {
	global $SELF, $dbh;
	if (call_adminer('navigation', true, $missing) && $missing != "auth") {
		ob_flush();
		flush();
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
			$result = $dbh->query("SHOW TABLES");
			if (!$result->num_rows) {
				echo "<p class='message'>" . lang('No tables.') . "\n";
			} else {
				echo "<p>\n";
				while ($row = $result->fetch_row()) {
					echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row[0]) . '">' . lang('select') . '</a> ';
					echo '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($row[0]) . '">' . adminer_table_name(array("Name" => $row[0])) . "</a><br>\n"; //! Adminer::table_name may work with full table status
				}
			}
			$result->free();
			echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a>\n";
		}
	}
}
