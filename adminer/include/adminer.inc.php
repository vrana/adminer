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
	
	/** Links after select heading
	* @param array result of SHOW TABLE STATUS
	* @return string
	*/
	function selectLinks($tableStatus) {
		return '<a href="' . h(ME) . 'table=' . urlencode($_GET['select']) . '">' . lang('Table structure') . '</a>';
	}
	
	/** Find backward keys for table
	* @param string
	* @return array $return[$target_table][$key_name][$target_column] = $source_column;
	*/
	function backwardKeys($table) {
		return array();
	}
	
	/** Query printed in select before execution
	* @param string query to be executed
	* @return string
	*/
	function selectQuery($query) {
		// it would be nice if $query can be passed by reference and printed value would be returned but call_user() doesn't allow reference parameters
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
		$return = ($field["type"] == "char" ? "<code>$val</code>" : $val);
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
		echo '<fieldset><legend><a href="#fieldset-select" onclick="return !toggle(\'fieldset-select\');">' . lang('Select') . "</a></legend><div id='fieldset-select'" . ($select ? "" : " class='hidden'") . ">\n";
		$i = 0;
		$fun_group = array(lang('Functions') => $this->functions, lang('Aggregation') => $this->grouping);
		foreach ($select as $key => $val) {
			$val = $_GET["columns"][$key];
			echo "<div><select name='columns[$i][fun]'><option>" . optionlist($fun_group, $val["fun"]) . "</select>";
			echo "<select name='columns[$i][col]'><option>" . optionlist($columns, $val["col"], true) . "</select></div>\n";
			$i++;
		}
		echo "<div><select name='columns[$i][fun]' onchange='this.nextSibling.onchange();'><option>" . optionlist($fun_group) . "</select>";
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
		echo '<fieldset><legend><a href="#fieldset-search" onclick="return !toggle(\'fieldset-search\');">' . lang('Search') . "</a></legend><div id='fieldset-search'" . ($where ? "" : " class='hidden'") . ">\n";
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "(<i>" . implode("</i>, <i>", array_map('h', $index["columns"])) . "</i>) AGAINST";
				echo " <input name='fulltext[$i]' value='" . h($_GET["fulltext"][$i]) . "'>";
				echo "<label><input type='checkbox' name='boolean[$i]' value='1'" . (isset($_GET["boolean"][$i]) ? " checked" : "") . ">BOOL</label>";
				echo "<br>\n";
			}
		}
		$i = 0;
		foreach ((array) $_GET["where"] as $val) {
			if (strlen("$val[col]$val[val]") && in_array($val["op"], $this->operators)) {
				echo "<div><select name='where[$i][col]'><option value=''>" . lang('(anywhere)') . optionlist($columns, $val["col"], true) . "</select>";
				echo "<select name='where[$i][op]'>" . optionlist($this->operators, $val["op"]) . "</select>";
				echo "<input name='where[$i][val]' value='" . h($val["val"]) . "'></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' onchange='select_add_row(this);'><option value=''>" . lang('(anywhere)') . optionlist($columns, null, true) . "</select>";
		echo "<select name='where[$i][op]'>" . optionlist($this->operators) . "</select>";
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
		echo '<fieldset><legend><a href="#fieldset-sort" onclick="return !toggle(\'fieldset-sort\');">' . lang('Sort') . "</a></legend><div id='fieldset-sort'" . ($order ? "" : " class='hidden'") . ">\n";
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if (isset($columns[$val])) {
				echo "<div><select name='order[$i]'><option>" . optionlist($columns, $val, true) . "</select>";
				echo "<label><input type='checkbox' name='desc[$i]' value='1'" . (isset($_GET["desc"][$key]) ? " checked" : "") . ">" . lang('descending') . "</label></div>\n";
				$i++;
			}
		}
		echo "<div><select name='order[$i]' onchange='select_add_row(this);'><option>" . optionlist($columns, null, true) . "</select>";
		echo "<label><input type='checkbox' name='desc[$i]' value='1'>" . lang('descending') . "</label></div>\n";
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
		global $dbh;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && strlen($_GET["fulltext"][$i])) {
				$return[] = "MATCH (" . implode(", ", array_map('idf_escape', $index["columns"])) . ") AGAINST (" . $dbh->quote($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
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
		$id = "sql-" . count($_SESSION["messages"]);
		$_SESSION["history"][$_GET["server"]][DB][] = $query;
		return " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-sql'>" . h($query) . '</pre><a href="' . h(ME . 'sql=&history=' . (count($_SESSION["history"][$_GET["server"]][DB]) - 1)) . '">' . lang('Edit') . '</a></div>';
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
		return '';
	}
	
	/** Process sent input
	* @param array single field from fields()
	* @param string
	* @param string
	* @return string expression to use in a query
	*/
	function processInput($field, $value, $function = "") {
		global $dbh;
		$name = $field["field"];
		$return = $dbh->quote($value);
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
	
	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection or "db" if there is no database selected
	* @return null
	*/
	function navigation($missing) {
		global $dbh;
		if ($missing != "auth") {
			$databases = get_databases();
			?>
<form action="" method="post">
<p>
<a href="<?php echo h(ME); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo h(ME); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo lang('Dump'); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<form action="">
<p>
<?php if (SID) { ?><input type="hidden" name="<?php echo session_name(); ?>" value="<?php echo h(session_id()); ?>"><?php } ?>
<?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo h($_GET["server"]); ?>"><?php } ?>
<?php if ($databases) { ?>
<select name="db" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)<?php echo optionlist($databases, DB); ?></select>
<?php } else { ?>
<input name="db" value="<?php echo h(DB); ?>">
<?php } ?>
<?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value=""><?php } ?>
<?php if (isset($_GET["schema"])) { ?><input type="hidden" name="schema" value=""><?php } ?>
<?php if (isset($_GET["dump"])) { ?><input type="hidden" name="dump" value=""><?php } ?>
<input type="submit" value="<?php echo lang('Use'); ?>"<?php echo ($databases ? " class='hidden'" : ""); ?>>
</p>
</form>
<?php
			if ($missing != "db" && strlen(DB) && $dbh->select_db(DB)) {
				$result = $dbh->query("SHOW TABLES");
				if (!$result->num_rows) {
					echo "<p class='message'>" . lang('No tables.') . "\n";
				} else {
					echo "<p id='tables'>\n";
					while ($row = $result->fetch_row()) {
						echo '<a href="' . h(ME) . 'select=' . urlencode($row[0]) . '">' . lang('select') . '</a> ';
						echo '<a href="' . h(ME) . 'table=' . urlencode($row[0]) . '">' . $this->tableName(array("Name" => $row[0])) . "</a><br>\n"; //! Adminer::tableName may work with full table status
					}
				}
				echo '<p><a href="' . h(ME) . 'create=">' . lang('Create new table') . "</a>\n";
			}
		}
	}
	
}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
