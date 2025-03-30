#!/usr/bin/env php
<?php
include __DIR__ . "/adminer/include/version.inc.php";
include __DIR__ . "/adminer/include/errors.inc.php";
include __DIR__ . "/externals/JsShrink/jsShrink.php";
include __DIR__ . "/externals/PhpShrink/phpShrink.php";

function add_apo_slashes($s) {
	return addcslashes($s, "\\'");
}

function add_quo_slashes($s) {
	$return = $s;
	$return = addcslashes($return, "\n\r\$\"\\");
	$return = preg_replace('~\0(?![0-7])~', '\\\\0', $return);
	$return = addcslashes($return, "\0");
	return $return;
}

function remove_lang($match) {
	global $translations;
	$idf = strtr($match[2], array("\\'" => "'", "\\\\" => "\\"));
	$s = ($translations[$idf] ?: $idf);
	if ($match[3] == ",") { // lang() has parameters
		return $match[1] . (is_array($s) ? "lang(array('" . implode("', '", array_map('add_apo_slashes', $s)) . "')," : "sprintf('" . add_apo_slashes($s) . "',");
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
	$return = preg_replace('~namespace Adminer;\s*~', '', $return);
	if ($vendor && preg_match('~/drivers/~', $match[2])) {
		$return = preg_replace('~^if \(isset\(\$_GET\["' . $vendor . '"]\)\) \{(.*)^}~ms', '\1', $return);
		// check function definition in drivers
		if ($vendor != "mysql") {
			preg_match_all(
				'~\bfunction ([^(]+)~',
				preg_replace('~class Driver.*\n\t}~sU', '', file_get_contents(__DIR__ . "/adminer/drivers/mysql.inc.php")),
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
				"type" => array("types", "type_values"),
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
			foreach ($functions as $val) {
				if (!strpos($return, "$val(")) {
					fprintf(STDERR, "Missing $val in $vendor\n");
				}
			}
		}
	}
	if (basename($match[2]) != "lang.inc.php" || !$_SESSION["lang"]) {
		if (basename($match[2]) == "lang.inc.php") {
			$return = str_replace('function lang(string $idf, $number = null): string {', 'function lang($idf, $number = null) {
	if (is_string($idf)) { // compiled version uses numbers, string comes from a plugin
		// English translation is closest to the original identifiers //! pluralized translations are not found
		$pos = array_search($idf, get_translations("en")); //! this should be cached
		if ($pos !== false) {
			$idf = $pos;
		}
	}', $return, $count);
			if (!$count) {
				echo "lang() not found\n";
			}
		}
		$tokens = token_get_all($return); // to find out the last token
		return "?>\n$return" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
	} elseif (preg_match('~\s*(\$pos = (.+\n).+;)~sU', $return, $match2)) {
		// single language lang() is used for plural
		return "function get_lang() {
	return '$_SESSION[lang]';
}

function lang(\$translation, \$number = null) {
	if (is_array(\$translation)) {
		\$pos = $match2[2]\t\t\t: " . (preg_match("~'$_SESSION[lang]'.* \\? (.+)\n~U", $match2[1], $match3) ? $match3[1] : "1") . '
		);
		$translation = $translation[$pos];
	}
	$translation = str_replace("%d", "%s", $translation);
	$number = format_number($number);
	return sprintf($translation, $number);
}
';
	} else {
		echo "lang() \$pos not found\n";
	}
}

function lzw_compress($string) {
	// compression
	$dictionary = array_flip(range("\0", "\xFF"));
	$word = "";
	$codes = array();
	for ($i=0; $i <= strlen($string); $i++) {
		$x = @$string[$i];
		if (strlen($x) && isset($dictionary[$word . $x])) {
			$word .= $x;
		} elseif ($i) {
			$codes[] = $dictionary[$word];
			$dictionary[$word . $x] = count($dictionary);
			$word = $x;
		}
	}
	// convert codes to binary string
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$return = "";
	$rest = 0;
	$rest_length = 0;
	foreach ($codes as $code) {
		$rest = ($rest << $bits) + $code;
		$rest_length += $bits;
		$dictionary_count++;
		if ($dictionary_count >> $bits) {
			$bits++;
		}
		while ($rest_length > 7) {
			$rest_length -= 8;
			$return .= chr($rest >> $rest_length);
			$rest &= (1 << $rest_length) - 1;
		}
	}
	return $return . ($rest_length ? chr($rest << (8 - $rest_length)) : "");
}

function put_file_lang($match) {
	global $lang_ids, $project;
	if ($_SESSION["lang"]) {
		return "";
	}
	$return = "";
	foreach (Adminer\langs() as $lang => $val) {
		include __DIR__ . "/adminer/lang/$lang.inc.php"; // assign $translations
		$translation_ids = array_flip($lang_ids); // default translation
		foreach ($translations as $key => $val) {
			if ($val !== null) {
				$translation_ids[$lang_ids[$key]] = implode("\t", (array) $val);
			}
		}
		$return .= '
		case "' . $lang . '": $compressed = "' . add_quo_slashes(lzw_compress(implode("\n", $translation_ids))) . '"; break;';
	}
	$translations_version = crc32($return);
	return '$translations = $_SESSION["translations"];
if ($_SESSION["translations_version"] != ' . $translations_version . ') {
	$translations = array();
	$_SESSION["translations_version"] = ' . $translations_version . ';
}

function get_translations($lang) {
	switch ($lang) {' . $return . '
	}
	$translations = array();
	foreach (explode("\n", lzw_decompress($compressed)) as $val) {
		$translations[] = (strpos($val, "\t") ? explode("\t", $val) : $val);
	}
	return $translations;
}

if (!$translations) {
	$translations = get_translations(LANG);
	$_SESSION["translations"] = $translations;
}
';
}

function minify_css($file) {
	global $project;
	if ($project == "editor") {
		$file = preg_replace('~.*\.url\(.*~', '', $file);
	}
	return lzw_compress(preg_replace('~\s*([:;{},])\s*~', '\1', preg_replace('~/\*.*?\*/\s*~s', '', $file)));
}

function minify_js($file) {
	$file = preg_replace_callback("~'use strict';~", function ($match) { // keep only the first one
		static $count = 0;
		$count++;
		return ($count == 1 ? $match[0] : '');
	}, $file);
	if (function_exists('jsShrink')) {
		$file = jsShrink($file);
	}
	return lzw_compress($file);
}

function compile_file($match, $callback = '') { // $callback only to match signature
	global $project;
	$file = "";
	list(, $filenames, $callback) = $match;
	if ($filenames != "") {
		foreach (preg_split('~;\s*~', $filenames) as $filename) {
			$file .= file_get_contents(__DIR__ . "/$project/$filename");
		}
	}
	if ($callback) {
		$file = call_user_func($callback, $file);
	}
	return '"' . add_quo_slashes($file) . '"';
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
$features = array("check", "call" => "routine", "dump", "event", "privileges", "procedure" => "routine", "processlist", "routine", "scheme", "sequence", "sql", "status", "trigger", "type", "user" => "privileges", "variables", "view");
$lang_ids = array(); // global variable simplifies usage in a callback function
$file = file_get_contents(__DIR__ . "/$project/index.php");
$file = preg_replace('~\*/~', "* @version " . Adminer\VERSION . "\n*/", $file, 1);
if ($vendor) {
	$_GET[$vendor] = true; // to load the driver
	include_once __DIR__ . $driver_path;
	Adminer\Db::$instance = (object) array('flavor' => '', 'server_info' => '99'); // used in support()
	foreach ($features as $key => $feature) {
		if (!Adminer\support($feature)) {
			if (!is_int($key)) {
				$feature = $key;
			}
			$file = str_replace("} elseif (isset(\$_GET[\"$feature\"])) {\n\tinclude \"./$feature.inc.php\";\n", "", $file);
		}
	}
	if (!Adminer\support("routine")) {
		$file = str_replace("if (isset(\$_GET[\"callf\"])) {\n\t\$_GET[\"call\"] = \$_GET[\"callf\"];\n}\nif (isset(\$_GET[\"function\"])) {\n\t\$_GET[\"procedure\"] = \$_GET[\"function\"];\n}\n", "", $file);
	}
}
$file = preg_replace_callback('~\b(include|require) "([^"]*)";~', 'put_file', $file);
$file = str_replace('include "../adminer/include/coverage.inc.php";', '', $file);
if ($vendor) {
	if (preg_match('~^/plugins/~', $driver_path)) {
		$file = preg_replace('((include "..)/adminer/drivers/mysql.inc.php)', "\\1$driver_path", $file);
	}
	$file = preg_replace('(include "../adminer/drivers/(?!' . preg_quote($vendor) . '\.).*\s*)', '', $file);
}
$file = preg_replace_callback('~\b(include|require) "([^"]*)";~', 'put_file', $file); // bootstrap.inc.php
if ($vendor) {
	foreach ($features as $feature) {
		if (!Adminer\support($feature)) {
			$file = preg_replace("((\t*)" . preg_quote('if (support("' . $feature . '")') . ".*?\n\\1\\}( else)?)s", '', $file);
		}
	}
	if ($project != "editor" && count(Adminer\SqlDriver::$drivers) == 1) {
		$file = str_replace('html_select("auth[driver]", SqlDriver::$drivers, DRIVER, "loginDriver(this);")', 'input_hidden("auth[driver]", "' . ($vendor == "mysql" ? "server" : $vendor) . '") . "' . reset(Adminer\SqlDriver::$drivers) . '"', $file, $count);
		if (!$count) {
			echo "auth[driver] form field not found\n";
		}
		$file = str_replace(" . script(\"qs('#username').form['auth[driver]'].onchange();\")", "", $file);
		if ($vendor == "sqlite") {
			$file = str_replace(");\n\t\techo \$this->loginFormField('server', '<tr><th>' . lang('Server') . '<td>', '<input name=\"auth[server]", ' . \'<input type="hidden" name="auth[server]"', $file);
		}
	}
	$file = preg_replace('(;\s*../externals/jush/modules/jush-(?!textarea\.|txt\.|js\.|' . preg_quote($vendor == "mysql" ? "sql" : $vendor) . '\.)[^.]+.js)', '', $file);
	$file = preg_replace_callback('~doc_link\(array\((.*)\)\)~sU', function ($match) use ($vendor) {
		list(, $links) = $match;
		$links = preg_replace("~'(?!(" . ($vendor == "mysql" ? "sql|mariadb" : $vendor) . ")')[^']*' => [^,]*,?~", '', $links);
		return (trim($links) ? "doc_link(array($links))" : "''");
	}, $file);
	//! strip doc_link() definition
}
if ($project == "editor") {
	$file = preg_replace('~;.\.\/externals/jush/jush(-dark)?\.css~', '', $file);
	$file = preg_replace('~compile_file\(\'\.\./(externals/jush/modules/jush\.js)[^)]+\)~', "''", $file);
}
$file = preg_replace_callback("~lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])~s", 'lang_ids', $file);
$file = preg_replace_callback('~\b(include|require) "([^"]*" . LANG . ".inc.php)";~', 'put_file_lang', $file);
$file = str_replace("\r", "", $file);
if ($_SESSION["lang"]) {
	// single language version
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("switch_lang();", "", $file);
	$file = str_replace('<?php echo LANG; ?>', $_SESSION["lang"], $file);
}
$file = str_replace('echo script_src("static/editing.js");' . "\n", "", $file); // merged into functions.js
$file = preg_replace('~\s+echo script_src\("\.\./externals/jush/modules/jush-(textarea|txt|js|" \. JUSH \. ")\.js"\);~', '', $file); // merged into jush.js
$file = preg_replace('~echo .*/jush(-dark)?.css\'>.*~', '', $file); // merged into default.css or dark.css
if (function_exists('stripTypes')) {
	$file = stripTypes($file);
}
$file = preg_replace_callback("~compile_file\\('([^']+)'(?:, '([^']*)')?\\)~", 'compile_file', $file); // integrate static files
$replace = 'preg_replace("~\\\\\\\\?.*~", "", ME) . "?file=\1&version=' . Adminer\VERSION . '"';
$file = preg_replace('~\.\./adminer/static/(default\.css)~', '<?php echo h(' . $replace . '); ?>', $file);
$file = preg_replace('~"\.\./adminer/static/(functions\.js)"~', $replace, $file);
$file = preg_replace('~\.\./adminer/static/([^\'"]*)~', '" . h(' . $replace . ') . "', $file);
$file = preg_replace('~"\.\./externals/jush/modules/(jush\.js)"~', $replace, $file);
if (function_exists('phpShrink')) {
	$file = phpShrink($file);
}

$filename = $project . (preg_match('~-dev$~', Adminer\VERSION) ? "" : "-" . Adminer\VERSION) . ($vendor ? "-$vendor" : "") . ($_SESSION["lang"] ? "-$_SESSION[lang]" : "") . ".php";
file_put_contents($filename, $file);
echo "$filename created (" . strlen($file) . " B).\n";
