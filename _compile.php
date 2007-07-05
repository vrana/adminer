<?php
function remove_lang($match) {
	global $LANG;
	return lang(strtr($match[2], array("\\'" => "'", "\\\\" => "\\")));
}

function put_file($match) {
	$return = file_get_contents($match[4]);
	$return = preg_replace("~\\?>?\n?\$~", '', $return);
	if (substr_count($return, "<?php") - substr_count($return, "?>") <= 0 && !$match[5]) {
		$return .= "<?php\n";
	}
	$return = preg_replace('~^<\\?php\\s+~', '', $return, 1, $count);
	if (!$count && !$match[1]) {
		$return = "?>\n$return";
	}
	return $return;
}

error_reporting(E_ALL & ~E_NOTICE);
$file = file_get_contents("index.php");
$LANG = (strlen($_SERVER["argv"][1]) == 2 ? $_SERVER["argv"][1] : "");
if ($LANG) {
	$file = str_replace("include \"./lang.inc.php\";\n", "", $file);
}
$file = preg_replace_callback('~(<\\?php\\s*)?(include|require)(_once)? "([^"]*)";(\\s*\\?>)?~', 'put_file', $file);
if ($LANG) {
	include "./lang.inc.php";
	preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']*|\\\\.)+)'\\)(;\\s*\\?>)?~s", 'remove_lang', $file);
}
//! remove spaces and comments
file_put_contents("phpMinAdmin" . ($LANG ? "-$LANG" : "") . ".php", $file);
echo "phpMinAdmin.php created.\n";
