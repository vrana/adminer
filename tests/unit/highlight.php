#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/html.inc.php";

// Test highlight_matches() and its use by shorten_utf8().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

$errors = 0;

/** @param list<string> $patterns */
function check(string $string, array $patterns, string $expected): void {
	global $errors;
	$highlighted = highlight_matches($string, $patterns);
	if ($highlighted !== $expected) {
		echo "Value " . json_encode($string) . " highlights to " . json_encode($highlighted) . " instead of " . json_encode($expected) . "\n";
		$errors++;
	}
}

// the value is escaped, the searched text is matched in the value, not in its HTML
check("amp &amp; <b>", array('(?s:amp)'), "<mark>amp</mark> &amp;<mark>amp</mark>; &lt;b>");
check("a<b>c", array('(?s:<b>)'), "a<mark>&lt;b></mark>c");
check("abc", array(), "abc");
check("0", array('(?:0)'), "<mark>0</mark>");

// the flags are inline, the value is UTF-8
check("xAbcx abc", array('(?si:abc)'), "x<mark>Abc</mark>x <mark>abc</mark>");
check("xAbcx abc", array('(?s:abc)'), "xAbcx <mark>abc</mark>");
check("žluťoučký kůň", array('(?i:ŤOU)'), "žlu<mark>ťou</mark>čký kůň");
check("a\nb", array('(?s:a.*?b)'), "<mark>a\nb</mark>");

// an empty match is skipped, it doesn't hide a match of the next regular expression
check("aaa", array('(?:a*)'), "<mark>aaa</mark>");
check("bbb", array('(?:a*)'), "bbb");
check("ab", array('(?:x*)', '(?:b)'), "a<mark>b</mark>");

// the matches of all regular expressions don't overlap, the leftmost wins
check("abcdef", array('(?:abc)', '(?:cde)'), "<mark>abc</mark>def");
check("xabcx", array('(?:abc)', '(?:b)'), "x<mark>abc</mark>x");
check("abc", array('(?:abc)', '(?:abc)'), "<mark>abc</mark>");
check("ab ab", array('(?:a)', '(?:b)'), "<mark>a</mark><mark>b</mark> <mark>a</mark><mark>b</mark>");

// the regular expressions typed by the user
check("a|b", array('(?:a|b)'), "<mark>a</mark>|<mark>b</mark>");
check("a(b)c", array('(?:\(b\))'), "a<mark>(b)</mark>c");
check("aa xy", array('(?:(x)y)', '(?:(a)\1)'), "<mark>aa</mark> <mark>xy</mark>"); // a back-reference refers to the group of its own regular expression
check("abc", array('(?:abc)', '(?:a(b)'), "abc"); // an invalid one disables all

// shorten_utf8() highlights up to the ellipsis
$shortened = shorten_utf8("xxxxxxxxxxabcdef", 13, "", array('(?:abcdef)'));
if ($shortened !== "xxxxxxxxxx<mark>abc</mark><i>…</i>") {
	echo "A match crossing the ellipsis is highlighted as " . json_encode($shortened) . "\n";
	$errors++;
}
$shortened = shorten_utf8("abc\ndef\nghi", 9, "", array('(?:def|ghi)'));
if ($shortened !== "abc\n<mark>def</mark>\n<i>…</i>") {
	echo "A match in the line replaced by the ellipsis is highlighted as " . json_encode($shortened) . "\n";
	$errors++;
}

exit($errors ? 1 : 0);
