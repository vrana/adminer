<?php
error_reporting(E_ALL & ~E_NOTICE);
if ($_SERVER["argc"] > 1) {
	$_COOKIE["lang"] = $_SERVER["argv"][1];
	include dirname(__FILE__) . "/adminer/include/lang.inc.php";
	if ($_SERVER["argc"] != 2 || !isset($langs[$_COOKIE["lang"]])) {
		echo "Usage: php lang.php [lang]\nPurpose: Update lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

$messages_all = array();
foreach (array_merge(glob(dirname(__FILE__) . "/adminer/*.php"), glob(dirname(__FILE__) . "/adminer/include/*.php")) as $filename) {
	$file = file_get_contents($filename);
	if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) {
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

foreach (glob(dirname(__FILE__) . "/adminer/lang/" . ($_COOKIE["lang"] ? $_COOKIE["lang"] : "*") . ".inc.php") as $filename) {
	$messages = $messages_all;
	preg_match_all("~^(\\s*)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", file_get_contents($filename), $matches, PREG_SET_ORDER);
	$s = "";
	foreach ($matches as $match) {
		if (isset($messages[$match[3]])) {
			$s .= "$match[1]$match[2],\n";
			unset($messages[$match[3]]);
		} else {
			$s .= "$match[1]// $match[2],\n";
		}
	}
	foreach($messages as $idf => $val) {
		if ($val == "," && strpos($idf, "%d")) {
			$s .= "\t$idf => array(),\n";
		} elseif (basename($filename) != "en.inc.php") {
			$s .= "\t$idf => null,\n";
		}
	}
	fwrite(fopen($filename, "w"), "<?php\n\$translations = array(\n$s);\n");
	echo "$filename updated.\n";
}
