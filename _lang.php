<?php
if ($_SERVER["argc"] > 1) {
	echo "Usage: php _lang.php\nPurpose: Update lang.inc.php from source code messages.\n";
	exit(1);
}

$messages_all = array();
foreach (glob("*.php") as $filename) {
	$file = file_get_contents($filename);
	if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) {
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

$file = file_get_contents("lang.inc.php");
preg_match_all("~\n\t\t'(.*)' => array\\(\n(.*\n)\t\t\\)~sU", $file, $translations, PREG_OFFSET_CAPTURE);
foreach (array_reverse($translations[2], true) as $key => $translation) {
	$messages = $messages_all;
	preg_match_all("~^(\\s*)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", $translation[0], $matches, PREG_SET_ORDER);
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
			$s .= "\t\t\t$idf => array(),\n";
		} elseif ($translations[1][$key][0] != 'en') {
			$s .= "\t\t\t$idf => '',\n";
		}
	}
	$file = substr_replace($file, $s, $translation[1], strlen($translation[0]));
}
file_put_contents("lang.inc.php", $file);
echo "lang.inc.php modified.\n";
