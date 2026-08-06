#!/usr/bin/env php
<?php
namespace Adminer;

// Test that compress_string() output uses only compress_alphabet() characters, decompress_string() restores the original
// and the pure-PHP inflate() fallback matches gzinflate(); the fallback is checked only on a sample of the translations, it is slow.
// Prints found errors, prints nothing and exits with 0 if everything is OK.

$_SESSION["lang"] = "en"; // lang.inc.php reads the language from the session, the same as compile.php does

require __DIR__ . "/../adminer/include/errors.inc.php"; // mutes undefined array key in decompress_string()
require __DIR__ . "/../adminer/include/functions.inc.php"; // used by lang.inc.php
require __DIR__ . "/../adminer/include/decompress.inc.php";
require __DIR__ . "/../adminer/include/lang.inc.php"; // declares Lang::$translations filled by the translation files
require __DIR__ . "/../adminer/include/compress.inc.php";

$errors = 0;

function check(string $name, string $string, string $dictionary = "", bool $fallback = true): void {
	global $errors;
	$compressed = compress_string($string, $dictionary);
	if (strspn($compressed, compress_alphabet()) != strlen($compressed)) {
		echo "$name: compressed string contains a character outside of compress_alphabet()\n";
		$errors++;
	}
	if (decompress_string($compressed, $dictionary) !== $string) {
		echo "$name: decompressed string doesn't match the original\n";
		$errors++;
	}
	if (!$fallback) {
		return; // the pure-PHP inflate() is by orders of magnitude slower than zlib
	}
	foreach (array(0, 1, 9) as $level) { // level 0 stores uncompressed blocks
		$binary = ($dictionary != ""
			? deflate_add(deflate_init(ZLIB_ENCODING_RAW, array('level' => $level, 'dictionary' => $dictionary)), $string, ZLIB_FINISH)
			: gzdeflate($string, $level)
		);
		if (inflate($binary, $dictionary) !== $string) {
			echo "$name: inflate() of gzdeflate() level $level doesn't match the original\n";
			$errors++;
		}
	}
}

/** Join the translations the same way as the compiled version does - plural forms by tabs, messages by newlines
* @param array<string, string|list<string>|null> $translations
* @param array<string, string> $english default translations, the message itself
*/
function lang_translations(array $translations, array $english): string {
	foreach ($translations as $key => $val) {
		if ($val !== null) {
			$english[$key] = implode("\t", (array) $val);
		}
	}
	return implode("\n", $english);
}

$alphabet = compress_alphabet();
if (strlen($alphabet) != 93 || count(array_unique(str_split($alphabet))) != 93) {
	echo "compress_alphabet(): expected 93 unique characters\n";
	$errors++;
}
if (preg_match("([^\n!-~]|['\\\\])", $alphabet)) {
	echo "compress_alphabet(): contains a character which needs escaping in single-quoted PHP string or whitespace other than \\n\n";
	$errors++;
}

check("empty string", "");
check("single character", "a");
check("NUL byte", "\0");
check("special characters", "'\\\"\$\r\n\t");
check("repetitive string", str_repeat("abcabc", 100));
check("all bytes", implode(array_map('chr', range(0, 255))));

mt_srand(0);
for ($length = 1; $length < 300; $length++) { // short strings use padding in the last chunk
	$string = "";
	for ($i = 0; $i < $length; $i++) {
		$string .= chr(mt_rand(0, 255));
	}
	check("random binary of length $length", $string);
}
$string = "";
for ($i = 0; $i < 100000; $i++) {
	$string .= chr(mt_rand(0, 255));
}
check("long random binary", $string);

check("CSS file", file_get_contents(__DIR__ . "/../adminer/static/default.css"));
check("JS file", file_get_contents(__DIR__ . "/../adminer/static/functions.js"));

// the same text as the compiled version compresses, see get_lang_translations() in compile.php
$langs = array();
foreach (glob(__DIR__ . "/../adminer/lang/*.inc.php") as $filename) {
	Lang::$translations = array();
	include $filename;
	$langs[basename($filename, ".inc.php")] = Lang::$translations;
}
$english = array(); // the default translation of a message is the message itself, en.inc.php overrides only the plural forms
foreach ($langs as $translations) {
	foreach (array_keys($translations) as $key) {
		$english[$key] = $key;
	}
}

$dictionary = lang_translations($langs["en"], $english);
// the pure-PHP inflate() is slow so run it only on a sample: the largest translation, English compressed without a dictionary,
// Estonian which has the most in common with the dictionary, and different scripts; the other translations exercise the same code paths
$fallback_langs = array("bn", "en", "et", "ru", "zh");
foreach ($langs as $lang => $translations) {
	$lang_dictionary = ($lang != "en" ? $dictionary : ""); // the compiled version compresses English alone and the other translations against it
	check("$lang translations", lang_translations($translations, $english), $lang_dictionary, in_array($lang, $fallback_langs));
}

check("empty string with dictionary", "", $dictionary);
check("string with nothing in common with the dictionary", "abc", "xyz");
check("dictionary longer than 32 kB", str_repeat("abcabc", 100), str_repeat("x", 40000) . "abcabc"); // only the last 32 kB of the dictionary is used

exit($errors ? 1 : 0);
