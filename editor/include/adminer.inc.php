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

function adminer_login_form($username) {
	if (call_adminer('login_form', true, $username)) {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Username'); ?><td><input type="hidden" name="server" value="" /><input name="username" value="<?php echo htmlspecialchars($username); ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<?php
	}
}

function adminer_login($login, $password) {
	return call_adminer('login', true, $login, $password);
}

function adminer_table_name($table_status) {
	table_comment($table_status);
	return call_adminer('table_name', htmlspecialchars(strlen($table_status["Comment"]) ? $table_status["Comment"] : $table_status["Name"]), $table_status);
}

function adminer_field_name($field) {
	return call_adminer('field_name', htmlspecialchars(strlen($field["comment"]) ? $field["comment"] : $field["field"]), $field);
}

function adminer_select_links($table_status) {
	return call_adminer('select_links', "", $table_status);
}

function adminer_backward_keys($table) {
	global $dbh;
	$return = array();
	$result = $dbh->query("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = " . $dbh->quote(adminer_database()) . "
AND REFERENCED_TABLE_SCHEMA = " . $dbh->quote(adminer_database()) . "
AND REFERENCED_TABLE_NAME = " . $dbh->quote($table) . "
ORDER BY ORDINAL_POSITION"); //! requires MySQL 5
	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$return[$row["TABLE_NAME"]][$row["CONSTRAINT_NAME"]][$row["COLUMN_NAME"]] = $row["REFERENCED_COLUMN_NAME"];
		}
		$result->free();
	}
	return call_adminer('backward_keys', $return, $table);
}

function adminer_select_query($query) {
	return call_adminer('select_query', "<!-- " . str_replace("--", "--><!--", $query) . " -->\n", $query);
}

function adminer_row_description($table) {
	$return = "";
	// first varchar column
	foreach (fields($table) as $field) {
		if ($field["type"] == "varchar") {
			$return = idf_escape($field["field"]);
			break;
		}
	}
	return call_adminer('row_description', $return, $table);
}

function adminer_row_descriptions($rows, $foreign_keys) {
	global $dbh;
	$return = $rows;
	foreach ($rows[0] as $key => $val) {
		foreach ((array) $foreign_keys[$key] as $foreign_key) {
			if (count($foreign_key["source"]) == 1) {
				$id = idf_escape($foreign_key["target"][0]);
				$name = adminer_row_description($foreign_key["table"]);
				if (strlen($name)) {
					// find all used ids
					$ids = array();
					foreach ($rows as $row) {
						$ids[$row[$key]] = $dbh->quote($row[$key]);
					}
					// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
					$descriptions = array();
					$result = $dbh->query("SELECT $id, $name FROM " . idf_escape($foreign_key["table"]) . " WHERE $id IN (" . implode(", ", $ids) . ")");
					while ($row = $result->fetch_row()) {
						$descriptions[$row[0]] = $row[1];
					}
					$result->free();
					// use the descriptions
					foreach ($rows as $n => $row) {
						$return[$n][$key] = $descriptions[$row[$key]];
					}
					break;
				}
			}
		}
	}
	return call_adminer('row_descriptions', $return, $rows, $foreign_keys);
}

function adminer_select_val($val, $link, $field) {
	$return = ($val == "<i>NULL</i>" ? "&nbsp;" : $val);
	if (ereg('blob|binary', $field["type"]) && !is_utf8($val)) {
		$return = lang('%d byte(s)', strlen($val));
		if (ereg("^(GIF|\xFF\xD8\xFF|\x89\x50\x4E\x47\x0D\x0A\x1A\x0A)", $val)) { // GIF|JPG|PNG, getimagetype() works with filename
			$return = "<img src=\"$link\" alt='$return'>";
		}
	}
	return call_adminer('select_val', ($link ? "<a href=\"$link\">$return</a>" : $return), $val, $link);
}

function adminer_select_extra_display($email_fields) {
	global $confirm;
	if (call_adminer('select_extra_display', true, $email_fields) && $email_fields) {
		echo '<fieldset><legend><a href="#fieldset-email" onclick="return !toggle(\'fieldset-email\');">' . lang('E-mail') . "</a></legend><div id='fieldset-email' class='hidden'>\n";
		echo "<p>" . lang('From') . ": <input name='email_from'>\n";
		echo lang('Subject') . ": <input name='email_subject'>\n";
		echo "<p><textarea name='email_message' rows='15' cols='60'></textarea>\n";
		echo "<p>" . (count($email_fields) == 1 ? '<input type="hidden" name="email_field" value="' . htmlspecialchars(key($email_fields)) . '">' : '<select name="email_field">' . optionlist($email_fields) . '</select> ');
		echo "<input type='submit' name='email' value='" . lang('Send') . "'$confirm>\n";
		echo "</div></fieldset>\n";
	}
}

function adminer_select_extra_process($where) {
	global $dbh;
	if ($_POST["email"]) {
		$sent = 0;
		if ($_POST["all"] || $_POST["check"]) {
			$where_check = "(" . implode(") OR (", array_map('where_check', (array) $_POST["check"])) . ")";
			$field = idf_escape($_POST["email_field"]);
			$result = $dbh->query("SELECT DISTINCT $field FROM " . idf_escape($_GET["select"]) . " WHERE $field IS NOT NULL AND $field != ''" . ($where ? " AND " . implode(" AND ", $where) : "") . ($_POST["all"] ? "" : " AND ($where_check)"));
			while ($row = $result->fetch_row()) {
				$sent += mail($row[0], email_header($_POST["email_subject"]), $_POST["email_message"], "MIME-Version: 1.0\nContent-Type: text/plain; charset=utf-8\nContent-Transfer-Encoding: 8bit" . ($_POST["email_from"] ? "\nFrom: " . email_header($_POST["email_from"]) : ""));
			}
			$result->free();
		}
		redirect(remove_from_uri(), lang('%d e-mail(s) have been sent.', $sent));
	}
	return call_adminer('select_extra_process', false, $where);
}

function adminer_message_query($query) {
	return call_adminer('message_query', "<!--\n" . str_replace("--", "--><!--", $query) . "\n-->", $query);
}

function adminer_edit_functions($field) {
	return call_adminer('edit_functions', (isset($_GET["select"]) ? array("orig" => lang('original')) : array()) + array(""), $field);
}

function adminer_edit_input($table, $field) {
	global $dbh;
	$return = null;
	$foreign_keys = column_foreign_keys($table);
	foreach ((array) $foreign_keys[$field["field"]] as $foreign_key) {
		if (count($foreign_key["source"]) == 1) {
			$id = idf_escape($foreign_key["target"][0]);
			$name = adminer_row_description($foreign_key["table"]);
			if (strlen($name) && $dbh->result($dbh->query("SELECT COUNT(*) FROM " . idf_escape($foreign_key["table"]))) <= 1000) { // optionlist with more than 1000 options would be too big
				if ($field["null"]) {
					$return[""] = "";
				}
				$result = $dbh->query("SELECT $id, $name FROM " . idf_escape($foreign_key["table"]) . " ORDER BY 2");
				while ($row = $result->fetch_row()) {
					$return[$row[0]] = $row[1];
				}
				$result->free();
				break;
			}
		}
	}
	return call_adminer('edit_input', $return, $table, $field);
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
	if (!ereg('varchar|text', $field["type"]) && !strlen($value)) {
		$return = "NULL";
	} elseif (ereg('date|time', $field["type"]) && $value == "CURRENT_TIMESTAMP") {
		$return = $value;
	}
	return call_adminer('process_input', $return, $name, $field);
}

function adminer_navigation($missing) {
	global $SELF;
	if (call_adminer('navigation', true, $missing) && $missing != "auth") {
		?>
<form action="" method="post">
<p>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<?php
		if ($missing != "db") {
			$table_status = table_status();
			if (!$table_status) {
				echo "<p class='message'>" . lang('No tables.') . "\n";
			} else {
				echo "<p>\n";
				foreach ($table_status as $row) {
					$name = adminer_table_name($row);
					if (isset($row["Engine"]) && strlen($name)) { // ignore views and tables without name
						echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . "\">$name</a><br>\n";
					}
				}
			}
		}
	}
}
