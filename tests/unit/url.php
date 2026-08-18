#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/functions.inc.php";

// Test url_escape(), bracket_escape(), relative_uri() and remove_from_uri().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

// characters which url_escape() leaves verbatim; + is not among them, it is the escaped space
const VERBATIM = "!\$()*,-./0123456789:;@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~";

$errors = 0;

function error(string $message): void {
	global $errors;
	echo "$message\n";
	$errors++;
}

$strings = array(
	"simple",
	"SELECT * FROM t WHERE a = 'x' AND b <= 1; -- c#d",
	"a&b",
	"a+b",
	"a b",
	"100%",
	"a=b",
	"a?b",
	"a;b",
	"a:b",
	"a[b]c",
	"a/b\\c",
	"a{b}c|d^e`f",
	"a,b\$c@d",
	'"quoted"',
	"<tag>",
	"a\tb",
	"a\r\nb",
	"\0",
	"\x7F",
	"příliš žluťoučký kůň",
	"🎉",
);

// every byte must be escaped exactly if it is not verbatim and must survive the round trip
for ($i = 0; $i < 256; $i++) {
	$char = chr($i);
	$escaped = url_escape($char);
	$expected = (strpos(VERBATIM, $char) !== false ? $char : ($char == " " ? "+" : sprintf("%%%02X", $i)));
	if ($escaped !== $expected) {
		error("Byte $i escapes to $escaped instead of $expected");
	}
	if (urldecode($escaped) !== $char) {
		error("Byte $i doesn't survive the round trip: $escaped");
	}
}

foreach (array_merge($strings, array("")) as $string) {
	$escaped = url_escape($string);

	// the value must be readable back from the query string
	parse_str("a=$escaped", $parsed);
	if (idx($parsed, "a", "") !== $string) {
		error("Value " . json_encode($string) . " results in " . json_encode(idx($parsed, "a")) . " ($escaped)");
	}

	// no character significant in the query string may be left verbatim; % starts an escape sequence, + is the escaped space
	if (strspn($escaped, VERBATIM . "%+") != strlen($escaped)) {
		error("Value " . json_encode($string) . " escapes to a string with an unexpected character: $escaped");
	}

	// bracket_escape() must be reversible
	if (bracket_escape(bracket_escape($string), true) !== $string) { // true - back
		error("Value " . json_encode($string) . " doesn't survive bracket_escape()");
	}
}

foreach ($strings as $string) {
	if (strpos($string, "\0") !== false) {
		continue; // PHP mangles a parameter name containing a null byte no matter how it is escaped
	}
	// the escaped value must be usable as a parameter name inside brackets
	$key = url_escape(bracket_escape($string));
	parse_str("where[$key]=1", $parsed);
	$keys = array_keys((array) idx($parsed, "where"));
	if (count($keys) != 1 || bracket_escape($keys[0], true) !== $string) { // true - back
		error("Value " . json_encode($string) . " results in the key " . json_encode(idx($keys, 0)) . " (where[$key])");
	}
}

// bracket_escape() alone must remove '=' which would end the parameter name
if (strpos(bracket_escape("a=b"), '=') !== false) {
	error("bracket_escape() doesn't escape '=': " . bracket_escape("a=b"));
}

// the hexadecimal digits must be upper case, otherwise the URL wouldn't match REQUEST_URI
if (url_escape("&") !== "%26" || url_escape("\xC3\xA9") !== "%C3%A9") {
	error("Escaped characters are not upper case: " . url_escape("&\xC3\xA9"));
}

// select.inc.php embeds the row identifier in the val[] parameter name
// the name is escaped by bracket_escape() and then by the browser, PHP decodes it once and then parses the brackets
$unique_idf = "&where[" . url_escape(bracket_escape("a=b[c]")) . "]=" . url_escape("x&y z");
$idf = bracket_escape($unique_idf);
parse_str(urlencode("val[$idf][col]") . "=3", $parsed);
if (idx(idx(idx($parsed, "val"), $idf), "col") !== "3") {
	error("The row identifier is not preserved in val[]: " . json_encode($parsed));
}
parse_str(bracket_escape($idf, true), $parsed); // true - back; where_check() without a driver
if (bracket_escape(key((array) idx($parsed, "where")), true) !== "a=b[c]" || first((array) idx($parsed, "where")) !== "x&y z") {
	error("The row identifier doesn't convert back to a condition: " . json_encode($parsed));
}

// relative_uri() must escape ':' only in the path
$_SERVER["REQUEST_URI"] = "/dir/adminer.php?sql=SELECT+a::text+FROM+t&db=x";
if (relative_uri() !== "adminer.php?sql=SELECT+a::text+FROM+t&db=x") {
	error("relative_uri() mangles the query string: " . relative_uri());
}
$_SERVER["REQUEST_URI"] = "/dir/a:b.php?db=x";
if (relative_uri() !== "a%3Ab.php?db=x") {
	error("relative_uri() doesn't escape ':' in the file name: " . relative_uri());
}

// remove_from_uri() must not be confused by an escaped '?' or '=' in a value
if (!defined("SID")) {
	define('SID', "");
}
$_SERVER["REQUEST_URI"] = "/adminer.php?sql=a%3Fpage%3D9&db=x&page=2";
if (remove_from_uri("page|next") !== "adminer.php?sql=a%3Fpage%3D9&db=x") {
	error("remove_from_uri() removes a parameter from a value: " . remove_from_uri("page|next"));
}

exit($errors ? 1 : 0);
