<?php
function remove_lang($match) {
	$s = lang(strtr($match[2], array("\\'" => "'", "\\\\" => "\\")));
	return ($match[1] && $match[3] ? $s : "$match[1]'" . addcslashes($s, "\\'") . "'$match[3]");
}

function put_file($match) {
	$return = file_get_contents($match[4]);
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

error_reporting(E_ALL & ~E_NOTICE);
if ($_SERVER["argc"] > 1) {
	include "./lang.inc.php";
	if ($_SERVER["argc"] != 2 || !in_array($_SERVER["argv"][1], lang())) {
		echo "Usage: php _compile.php [lang]\nPurpose: Compile phpMinAdmin[-lang].php from index.php.\n";
		exit(1);
	}
	$_SESSION["lang"] = $_SERVER["argv"][1];
}
$filename = "phpMinAdmin.php";
$file = file_get_contents("index.php");
if ($_SESSION["lang"]) {
	$filename = "phpMinAdmin-$_SESSION[lang].php";
	$file = str_replace("include \"./lang.inc.php\";\n", "", $file);
}
$file = preg_replace_callback('~(<\\?php)?\\s*(include|require)(_once)? "([^"]*)";(\\s*\\?>)?~', 'put_file', $file);
if ($_SESSION["lang"]) {
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']*|\\\\.)+)'\\)(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("<?php switch_lang(); ?>\n", "", $file);
	$file = str_replace("<?php echo get_lang(); ?>", $_SESSION["lang"], $file);
}
//! remove spaces and comments
file_put_contents($filename, $file);
echo "$filename created.\n";
