#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/html.inc.php";

// Test shorten_utf8().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

const ELLIPSIS = "<i>…</i>";

$errors = 0;

function check(string $string, int $length, string $expected, string $suffix = ""): void {
	global $errors;
	$shortened = shorten_utf8($string, $length, $suffix);
	if ($shortened !== $expected) {
		echo "Value " . json_encode($string) . " shortens to " . json_encode($shortened) . " instead of " . json_encode($expected) . "\n";
		$errors++;
	}
}

// a string fitting in the length is returned as is
check("a\nb\nc", 100, "a\nb\nc");
check("a\nb\n", 4, "a\nb\n"); // the trailing newline must not be treated as the start of the last line
check("", 10, "");

// a shortened single-line string ends by the ellipsis
check("one line that is long", 8, "one line" . ELLIPSIS);
check("SELECT a\nFROM b", 8, "SELECT a" . ELLIPSIS); // the shortened string is single-line even if the original is not
check("a\nb", 0, ELLIPSIS);

// the ellipsis replaces the whole last line of a shortened multi-line string
check("SELECT a\nFROM b\nWHERE c = 1 AND d = 2", 20, "SELECT a\nFROM b\n" . ELLIPSIS);
check("a\nb\nc", 3, "a\n" . ELLIPSIS); // shortened exactly at the line break
check("SELECT a\nFROM b", 9, "SELECT a\n" . ELLIPSIS); // the shortened string already ends by a newline
check("a\nb\nc", 4, "a\nb\n" . ELLIPSIS); // the shortened string ends by a newline and holds another one
check("\nabc def", 5, "\n" . ELLIPSIS); // the last line is also the first one
check("SELECT a\r\nFROM b\r\nWHERE x", 15, "SELECT a\r\n" . ELLIPSIS); // CRLF

// the length is in characters, not in bytes
check("příliš\nžluťoučký kůň", 10, "příliš\n" . ELLIPSIS);
check("příliš žluťoučký", 7, "příliš " . ELLIPSIS);

// an invalid UTF-8 string is shortened by the ASCII fallback
check("a\n\xFF\xFEb c d", 4, "a\n" . ELLIPSIS);

// the result is escaped and the suffix is placed before the ellipsis
check("<b>\nx & y z", 8, "&lt;b>\n" . ELLIPSIS);
check("a\nb c", 3, "a\n</code>" . ELLIPSIS, "</code>");
check("a b", 10, "a b</code>", "</code>");

exit($errors ? 1 : 0);
