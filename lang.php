<?php
error_reporting(4343); // errors and warnings
$project = "adminer";
if (file_exists($_SERVER["argv"][1] . "/index.php")) {
	$project = $_SERVER["argv"][1];
	array_shift($_SERVER["argv"]);
}
if (isset($_SERVER["argv"][1])) {
	$_COOKIE["adminer_lang"] = $_SERVER["argv"][1]; // Adminer functions read language from cookie
	include dirname(__FILE__) . "/adminer/include/lang.inc.php";
	if (isset($_SERVER["argv"][2]) || !isset($langs[$_COOKIE["adminer_lang"]])) {
		echo "Usage: php lang.php [adminer] [lang]\nPurpose: Update adminer/lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

preg_match_all('~\\b(include|require) "([^"]*)";~', file_get_contents(dirname(__FILE__) . "/$project/index.php") . file_get_contents(dirname(__FILE__) . "/adminer/include/bootstrap.inc.php"), $matches);
$filenames = $matches[2];
$filenames[] = "index.php";

$messages_all = array();
foreach ($filenames as $filename) {
	$filename = dirname(__FILE__) . "/$project/$filename";
	if (basename($filename) != '$LANG.inc.php') {
		$file = file_get_contents($filename);
		if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) { // lang() always uses apostrophes
			$messages_all += array_combine($matches[1], $matches[2]);
		}
	}
}

//! get translations of new Editor messages from Adminer

foreach (glob(dirname(__FILE__) . "/$project/lang/" . ($_COOKIE["adminer_lang"] ? $_COOKIE["adminer_lang"] : "*") . ".inc.php") as $filename) {
	$messages = $messages_all;
	$file = file_get_contents($filename);
	preg_match_all("~^(\\s*)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", $file, $matches, PREG_SET_ORDER);
	$s = "";
	foreach ($matches as $match) {
		if (isset($messages[$match[3]])) {
			// keep current messages
			$s .= "$match[1]$match[2],\n";
			unset($messages[$match[3]]);
		} else {
			// comment deprecated messages
			$s .= "$match[1]// $match[2],\n";
		}
	}
	foreach($messages as $idf => $val) {
		// add new messages
		if ($val == "," && strpos($idf, "%d")) {
			$s .= "\t$idf => array(),\n";
		} elseif (basename($filename) != "en.inc.php") {
			$s .= "\t$idf => null,\n";
		}
	}
	$s = "<?php\n\$translations = array(\n$s);\n";
	if ($s != $file) {
		fwrite(fopen($filename, "w"), $s); // file_put_contents() since PHP 5
		echo "$filename updated.\n";
	}
}
