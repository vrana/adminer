#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/functions.inc.php";

// Test format_number().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

$errors = 0;
$translations = array();

/** Get the translation from $translations instead of from adminer/lang/ */
function lang(string $idf): string {
	global $translations;
	return ($translations[$idf] ?: $idf);
}

/** Set the digit grouping and the digits used by the following checks */
function set_format(string $format, string $digits = '0123456789'): void {
	global $translations;
	$translations = array('#,##0' => $format, '0123456789' => $digits);
}

/** @param float|numeric-string $val */
function check($val, string $expected): void {
	global $errors;
	$formatted = format_number($val);
	if ($formatted !== $expected) {
		echo "Value " . json_encode($val) . " formats to " . json_encode($formatted) . " instead of " . json_encode($expected) . "\n";
		$errors++;
	}
}

// the English format groups by three digits
set_format('#,##0');
check(1234567890, "1,234,567,890");
check(1234567, "1,234,567");
check(1000, "1,000");
check(123, "123");
check(0, "0");
check(-1234, "-1,234"); // the separator must not be placed after the sign
check(-123, "-123");
check("1234567", "1,234,567"); // numeric string
check(1234.6, "1,235"); // the decimals are rounded

// a multi-byte separator, number_format() would use only its first byte in PHP 5.3
set_format('# ##0');
check(1234567, "1 234 567");
check(-1234, "-1 234");

// the Indian numbering system repeats the second group from the right
set_format('#,##,##0');
check(1234567890, "1,23,45,67,890");
check(1234567, "12,34,567");
check(100000, "1,00,000");
check(1000, "1,000");
check(100, "100");

// other group sizes
set_format('# ###0');
check(1234567890, "12 3456 7890");
check(1234567, "123 4567");

// the digits are transliterated after grouping
set_format('#,##,##0', '०१२३४५६७८९');
check(1234567, "१२,३४,५६७");

exit($errors ? 1 : 0);
