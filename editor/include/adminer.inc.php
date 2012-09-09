<?php
class Adminer {
	var $operators = array("<=", ">=");
	var $_values = array();
	
	function name() {
		return "<a href='http://www.adminer.org/editor/' id='h1'>" . lang('Editor') . "</a>";
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
		$databases = $this->databases(false);
		return (!$databases
			? $connection->result("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1)") // username without the database list
			: $databases[(information_schema($databases[0]) ? 1 : 0)] // first available database
		);
	}
	
	function databases($flush = true) {
		return get_databases($flush);
	}
	
	function queryTimeout() {
		return 5;
	}
	
	function headers() {
		return true;
	}
	
	function head() {
		return true;
	}
	
	function loginForm() {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Username'); ?><td><input type="hidden" name="auth[driver]" value="server"><input id="username" name="auth[username]" value="<?php echo h($_GET["username"]);  ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="auth[password]">
</table>
<script type="text/javascript">
document.getElementById('username').focus();
</script>
<?php
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}
	
	function login($login, $password) {
		global $connection;
		$connection->query("SET time_zone = " . q(substr_replace(@date("O"), ":", -2, 0))); // date("P") available since PHP 5.1.3, @ - requires date.timezone since PHP 5.3.0
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
		if ($set !== null) {
			echo '<p class="tabs"><a href="' . h(ME . 'edit=' . urlencode($TABLE) . $set) . '">' . lang('New item') . "</a>\n";
		}
		echo "<a href='" . h(remove_from_uri("page")) . "&amp;page=last' title='" . lang('Last page') . "'>&gt;&gt;</a>\n";
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
				$return[$key]["name"] = (preg_match("(^$search$separator(.+)|^(.+?)$separator$search\$)iu", $name, $match) ? $match[2] . $match[3] : $name);
			} else {
				unset($return[$key]);
			}
		}
		return $return;
	}
	
	function backwardKeysPrint($backwardKeys, $row) {
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
	
	function selectQuery($query) {
		return "<!--\n" . str_replace("--", "--><!-- ", $query) . "\n-->\n";
	}
	
	function rowDescription($table) {
		// first varchar column
		foreach (fields($table) as $field) {
			if (ereg("varchar|character varying", $field["type"])) {
				return idf_escape($field["field"]);
			}
		}
		return "";
	}
	
	function rowDescriptions($rows, $foreignKeys) {
		$return = $rows;
		foreach ($rows[0] as $key => $val) {
			if (list($table, $id, $name) = $this->_foreignColumn($foreignKeys, $key)) {
				// find all used ids
				$ids = array();
				foreach ($rows as $row) {
					$ids[$row[$key]] = exact_value($row[$key]);
				}
				// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
				$descriptions = $this->_values[$table];
				if (!$descriptions) {
					$descriptions = get_key_vals("SELECT $id, $name FROM " . table($table) . " WHERE $id IN (" . implode(", ", $ids) . ")");
				}
				// use the descriptions
				foreach ($rows as $n => $row) {
					if (isset($row[$key])) {
						$return[$n][$key] = (string) $descriptions[$row[$key]];
					}
				}
			}
		}
		return $return;
	}
	
	function selectVal($val, $link, $field) {
		$return = ($val === null ? "&nbsp;" : $val);
		if (ereg('blob|bytea', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($val));
			if (ereg("^(GIF|\xFF\xD8\xFF|\x89PNG\x0D\x0A\x1A\x0A)", $val)) { // GIF|JPG|PNG, getimagetype() works with filename
				$return = "<img src='$link' alt='$return'>";
			}
		}
		if (like_bool($field) && $return != "&nbsp;") { // bool
			$return = ($val ? lang('yes') : lang('no'));
		}
		if ($link) {
			$return = "<a href='$link'>$return</a>";
		}
		if (!$link && !like_bool($field) && ereg('int|float|double|decimal', $field["type"])) {
			$return = "<div class='number'>$return</div>"; // Firefox doesn't support <colgroup>
		} elseif (ereg('date', $field["type"])) {
			$return = "<div class='datetime'>$return</div>";
		}
		return $return;
	}
	
	function editVal($val, $field) {
		if (ereg('date|timestamp', $field["type"]) && $val !== null) {
			return preg_replace('~^(\\d{2}(\\d+))-(0?(\\d+))-(0?(\\d+))~', lang('$1-$3-$5'), $val);
		}
		return $val;
	}
	
	function selectColumnsPrint($select, $columns) {
		// can allow grouping functions by indexes
	}
	
	function selectSearchPrint($where, $columns, $indexes) {
		$where = (array) $_GET["where"];
		echo '<fieldset id="fieldset-search"><legend>' . lang('Search') . "</legend><div>\n";
		$keys = array();
		foreach ($where as $key => $val) {
			$keys[$val["col"]] = $key;
		}
		$i = 0;
		$fields = fields($_GET["select"]);
		foreach ($columns as $name => $desc) {
			$field = $fields[$name];
			if (ereg("enum", $field["type"]) || like_bool($field)) { //! set - uses 1 << $i and FIND_IN_SET()
				$key = $keys[$name];
				$i--;
				echo "<div>" . h($desc) . "<input type='hidden' name='where[$i][col]' value='" . h($name) . "'>:";
				echo (like_bool($field)
					? " <select name='where[$i][val]'>" . optionlist(array("" => "", lang('no'), lang('yes')), $where[$key]["val"], true) . "</select>"
					: enum_input("checkbox", " name='where[$i][val][]'", $field, (array) $where[$key]["val"], ($field["null"] ? 0 : null))
				);
				echo "</div>\n";
				unset($columns[$name]);
			} elseif (is_array($options = $this->_foreignKeyOptions($_GET["select"], $name))) {
				if ($fields[$name]["null"]) {
					$options[0] = '(' . lang('empty') . ')';
				}
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
		echo "<div><select name='where[$i][col]' onchange='this.nextSibling.nextSibling.onchange();'><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, null, true) . "</select>";
		echo html_select("where[$i][op]", array(-1 => "") + $this->operators);
		echo "<input name='where[$i][val]' onchange='selectAddRow(this);'></div>\n";
		echo "</div></fieldset>\n";
	}
	
	function selectOrderPrint($order, $columns, $indexes) {
		//! desc
		$orders = array();
		foreach ($indexes as $key => $index) {
			$order = array();
			foreach ($index["columns"] as $val) {
				$order[] = $columns[$val];
			}
			if (count(array_filter($order, 'strlen')) > 1 && $key != "PRIMARY") {
				$orders[$key] = implode(", ", $order);
			}
		}
		if ($orders) {
			echo '<fieldset><legend>' . lang('Sort') . "</legend><div>";
			echo "<select name='index_order'>" . optionlist(array("" => "") + $orders, ($_GET["order"][0] != "" ? "" : $_GET["index_order"]), true) . "</select>";
			echo "</div></fieldset>\n";
		}
		if ($_GET["order"]) {
			echo "<div style='display: none;'>" . hidden_fields(array(
				"order" => array(1 => reset($_GET["order"])),
				"desc" => ($_GET["desc"] ? array(1 => 1) : array()),
			)) . "</div>\n";
		}
	}
	
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo html_select("limit", array("", "30", "100"), $limit);
		echo "</div></fieldset>\n";
	}
	
	function selectLengthPrint($text_length) {
	}
	
	function selectActionPrint($indexes) {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo "</div></fieldset>\n";
	}
	
	function selectCommandPrint() {
		return true;
	}
	
	function selectImportPrint() {
		return true;
	}
	
	function selectEmailPrint($emailFields, $columns) {
		if ($emailFields) {
			print_fieldset("email", lang('E-mail'), $_POST["email_append"]);
			echo "<div onkeydown=\"eventStop(event); return bodyKeydown(event, 'email');\">\n";
			echo "<p>" . lang('From') . ": <input name='email_from' value='" . h($_POST ? $_POST["email_from"] : $_COOKIE["adminer_email"]) . "'>\n";
			echo lang('Subject') . ": <input name='email_subject' value='" . h($_POST["email_subject"]) . "'>\n";
			echo "<p><textarea name='email_message' rows='15' cols='75'>" . h($_POST["email_message"] . ($_POST["email_append"] ? '{$' . "$_POST[email_addition]}" : "")) . "</textarea>\n";
			echo "<p onkeydown=\"eventStop(event); return bodyKeydown(event, 'email_append');\">" . html_select("email_addition", $columns, $_POST["email_addition"]) . "<input type='submit' name='email_append' value='" . lang('Insert') . "'>\n"; //! JavaScript
			echo "<p>" . lang('Attachments') . ": <input type='file' name='email_files[]' onchange=\"this.onchange = function () { }; var el = this.cloneNode(true); el.value = ''; this.parentNode.appendChild(el);\">";
			echo "<p>" . (count($emailFields) == 1 ? '<input type="hidden" name="email_field" value="' . h(key($emailFields)) . '">' : html_select("email_field", $emailFields));
			echo "<input type='submit' name='email' value='" . lang('Send') . "' onclick=\"return this.form['delete'].onclick();\">\n";
			echo "</div>\n";
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
						$name = idf_escape($name);
						if ($col != "" && $field["type"] == "enum") {
							$conds[] = (in_array(0, $val) ? "$name IS NULL OR " : "") . "$name IN (" . implode(", ", array_map('intval', $val)) . ")";
						} else {
							$text_type = ereg('char|text|enum|set', $field["type"]);
							$value = $this->processInput($field, (!$op && $text_type && ereg('^[^%]+$', $val) ? "%$val%" : $val));
							$conds[] = $name . ($value == "NULL" ? " IS" . ($op == ">=" ? " NOT" : "") . " $value"
								: (in_array($op, $this->operators) || $op == "=" ? " $op $value"
								: ($text_type ? " LIKE $value"
								: " IN (" . str_replace(",", "', '", $value) . ")"
							))); //! can issue "Illegal mix of collations" for columns in other character sets - solve by CONVERT($name using utf8)
							if ($key < 0 && $val == "0") {
								$conds[] = "$name IS NULL";
							}
						}
					}
				}
				$return[] = ($conds ? "(" . implode(" OR ", $conds) . ")" : "0");
			}
		}
		return $return;
	}
	
	function selectOrderProcess($fields, $indexes) {
		$index_order = $_GET["index_order"];
		if ($index_order != "") {
			unset($_GET["order"][1]);
		}
		if ($_GET["order"]) {
			return array(idf_escape(reset($_GET["order"])) . ($_GET["desc"] ? " DESC" : ""));
		}
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
				$rows = get_rows("SELECT DISTINCT $field" . ($matches[1] ? ", " . implode(", ", array_map('idf_escape', array_unique($matches[1]))) : "") . " FROM " . table($_GET["select"])
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
	
	function selectQueryBuild($select, $where, $group, $order, $limit, $page) {
		return "";
	}
	
	function messageQuery($query) {
		return " <span class='time'>" . @date("H:i:s") . "</span><!--\n" . str_replace("--", "--><!-- ", $query) . "\n-->";
	}
	
	function editFunctions($field) {
		$return = array();
		if ($field["null"] && ereg('blob', $field["type"])) {
			$return["NULL"] = lang('empty');
		}
		$return[""] = ($field["null"] || $field["auto_increment"] || like_bool($field) ? "" : "*");
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
				. enum_input("radio", $attrs, $field, ($value || isset($_GET["select"]) ? $value : 0), ($field["null"] ? "" : null))
			;
		}
		$options = $this->_foreignKeyOptions($table, $field["field"], $value);
		if ($options !== null) {
			return (is_array($options)
				? "<select$attrs>" . optionlist($options, $value, true) . "</select>"
				:  "<input value='" . h($value) . "'$attrs class='hidden'><input value='" . h($options) . "' class='jsonly' onkeyup=\"whisper('" . h(ME . "script=complete&source=" . urlencode($table) . "&field=" . urlencode($field["field"])) . "&value=', this);\"><div onclick='return whisperClick(event, this.previousSibling);'></div>"
			);
		}
		if (like_bool($field)) {
			return '<input type="checkbox" value="' . h($value ? $value : 1) . '"' . ($value ? ' checked' : '') . "$attrs>";
		}
		$hint = "";
		if (ereg('time', $field["type"])) {
			$hint = lang('HH:MM:SS');
		}
		if (ereg('date|timestamp', $field["type"])) {
			$hint = lang('[yyyy]-mm-dd') . ($hint ? " [$hint]" : "");
		}
		if ($hint) {
			return "<input value='" . h($value) . "'$attrs> ($hint)"; //! maxlength
		}
		if (eregi('_(md5|sha1)$', $field["field"])) {
			return "<input type='password' value='" . h($value) . "'$attrs>";
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
		$return = ($field["type"] == "bit" && ereg('^[0-9]+$', $value) ? $return : q($return));
		if ($value == "" && like_bool($field)) {
			$return = "0";
		} elseif ($value == "" && ($field["null"] || !ereg('char|text', $field["type"]))) {
			$return = "NULL";
		} elseif (ereg('^(md5|sha1)$', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
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
				if ($style == "table") {
					dump_csv(array_keys($row));
					$style = "INSERT";
				}
				dump_csv($row);
			}
		}
	}
	
	function dumpFilename($identifier) {
		return friendly_url($identifier);
	}
	
	function dumpHeaders($identifier, $multi_table = false) {
		$ext = "csv";
		header("Content-Type: text/csv; charset=utf-8");
		return $ext;
	}
	
	function homepage() {
		return true;
	}
	
	function navigation($missing) {
		global $VERSION, $token;
		?>
<h1>
<?php echo $this->name(); ?> <span class="version"><?php echo $VERSION; ?></span>
<a href="http://www.adminer.org/editor/#download" id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? h($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php
		if ($missing == "auth") {
			$first = true;
			foreach ((array) $_SESSION["pwds"]["server"][""] as $username => $password) {
				if ($password !== null) {
					if ($first) {
						echo "<p id='logins' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n";
						$first = false;
					}
					echo "<a href='" . h(auth_url("server", "", $username)) . "'>" . ($username != "" ? h($username) : "<i>" . lang('empty') . "</i>") . "</a><br>\n";
				}
			}
		} else {
			?>
<form action="" method="post">
<p class="logout">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" id="logout">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</p>
</form>
<?php
			$this->databasesPrint($missing);
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
	
	function databasesPrint($missing) {
	}
	
	function tablesPrint($tables) {
		echo "<p id='tables' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n";
		foreach ($tables as $row) {
			$name = $this->tableName($row);
			if (isset($row["Engine"]) && $name != "") { // ignore views and tables without name
				echo "<a href='" . h(ME) . 'select=' . urlencode($row["Name"]) . "'" . bold($_GET["select"] == $row["Name"]) . " title='" . lang('Select data') . "'>$name</a><br>\n";
			}
		}
	}
	
	function _foreignColumn($foreignKeys, $column) {
		foreach ((array) $foreignKeys[$column] as $foreignKey) {
			if (count($foreignKey["source"]) == 1) {
				$name = $this->rowDescription($foreignKey["table"]);
				if ($name != "") {
					$id = idf_escape($foreignKey["target"][0]);
					return array($foreignKey["table"], $id, $name);
				}
			}
		}
	}
	
	function _foreignKeyOptions($table, $column, $value = null) {
		global $connection;
		if (list($target, $id, $name) = $this->_foreignColumn(column_foreign_keys($table), $column)) {
			$return = &$this->_values[$target];
			if ($return === null) {
				$table_status = table_status($target);
				$return = ($table_status["Rows"] > 1000 ? "" : array("" => "") + get_key_vals("SELECT $id, $name FROM " . table($target) . " ORDER BY 2"));
			}
			if (!$return && $value !== null) {
				return $connection->result("SELECT $name FROM " . table($target) . " WHERE $id = " . q($value));
			}
			return $return;
		}
	}

}

$adminer = (function_exists('adminer_object') ? adminer_object() : new Adminer);
