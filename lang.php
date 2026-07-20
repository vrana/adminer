#!/usr/bin/env php
<?php
include __DIR__ . "/adminer/include/errors.inc.php";

$plugins = ($_SERVER["argv"][1] != "no-plugins");
if (!$plugins) {
	array_shift($_SERVER["argv"]);
}

unset($_COOKIE["adminer_lang"]);
$_SESSION["lang"] = $_SERVER["argv"][1]; // Adminer functions read language from session
if (isset($_SESSION["lang"])) {
	if (isset($_SERVER["argv"][2]) || !file_exists(__DIR__ . "/adminer/lang/$_SESSION[lang].inc.php")) {
		echo "Usage: php lang.php [no-plugins] [lang]\nPurpose: Update adminer/lang/*.inc.php from source code messages.\n";
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
	if ($plugins && $lang != "xx") {
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
		preg_match_all("~^(\\s*(?:// [^'].*\\s+)?)(?:// )?(('(?:[^\\\\']+|\\\\.)*') => (.*?[^,\n])),?( // .*)?$~m", $match[2][0], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		$s = "";
		$fullstop = ($lang == 'bn' || $lang == 'hi' ? '।' : (preg_match('~^(ja|zh)~', $lang) ? '。' : ($lang == 'he' ? '[^.]' : '\.')));
		foreach ($matches as $match) {
			list(, list($indent), list($line, $offset), list($en), list($translation)) = $match;
			$comment = (isset($match[5]) ? $match[5][0] : "");
			if (isset($messages[$en])) {
				// keep current messages
				$s .= "$indent$line,$comment\n";
				unset($messages[$en]);
				$en_fullstop = (substr($en, -2, 1) == ".");
				//! check in array
				if (!in_array($en, array('\'$1-$3-$5\'', "'[yyyy]-mm-dd'", "','")) && ($en_fullstop xor preg_match("~$fullstop'\)?\$~", $line))) {
					if ($lang != ($en_fullstop ? "ja" : "he")) { // fullstop is optional in 'ja', forbidden in 'he'
						echo "$filename:" . (substr_count($file, "\n", 0, $start + $offset) + 1) . ":Not matching fullstop: $line\n";
					}
				}
				foreach (placeholder_errors($lang, $en, $translation) as $error) {
					echo "$filename:" . (substr_count($file, "\n", 0, $start + $offset) + 1) . ":Placeholders: $error: $line\n";
				}
			} else {
				// comment deprecated messages
				$s .= "$indent// $line,$comment\n";
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

/** Check that printf placeholders in the translation match the English original
* @param string $en English original including apostrophes
* @param string $translation quoted string or array(...) of quoted plural forms
* @return list<string> found problems
*/
function placeholder_errors($lang, $en, $translation) {
	$errors = array();
	$spec = '%(\d+\$)?(?:\.\d+)?([dsf])'; // %2$s is positional, %.3f is used for time
	preg_match_all("~$spec~", $en, $match);
	$types = $match[2]; // types of arguments passed to lang()
	preg_match_all("~'(?:[^\\\\']+|\\\\.)*'~", $translation, $match);
	if (preg_match('~^array\(~', $translation)) {
		if (count($match[0]) != plural_forms($lang)) {
			$errors[] = "expected " . plural_forms($lang) . " plural forms";
		}
		if ($lang != "xx" && count(array_unique($match[0])) == 1) { // 'xx' is a template for new translations so it keeps the arrays
			$errors[] = 'identical plural forms'; // could be a plain string
		}
	}
	foreach ($match[0] as $single) {
		preg_match_all("~$spec~", $single, $specs, PREG_SET_ORDER);
		$seq = 0;
		$positional = 0;
		$missing = $types;
		foreach ($specs as $sp) {
			$pos = ($sp[1] != "" ? intval($sp[1]) : ++$seq);
			$positional += ($sp[1] != "");
			if ($pos > count($types)) {
				$errors[] = "extra %$sp[2]"; // would throw ValueError in vsprintf()
			} elseif ($types[$pos - 1] != $sp[2]) {
				$errors[] = "%$sp[2] instead of %" . $types[$pos - 1];
			}
			unset($missing[$pos - 1]);
		}
		if ($positional && $seq) {
			$errors[] = 'mixed positional and sequential placeholders'; // %s after %2$s would still print the first argument
		}
		if (array_diff($missing, array('d'))) {
			$errors[] = 'missing %s'; // %d may be omitted e.g. in singular forms
		}
		if (strpos(preg_replace(array("~$spec~", '~%%~'), '', $single), '%') !== false) {
			$errors[] = 'invalid %'; // '% d' prints the number with a space flag and eats the following letter
		}
	}
	return array_unique($errors);
}

/** Get the number of plural forms selected by lang_format()
* @return int
*/
function plural_forms($lang) {
	return ($lang == 'sl' ? 4 : (preg_match('~^(cs|sk|pl|lt|lv|bs|hr|ru|sr|uk)$~', $lang) ? 3 : 2));
}
