#!/usr/bin/env php
<?php
include __DIR__ . "/adminer/include/version.inc.php";
include __DIR__ . "/adminer/include/errors.inc.php";
include __DIR__ . "/adminer/include/compress.inc.php";
include __DIR__ . "/externals/JsShrink/jsShrink.php";
include __DIR__ . "/externals/PhpShrink/phpShrink.php";

function add_apo_slashes($s) {
	return addcslashes($s, "\\'");
}

/** Print an error and exit
* @return never
*/
function not_found($search) {
	echo "Not found: " . substr(str_replace("\n", "\\n", $search), 0, 70) . "\n";
	exit(1);
}

/** Replace a string, exit if it is not found */
function replace($search, $replace, $subject) {
	$return = str_replace($search, $replace, $subject, $count);
	if (!$count) {
		not_found($search);
	}
	return $return;
}

/** Replace by a regular expression, exit if it doesn't match
* @param string|callable $replace callback receives the matches
*/
function replace_re($pattern, $replace, $subject, $limit = -1) {
	$return = (is_callable($replace)
		? preg_replace_callback($pattern, $replace, $subject, $limit, $count)
		: preg_replace($pattern, $replace, $subject, $limit, $count)
	);
	if (!$count) {
		not_found($pattern);
	}
	return $return;
}

function remove_lang($match) {
	$idf = strtr($match[2], array("\\'" => "'", "\\\\" => "\\"));
	$s = str_replace("'", '’', Adminer\Lang::$translations[$idf] ?: $idf); // lang_format() is not called for translations without parameters
	if ($match[3] == ",") { // lang() has parameters
		return $match[1] . (is_array($s) ? "lang_format(array('" . implode("', '", array_map('add_apo_slashes', $s)) . "')," : "sprintf('" . add_apo_slashes($s) . "',");
	}
	return ($match[1] && $match[4] ? $s : "$match[1]'" . add_apo_slashes($s) . "'$match[4]");
}

function lang_ids($match) {
	global $lang_ids;
	$lang_id = &$lang_ids[stripslashes($match[1])];
	if ($lang_id === null) {
		$lang_id = count($lang_ids) - 1;
	}
	return ($_SESSION["lang"] ? $match[0] : "lang($lang_id$match[2]");
}

function put_file($match) {
	global $project, $vendor;
	if (preg_match('~LANG~', $match[2])) {
		return $match[0]; // processed later
	}
	$return = file_get_contents(__DIR__ . "/$project/$match[2]");
	$return = replace_re('~namespace Adminer;\s*~', '', $return);
	if ($vendor && preg_match('~/drivers/~', $match[2])) {
		if ($vendor != "mysql") { // mysql.inc.php is not wrapped in a condition
			$return = replace_re('~^if \(isset\(\$_GET\["' . $vendor . '"]\)\) \{(.*)^}~ms', '\1', $return);
			// check function definition in drivers
			preg_match_all(
				'~\bfunction (?!alter_table|drop_tables|truncate_tables)([^(]+)~', // used for feature detection
				replace_re('~class Driver.*\n\t}~sU', '', file_get_contents(__DIR__ . "/adminer/drivers/mysql.inc.php")),
				$matches
			); //! respect context (extension, class)
			$functions = array_combine($matches[1], $matches[0]);
			$requires = array(
				"copy" => array("copy_tables"),
				"database" => array("create_database", "rename_database", "drop_databases", "move_tables"),
				"dump" => array("use_sql", "create_sql", "truncate_sql", "trigger_sql"),
				"kill" => array("kill_process", "connection_id", "max_connections"),
				"processlist" => array("process_list"),
				"routine" => array("routines", "routine", "routine_languages", "routine_id"),
				"scheme" => array("schemas", "get_schema", "set_schema"),
				"sql" => array("multi_query", "store_result", "next_result", "explain"),
				"status" => array("show_status"),
				"indexes" => array("alter_indexes"),
				"table" => array("auto_increment"),
				"trigger" => array("triggers", "trigger", "trigger_options", "trigger_sql"),
				"type" => array("types", "type_values", "type_definition"),
				"variables" => array("show_variables"),
				"view" => array("drop_views", "view"),
			);
			foreach ($requires as $support => $fns) {
				if (!Adminer\support($support)) {
					foreach ($fns as $fn) {
						unset($functions[$fn]);
					}
				}
			}
			unset($functions["__construct"], $functions["__destruct"], $functions["set_charset"], $functions["multi_query"], $functions["store_result"], $functions["next_result"]);
			unset($functions["parse_type"]); // helper of the MySQL driver, not a part of the driver interface
			foreach ($functions as $val) {
				if (!strpos($return, "$val(")) {
					fprintf(STDERR, "Missing $val in $vendor\n");
				}
			}
		}
	}
	if (basename($match[2]) == "lang.inc.php") {
		if (!$_SESSION["lang"]) {
			$return = replace('function lang(string $idf, $number = null): string {', 'function lang($idf, $number = null) {
	if (is_string($idf)) { // compiled version uses numbers, string comes from a plugin
		// English translation is closest to the original identifiers //! pluralized translations are not found
		$pos = array_search($idf, get_translations("en")); //! this should be cached
		if ($pos !== false) {
			$idf = $pos;
		}
	}', $return);
		} else {
			$return = replace_re('~// not used in a single language version from here.*~s', '', $return);
			$return = replace_re('~(\$pos = (.+\n).+;)~sU', function ($match) {
				return "\$pos = $match[2]\t\t\t: " . (preg_match("~'$_SESSION[lang]'.* \\? (.+)\n~U", $match[1], $match2) ? $match2[1] : "1") . "\n\t\t);";
			}, $return);
			$return = replace('Lang::$translations[$idf] ?: $idf', '$idf', $return); // lang() is used only by old plugins
			$return .= "define('Adminer\\LANG', '$_SESSION[lang]');\n";
		}
	}
	$tokens = token_get_all($return); // to find out the last token
	return "?>\n$return" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
}

function put_file_lang($match) {
	if ($_SESSION["lang"]) {
		return "";
	}
	$return = "";
	$dictionary = get_lang_translations("en"); // other languages are compressed against English, it saves 13% of their size
	foreach (array_keys(Adminer\langs()) as $lang) {
		$compressed = Adminer\compress_string(get_lang_translations($lang), ($lang != "en" ? $dictionary : ""));
		$return .= '
		case "' . $lang . '": return \'' . $compressed . '\';';
	}
	$translations_version = crc32($return);
	return 'Lang::$translations = (array) $_SESSION["translations"];
if ($_SESSION["translations_version"] != LANG . ' . $translations_version . ') {
	Lang::$translations = array();
	$_SESSION["translations_version"] = LANG . ' . $translations_version . ';
}
if (!Lang::$translations) {
	Lang::$translations = get_translations(LANG);
	$_SESSION["translations"] = Lang::$translations;
}

function get_compressed($lang) {
	switch ($lang) {' . $return . '
	}
}

function get_translations($lang) {
	$dictionary = ($lang != "en" ? decompress_string(get_compressed("en")) : "");
	$translations = array();
	foreach (explode("\n", decompress_string(get_compressed($lang), $dictionary)) as $val) {
		$translations[] = (strpos($val, "\t") ? explode("\t", $val) : $val);
	}
	return $translations;
}
';
}

/** Get translations of a language indexed by $lang_ids, joined by newlines */
function get_lang_translations($lang) {
	global $lang_ids;
	include __DIR__ . "/adminer/lang/$lang.inc.php";
	$translation_ids = array_flip($lang_ids); // default translation
	foreach (Adminer\Lang::$translations as $key => $val) {
		if ($val !== null) {
			$translation_ids[$lang_ids[$key]] = implode("\t", (array) $val);
		}
	}
	return implode("\n", $translation_ids);
}

function minify_css($file) {
	global $project;
	if ($project == "editor") {
		$file = preg_replace('~\.icon-(plus|cross).*~', '', $file);
	}
	// images are inlined as data: URIs directly in the CSS, see the comment in default.css
	return Adminer\compress_string(preg_replace('~\s*([:;{},])\s*~', '\1', preg_replace('~/\*.*?\*/\s*~s', '', $file)));
}

function minify_js($file) {
	$file = preg_replace_callback("~'use strict';~", function ($match) {
		static $count = 0;
		$count++;
		return ($count == 1 ? $match[0] : ''); // keep only the first one
	}, $file);
	if (function_exists('jsShrink')) {
		$file = jsShrink($file);
	}
	return Adminer\compress_string($file);
}

// $callback only to match signature
function compile_file($match, $callback = '') {
	global $project;
	$file = "";
	list(, $filenames, $callback) = $match;
	if ($filenames != "") {
		foreach (preg_split('~;\s*~', $filenames) as $filename) {
			$file .= file_get_contents(__DIR__ . "/$project/$filename");
		}
	}
	if ($callback) {
		return "'" . call_user_func($callback, $file) . "'"; // compressed string doesn't need escaping
	}
	return "base64_decode('" . base64_encode($file) . "')";
}

function number_type() {
	return '';
}

function ini_bool() {
	return true;
}

$project = "adminer";
if ($_SERVER["argv"][1] == "editor") {
	$project = "editor";
	array_shift($_SERVER["argv"]);
}

$vendor = "";
$driver_path = "/adminer/drivers/" . $_SERVER["argv"][1] . ".inc.php";
if (!file_exists(__DIR__ . $driver_path)) {
	$driver_path = "/plugins/drivers/" . $_SERVER["argv"][1] . ".php";
}
if (file_exists(__DIR__ . $driver_path)) {
	$vendor = $_SERVER["argv"][1];
	array_shift($_SERVER["argv"]);
}

unset($_COOKIE["adminer_lang"]);
$_SESSION["lang"] = $_SERVER["argv"][1]; // Adminer functions read language from session
include __DIR__ . "/adminer/include/functions.inc.php";
include __DIR__ . "/adminer/include/decompress.inc.php";
include __DIR__ . "/adminer/include/lang.inc.php";
if (Adminer\idx(Adminer\langs(), $_SESSION["lang"])) {
	include __DIR__ . "/adminer/lang/$_SESSION[lang].inc.php";
	array_shift($_SERVER["argv"]);
}

if ($_SERVER["argv"][1]) {
	echo "Usage: php compile.php [editor] [driver] [lang]\n";
	echo "Purpose: Compile adminer[-driver][-lang].php or editor[-driver][-lang].php.\n";
	exit(1);
}

include __DIR__ . "/adminer/include/db.inc.php";
include __DIR__ . "/adminer/include/pdo.inc.php";
include __DIR__ . "/adminer/include/driver.inc.php";
include __DIR__ . "/adminer/include/plugins.inc.php"; // for Plugins::checksum()
$features = array(
	"check", "call" => "routine", "dump", "event", "privileges", "procedure" => "routine", "processlist", "routine",
	"scheme", "sequence", "sql", "status", "trigger", "type", "user" => "privileges", "variables", "view",
);
$lang_ids = array(); // global variable simplifies usage in a callback function
$file = file_get_contents(__DIR__ . "/$project/index.php");
$file = replace_re('~\*/~', "* @version " . Adminer\VERSION . "\n*/", $file, 1);
if ($vendor) {
	$_GET[$vendor] = true; // to load the driver
	include_once __DIR__ . $driver_path;
	Adminer\Db::$instance = (object) array('flavor' => '', 'server_info' => '99'); // used in support()
	foreach ($features as $key => $feature) {
		if (!Adminer\support($feature)) {
			if (!is_int($key)) {
				$feature = $key;
			}
			if (file_exists(__DIR__ . "/$project/$feature.inc.php")) { // e.g. routine and status have no page of their own, Editor includes the pages from adminer/
				$file = replace("} elseif (isset(\$_GET[\"$feature\"])) {\n\tinclude \"./$feature.inc.php\";\n", "", $file);
			}
		}
	}
	if ($project != "editor" && !Adminer\support("routine")) {
		$file = replace(
			"if (isset(\$_GET[\"callf\"])) {\n\t\$_GET[\"call\"] = \$_GET[\"callf\"];\n}\n"
				. "if (isset(\$_GET[\"function\"])) {\n\t\$_GET[\"procedure\"] = \$_GET[\"function\"];\n}\n",
			"",
			$file
		);
	}
}
$file = replace_re('~\b(include|require) "([^"]*)";~', 'put_file', $file);
$file = replace('include "../adminer/include/coverage.inc.php";', '', $file);
if ($vendor) {
	if (preg_match('~^/plugins/~', $driver_path)) {
		$file = replace_re('((include "..)/adminer/drivers/mysql.inc.php)', "\\1$driver_path", $file);
	}
	$file = replace_re('(include "../adminer/drivers/(?!' . preg_quote($vendor) . '\.).*\s*)', '', $file);
}
$file = replace_re('~\b(include|require) "([^"]*)";~', 'put_file', $file); // bootstrap.inc.php

// inline the checksums of official plugins, the plugins/ directory is not available next to the compiled file
$checksums = "";
foreach (array("plugins", "plugins/drivers") as $dir) {
	foreach (glob(__DIR__ . "/$dir/*.php") as $filename) {
		$checksums .= "\n\t\t\t'" . basename($filename, '.php') . "' => '" . Adminer\Plugins::checksum($filename) . "',";
	}
}
$file = replace_re('~(function officialChecksums\(\): array \{\n).*?(\n\t\})~s', "\\1\t\treturn array($checksums\n\t\t);\\2", $file, 1);

// inline the checksums of official designs, the designs/ directory is not available next to the compiled file
$designs = "";
foreach (glob(__DIR__ . "/designs/*/*.css") as $filename) {
	if (preg_match('~^/\* Adminer design ([-\w]+) \*/~', file_get_contents($filename), $match)) {
		$designs .= "\n\t\t'$match[1]/" . basename($filename) . "' => '" . Adminer\Plugins::checksum($filename) . "',";
	}
}
$file = replace_re('~(function official_design_checksums\(\): array \{\n).*?(\n\})~s', "\\1\treturn array($designs\n\t);\\2", $file, 1);
if ($vendor) {
	foreach ($features as $feature) {
		if (!Adminer\support($feature)) {
			$file = preg_replace("((\t*)" . preg_quote('if (support("' . $feature . '")') . ".*?\n\\1\\}( else)?)s", '', $file);
		}
	}
	if ($project != "editor" && count(Adminer\SqlDriver::$drivers) == 1) {
		$file = replace(
			'html_select("auth[driver]", SqlDriver::$drivers, DRIVER, on(\'change\', \'loginDriver\'))',
			'input_hidden("auth[driver]", "' . ($vendor == "mysql" ? "server" : $vendor) . '") . "' . reset(Adminer\SqlDriver::$drivers) . '"',
			$file
		);
		$file = replace(". script(\"fire(qs('#username').form['auth[driver]'], 'change');\")", "", $file);
		if ($vendor == "sqlite") {
			// SQLite doesn't use the server but the value must be preserved for the login form
			$file = replace_re('~(\t*)echo adminer\(\)->loginFormField\(\s*\'server\',.*?\);\n~s', "\\1echo input_hidden(\"auth[server]\", SERVER);\n", $file);
		}
	}
	$file = replace_re('(;\s*../externals/jush/modules/jush-(?!autocomplete-sql\.|textarea\.|txt\.|json\.|' . preg_quote($vendor == "mysql" ? "sql" : $vendor) . '\.)[^.]+.js)', '', $file);
	$file = replace_re('~doc_link\(array\((.*)\)\)~sU', function ($match) use ($vendor) {
		list(, $links) = $match;
		$links = preg_replace("~'(?!(" . ($vendor == "mysql" ? "sql|mariadb" : $vendor) . ")')[^']*' => [^,]*,?~", '', $links);
		return (trim($links) ? "doc_link(array($links))" : "''");
	}, $file);
	//! strip doc_link() definition
}
if ($project == "editor") {
	$file = replace_re('~;.\.\/externals/jush/jush(-dark)?\.css~', '', $file);
	$file = replace_re('~compile_file\(\'\.\./(externals/jush/modules/jush\.js)[^)]+\)~', "''", $file);
}
// \s* after ( allows wrapping long lang() calls
$file = replace_re("~(?<!>)lang\\(\\s*'((?:[^\\\\']+|\\\\.)*)'([,)])~s", 'lang_ids', $file);
$file = replace_re('~\b(include|require) "([^"]*" . LANG . ".inc.php)";~', 'put_file_lang', $file);
$file = str_replace("\r", "", $file);
if ($_SESSION["lang"]) {
	// single language version
	$file = replace_re("~(<\\?php\\s*echo )?(?<!>)lang\\(\\s*'((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = replace("switch_lang();", "", $file);
	$file = replace('<?php echo LANG; ?>', $_SESSION["lang"], $file);
}
$file = replace('echo script_src("static/editing.js");' . "\n", "", $file); // merged into functions.js
if ($project != "editor") { // the Editor doesn't use jush
	$file = replace_re('~\s+echo script_src\("\.\./externals/jush/modules/jush-(autocomplete-sql|textarea|txt|json)\.js", true\);~', '', $file); // merged into jush.js
	// modules of bundled drivers are merged into jush.js, plugin drivers can ship their own module
	if (!$vendor || Adminer\support("sql")) { // the module is printed in a block which is removed for drivers without SQL support
		$file = replace_re(
			'~(\t*)echo script_src\("\.\./externals/jush/modules/(jush-" \. JUSH \. "\.js)", true\);~',
			'\1$jush_file = "adminer-plugins/\2";' . "\n" . '\1echo (file_exists($jush_file) ? script_src($jush_file, true) : "");',
			$file
		);
	}
	$file = replace_re('~echo .*/jush(-dark)?.css\'>.*~', '', $file); // merged into default.css or dark.css
}
if (function_exists('stripTypes')) {
	$file = stripTypes($file);
}
$file = replace_re("~compile_file\\('([^']+)'(?:, '([^']*)')?\\)~", 'compile_file', $file); // integrate static files
$replace = 'preg_replace("~\\\\\\\\?.*~", "", ME) . "?file=\1&version=' . Adminer\VERSION . '"';
$file = replace_re('~\.\./adminer/static/(default\.css)~', '<?php echo h(' . $replace . '); ?>', $file);
$file = replace_re('~"\.\./adminer/static/(functions\.js)"~', $replace, $file);
$file = replace_re('~\.\./adminer/static/([^\'"]*)~', '" . h(' . $replace . ') . "', $file);
if ($project != "editor") { // the Editor doesn't use jush
	$file = replace_re('~"\.\./externals/jush/modules/(jush\.js)"~', $replace, $file);
}
if (function_exists('phpShrink')) {
	$file = phpShrink($file);
}

$filename = $project . (preg_match('~-dev$~', Adminer\VERSION) ? "" : "-" . Adminer\VERSION) . ($vendor ? "-$vendor" : "") . ($_SESSION["lang"] ? "-$_SESSION[lang]" : "") . ".php";
file_put_contents($filename, $file);
echo "$filename created (" . strlen($file) . " B).\n";
