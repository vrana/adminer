<?php
namespace Adminer;

/** Get characters used in compressed string */
function compress_alphabet(): string {
	// this doesn't need escaping in single-quoted PHP string and survives stripping trailing whitespace
	return strtr(implode(range('"', '~')), "'\\", "!\n");
}

// used in compiled version
function decompress_string(string $string): string {
	// convert string to bytes; 2 chars from a 93-symbol alphabet hold 13 bits
	$alphabet = array_flip(str_split(compress_alphabet()));
	$length = strlen($string);
	$valid = ($length ? 13 * ($length - 1) / 2 - $alphabet[$string[0]] : 0); // number of data bits; first char stores the count of padding bits
	$binary = "";
	$rest = 0;
	$rest_length = 0;
	for ($i=1; $i < $length; $i += 2) {
		$rest = ($rest << 13) + $alphabet[$string[$i]] * 93 + $alphabet[$string[$i + 1]];
		$rest_length += 13;
		while ($rest_length >= 8 && $valid >= 8) {
			$rest_length -= 8;
			$valid -= 8;
			$binary .= chr($rest >> $rest_length);
			$rest &= (1 << $rest_length) - 1;
		}
	}
	if ($binary == "") {
		return "";
	}
	return (function_exists('gzinflate') ? gzinflate($binary) : inflate($binary));
}

/** Decompress a raw deflate stream (RFC 1951) */
function inflate(string $binary): string {
	// used when the zlib extension is missing
	$length_bases = array(3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 15, 17, 19, 23, 27, 31, 35, 43, 51, 59, 67, 83, 99, 115, 131, 163, 195, 227, 258);
	$length_extras = array(0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0);
	$dist_bases = array(1, 2, 3, 4, 5, 7, 9, 13, 17, 25, 33, 49, 65, 97, 129, 193, 257, 385, 513, 769, 1025, 1537, 2049, 3073, 4097, 6145, 8193, 12289, 16385, 24577);
	$dist_extras = array(0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13);
	$return = "";
	$pos = 0;
	do {
		$final = inflate_bits($binary, $pos, 1);
		$type = inflate_bits($binary, $pos, 2);
		if (!$type) { // uncompressed block
			$pos = ($pos + 7) & ~7; // skip to a byte boundary
			$length = inflate_bits($binary, $pos, 16);
			$pos += 16; // one's complement of the length
			$return .= substr($binary, $pos >> 3, $length);
			$pos += $length << 3;
		} else {
			if ($type == 1) { // fixed Huffman codes
				$lit_lengths = array_merge(array_fill(0, 144, 8), array_fill(0, 112, 9), array_fill(0, 24, 7), array_fill(0, 8, 8));
				$dist_lengths = array_fill(0, 30, 5);
			} else { // dynamic Huffman codes
				$lit_count = inflate_bits($binary, $pos, 5) + 257;
				$dist_count = inflate_bits($binary, $pos, 5) + 1;
				$order = array(16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15);
				$meta_lengths = array_fill(0, 19, 0);
				$meta_count = inflate_bits($binary, $pos, 4) + 4;
				for ($i = 0; $i < $meta_count; $i++) {
					$meta_lengths[$order[$i]] = inflate_bits($binary, $pos, 3);
				}
				$meta_table = inflate_table($meta_lengths);
				$lengths = array();
				while (count($lengths) < $lit_count + $dist_count) {
					$symbol = inflate_symbol($binary, $pos, $meta_table);
					if ($symbol == 16) {
						$lengths = array_merge($lengths, array_fill(0, inflate_bits($binary, $pos, 2) + 3, end($lengths)));
					} elseif ($symbol == 17) {
						$lengths = array_merge($lengths, array_fill(0, inflate_bits($binary, $pos, 3) + 3, 0));
					} elseif ($symbol == 18) {
						$lengths = array_merge($lengths, array_fill(0, inflate_bits($binary, $pos, 7) + 11, 0));
					} else {
						$lengths[] = $symbol;
					}
				}
				$lit_lengths = array_slice($lengths, 0, $lit_count);
				$dist_lengths = array_slice($lengths, $lit_count);
			}
			$lit_table = inflate_table($lit_lengths);
			$dist_table = inflate_table($dist_lengths);
			while (($symbol = inflate_symbol($binary, $pos, $lit_table)) != 256) {
				if ($symbol < 256) {
					$return .= chr($symbol);
				} else {
					$length = $length_bases[$symbol - 257] + inflate_bits($binary, $pos, $length_extras[$symbol - 257]);
					$dist_symbol = inflate_symbol($binary, $pos, $dist_table);
					$offset = strlen($return) - $dist_bases[$dist_symbol] - inflate_bits($binary, $pos, $dist_extras[$dist_symbol]);
					for ($i = 0; $i < $length; $i++) { // the copied area can overlap with the produced area
						$return .= $return[$offset + $i];
					}
				}
			}
		}
	} while (!$final);
	return $return;
}

/** Read the given number of bits, least significant first, and advance the bit position */
function inflate_bits(string $binary, int &$pos, int $count): int {
	$return = 0;
	for ($i = 0; $i < $count; $i++) {
		$return += ((ord($binary[$pos >> 3]) >> ($pos & 7)) & 1) << $i;
		$pos++;
	}
	return $return;
}

/** Create a canonical Huffman decoding table
* @param int[] $lengths code lengths indexed by symbol
* @return int[][] symbols indexed by code length and code
*/
function inflate_table(array $lengths): array {
	$table = array();
	$code = 0;
	for ($bits = 1; $bits <= max($lengths); $bits++) {
		foreach ($lengths as $symbol => $length) {
			if ($length == $bits) {
				$table[$bits][$code] = $symbol;
				$code++;
			}
		}
		$code <<= 1;
	}
	return $table;
}

/** Read one Huffman-coded symbol and advance the bit position
* @param int[][] $table
*/
function inflate_symbol(string $binary, int &$pos, array $table): int {
	$code = 0;
	$bits = 0;
	do {
		$code = ($code << 1) + inflate_bits($binary, $pos, 1);
		$bits++;
	} while (!isset($table[$bits][$code]));
	return $table[$bits][$code];
}
