#!/usr/bin/env php
<?php
namespace Adminer;

// Test that compress_string() output uses only compress_alphabet() characters, decompress_string() restores the original
// and the pure-PHP inflate() fallback matches gzinflate().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

require __DIR__ . "/../adminer/include/errors.inc.php"; // mutes undefined array key in decompress_string()
require __DIR__ . "/../adminer/include/decompress.inc.php";
require __DIR__ . "/../adminer/include/compress.inc.php";

$errors = 0;

function check(string $name, string $string, string $dictionary = ""): void {
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
$dictionary = file_get_contents(__DIR__ . "/../adminer/lang/en.inc.php"); // the compiled version compresses translations against English
foreach (glob(__DIR__ . "/../adminer/lang/*.inc.php") as $filename) {
	check(basename($filename), file_get_contents($filename));
	check(basename($filename) . " with dictionary", file_get_contents($filename), $dictionary);
}

check("empty string with dictionary", "", $dictionary);
check("string with nothing in common with the dictionary", "abc", "xyz");
check("dictionary longer than 32 kB", str_repeat("abcabc", 100), str_repeat("x", 40000) . "abcabc"); // only the last 32 kB of the dictionary is used

exit($errors ? 1 : 0);
