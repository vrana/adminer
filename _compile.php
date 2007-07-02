<?php
function put_file($match) {
	//! exit on error with require, _once
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

$file = file_get_contents("index.php");
$file = preg_replace_callback('~(<\\?php\\s*)?(include|require)(_once)? "([^"]*)";(\\s*\\?>)?~', 'put_file', $file);
//! remove spaces and comments
file_put_contents("phpMinAdmin.php", $file);
echo "phpMinAdmin.php created.\n";
