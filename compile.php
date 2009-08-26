<?php
include dirname(__FILE__) . "/adminer/include/version.inc.php";
include dirname(__FILE__) . "/externals/jsmin-php/jsmin.php";

function add_apo_slashes($s) {
	return addcslashes($s, "\\'");
}

function remove_lang($match) {
	global $translations;
	$idf = strtr($match[2], array("\\'" => "'", "\\\\" => "\\"));
	$s = ($translations[$idf] ? $translations[$idf] : $idf);
	if ($match[3] == ",") { // lang() has parameters
		return "$match[1]" . (is_array($s) ? "lang(array('" . implode("', '", array_map('add_apo_slashes', $s)) . "')," : "sprintf('" . add_apo_slashes($s) . "',");
	}
	return ($match[1] && $match[4] ? $s : "$match[1]'" . add_apo_slashes($s) . "'$match[4]");
}

$lang_ids = array(); // global variable simplifies usage in a callback function

function lang_ids($match) {
	global $lang_ids;
	$lang_id = &$lang_ids[stripslashes($match[1])];
	if (!isset($lang_id)) {
		$lang_id = count($lang_ids) - 1;
	}
	return ($_COOKIE["adminer_lang"] ? $match[0] : "lang($lang_id$match[2]");
}

function put_file($match) {
	global $project;
	if (basename($match[2]) == '$LANG.inc.php') {
		return $match[0]; // processed later
	}
	$return = file_get_contents(dirname(__FILE__) . "/$project/$match[2]");
	if (basename($match[2]) != "lang.inc.php" || !$_COOKIE["adminer_lang"]) {
		$tokens = token_get_all($return); // to find out the last token
		return "?>\n$return" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
	} elseif (preg_match('~\\s*(\\$pos = .*)~', $return, $match2)) {
		// single language lang() is used for plural
		return "function lang(\$translation, \$number) {\n\t" . str_replace('$LANG', "'$_COOKIE[adminer_lang]'", $match2[1]) . "\n\treturn sprintf(\$translation[\$pos], \$number);\n}\n";
	} else {
		echo "lang() not found\n";
	}
}

function put_file_lang($match) {
	global $lang_ids, $project;
	if ($_COOKIE["adminer_lang"]) {
		return "";
	}
	$return = "";
	foreach (glob(dirname(__FILE__) . "/adminer/lang/*.inc.php") as $filename) {
		include $filename; // assign $translations
		$translation_ids = array_flip($lang_ids); // default translation
		foreach ($translations as $key => $val) {
			if (isset($val)) {
				$translation_ids[$lang_ids[$key]] = $val;
			}
		}
		$return .= "\tcase \"" . basename($filename, '.inc.php') . '": $translations = array(';
		foreach ($translation_ids as $val) {
			$return .= (is_array($val) ? "array('" . implode("', '", array_map('add_apo_slashes', $val)) . "')" : "'" . add_apo_slashes($val) . "'") . ", ";
		}
		$return = substr($return, 0, -2) . "); break;\n";
	}
	return "switch (\$LANG) {\n$return}\n";
}

function short_identifier($number, $chars) {
	$return = '';
	while ($number >= 0) {
		$return .= $chars{$number % strlen($chars)};
		$number = floor($number / strlen($chars)) - 1;
	}
	return $return;
}

// based on http://latrine.dgx.cz/jak-zredukovat-php-skripty
function php_shrink($input) {
	$special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER'));
	static $short_variables = array();
	$shortening = true;
	$tokens = token_get_all($input);
	
	foreach ($tokens as $i => $token) {
		if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
			$short_variables[$token[1]]++;
		}
	}
	
	arsort($short_variables);
	foreach (array_keys($short_variables) as $number => $key) {
		$short_variables[$key] = short_identifier($number, implode("", range('a', 'z')) . '_' . implode("", range('A', 'Z'))); // could use also numbers and \x7f-\xff
	}
	
	$set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
	$space = '';
	$output = '';
	$in_echo = false;
	$doc_comment = false; // include only first /**
	for (reset($tokens); list($i, $token) = each($tokens); ) {
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
		&& strlen(addcslashes($tokens[$i+3][1], "'\\")) < strlen($tokens[$i+3][1]) + 3
		) {
			$tokens[$i+2] = array(T_ECHO, 'echo');
			$tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . addcslashes($tokens[$i+3][1], "'\\") . "'");
			$tokens[$i+4] = array(0, ';');
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
			$space = "\n";
		} else {
			if ($token[0] == T_DOC_COMMENT) {
				$doc_comment = true;
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
				if (in_array($tokens[$i+1][0], array(T_WHITESPACE, T_COMMENT)) && $tokens[$i+2][0] === T_ECHO) {
					next($tokens);
					$i++;
				}
				if ($tokens[$i+1][0] === T_ECHO) {
					// join two consecutive echos
					next($tokens);
					$token[1] = '.'; //! join ''.'' and "".""
				} else {
					$in_echo = false;
				}
			} elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
				$token[1] = '$' . $short_variables[$token[1]];
			}
			if (isset($set[substr($output, -1)]) || isset($set[$token[1]{0}])) {
				$space = '';
			}
			$output .= $space . $token[1];
			$space = '';
		}
	}
	return $output;
}

function minify_css($file) {
	return preg_replace('~\\s*([:;{},])\\s*~', '\\1', $file);
}

function compile_file($match) {
	global $project;
	return call_user_func($match[2], file_get_contents(dirname(__FILE__) . "/$project/$match[1]"));
}

error_reporting(4343); // errors and warnings
$project = "adminer";
if (file_exists(dirname(__FILE__) . "/" . $_SERVER["argv"][1] . "/index.php")) {
	$project = $_SERVER["argv"][1];
	array_shift($_SERVER["argv"]);
}
$_COOKIE["adminer_lang"] = $_SERVER["argv"][1]; // Adminer functions read language from cookie
if (isset($_SERVER["argv"][1])) {
	include dirname(__FILE__) . "/adminer/include/lang.inc.php";
	if (isset($_SERVER["argv"][2]) || !isset($langs[$_COOKIE["adminer_lang"]])) {
		echo "Usage: php compile.php [adminer] [lang]\nPurpose: Compile adminer[-lang].php from adminer/index.php.\n";
		exit(1);
	}
	include dirname(__FILE__) . "/adminer/lang/$_COOKIE[adminer_lang].inc.php";
}

$file = file_get_contents(dirname(__FILE__) . "/$project/index.php");
$file = preg_replace_callback('~\\b(include|require) "([^"]*)";~', 'put_file', $file);
$file = str_replace('include "../adminer/include/coverage.inc.php";', '', $file);
$file = preg_replace_callback('~\\b(include|require) "([^"]*)";~', 'put_file', $file); // bootstrap.inc.php
$file = preg_replace_callback("~lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])~s", 'lang_ids', $file);
$file = preg_replace_callback('~\\b(include|require) "([^"]*\\$LANG.inc.php)";~', 'put_file_lang', $file);
if ($_COOKIE["adminer_lang"]) {
	// single language version
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("<?php switch_lang(); ?>\n", "", $file);
	$file = str_replace('<?php echo $LANG; ?>', $_COOKIE["adminer_lang"], $file);
}
$file = str_replace('<script type="text/javascript" src="editing.js"></script>' . "\n", "", $file);
$file = preg_replace_callback("~compile_file\\('([^']+)', '([^']+)'\\);~", 'compile_file', $file); // integrate static files
$replace = 'h(preg_replace("~\\\\\\\\?.*~", "", $_SERVER["REQUEST_URI"])) . "?file=\\1&amp;version=' . $VERSION;
$file = preg_replace('~\\.\\./adminer/(default\\.css|functions\\.js|favicon\\.ico)~', '<?php echo ' . $replace . '"; ?>', $file);
$file = preg_replace('~\\.\\./adminer/((plus|cross|up|down|arrow)\\.gif)~', '" . ' . $replace, $file);
$file = str_replace("../externals/jush/", "https://jush.svn.sourceforge.net/svnroot/jush/trunk/", $file); // mixed-content warning if Adminer runs on HTTPS and external files on HTTP
$file = preg_replace("~<\\?php\\s*\\?>\n?|\\?>\n?<\\?php~", '', $file);
$file = php_shrink($file);

$filename = $project . ($_COOKIE["adminer_lang"] ? "-$_COOKIE[adminer_lang]" : "") . ".php";
fwrite(fopen($filename, "w"), $file); // file_put_contents() since PHP 5
echo "$filename created.\n";
