#!/usr/bin/env php
<?php
include __DIR__ . "/adminer/include/errors.inc.php";

unset($_COOKIE["adminer_lang"]);
$_SESSION["lang"] = $_SERVER["argv"][1]; // Adminer functions read language from session
if (isset($_SESSION["lang"])) {
	include __DIR__ . "/adminer/include/lang.inc.php";
	if (isset($_SERVER["argv"][2]) || (!isset($langs[$_SESSION["lang"]]) && $_SESSION["lang"] != "xx")) {
		echo "Usage: php lang.php [lang]\nPurpose: Update adminer/lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

$messages_all = array();
foreach (
	array_merge(
		glob(__DIR__ . "/adminer/*.php"),
		glob(__DIR__ . "/adminer/include/*.php"),
		glob(__DIR__ . "/adminer/drivers/*.php"),
		glob(__DIR__ . "/editor/*.php"),
		glob(__DIR__ . "/editor/include/*.php")
	) as $filename
) {
	$file = file_get_contents($filename);
	if (preg_match_all("~lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) { // lang() always uses apostrophes
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

foreach (glob(__DIR__ . "/adminer/lang/" . ($_SESSION["lang"] ?: "*") . ".inc.php") as $filename) {
	$messages = $messages_all;
	$file = file_get_contents($filename);
	$file = str_replace("\r", "", $file);
	preg_match_all("~^(\\s*(?:// [^'].*\\s+)?)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => .*[^,\n]),?~m", $file, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
	$s = "";
	$lang = basename($filename, ".inc.php");
	$fullstop = ($lang == "bn" ? '।' : (substr($lang, 0, 2) == 'zh' ? '。' : ($lang == 'he' || $lang == 'ja' ? '' : '\.')));
	foreach ($matches as $match) {
		list(, list($indent), list($line, $offset), list($en)) = $match;
		if (isset($messages[$en])) {
			// keep current messages
			$s .= "$indent$line,\n";
			unset($messages[$en]);
			if ($en != "','" && $fullstop && (substr($en, -2, 1) == "." xor preg_match("~$fullstop'\)?\$~", $line))) {
				echo "$filename:" . (substr_count($file, "\n", 0, $offset) + 1) . ":Not matching fullstop: $line\n";
			}
		} else {
			// comment deprecated messages
			$s .= "$indent// $line,\n";
		}
	}
	if ($messages) {
		if (basename($filename) != "en.inc.php") {
			$s .= "\n";
		}
		foreach ($messages as $idf => $val) {
			// add new messages
			if ($val == "," && strpos($idf, "%d")) {
				$s .= "\t$idf => array(),\n";
			} elseif (basename($filename) != "en.inc.php") {
				$s .= "\t$idf => null,\n";
			}
		}
	}
	$s = "<?php\nnamespace Adminer;\n\n\$translations = array(\n$s);\n";
	if ($s != $file) {
		file_put_contents($filename, $s);
		echo "$filename updated.\n";
	}
}
