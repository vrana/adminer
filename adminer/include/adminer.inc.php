<?php
// any method change in this file should be transferred to editor/include/adminer.inc.php and plugins/plugin.php

class Adminer {
	/** @var array operators used in select, null for all operators */
	var $operators;
	
	/** Name in title and navigation
	* @return string HTML code
	*/
	function name() {
		return "<a href='http://www.adminer.org/' id='h1'>Adminer</a>";
	}
	
	/** Connection parameters
	* @return array ($server, $username, $password)
	*/
	function credentials() {
		return array(SERVER, $_GET["username"], get_session("pwds"));
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
	
	/** Get cached list of databases
	* @param bool
	* @return array
	*/
	function databases($flush = true) {
		return get_databases($flush);
	}
	
	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout() {
		return 5;
	}
	
	/** Headers to send before HTML output
	* @return bool true to send security headers
	*/
	function headers() {
		return true;
	}
	
	/** Print HTML code inside <head>
	* @return bool true to link adminer.css if exists
	*/
	function head() {
		return true;
	}
	
	/** Print login form
	* @return null
	*/
	function loginForm() {
		global $drivers;
		?>
<table cellspacing="0">
<tr><th><?php echo lang('System'); ?><td><?php echo html_select("auth[driver]", $drivers, DRIVER, "loginDriver(this);"); ?>
<tr><th><?php echo lang('Server'); ?><td><input name="auth[server]" value="<?php echo h(SERVER); ?>" title="hostname[:port]" placeholder="localhost" autocapitalize="off">
<tr><th><?php echo lang('Username'); ?><td><input name="auth[username]" id="username" value="<?php echo h($_GET["username"]); ?>" autocapitalize="off">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="auth[password]">
<tr><th><?php echo lang('Database'); ?><td><input name="auth[db]" value="<?php echo h($_GET["db"]); ?>" autocapitalize="off">
</table>
<script type="text/javascript">
var username = document.getElementById('username');
focus(username);
username.form['auth[driver]'].onchange();
</script>
<?php
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
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
	* @return string HTML code, "" to ignore table
	*/
	function tableName($tableStatus) {
		return h($tableStatus["Name"]);
	}
	
	/** Field caption used in select and edit
	* @param array single field returned from fields()
	* @param int order of column in select
	* @return string HTML code, "" to ignore field
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
		if ($set !== null) {
			$links["edit"] = lang('New item');
		}
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($tableStatus["Name"]) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo "\n";
	}
	
	/** Get foreign keys for table
	* @param string
	* @return array same format as foreign_keys()
	*/
	function foreignKeys($table) {
		return foreign_keys($table);
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
		global $jush, $token;
		return "<form action='" . h(ME) . "sql=' method='post'><p><span onclick=\"return !selectEditSql(event, this, '" . lang('Execute') . "');\">"
			. "<code class='jush-$jush'>" . h(str_replace("\n", " ", $query)) . "</code>"
			. " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a>"
			. "</span><input type='hidden' name='token' value='$token'></p></form>\n"; // </p> - required for IE9 inline edit
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
	
	/** Get a link to use in select table
	* @param string raw value of the field
	* @param array single field returned from fields()
	* @return string or null to create the default link
	*/
	function selectLink($val, $field) {
	}
	
	/** Value printed in select table
	* @param string HTML-escaped value to print
	* @param string link to foreign key
	* @param array single field returned from fields()
	* @return string
	*/
	function selectVal($val, $link, $field) {
		$return = ($val === null ? "<i>NULL</i>" : (ereg("char|binary", $field["type"]) && !ereg("var", $field["type"]) ? "<code>$val</code>" : $val));
		if (ereg('blob|bytea|raw|file', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen(html_entity_decode($val, ENT_QUOTES)));
		}
		return ($link ? "<a href='" . h($link) . "'>$return</a>" : $return);
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
	* @param array result of selectColumnsProcess()[0]
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
			echo "(<select name='columns[$i][col]' onchange='selectFieldChange(this.form);'><option>" . optionlist($columns, $val["col"], true) . "</select>)</div>\n";
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
				echo " <input type='search' name='fulltext[$i]' value='" . h($_GET["fulltext"][$i]) . "' onchange='selectFieldChange(this.form);'>";
				echo checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL");
				echo "<br>\n";
			}
		}
		$_GET["where"] = (array) $_GET["where"];
		reset($_GET["where"]);
		$change_next = "this.nextSibling.onchange();";
		for ($i = 0; $i <= count($_GET["where"]); $i++) {
			list(, $val) = each($_GET["where"]);
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators))) {
				echo "<div><select name='where[$i][col]' onchange='$change_next'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, $val["col"], true) . "</select>";
				echo html_select("where[$i][op]", $this->operators, $val["op"], $change_next);
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "' onchange='" . ($val ? "selectFieldChange(this.form)" : "selectAddRow(this)") . ";' onsearch='selectSearchSearch(this);'></div>\n";
			}
		}
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
				echo "<div><select name='order[$i]' onchange='selectFieldChange(this.form);'><option>" . optionlist($columns, $val, true) . "</select>";
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang('descending')) . "</div>\n";
				$i++;
			}
		}
		echo "<div><select name='order[$i]' onchange='selectAddRow(this);'><option>" . optionlist($columns, null, true) . "</select>";
		echo "<label><input type='checkbox' name='desc[$i]' value='1'>" . lang('descending') . "</label></div>\n"; // not checkbox() to allow selectAddRow()
		echo "</div></fieldset>\n";
	}
	
	/** Print limit box in select
	* @param string result of selectLimitProcess()
	* @return null
	*/
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo "<input type='number' name='limit' class='size' value='" . h($limit) . "' onchange='selectFieldChange(this.form);'>";
		echo "</div></fieldset>\n";
	}
	
	/** Print text length box in select
	* @param string result of selectLengthProcess()
	* @return null
	*/
	function selectLengthPrint($text_length) {
		if ($text_length !== null) {
			echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}
	
	/** Print action box in select
	* @param array
	* @return null
	*/
	function selectActionPrint($indexes) {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo " <span id='noindex' title='" . lang('Full table scan') . "'></span>";
		echo "<script type='text/javascript'>\n";
		echo "var indexColumns = ";
		$columns = array();
		foreach ($indexes as $index) {
			if ($index["type"] != "FULLTEXT") {
				$columns[reset($index["columns"])] = 1;
			}
		}
		$columns[""] = 1;
		foreach ($columns as $key => $val) {
			json_row($key);
		}
		echo ";\n";
		echo "selectFieldChange(document.getElementById('form'));\n";
		echo "</script>\n";
		echo "</div></fieldset>\n";
	}
	
	/** Print command box in select
	* @return bool whether to print default commands
	*/
	function selectCommandPrint() {
		return !information_schema(DB);
	}
	
	/** Print import box in select
	* @return bool whether to print default import
	*/
	function selectImportPrint() {
		return !information_schema(DB);
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
		global $jush;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && $_GET["fulltext"][$i] != "") {
				$return[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $val) {
			if ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators)) {
				$cond = " $val[op]";
				if (ereg('IN$', $val["op"])) {
					$in = process_length($val["val"]);
					$cond .= " (" . ($in != "" ? $in : "NULL") . ")";
				} elseif ($val["op"] == "SQL") {
					$cond = " $val[val]"; // SQL injection
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
						$is_text = ereg('char|text|enum|set', $field["type"]);
						if ((is_numeric($val["val"]) || !ereg('(^|[^o])int|float|double|decimal|bit', $field["type"]))
							&& (!ereg("[\x80-\xFF]", $val["val"]) || $is_text)
						) {
							$name = idf_escape($name);
							$cols[] = ($jush == "sql" && $is_text && !ereg('^utf8', $field["collation"]) ? "CONVERT($name USING utf8)" : $name);
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
		return (isset($_GET["limit"]) ? $_GET["limit"] : "50");
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
	
	/** Build SQL query used in select
	* @param array result of selectColumnsProcess()[0]
	* @param array result of selectSearchProcess()
	* @param array result of selectColumnsProcess()[1]
	* @param array result of selectOrderProcess()
	* @param int result of selectLimitProcess()
	* @param int index of page starting at zero
	* @return string empty string to use default query
	*/
	function selectQueryBuild($select, $where, $group, $order, $limit, $page) {
		return "";
	}
	
	/** Query printed after execution in the message
	* @param string executed query
	* @return string
	*/
	function messageQuery($query) {
		global $jush;
		restart_session();
		$history = &get_session("queries");
		$id = "sql-" . count($history[$_GET["db"]]);
		if (strlen($query) > 1e6) {
			$query = ereg_replace('[\x80-\xFF]+$', '', substr($query, 0, 1e6)) . "\n..."; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
		}
		$history[$_GET["db"]][] = array($query, time()); // not DB - $_GET["db"] is changed in database.inc.php //! respect $_GET["ns"]
		return " <span class='time'>" . @date("H:i:s") . "</span> <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre><code class='jush-$jush'>" . shorten_utf8($query, 1000) . '</code></pre><p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a></div>'; // @ - time zone may be not set
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
				if ($key && !ereg('set|blob|bytea|raw|file', $field["type"])) {
					$return .= "/SQL";
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
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='-1' checked><i>" . lang('original') . "</i></label> " : "")
				. ($field["null"] ? "<label><input type='radio'$attrs value=''" . ($value !== null || isset($_GET["select"]) ? "" : " checked") . "><i>NULL</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, 0) // 0 - empty
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
		if ($function == "SQL") {
			return $value; // SQL injection
		}
		$name = $field["field"];
		$return = q($value);
		if (ereg('^(now|getdate|uuid)$', $function)) {
			$return = "$function()";
		} elseif (ereg('^current_(date|timestamp)$', $function)) {
			$return = $function;
		} elseif (ereg('^([+-]|\\|\\|)$', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (ereg('^[+-] interval$', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : $return);
		} elseif (ereg('^(addtime|subtime|concat)$', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (ereg('^(md5|sha1|password|encrypt)$', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}
	
	/** Returns export output options
	* @return array
	*/
	function dumpOutput() {
		$return = array('text' => lang('open'), 'file' => lang('save'));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}
	
	/** Returns export format options
	* @return array empty to disable export
	*/
	function dumpFormat() {
		return array('sql' => 'SQL', 'csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}
	
	/** Export database structure
	* @param string
	* @return null prints data
	*/
	function dumpDatabase($db) {
	}
	
	/** Export table structure
	* @param string
	* @param string
	* @param int 0 table, 1 view, 2 temporary view table
	* @return null prints data
	*/
	function dumpTable($table, $style, $is_view = 0) {
		if ($_POST["format"] != "sql") {
			echo "\xef\xbb\xbf"; // UTF-8 byte order mark
			if ($style) {
				dump_csv(array_keys(fields($table)));
			}
		} elseif ($style) {
			if ($is_view == 2) {
				$fields = array();
				foreach (fields($table) as $name => $field) {
					$fields[] = idf_escape($name) . " $field[full_type]";
				}
				$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
			} else {
				$create = create_sql($table, $_POST["auto_increment"]);
			}
			if ($create) {
				if ($style == "DROP+CREATE" || $is_view == 1) {
					echo "DROP " . ($is_view == 2 ? "VIEW" : "TABLE") . " IF EXISTS " . table($table) . ";\n";
				}
				if ($is_view == 1) {
					$create = remove_definer($create);
				}
				echo "$create;\n\n";
			}
		}
	}
	
	/** Export table data
	* @param string
	* @param string
	* @param string
	* @return null prints data
	*/
	function dumpData($table, $style, $query) {
		global $connection, $jush;
		$max_packet = ($jush == "sqlite" ? 0 : 1048576); // default, minimum is 1024
		if ($style) {
			if ($_POST["format"] == "sql") {
				if ($style == "TRUNCATE+INSERT") {
					echo truncate_sql($table) . ";\n";
				}
				$fields = fields($table);
			}
			$result = $connection->query($query, 1); // 1 - MYSQLI_USE_RESULT //! enum and set as numbers
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				while ($row = $result->$fetch_function()) {
					if (!$keys) {
						$values = array();
						foreach ($row as $val) {
							$field = $result->fetch_field();
							$keys[] = $field->name;
							$key = idf_escape($field->name);
							$values[] = "$key = VALUES($key)";
						}
						$suffix = ($style == "INSERT+UPDATE" ? "\nON DUPLICATE KEY UPDATE " . implode(", ", $values) : "") . ";\n";
					}
					if ($_POST["format"] != "sql") {
						if ($style == "table") {
							dump_csv($keys);
							$style = "INSERT";
						}
						dump_csv($row);
					} else {
						if (!$insert) {
							$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('idf_escape', $keys)) . ") VALUES";
						}
						foreach ($row as $key => $val) {
							$field = $fields[$key];
							$row[$key] = ($val !== null
								? unconvert_field($field, ereg('(^|[^o])int|float|double|decimal', $field["type"]) && $val != '' ? $val : q($val))
								: "NULL"
							);
						}
						$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet) { // 4 - length specification
							$buffer .= ",$s";
						} else {
							echo $buffer . $suffix;
							$buffer = $insert . $s;
						}
					}
				}
				if ($buffer) {
					echo $buffer . $suffix;
				}
			} elseif ($_POST["format"] == "sql") {
				echo "-- " . str_replace("\n", " ", $connection->error) . "\n";
			}
		}
	}
	
	/** Set export filename
	* @param string
	* @return string filename without extension
	*/
	function dumpFilename($identifier) {
		return friendly_url($identifier != "" ? $identifier : (SERVER != "" ? SERVER : "localhost"));
	}
	
	/** Send headers for export
	* @param string
	* @param bool
	* @return string extension
	*/
	function dumpHeaders($identifier, $multi_table = false) {
		$output = $_POST["output"];
		$ext = (ereg('sql', $_POST["format"]) ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
		header("Content-Type: " .
			($output == "gz" ? "application/x-gzip" :
			($ext == "tar" ? "application/x-tar" :
			($ext == "sql" || $output != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
		)));
		if ($output == "gz") {
			ob_start('gzencode', 1e6);
		}
		return $ext;
	}
	
	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage() {
		echo '<p>' . ($_GET["ns"] == "" ? '<a href="' . h(ME) . 'database=">' . lang('Alter database') . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema')) . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . lang('Database schema') . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . lang('Privileges') . "</a>\n" : "");
		return true;
	}
	
	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	* @return null
	*/
	function navigation($missing) {
		global $VERSION, $token, $jush, $drivers;
		?>
<h1>
<?php echo $this->name(); ?> <span class="version"><?php echo $VERSION; ?></span>
<a href="http://www.adminer.org/#download" id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing == "auth") {
			$first = true;
			foreach ((array) $_SESSION["pwds"] as $driver => $servers) {
				foreach ($servers as $server => $usernames) {
					foreach ($usernames as $username => $password) {
						if ($password !== null) {
							if ($first) {
								echo "<p id='logins' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n";
								$first = false;
							}
							$dbs = $_SESSION["db"][$driver][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								echo "<a href='" . h(auth_url($driver, $server, $username, $db)) . "'>($drivers[$driver]) " . h($username . ($server != "" ? "@$server" : "") . ($db != "" ? " - $db" : "")) . "</a><br>\n";
							}
						}
					}
				}
			}
		} else {
			?>
<form action="" method="post">
<p class="logout">
<?php
			if (DB == "" || !$missing) {
				echo "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"])) . ">" . lang('SQL command') . "</a>\n";
				if (support("dump")) {
					echo "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . lang('Dump') . "</a>\n";
				}
			}
			?>
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" id="logout">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</p>
</form>
<?php
			$this->databasesPrint($missing);
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				echo '<p><a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . lang('Create new table') . "</a>\n";
				$tables = table_status('', true);
				if (!$tables) {
					echo "<p class='message'>" . lang('No tables.') . "\n";
				} else {
					$this->tablesPrint($tables);
					$links = array();
					foreach ($tables as $table => $type) {
						$links[] = preg_quote($table, '/');
					}
					echo "<script type='text/javascript'>\n";
					echo "var jushLinks = { $jush: [ '" . js_escape(ME) . "table=\$&', /\\b(" . implode("|", $links) . ")\\b/g ] };\n";
					foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
						echo "jushLinks.$val = jushLinks.$jush;\n";
					}
					echo "</script>\n";
				}
			}
		}
	}
	
	/** Prints databases list in menu
	* @param string
	* @return null
	*/
	function databasesPrint($missing) {
		global $connection;
		$databases = $this->databases();
		?>
<form action="">
<p id="dbs">
<?php
hidden_fields_get();
echo ($databases
	? '<select name="db" onmousedown="dbMouseDown(event, this);" onchange="dbChange(this);">' . optionlist(array("" => "(" . lang('database') . ")") + $databases, DB) . '</select>'
	: '<input name="db" value="' . h(DB) . '" autocapitalize="off">'
);
?>
<input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?>>
<?php
		if ($missing != "db" && DB != "" && $connection->select_db(DB)) {
			if (support("scheme")) {
				echo "<br>" . html_select("ns", array("" => "(" . lang('schema') . ")") + schemas(), $_GET["ns"], "this.form.submit();");
				if ($_GET["ns"] != "") {
					set_schema($_GET["ns"]);
				}
			}
		}
		echo (isset($_GET["sql"]) ? '<input type="hidden" name="sql" value="">'
			: (isset($_GET["schema"]) ? '<input type="hidden" name="schema" value="">'
			: (isset($_GET["dump"]) ? '<input type="hidden" name="dump" value="">'
		: "")));
		echo "</p></form>\n";
	}
	
	/** Prints table list in menu
	* @param array result of table_status('', true)
	* @return null
	*/
	function tablesPrint($tables) {
		echo "<p id='tables' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n";
		foreach ($tables as $table => $status) {
			echo '<a href="' . h(ME) . 'select=' . urlencode($table) . '"' . bold($_GET["select"] == $table) . ">" . lang('select') . "</a> ";
			echo '<a href="' . h(ME) . 'table=' . urlencode($table) . '"' . bold($_GET["table"] == $table) . " title='" . lang('Show structure') . "'>" . $this->tableName($status) . "</a><br>\n";
		}
	}
	
}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
if ($adminer->operators === null) {
	$adminer->operators = $operators;
}
