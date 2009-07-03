<?php
error_reporting(4343); // errors and warnings
if ($_SERVER["argc"] > 1) {
	$_COOKIE["adminer_lang"] = $_SERVER["argv"][1]; // Adminer functions read language from cookie
	include dirname(__FILE__) . "/adminer/include/lang.inc.php";
	if ($_SERVER["argc"] != 2 || !isset($langs[$_COOKIE["adminer_lang"]])) {
		echo "Usage: php lang.php [lang]\nPurpose: Update adminer/lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

$messages_all = array();
foreach (array_merge(glob(dirname(__FILE__) . "/adminer/*.php"), glob(dirname(__FILE__) . "/adminer/include/*.php")) as $filename) {
	$file = file_get_contents($filename);
	if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) { // lang() always uses apostrophes
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

foreach (glob(dirname(__FILE__) . "/adminer/lang/" . ($_COOKIE["adminer_lang"] ? $_COOKIE["adminer_lang"] : "*") . ".inc.php") as $filename) {
	$messages = $messages_all;
	preg_match_all("~^(\\s*)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", file_get_contents($filename), $matches, PREG_SET_ORDER);
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
	fwrite(fopen($filename, "w"), "<?php\n\$translations = array(\n$s);\n"); // file_put_contents() since PHP 5
	echo "$filename updated.\n";
}
