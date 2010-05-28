<?php
class Adminer {
	/** @var array operators used in select, null for all operators */
	var $operators;
	
	/** Name in title and navigation
	* @return string
	*/
	function name() {
		return "Adminer";
	}
	
	/** Connection parameters
	* @return array ($server, $username, $password)
	*/
	function credentials() {
		return array(SERVER, $_GET["username"], get_session("passwords"));
	}
	
	/** Get key used for permanent login
	* @return string cryptic string which gets combined with password
	*/
	function permanentLogin() {
		return password_file();
	}
	
	/** Identifier of selected database
	* @return string
	*/
	function database() {
		// should be used everywhere instead of DB
		return DB;
	}
	
	/** Print login form
	* @return null
	*/
	function loginForm() {
		global $drivers, $possible_drivers;
		?>
<table cellspacing="0">
<tr><th><?php echo lang('System'); ?><td><?php echo html_select("driver", $drivers, DRIVER); ?>
<tr><th><?php echo lang('Server'); ?><td><input name="server" value="<?php echo h(SERVER); ?>">
<tr><th><?php echo lang('Username'); ?><td><input id="username" name="username" value="<?php echo h($_GET["username"]); ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<script type="text/javascript">
document.getElementById('username').focus();
</script>
<?php
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("permanent", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}
	
	/** Authorize the user
	* @param string
	* @param string
	* @return bool
	*/
	function login($login, $password) {
		return true;
	}
	
	/** Table caption used in navigation and headings
	* @param array result of SHOW TABLE STATUS
	* @return string
	*/
	function tableName($tableStatus) {
		return h($tableStatus["Name"]);
	}
	
	/** Field caption used in select and edit
	* @param array single field returned from fields()
	* @param int order of column in select
	* @return string
	*/
	function fieldName($field, $order = 0) {
		return '<span title="' . h($field["full_type"]) . '">' . h($field["field"]) . '</span>';
	}
	
	/** Print links after select heading
	* @param array result of SHOW TABLE STATUS
	* @param string new item options, NULL for no new item
	* @return null
	*/
	function selectLinks($tableStatus, $set = "") {
		echo '<p class="tabs">';
		$links = array("select" => lang('Select data'), "table" => lang('Show structure'));
		if (is_view($tableStatus)) {
			$links["view"] = lang('Alter view');
		} else {
			$links["create"] = lang('Alter table');
		}
		if (isset($set)) {
			$links["edit"] = lang('New item');
		}
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($tableStatus["Name"]) . ($key == "edit" ? $set : "") . "'>" . bold($val, isset($_GET[$key])) . "</a>";
		}
		echo "\n";
	}
	
	/** Find backward keys for table
	* @param string
	* @param string
	* @return array $return[$target_table]["keys"][$key_name][$target_column] = $source_column; $return[$target_table]["name"] = $this->tableName($target_table);
	*/
	function backwardKeys($table, $tableName) {
		return array();
	}
	
	/** Print backward keys for row
	* @param array result of $this->backwardKeys()
	* @param array
	* @return null
	*/
	function backwardKeysPrint($backwardKeys, $row) {
	}
	
	/** Query printed in select before execution
	* @param string query to be executed
	* @return string
	*/
	function selectQuery($query) {
		global $jush;
		return "<p><code class='jush-$jush'>" . h(str_replace("\n", " ", $query)) . "</code> <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a>\n";
	}
	
	/** Description of a row in a table
	* @param string
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription($table) {
		return "";
	}
	
	/** Get descriptions of selected data
	* @param array all data to print
	* @param array
	* @return array
	*/
	function rowDescriptions($rows, $foreignKeys) {
		return $rows;
	}
	
	/** Value printed in select table
	* @param string HTML-escaped value to print
	* @param string link to foreign key
	* @param array single field returned from fields()
	* @return string
	*/
	function selectVal($val, $link, $field) {
		$return = ($val != "<i>NULL</i>" && $field["type"] == "char" ? "<code>$val</code>" : $val);
		if (ereg('binary|blob|bytea|raw|file', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($val));
		}
		return ($link ? "<a href='$link'>$return</a>" : $return);
	}
	
	/** Value conversion used in select and edit
	* @param string
	* @param array single field returned from fields()
	* @return string
	*/
	function editVal($val, $field) {
		return $val;
	}
	
	/** Print columns box in select
	* @param array result of selectColumnsProcess()
	* @param array selectable columns
	* @return null
	*/
	function selectColumnsPrint($select, $columns) {
		global $functions, $grouping;
		print_fieldset("select", lang('Select'), $select);
		$i = 0;
		$fun_group = array(lang('Functions') => $functions, lang('Aggregation') => $grouping);
		foreach ($select as $key => $val) {
			$val = $_GET["columns"][$key];
			echo "<div>" . html_select("columns[$i][fun]", array(-1 => "") + $fun_group, $val["fun"]);
			echo "(<select name='columns[$i][col]'><option>" . optionlist($columns, $val["col"], true) . "</select>)</div>\n";
			$i++;
		}
		echo "<div>" . html_select("columns[$i][fun]", array(-1 => "") + $fun_group, "", "this.nextSibling.nextSibling.onchange();");
		echo "(<select name='columns[$i][col]' onchange='selectAddRow(this);'><option>" . optionlist($columns, null, true) . "</select>)</div>\n";
		echo "</div></fieldset>\n";
	}
	
	/** Print search box in select
	* @param array result of selectSearchProcess()
	* @param array selectable columns
	* @param array
	* @return null
	*/
	function selectSearchPrint($where, $columns, $indexes) {
		print_fieldset("search", lang('Search'), $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "(<i>" . implode("</i>, <i>", array_map('h', $index["columns"])) . "</i>) AGAINST";
				echo " <input name='fulltext[$i]' value='" . h($_GET["fulltext"][$i]) . "'>";
				echo checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL");
				echo "<br>\n";
			}
		}
		$i = 0;
		foreach ((array) $_GET["where"] as $val) {
			if ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators)) {
				echo "<div><select name='where[$i][col]'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, $val["col"], true) . "</select>";
				echo html_select("where[$i][op]", $this->operators, $val["op"]);
				echo "<input name='where[$i][val]' value='" . h($val["val"]) . "'></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' onchange='selectAddRow(this);'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, null, true) . "</select>";
		echo html_select("where[$i][op]", $this->operators);
		echo "<input name='where[$i][val]'></div>\n";
		echo "</div></fieldset>\n";
	}
	
	/** Print order box in select
	* @param array result of selectOrderProcess()
	* @param array selectable columns
	* @param array
	* @return null
	*/
	function selectOrderPrint($order, $columns, $indexes) {
		print_fieldset("sort", lang('Sort'), $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if (isset($columns[$val])) {
				echo "<div><select name='order[$i]'><option>" . optionlist($columns, $val, true) . "</select>";
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang('descending')) . "</div>\n";
				$i++;
			}
		}
		echo "<div><select name='order[$i]' onchange='selectAddRow(this);'><option>" . optionlist($columns, null, true) . "</select>";
		echo checkbox("desc[$i]", 1, 0, lang('descending')) . "</div>\n";
		echo "</div></fieldset>\n";
	}
	
	/** Print limit box in select
	* @param string result of selectLimitProcess()
	* @return null
	*/
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo "<input name='limit' size='3' value='" . h($limit) . "'>";
		echo "</div></fieldset>\n";
	}
	
	/** Print text length box in select
	* @param string result of selectLengthProcess()
	* @return null
	*/
	function selectLengthPrint($text_length) {
		if (isset($text_length)) {
			echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
			echo '<input name="text_length" size="3" value="' . h($text_length) . '">';
			echo "</div></fieldset>\n";
		}
	}
	
	/** Print action box in select
	* @return null
	*/
	function selectActionPrint() {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo "</div></fieldset>\n";
	}
	
	/** Print extra text in the end of a select form
	* @param array fields holding e-mails
	* @param array selectable columns
	* @return null
	*/
	function selectEmailPrint($emailFields, $columns) {
	}
	
	/** Process columns box in select
	* @param array selectable columns
	* @param array
	* @return array (array(select_expressions), array(group_expressions))
	*/
	function selectColumnsProcess($columns, $indexes) {
		global $functions, $grouping;
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || (isset($columns[$val["col"]]) && (!$val["fun"] || in_array($val["fun"], $functions) || in_array($val["fun"], $grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], (isset($columns[$val["col"]]) ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], $grouping)) {
					$group[] = $select[$key];
				}
			}
		}
		return array($select, $group);
	}
	
	/** Process search box in select
	* @param array
	* @param array
	* @return array expressions to join by AND
	*/
	function selectSearchProcess($fields, $indexes) {
		global $connection, $jush;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && $_GET["fulltext"][$i] != "") {
				$return[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . $connection->quote($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $val) {
			if ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators)) {
				$cond = " $val[op]";
				if (ereg('IN$', $val["op"])) {
					$in = process_length($val["val"]);
					$cond .= " (" . ($in != "" ? $in : "NULL") . ")";
				} elseif ($val["op"] == "LIKE %%") {
					$cond = " LIKE " . $this->processInput($fields[$val["col"]], "%$val[val]%");
				} elseif (!ereg('NULL$', $val["op"])) {
					$cond .= " " . $this->processInput($fields[$val["col"]], $val["val"]);
				}
				if ($val["col"] != "") {
					$return[] = idf_escape($val["col"]) . $cond;
				} else {
					// find anywhere
					$cols = array();
					foreach ($fields as $name => $field) {
						if (is_numeric($val["val"]) || !ereg('int|float|double|decimal', $field["type"])) {
							$name = idf_escape($name);
							$cols[] = ($jush == "sql" && ereg('char|text|enum|set', $field["type"]) && !ereg('^utf8', $field["collation"]) ? "CONVERT($name USING utf8)" : $name);
						}
					}
					$return[] = ($cols ? "(" . implode("$cond OR ", $cols) . "$cond)" : "0");
				}
			}
		}
		return $return;
	}
	
	/** Process order box in select
	* @param array
	* @param array
	* @return array expressions to join by comma
	*/
	function selectOrderProcess($fields, $indexes) {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if (isset($fields[$val]) || preg_match('~^((COUNT\\(DISTINCT |[A-Z0-9_]+\\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\\)|COUNT\\(\\*\\))$~', $val)) { //! MS SQL uses []
				$return[] = (isset($fields[$val]) ? idf_escape($val) : $val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
			}
		}
		return $return;
	}
	
	/** Process limit box in select
	* @return string expression to use in LIMIT, will be escaped
	*/
	function selectLimitProcess() {
		return (isset($_GET["limit"]) ? $_GET["limit"] : "30");
	}
	
	/** Process length box in select
	* @return string number of characters to shorten texts, will be escaped
	*/
	function selectLengthProcess() {
		return (isset($_GET["text_length"]) ? $_GET["text_length"] : "100");
	}
	
	/** Process extras in select form
	* @param array AND conditions
	* @param array
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess($where, $foreignKeys) {
		return false;
	}
	
	/** Query printed after execution in the message
	* @param string executed query
	* @return string
	*/
	function messageQuery($query) {
		global $jush;
		restart_session();
		$id = "sql-" . count($_SESSION["messages"]);
		$history = &get_session("history");
		$history[$_GET["db"]][] = (strlen($query) > 1e6 // not DB - reset in drop database
			? ereg_replace('[\x80-\xFF]+$', '', substr($query, 0, 1e6)) . "\n..." // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
			: $query
		); //! respect $_GET["ns"]
		return " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-$jush'>" . shorten_utf8($query, 1000) . '</pre><p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a></div>';
	}
	
	/** Functions displayed in edit form
	* @param array single field from fields()
	* @return array
	*/
	function editFunctions($field) {
		global $edit_functions;
		$return = ($field["null"] ? "NULL/" : "");
		foreach ($edit_functions as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && (isset($_GET["select"]) || where($_GET)))) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || ereg($pattern, $field["type"])) {
						$return .= "/$val";
					}
				}
			}
		}
		return explode("/", $return);
	}
	
	/** Get options to display edit field
	* @param string table name
	* @param array single field from fields()
	* @param string attributes to use inside the tag
	* @param string
	* @return string custom input field or empty string for default
	*/
	function editInput($table, $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			return ($field["null"] ? "<label><input type='radio'$attrs value=''" . (isset($value) || isset($_GET["select"]) ? "" : " checked") . "><i>NULL</i></label> " : "")
				. "<label><input type='radio'$attrs value='0'" . ($value === 0 ? " checked" : "") . "><i>" . lang('empty') . "</i></label>"
			;
		}
		return "";
	}
	
	/** Process sent input
	* @param array single field from fields()
	* @param string
	* @param string
	* @return string expression to use in a query
	*/
	function processInput($field, $value, $function = "") {
		global $connection;
		$name = $field["field"];
		$return = $connection->quote($value);
		if (ereg('^(now|getdate|uuid)$', $function)) {
			$return = "$function()";
		} elseif (ereg('^current_(date|timestamp)$', $function)) {
			$return = $function;
		} elseif (ereg('^([+-]|\\|\\|)$', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (ereg('^[+-] interval$', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^([0-9]+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : $return);
		} elseif (ereg('^(addtime|subtime|concat)$', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (ereg('^(md5|sha1|password|encrypt)$', $function)) {
			$return = "$function($return)";
		}
		return $return;
	}
	
	/** Returns export output options
	* @param bool generate select (otherwise radio)
	* @param string
	* @return string
	*/
	function dumpOutput($select, $value = "") {
		$return = array('text' => lang('open'), 'file' => lang('save'));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		if (function_exists('bzcompress')) {
			$return['bz2'] = 'bzip2';
		}
		// ZipArchive requires temporary file, ZIP can be created by gzcompress - see PEAR File_Archive
		return html_select("output", $return, $value, $select);
	}
	
	/** Returns export format options
	* @param bool generate select (otherwise radio)
	* @param string
	* @return string
	*/
	function dumpFormat($select, $value = "") {
		return html_select("format", array('sql' => 'SQL', 'csv' => 'CSV,', 'csv;' => 'CSV;'), $value, $select);
	}
	
	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection or "db" if there is no database selected
	* @return null
	*/
	function navigation($missing) {
		global $VERSION, $connection, $token, $jush, $drivers;
		?>
<h1>
<a href="http://www.adminer.org/" id="h1"><?php echo $this->name(); ?></a>
<span class="version"><?php echo $VERSION; ?></span>
<a href="http://www.adminer.org/#download" id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing == "auth") {
			$first = true;
			foreach ((array) $_SESSION["passwords"] as $driver => $servers) {
				foreach ($servers as $server => $usernames) {
					foreach ($usernames as $username => $password) {
						if (isset($password)) {
							if ($first) {
								echo "<p>\n";
								$first = false;
							}
							echo "<a href='" . h(auth_url($driver, $server, $username)) . "'>($drivers[$driver]) " . h($username . ($server != "" ? "@$server" : "")) . "</a><br>\n";
						}
					}
				}
			}
		} else {
			$databases = get_databases();
			?>
<form action="" method="post">
<p class="logout">
<a href="<?php echo h(ME); ?>sql="><?php echo bold(lang('SQL command'), isset($_GET["sql"])); ?></a>
<a href="<?php echo h(ME); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo bold(lang('Dump'), isset($_GET["dump"])); ?></a>
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<form action="">
<p>
<?php hidden_fields_get(); ?>
<?php echo ($databases ? html_select("db", array("" => "(" . lang('database') . ")") + $databases, DB, "this.form.submit();") : '<input name="db" value="' . h(DB) . '">'); ?>
<input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?>>
<?php
			if ($missing != "db" && DB != "" && $connection->select_db(DB)) {
				if (support("scheme")) {
					echo "<br>" . html_select("ns", array("" => "(" . lang('schema') . ")") + schemas(), $_GET["ns"], "this.form.submit();");
					if ($_GET["ns"] != "") {
						set_schema($_GET["ns"]);
					}
				}
				if ($_GET["ns"] !== "") {
					$tables = tables_list();
					if (!$tables) {
						echo "<p class='message'>" . lang('No tables.') . "\n";
					} else {
						$this->tablesPrint($tables);
						$links = array();
						foreach ($tables as $table => $type) {
							$links[] = preg_quote($table, '/');
						}
						echo "<script type='text/javascript'>\n";
						echo "var jushLinks = { $jush: [ '" . addcslashes(h(ME), "\\'/") . "table=\$&', /\\b(" . implode("|", $links) . ")\\b/g ] };\n";
						foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
							echo "jushLinks.$val = jushLinks.$jush;\n";
						}
						echo "</script>\n";
					}
					echo '<p><a href="' . h(ME) . 'create=">' . bold(lang('Create new table'), $_GET["create"] === "") . "</a>\n";
				}
			}
			echo (isset($_GET["sql"]) ? '<input type="hidden" name="sql" value="">'
				: (isset($_GET["schema"]) ? '<input type="hidden" name="schema" value="">'
				: (isset($_GET["dump"]) ? '<input type="hidden" name="dump" value="">'
			: "")));
			echo "</p></form>\n";
		}
	}
	
	/** Prints table list in menu
	* @param array
	* @return null
	*/
	function tablesPrint($tables) {
		echo "<p id='tables'>\n";
		foreach ($tables as $table => $type) {
			echo '<a href="' . h(ME) . 'select=' . urlencode($table) . '">' . bold(lang('select'), $_GET["select"] == $table) . '</a> ';
			echo '<a href="' . h(ME) . 'table=' . urlencode($table) . '">' . bold($this->tableName(array("Name" => $table)), $_GET["table"] == $table) . "</a><br>\n"; //! Adminer::tableName may work with full table status
		}
	}
	
}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
if (!isset($adminer->operators)) {
	$adminer->operators = $operators;
}
