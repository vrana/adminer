#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/functions.inc.php";

// Test json_decode_exact(), json_scalar() and json_encode_exact().
// Prints found errors, prints nothing and exits with 0 if everything is OK.

$errors = 0;
$u = substr(json_encode("\1"), 1, -1); // the escape of U+0001, built here to not depend on how this file is edited

/** Check that a value survives decoding and encoding with the exact text, @ stands for the escape of U+0001 */
function check(string $json): void {
	global $errors, $u;
	$json = str_replace('@', $u, $json);
	$decoded = json_decode_exact($json);
	$encoded = ($decoded === null || is_scalar($decoded) ? json_encode(json_scalar($decoded), 64 | 256) : json_encode_exact($decoded, 64 | 256));
	if ($encoded !== $json) {
		echo "JSON " . $json . " is encoded as " . var_export($encoded, true) . "\n";
		$errors++;
	}
}

/** Check that a scalar is decoded to the expected PHP value */
function check_scalar(string $json, $expected): void {
	global $errors, $u;
	$actual = json_scalar(json_decode_exact(str_replace('@', $u, $json)));
	if ($actual !== $expected) {
		echo "JSON " . $json . " is decoded as " . var_export($actual, true) . " instead of " . var_export($expected, true) . "\n";
		$errors++;
	}
}

// numbers which a float would round or change
check('[18446744073709551615,-170141183460469231731687303715884105728,5]');
check('[123456789.123456789,12345678901234567890.123456789012345678]');
check('[-0,0,-0.0,1e300,2.5E-5,-1.5e+10,0.1]');
check('{"a":{"b":[1,{"c":0.30000000000000000001}]}}');

// objects stay objects
check('{}');
check('[{},[],{"0":"a","1":"b"}]');
check('{"5":"a"}');

// strings are not touched, also those looking like a marked number
check('["x","5","-1","1e5","x/y","č","tab\tq\"uote\\\\"]');
check('["@x","@5","@-1","@@y","x@","x@5"]');
check('{"@k":"@5","k":"a\"@5"}'); // the marker after an escaped quote is inside the string
check('["a\\\\","@5"]'); // an escaped backslash ends the string before the quote
check('"@7"');
check('"plain"');

// scalars
check_scalar('18446744073709551615', '18446744073709551615');
check_scalar('-0', '-0');
check_scalar('42', '42');
check_scalar('"@7"', "\1" . '7');
check_scalar('"@@7"', "\1\1" . '7');
check_scalar('"x"', 'x');
check_scalar('""', '');
check_scalar('true', true);
check_scalar('false', false);
check_scalar('null', null);

// invalid JSON
if (json_decode_exact('[1, 2') !== null || json_decode_exact('not JSON') !== null) {
	echo "Invalid JSON is not decoded as null\n";
	$errors++;
}

// long strings must not exceed the PCRE limits
foreach (array(str_repeat('a', 1e7), str_repeat('a\"', 3e6), str_repeat('\\\\', 3e6)) as $i => $long) {
	$decoded = json_decode_exact('["' . $long . '",' . "123456789.123456789]");
	if (!is_array($decoded) || strlen($decoded[0]) != strlen(json_decode('"' . $long . '"')) || json_scalar($decoded[1]) !== '123456789.123456789') {
		echo "Long string $i is not decoded, preg_last_error: " . preg_last_error() . "\n";
		$errors++;
	} elseif (json_encode_exact($decoded, 64 | 256) === '') {
		echo "Long string $i is not encoded, preg_last_error: " . preg_last_error() . "\n";
		$errors++;
	}
}

exit($errors ? 1 : 0);
