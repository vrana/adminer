<?php
class Adminer {
	var $operators = array("<=", ">=");
	var $_values = array();
	
	function name() {
		return lang('Editor');
	}
	
	//! driver, ns
	
	function credentials() {
		return array(SERVER, $_GET["username"], get_session("pwds"));
	}
	
	function permanentLogin() {
		return password_file();
	}
	
	function database() {
		global $connection;
		$databases = get_databases(false);
		return (!$databases
			? $connection->result("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1)") // username without the database list
			: $databases[(information_schema($databases[0]) ? 1 : 0)] // first available database
		);
	}
	
	function headers() {
		header("X-Frame-Options: deny");
		header("X-XSS-Protection: 0");
	}
	
	function loginForm() {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Username'); ?><td><input type="hidden" name="driver" value="server"><input type="hidden" name="server" value=""><input id="username" name="username" value="<?php echo h($_GET["username"]);  ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<script type="text/javascript">
document.getElementById('username').focus();
</script>
<?php
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("permanent", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}
	
	function login($login, $password) {
		return true;
	}
	
	function tableName($tableStatus) {
		return h($tableStatus["Comment"] != "" ? $tableStatus["Comment"] : $tableStatus["Name"]);
	}
	
	function fieldName($field, $order = 0) {
		return h($field["comment"] != "" ? $field["comment"] : $field["field"]);
	}
	
	function selectLinks($tableStatus, $set = "") {
		$TABLE = $tableStatus["Name"];
		if (isset($set)) {
			echo '<p class="tabs"><a href="' . h(ME . 'edit=' . urlencode($TABLE) . $set) . '">' . lang('New item') . "</a>\n";
		}
		echo "<a href='" . h(remove_from_uri("page")) . "&amp;page=last' title='" . lang('Last page') . "' onclick='return !ajaxMain(this.href, undefined, event);'>&gt;&gt;</a>\n";
	}
	
	function foreignKeys($table) {
		return foreign_keys($table);
	}
	
	function backwardKeys($table, $tableName) {
		$return = array();
		foreach (get_rows("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = " . q($this->database()) . "
AND REFERENCED_TABLE_SCHEMA = " . q($this->database()) . "
AND REFERENCED_TABLE_NAME = " . q($table) . "
ORDER BY ORDINAL_POSITION", null, "") as $row) { //! requires MySQL 5
			$return[$row["TABLE_NAME"]]["keys"][$row["CONSTRAINT_NAME"]][$row["COLUMN_NAME"]] = $row["REFERENCED_COLUMN_NAME"];
		}
		foreach ($return as $key => $val) {
			$name = $this->tableName(table_status($key));
			if ($name != "") {
				$search = preg_quote($tableName);
				$separator = "(:|\\s*-)?\\s+";
				$return[$key]["name"] = (preg_match("(^$search$separator(.+)|^(.+?)$separator$search\$)", $name, $match) ? $match[2] . $match[3] : $name);
			} else {
				unset($return[$key]);
			}
		}
		return $return;
	}
	
	function backwardKeysPrint($backwardKeys, $row) {
		if ($backwardKeys) {
			echo "<td>";
			foreach ($backwardKeys as $table => $backwardKey) {
				foreach ($backwardKey["keys"] as $cols) {
					$link = ME . 'select=' . urlencode($table);
					$i = 0;
					foreach ($cols as $column => $val) {
						$link .= where_link($i++, $column, $row[$val]);
					}
					echo "<a href='" . h($link) . "'>" . h($backwardKey["name"]) . "</a>";
					$link = ME . 'edit=' . urlencode($table);
					foreach ($cols as $column => $val) {
						$link .= "&set" . urlencode("[" . bracket_escape($column) . "]") . "=" . urlencode($row[$val]);
					}
					echo "<a href='" . h($link) . "' title='" . lang('New item') . "'>+</a> ";
				}
			}
		}
	}
	
	function selectQuery($query) {
		return "<!--\n" . str_replace("--", "--><!-- ", $query) . "\n-->\n";
	}
	
	function rowDescription($table) {
		// first varchar column
		foreach (fields($table) as $field) {
			if ($field["type"] == "varchar") {
				return idf_escape($field["field"]);
			}
		}
		return "";
	}
	
	function rowDescriptions($rows, $foreignKeys) {
		$return = $rows;
		foreach ($rows[0] as $key => $val) {
			foreach ((array) $foreignKeys[$key] as $foreignKey) {
				if (count($foreignKey["source"]) == 1) {
					$id = idf_escape($foreignKey["target"][0]);
					$name = $this->rowDescription($foreignKey["table"]);
					if ($name != "") {
						// find all used ids
						$ids = array();
						foreach ($rows as $row) {
							$ids[$row[$key]] = exact_value($row[$key]);
						}
						// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
						$descriptions = $this->_values[$foreignKey["table"]];
						if (!$descriptions) {
							$descriptions = get_key_vals("SELECT $id, $name FROM " . idf_escape($foreignKey["table"]) . " WHERE $id IN (" . implode(", ", $ids) . ")");
						}
						// use the descriptions
						foreach ($rows as $n => $row) {
							if (isset($row[$key])) {
								$return[$n][$key] = (string) $descriptions[$row[$key]];
							}
						}
						break;
					}
				}
			}
		}
		return $return;
	}
	
	function selectVal($val, $link, $field) {
		$return = ($val == "<i>NULL</i>" ? "&nbsp;" : $val);
		if (ereg('blob|bytea', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($val));
			if (ereg("^(GIF|\xFF\xD8\xFF|\x89\x50\x4E\x47\x0D\x0A\x1A\x0A)", $val)) { // GIF|JPG|PNG, getimagetype() works with filename
				$return = "<img src='$link' alt='$return'>";
			}
		}
		if ($field["full_type"] == "tinyint(1)" && $return != "&nbsp;") { // bool
			$return = '<img src="' . ($val ? "../adminer/static/plus.gif" : "../adminer/static/cross.gif") . '" alt="' . h($val) . '">';
		}
		if ($link) {
			$return = "<a href='$link'>$return</a>";
		}
		if (!$link && $field["full_type"] != "tinyint(1)" && ereg('int|float|double|decimal', $field["type"])) {
			$return = "<div class='number'>$return</div>"; // Firefox doesn't support <colgroup>
		} elseif (ereg('date', $field["type"])) {
			$return = "<div class='datetime'>$return</div>";
		}
		return $return;
	}
	
	function editVal($val, $field) {
		if (ereg('date|timestamp', $field["type"]) && isset($val)) {
			return preg_replace('~^(\\d{2}(\\d+))-(0?(\\d+))-(0?(\\d+))~', lang('$1-$3-$5'), $val);
		}
		return (ereg("binary", $field["type"]) ? reset(unpack("H*", $val)) : $val);
	}
	
	function selectColumnsPrint($select, $columns) {
		// can allow grouping functions by indexes
	}
	
	function selectSearchPrint($where, $columns, $indexes) {
		$where = (array) $_GET["where"];
		echo '<fieldset><legend>' . lang('Search') . "</legend><div>\n";
		$keys = array();
		foreach ($where as $key => $val) {
			$keys[$val["col"]] = $key;
		}
		$i = 0;
		foreach (fields($_GET["select"]) as $name => $field) {
			if (ereg("enum", $field["type"])) { //! set - uses 1 << $i and FIND_IN_SET()
				$desc = $columns[$name];
				$key = $keys[$name];
				$i--;
				echo "<div>" . h($desc) . "<input type='hidden' name='where[$i][col]' value='" . h($name) . "'>:";
				echo enum_input("checkbox", " name='where[$i][val][]'", $field, (array) $where[$key]["val"]); //! impossible to search for NULL
				echo "</div>\n";
				unset($columns[$name]);
			}
		}
		foreach ($columns as $name => $desc) {
			$options = $this->_foreignKeyOptions($_GET["select"], $name);
			if ($options) {
				$key = $keys[$name];
				$i--;
				echo "<div>" . h($desc) . "<input type='hidden' name='where[$i][col]' value='" . h($name) . "'><input type='hidden' name='where[$i][op]' value='='>: <select name='where[$i][val]'>" . optionlist($options, $where[$key]["val"], true) . "</select></div>\n";
				unset($columns[$name]);
			}
		}
		$i = 0;
		foreach ($where as $val) {
			if (($val["col"] == "" || $columns[$val["col"]]) && "$val[col]$val[val]" != "") {
				echo "<div><select name='where[$i][col]'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, $val["col"], true) . "</select>";
				echo html_select("where[$i][op]", array(-1 => "") + $this->operators, $val["op"]);
				echo "<input name='where[$i][val]' value='" . h($val["val"]) . "'></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' onchange='selectAddRow(this);'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, null, true) . "</select>";
		echo html_select("where[$i][op]", array(-1 => "") + $this->operators);
		echo "<input name='where[$i][val]'></div>\n";
		echo "</div></fieldset>\n";
	}
	
	function selectOrderPrint($order, $columns, $indexes) {
		//! desc
		$orders = array();
		foreach ($indexes as $key => $index) {
			$order = array();
			foreach ($index["columns"] as $val) {
				$order[] = $this->fieldName(array("field" => $val, "comment" => $columns[$val]));
			}
			if (count(array_filter($order, 'strlen')) > 1 && $key != "PRIMARY") {
				$orders[$key] = implode(", ", $order);
			}
		}
		if ($orders) {
			echo '<fieldset><legend>' . lang('Sort') . "</legend><div>";
			echo "<select name='index_order'>" . optionlist(array("" => "") + $orders, $_GET["index_order"], true) . "</select>";
			echo "</div></fieldset>\n";
		}
	}
	
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo html_select("limit", array("", "30", "100"), $limit);
		echo "</div></fieldset>\n";
	}
	
	function selectLengthPrint($text_length) {
	}
	
	function selectActionPrint() {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo "</div></fieldset>\n";
	}
	
	function selectEmailPrint($emailFields, $columns) {
		if ($emailFields) {
			echo '<fieldset><legend><a href="#fieldset-email" onclick="return !toggle(\'fieldset-email\');">' . lang('E-mail') . "</a></legend><div id='fieldset-email'" . ($_POST["email_append"] ? "" : " class='hidden'") . ">\n";
			echo "<p>" . lang('From') . ": <input name='email_from' value='" . h($_POST ? $_POST["email_from"] : $_COOKIE["adminer_email"]) . "'>\n";
			echo lang('Subject') . ": <input name='email_subject' value='" . h($_POST["email_subject"]) . "'>\n";
			echo "<p><textarea name='email_message' rows='15' cols='75' onkeypress='return textareaKeypress(this, event, false, this.form.email);'>" . h($_POST["email_message"] . ($_POST["email_append"] ? '{$' . "$_POST[email_addition]}" : "")) . "</textarea><br>\n";
			echo html_select("email_addition", $columns, $_POST["email_addition"]) . "<input type='submit' name='email_append' value='" . lang('Insert') . "'>\n"; //! JavaScript
			echo "<p>" . lang('Attachments') . ": <input type='file' name='email_files[]' onchange=\"this.onchange = function () { }; var el = this.cloneNode(true); el.value = ''; this.parentNode.appendChild(el);\">";
			echo "<p>" . (count($emailFields) == 1 ? '<input type="hidden" name="email_field" value="' . h(key($emailFields)) . '">' : html_select("email_field", $emailFields));
			echo "<input type='submit' name='email' value='" . lang('Send') . "' onclick=\"return this.form['delete'].onclick();\">\n";
			echo "</div></fieldset>\n";
		}
	}
	
	function selectColumnsProcess($columns, $indexes) {
		return array(array(), array());
	}
	
	function selectSearchProcess($fields, $indexes) {
		$return = array();
		foreach ((array) $_GET["where"] as $key => $where) {
			$col = $where["col"];
			$op = $where["op"];
			$val = $where["val"];
			if (($key < 0 ? "" : $col) . $val != "") {
				$conds = array();
				foreach (($col != "" ? array($col => $fields[$col]) : $fields) as $name => $field) {
					if ($col != "" || is_numeric($val) || !ereg('int|float|double|decimal', $field["type"])) {
						if ($col != "" && $field["type"] == "enum") {
							$conds[] = idf_escape($name) . " IN (" . implode(", ", array_map('intval', $val)) . ")";
						} else {
							$text_type = ereg('char|text|enum|set', $field["type"]);
							$value = $this->processInput($field, ($text_type && ereg('^[^%]+$', $val) ? "%$val%" : $val));
							$conds[] = idf_escape($name) . ($value == "NULL" ? " IS" . ($op == ">=" ? " NOT" : "") : (in_array($op, $this->operators) ? " $op" : ($op != "=" && $text_type ? " LIKE" : " ="))) . " $value"; //! can issue "Illegal mix of collations" for columns in other character sets - solve by CONVERT($name using utf8)
						}
					}
				}
				$return[] = ($conds ? "(" . implode(" OR ", $conds) . ")" : "0");
			}
		}
		return $return;
	}
	
	function selectOrderProcess($fields, $indexes) {
		if ($_GET["order"]) {
			return array(idf_escape($_GET["order"][0]) . (isset($_GET["desc"][0]) ? " DESC" : ""));
		}
		$index_order = $_GET["index_order"];
		foreach (($index_order != "" ? array($indexes[$index_order]) : $indexes) as $index) {
			if ($index_order != "" || $index["type"] == "INDEX") {
				$desc = false;
				foreach ($index["columns"] as $val) {
					if (ereg('date|timestamp', $fields[$val]["type"])) {
						$desc = true;
						break;
					}
				}
				$return = array();
				foreach ($index["columns"] as $val) {
					$return[] = idf_escape($val) . ($desc ? " DESC" : "");
				}
				return $return;
			}
		}
		return array();
	}
	
	function selectLimitProcess() {
		return (isset($_GET["limit"]) ? $_GET["limit"] : "30");
	}
	
	function selectLengthProcess() {
		return "100";
	}
	
	function selectEmailProcess($where, $foreignKeys) {
		if ($_POST["email_append"]) {
			return true;
		}
		if ($_POST["email"]) {
			$sent = 0;
			if ($_POST["all"] || $_POST["check"]) {
				$field = idf_escape($_POST["email_field"]);
				$subject = $_POST["email_subject"];
				$message = $_POST["email_message"];
				preg_match_all('~\\{\\$([a-z0-9_]+)\\}~i', "$subject.$message", $matches); // allows {$name} in subject or message
				$rows = get_rows("SELECT DISTINCT $field" . ($matches[1] ? ", " . implode(", ", array_map('idf_escape', array_unique($matches[1]))) : "") . " FROM " . idf_escape($_GET["select"])
					. " WHERE $field IS NOT NULL AND $field != ''"
					. ($where ? " AND " . implode(" AND ", $where) : "")
					. ($_POST["all"] ? "" : " AND ((" . implode(") OR (", array_map('where_check', (array) $_POST["check"])) . "))")
				);
				$fields = fields($_GET["select"]);
				foreach ($this->rowDescriptions($rows, $foreignKeys) as $row) {
					$replace = array('{\\' => '{'); // allow literal {$name}
					foreach ($matches[1] as $val) {
						$replace['{$' . "$val}"] = $this->editVal($row[$val], $fields[$val]);
					}
					$email = $row[$_POST["email_field"]];
					if (is_mail($email) && send_mail($email, strtr($subject, $replace), strtr($message, $replace), $_POST["email_from"], $_FILES["email_files"])) {
						$sent++;
					}
				}
			}
			cookie("adminer_email", $_POST["email_from"]);
			redirect(remove_from_uri(), lang('%d e-mail(s) have been sent.', $sent));
		}
		return false;
	}
	
	function messageQuery($query) {
		return "<!--\n" . str_replace("--", "--><!-- ", $query) . "\n-->";
	}
	
	function editFunctions($field) {
		$return = array("" => ($field["null"] || $field["auto_increment"] || $field["full_type"] == "tinyint(1)" ? "" : "*"));
		//! respect driver
		if (ereg('date|time', $field["type"])) {
			$return["now"] = lang('now');
		}
		if (eregi('_(md5|sha1)$', $field["field"], $match)) {
			$return[] = strtolower($match[1]);
		}
		return $return;
	}
	
	function editInput($table, $field, $attrs, $value) {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='-1' checked><i>" . lang('original') . "</i></label> " : "")
				. ($field["null"] ? "<label><input type='radio'$attrs value=''" . ($value || isset($_GET["select"]) ? "" : " checked") . "><i>" . lang('empty') . "</i></label>" : "")
				. enum_input("radio", $attrs, $field, $value)
			;
		}
		$options = $this->_foreignKeyOptions($table, $field["field"]);
		if ($options) {
			return "<select$attrs>" . optionlist($options, $value, true) . "</select>";
		}
		if ($field["full_type"] == "tinyint(1)") { // bool
			return '<input type="checkbox" value="' . h($value ? $value : 1) . '"' . ($value ? ' checked' : '') . "$attrs>";
		}
		if (ereg('date|timestamp', $field["type"])) {
			return "<input value='" . h($value) . "'$attrs> (" . lang('[yyyy]-mm-dd') . ")"; //! maxlength
		}
		return '';
	}
	
	function processInput($field, $value, $function = "") {
		if ($function == "now") {
			return "$function()";
		}
		$return = $value;
		if (ereg('date|timestamp', $field["type"]) && preg_match('(^' . str_replace('\\$1', '(?P<p1>\\d*)', preg_replace('~(\\\\\\$([2-6]))~', '(?P<p\\2>\\d{1,2})', preg_quote(lang('$1-$3-$5')))) . '(.*))', $value, $match)) {
			$return = ($match["p1"] != "" ? $match["p1"] : ($match["p2"] != "" ? ($match["p2"] < 70 ? 20 : 19) . $match["p2"] : gmdate("Y"))) . "-$match[p3]$match[p4]-$match[p5]$match[p6]" . end($match);
		}
		$return = q($return);
		if (!ereg('char|text', $field["type"]) && $field["full_type"] != "tinyint(1)" && $value == "") {
			$return = "NULL";
		} elseif (ereg('^(md5|sha1)$', $function)) {
			$return = "$function($return)";
		}
		if (ereg("binary", $field["type"])) {
			$return = "unhex($return)";
		}
		return $return;
	}
	
	function dumpOutput() {
		return array();
	}
	
	function dumpFormat() {
		return array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}
	
	function dumpTable() {
		echo "\xef\xbb\xbf"; // UTF-8 byte order mark
	}
	
	function dumpData($table, $style, $query) {
		global $connection;
		$result = $connection->query($query, 1); // 1 - MYSQLI_USE_RESULT
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				dump_csv($row);
			}
		}
	}
	
	function dumpHeaders($identifier) {
		$filename = ($identifier != "" ? friendly_url($identifier) : "dump");
		$ext = "csv";
		header("Content-Type: text/csv; charset=utf-8");
		header("Content-Disposition: attachment; filename=$filename.$ext");
		session_write_close();
		return $ext;
	}
	
	function navigation($missing) {
		global $VERSION, $token;
		?>
<h1>
<a href="http://www.adminer.org/" id="h1"><?php echo $this->name(); ?></a>
<span class="version"><?php echo $VERSION; ?></span>
<a href="http://www.adminer.org/editor/#download" id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing == "auth") {
			$first = true;
			foreach ((array) $_SESSION["pwds"]["server"][""] as $username => $password) {
				if (isset($password)) {
					if ($first) {
						echo "<p onclick='eventStop(event);'>\n";
						$first = false;
					}
					echo "<a href='" . h(auth_url("server", "", $username)) . "'>" . ($username != "" ? h($username) : "<i>" . lang('empty') . "</i>") . "</a><br>\n";
				}
			}
		} else {
			?>
<form action="" method="post">
<p class="logout">
<input type="hidden" name="token" value="<?php echo $token; ?>">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>">
</p>
</form>
<?php
			if ($missing != "db" && $missing != "ns") {
				$table_status = table_status();
				if (!$table_status) {
					echo "<p class='message'>" . lang('No tables.') . "\n";
				} else {
					$this->tablesPrint($table_status);
				}
			}
		}
	}
	
	function tablesPrint($tables) {
		echo "<p id='tables'>\n";
		foreach ($tables as $row) {
			$name = $this->tableName($row);
			if (isset($row["Engine"]) && $name != "") { // ignore views and tables without name
				echo "<a href='" . h(ME) . 'select=' . urlencode($row["Name"]) . "'" . bold($_GET["select"] == $row["Name"]) . ">$name</a><br>\n";
			}
		}
	}
	
	function _foreignKeyOptions($table, $column) {
		$foreignKeys = column_foreign_keys($table);
		foreach ((array) $foreignKeys[$column] as $foreignKey) {
			if (count($foreignKey["source"]) == 1) {
				$id = idf_escape($foreignKey["target"][0]);
				$name = $this->rowDescription($foreignKey["table"]);
				if ($name != "") {
					$return = &$this->_values[$foreignKey["table"]];
					if (!isset($return)) {
						$table_status = table_status($foreignKey["table"]);
						$return = ($table_status["Rows"] > 1000 ? array() : array("" => "") + get_key_vals("SELECT $id, $name FROM " . idf_escape($foreignKey["table"]) . " ORDER BY 2"));
					}
					return $return;
				}
			}
		}
	}

}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
