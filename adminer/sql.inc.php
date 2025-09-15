<?php
namespace Adminer;

if (!$error && $_POST["export"]) {
	save_settings(array("output" => $_POST["output"], "format" => $_POST["format"]), "adminer_import");
	dump_headers("sql");
	if ($_POST["format"] == "sql") {
		echo "$_POST[query]\n";
	} else {
		adminer()->dumpTable("", "");
		adminer()->dumpData("", "table", $_POST["query"]);
		adminer()->dumpFooter();
	}
	exit;
}

restart_session();
$history_all = &get_session("queries");
$history = &$history_all[DB];
if (!$error && $_POST["clear"]) {
	$history = array();
	redirect(remove_from_uri("history"));
}
stop_session();

page_header((isset($_GET["import"]) ? lang('Import') : lang('SQL command')), $error);
$line_comment = '--' . (JUSH == 'sql' ? ' ' : '');

if (!$error && $_POST) {
	$fp = false;
	if (!isset($_GET["import"])) {
		$query = $_POST["query"];
	} elseif ($_POST["webfile"]) {
		$sql_file_path = adminer()->importServerPath();
		$fp = @fopen((file_exists($sql_file_path)
			? $sql_file_path
			: "compress.zlib://$sql_file_path.gz"
		), "rb");
		$query = ($fp ? fread($fp, 1e6) : false);
	} else {
		$query = get_file("sql_file", true, ";");
	}

	if (is_string($query)) { // get_file() returns error as number, fread() as false
		if (function_exists('memory_get_usage') && ($memory_limit = ini_bytes("memory_limit")) != "-1") {
			@ini_set("memory_limit", max($memory_limit, strval(2 * strlen($query) + memory_get_usage() + 8e6))); // @ - may be disabled, 2 - substr and trim, 8e6 - other variables
		}

		if ($query != "" && strlen($query) < 1e6) { // don't add big queries
			$q = $query . (preg_match("~;[ \t\r\n]*\$~", $query) ? "" : ";"); //! doesn't work with DELIMITER |
			if (!$history || first(end($history)) != $q) { // no repeated queries
				restart_session();
				$history[] = array($q, time()); //! add elapsed time
				set_session("queries", $history_all); // required because reference is unlinked by stop_session()
				stop_session();
			}
		}

		$space = "(?:\\s|/\\*[\s\S]*?\\*/|(?:#|$line_comment)[^\n]*\n?|--\r?\n)";
		$delimiter = ";";
		$offset = 0;
		$empty = true;
		$connection2 = connect(); // connection for exploring indexes and EXPLAIN (to not replace FOUND_ROWS()) //! PDO - silent error
		if ($connection2 && DB != "") {
			$connection2->select_db(DB);
			if ($_GET["ns"] != "") {
				set_schema($_GET["ns"], $connection2);
			}
		}
		$commands = 0;
		$errors = array();
		$parse = '[\'"' . (JUSH == "sql" ? '`#' : (JUSH == "sqlite" ? '`[' : (JUSH == "mssql" ? '[' : ''))) . ']|/\*|' . $line_comment . '|$' . (JUSH == "pgsql" ? '|\$([a-zA-Z]\w*)?\$' : '');
		$total_start = microtime(true);
		$adminer_export = get_settings("adminer_import"); // this doesn't offer SQL export so we match the import/export style at select

		while ($query != "") {
			if (!$offset && preg_match("~^$space*+DELIMITER\\s+(\\S+)~i", $query, $match)) {
				$delimiter = preg_quote($match[1]);
				$query = substr($query, strlen($match[0]));
			} elseif (!$offset && JUSH == 'pgsql' && preg_match("~^($space*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i", $query, $match)) {
				$delimiter = "\n\\\\\\.\r?\n";
				$offset = strlen($match[0]);
			} else {
				preg_match("($delimiter\\s*|$parse)", $query, $match, PREG_OFFSET_CAPTURE, $offset); // always matches
				list($found, $pos) = $match[0];
				if (!$found && $fp && !feof($fp)) {
					$query .= fread($fp, 1e5);
				} else {
					if (!$found && rtrim($query) == "") {
						break;
					}
					$offset = $pos + strlen($found);

					if ($found && !preg_match("(^$delimiter)", $found)) { // find matching quote or comment end
						$c_style_escapes = driver()->hasCStyleEscapes() || (JUSH == "pgsql" && ($pos > 0 && strtolower($query[$pos - 1]) == "e"));

						$pattern =
							($found == '/*' ? '\*/' :
							($found == '[' ? ']' :
							(preg_match("~^$line_comment|^#~", $found) ? "\n" :
							preg_quote($found) . ($c_style_escapes ? '|\\\\.' : ''))))
						;

						while (preg_match("($pattern|\$)s", $query, $match, PREG_OFFSET_CAPTURE, $offset)) {
							$s = $match[0][0];
							if (!$s && $fp && !feof($fp)) {
								$query .= fread($fp, 1e5);
							} else {
								$offset = $match[0][1] + strlen($s);
								if (!$s || $s[0] != "\\") {
									break;
								}
							}
						}

					} else { // end of a query
						$empty = false;
						$q = substr($query, 0, $pos + ($delimiter[0] == "\n" ? 3 : 0)); // 3 - pass "\n\\." to PostgreSQL COPY
						$commands++;
						$print = "<pre id='sql-$commands'><code class='jush-" . JUSH . "'>" . adminer()->sqlCommandQuery($q) . "</code></pre>\n";
						if (JUSH == "sqlite" && preg_match("~^$space*+ATTACH\\b~i", $q, $match)) {
							// PHP doesn't support setting SQLITE_LIMIT_ATTACHED
							echo $print;
							echo "<p class='error'>" . lang('ATTACH queries are not supported.') . "\n";
							$errors[] = " <a href='#sql-$commands'>$commands</a>";
							if ($_POST["error_stops"]) {
								break;
							}
						} else {
							if (!$_POST["only_errors"]) {
								echo $print;
								ob_flush();
								flush(); // can take a long time - show the running query
							}
							$start = microtime(true);
							//! don't allow changing of character_set_results, convert encoding of displayed query
							if (connection()->multi_query($q) && $connection2 && preg_match("~^$space*+USE\\b~i", $q)) {
								$connection2->query($q);
							}

							do {
								$result = connection()->store_result();

								if (connection()->error) {
									echo ($_POST["only_errors"] ? $print : "");
									echo "<p class='error'>" . lang('Error in query') . (connection()->errno ? " (" . connection()->errno . ")" : "") . ": " . error() . "\n";
									$errors[] = " <a href='#sql-$commands'>$commands</a>";
									if ($_POST["error_stops"]) {
										break 2;
									}

								} else {
									$time = " <span class='time'>(" . format_time($start) . ")</span>"
										. (strlen($q) < 1000 ? " <a href='" . h(ME) . "sql=" . urlencode(trim($q)) . "'>" . lang('Edit') . "</a>" : "") // 1000 - maximum length of encoded URL in IE is 2083 characters
									;
									$affected = connection()->affected_rows; // getting warnings overwrites this
									$warnings = ($_POST["only_errors"] ? "" : driver()->warnings());
									$warnings_id = "warnings-$commands";
									if ($warnings) {
										$time .= ", <a href='#$warnings_id'>" . lang('Warnings') . "</a>" . script("qsl('a').onclick = partial(toggle, '$warnings_id');", "");
									}
									$explain = null;
									$orgtables = null;
									$explain_id = "explain-$commands";
									if (is_object($result)) {
										$limit = $_POST["limit"];
										$orgtables = print_select_result($result, $connection2, array(), $limit);
										if (!$_POST["only_errors"]) {
											echo "<form action='' method='post'>\n";
											$num_rows = $result->num_rows;
											echo "<p class='sql-footer'>" . ($num_rows ? ($limit && $num_rows > $limit ? lang('%d / ', $limit) : "") . lang('%d row(s)', $num_rows) : "");
											echo $time;
											if ($connection2 && preg_match("~^($space|\\()*+SELECT\\b~i", $q) && ($explain = explain($connection2, $q))) {
												echo ", <a href='#$explain_id'>Explain</a>" . script("qsl('a').onclick = partial(toggle, '$explain_id');", "");
											}
											$id = "export-$commands";
											echo ", <a href='#$id'>" . lang('Export') . "</a>" . script("qsl('a').onclick = partial(toggle, '$id');", "") . "<span id='$id' class='hidden'>: "
												. html_select("output", adminer()->dumpOutput(), $adminer_export["output"]) . " "
												. html_select("format", adminer()->dumpFormat(), $adminer_export["format"])
												. input_hidden("query", $q)
												. "<input type='submit' name='export' value='" . lang('Export') . "'>" . input_token() . "</span>\n"
												. "</form>\n"
											;
										}

									} else {
										if (preg_match("~^$space*+(CREATE|DROP|ALTER)$space++(DATABASE|SCHEMA)\\b~i", $q)) {
											restart_session();
											set_session("dbs", null); // clear cache
											stop_session();
										}
										if (!$_POST["only_errors"]) {
											echo "<p class='message' title='" . h(connection()->info) . "'>" . lang('Query executed OK, %d row(s) affected.', $affected) . "$time\n";
										}
									}
									echo ($warnings ? "<div id='$warnings_id' class='hidden'>\n$warnings</div>\n" : "");
									if ($explain) {
										echo "<div id='$explain_id' class='hidden explain'>\n";
										print_select_result($explain, $connection2, $orgtables);
										echo "</div>\n";
									}
								}

								$start = microtime(true);
							} while (connection()->next_result());
						}

						$query = substr($query, $offset);
						$offset = 0;
					}

				}
			}
		}

		if ($empty) {
			echo "<p class='message'>" . lang('No commands to execute.') . "\n";
		} elseif ($_POST["only_errors"]) {
			echo "<p class='message'>" . lang('%d query(s) executed OK.', $commands - count($errors));
			echo " <span class='time'>(" . format_time($total_start) . ")</span>\n";
		} elseif ($errors && $commands > 1) {
			echo "<p class='error'>" . lang('Error in query') . ": " . implode("", $errors) . "\n";
		}
		//! MS SQL - SET SHOWPLAN_ALL OFF

	} else {
		echo "<p class='error'>" . upload_error($query) . "\n";
	}
}
?>

<form action="" method="post" enctype="multipart/form-data" id="form">
<?php
$execute = "<input type='submit' value='" . lang('Execute') . "' title='Ctrl+Enter'>";
if (!isset($_GET["import"])) {
	$q = $_GET["sql"]; // overwrite $q from if ($_POST) to save memory
	if ($_POST) {
		$q = $_POST["query"];
	} elseif ($_GET["history"] == "all") {
		$q = $history;
	} elseif ($_GET["history"] != "") {
		$q = idx($history[$_GET["history"]], 0);
	}
	echo "<p>";
	textarea("query", $q, 20);
	echo script(($_POST ? "" : "qs('textarea').focus();\n") . "qs('#form').onsubmit = partial(sqlSubmit, qs('#form'), '" . js_escape(remove_from_uri("sql|limit|error_stops|only_errors|history")) . "');");
	echo "<p>";
	adminer()->sqlPrintAfter();
	echo "$execute\n";
	echo lang('Limit rows') . ": <input type='number' name='limit' class='size' value='" . h($_POST ? $_POST["limit"] : $_GET["limit"]) . "'>\n";

} else {
	$gz = (extension_loaded("zlib") ? "[.gz]" : "");
	echo "<fieldset><legend>" . lang('File upload') . "</legend><div>";
	echo file_input("SQL$gz: <input type='file' name='sql_file[]' multiple>\n$execute");
	echo "</div></fieldset>\n";
	$importServerPath = adminer()->importServerPath();
	if ($importServerPath) {
		echo "<fieldset><legend>" . lang('From server') . "</legend><div>";
		echo lang('Webserver file %s', "<code>" . h($importServerPath) . "$gz</code>");
		echo ' <input type="submit" name="webfile" value="' . lang('Run file') . '">';
		echo "</div></fieldset>\n";
	}
	echo "<p>";
}

echo checkbox("error_stops", 1, ($_POST ? $_POST["error_stops"] : isset($_GET["import"]) || $_GET["error_stops"]), lang('Stop on error')) . "\n";
echo checkbox("only_errors", 1, ($_POST ? $_POST["only_errors"] : isset($_GET["import"]) || $_GET["only_errors"]), lang('Show only errors')) . "\n";
echo input_token();

if (!isset($_GET["import"]) && $history) {
	print_fieldset("history", lang('History'), $_GET["history"] != "");
	for ($val = end($history); $val; $val = prev($history)) { // not array_reverse() to save memory
		$key = key($history);
		list($q, $time, $elapsed) = $val;
		echo '<a href="' . h(ME . "sql=&history=$key") . '">' . lang('Edit') . "</a>"
			. " <span class='time' title='" . @date('Y-m-d', $time) . "'>" . @date("H:i:s", $time) . "</span>" // @ - time zone may be not set
			. " <code class='jush-" . JUSH . "'>" . shorten_utf8(ltrim(str_replace("\n", " ", str_replace("\r", "", preg_replace("~^(#|$line_comment).*~m", '', $q)))), 80, "</code>")
			. ($elapsed ? " <span class='time'>($elapsed)</span>" : "")
			. "<br>\n"
		;
	}
	echo "<input type='submit' name='clear' value='" . lang('Clear') . "'>\n";
	echo "<a href='" . h(ME . "sql=&history=all") . "'>" . lang('Edit all') . "</a>\n";
	echo "</div></fieldset>\n";
}
?>
</form>
