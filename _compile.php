<?php
function add_apo_slashes($s) {
	return addcslashes($s, "\\'");
}

function remove_lang($match) {
	$s = lang(strtr($match[2], array("\\'" => "'", "\\\\" => "\\")), false);
	if ($match[3] == ",") {
		return "$match[1]" . (is_array($s) ? "lang(array('" . implode("', '", array_map('add_apo_slashes', $s)) . "')," : "sprintf('" . add_apo_slashes($s) . "',");
	}
	return ($match[1] && $match[4] ? $s : "$match[1]'" . add_apo_slashes($s) . "'$match[4]");
}

function put_file($match) {
	$return = file_get_contents($match[4]);
	if ($match[4] == "./lang.inc.php") {
		if (!$_SESSION["lang"]) {
			$return = str_replace("\tif (\$number === false) { // used in _compile.php\n\t\treturn (\$translation ? \$translation : \$idf);\n\t}\n", "", $return);
		} elseif (preg_match("~case '$_SESSION[lang]': (.*) break;~", $return, $match2) || preg_match("~default: (.*)~", $return, $match2)) {
			return "$match[1]\nfunction lang(\$ar, \$number) {\n\t$match2[1]\n\treturn \$ar[\$pos];\n}\n$match[5]";
		}
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

error_reporting(E_ALL & ~E_NOTICE);
if ($_SERVER["argc"] > 1) {
	$_SESSION["lang"] = $_SERVER["argv"][1];
	include "./lang.inc.php";
	if ($_SERVER["argc"] != 2 || !in_array($_SERVER["argv"][1], lang())) {
		echo "Usage: php _compile.php [lang]\nPurpose: Compile phpMinAdmin[-lang].php from index.php.\n";
		exit(1);
	}
}
$filename = "phpMinAdmin.php";
$file = file_get_contents("index.php");
if ($_SESSION["lang"]) {
	$filename = "phpMinAdmin-$_SESSION[lang].php";
}
$file = preg_replace_callback('~(<\\?php)?\\s*(include|require)(_once)? "([^"]*)";(\\s*\\?>)?~', 'put_file', $file);
if ($_SESSION["lang"]) {
	$file = preg_replace_callback("~(<\\?php\\s*echo )?lang\\('((?:[^\\\\']+|\\\\.)*)'([,)])(;\\s*\\?>)?~s", 'remove_lang', $file);
	$file = str_replace("<?php switch_lang(); ?>\n", "", $file);
	$file = str_replace("<?php echo get_lang(); ?>", $_SESSION["lang"], $file);
}
file_put_contents($filename, $file);
echo "$filename created.\n";
