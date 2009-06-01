<?php
include dirname(__FILE__) . "/include/version.inc.php";
include dirname(__FILE__) . "/externals/jsmin-php/jsmin.php";

function add_apo_slashes($s) {
	return addcslashes($s, "\\'");
}

function remove_lang($match) {
	global $translations;
	$idf = strtr($match[2], array("\\'" => "'", "\\\\" => "\\"));
	$s = ($translations[$idf] ? $translations[$idf] : $idf);
	if ($match[3] == ",") {
		return "$match[1]" . (is_array($s) ? "lang(array('" . implode("', '", array_map('add_apo_slashes', $s)) . "')," : "sprintf('" . add_apo_slashes($s) . "',");
	}
	return ($match[1] && $match[4] ? $s : "$match[1]'" . add_apo_slashes($s) . "'$match[4]");
}

$lang_ids = array();
function lang_ids($match) {
	global $lang_ids;
	return 'lang(' . $lang_ids[stripslashes($match[1])] . $match[2];
}

function put_file($match) {
	global $lang_ids;
	if ($match[2] == './lang/$LANG.inc.php') {
		if ($_COOKIE["lang"]) {
			return "";
		}
		$return = "";
		foreach (glob(dirname(__FILE__) . "/lang/*.inc.php") as $filename) {
			include $filename;
			foreach ($translations as $key => $val) {
				if (!isset($lang_ids[$key])) {
					$lang_ids[$key] = count($lang_ids);
				}
			}
		}
		foreach (glob(dirname(__FILE__) . "/lang/*.inc.php") as $filename) {
			include $filename;
			$translation_ids = array_flip($lang_ids);
			foreach ($translations as $key => $val) {
				$translation_ids[$lang_ids[$key]] = $val;
			}
			$return .= 'case "' . basename($filename, '.inc.php') . '": $translations = array(';
			foreach ($translation_ids as $val) {
				$return .= (is_array($val) ? "array('" . implode("', '", array_map('add_apo_slashes', $val)) . "')" : "'" . add_apo_slashes($val) . "'") . ", ";
			}
			$return = substr($return, 0, -2) . "); break;\n";
		}
		return "switch (\$LANG) {\n$return}\n";
	}
	$return = file_get_contents(dirname(__FILE__) . "/$match[2]");
	if ($match[2] != "./include/lang.inc.php" || !$_COOKIE["lang"]) {
		$tokens = token_get_all($return);
		return "?>\n$return" . (in_array($tokens[count($tokens) - 1][0], array(T_CLOSE_TAG, T_INLINE_HTML), true) ? "<?php" : "");
	} elseif (preg_match('~\\s*(\\$pos = .*)~', $return, $match2)) {
		return "function lang(\$translation, \$number) {\n\t" . str_replace('$LANG', "'$_COOKIE[lang]'", $match2[1]) . "\n\treturn sprintf(\$translation[\$pos], \$number);\n}\n";
	} else {
		echo "lang() not found\n";
	}
}

function short_identifier($number, $chars) {
	$return = '';
	while ($number >= 0) {
		$return .= $chars{$number % strlen($chars)};
		$number = floor($number / strlen($chars)) - 1;
	}
	return $return;
}

// based on Dgx's PHP shrinker
function php_shrink($input) {
	$special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER'));
	static $short_variables = array();
	$shortening = true;
	$special_functions = array_flip(array('Min_MySQLi', 'Min_MySQLResult', '__construct'));
	$defined_functions = array();
	static $short_functions = array();
	$tokens = token_get_all($input);
	
	foreach ($tokens as $i => $token) {
		if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
			$short_variables[$token[1]]++;
		} elseif ($token[0] === T_STRING && $tokens[$i+1] === '(' && !isset($special_functions[$token[1]])) {
			$short_functions[$token[1]]++;
			if ($tokens[$i-2][0] === T_FUNCTION) {
				$defined_functions[$token[1]] = true;
			}
		}
	}
	
	arsort($short_variables);
	foreach (array_keys($short_variables) as $number => $key) {
		$short_variables[$key] = short_identifier($number, implode("", range('a', 'z')) . '_' . implode("", range('A', 'Z'))); // could use also numbers and \x7f-\xff
	}
	arsort($short_functions);
	$number = 0;
	foreach ($short_functions as $key => $val) {
		if (isset($defined_functions[$key])) {
			do {
				$short_functions[$key] = short_identifier($number, implode("", range('a', 'z')));
				$number++;
			} while (isset($short_functions[$short_functions[$key]]));
		}
	}
	
	$set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
	$space = '';
	$output = '';
	$in_echo = false;
	for (reset($tokens); list($i, $token) = each($tokens); ) {
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE) {
			$space = "\n";
		} else {
			if ($token[0] == T_VAR) {
				$shortening = false;
			} elseif (!$shortening) {
				if ($token[1] == ';') {
					$shortening = true;
				}
			} elseif ($token[0] == T_ECHO) {
				$in_echo = true;
			} elseif ($token[1] == ';' && $in_echo) {
				$in_echo = false;
				if ($tokens[$i+1][0] === T_WHITESPACE && $tokens[$i+2][0] === T_ECHO) {
					next($tokens);
					next($tokens);
					$token[1] = '.'; //! join ''.'' and "".""
				}
			} elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
				$token[1] = '$' . $short_variables[$token[1]];
			} elseif ($token[0] === T_STRING && $tokens[$i+1] === '(' && isset($defined_functions[$token[1]])
			&& $tokens[$i-1][0] !== T_DOUBLE_COLON && $tokens[$i-2][0] !== T_NEW && $tokens[$i-2][1] !== '_result'
			) {
				$token[1] = $short_functions[$token[1]];
			} elseif ($token[0] == T_CONSTANT_ENCAPSED_STRING && (($tokens[$i-1] === '(' && in_array($tokens[$i-2][1], array('array_map', 'set_exception_handler'), true)) || $token[1] == "'normalize_enum'") && isset($defined_functions[substr($token[1], 1, -1)])) {
				$token[1] = "'" . $short_functions[substr($token[1], 1, -1)] . "'";
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

error_reporting(E_ALL & ~E_NOTICE);
if ($_SERVER["argc"] > 1) {
	$_COOKIE["lang"] = $_SERVER["argv"][1];
	include dirname(__FILE__) . "/include/lang.inc.php";
	if ($_SERVER["argc"] != 2 || !isset($langs[$_COOKIE["lang"]])) {
		echo "Usage: php _compile.php [lang]\nPurpose: Compile phpMinAdmin[-lang].php from index.php.\n";
		exit(1);
	}
	include dirname(__FILE__) . "/lang/$_COOKIE[lang].inc.php";
}

$filename = "phpMinAdmin" . ($_COOKIE["lang"] ? "-$_COOKIE[lang]" : "") . ".php";
$file = file_get_contents(dirname(__FILE__) . "/index.php");
$file = preg_replace_callback('~\\b(include|require) "([^"]*)";~', 'put_file', $file);
$file = preg_replace("~<\\?php\\s*\\?>|\\?>\n?<\\?php~", '', $file);
$file = preg_replace("~if \\(isset\\(\\\$_SESSION\\[\"coverage.*\n}\n| && !isset\\(\\\$_SESSION\\[\"coverage\"\\]\\)~sU", '', $file);
if ($_COOKIE["lang"]) {
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("<?php switch_lang(); ?>\n", "", $file);
	$file = str_replace('<?php echo $LANG; ?>', $_COOKIE["lang"], $file);
} else {
	$file = preg_replace_callback("~lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])~s", 'lang_ids', $file);
}
$replace = 'preg_replace("~\\\\\\\\?.*~", "", $_SERVER["REQUEST_URI"]) . "?file=\\0&amp;version=' . $VERSION;
$file = preg_replace('~default\\.css|functions\\.js|favicon\\.ico|(up|down|plus|cross)\\.gif~', '<?php echo ' . $replace . '"; ?>', $file);
$file = preg_replace('~arrow\\.gif~', '" . ' . $replace, $file);
$file = str_replace('error_reporting(E_ALL & ~E_NOTICE);', 'error_reporting(E_ALL & ~E_NOTICE);
if (isset($_GET["file"])) {
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	if ($_GET["file"] == "favicon.ico") {
		header("Content-Type: image/x-icon");
		echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/favicon.ico")) . '");
	} elseif ($_GET["file"] == "default.css") {
		header("Content-Type: text/css");
		?>' . preg_replace('~\\s*([:;{},])\\s*~', '\\1', file_get_contents(dirname(__FILE__) . "/default.css")) . '<?php
	} elseif ($_GET["file"] == "functions.js") {
		header("Content-Type: text/javascript");
		?>' . JSMin::minify(file_get_contents(dirname(__FILE__) . "/functions.js")) . '<?php
	} else {
		header("Content-Type: image/gif");
		switch ($_GET["file"]) {
			case "arrow.gif": echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/arrow.gif")) . '"); break;
			case "up.gif": echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/up.gif")) . '"); break;
			case "down.gif": echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/down.gif")) . '"); break;
			case "plus.gif": echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/plus.gif")) . '"); break;
			case "cross.gif": echo base64_decode("' . base64_encode(file_get_contents(dirname(__FILE__) . "/cross.gif")) . '"); break;
		}
	}
	exit;
}', $file);
$file = str_replace("externals/jush/", "http://jush.sourceforge.net/", $file);
$file = php_shrink($file);
fwrite(fopen($filename, "w"), $file);
echo "$filename created.\n";
