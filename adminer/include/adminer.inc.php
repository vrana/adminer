<?php
namespace Adminer;

// any method change in this file should be transferred to editor/include/adminer.inc.php and plugins.inc.php

class Adminer {
	/** @var list<string> */ public $operators; // operators used in select, null for all operators
	/** @var string @visibility protected(set) */ public $error = ''; // HTML

	/** Name in title and navigation
	* @return string HTML code
	*/
	function name() {
		return "<a href='https://www.adminer.org/'" . target_blank() . " id='h1'>Adminer</a>";
	}

	/** Connection parameters
	* @return array{string, string, string}
	*/
	function credentials() {
		return array(SERVER, $_GET["username"], get_password());
	}

	/** Get SSL connection options
	* @return string[]|void
	*/
	function connectSsl() {
	}

	/** Get key used for permanent login
	* @return string cryptic string which gets combined with password or false in case of an error
	*/
	function permanentLogin(bool $create = false) {
		return password_file($create);
	}

	/** Return key used to group brute force attacks; behind a reverse proxy, you want to return the last part of X-Forwarded-For
	* @return string
	*/
	function bruteForceKey() {
		return $_SERVER["REMOTE_ADDR"];
	}

	/** Get server name displayed in breadcrumbs
	* @return string HTML code or null
	*/
	function serverName(string $server) {
		return h($server);
	}

	/** Identifier of selected database
	* @return string
	*/
	function database() {
		// should be used everywhere instead of DB
		return DB;
	}

	/** Get cached list of databases
	* @return list<string>
	*/
	function databases(bool $flush = true) {
		return get_databases($flush);
	}

	/** Get list of schemas
	* @return list<string>
	*/
	function schemas() {
		return schemas();
	}

	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout() {
		return 2;
	}

	/** Headers to send before HTML output
	* @return void
	*/
	function headers() {
	}

	/** Get Content Security Policy headers
	* @return list<string[]> of arrays with directive name in key, allowed sources in value
	*/
	function csp() {
		return csp();
	}

	/** Print HTML code inside <head>
	* @param bool $dark dark CSS: false to disable, true to force, null to base on user preferences
	* @return bool true to link favicon.ico
	*/
	function head(bool $dark = null) {
		// this is matched by compile.php
		echo "<link rel='stylesheet' href='../externals/jush/jush.css'>\n";
		echo ($dark !== false ? "<link rel='stylesheet'" . ($dark ? "" : " media='(prefers-color-scheme: dark)'") . " href='../externals/jush/jush-dark.css'>\n" : "");
		return true;
	}

	/** Get URLs of the CSS files
	* @return list<string>
	*/
	function css() {
		$return = array();
		foreach (array("", "-dark") as $mode) {
			$filename = "adminer$mode.css";
			if (file_exists($filename)) {
				$return[] = "$filename?v=" . crc32(file_get_contents($filename));
			}
		}
		return $return;
	}

	/** Print login form
	* @return void
	*/
	function loginForm() {
		global $drivers;
		echo "<table class='layout'>\n";
		// this is matched by compile.php
		echo $this->loginFormField('driver', '<tr><th>' . lang('System') . '<td>', html_select("auth[driver]", $drivers, DRIVER, "loginDriver(this);"));
		echo $this->loginFormField('server', '<tr><th>' . lang('Server') . '<td>', '<input name="auth[server]" value="' . h(SERVER) . '" title="hostname[:port]" placeholder="localhost" autocapitalize="off">');
		// this is matched by compile.php
		echo $this->loginFormField('username', '<tr><th>' . lang('Username') . '<td>', '<input name="auth[username]" id="username" autofocus value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">' . script("qs('#username').form['auth[driver]'].onchange();"));
		echo $this->loginFormField('password', '<tr><th>' . lang('Password') . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">');
		echo $this->loginFormField('db', '<tr><th>' . lang('Database') . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">');
		echo "</table>\n";
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}

	/** Get login form field
	* @param string $heading HTML
	* @param string $value HTML
	* @return string
	*/
	function loginFormField(string $name, string $heading, string $value) {
		return $heading . $value . "\n";
	}

	/** Authorize the user
	* @return mixed true for success, string for error message, false for unknown error
	*/
	function login(string $login, string $password) {
		if ($password == "") {
			return lang('Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.', target_blank());
		}
		return true;
	}

	/** Table caption used in navigation and headings
	* @param TableStatus $tableStatus result of table_status1()
	* @return string HTML code, "" to ignore table
	*/
	function tableName(array $tableStatus) {
		return h($tableStatus["Name"]);
	}

	/** Field caption used in select and edit
	* @param Field $field single field returned from fields()
	* @param int $order order of column in select
	* @return string HTML code, "" to ignore field
	*/
	function fieldName(array $field, int $order = 0) {
		$type = $field["full_type"];
		$comment = $field["comment"];
		return '<span title="' . h($type . ($comment != "" ? ($type ? ": " : "") . $comment : '')) . '">' . h($field["field"]) . '</span>';
	}

	/** Print links after select heading
	* @param TableStatus $tableStatus result of table_status1()
	* @param string $set new item options, NULL for no new item
	* @return void
	*/
	function selectLinks(array $tableStatus, string $set = "") {
		global $driver;
		echo '<p class="links">';
		$links = array("select" => lang('Select data'));
		if (support("table") || support("indexes")) {
			$links["table"] = lang('Show structure');
		}
		$is_view = false;
		if (support("table")) {
			$is_view = is_view($tableStatus);
			if ($is_view) {
				$links["view"] = lang('Alter view');
			} else {
				$links["create"] = lang('Alter table');
			}
		}
		if ($set !== null) {
			$links["edit"] = lang('New item');
		}
		$name = $tableStatus["Name"];
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . urlencode($name) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo doc_link(array(JUSH => $driver->tableHelp($name, $is_view)), "?");
		echo "\n";
	}

	/** Get foreign keys for table
	* @return ForeignKey[] same format as foreign_keys()
	*/
	function foreignKeys(string $table) {
		return foreign_keys($table);
	}

	/** Find backward keys for table
	* @return BackwardKey[]
	*/
	function backwardKeys(string $table, string $tableName) {
		return array();
	}

	/** Print backward keys for row
	* @param BackwardKey[] $backwardKeys result of $this->backwardKeys()
	* @param string[] $row
	* @return void
	*/
	function backwardKeysPrint(array $backwardKeys, array $row) {
	}

	/** Query printed in select before execution
	* @param string $query query to be executed
	* @param float $start start time of the query
	* @return string
	*/
	function selectQuery(string $query, float $start, bool $failed = false) {
		global $driver;
		$return = "</p>\n"; // required for IE9 inline edit
		if (!$failed && ($warnings = $driver->warnings())) {
			$id = "warnings";
			$return = ", <a href='#$id'>" . lang('Warnings') . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "")
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-" . JUSH . "'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . urlencode($query) . "'>" . lang('Edit') . "</a>" : "")
			. $return
		;
	}

	/** Query printed in SQL command before execution
	* @param string $query query to be executed
	* @return string escaped query to be printed
	*/
	function sqlCommandQuery(string $query) {
		return shorten_utf8(trim($query), 1000);
	}

	/** Print HTML code just before the Execute button in SQL command
	* @return void
	*/
	function sqlPrintAfter() {
	}

	/** Description of a row in a table
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription(string $table) {
		return "";
	}

	/** Get descriptions of selected data
	* @param list<string[]> $rows all data to print
	* @param ForeignKey[] $foreignKeys
	* @return list<string[]>
	*/
	function rowDescriptions(array $rows, array $foreignKeys) {
		return $rows;
	}

	/** Get a link to use in select table
	* @param string $val raw value of the field
	* @param Field $field single field returned from fields()
	* @return string|void null to create the default link
	*/
	function selectLink(string $val, array $field) {
	}

	/** Value printed in select table
	* @param string $val HTML-escaped value to print
	* @param string $link link to foreign key
	* @param Field $field single field returned from fields()
	* @param string $original original value before applying editVal() and escaping
	* @return string
	*/
	function selectVal(string $val, string $link, array $field, string $original) {
		$return = ($val === null ? "<i>NULL</i>"
			: (preg_match("~char|binary|boolean~", $field["type"]) && !preg_match("~var~", $field["type"]) ? "<code>$val</code>"
			: (preg_match('~json~', $field["type"]) ? "<code class='jush-js'>$val</code>"
			: $val)
		));
		if (preg_match('~blob|bytea|raw|file~', $field["type"]) && !is_utf8($val)) {
			$return = "<i>" . lang('%d byte(s)', strlen($original)) . "</i>";
		}
		return ($link ? "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : "") . ">$return</a>" : $return);
	}

	/** Value conversion used in select and edit
	* @param Field $field single field returned from fields()
	* @return string
	*/
	function editVal(string $val, array $field) {
		return $val;
	}

	/** Print table structure in tabular format
	* @param Field[] $fields data about individual fields
	* @param TableStatus $tableStatus
	* @return void
	*/
	function tableStructurePrint(array $fields, array $tableStatus = null) {
		global $driver;
		echo "<div class='scrollable'>\n";
		echo "<table class='nowrap odds'>\n";
		echo "<thead><tr><th>" . lang('Column') . "<td>" . lang('Type') . (support("comment") ? "<td>" . lang('Comment') : "") . "</thead>\n";
		$structured_types = $driver->structuredTypes();
		foreach ($fields as $field) {
			echo "<tr><th>" . h($field["field"]);
			$type = h($field["full_type"]);
			$collation = h($field["collation"]);
			echo "<td><span title='$collation'>"
				. (in_array($type, (array) $structured_types[lang('User types')])
					? "<a href='" . h(ME . 'type=' . urlencode($type)) . "'>$type</a>"
					: $type . ($collation && isset($tableStatus["Collation"]) && $collation != $tableStatus["Collation"] ? " $collation" : ""))
				. "</span>"
			;
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . lang('Auto Increment') . "</i>" : "");
			$default = h($field["default"]);
			echo (isset($field["default"]) ? " <span title='" . lang('Default value') . "'>[<b>" . ($field["generated"] ? "<code class='jush-" . JUSH . "'>$default</code>" : $default) . "</b>]</span>" : "");
			echo (support("comment") ? "<td>" . h($field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
	}

	/** Print list of indexes on table in tabular format
	* @param Index[] $indexes data about all indexes on a table
	* @return void
	*/
	function tableIndexesPrint(array $indexes) {
		echo "<table>\n";
		foreach ($indexes as $name => $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . $index["lengths"][$key] . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}
			echo "<tr title='" . h($name) . "'><th>$index[type]<td>" . implode(", ", $print) . "\n";
		}
		echo "</table>\n";
	}

	/** Print columns box in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param string[] $columns selectable columns
	* @return void
	*/
	function selectColumnsPrint(array $select, array $columns) {
		global $driver;
		print_fieldset("select", lang('Select'), $select);
		$i = 0;
		$select[""] = array();
		foreach ($select as $key => $val) {
			$val = idx($_GET["columns"], $key, array());
			$column = select_input(
				" name='columns[$i][col]'",
				$columns,
				$val["col"],
				($key !== "" ? "selectFieldChange" : "selectAddRow")
			);
			echo "<div>" . ($driver->functions || $driver->grouping ? html_select("columns[$i][fun]", array(-1 => "") + array_filter(array(lang('Functions') => $driver->functions, lang('Aggregation') => $driver->grouping)), $val["fun"])
				. on_help("event.target.value && event.target.value.replace(/ |\$/, '(') + ')'", 1)
				. script("qsl('select').onchange = function () { helpClose();" . ($key !== "" ? "" : " qsl('select, input', this.parentNode).onchange();") . " };", "")
				. "($column)" : $column) . "</div>\n";
			$i++;
		}
		echo "</div></fieldset>\n";
	}

	/** Print search box in select
	* @param list<string> $where result of selectSearchProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return void
	*/
	function selectSearchPrint(array $where, array $columns, array $indexes) {
		print_fieldset("search", lang('Search'), $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "<div>(<i>" . implode("</i>, <i>", array_map('Adminer\h', $index["columns"])) . "</i>) AGAINST";
				echo " <input type='search' name='fulltext[$i]' value='" . h($_GET["fulltext"][$i]) . "'>";
				echo script("qsl('input').oninput = selectFieldChange;", "");
				echo checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL");
				echo "</div>\n";
			}
		}
		$change_next = "this.parentNode.firstChild.onchange();";
		foreach (array_merge((array) $_GET["where"], array(array())) as $i => $val) {
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators))) {
				echo "<div>" . select_input(
					" name='where[$i][col]'",
					$columns,
					$val["col"],
					($val ? "selectFieldChange" : "selectAddRow"),
					"(" . lang('anywhere') . ")"
				);
				echo html_select("where[$i][op]", $this->operators, $val["op"], $change_next);
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "'>";
				echo script("mixin(qsl('input'), {oninput: function () { $change_next }, onkeydown: selectSearchKeydown, onsearch: selectSearchSearch});", "");
				echo "</div>\n";
			}
		}
		echo "</div></fieldset>\n";
	}

	/** Print order box in select
	* @param list<string> $order result of selectOrderProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return void
	*/
	function selectOrderPrint(array $order, array $columns, array $indexes) {
		print_fieldset("sort", lang('Sort'), $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]'", $columns, $val, "selectFieldChange");
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang('descending')) . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]'", $columns, "", "selectAddRow");
		echo checkbox("desc[$i]", 1, false, lang('descending')) . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select
	* @param string $limit result of selectLimitProcess()
	* @return void
	*/
	function selectLimitPrint(string $limit) {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		echo "<input type='number' name='limit' class='size' value='" . h($limit) . "'>";
		echo script("qsl('input').oninput = selectFieldChange;", "");
		echo "</div></fieldset>\n";
	}

	/** Print text length box in select
	* @param string $text_length result of selectLengthProcess()
	* @return void
	*/
	function selectLengthPrint(string $text_length) {
		if ($text_length !== null) {
			echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
			echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "'>";
			echo "</div></fieldset>\n";
		}
	}

	/** Print action box in select
	* @param Index[] $indexes
	* @return void
	*/
	function selectActionPrint(array $indexes) {
		echo "<fieldset><legend>" . lang('Action') . "</legend><div>";
		echo "<input type='submit' value='" . lang('Select') . "'>";
		echo " <span id='noindex' title='" . lang('Full table scan') . "'></span>";
		echo "<script" . nonce() . ">\n";
		echo "const indexColumns = ";
		$columns = array();
		foreach ($indexes as $index) {
			$current_key = reset($index["columns"]);
			if ($index["type"] != "FULLTEXT" && $current_key) {
				$columns[$current_key] = 1;
			}
		}
		$columns[""] = 1;
		foreach ($columns as $key => $val) {
			json_row($key);
		}
		echo ";\n";
		echo "selectFieldChange.call(qs('#form')['select']);\n";
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
	* @param string[] $emailFields fields holding e-mails
	* @param string[] $columns selectable columns
	* @return void
	*/
	function selectEmailPrint(array $emailFields, array $columns) {
	}

	/** Process columns box in select
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return list<list<string>> [[select_expressions], [group_expressions]]
	*/
	function selectColumnsProcess(array $columns, array $indexes) {
		global $driver;
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || ($val["col"] != "" && (!$val["fun"] || in_array($val["fun"], $driver->functions) || in_array($val["fun"], $driver->grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], ($val["col"] != "" ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], $driver->grouping)) {
					$group[] = $select[$key];
				}
			}
		}
		return array($select, $group);
	}

	/** Process search box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by AND
	*/
	function selectSearchProcess(array $fields, array $indexes) {
		global $connection, $driver;
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && $_GET["fulltext"][$i] != "") {
				$return[] = "MATCH (" . implode(", ", array_map('Adminer\idf_escape', $index["columns"])) . ") AGAINST (" . q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		foreach ((array) $_GET["where"] as $key => $val) {
			if ("$val[col]$val[val]" != "" && in_array($val["op"], $this->operators)) {
				$prefix = "";
				$cond = " $val[op]";
				if (preg_match('~IN$~', $val["op"])) {
					$in = process_length($val["val"]);
					$cond .= " " . ($in != "" ? $in : "(NULL)");
				} elseif ($val["op"] == "SQL") {
					$cond = " $val[val]"; // SQL injection
				} elseif ($val["op"] == "LIKE %%") {
					$cond = " LIKE " . $this->processInput($fields[$val["col"]], "%$val[val]%");
				} elseif ($val["op"] == "ILIKE %%") {
					$cond = " ILIKE " . $this->processInput($fields[$val["col"]], "%$val[val]%");
				} elseif ($val["op"] == "FIND_IN_SET") {
					$prefix = "$val[op](" . q($val["val"]) . ", ";
					$cond = ")";
				} elseif (!preg_match('~NULL$~', $val["op"])) {
					$cond .= " " . $this->processInput($fields[$val["col"]], $val["val"]);
				}
				if ($val["col"] != "") {
					$return[] = $prefix . $driver->convertSearch(idf_escape($val["col"]), $val, $fields[$val["col"]]) . $cond;
				} else {
					// find anywhere
					$cols = array();
					foreach ($fields as $name => $field) {
						if (
							isset($field["privileges"]["where"])
							&& (preg_match('~^[-\d.' . (preg_match('~IN$~', $val["op"]) ? ',' : '') . ']+$~', $val["val"]) || !preg_match('~' . number_type() . '|bit~', $field["type"]))
							&& (!preg_match("~[\x80-\xFF]~", $val["val"]) || preg_match('~char|text|enum|set~', $field["type"]))
							&& (!preg_match('~date|timestamp~', $field["type"]) || preg_match('~^\d+-\d+-\d+~', $val["val"]))
						) {
							$cols[] = $prefix . $driver->convertSearch(idf_escape($name), $val, $field) . $cond;
						}
					}
					$return[] = ($cols ? "(" . implode(" OR ", $cols) . ")" : "1 = 0");
				}
			}
		}
		return $return;
	}

	/** Process order box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by comma
	*/
	function selectOrderProcess(array $fields, array $indexes) {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$return[] = (preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val) ? $val : idf_escape($val)) //! MS SQL uses []
					. (isset($_GET["desc"][$key]) ? " DESC" : "")
				;
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
	* @param string[] $where AND conditions
	* @param ForeignKey[] $foreignKeys
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess(array $where, array $foreignKeys) {
		return false;
	}

	/** Build SQL query used in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param list<string> $where result of selectSearchProcess()
	* @param list<string> $group result of selectColumnsProcess()[1]
	* @param list<string> $order result of selectOrderProcess()
	* @param int $limit result of selectLimitProcess()
	* @param int $page index of page starting at zero
	* @return string empty string to use default query
	*/
	function selectQueryBuild(array $select, array $where, array $group, array $order, int $limit, int $page) {
		return "";
	}

	/** Query printed after execution in the message
	* @param string $query executed query
	* @param string $time elapsed time
	* @return string
	*/
	function messageQuery(string $query, string $time, bool $failed = false) {
		global $driver;
		restart_session();
		$history = &get_session("queries");
		if (!idx($history, $_GET["db"])) {
			$history[$_GET["db"]] = array();
		}
		if (strlen($query) > 1e6) {
			$query = preg_replace('~[\x80-\xFF]+$~', '', substr($query, 0, 1e6)) . "\n…"; // [\x80-\xFF] - valid UTF-8, \n - can end by one-line comment
		}
		$history[$_GET["db"]][] = array($query, time(), $time); // not DB - $_GET["db"] is changed in database.inc.php //! respect $_GET["ns"]
		$sql_id = "sql-" . count($history[$_GET["db"]]);
		$return = "<a href='#$sql_id' class='toggle'>" . lang('SQL command') . "</a>\n";
		if (!$failed && ($warnings = $driver->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . lang('Warnings') . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-" . JUSH . "'>" . shorten_utf8($query, 1000) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql") ? '<p><a href="' . h(str_replace("db=" . urlencode(DB), "db=" . urlencode($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a>' : '')
			. '</div>'
		;
	}

	/** Print before edit form
	* @param Field[] $fields
	* @param mixed $row
	* @return void
	*/
	function editRowPrint(string $table, array $fields, $row, bool $update) {
	}

	/** Functions displayed in edit form
	* @param Field $field single field from fields()
	* @return list<string>
	*/
	function editFunctions(array $field) {
		global $driver;
		$return = ($field["null"] ? "NULL/" : "");
		$update = isset($_GET["select"]) || where($_GET);
		foreach ($driver->editFunctions as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && $update)) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || preg_match("~$pattern~", $field["type"])) {
						$return .= "/$val";
					}
				}
			}
			if ($key && !preg_match('~set|blob|bytea|raw|file|bool~', $field["type"])) {
				$return .= "/SQL";
			}
		}
		if ($field["auto_increment"] && !$update) {
			$return = lang('Auto Increment');
		}
		return explode("/", $return);
	}

	/** Get options to display edit field
	* @param string $table table name
	* @param Field $field single field from fields()
	* @param string $attrs attributes to use inside the tag
	* @return string custom input field or empty string for default
	*/
	function editInput(string $table, array $field, string $attrs, string $value) {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='-1' checked><i>" . lang('original') . "</i></label> " : "")
				. ($field["null"] ? "<label><input type='radio'$attrs value=''" . ($value !== null || isset($_GET["select"]) ? "" : " checked") . "><i>NULL</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, $value === 0 ? 0 : null) // 0 - empty value
			;
		}
		return "";
	}

	/** Get hint for edit field
	* @param string $table table name
	* @param Field $field single field from fields()
	* @return string
	*/
	function editHint(string $table, array $field, string $value) {
		return "";
	}

	/** Process sent input
	* @param Field $field single field from fields()
	* @return string expression to use in a query
	*/
	function processInput(array $field, string $value, string $function = "") {
		if ($function == "SQL") {
			return $value; // SQL injection
		}
		$name = $field["field"];
		$return = q($value);
		if (preg_match('~^(now|getdate|uuid)$~', $function)) {
			$return = "$function()";
		} elseif (preg_match('~^current_(date|timestamp)$~', $function)) {
			$return = $function;
		} elseif (preg_match('~^([+-]|\|\|)$~', $function)) {
			$return = idf_escape($name) . " $function $return";
		} elseif (preg_match('~^[+-] interval$~', $function)) {
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i", $value) ? $value : $return);
		} elseif (preg_match('~^(addtime|subtime|concat)$~', $function)) {
			$return = "$function(" . idf_escape($name) . ", $return)";
		} elseif (preg_match('~^(md5|sha1|password|encrypt)$~', $function)) {
			$return = "$function($return)";
		}
		return unconvert_field($field, $return);
	}

	/** Return export output options
	* @return string[]
	*/
	function dumpOutput() {
		$return = array('text' => lang('open'), 'file' => lang('save'));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}

	/** Return export format options
	* @return string[] empty to disable export
	*/
	function dumpFormat() {
		return (support("dump") ? array('sql' => 'SQL') : array()) + array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	/** Export database structure
	* @return void prints data
	*/
	function dumpDatabase(string $db) {
	}

	/** Export table structure
	* @param int $is_view 0 table, 1 view, 2 temporary view table
	* @return void prints data
	*/
	function dumpTable(string $table, string $style, int $is_view = 0) {
		if ($_POST["format"] != "sql") {
			echo "\xef\xbb\xbf"; // UTF-8 byte order mark
			if ($style) {
				dump_csv(array_keys(fields($table)));
			}
		} else {
			if ($is_view == 2) {
				$fields = array();
				foreach (fields($table) as $name => $field) {
					$fields[] = idf_escape($name) . " $field[full_type]";
				}
				$create = "CREATE TABLE " . table($table) . " (" . implode(", ", $fields) . ")";
			} else {
				$create = create_sql($table, $_POST["auto_increment"], $style);
			}
			set_utf8mb4($create);
			if ($style && $create) {
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
	* @return void prints data
	*/
	function dumpData(string $table, string $style, string $query) {
		global $connection;
		if ($style) {
			$max_packet = (JUSH == "sqlite" ? 0 : 1048576); // default, minimum is 1024
			$fields = array();
			$identity_insert = false;
			if ($_POST["format"] == "sql") {
				if ($style == "TRUNCATE+INSERT") {
					echo truncate_sql($table) . ";\n";
				}
				$fields = fields($table);
				if (JUSH == "mssql") {
					foreach ($fields as $field) {
						if ($field["auto_increment"]) {
							echo "SET IDENTITY_INSERT " . table($table) . " ON;\n";
							$identity_insert = true;
							break;
						}
					}
				}
			}
			$result = $connection->query($query, 1); // 1 - MYSQLI_USE_RESULT //! enum and set as numbers
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$generated = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				while ($row = $result->$fetch_function()) {
					if (!$keys) {
						$values = array();
						foreach ($row as $val) {
							$field = $result->fetch_field();
							if (idx($fields[$field->name], 'generated')) {
								$generated[$field->name] = true;
								continue;
							}
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
							$insert = "INSERT INTO " . table($table) . " (" . implode(", ", array_map('Adminer\idf_escape', $keys)) . ") VALUES";
						}
						foreach ($row as $key => $val) {
							if ($generated[$key]) {
								unset($row[$key]);
								continue;
							}
							$field = $fields[$key];
							$row[$key] = ($val !== null
								? unconvert_field($field, preg_match(number_type(), $field["type"]) && !preg_match('~\[~', $field["full_type"]) && is_numeric($val) ? $val : q(($val === false ? 0 : $val)))
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
			if ($identity_insert) {
				echo "SET IDENTITY_INSERT " . table($table) . " OFF;\n";
			}
		}
	}

	/** Set export filename
	* @return string filename without extension
	*/
	function dumpFilename(string $identifier) {
		return friendly_url($identifier != "" ? $identifier : (SERVER != "" ? SERVER : "localhost"));
	}

	/** Send headers for export
	* @return string extension
	*/
	function dumpHeaders(string $identifier, bool $multi_table = false) {
		$output = $_POST["output"];
		$ext = (preg_match('~sql~', $_POST["format"]) ? "sql" : ($multi_table ? "tar" : "csv")); // multiple CSV packed to TAR
		header("Content-Type: " .
			($output == "gz" ? "application/x-gzip" :
			($ext == "tar" ? "application/x-tar" :
			($ext == "sql" || $output != "file" ? "text/plain" : "text/csv") . "; charset=utf-8"
		)));
		if ($output == "gz") {
			ob_start(function ($string) {
				// ob_start() callback receives an optional parameter $phase but gzencode() accepts optional parameter $level
				return gzencode($string);
			}, 1e6);
		}
		return $ext;
	}

	/** Print text after export
	* @return void prints data
	*/
	function dumpFooter() {
		if ($_POST["format"] == "sql") {
			echo "-- " . gmdate("Y-m-d H:i:s e") . "\n";
		}
	}

	/** Set the path of the file for webserver load
	* @return string path of the sql dump file
	*/
	function importServerPath() {
		return "adminer.sql";
	}

	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage() {
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . lang('Alter database') . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema')) . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . lang('Database schema') . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . lang('Privileges') . "</a>\n" : "");
		return true;
	}

	/** Print navigation after Adminer title
	* @param string $missing can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	* @return void
	*/
	function navigation(string $missing) {
		global $VERSION, $drivers, $connection;
		echo "<h1>" . $this->name() . " <span class='version'>$VERSION";
		$new_version = $_COOKIE["adminer_version"];
		echo " <a href='https://www.adminer.org/#download'" . target_blank() . " id='version'>" . (version_compare($VERSION, $new_version) < 0 ? h($new_version) : "") . "</a>";
		echo "</span></h1>\n";
		// this is matched by compile.php
		switch_lang();
		if ($missing == "auth") {
			$output = "";
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers as $server => $usernames) {
					$name = h(get_setting("vendor-$vendor-$server") ?: $drivers[$vendor]);
					foreach ($usernames as $username => $password) {
						if ($password !== null) {
							$dbs = $_SESSION["db"][$vendor][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								$output .= "<li><a href='" . h(auth_url($vendor, $server, $username, $db)) . "'>($name) " . h($username . ($server != "" ? "@" . $this->serverName($server) : "") . ($db != "" ? " - $db" : "")) . "</a>\n";
							}
						}
					}
				}
			}
			if ($output) {
				echo "<ul id='logins'>\n$output</ul>\n" . script("mixin(qs('#logins'), {onmouseover: menuOver, onmouseout: menuOut});");
			}
		} else {
			$tables = array();
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				$connection->select_db(DB);
				$tables = table_status('', true);
			}
			$this->syntaxHighlighting($tables);
			$this->databasesPrint($missing);
			$actions = array();
			if (DB == "" || !$missing) {
				if (support("sql")) {
					$actions[] = "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . lang('SQL command') . "</a>";
					$actions[] = "<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . lang('Import') . "</a>";
				}
				$actions[] = "<a href='" . h(ME) . "dump=" . urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'" . bold(isset($_GET["dump"])) . ">" . lang('Export') . "</a>";
			}
			$in_db = $_GET["ns"] !== "" && !$missing && DB != "";
			if ($in_db) {
				$actions[] = '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . lang('Create table') . "</a>";
			}
			echo ($actions ? "<p class='links'>\n" . implode("\n", $actions) . "\n" : "");
			if ($in_db) {
				if ($tables) {
					$this->tablesPrint($tables);
				} else {
					echo "<p class='message'>" . lang('No tables.') . "</p>\n";
				}
			}
		}
	}

	/** Set up syntax highlight for code and <textarea>
	* @param TableStatus[] $tables result of table_status('', true)
	* @return void
	*/
	function syntaxHighlighting(array $tables) {
		global $connection;
		// this is matched by compile.php
		echo script_src("../externals/jush/modules/jush.js");
		echo script_src("../externals/jush/modules/jush-textarea.js");
		echo script_src("../externals/jush/modules/jush-txt.js");
		echo script_src("../externals/jush/modules/jush-js.js");
		if (support("sql")) {
			echo script_src("../externals/jush/modules/jush-" . JUSH . ".js");
			echo "<script" . nonce() . ">\n";
			if ($tables) {
				$links = array();
				foreach ($tables as $table => $type) {
					$links[] = preg_quote($table, '/');
				}
				echo "var jushLinks = { " . JUSH . ": [ '" . js_escape(ME) . (support("table") ? "table=" : "select=") . "\$&', /\\b(" . implode("|", $links) . ")\\b/g ] };\n";
				foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
					echo "jushLinks.$val = jushLinks." . JUSH . ";\n";
				}
			}
			echo "</script>\n";
		}
		echo script("syntaxHighlighting('" . (is_object($connection) ? preg_replace('~^(\d\.?\d).*~s', '\1', $connection->server_info) : "") . "'"
			. ($connection->flavor == 'maria' ? ", 'maria'" : ($connection->flavor == 'cockroach' ? ", 'cockroach'" : "")) . ");"
		);
	}

	/** Print databases list in menu
	* @return void
	*/
	function databasesPrint(string $missing) {
		global $adminer, $connection;
		$databases = $this->databases();
		if (DB && $databases && !in_array(DB, $databases)) {
			array_unshift($databases, DB);
		}
		echo "<form action=''>\n<p id='dbs'>\n";
		hidden_fields_get();
		$db_events = script("mixin(qsl('select'), {onmousedown: dbMouseDown, onchange: dbChange});");
		echo "<span title='" . lang('Database') . "'>" . lang('DB') . ":</span> " . ($databases
			? html_select("db", array("" => "") + $databases, DB) . $db_events
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off' size='19'>\n"
		);
		echo "<input type='submit' value='" . lang('Use') . "'" . ($databases ? " class='hidden'" : "") . ">\n";
		if (support("scheme")) {
			if ($missing != "db" && DB != "" && $connection->select_db(DB)) {
				echo "<br><span>" . lang('Schema') . ":</span> " . html_select("ns", array("" => "") + $adminer->schemas(), $_GET["ns"]) . $db_events;
				if ($_GET["ns"] != "") {
					set_schema($_GET["ns"]);
				}
			}
		}
		foreach (array("import", "sql", "schema", "dump", "privileges") as $val) {
			if (isset($_GET[$val])) {
				echo input_hidden($val);
				break;
			}
		}
		echo "</p></form>\n";
	}

	/** Print table list in menu
	* @param TableStatus[] $tables result of table_status('', true)
	* @return void
	*/
	function tablesPrint(array $tables) {
		echo "<ul id='tables'>" . script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$name = $this->tableName($status);
			if ($name != "") {
				echo '<li><a href="' . h(ME) . 'select=' . urlencode($table) . '"'
					. bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
					. " title='" . lang('Select data') . "'>" . lang('select') . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
						. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"])), (is_view($status) ? "view" : "structure"))
						. " title='" . lang('Show structure') . "'>$name</a>"
					: "<span>$name</span>"
				) . "\n";
			}
		}
		echo "</ul>\n";
	}
}
