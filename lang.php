#!/usr/bin/env php
<?php
include __DIR__ . "/adminer/include/errors.inc.php";

unset($_COOKIE["adminer_lang"]);
$_SESSION["lang"] = $_SERVER["argv"][1]; // Adminer functions read language from session
if (isset($_SESSION["lang"])) {
	if (isset($_SERVER["argv"][2]) || !file_exists(__DIR__ . "/adminer/lang/$_SESSION[lang].inc.php")) {
		echo "Usage: php lang.php [lang]\nPurpose: Update adminer/lang/*.inc.php from source code messages.\n";
		exit(1);
	}
}

$messages_all = array();
foreach (glob(__DIR__ . "/{adminer,adminer/include,adminer/drivers,editor,editor/include}/*.php", GLOB_BRACE) as $include) {
	$file = file_get_contents($include);
	if (preg_match_all("~[^>]lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches)) { // lang() always uses apostrophes
		$messages_all += array_combine($matches[1], $matches[2]);
	}
}

foreach (glob(__DIR__ . "/adminer/lang/" . ($_SESSION["lang"] ?: "*") . ".inc.php") as $filename) {
	$lang = basename($filename, ".inc.php");
	update_translations($lang, $messages_all, $filename, '~(\$translations = array\(\n)(.*\n)(?=\);)~sU');
	if ($lang != "xx") {
		foreach (glob(__DIR__ . "/plugins/*.php") as $filename) {
			$file = file_get_contents($filename);
			if (preg_match('~extends Adminer\\\\Plugin~', $file)) {
				preg_match_all("~\\\$this->lang\\(('(?:[^\\\\']+|\\\\.)*')([),])~", $file, $matches);
				$messages = array("''" => "") + array_combine($matches[1], $matches[2]);
				$file = preg_replace("~(\\\$translations = array\\((?!.*'$lang').*?)\t\\);~s", "\\1\t\t'$lang' => array(\n\t\t),\n\t);", $file);
				file_put_contents($filename, $file);
				update_translations($lang, $messages, $filename, "~(\\\$translations = array\\(.*'$lang' => array\\(\n)(.*)(?=^\t\t\\),)~msU", "\t\t\t");
			}
		}
	}
}

function update_translations($lang, $messages, $filename, $pattern, $tabs = "\t") {
	$file = file_get_contents($filename);
	$file = str_replace("\r", "", $file);
	$start = 0;
	$s = preg_replace_callback($pattern, function ($match) use ($lang, $messages, $filename, $file, $tabs, &$start) {
		$prefix = $match[1][0];
		$start = $match[2][1];
		preg_match_all("~^(\\s*(?:// [^'].*\\s+)?)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => (.*[^,\n])),?~m", $match[2][0], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		$s = "";
		$fullstop = ($lang == 'bn' || $lang == 'hi' ? '।' : (preg_match('~^(ja|zh)~', $lang) ? '。' : ($lang == 'he' ? '[^.]' : '\.')));
		foreach ($matches as $match) {
			list(, list($indent), list($line, $offset), list($en), list($translation)) = $match;
			if (isset($messages[$en])) {
				// keep current messages
				$s .= "$indent$line,\n";
				unset($messages[$en]);
				$en_fullstop = (substr($en, -2, 1) == ".");
				//! check in array
				if ($en != "','" && ($en_fullstop xor preg_match("~$fullstop'\)?\$~", $line))) {
					if ($lang != ($en_fullstop ? "ja" : "he")) { // fullstop is optional in 'ja', forbidden in 'he'
						echo "$filename:" . (substr_count($file, "\n", 0, $start + $offset) + 1) . ":Not matching fullstop: $line\n";
					}
				}
				if (preg_match('~%~', $en) xor preg_match('~%~', $translation)) {
					echo "$filename:" . (substr_count($file, "\n", 0, $start + $offset) + 1) . ":Not matching placeholder.\n";
				}
			} else {
				// comment deprecated messages
				$s .= "$indent// $line,\n";
			}
		}
		if ($messages) {
			$start += strlen($s);
			foreach ($messages as $idf => $val) {
				// add new messages
				if ($val == "," && strpos($idf, "%d")) {
					$s .= "$tabs$idf => array(),\n";
				} elseif ($lang != "en") {
					$s .= "$tabs$idf => null,\n";
				}
			}
		}
		return $prefix . $s;
	}, $file, -1, $count, PREG_OFFSET_CAPTURE);
	if ($s != $file) {
		$s = str_replace("array(\n\t\t\t'' => null,\n\t\t),", "array('' => null),", $s);
		file_put_contents($filename, $s);
		echo "$filename:" . (substr_count($s, "\n", 0, $start) + 1) . ":Updated.\n";
	}
}
