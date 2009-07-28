<?php
class Adminer {
	
	function name() {
		return lang('Editor');
	}
	
	function credentials() {
		return array(); // default INI settings
	}
	
	function database() {
		$dbs = get_databases(false);
		return (count($dbs) == 1 ? $dbs[0] : (count($dbs) == 2 && information_schema($dbs[0]) ? $dbs[1] : 'test'));
	}
	
	function loginForm($username) {
		?>
<table cellspacing="0">
<tr><th><?php echo lang('Username'); ?><td><input type="hidden" name="server" value="" /><input name="username" value="<?php echo htmlspecialchars($username); ?>">
<tr><th><?php echo lang('Password'); ?><td><input type="password" name="password">
</table>
<?php
	}
	
	function login($login, $password) {
		return true;
	}
	
	function tableName($tableStatus) {
		table_comment($tableStatus);
		return htmlspecialchars(strlen($tableStatus["Comment"]) ? $tableStatus["Comment"] : $tableStatus["Name"]);
	}
	
	function fieldName($field, $order = 0) {
		return htmlspecialchars(strlen($field["comment"]) ? $field["comment"] : $field["field"]);
	}
	
	function selectLinks($tableStatus) {
		return "";
	}
	
	function backwardKeys($table) {
		global $dbh;
		$return = array();
		$result = $dbh->query("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = " . $dbh->quote($this->database()) . "
AND REFERENCED_TABLE_SCHEMA = " . $dbh->quote($this->database()) . "
AND REFERENCED_TABLE_NAME = " . $dbh->quote($table) . "
ORDER BY ORDINAL_POSITION"); //! requires MySQL 5
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$return[$row["TABLE_NAME"]][$row["CONSTRAINT_NAME"]][$row["COLUMN_NAME"]] = $row["REFERENCED_COLUMN_NAME"];
			}
			$result->free();
		}
		return $return;
	}
	
	function selectQuery($query) {
		return "<!-- " . str_replace("--", "--><!--", $query) . " -->\n";
	}
	
	function rowDescription($table) {
		$return = "";
		// first varchar column
		foreach (fields($table) as $field) {
			if ($field["type"] == "varchar") {
				$return = idf_escape($field["field"]);
				break;
			}
		}
		return $return;
	}
	
	function rowDescriptions($rows, $foreignKeys) {
		global $dbh;
		$return = $rows;
		foreach ($rows[0] as $key => $val) {
			foreach ((array) $foreignKeys[$key] as $foreignKey) {
				if (count($foreignKey["source"]) == 1) {
					$id = idf_escape($foreignKey["target"][0]);
					$name = $this->rowDescription($foreignKey["table"]);
					if (strlen($name)) {
						// find all used ids
						$ids = array();
						foreach ($rows as $row) {
							$ids[$row[$key]] = $dbh->quote($row[$key]);
						}
						// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
						$descriptions = array();
						$result = $dbh->query("SELECT $id, $name FROM " . idf_escape($foreignKey["table"]) . " WHERE $id IN (" . implode(", ", $ids) . ")");
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
		return $return;
	}
	
	function selectVal($val, $link, $field) {
		global $SELF; // used by compiled images
		$return = ($val == "<i>NULL</i>" ? "&nbsp;" : $val);
		if (ereg('blob|binary', $field["type"]) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($val));
			if (ereg("^(GIF|\xFF\xD8\xFF|\x89\x50\x4E\x47\x0D\x0A\x1A\x0A)", $val)) { // GIF|JPG|PNG, getimagetype() works with filename
				$return = "<img src=\"$link\" alt='$return'>";
			}
		}
		if ($field["full_type"] == "tinyint(1)" && $return != "&nbsp;") { // bool
			$return = '<img src="' . ($val ? "../adminer/plus.gif" : "../adminer/cross.gif") . '" alt="' . htmlspecialchars($val) . '">';
		}
		return ($link ? "<a href=\"$link\">$return</a>" : $return);
	}
	
	function editVal($val, $field) {
		if (ereg('date|timestamp', $field["type"])) {
			return preg_replace('~^([0-9]{2}([0-9]+))-(0?([0-9]+))-(0?([0-9]+))~', lang('$1-$3-$5'), $val);
		}
		return $val;
	}
	
	function selectColumnsPrint($select, $columns) {
		//! allow grouping functions by indexes
	}
	
	function selectSearchPrint($where, $columns, $indexes) {
		//! from-to, foreign keys
		echo '<fieldset><legend>' . lang('Search') . "</legend><div>\n";
		$i = 0;
		foreach ((array) $_GET["where"] as $val) {
			if (strlen("$val[col]$val[val]")) {
				echo "<div><select name='where[$i][col]'><option value=''>" . lang('(anywhere)') . optionlist($columns, $val["col"], true) . "</select>";
				echo "<input name='where[$i][val]' value=\"" . htmlspecialchars($val["val"]) . "\"></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' onchange='select_add_row(this);'><option value=''>" . lang('(anywhere)') . optionlist($columns, null, true) . "</select>";
		echo "<input name='where[$i][val]'></div>\n";
		echo "</div></fieldset>\n";
	}
	
	function selectOrderPrint($order, $columns, $indexes) {
		//! desc
		$orders = array();
		foreach ($indexes as $i => $index) {
			$order = array();
			foreach ($index["columns"] as $val) {
				$order[] = $this->fieldName(array("field" => $val, "comment" => $columns[$val]));
			}
			if (count(array_filter($order, 'strlen')) > 1) {
				$orders[$i] = implode(", ", $order);
			}
		}
		if ($orders) {
			echo '<fieldset><legend>' . lang('Sort') . "</legend><div>";
			echo "<select name='index_order'>" . optionlist($orders, $_GET["index_order"], true) . "</select>";
			echo "</div></fieldset>\n";
		}
	}
	
	function selectLimitPrint($limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo "<select name='limit'>" . optionlist(array("", "30", "100"), $limit) . "</select>";
		echo "</div></fieldset>\n";
	}
	
	function selectLengthPrint($text_length) {
	}
	
	function selectActionPrint() {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo "</div></fieldset>\n";
	}
	
	function selectEmailPrint($emailFields) {
		global $confirm;
		if ($emailFields) {
			echo '<fieldset><legend><a href="#fieldset-email" onclick="return !toggle(\'fieldset-email\');">' . lang('E-mail') . "</a></legend><div id='fieldset-email' class='hidden'>\n";
			echo "<p>" . lang('From') . ": <input name='email_from'>\n";
			echo lang('Subject') . ": <input name='email_subject'>\n";
			echo "<p><textarea name='email_message' rows='15' cols='60'></textarea>\n";
			echo "<p>" . (count($emailFields) == 1 ? '<input type="hidden" name="email_field" value="' . htmlspecialchars(key($emailFields)) . '">' : '<select name="email_field">' . optionlist($emailFields) . '</select> ');
			echo "<input type='submit' name='email' value='" . lang('Send') . "'$confirm>\n";
			echo "</div></fieldset>\n";
		}
	}
	
	function selectColumnsProcess($columns, $indexes) {
		return array(array(), array());
	}
	
	function selectSearchProcess($indexes, $fields) {
		$return = array();
		foreach ((array) $_GET["where"] as $val) {
			if (strlen("$val[col]$val[val]")) {
				$value = $this->processInput($fields[$val["col"]], $val["val"]);
				$cond = ($value == "NULL" ? " IS" : ($val["op"] == "=" ? " =" : " LIKE")) . " $value";
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
	
	function selectOrderProcess($columns, $select, $indexes) {
		if ($_GET["order"]) {
			return array(idf_escape($_GET["order"][0]) . (isset($_GET["desc"][0]) ? " DESC" : ""));
		}
		if ($_GET["index_order"]) {
			return $indexes[$_GET["index_order"]]["columns"];
		}
		unset($indexes["PRIMARY"]);
		$index = reset($indexes);
		return ($index ? $index["columns"] : array());
	}
	
	function selectLimitProcess() {
		return (isset($_GET["limit"]) ? $_GET["limit"] : "30");
	}
	
	function selectLengthProcess() {
		return "100";
	}
	
	function selectEmailProcess($where) {
		global $dbh;
		if ($_POST["email"]) {
			$sent = 0;
			if ($_POST["all"] || $_POST["check"]) {
				$field = idf_escape($_POST["email_field"]);
				$result = $dbh->query("SELECT DISTINCT $field FROM " . idf_escape($_GET["select"])
					. " WHERE $field IS NOT NULL AND $field != ''"
					. ($where ? " AND " . implode(" AND ", $where) : "")
					. ($_POST["all"] ? "" : " AND ((" . implode(") OR (", array_map('where_check', (array) $_POST["check"])) . "))")
				);
				while ($row = $result->fetch_row()) {
					if (is_email($row[0]) && mail($row[0], email_header($_POST["email_subject"]), $_POST["email_message"],
						"MIME-Version: 1.0\nContent-Type: text/plain; charset=utf-8\nContent-Transfer-Encoding: 8bit"
						. (is_email($_POST["email_from"]) ? "\nFrom: $_POST[email_from]" : "") //! should allow address with a name but simple application of email_header() adds the default server domain
					)) {
						$sent++;
					}
				}
				$result->free();
			}
			redirect(remove_from_uri(), lang('%d e-mail(s) have been sent.', $sent));
		}
		return false;
	}
	
	function messageQuery($query) {
		return "<!--\n" . str_replace("--", "--><!--", $query) . "\n-->";
	}
	
	function editFunctions($field) {
		return (isset($_GET["select"]) ? array("orig" => lang('original')) : array()) + array("");
	}
	
	function editInput($table, $field, $attrs, $value) {
		global $dbh;
		$foreign_keys = column_foreign_keys($table);
		foreach ((array) $foreign_keys[$field["field"]] as $foreign_key) {
			if (count($foreign_key["source"]) == 1) {
				$id = idf_escape($foreign_key["target"][0]);
				$name = $this->rowDescription($foreign_key["table"]);
				if (strlen($name) && $dbh->result($dbh->query("SELECT COUNT(*) FROM " . idf_escape($foreign_key["table"]))) <= 1000) { // optionlist with more than 1000 options would be too big
					$return = array();
					$result = $dbh->query("SELECT $id, $name FROM " . idf_escape($foreign_key["table"]) . " ORDER BY 2");
					if ($field["null"] || !$result->num_rows) { // empty <select> is not HTML-valid
						$return[""] = "";
					}
					while ($row = $result->fetch_row()) {
						$return[$row[0]] = $row[1];
					}
					$result->free();
					return "<select$attrs>" . optionlist($return, $value, true) . "</select>";
				}
			}
		}
		if ($field["full_type"] == "tinyint(1)") { // bool
			return '<input type="checkbox" value="' . htmlspecialchars($value ? $value : 1) . '"' . ($value ? ' checked="checked"' : '') . "$attrs>";
		}
		return '';
	}
	
	function processInput($field, $value, $function = "") {
		global $dbh;
		$return = $dbh->quote(ereg('date|timestamp', $field["type"]) ? preg_replace_callback('(' . preg_replace('~(\\\\\\$([0-9]))~', '(?P<p\\2>[0-9]+)', preg_quote(lang('$1-$3-$5'))) . ')', 'conversion_date', $value) : $value);
		if (!ereg('varchar|text', $field["type"]) && $field["full_type"] != "tinyint(1)" && !strlen($value)) {
			$return = "NULL";
		} elseif (ereg('date|time', $field["type"]) && $value == "CURRENT_TIMESTAMP") {
			$return = $value;
		}
		return $return;
	}
	
	function navigation($missing) {
		global $SELF;
		if ($missing != "auth") {
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
						$name = $this->tableName($row);
						if (isset($row["Engine"]) && strlen($name)) { // ignore views and tables without name
							echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . "\">$name</a><br>\n";
						}
					}
				}
			}
		}
	}
	
}
