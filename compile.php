#!/usr/bin/env php
<?php
function adminer_errors($errno, $errstr) {
	return !!preg_match('~^(Trying to access array offset on value of type null|Undefined array key)~', $errstr);
}

error_reporting(6135); // errors and warnings
set_error_handler('adminer_errors', E_WARNING);

include dirname(__FILE__) . "/adminer/include/debug.inc.php";
include dirname(__FILE__) . "/adminer/include/version.inc.php";
include dirname(__FILE__) . "/vendor/vrana/jsshrink/jsShrink.php";

function is_dev_version()
{
	global $VERSION;

	return (bool)preg_match('~-dev$~', $VERSION);
}

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

function replace_lang($match) {
	global $lang_ids;

	$text = stripslashes($match[1]);
	if (!isset($lang_ids[$text])) {
		$lang_ids[$text] = count($lang_ids);
	}

	return "lang($lang_ids[$text]$match[2]";
}

function put_file($match) {
	global $project, $selected_languages, $single_driver;

	$filename = basename($match[2]);

	// Language is processed later.
	if ($filename == '$LANG.inc.php') {
		return $match[0];
	}

	$content = file_get_contents(dirname(__FILE__) . "/$project/$match[2]");

	if ($filename == "file.inc.php") {
		$content = str_replace("\n// caching headers added in compile.php", (is_dev_version() ? '' : '
			if ($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
				header("HTTP/1.1 304 Not Modified");
				exit;
			}

			header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: immutable");
			'), $content, $count);
		if (!$count) {
			echo "adminer/file.inc.php: Caching headers placeholder not found\n";
		}
	}

	if ($filename == "lang.inc.php") {
		$content = str_replace(
			'return $key; // compile: convert translation key',
			'static $en_translations = null;

			// Convert string key used in plugins to compiled numeric key.
			if (is_string($key)) {
				if (!$en_translations) {
					$en_translations = get_translations("en");
				}

				// Find text in English translations or plurals map.
				if (($index = array_search($key, $en_translations)) !== false) {
					$key = $index;
				} elseif (($index = get_plural_translation_id($key)) !== null) {
					$key = $index;
				}
			}

			return $key;',
			$content, $count
		);

		if (!$count) {
			echo "function lang() not found\n";
		}

		if ($selected_languages) {
			$available_languages = array_fill_keys($selected_languages, true);
			$content = str_replace(
				'return $languages; // compile: available languages',
				'return ' . var_export($available_languages, true) . ';',
				$content
			);
		}
	}

	$tokens = token_get_all($content); // to find out the last token

	return "?>\n$content" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
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

function put_file_lang() {
	global $lang_ids, $selected_languages;

	$languages = array_map(function ($filename) {
		preg_match('~/([^/.]+)\.inc\.php$~', $filename, $matches);
		return $matches[1];
	}, glob(dirname(__FILE__) . "/adminer/lang/*.inc.php"));

	$cases = "";
	$plurals_map = [];

	foreach ($languages as $language) {
		// Include only selected language and "en" into single language compilation.
		// "en" is used for translations in plugins.
		if ($selected_languages && !in_array($language, $selected_languages) && $language != "en") {
			continue;
		}

		// Assign $translations
		$translations = [];
		include dirname(__FILE__) . "/adminer/lang/$language.inc.php";

		$translation_ids = array_flip($lang_ids); // default translation
		foreach ($translations as $key => $val) {
			if ($val !== null) {
				$translation_ids[$lang_ids[$key]] = $val;

				if ($language == "en" && is_array($val)) {
					$plurals_map[$key] = $lang_ids[$key];
				}
			}
		}

		$cases .= 'case "' . $language . '": $compressed = "' . add_quo_slashes(lzw_compress(json_encode($translation_ids, JSON_UNESCAPED_UNICODE))) . '"; break;';
	}

	$translations_version = crc32($cases);

	return '
		function get_translations($lang) {
			switch ($lang) {' . $cases . '}

			return json_decode(lzw_decompress($compressed), true);
		}

		function get_plural_translation_id($key) {
			$plurals_map = ' . var_export($plurals_map, true) . ';

			return isset($plurals_map[$key]) ? $plurals_map[$key] : null;
		}

		$translations = $_SESSION["translations"];

		if ($_SESSION["translations_version"] != ' . $translations_version . ') {
			$translations = [];
			$_SESSION["translations_version"] = ' . $translations_version . ';
		}
		if ($_SESSION["translations_language"] != $LANG) {
			$translations = [];
			$_SESSION["translations_language"] = $LANG;
		}

		if (!$translations) {
			$translations = get_translations($LANG);
			$_SESSION["translations"] = $translations;
		}
	';
}

function short_identifier($number, $chars) {
	$return = '';
	while ($number >= 0) {
		$return .= $chars[$number % strlen($chars)];
		$number = floor($number / strlen($chars)) - 1;
	}
	return $return;
}

// based on http://latrine.dgx.cz/jak-zredukovat-php-skripty
function php_shrink($input) {
	global $VERSION;
	$special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER', '$http_response_header', '$php_errormsg'));
	$short_variables = array();
	$shortening = true;
	$tokens = token_get_all($input);

	// remove unnecessary { }
	//! change also `while () { if () {;} }` to `while () if () ;` but be careful about `if () { if () { } } else { }
	$shorten = 0;
	$opening = -1;
	foreach ($tokens as $i => $token) {
		if (in_array($token[0], array(T_IF, T_ELSE, T_ELSEIF, T_WHILE, T_DO, T_FOR, T_FOREACH), true)) {
			$shorten = ($token[0] == T_FOR ? 4 : 2);
			$opening = -1;
		} elseif (in_array($token[0], array(T_SWITCH, T_FUNCTION, T_CLASS, T_CLOSE_TAG), true)) {
			$shorten = 0;
		} elseif ($token === ';') {
			$shorten--;
		} elseif ($token === '{') {
			if ($opening < 0) {
				$opening = $i;
			} elseif ($shorten > 1) {
				$shorten = 0;
			}
		} elseif ($token === '}' && $opening >= 0 && $shorten == 1) {
			unset($tokens[$opening]);
			unset($tokens[$i]);
			$shorten = 0;
			$opening = -1;
		}
	}
	$tokens = array_values($tokens);

	foreach ($tokens as $token) {
		if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
			$short_variables[$token[1]]++;
		}
	}

	arsort($short_variables);
	$chars = implode(range('a', 'z')) . '_' . implode(range('A', 'Z'));
	// preserve variable names between versions if possible
	$short_variables2 = array_splice($short_variables, strlen($chars));
	ksort($short_variables);
	ksort($short_variables2);
	$short_variables += $short_variables2;
	foreach (array_keys($short_variables) as $number => $key) {
		$short_variables[$key] = short_identifier($number, $chars); // could use also numbers and \x7f-\xff
	}

	$set = array_flip(preg_split('//', '!"#$%&\'()*+,-./:;<=>?@[]^`{|}'));
	$space = '';
	$output = '';
	$in_echo = false;
	$doc_comment = false; // include only first /**
	for (reset($tokens); list($i, $token) = each($tokens); ) {
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
			&& strlen(add_apo_slashes($tokens[$i+3][1])) < strlen($tokens[$i+3][1]) + 3
		) {
			$tokens[$i+2] = array(T_ECHO, 'echo');
			$tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . add_apo_slashes($tokens[$i+3][1]) . "'");
			$tokens[$i+4] = array(0, ';');
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
			$space = " ";
		} else {
			if ($token[0] == T_DOC_COMMENT) {
				$doc_comment = true;
				$token[1] = substr_replace($token[1], "* @version $VERSION\n", -2, 0);
			}
			if ($token[0] == T_VAR) {
				$shortening = false;
			} elseif (!$shortening) {
				if ($token[1] == ';') {
					$shortening = true;
				}
			} elseif ($token[0] == T_ECHO) {
				$in_echo = true;
			} elseif ($token[1] == ';' && $in_echo) {
				if ($tokens[$i+1][0] === T_WHITESPACE && $tokens[$i+2][0] === T_ECHO) {
					next($tokens);
					$i++;
				}
				if ($tokens[$i+1][0] === T_ECHO) {
					// join two consecutive echos
					next($tokens);
					$token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
				} else {
					$in_echo = false;
				}
			} elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
				$token[1] = '$' . $short_variables[$token[1]];
			}

			if ($token[0] == T_FUNCTION || $token[0] == T_CLASS || $token[0] == T_INTERFACE || $token[0] == T_TRAIT) {
				$space = "\n";
			} elseif (isset($set[substr($output, -1)]) || isset($set[$token[1][0]])) {
				$space = '';
			}

			$output .= $space . $token[1];
			$space = '';
		}
	}
	return $output;
}

function minify_css($file) {
	return lzw_compress(preg_replace('~\s*([:;{},])\s*~', '\1', preg_replace('~/\*.*\*/~sU', '', $file)));
}

function minify_js($file) {
	if (function_exists('jsShrink')) {
		$file = jsShrink($file);
	}
	return lzw_compress($file);
}

function compile_file($match) {
	global $project;
	$file = "";
	list(, $filenames, $callback) = $match;
	if ($filenames != "") {
		foreach (explode(";", $filenames) as $filename) {
			$file .= file_get_contents(dirname(__FILE__) . "/$project/$filename");
		}
	}
	if ($callback) {
		$file = call_user_func($callback, $file);
	}
	return '"' . add_quo_slashes($file) . '"';
}

if (!function_exists("each")) {
	function each(&$arr) {
		$key = key($arr);
		next($arr);
		return $key === null ? false : array($key, $arr[$key]);
	}
}

function min_version() {
	return true;
}

function number_type() {
	return '';
}

$project = "adminer";
array_shift($argv);

if ($argv[0] == "editor") {
	$project = "editor";
	array_shift($argv);
}

$selected_drivers = [];
if ($argv) {
	$params = explode(",", $argv[0]);
	if (file_exists(dirname(__FILE__) . "/adminer/drivers/" . $params[0] . ".inc.php")) {
		$selected_drivers = $params;
		array_shift($argv);
	}
}
$single_driver = count($selected_drivers) == 1 ? $selected_drivers[0] : null;

$selected_languages = [];
if ($argv) {
	$params = explode(",", $argv[0]);
	if (file_exists(dirname(__FILE__) . "/adminer/lang/" . $params[0] . ".inc.php")) {
		$selected_languages = $params;
		array_shift($argv);
	}
}
$single_language = count($selected_languages) == 1 ? $selected_languages[0] : null;

if ($argv) {
	echo "Usage: php compile.php [editor] [driver] [language]\n";
	echo "Purpose: Compile adminer[-driver][-lang].php or editor[-driver][-lang].php.\n";
	exit(1);
}

// Check function definition in drivers.
$file = file_get_contents(dirname(__FILE__) . "/adminer/drivers/mysql.inc.php");
$file = preg_replace('~class Min_Driver.*\n\t}~sU', '', $file);
preg_match_all('~\bfunction ([^(]+)~', $file, $matches); //! respect context (extension, class)
$functions = array_combine($matches[1], $matches[0]);
//! do not warn about functions without declared support()
unset($functions["__construct"], $functions["__destruct"], $functions["set_charset"]);

foreach (glob(dirname(__FILE__) . "/adminer/drivers/*.inc.php") as $filename) {
	preg_match('~/([^/.]+)\.inc\.php$~', $filename, $matches);
	if ($matches[1] == "mysql" || ($selected_drivers && !in_array($matches[1], $selected_drivers))) {
		continue;
	}

	$file = file_get_contents($filename);
	foreach ($functions as $function) {
		if (!strpos($file, "$function(")) {
			fprintf(STDERR, "Missing $function in $filename\n");
		}
	}
}

include dirname(__FILE__) . "/adminer/include/pdo.inc.php";
include dirname(__FILE__) . "/adminer/include/driver.inc.php";

$features = ["call" => "routine", "dump", "event", "privileges", "procedure" => "routine", "processlist", "routine", "scheme", "sequence", "status", "trigger", "type", "user" => "privileges", "variables", "view"];
$lang_ids = []; // global variable simplifies usage in a callback functions

// Start with index.php.
$file = file_get_contents(dirname(__FILE__) . "/$project/index.php");

// Remove including source code for unsupported features in single-driver file.
if ($single_driver) {
	$_GET[$single_driver] = true; // to load the driver
	include_once dirname(__FILE__) . "/adminer/drivers/$single_driver.inc.php";

	foreach ($features as $key => $feature) {
		if (!support($feature)) {
			if (is_string($key)) {
				$feature = $key;
			}
			$file = str_replace("} elseif (isset(\$_GET[\"$feature\"])) {\n\tinclude \"./$feature.inc.php\";\n", "", $file);
		}
	}
	if (!support("routine")) {
		$file = str_replace("if (isset(\$_GET[\"callf\"])) {\n\t\$_GET[\"call\"] = \$_GET[\"callf\"];\n}\nif (isset(\$_GET[\"function\"])) {\n\t\$_GET[\"procedure\"] = \$_GET[\"function\"];\n}\n", "", $file);
	}
}

// Compile files included into the index.php.
$file = preg_replace_callback('~\b(include|require) "([^"]*)";~', 'put_file', $file);

// Remove including debug files.
$file = str_replace('include "../adminer/include/debug.inc.php";', '', $file);
$file = str_replace('include "../adminer/include/coverage.inc.php";', '', $file);

// Remove including unwanted drivers.
if ($selected_drivers) {
	$file = preg_replace_callback('~include "../adminer/drivers/([^.]+).*\n~', function ($match) use ($selected_drivers) {
		return in_array($match[1], $selected_drivers) ? $match[0] : "";
	}, $file);
}

// Compile files included into the bootstrap.inc.php.
$file = preg_replace_callback('~\b(include|require) "([^"]*)";~', 'put_file', $file);

if ($single_driver) {
	// Remove source code for unsupported features.
	foreach ($features as $feature) {
		if (!support($feature)) {
			$file = preg_replace("((\t*)" . preg_quote('if (support("' . $feature . '")') . ".*\n\\1\\})sU", '', $file);
		}
	}

	$file = preg_replace('(;\.\./vendor/vrana/jush/modules/jush-(?!textarea\.|txt\.|js\.|' . preg_quote($single_driver == "mysql" ? "sql" : $single_driver) . '\.)[^.]+.js)', '', $file);
	$file = preg_replace_callback('~doc_link\(array\((.*)\)\)~sU', function ($match) use ($single_driver) {
		list(, $links) = $match;
		$links = preg_replace("~'(?!(" . ($single_driver == "mysql" ? "sql|mariadb" : $single_driver) . ")')[^']*' => [^,]*,?~", '', $links);
		return (trim($links) ? "doc_link(array($links))" : "''");
	}, $file);

	//! strip doc_link() definition
}

if ($project == "editor") {
	$file = preg_replace('~;\.\./vendor/vrana/jush/jush\.css~', '', $file);
	$file = preg_replace('~compile_file\(\'\.\./(vendor/vrana/jush/modules/jush\.js|adminer/static/[^.]+\.gif)[^)]+\)~', "''", $file);
}

$file = preg_replace_callback("~lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])~s", 'replace_lang', $file);
$file = preg_replace_callback('~\b(include|require) "([^"]*\$LANG.inc.php)";~', 'put_file_lang', $file);

$file = str_replace("\r", "", $file);
$file = str_replace('<?php echo script_src("static/editing.js?" . filemtime("../adminer/static/editing.js")); ?>' . "\n", "", $file);
$file = preg_replace('~\s+echo script_src\("\.\./vendor/vrana/jush/modules/jush-(textarea|txt|js|\$jush)\.js"\);~', '', $file);
$file = str_replace('<link rel="stylesheet" type="text/css" href="../vendor/vrana/jush/jush.css">' . "\n", "", $file);
$file = preg_replace_callback("~compile_file\\('([^']+)'(?:, '([^']*)')?\\)~", 'compile_file', $file); // integrate static files
$replace = 'preg_replace("~\\\\\\\\?.*~", "", ME) . "?file=\1&version=' . substr(md5(microtime()), 0, 8) . '"';
$file = preg_replace('~\.\./adminer/static/(favicon\.ico)~', '<?php echo h(' . $replace . '); ?>', $file);
$file = preg_replace('~\.\./adminer/static/(default\.css)\?.*default.css"\);\s+\?>~', '<?php echo h(' . $replace . '); ?>', $file);
$file = preg_replace('~"\.\./adminer/static/(functions\.js)\?".*functions.js"\)~', $replace, $file);
$file = preg_replace('~\.\./adminer/static/([^\'"]*)~', '" . h(' . $replace . ') . "', $file);
$file = preg_replace('~"\.\./vendor/vrana/jush/modules/(jush\.js)"~', $replace, $file);
$file = preg_replace("~<\\?php\\s*\\?>\n?|\\?>\n?<\\?php~", '', $file);
$file = php_shrink($file);

@mkdir("temp", 0777, true);
$filename = "temp/$project"
	. (is_dev_version() ? "" : "-$VERSION")
	. ($single_driver ? "-$single_driver" : "")
	. ($single_language ? "-$single_language" : "")
	. ".php";

file_put_contents($filename, $file);
echo "$filename created (" . strlen($file) . " B).\n";
