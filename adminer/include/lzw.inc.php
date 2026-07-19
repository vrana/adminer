<?php
namespace Adminer;

// this file is used only in compilation, lzw_decompress() is in functions.inc.php

/** Compress string to characters from lzw_alphabet(), tested by tests/lzw.php */
function lzw_compress(string $string): string {
	// compression
	$dictionary = array_flip(range("\0", "\xFF"));
	$word = "";
	$codes = array();
	for ($i=0; $i <= strlen($string); $i++) {
		$x = @$string[$i];
		if (strlen($x) && isset($dictionary[$word . $x])) {
			$word .= $x;
		} elseif ($i) {
			$codes[] = $dictionary[$word];
			$dictionary[$word . $x] = count($dictionary);
			$word = $x;
		}
	}
	// convert codes to string; 2 chars from a 93-symbol alphabet hold 13 bits
	$alphabet = lzw_alphabet();
	$dictionary_count = 256;
	$bits = 8; // ceil(log($dictionary_count, 2))
	$return = "";
	$rest = 0;
	$rest_length = 0;
	foreach ($codes as $code) {
		$rest = ($rest << $bits) + $code;
		$rest_length += $bits;
		$dictionary_count++;
		if ($dictionary_count >> $bits) {
			$bits++;
		}
		while ($rest_length >= 13) {
			$rest_length -= 13;
			$chunk = $rest >> $rest_length;
			$return .= $alphabet[(int) ($chunk / 93)] . $alphabet[$chunk % 93];
			$rest &= (1 << $rest_length) - 1;
		}
	}
	$padding = 0;
	if ($rest_length) {
		$padding = 13 - $rest_length;
		$chunk = $rest << $padding;
		$return .= $alphabet[(int) ($chunk / 93)] . $alphabet[$chunk % 93];
	}
	return ($codes ? $alphabet[$padding] . $return : "");
}
