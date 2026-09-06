#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/functions.inc.php";

// Test format_number() and its JavaScript counterpart formatNumber().
// The JavaScript checks are skipped if Node.js is not available.
// Prints found errors, prints nothing and exits with 0 if everything is OK.

$errors = 0;
$translations = array();
/** @var list<array{string, string, string, string}> format, digits, value, expected result */
$cases = array();

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
	global $errors, $translations, $cases;
	$formatted = format_number($val);
	if ($formatted !== $expected) {
		echo "Value " . json_encode($val) . " formats to " . json_encode($formatted) . " instead of " . json_encode($expected) . "\n";
		$errors++;
	}
	if ($val == (int) $val) { // formatNumber() gets the number of selected items, it doesn't round like number_format()
		$cases[] = array($translations['#,##0'], $translations['0123456789'], (string) $val, $expected);
	}
}

/** Verify that formatNumber() in adminer/static/functions.js returns the same as format_number()
* @param list<array{string, string, string, string}> $cases
*/
function check_js(array $cases): void {
	global $errors;
	if (!preg_match('~^v\d~', (string) shell_exec("node --version 2>&1"))) {
		fwrite(STDERR, "Skipped the JavaScript checks, Node.js is not available.\n");
		return;
	}
	$js = file_get_contents(__DIR__ . "/../../adminer/static/functions.js");
	if (!preg_match('~/\*\* Group digits.*?\n}\n~s', $js, $match)) {
		echo "formatNumber() not found in functions.js\n";
		$errors++;
		return;
	}
	$file = tempnam(sys_get_temp_dir(), "adminer");
	// the function reads the globals printed by design.inc.php
	file_put_contents($file, "let numberFormat, numberDigits;\n$match[0]
const results = " . json_encode($cases) . ".map(function (case_) {
	numberFormat = case_[0];
	numberDigits = case_[1];
	return formatNumber(case_[2]);
});
process.stdout.write(JSON.stringify(results));
");
	$output = shell_exec("node " . escapeshellarg($file) . " 2>&1");
	unlink($file);
	$results = json_decode((string) $output, true);
	if (!is_array($results)) {
		echo "Node.js failed: " . trim((string) $output) . "\n";
		$errors++;
		return;
	}
	foreach ($cases as $key => $case) {
		if ($results[$key] !== $case[3]) {
			echo "JavaScript: value " . json_encode($case[2]) . " formats to " . json_encode($results[$key]) . " instead of " . json_encode($case[3]) . "\n";
			$errors++;
		}
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

check_js($cases);

exit($errors ? 1 : 0);
