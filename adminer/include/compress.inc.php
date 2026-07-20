<?php
namespace Adminer;

// this file is used only in compilation, decompress_string() is in decompress.inc.php; requires the zlib extension

/** Compress string with deflate to characters from compress_alphabet(), tested by tests/compress.php */
function compress_string(string $string): string {
	$binary = ($string != "" ? gzdeflate($string, 9) : "");
	// convert bytes to string; 2 chars from a 93-symbol alphabet hold 13 bits
	$alphabet = compress_alphabet();
	$return = "";
	$rest = 0;
	$rest_length = 0;
	for ($i=0; $i < strlen($binary); $i++) {
		$rest = ($rest << 8) + ord($binary[$i]);
		$rest_length += 8;
		if ($rest_length >= 13) {
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
	return ($binary != "" ? $alphabet[$padding] . $return : "");
}
