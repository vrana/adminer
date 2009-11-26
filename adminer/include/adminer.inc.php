<?php
class Adminer {
	var $functions = array("char_length", "from_unixtime", "hex", "lower", "round", "sec_to_time", "time_to_sec", "unix_timestamp", "upper");
	var $grouping = array("avg", "count", "count distinct", "group_concat", "max", "min", "sum");
	var $operators = array("=", "<", ">", "<=", ">=", "!=", "LIKE", "REGEXP", "IN", "IS NULL", "NOT LIKE", "NOT REGEXP", "NOT IN", "IS NOT NULL");
	
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
		return array($_GET["server"], $_SESSION["usernames"][$_GET["server"]], $_SESSION["passwords"][$_GET["server"]]);
	}
	
	/** Identifier of selected database
	* @return string
	*/
	function database() {
		// should be used everywhere instead of DB
		return DB;
	}
	
	/** Print login form
	* @param string
	* @return null
	*/
	function loginForm($username) {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Server'); ?><td><input name="server" value="<?php echo h($_GET["server"]); ?>">
<tr><th><?php echo lang('Username'); ?><td><input name="username" value="<?php echo h($username); ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<?php
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
		$links = array("select" => lang('Select table'), "table" => lang('Table structure'));
		if (isset($tableStatus["Rows"])) {
			$links["create"] = lang('Alter table');
		} else {
			$links["view"] = lang('Alter view');
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
		return "<p><code class='jush-sql'>" . h($query) . "</code> <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a></p>\n";
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
		if (ereg('blob|binary', $field["type"]) && !is_utf8($val)) {
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
		print_fieldset("select", lang('Select'), $select);
		$i = 0;
		$fun_group = array(lang('Functions') => $this->functions, lang('Aggregation') => $this->grouping);
		foreach ($select as $key => $val) {
			$val = $_GET["columns"][$key];
			echo "<div>" . html_select("columns[$i][fun]", array(-1 => "") + $fun_group, $val["fun"]);
			echo "<select name='columns[$i][col]'><option>" . optionlist($columns, $val["col"], true) . "</select></div>\n";
			$i++;
		}
		echo "<div>" . html_select("columns[$i][fun]", array(-1 => "") + $fun_group, "", "this.nextSibling.onchange();");
		echo "<select name='columns[$i][col]' onchange='select_add_row(this);'><option>" . optionlist($columns, null, true) . "</select></div>\n";
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
			if (strlen("$val[col]$val[val]") && in_array($val["op"], $this->operators)) {
				echo "<div><select name='where[$i][col]'><option value=''>" . lang('(anywhere)') . optionlist($columns, $val["col"], true) . "</select>";
				echo html_select("where[$i][op]", $this->operators, $val["op"]);
				echo "<input name='where[$i][val]' value='" . h($val["val"]) . "'></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' onchange='select_add_row(this);'><option value=''>" . lang('(anywhere)') . optionlist($columns, null, true) . "</select>";
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
		echo "<div><select name='order[$i]' onchange='select_add_row(this);'><option>" . optionlist($columns, null, true) . "</select>";
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
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || (isset($columns[$val["col"]]) && (!$val["fun"] || in_array($val["fun"], $this->functions) || in_array($val["fun"], $this->grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], (isset($columns[$val["col"]]) ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], $this->grouping)) {
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
		global $connection;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && strlen($_GET["fulltext"][$i])) {
				$return[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . $connection->quote($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $val) {
			if (strlen("$val[col]$val[val]") && in_array($val["op"], $this->operators)) {
				$in = process_length($val["val"]);
				$cond = " $val[op]" . (ereg('NULL$', $val["op"]) ? "" : (ereg('IN$', $val["op"]) ? " (" . (strlen($in) ? $in : "NULL") . ")" : " " . $this->processInput($fields[$val["col"]], $val["val"])));
				if (strlen($val["col"])) {
					$return[] = idf_escape($val["col"]) . $cond;
				} else {
					// find anywhere
					$cols = array();
					foreach ($fields as $name => $field) {
						if (is_numeric($val["val"]) || !ereg('int|float|double|decimal', $field["type"])) {
							$cols[] = $name;
						}
					}
					$return[] = ($cols ? "(" . implode("$cond OR ", array_map('idf_escape', $cols)) . "$cond)" : "0");
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
			if (isset($fields[$val]) || preg_match('~^((COUNT\\(DISTINCT |[A-Z0-9_]+\\()`(?:[^`]|``)+`\\)|COUNT\\(\\*\\))$~', $val)) {
				$return[] = idf_escape($val) . (isset($_GET["desc"][$key]) ? " DESC" : "");
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
		restart_session();
		$id = "sql-" . count($_SESSION["messages"]);
		$_SESSION["history"][$_GET["server"]][DB][] = $query;
		return " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-sql'>" . shorten_utf8($query, 1000) . '</pre><a href="' . h(ME . 'sql=&history=' . (count($_SESSION["history"][$_GET["server"]][DB]) - 1)) . '">' . lang('Edit') . '</a></div>';
	}
	
	/** Functions displayed in edit form
	* @param array single field from fields()
	* @return array
	*/
	function editFunctions($field) {
		$return = array("");
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
			if (ereg('char|text', $field["type"])) {
				$return[] = "concat";
			}
		}
		if ($field["null"]) {
			array_unshift($return, "NULL");
		}
		return $return;
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
			return ($field["null"] ? "<label><input type='radio'$attrs value=''" . (isset($value) || isset($_GET["select"]) ? "" : " checked") . "><em>NULL</em></label> " : "")
				. "<input type='radio'$attrs value='0'" . ($value === 0 ? " checked" : "") . ">"
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
		if (ereg('^(now|uuid)$', $function)) {
			$return = "$function()";
		} elseif (ereg('^[+-]$', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (ereg('^[+-] interval$', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^([0-9]+|'[0-9.: -]') [A-Z_]+$~i", $value) ? $value : $return);
		} elseif (ereg('^(addtime|subtime|concat)$', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (ereg('^(md5|sha1|password)$', $function)) {
			$return = "$function($return)";
		}
		return $return;
	}
	
	/** Returns export output options
	* @param bool generate select (otherwise radio)
	* @return string
	*/
	function dumpOutput($select) {
		$return = array('text' => lang('open'), 'file' => lang('save'));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		if (function_exists('bzcompress')) {
			$return['bz2'] = 'bzip2';
		}
		// ZipArchive requires temporary file, ZIP can be created by gzcompress - see PEAR File_Archive
		return html_select("output", $return, "text", $select);
	}
	
	/** Returns export format options
	* @param bool generate select (otherwise radio)
	* @return string
	*/
	function dumpFormat($select) {
		return html_select("format", array('sql' => 'SQL', 'csv' => 'CSV'), "sql", $select);
	}
	
	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection or "db" if there is no database selected
	* @return null
	*/
	function navigation($missing) {
		global $VERSION, $connection;
		?>
<h1>
<a href="http://www.adminer.org/" id="h1"><?php echo $this->name(); ?></a>
<span class="version"><?php echo $VERSION; ?></span>
<a href="http://www.adminer.org/#download" id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing != "auth") {
			$databases = get_databases();
			?>
<form action="" method="post">
<p class="logout">
<a href="<?php echo h(ME); ?>sql="><?php echo bold(lang('SQL command'), isset($_GET["sql"])); ?></a>
<a href="<?php echo h(ME); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo bold(lang('Dump'), isset($_GET["dump"])); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<form action="">
<p>
<?php if (SID) { ?><input type="hidden" name="<?php echo session_name(); ?>" value="<?php echo h(session_id()); ?>"><?php } ?>
<?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo h($_GET["server"]); ?>"><?php } ?>
<?php echo ($databases ? html_select("db", array("" => "(" . lang('database') . ")") + $databases, DB, "this.form.submit();") : '<input name="db" value="' . h(DB) . '">'); ?>
<?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value=""><?php } ?>
<?php if (isset($_GET["schema"])) { ?><input type="hidden" name="schema" value=""><?php } ?>
<?php if (isset($_GET["dump"])) { ?><input type="hidden" name="dump" value=""><?php } ?>
 <input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?>>
</p>
</form>
<?php
			if ($missing != "db" && strlen(DB) && $connection->select_db(DB)) {
				$tables = tables_list();
				if (!$tables) {
					echo "<p class='message'>" . lang('No tables.') . "\n";
				} else {
					$this->tablesPrint($tables);
				}
				echo '<p><a href="' . h(ME) . 'create=">' . bold(lang('Create new table'), $_GET["create"] === "") . "</a>\n";
			}
		}
	}
	
	/** Prints table list in menu
	* @param array
	* @return null
	*/
	function tablesPrint($tables) {
		echo "<p id='tables'>\n";
		foreach ($tables as $table) {
			echo '<a href="' . h(ME) . 'select=' . urlencode($table) . '">' . bold(lang('select'), $_GET["select"] == $table) . '</a> ';
			echo '<a href="' . h(ME) . 'table=' . urlencode($table) . '">' . bold($this->tableName(array("Name" => $table)), $_GET["table"] == $table) . "</a><br>\n"; //! Adminer::tableName may work with full table status
		}
	}
	
}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
