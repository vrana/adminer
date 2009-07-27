<?php
class Adminer {
	
	/** Name in title and navigation
	* @return string
	*/
	function name() {
		return lang('Adminer');
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
		// should be used everywhere instead of $_GET["db"]
		return $_GET["db"];
	}
	
	/** Print login form
	* @param string
	* @return null
	*/
	function loginForm($username) {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Server'); ?><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>">
<tr><th><?php echo lang('Username'); ?><td><input name="username" value="<?php echo htmlspecialchars($username); ?>">
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
		return htmlspecialchars($tableStatus["Name"]);
	}
	
	/** Field caption used in select and edit
	* @param array single field returned from fields()
	* @return string
	*/
	function fieldName($field) {
		return '<span title="' . htmlspecialchars($field["full_type"]) . '">' . htmlspecialchars($field["field"]) . '</span>';
	}
	
	/** Links after select heading
	* @param array result of SHOW TABLE STATUS
	* @return string
	*/
	function selectLinks($tableStatus) {
		global $SELF;
		return '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($_GET['select']) . '">' . lang('Table structure') . '</a>';
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
		global $SELF;
		// it would be nice if $query can be passed by reference and printed value would be returned but call_user() doesn't allow reference parameters
		return "<p><code class='jush-sql'>" . htmlspecialchars($query) . "</code> <a href='" . htmlspecialchars($SELF) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a>\n";
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
	* @param string escaped value to print
	* @param string link to foreign key
	* @param array single field returned from fields()
	* @return string
	*/
	function selectVal($val, $link, $field) {
		$return = ($field["type"] == "char" ? "<code>$val</code>" : $val);
		if (ereg('blob|binary', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($val));
		}
		return ($link ? "<a href=\"$link\">$return</a>" : $return);
	}
	
	/** Print extra text in the end of a select form
	* @param array fields holding e-mails
	* @return null
	*/
	function selectExtraDisplay($emailFields) {
	}
	
	/** Process extras in select form
	* @param array AND conditions
	* @return bool true if processed, false to process other parts of form
	*/
	function selectExtraProcess($where) {
		return false;
	}
	
	/** Query printed after execution in the message
	* @param string executed query
	* @return string
	*/
	function messageQuery($query) {
		global $SELF;
		$id = "sql-" . count($_SESSION["messages"]);
		$_SESSION["history"][$_GET["server"]][$_GET["db"]][] = $query;
		return " <a href='#$id' onclick=\"return !toggle('$id');\">" . lang('SQL command') . "</a><div id='$id' class='hidden'><pre class='jush-sql'>" . htmlspecialchars($query) . '</pre><a href="' . htmlspecialchars($SELF . 'sql=&history=' . (count($_SESSION["history"][$_GET["server"]][$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a></div>';
	}
	
	/** Functions displayed in edit form
	* @param array single field from fields()
	* @return array
	*/
	function editFunctions($field) {
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
		return (isset($_GET["select"]) ? array("orig" => lang('original')) : array()) + $return;
	}
	
	/** Get options to display edit field
	* @param string table name
	* @param array single field from fields()
	* @return array options for <select> or empty to display <input>
	*/
	function editInput($table, $field) {
		return false;
	}
	
	/** Process sent input
	* @param string field name
	* @param array single field from fields()
	* @return string expression to use in a query
	*/
	function processInput($name, $field) {
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
		return $return;
	}
	
	/** Prints navigation after Adminer title
	* @param string can be "auth" if there is no database connection or "db" if there is no database selected
	* @return null
	*/
	function navigation($missing) {
		global $SELF, $dbh;
		if ($missing != "auth") {
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
						echo '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($row[0]) . '">' . $this->tableName(array("Name" => $row[0])) . "</a><br>\n"; //! Adminer::table_name may work with full table status
					}
				}
				$result->free();
				echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a>\n";
			}
		}
	}
	
}
