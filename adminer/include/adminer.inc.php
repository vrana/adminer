<?php
namespace Adminer;

// any method change in this file should be transferred to editor/include/adminer.inc.php

/** Default Adminer plugin; it should call methods via adminer()->f() instead of $this->f() to give chance to other plugins */
class Adminer {
	/** @var Adminer|Plugins */ static $instance;
	/** @visibility protected(set) */ public string $error = ''; // HTML

	/** Name in title and navigation
	* @return string HTML code
	*/
	function name(): string {
		return "<a href='https://www.adminer.org/'" . target_blank() . " id='h1'><img src='../adminer/static/logo.png' width='24' height='24' alt='' id='logo'>Adminer</a>";
	}

	/** Connection parameters
	* @return array{string, string, string}
	*/
	function credentials(): array {
		return array(SERVER, $_GET["username"], get_password());
	}

	/** Get SSL connection options
	* @return string[]|void
	*/
	function connectSsl() {
	}

	/** Get key used for permanent login
	* @return string cryptic string which gets combined with password or '' in case of an error
	*/
	function permanentLogin(bool $create = false): string {
		return password_file($create);
	}

	/** Return key used to group brute force attacks; behind a reverse proxy, you want to return the last part of X-Forwarded-For */
	function bruteForceKey(): string {
		return $_SERVER["REMOTE_ADDR"];
	}

	/** Get server name displayed in breadcrumbs
	* @return string HTML code or null
	*/
	function serverName(?string $server): string {
		return h($server);
	}

	/** Identifier of selected database */
	function database(): ?string {
		// should be used everywhere instead of DB
		return DB;
	}

	/** Get list of databases
	* @return list<string>
	*/
	function databases(bool $flush = true): array {
		return get_databases($flush);
	}

	/** Print links after list of plugins */
	function pluginsLinks(): void {
	}

	/** Operators used in select
	* @return list<string> operators
	*/
	function operators(): array {
		return driver()->operators;
	}

	/** Get list of schemas
	* @return list<string>
	*/
	function schemas(): array {
		$return = schemas();
		if ($_GET["ns"] != "" && !in_array($_GET["ns"], $return)) {
			array_unshift($return, $_GET["ns"]);
		}
		return $return;
	}

	/** Specify limit for waiting on some slow queries like DB list
	* @return float number of seconds
	*/
	function queryTimeout(): float {
		return 2;
	}

	/** Called after connecting and selecting a database */
	function afterConnect(): void {
	}

	/** Headers to send before HTML output */
	function headers(): void {
	}

	/** Get Content Security Policy headers
	* @param list<string[]> $csp of arrays with directive name in key, allowed sources in value
	* @return list<string[]> same as $csp
	*/
	function csp(array $csp): array {
		return $csp;
	}

	/** Decide whether to check for a new Adminer version */
	function verifyVersion(): bool {
		return true;
	}

	/** Print HTML code inside <head>
	* @param bool $dark dark CSS: false to disable, true to force, null to base on user preferences
	* @return bool true to link favicon.ico
	*/
	function head(?bool $dark = null): bool {
		// this is matched by compile.php
		echo "<link rel='stylesheet' href='../externals/jush/jush.css'>\n";
		echo ($dark !== false ? "<link rel='stylesheet'" . ($dark ? "" : " media='(prefers-color-scheme: dark)'") . " href='../externals/jush/jush-dark.css'>\n" : "");
		return true;
	}

	/** Print extra classes in <body class>; must start with a space */
	function bodyClass(): void {
		echo " adminer";
	}

	/** Get URLs of the CSS files
	* @return string[] key is URL, value is either 'light' (supports only light color scheme), 'dark' or '' (both)
	*/
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

	/** Print login form */
	function loginForm(): void {
		echo "<table class='layout'>\n";
		// this is matched by compile.php
		echo adminer()->loginFormField('driver', '<tr><th>' . lang('System') . '<td>', html_select("auth[driver]", SqlDriver::$drivers, DRIVER, on('change', 'loginDriver')));
		echo adminer()->loginFormField(
			'server',
			'<tr><th>' . lang('Server') . '<td>',
			"<input name='auth[server]' value='" . h(SERVER) . "' title='" . lang('hostname[:port] or :socket') . "' placeholder='localhost' autocapitalize='off'>"
		);
		// this is matched by compile.php
		echo adminer()->loginFormField(
			'username',
			'<tr><th>' . lang('Username') . '<td>',
			'<input name="auth[username]" id="username" autofocus value="' . h($_GET["username"]) . '" autocomplete="username" autocapitalize="off">'
				. script("fire(qs('#username').form['auth[driver]'], 'change');")
		);
		echo adminer()->loginFormField('password', '<tr><th>' . lang('Password') . '<td>', '<input type="password" name="auth[password]" autocomplete="current-password">');
		echo adminer()->loginFormField('db', '<tr><th>' . lang('Database') . '<td>', '<input name="auth[db]" value="' . h($_GET["db"]) . '" autocapitalize="off">');
		echo "</table>\n";
		echo "<p><input type='submit' value='" . lang('Login') . "'>\n";
		echo checkbox("auth[permanent]", 1, $_COOKIE["adminer_permanent"], lang('Permanent login')) . "\n";
	}

	/** Get login form field
	* @param string $heading HTML
	* @param string $value HTML
	*/
	function loginFormField(string $name, string $heading, string $value): string {
		return $heading . $value . "\n";
	}

	/** Authorize the user
	* @return mixed true for success, string for error message, false for unknown error
	*/
	function login(string $login, string $password) {
		if ($password == "" || !password_required()) { // !password_required() - the server would accept any password
			return lang('Adminer does not support accessing a database without a password, <a href="https://www.adminer.org/en/password/"%s>more information</a>.', target_blank());
		}
		return true;
	}

	/** Table caption used in navigation and headings
	* @param TableStatus $tableStatus
	* @return string HTML code, "" to ignore table
	*/
	function tableName(array $tableStatus): string {
		return h($tableStatus["Name"]);
	}

	/** Field caption used in select and edit
	* @param Field|RoutineField $field
	* @param int $order order of column in select
	* @return string HTML code, "" to ignore field
	*/
	function fieldName(array $field, int $order = 0): string {
		$type = $field["full_type"] . ($field["null"] ? " NULL" : "");
		$comment = $field["comment"];
		return '<span title="' . h($type . ($comment != "" ? ($type ? ": " : "") . $comment : '')) . '">' . h($field["field"]) . '</span>';
	}

	/** Get comment of a table, column or routine
	* @param 'TABLE'|'COLUMN'|'PROCEDURE'|'FUNCTION' $type
	* @param ?string $comment null if the driver doesn't return comments
	* @return string HTML code
	*/
	function commentValue(string $type, ?string $comment): string {
		if ($comment == "" || $type == 'TABLE' || $type == 'COLUMN') {
			return h($comment);
		}
		// routine comments usually hold documentation, e.g. in the MySQL sys schema
		/** Format string as table row */
		$pre_tr = function (string $s): string {
			return preg_replace('~^~m', '<tr>', preg_replace('~\|~', '<td>', preg_replace('~\|$~m', "", rtrim($s))));
		};
		$table = '(\+--[-+]+\+\n)';
		$row = '(\| .* \|\n)';
		return "<pre>\n" . preg_replace_callback(
			"~^$table?$row$table?($row*)$table?~m",
			function ($match) use ($pre_tr) {
				$first_row = $pre_tr($match[2]);
				return "<table>\n" . ($match[1] ? "<thead>$first_row<tbody>\n" : $first_row) . $pre_tr($match[4]) . "\n</table>";
			},
			preg_replace(
				'~(\n(    -|mysql)&gt; )(.+)~',
				"\\1<code class='jush-sql'>\\3</code>",
				preg_replace('~(.+)\n---+\n~', "<b>\\1</b>\n", h($comment))
			)
		) . "</pre>\n";
	}

	/** Get input for editing a comment
	* @param 'TABLE'|'COLUMN' $type
	* @param string $attrs attributes to use inside the tag
	* @param ?string $comment null if the driver doesn't return comments
	* @return string HTML code
	*/
	function commentInput(string $type, string $attrs, ?string $comment): string {
		$value = h($comment);
		return (preg_match('~\n~', $value) // $value is never null unlike $comment
			? "<textarea$attrs rows='2' cols='" . ($type == 'TABLE' ? 20 : 30) . "' style='vertical-align: bottom;'>\n$value</textarea>" // \n to preserve the leading newline
			: "<input$attrs value='$value'>"
		);
	}

	/** Print links after select heading
	* @param TableStatus $tableStatus
	* @param ?string $set new item options, NULL for no new item
	*/
	function selectLinks(array $tableStatus, ?string $set = ""): void {
		$name = $tableStatus["Name"];
		echo '<p class="links">';
		$links = array("select" => lang('Select data'));
		if (support("table") || support("indexes")) {
			$links["table"] = lang('Show structure');
		}
		$is_view = false;
		if (support("table")) {
			$is_view = is_view($tableStatus);
			if ($is_view) {
				if (support("view")) {
					$links["view"] = lang('Alter view');
				}
			} elseif (function_exists('Adminer\alter_table') && $name != "") {
				$links["create"] = lang('Alter table');
			}
		}
		if ($set !== null) {
			$links["edit"] = lang('New item');
		}
		foreach ($links as $key => $val) {
			echo " <a href='" . h(ME) . "$key=" . url_escape($name) . ($key == "edit" ? $set : "") . "'" . bold(isset($_GET[$key])) . ">$val</a>";
		}
		echo doc_link(array(JUSH => driver()->tableHelp($name, $is_view)), "?");
		echo "\n";
	}

	/** Get foreign keys for table
	* @return ForeignKey[] same format as foreign_keys()
	*/
	function foreignKeys(string $table): array {
		return foreign_keys($table);
	}

	/** Find backward keys for table
	* @return BackwardKey[]
	*/
	function backwardKeys(string $table, string $tableName): array {
		return array();
	}

	/** Print backward keys for row
	* @param BackwardKey[] $backwardKeys
	* @param string[] $row
	*/
	function backwardKeysPrint(array $backwardKeys, array $row): void {
	}

	/** Query printed in select before execution
	* @param string $query query to be executed
	* @param float $start start time of the query
	*/
	function selectQuery(string $query, float $start, bool $failed = false): string {
		$return = "\n";
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings";
			$return = ", <a href='#$id' class='toggle'>" . lang('Warnings') . "</a>"
				. "$return<div id='$id' class='hidden'>\n$warnings</div>\n"
			;
		}
		return "<p><code class='jush-" . JUSH . "'>" . h(str_replace("\n", " ", $query)) . "</code> <span class='time'>(" . format_time($start) . ")</span>"
			. (support("sql") ? " <a href='" . h(ME) . "sql=" . url_escape($query) . "' class='hover'>" . lang('Edit') . "</a>" : "")
			. $return
		;
	}

	/** Query printed in SQL command before execution
	* @param string $query query to be executed
	* @return string escaped query to be printed
	*/
	function sqlCommandQuery(string $query): string {
		return shorten_utf8(trim($query), 1000);
	}

	/** Print HTML code just before the Execute button in SQL command */
	function sqlPrintAfter(): void {
	}

	/** Description of a row in a table
	* @return string SQL expression, empty string for no description
	*/
	function rowDescription(string $table): string {
		return "";
	}

	/** Get descriptions of selected data
	* @param list<string[]> $rows all data to print
	* @param list<ForeignKey>[] $foreignKeys
	* @return list<string[]>
	*/
	function rowDescriptions(array $rows, array $foreignKeys): array {
		return $rows;
	}

	/** Get a link to use in select table
	* @param string $val raw value of the field
	* @param array{type: string} $field
	* @return string|void null to create the default link
	*/
	function selectLink(?string $val, array $field) {
	}

	/** Value printed in select table
	* @param ?string $val HTML-escaped value to print
	* @param ?string $link link to foreign key
	* @param array{type: string, full_type?: string} $field
	* @param string $original original value before applying editVal() and escaping
	*/
	function selectVal(?string $val, ?string $link, array $field, ?string $original): string {
		$return = ($val === null ? "<i>NULL</i>"
			: (preg_match("~char|binary|boolean~", $field["type"]) && !preg_match("~var~", $field["type"]) ? "<code>$val</code>"
			: (preg_match('~^jsonb?$~', $field["full_type"]) ? "<code class='jush-json'>$val</code>"
			: $val)
		));
		if (is_blob($field) && !is_utf8($val)) {
			$return = "<i>" . lang('%d byte(s)', strlen($original)) . "</i>";
		}
		return ($link ? "<a href='" . h($link) . "'" . (is_url($link) ? target_blank() : "") . ">$return</a>" : $return);
	}

	/** Value conversion used in select and edit
	* @param array{type: string} $field
	*/
	function editVal(?string $val, array $field): ?string {
		return $val;
	}

	/** Get configuration options for AdminerConfig
	* @return string[] key is config description, value is HTML
	*/
	function config(): array {
		return array();
	}

	/** Print table structure in tabular format
	* @param Field[] $fields
	* @param TableStatus $tableStatus
	*/
	function tableStructurePrint(array $fields, ?array $tableStatus = null): void {
		echo "<div class='scrollable'>\n";
		echo "<table class='nowrap odds'>\n";
		echo "<thead><tr><th>" . lang('Column') . "<td>" . lang('Type') . (support("comment") ? "<td>" . lang('Comment') : "") . "<tbody>\n";
		$structured_types = driver()->structuredTypes();
		foreach ($fields as $field) {
			echo "<tr><th>" . h($field["field"]);
			$type = h($field["full_type"]);
			$collation = h($field["collation"]);
			echo "<td><span title='$collation'>"
				. (in_array($type, (array) $structured_types[lang('User types')])
					? "<a href='" . h(ME . 'type=' . url_escape($type)) . "'>$type</a>"
					: $type . ($collation && isset($tableStatus["Collation"]) && $collation != $tableStatus["Collation"] ? " $collation" : ""))
				. "</span>"
			;
			echo ($field["null"] ? " <i>NULL</i>" : "");
			echo ($field["auto_increment"] ? " <i>" . lang('Auto Increment') . "</i>" : "");
			echo (isset($field["default"])
				? " <span title='" . lang('Default value') . "'>[<b>" . ($field["generated"]
					? "<code class='jush-" . JUSH . "'>" . shorten_utf8(preg_replace('~\s+~', ' ', ltrim($field["default"])), 80, "</code>")
					: h($field["default"])
				) . "</b>]</span>"
				: ""
			);
			echo (support("comment") ? "<td>" . adminer()->commentValue('COLUMN', $field["comment"]) : "");
			echo "\n";
		}
		echo "</table>\n";
		echo "</div>\n";
	}

	/** Print list of indexes on table in tabular format
	* @param Index[] $indexes
	* @param TableStatus $tableStatus
	*/
	function tableIndexesPrint(array $indexes, array $tableStatus): void {
		$partial = false;
		foreach ($indexes as $name => $index) {
			$partial |= !!$index["partial"];
		}
		echo "<table>\n";
		$default_algorithm = first(driver()->indexAlgorithms($tableStatus));
		foreach ($indexes as $name => $index) {
			ksort($index["columns"]); // enforce correct columns order
			$print = array();
			foreach ($index["columns"] as $key => $val) {
				$print[] = "<i>" . h($val) . "</i>"
					. ($index["lengths"][$key] ? "(" . h($index["lengths"][$key]) . ")" : "")
					. ($index["descs"][$key] ? " DESC" : "")
				;
			}

			echo "<tr title='" . h($name) . "'>";
			echo "<th>" . h($index["type"]) . ($default_algorithm && $index['algorithm'] != $default_algorithm ? " (" . h($index['algorithm']) . ")" : "");
			echo "<td>" . implode(", ", $print);
			if ($partial) {
				echo "<td>" . ($index['partial'] ? "<code class='jush-" . JUSH . "'>WHERE " . h($index['partial']) : "");
			}
			echo "\n";
		}
		echo "</table>\n";
	}

	/** Print columns box in select
	* @param list<string> $select result of selectColumnsProcess()[0]
	* @param string[] $columns selectable columns
	*/
	function selectColumnsPrint(array $select, array $columns): void {
		print_fieldset("select", lang('Select'), $select);
		$i = 0;
		$select[""] = array();
		foreach ($select as $key => $val) {
			$val = idx($_GET["columns"], $key, array());
			$column = select_input(
				" name='columns[$i][col]' data-default=''" . on('change', ($key !== "" ? 'selectFieldChange' : 'selectAddRow')),
				$columns,
				$val["col"]
			);
			echo "<div>" . (driver()->functions || driver()->grouping
				? html_select(
					"columns[$i][fun]",
					array(-1 => "") + array_filter(array(lang('Functions') => driver()->functions, lang('Aggregation') => driver()->grouping)),
					$val["fun"],
					" data-default=''" . on('change', ($key !== "" ? 'helpClose' : 'selectFunAddRow'))
						. on_help_value(' (.*)|$', '($1)')
				) . "($column)"
				: $column
			) . "</div>\n";
			$i++;
		}
		echo "</div></fieldset>\n";
	}

	/** Print search box in select
	* @param list<string> $where result of selectSearchProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectSearchPrint(array $where, array $columns, array $indexes): void {
		print_fieldset("search", lang('Search'), $where);
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT") {
				echo "<div>(<i>" . implode("</i>, <i>", array_map('Adminer\h', $index["columns"])) . "</i>) AGAINST";
				echo " <input type='search' name='fulltext[$i]' value='" . h(idx($_GET["fulltext"], $i)) . "' data-default=''" . on('input', 'selectFieldChange') . ">";
				echo (JUSH == 'sql' ? checkbox("boolean[$i]", 1, isset($_GET["boolean"][$i]), "BOOL") : '');
				echo "</div>\n";
			}
		}
		$operators = adminer()->operators();
		foreach (array_merge((array) $_GET["where"], array(array())) as $i => $val) {
			if (!$val || ("$val[col]$val[val]" != "" && in_array($val["op"], $operators))) {
				echo "<div>" . select_input(
					" name='where[$i][col]' data-default=''" . on('change', ($val ? 'selectFieldChange' : 'selectAddRow')),
					$columns,
					$val["col"],
					"(" . lang('anywhere') . ")"
				);
				// the first operator is selected by the browser if none is
				echo html_select("where[$i][op]", $operators, $val["op"], " data-default='" . h(first($operators)) . "'" . on('change', 'selectFirstChange'));
				echo "<input type='search' name='where[$i][val]' value='" . h($val["val"]) . "' data-default=''"
					. on('input', 'selectFirstChange') . on('keydown', 'selectSearchKeydown') . on('search', 'selectSearchSearch') . ">";
				echo "</div>\n";
			}
		}
		echo "</div></fieldset>\n";
	}

	/** Print order box in select
	* @param list<string> $order result of selectOrderProcess()
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	*/
	function selectOrderPrint(array $order, array $columns, array $indexes): void {
		print_fieldset("sort", lang('Sort'), $order);
		$i = 0;
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				echo "<div>" . select_input(" name='order[$i]' data-default=''" . on('change', 'selectFieldChange'), $columns, $val);
				echo checkbox("desc[$i]", 1, isset($_GET["desc"][$key]), lang('descending')) . "</div>\n";
				$i++;
			}
		}
		echo "<div>" . select_input(" name='order[$i]' data-default=''" . on('change', 'selectAddRow'), $columns);
		echo checkbox("desc[$i]", 1, false, lang('descending')) . "</div>\n";
		echo "</div></fieldset>\n";
	}

	/** Print limit box in select */
	function selectLimitPrint(int $limit): void {
		echo "<fieldset><legend>" . lang('Limit') . "</legend><div>"; // <div> for easy styling
		// data-default - the same value as in selectLimitProcess()
		// 0 is printed as an empty value, both mean no limit
		echo "<input type='number' name='limit' class='size' value='" . h($limit ?: "") . "' data-default='50'" . on('input', 'selectFieldChange') . ">";
		echo "</div></fieldset>\n";
	}

	/** Print text length box in select
	* @param numeric-string $text_length result of selectLengthProcess()
	*/
	function selectLengthPrint(string $text_length): void {
		echo "<fieldset><legend>" . lang('Text length') . "</legend><div>";
		// data-default - the same value as in selectLengthProcess()
		echo "<input type='number' name='text_length' class='size' value='" . h($text_length) . "' data-default='100'>";
		echo "</div></fieldset>\n";
	}

	/** Print action box in select
	* @param Index[] $indexes
	*/
	function selectActionPrint(array $indexes): void {
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
	function selectCommandPrint(): bool {
		return !information_schema(DB);
	}

	/** Print import box in select
	* @return bool whether to print default import
	*/
	function selectImportPrint(): bool {
		return !information_schema(DB);
	}

	/** Print extra text in the end of a select form
	* @param string[] $emailFields fields holding e-mails
	* @param string[] $columns selectable columns
	*/
	function selectEmailPrint(array $emailFields, array $columns): void {
	}

	/** Process columns box in select
	* @param string[] $columns selectable columns
	* @param Index[] $indexes
	* @return list<list<string>> [[select_expressions], [group_expressions]]
	*/
	function selectColumnsProcess(array $columns, array $indexes): array {
		$select = array(); // select expressions, empty for *
		$group = array(); // expressions without aggregation - will be used for GROUP BY if an aggregation function is used
		foreach ((array) $_GET["columns"] as $key => $val) {
			if ($val["fun"] == "count" || ($val["col"] != "" && (!$val["fun"] || in_array($val["fun"], driver()->functions) || in_array($val["fun"], driver()->grouping)))) {
				$select[$key] = apply_sql_function($val["fun"], ($val["col"] != "" ? idf_escape($val["col"]) : "*"));
				if (!in_array($val["fun"], driver()->grouping)) {
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
	function selectSearchProcess(array $fields, array $indexes): array {
		$return = array();
		foreach ($indexes as $i => $index) {
			if ($index["type"] == "FULLTEXT" && idx($_GET["fulltext"], $i) != "") {
				$return[] = "MATCH (" . implode(", ", array_map('Adminer\idf_escape', $index["columns"])) . ") AGAINST ("
					. q($_GET["fulltext"][$i]) . (isset($_GET["boolean"][$i]) ? " IN BOOLEAN MODE" : "") . ")";
			}
		}
		$operators = adminer()->operators();
		foreach ((array) $_GET["where"] as $key => $val) {
			// the form doesn't send the fields holding the default value, the first operator is preselected by the browser
			$val += array("col" => "", "op" => first($operators), "val" => "");
			$_GET["where"][$key] = $val; // used also by selectSearchPrint() and by the COUNT(*) links
			$col = $val["col"];
			if ("$col$val[val]" != "" && in_array($val["op"], $operators)) {
				if ($val["op"] == "SQL" && (!$_POST || !verify_token())) {
					SqlDb::$untrusted = true; // the condition can be sent by GET which is not protected by the CSRF token
				}
				$conds = array();
				foreach (($col != "" ? array($col => $fields[$col]) : $fields) as $name => $field) {
					$prefix = "";
					$cond = " $val[op]";
					if (preg_match('~IN$~', $val["op"])) {
						$in = process_length($val["val"]);
						$cond .= " " . ($in != "" ? $in : "(NULL)");
					} elseif ($val["op"] == "SQL") {
						$cond = " $val[val]"; // SQL injection
					} elseif (preg_match('~^(I?LIKE) %%$~', $val["op"], $match)) {
						$cond = " $match[1] " . adminer()->processInput($field, "%$val[val]%");
					} elseif ($val["op"] == "FIND_IN_SET") {
						$prefix = "$val[op](" . q($val["val"]) . ", ";
						$cond = ")";
					} elseif (!preg_match('~NULL$~', $val["op"])) {
						$cond .= " " . adminer()->processInput($field, $val["val"]);
					}
					if (
						$col != "" || ( // find anywhere
							isset($field["privileges"]["where"])
							&& (preg_match('~^[-\d.' . (preg_match('~IN$~', $val["op"]) ? ',' : '') . ']+$~', $val["val"]) || !preg_match('~' . number_type() . '|bit~', $field["type"]))
							&& (!preg_match("~[\x80-\xFF]~", $val["val"]) || preg_match('~char|text|enum|set~', $field["type"]))
							&& (!preg_match('~date|timestamp~', $field["type"]) || preg_match('~^\d+-\d+-\d+~', $val["val"]))
						)
					) {
						$conds[] = $prefix . driver()->convertSearch(idf_escape($name), $val, $field) . $cond;
					}
				}
				$return[] =
					(count($conds) == 1 ? $conds[0] :
					($conds ? "(" . implode(" OR ", $conds) . ")" :
					"1 = 0"
				));
			}
		}
		return $return;
	}

	/** Process order box in select
	* @param Field[] $fields
	* @param Index[] $indexes
	* @return list<string> expressions to join by comma
	*/
	function selectOrderProcess(array $fields, array $indexes): array {
		$return = array();
		foreach ((array) $_GET["order"] as $key => $val) {
			if ($val != "") {
				$return[] = (preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~', $val) ? $val : idf_escape($val)) //! MS SQL uses []
					. (isset($_GET["desc"][$key]) ? " DESC" . (JUSH == 'pgsql' && idx($fields[$val], "null") ? " NULLS LAST" : "") : "")
				;
			}
		}
		return $return;
	}

	/** Process limit box in select */
	function selectLimitProcess(): int {
		return (isset($_GET["limit"]) ? intval($_GET["limit"]) : 50);
	}

	/** Process length box in select
	* @return numeric-string number of characters to shorten texts, will be escaped, empty string means unlimited
	*/
	function selectLengthProcess(): string {
		return (isset($_GET["text_length"]) ? "$_GET[text_length]" : "100");
	}

	/** Process extras in select form
	* @param string[] $where AND conditions
	* @param list<ForeignKey>[] $foreignKeys
	* @return bool true if processed, false to process other parts of form
	*/
	function selectEmailProcess(array $where, array $foreignKeys): bool {
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
	function selectQueryBuild(array $select, array $where, array $group, array $order, int $limit, ?int $page): string {
		return "";
	}

	/** Query printed after execution in the message
	* @param string $query executed query
	* @param string $time elapsed time
	*/
	function messageQuery(string $query, string $time, bool $failed = false): string {
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
		$return = "<a href='#$sql_id' class='toggle'>" . lang('SQL command') . "</a> " . copy_icon() . "\n";
		if (!$failed && ($warnings = driver()->warnings())) {
			$id = "warnings-" . count($history[$_GET["db"]]);
			$return = "<a href='#$id' class='toggle'>" . lang('Warnings') . "</a>, $return<div id='$id' class='hidden'>\n$warnings</div>\n";
		}
		return " <span class='time'>" . @date("H:i:s") . "</span>" // @ - time zone may be not set
			. " $return<div id='$sql_id' class='hidden'><pre><code class='jush-" . JUSH . "'>" . shorten_utf8($query, 1e4) . "</code></pre>"
			. ($time ? " <span class='time'>($time)</span>" : '')
			. (support("sql")
				? '<p><a href="' . h(str_replace("db=" . url_escape(DB), "db=" . url_escape($_GET["db"]), ME) . 'sql=&history=' . (count($history[$_GET["db"]]) - 1)) . '">' . lang('Edit') . '</a>'
				: '')
			. '</div>'
		;
	}

	/** Print before edit form
	* @param Field[] $fields
	* @param mixed $row
	*/
	function editRowPrint(string $table, array $fields, $row, ?bool $update): void {
	}

	/** Functions displayed in edit form
	* @param Field|array{null:bool} $field
	* @return string[]
	*/
	function editFunctions(array $field): array {
		$return = ($field["null"] ? "NULL/" : "");
		$update = isset($_GET["select"]) || where($_GET);
		foreach (array(driver()->insertFunctions, driver()->editFunctions) as $key => $functions) {
			if (!$key || (!isset($_GET["call"]) && $update)) { // relative functions
				foreach ($functions as $pattern => $val) {
					if (!$pattern || preg_match("~$pattern~", $field["type"])) {
						$return .= "/$val";
					}
				}
			}
			if ($key && $functions && !preg_match('~set|bool~', $field["type"]) && !is_blob($field)) {
				$return .= "/SQL";
			}
		}
		if ($field["auto_increment"] && !$update) {
			$return = lang('Auto Increment');
		}
		return explode("/", $return);
	}

	/** Get options to display edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	* @param string $attrs attributes to use inside the tag
	* @param string|string[]|false|null $value false means original value
	* @return string custom input field or empty string for default
	*/
	function editInput(?string $table, array $field, string $attrs, $value): string {
		if ($field["type"] == "enum") {
			return (isset($_GET["select"]) ? "<label><input type='radio'$attrs value='orig' checked><i>" . lang('original') . "</i></label> " : "")
				. enum_input("radio", $attrs, $field, $value, "NULL")
			;
		}
		return "";
	}

	/** Get hint for edit field
	* @param ?string $table null in call.inc.php
	* @param Field $field
	*/
	function editHint(?string $table, array $field, ?string $value): string {
		return "";
	}

	/** Process sent input
	* @param Field $field
	* @return string expression to use in a query
	*/
	function processInput(array $field, string $value, ?string $function = ""): string {
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
			$return = idf_escape($name) . " $function " . (preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i", $value) && JUSH != "pgsql" ? $value : $return);
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
	function dumpOutput(): array {
		$return = array('text' => lang('open'), 'file' => lang('save'));
		if (function_exists('gzencode')) {
			$return['gz'] = 'gzip';
		}
		return $return;
	}

	/** Return export format options
	* @return string[] empty to disable export
	*/
	function dumpFormat(): array {
		return (support("dump") ? array('sql' => 'SQL') : array()) + array('csv' => 'CSV,', 'csv;' => 'CSV;', 'tsv' => 'TSV');
	}

	/** Export database structure
	* @return void prints data
	*/
	function dumpDatabase(string $db): void {
	}

	/** Export table structure
	* @param int $is_view 0 table, 1 view, 2 temporary view table
	* @return void prints data
	*/
	function dumpTable(string $table, string $style, int $is_view = 0): void {
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
	* @param string $query empty to select the rows by Driver::select() from the parts below
	* @param list<string> $select
	* @param list<string> $where
	* @param list<string> $group
	* @param list<string> $order
	* @return void prints data
	*/
	function dumpData(string $table, string $style, string $query, array $select = array(), array $where = array(), array $group = array(), array $order = array()): void {
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
			// the query is used by the SQL command page, which exports a result belonging to no table, and by the UNION in select
			$result = ($query != ""
				? connection()->query($query, 1) // 1 - MYSQLI_USE_RESULT
				: driver()->select($table, ($select ?: array("*")), $where, $group, $order, 0) // 0 - all rows
			);
			if ($result) {
				$insert = "";
				$buffer = "";
				$keys = array();
				$generated = array();
				$suffix = "";
				$fetch_function = ($table != '' ? 'fetch_assoc' : 'fetch_row');
				$count = 0;
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
							$row[$key] = ($val === null ? "NULL"
								: ($val === false ? 0
								: unconvert_field($field, preg_match(number_type(), $field["type"]) && !preg_match('~\[~', $field["full_type"]) && is_numeric($val)
									? $val
									: (!is_blob($field) || is_utf8($val) ? q($val) : driver()->quoteBinary($val)))
							));
						}
						$s = ($max_packet ? "\n" : " ") . "(" . implode(",\t", $row) . ")";
						if (!$buffer) {
							$buffer = $insert . $s;
						} elseif (
							JUSH == 'mssql'
							? $count % 1000 != 0 // https://learn.microsoft.com/en-us/sql/t-sql/queries/table-value-constructor-transact-sql#limitations-and-restrictions
							: strlen($buffer) + 4 + strlen($s) + strlen($suffix) < $max_packet // 4 - length specification
						) {
							$buffer .= ",$s";
						} else {
							echo $buffer . $suffix;
							$buffer = $insert . $s;
						}
					}
					$count++;
				}
				if ($buffer) {
					echo $buffer . $suffix;
				}
			} elseif ($_POST["format"] == "sql") {
				echo "-- " . str_replace("\n", " ", connection()->error) . "\n";
			}
			if ($identity_insert) {
				echo "SET IDENTITY_INSERT " . table($table) . " OFF;\n";
			}
		}
	}

	/** Set export filename
	* @return string filename without extension
	*/
	function dumpFilename(string $identifier): string {
		return friendly_url($identifier != "" ? $identifier : (SERVER ?: "localhost"));
	}

	/** Send headers for export
	* @return string extension
	*/
	function dumpHeaders(string $identifier, bool $multi_table = false): string {
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
	function dumpFooter(): void {
		if ($_POST["format"] == "sql") {
			echo "-- " . gmdate("Y-m-d H:i:s e") . "\n";
		}
	}

	/** Set the path of the file for webserver load
	* @return string path of the sql dump file
	*/
	function importServerPath(): string {
		return "adminer.sql";
	}

	/** Print fieldsets with other import formats
	* @return void prints data
	*/
	function importPrint(): void {
	}

	/** Import data sent to the import form and print the result
	* @return bool whether the data was imported, false to import them as SQL commands
	*/
	function importProcess(): bool {
		return false;
	}

	/** Print homepage
	* @return bool whether to print default homepage
	*/
	function homepage(): bool {
		echo '<p class="links">' . ($_GET["ns"] == "" && support("database") ? '<a href="' . h(ME) . 'database=">' . lang('Alter database') . "</a>\n" : "");
		echo (support("scheme") ? "<a href='" . h(ME) . "scheme='>" . ($_GET["ns"] != "" ? lang('Alter schema') : lang('Create schema')) . "</a>\n" : "");
		echo ($_GET["ns"] !== "" ? '<a href="' . h(ME) . 'schema=">' . lang('Database schema') . "</a>\n" : "");
		echo (support("privileges") ? "<a href='" . h(ME) . "privileges='>" . lang('Privileges') . "</a>\n" : "");
		if ($_GET["ns"] !== "") {
			echo (support("routine") ? "<a href='#routines'>" . lang('Routines') . "</a>\n" : "");
			echo (support("sequence") ? "<a href='#sequences'>" . lang('Sequences') . "</a>\n" : "");
			echo (support("type") ? "<a href='#user-types'>" . lang('User types') . "</a>\n" : "");
			echo (support("event") ? "<a href='#events'>" . lang('Events') . "</a>\n" : "");
		}
		return true;
	}

	/** Print navigation after Adminer title
	* @param string $missing can be "auth" if there is no database connection, "db" if there is no database selected, "ns" with invalid schema
	*/
	function navigation(string $missing): void {
		echo "<h1>" . adminer()->name() . " <span class='version'>" . VERSION;
		$new_version = $_COOKIE["adminer_version"];
		echo " <a href='https://www.adminer.org/#download'" . target_blank() . " id='version'>" . (version_compare(VERSION, $new_version) < 0 ? h($new_version) : "") . version_iframe() . "</a>";
		echo "</span></h1>\n";
		// this is matched by compile.php
		switch_lang();
		if ($missing == "auth") {
			$output = "";
			foreach ((array) $_SESSION["pwds"] as $vendor => $servers) {
				foreach ($servers as $server => $usernames) {
					$name = h(get_setting("vendor-$vendor-$server") ?: get_driver($vendor));
					foreach ($usernames as $username => $password) {
						if ($name && $password !== null) {
							$dbs = $_SESSION["db"][$vendor][$server][$username];
							foreach (($dbs ? array_keys($dbs) : array("")) as $db) {
								$output .= "<li><a href='" . h(auth_url($vendor, $server, $username, $db)) . "'>($name) "
									. h("$username@")
									. ($server != "" ? adminer()->serverName($server) : "")
									. h($db != "" ? " - $db" : "")
									. "</a>\n"
								;
							}
						}
					}
				}
			}
			if ($output) {
				echo "<ul id='logins'" . on('mouseover', 'menuOver') . on('mouseout', 'menuOut') . ">\n$output</ul>\n";
			}
		} else {
			$tables = array();
			if ($_GET["ns"] !== "" && !$missing && DB != "") {
				connection()->select_db(DB);
				$tables = table_status('', true);
			}
			adminer()->syntaxHighlighting($tables);
			adminer()->databasesPrint($missing);
			$actions = array();
			if (DB == "" || !$missing) {
				if (support("sql")) {
					$actions['sql'] = "<a href='" . h(ME) . "sql='" . bold(isset($_GET["sql"]) && !isset($_GET["import"])) . ">" . lang('SQL command') . "</a>";
					$actions['import'] = "<a href='" . h(ME) . "import='" . bold(isset($_GET["import"])) . ">" . lang('Import') . "</a>";
				}
				$actions['dump'] = "<a href='" . h(ME) . "dump=" . url_escape(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]) . "' id='dump'"
					. bold(isset($_GET["dump"])) . ">" . lang('Export') . "</a>";
			}
			$in_db = $_GET["ns"] !== "" && !$missing && DB != "";
			if ($in_db && function_exists('Adminer\alter_table')) {
				$actions['create'] = '<a href="' . h(ME) . 'create="' . bold($_GET["create"] === "") . ">" . lang('Create table') . "</a>";
			}
			$actions = adminer()->menuActions($actions, $missing);
			echo ($actions ? "<p class='links'>\n" . implode("\n", $actions) . "\n" : "");
			if ($in_db) {
				if ($tables) {
					adminer()->tablesPrint($tables);
				} else {
					echo "<p class='message'>" . lang('No tables.') . "</p>\n";
				}
			}
		}
	}

	/** Set up syntax highlight for code and <textarea>
	* @param TableStatus[] $tables
	*/
	function syntaxHighlighting(array $tables): void {
		// this is matched by compile.php
		echo script_src("../externals/jush/modules/jush.js", true);
		echo script_src("../externals/jush/modules/jush-autocomplete-sql.js", true);
		echo script_src("../externals/jush/modules/jush-textarea.js", true);
		echo script_src("../externals/jush/modules/jush-txt.js", true);
		echo script_src("../externals/jush/modules/jush-json.js", true);
		if (support("sql")) {
			echo script_src("../externals/jush/modules/jush-" . JUSH . ".js", true);
			echo "<script" . nonce() . ">\n";
			if ($tables) {
				$links = array();
				foreach ($tables as $table => $type) {
					$links[] = js_escape_re($table);
				}
				echo "var jushLinks = { " . JUSH . ":";
				json_row(js_escape(ME) . (support("table") ? "table" : "select") . '=$&', '/\b(?<!\$)(' . implode('|', $links) . ')(?!\$)\b/g', false); // $ is used in PostgreSQL as part of name
				// routines() is slow so it is called only on the pages printing SQL where a routine name can appear
				$sql_pages = array("sql", "check", "event", "procedure", "trigger", "view", "type", "table", "processlist"); // ?function= sets ?procedure=
				if (support('routine') && array_intersect_key($_GET, array_flip($sql_pages))) {
					foreach (routines() as $row) {
						json_row(js_escape(ME) . 'function=' . url_escape($row["SPECIFIC_NAME"]) . '&name=$&', '/\b' . js_escape_re($row["ROUTINE_NAME"]) . '(?=["`\]]?\()/g', false);
					}
				}
				json_row('');
				echo "};\n";
				foreach (array("bac", "bra", "sqlite_quo", "mssql_bra") as $val) {
					echo "jushLinks.$val = jushLinks." . JUSH . ";\n";
				}
				if (isset($_GET["sql"]) || isset($_GET["trigger"]) || isset($_GET["check"])) {
					$tablesColumns = array_fill_keys(array_keys($tables), array());
					foreach (driver()->allFields() as $table => $fields) {
						foreach ($fields as $field) {
							$tablesColumns[$table][] = $field["field"];
						}
					}
					echo "addEventListener('DOMContentLoaded', () => { autocompleter = jush.autocompleteSql('" . idf_escape("") . "', " . json_encode($tablesColumns) . "); });\n";
				}
			}
			echo "</script>\n";
		}
		echo script("syntaxHighlighting('" . (preg_match('~^\d\.?\d~', connection()->server_info, $match) ? $match[0] : "") . "', '" . connection()->flavor . "');");
	}

	/** Print databases list in menu */
	function databasesPrint(string $missing): void {
		$databases = adminer()->databases();
		if (DB && $databases && !in_array(DB, $databases)) {
			array_unshift($databases, DB);
		}
		echo "<form action=''>\n<p id='dbs'>\n";
		hidden_fields_get();
		$db_events = on('mousedown', 'dbMouseDown') . on('change', 'dbChange');
		echo "<label title='" . lang('Database') . "'>" . lang('DB') . ": " . ($databases
			? html_select("db", array("" => "") + $databases, DB, $db_events)
			: "<input name='db' value='" . h(DB) . "' autocapitalize='off' size='19'>\n"
		) . "</label>";
		echo "<input type='submit' value='" . lang('Use') . "'" . ($databases ? " class='hidden'" : "") . ">\n";
		if (support("scheme")) {
			if ($missing != "db" && DB != "" && connection()->select_db(DB)) {
				echo "<br><label>" . lang('Schema') . ": " . html_select("ns", array("" => "") + adminer()->schemas(), $_GET["ns"], $db_events) . "</label>";
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
	* @param string[] $actions
	* @return string[]
	*/
	function menuActions(array $actions, string $missing): array {
		return $actions;
	}

	/** Print table list in menu
	* @param TableStatus[] $tables
	*/
	function tablesPrint(array $tables): void {
		echo "<ul id='tables'" . on('mouseover', 'menuOver') . on('mouseout', 'menuOut') . ">";
		foreach ($tables as $table => $status) {
			$table = "$table"; // do not highlight "0" as active everywhere
			$name = adminer()->tableName($status);
			if ($name != "" && !$status["partition"]) {
				echo '<li><a href="' . h(ME) . 'select=' . url_escape($table) . '"'
					. bold($_GET["select"] == $table || $_GET["edit"] == $table, "select hover")
					. " title='" . lang('Select data') . "'>" . lang('select') . "</a> "
				;
				echo (support("table") || support("indexes")
					? '<a href="' . h(ME) . 'table=' . url_escape($table) . '"'
						. bold(
							in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"], $_GET["check"], $_GET["view"])),
							(is_view($status) ? "view" : "structure")
						)
						. " title='" . lang('Show structure') . "'>$name</a>"
					: "<span>$name</span>"
				) . "\n";
			}
		}
		echo "</ul>\n";
	}

	/** Get server variables
	* @return list<string[]> [[$name, $value]]
	*/
	function showVariables(): array {
		return show_variables();
	}

	/** Get status variables
	* @return list<string[]> [[$name, $value]]
	*/
	function showStatus(): array {
		return show_status();
	}

	/** Get process list
	* @return list<string[]> [$row]
	*/
	function processList(): array {
		return process_list();
	}

	/** Kill a process
	* @param numeric-string $id
	* @return Result|bool
	*/
	function killProcess(string $id) {
		return kill_process($id);
	}
}
