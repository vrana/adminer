<?php
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

function put_file($match) {
	if ($match[4] == './lang/$LANG.inc.php') {
		if ($_COOKIE["lang"]) {
			return "";
		}
		$return = "";
		foreach (glob("./lang/*.inc.php") as $filename) {
			$return .= "case '" . basename($filename, '.inc.php') . "': " . substr(file_get_contents($filename), 6) . "break;\n";
		}
		return "switch (\$LANG) {\n$return}\n";
	}
	$return = file_get_contents($match[4]);
	if ($match[4] == "./lang.inc.php" && $_COOKIE["lang"] && (preg_match("~case '$_COOKIE[lang]': (.*) break;~", $return, $match2) || preg_match("~default: (.*)~", $return, $match2))) {
		return "$match[1]\nfunction lang(\$ar, \$number) {\n\t$match2[1]\n\treturn sprintf(\$ar[\$pos], \$number);\n}\n$match[5]";
	}
	$return = preg_replace("~\\?>\n?\$~", '', $return);
	if (substr_count($return, "<?php") <= substr_count($return, "?>") && !$match[5]) {
		$return .= "<?php\n";
	}
	$return = preg_replace('~^<\\?php\\s+~', '', $return, 1, $count);
	if ($count) {
		$return = "\n$return";
	} elseif (!$match[1]) {
		$return = "?>\n$return";
	}
	return $return;
}

// Dgx's PHP shrinker
function php_shrink($input) {
	$set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
	$space = '';
	$output = '';
	foreach (token_get_all($input) as $token) {
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE) {
			$space = "\n";
		} else {
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
	include "./lang.inc.php";
	if ($_SERVER["argc"] != 2 || !isset($langs[$_COOKIE["lang"]])) {
		echo "Usage: php _compile.php [lang]\nPurpose: Compile phpMinAdmin[-lang].php from index.php.\n";
		exit(1);
	}
	include "./lang/$_COOKIE[lang].inc.php";
}

$filename = "phpMinAdmin" . ($_COOKIE["lang"] ? "-$_COOKIE[lang]" : "") . ".php";
$file = file_get_contents("index.php");
$file = preg_replace_callback('~(<\\?php)?\\s*(include|require)(_once)? "([^"]*)";(\\s*\\?>)?~', 'put_file', $file);
if ($_COOKIE["lang"]) {
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("<?php switch_lang(); ?>\n", "", $file);
	$file = str_replace('<?php echo $LANG; ?>', $_COOKIE["lang"], $file);
}
$file = str_replace("favicon.ico", '<?php echo preg_replace("~\\\\?.*~", "", $_SERVER["REQUEST_URI"]) . "?file=favicon.ico"; ?>', $file);
$file = str_replace("default.css", '<?php echo preg_replace("~\\\\?.*~", "", $_SERVER["REQUEST_URI"]) . "?file=default.css"; ?>', $file);
$file = str_replace("arrow.gif", '" . preg_replace("~\\\\?.*~", "", $_SERVER["REQUEST_URI"]) . "?file=arrow.gif', $file);
$file = str_replace('error_reporting(E_ALL & ~E_NOTICE);', 'error_reporting(E_ALL & ~E_NOTICE);
if (isset($_GET["file"])) {
	$last_modified = filemtime(__FILE__);
	if ($_SERVER["HTTP_IF_MODIFIED_SINCE"] && $last_modified <= strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
		header("HTTP/1.1 304 Not Modified");
	} else {
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", $last_modified) . " GMT");
		switch ($_GET["file"]) {
			case "favicon.ico":
				header("Content-Type: image/x-icon");
				echo base64_decode("' . base64_encode(file_get_contents("favicon.ico")) . '");
			break;
			case "default.css":
				header("Content-Type: text/css");
				?>' . file_get_contents("default.css") . '<?php
			break;
			default:
				header("Content-Type: image/gif");
				echo base64_decode("' . base64_encode(file_get_contents("arrow.gif")) . '");
			break;
		}
	}
	exit;
}', $file);
$file = php_shrink($file);
fwrite(fopen($filename, "w"), $file);
echo "$filename created.\n";
