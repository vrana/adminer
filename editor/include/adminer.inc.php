<?php
namespace Adminer;

class Adminer {
	/** @var Adminer|Plugins */ static $instance;
	/** @visibility protected(set) */ public string $error = ''; // HTML
	/** @var array<string, string[]|string> */ private array $values = array(); // [table => options or one description]

	function name(): string {
		return "<a href='https://www.adminer.org/editor/'" . target_blank() . " id='h1'><img src='../adminer/static/logo.png' width='24' height='24' alt='' id='logo'>" . lang('Editor') . "</a>";
	}

	//! driver, ns

	function credentials(): array {
		return array(SERVER, $_GET["username"], get_password());
	}

	function connectSsl() {
	}

	function permanentLogin(bool $create = false): string {
		return password_file($create);
	}

	function bruteForceKey(): string {
		return $_SERVER["REMOTE_ADDR"];
	}

	function serverName(?string $server): string {
		return '';
	}

	function database(): ?string {
		if (connection()) {
			$databases = adminer()->databases(false);
			if (!$databases) {
				return get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1)"); // username without the database list
			}
			foreach ($databases as $db) {
				if (!information_schema($db)) {
					return $db; // first available database
				}
			}
			return $databases[0];
		}
		return null; // drivers ask for the database also before connecting
	}

	function operators(): array {
		return array("<=", ">=");
	}

	function schemas(): array {
		return schemas();
	}

	function databases(bool $flush = true): array {
		return get_databases($flush);
	}

	function pluginsLinks(): void {
	}

	function queryTimeout(): float {
		return 5;
	}

	function afterConnect(): void {
	}

	function headers(): void {
	}

	function csp(array $csp): array {
		return $csp;
	}

	function verifyVersion(): bool {
		return true;
	}

	function head(?bool $dark = null): bool {
		return true;
	}

	function bodyClass(): void {
		echo " editor";
	}

	function css(): array {
		$return = array();
		foreach (array("", "-dark") as $mode) {
			$filename = "adminer$mode.css";
			if (file_exists($filename)) {
				$file = file_get_contents($filename);
				$return["$filename?v=" . crc32($file)] = ($mode
					? "dark"
					: (preg_match('~prefers-color-scheme:\s*dark~', $file) ? '' : 'light')
				);
			}
		}
		return $return;
	}

	function loginForm(): void {
		echo "<table class='layout'>\n";
		echo adminer()->loginFormField('username', '<tr><th>' . lang('Username') . '<td>', input_hidden("auth[driver]", "server")
			. '<input name="auth[username]" autofocus value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">');
		echo adminer()->loginFormField('password', '<tr><th>' . lang('Password') . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">');
		echo "</table>\n";
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}

	function loginFormField(string $name, string $heading, string $value): string {
		return $heading . $value . "\n";
	}

	function login(string $login, string $password) {
		return true;
	}

	function tableName(array $tableStatus): string {
		return h(isset($tableStatus["Engine"])
			? ($tableStatus["Comment"] != "" ? $tableStatus["Comment"] : $tableStatus["Name"])
			: ""); // ignore views
	}

	function fieldName(array $field, int $order = 0): string {
		return h(preg_replace('~\s+\[.*\]$~', '', ($field["comment"] != "" ? $field["comment"] : $field["field"])));
	}

	function selectLinks(array $tableStatus, ?string $set = ""): void {
		$TABLE = $tableStatus["Name"];
		if ($set !== null) {
			echo '<p class="tabs"><a href="' . h(ME . 'edit=' . url_escape($TABLE) . $set) . '">' . lang('New item') . "</a>\n";
		}
	}

	function foreignKeys(string $table): array {
		return foreign_keys($table);
	}

	function backwardKeys(string $table, string $tableName): array {
		$return = array();
		foreach (
			get_rows("SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = " . q(adminer()->database()) . "
AND REFERENCED_TABLE_SCHEMA = " . q(adminer()->database()) . "
AND REFERENCED_TABLE_NAME = " . q($table) . "
ORDER BY ORDINAL_POSITION", null, "") as $row
		) {
			$return[$row["TABLE_NAME"]]["keys"][$row["CONSTRAINT_NAME"]][$row["COLUMN_NAME"]] = $row["REFERENCED_COLUMN_NAME"];
		}
		foreach ($return as $key => $val) {
			$name = adminer()->tableName(table_status1($key, true));
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

	function backwardKeysPrint(array $backwardKeys, array $row): void {
		foreach ($backwardKeys as $table => $backwardKey) {
			foreach ($backwardKey["keys"] as $cols) {
				$link = ME . 'select=' . url_escape($table);
				$i = 0;
				foreach ($cols as $column => $val) {
					$link .= where_link($i++, $column, $row[$val]);
				}
				echo "<a href='" . h($link) . "'>" . h($backwardKey["name"]) . "</a>";
				$link = ME . 'edit=' . url_escape($table);
				foreach ($cols as $column => $val) {
					$link .= "&set[" . url_escape(bracket_escape($column)) . "]=" . url_escape($row[$val]);
				}
				echo "<a href='" . h($link) . "' title='" . lang('New item') . "'>+</a> ";
			}
		}
	}

	function selectQuery(string $query, float $start, bool $failed = false): string {
		return "<!--\n" . str_replace("--", "--><!-- ", $query) . "\n(" . format_time($start) . ")\n-->\n";
	}

	function rowDescription(string $table): string {
		// first varchar column
		foreach (fields($table) as $field) {
			if (preg_match("~varchar|character varying~", $field["type"])) {
				return idf_escape($field["field"]);
			}
		}
		return "";
	}

	function rowDescriptions(array $rows, array $foreignKeys): array {
		$return = $rows;
		foreach ($rows[0] as $key => $val) {
			if (list($table, $id, $name) = $this->_foreignColumn($foreignKeys, $key)) {
				// find all used ids
				$ids = array();
				foreach ($rows as $row) {
					$ids[$row[$key]] = q($row[$key]);
				}
				// uses constant number of queries to get the descriptions, join would be complex, multiple queries would be slow
				$descriptions = $this->values[$table];
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

	function selectLink(?string $val, array $field) {
	}

	function selectVal(?string $val, ?string $link, array $field, ?string $original): string {
		$return = "$val";
		$link = h($link);
		if (is_blob($field) && !is_utf8($val)) {
			$return = lang('%d byte(s)', strlen($original));
			$size = (function_exists('getimagesizefromstring') ? @getimagesizefromstring($original) : array()); // @ - the notice for other data contains the data; the function is since PHP 5.4
			if ($size) { // the same as in the select-image plugin
				$return = "<img src='$link' alt='$return' $size[3] loading='lazy'>"; // $size[3] is width and height
			}
		}
		if (like_bool($field) && $return != "") { // bool
			$return = (preg_match('~^(1|t|true|y|yes|on)$~i', $val) ? lang('yes') : lang('no'));
		}
		if ($link) {
			$return = "<a href='$link'" . (is_url($link) ? target_blank() : "") . ">$return</a>";
		}
		// Firefox doesn't support <colgroup>
		if (preg_match('~date~', $field["type"])) {
			$return = "<div class='datetime'>$return</div>";
		}
		return $return;
	}

	function editVal(?string $val, array $field): ?string {
		if (preg_match('~date|timestamp~', $field["type"]) && $val !== null) {
			return preg_replace('~^(\d{2}(\d+))-(0?(\d+))-(0?(\d+))~', lang('$1-$3-$5'), $val);
		}
		return $val;
	}

	function config(): array {
		return array();
	}

	function selectColumnsPrint(array $select, array $columns): void {
		// can allow grouping functions by indexes
	}

	/** Get columns having their own search field; they are identified by their position, not by the name in the query string
	* @param Field[] $fields
	* @return string[] negative index => column name
	*/
	private function searchColumns(array $fields): array {
		$return = array();
		$i = 0;
		foreach ($fields as $name => $field) {
			if (
				isset($field["privileges"]["where"]) && $this->fieldName($field) != "" // the same condition as for $search_columns in select.inc.php
				&& ($field["type"] == "enum" || like_bool($field) || is_array($this->foreignKeyOptions($_GET["select"], $name)))
			) {
				$return[--$i] = $name;
			}
		}
		return $return;
	}

	function selectSearchPrint(array $where, array $columns, array $indexes): void {
		$where = (array) $_GET["where"];
		echo '<fieldset id="fieldset-search"><legend>' . lang('Search') . "</legend><div>\n";
		$fields = fields($_GET["select"]);
		foreach ($this->searchColumns($fields) as $i => $name) {
			$field = $fields[$name];
			$val = idx($where[$i], "val");
			echo "<div>" . h($columns[$name]);
			if ($field["type"] == "enum" || like_bool($field)) { //! set - uses 1 << $i and FIND_IN_SET()
				echo ":";
				echo (like_bool($field)
					? "<select name='where[$i][val]' data-default=''>" . optionlist(array("" => "", lang('no'), lang('yes')), $val, true) . "</select>"
					: enum_input("checkbox", " name='where[$i][val][]'", $field, (array) $val, lang('empty'))
				);
			} else {
				$options = $this->foreignKeyOptions($_GET["select"], $name);
				if ($field["null"]) {
					$options[0] = '(' . lang('empty') . ')';
				}
				echo ": <select name='where[$i][val]' data-default=''>" . optionlist($options, $val, true) . "</select>";
			}
			echo "</div>\n";
			unset($columns[$name]);
		}
		$i = 0;
		foreach ($where as $key => $val) {
			if ($key >= 0 && ($val["col"] == "" || $columns[$val["col"]]) && "$val[col]$val[val]" != "") {
				echo "<div><select name='where[$i][col]' data-default=''><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, $val["col"], true) . "</select>";
				echo html_select("where[$i][op]", array(-1 => "") + adminer()->operators(), $val["op"], " data-default=''");
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "' data-default=''"
					. on('keydown', 'selectSearchKeydown') . on('search', 'selectSearchSearch') . "></div>\n";
				$i++;
			}
		}
		echo "<div><select name='where[$i][col]' data-default=''" . on('change', 'selectAddRow') . "><option value=''>(" . lang('anywhere') . ")" . optionlist($columns, null, true) . "</select>";
		echo html_select("where[$i][op]", array(-1 => "") + adminer()->operators(), null, " data-default=''");
		echo "<input type='search' name='where[$i][val]' data-default=''"
			. on('change', 'selectFirstChange') . on('keydown', 'selectSearchKeydown') . on('search', 'selectSearchSearch') . "></div>\n";
		echo "</div></fieldset>\n";
	}

	function selectOrderPrint(array $order, array $columns, array $indexes): void {
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
			echo "<select name='index_order' data-default=''>" . optionlist(array("" => "") + $orders, (idx($_GET["order"], 0) != "" ? "" : $_GET["index_order"]), true) . "</select>";
			echo "</div></fieldset>\n";
		}
		if ($_GET["order"]) {
			echo "<div hidden>" . hidden_fields(array(
				"order" => array(1 => reset($_GET["order"])),
				"desc" => ($_GET["desc"] ? array(1 => 1) : array()),
			)) . "</div>\n";
		}
	}

	function selectLimitPrint(int $limit): void {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		// data-default - the same value as in selectLimitProcess()
		echo html_select("limit", array("", "50", "100"), (string) $limit, " data-default='50'");
		echo "</div></fieldset>\n";
	}

	function selectLengthPrint(string $text_length): void {
	}

	function selectActionPrint(array $indexes): void {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo "</div></fieldset>\n";
	}

	function selectCommandPrint(): bool {
		return true;
	}

	function selectImportPrint(): bool {
		return true;
	}

	function selectEmailPrint(array $emailFields, array $columns): void {
	}

	function selectColumnsProcess(array $columns, array $indexes): array {
		return array(array(), array());
	}

	function selectSearchProcess(array $fields, array $indexes): array {
		$return = array();
		$search_columns = $this->searchColumns($fields);
		foreach ((array) $_GET["where"] as $key => $where) {
			if ($key < 0) { // the column and the operator are given by the position, they are not sent
				$where["col"] = idx($search_columns, $key, "");
				$field = idx($fields, $where["col"], array());
				$where["op"] = ($field && ($field["type"] == "enum" || like_bool($field)) ? "" : "=");
			}
			$col = $where["col"];
			$op = $where["op"];
			$val = $where["val"];
			if (($key >= 0 && $col != "") || $val != "") {
				$conds = array();
				foreach (($col != "" ? array($col => $fields[$col]) : $fields) as $name => $field) {
					if ($col != "" || is_numeric($val) || !preg_match(number_type(), $field["type"])) {
						$name = idf_escape($name);
						if ($col != "" && $field["type"] == "enum") {
							$in = array();
							foreach ($val as $val1) {
								if (preg_match('~val-~', $val1)) {
									$in[] = q(substr($val1, 4));
								}
							}
							$conds[] = (in_array("null", $val) ? "$name IS NULL OR " : "") . ($in ? "$name IN (" . implode(", ", $in) . ")" : "0");
						} else {
							$text_type = preg_match('~char|text|enum|set~', $field["type"]);
							$value = adminer()->processInput($field, (!$op && $text_type && preg_match('~^[^%]+$~', $val) ? "%$val%" : $val));
							$conds[] = driver()->convertSearch($name, $where, $field) . ($value == "NULL" ? " IS" . ($op == ">=" ? " NOT" : "") . " $value"
								: (in_array($op, adminer()->operators()) || $op == "=" ? " $op $value"
								: ($text_type ? " LIKE $value"
								: " IN (" . ($value[0] == "'" ? str_replace(",", "', '", $value) : $value) . ")"
							)));
							if ($key < 0 && $val == "0") {
								$conds[] = "$name IS NULL";
							}
						}
					}
				}
				$return[] = ($conds ? "(" . implode(" OR ", $conds) . ")" : "1 = 0");
			}
		}
		return $return;
	}

	function selectOrderProcess(array $fields, array $indexes): array {
		$index_order = $_GET["index_order"];
		if ($index_order != "") {
			unset($_GET["order"][1]);
		}
		if ($_GET["order"]) {
			return array(idf_escape(reset($_GET["order"])) . ($_GET["desc"] ? " DESC" : ""));
		}
		foreach (($index_order != "" ? array($indexes[$index_order]) : $indexes) as $index) {
			if ($index_order != "" || $index["type"] == "INDEX") {
				$has_desc = array_filter($index["descs"]);
				$desc = false;
				foreach ($index["columns"] as $val) {
					if (preg_match('~date|timestamp~', $fields[$val]["type"])) {
						$desc = true;
						break;
					}
				}
				$return = array();
				foreach ($index["columns"] as $key => $val) {
					$return[] = idf_escape($val) . (($has_desc ? $index["descs"][$key] : $desc) ? " DESC" : "");
				}
				return $return;
			}
		}
		return array();
	}

	function selectLimitProcess(): int {
		return (isset($_GET["limit"]) ? intval($_GET["limit"]) : 50);
	}

	function selectLengthProcess(): string {
		return "100";
	}

	function selectEmailProcess(array $where, array $foreignKeys): bool {
		return false;
	}

	function selectQueryBuild(array $select, array $where, array $group, array $order, int $limit, ?int $page): string {
		return "";
	}

	function messageQuery(string $query, string $time, bool $failed = false): string {
		return " <span class='time'>" . @date("H:i:s") . "</span><!--\n" . str_replace("--", "--><!-- ", $query) . "\n" . ($time ? "($time)\n" : "") . "-->";
	}

	function editRowPrint(string $table, array $fields, $row, ?bool $update): void {
	}

	function editFunctions(array $field): array {
		$return = array();
		if ($field["null"] && preg_match('~blob~', $field["type"])) {
			$return["NULL"] = lang('empty');
		}
		$return[""] = ($field["null"] || $field["auto_increment"] || like_bool($field) ? "" : "*");
		//! respect driver
		if (preg_match('~date|time~', $field["type"])) {
			$return["now"] = lang('now');
		}
		if (preg_match('~_(md5|sha1)$~i', $field["field"], $match)) {
			$return[] = strtolower($match[1]);
		}
		return $return;
	}

	function editInput(?string $table, array $field, string $attrs, $value): string {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='orig' checked><i>" . lang('original') . "</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, lang('empty'))
			;
		}
		$options = $this->foreignKeyOptions($table, $field["field"], $value);
		if ($options !== null) {
			return (is_array($options)
				? "<select$attrs>" . optionlist($options, (string) $value, true) . "</select>"
				: "<input value='" . h($value) . "'$attrs class='hidden'>"
					. "<input value='" . h($options) . "' class='jsonly'"
						. on('input', 'whisper', ME . "script=complete&source=" . url_escape($table) . "&field=" . url_escape($field["field"]) . "&value=") . ">"
					. "<div" . on('click', 'whisperClick') . "></div>"
			);
		}
		if (like_bool($field)) {
			return '<input type="checkbox" value="1"' . (preg_match('~^(1|t|true|y|yes|on)$~i', $value) ? ' checked' : '') . "$attrs>";
		}
		$hint = "";
		if (preg_match('~time~', $field["type"])) {
			$hint = lang('HH:MM:SS');
		}
		if (preg_match('~date|timestamp~', $field["type"])) {
			$hint = lang('[yyyy]-mm-dd') . ($hint ? " [$hint]" : "");
		}
		if ($hint) {
			return "<input value='" . h($value) . "'$attrs> ($hint)"; //! maxlength
		}
		if (preg_match('~_(md5|sha1)$~i', $field["field"])) {
			return "<input type='password' value='" . h($value) . "'$attrs>";
		}
		return '';
	}

	function editHint(?string $table, array $field, ?string $value): string {
		return (preg_match('~\s+(\[.*\])$~', ($field["comment"] != "" ? $field["comment"] : $field["field"]), $match) ? h(" $match[1]") : '');
	}

	function processInput(array $field, string $value, ?string $function = ""): string {
		if ($function == "now") {
			return "$function()";
		}
		$return = $value;
		if (
			preg_match('~date|timestamp~', $field["type"])
			&& preg_match('(^' . str_replace('\$1', '(?P<p1>\d*)', preg_replace('~(\\\\\\$([2-6]))~', '(?P<p\2>\d{1,2})', preg_quote(lang('$1-$3-$5')))) . '(.*))', $value, $match)
		) {
			$return = ($match["p1"] != "" ? $match["p1"] : ($match["p2"] != "" ? ($match["p2"] < 70 ? 20 : 19) . $match["p2"] : gmdate("Y")))
				. "-$match[p3]$match[p4]-$match[p5]$match[p6]" . end($match);
		}
		$return = q($return);
		if ($value == "" && like_bool($field)) {
			$return = "'0'";
		} elseif ($value == "" && ($field["null"] || !preg_match('~char|text~', $field["type"]))) {
			$return = "NULL";
		} elseif (preg_match('~^(md5|sha1)$~', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}

	function dumpOutput(): array {
		return array();
	}

	function dumpFormat(): array {
		return array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	function dumpDatabase(string $db): void {
	}

	function dumpTable(string $table, string $style, int $is_view = 0): void {
		echo "\xef\xbb\xbf"; // UTF-8 byte order mark
	}

	function dumpData(string $table, string $style, string $query): void {
		$result = connection()->query($query, 1); // 1 - MYSQLI_USE_RESULT
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

	function dumpFilename(string $identifier): string {
		return friendly_url($identifier);
	}

	function dumpHeaders(string $identifier, bool $multi_table = false): string {
		$ext = "csv";
		header("Content-Type: text/csv; charset=utf-8");
		return $ext;
	}

	function dumpFooter(): void {
	}

	function importServerPath(): string {
		return '';
	}

	function homepage(): bool {
		return true;
	}

	function navigation(string $missing): void {
		echo "<h1>" . adminer()->name() . " <span class='version'>" . VERSION;
		$new_version = $_COOKIE["adminer_version"];
		echo " <a href='https://www.adminer.org/editor/#download'" . target_blank() . " id='version'>"
			. (version_compare(VERSION, $new_version) < 0 ? h($new_version) : "") . version_iframe() . "</a>";
		echo "</span></h1>\n";
		switch_lang();
		if ($missing == "auth") {
			$first = true;
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers[""] as $username => $password) {
					if ($password !== null) {
						if ($first) {
							echo "<ul id='logins'" . on('mouseover', 'menuOver') . on('mouseout', 'menuOut') . ">";
							$first = false;
						}
						echo "<li><a href='" . h(auth_url($vendor, "", $username)) . "'>" . ($username != "" ? h($username) : "<i>" . lang('empty') . "</i>") . "</a>\n";
					}
				}
			}
		} else {
			adminer()->databasesPrint($missing);
			$actions = adminer()->menuActions(array(), $missing);
			echo ($actions ? "<p class='links'>\n" . implode("\n", $actions) . "\n" : "");
			if ($missing != "db" && $missing != "ns") {
				$table_status = table_status('', true);
				if (!$table_status) {
					echo "<p class='message'>" . lang('No tables.') . "\n";
				} else {
					adminer()->tablesPrint($table_status);
				}
			}
		}
	}

	function syntaxHighlighting(array $tables): void {
	}

	function databasesPrint(string $missing): void {
	}

	function menuActions(array $actions, string $missing): array {
		return $actions;
	}

	function tablesPrint(array $tables): void {
		echo "<ul id='tables'" . on('mouseover', 'menuOver') . on('mouseout', 'menuOut') . ">";
		foreach ($tables as $row) {
			echo '<li>';
			$name = adminer()->tableName($row);
			if ($name != "") { // ignore tables without name
				echo "<a href='" . h(ME) . 'select=' . url_escape($row["Name"]) . "'"
					. bold($_GET["select"] == $row["Name"] || $_GET["edit"] == $row["Name"], "select")
					. " title='" . lang('Select data') . "'>$name</a>\n"
				;
			}
		}
		echo "</ul>\n";
	}

	/** Get the foreign key of a column with a description
	* @return array{string, string, string}|void [table, id, name]
	*/
	function _foreignColumn(array $foreignKeys, string $column) {
		foreach ((array) $foreignKeys[$column] as $foreignKey) {
			if (count($foreignKey["source"]) == 1) {
				$name = adminer()->rowDescription($foreignKey["table"]);
				if ($name != "") {
					$id = idf_escape($foreignKey["target"][0]);
					return array($foreignKey["table"], $id, $name);
				}
			}
		}
	}

	/** Get options of a foreign key column
	* @return string[]|string|void options, one description if there are too many rows or nothing if it's not a foreign key
	*/
	private function foreignKeyOptions(string $table, string $column, ?string $value = null) {
		if (list($target, $id, $name) = $this->_foreignColumn(column_foreign_keys($table), $column)) {
			$return = &$this->values[$target];
			if ($return === null) {
				$table_status = table_status1($target);
				$return = ($table_status["Rows"] > 1000 ? "" : array("" => "") + get_key_vals("SELECT $id, $name FROM " . table($target) . " ORDER BY 2"));
			}
			if (!$return && $value !== null) {
				return get_val("SELECT $name FROM " . table($target) . " WHERE $id = " . q($value));
			}
			return $return;
		}
	}
}
