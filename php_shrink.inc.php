<?php

/** Minify PHP code with these operations:
* remove extra {}
* minify variables
* strip comments, preserve only the first doc-comment
* join consecutive echo
* change ?>HTML<?php to echo 'HTML' if it saves space
* strip public visibility or change it to var
*
* @param string PHP code including <?php
* @return string
*/
function php_shrink($input) {
	// based on http://latrine.dgx.cz/jak-zredukovat-php-skripty
	$input = preg_replace("~<\\?php\\s*\\?>\n?|\\?>\n?<\\?php~", '', $input);
	$special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER', '$http_response_header', '$php_errormsg'));
	$short_variables = array();
	$shortening = true;
	$tokens = token_get_all($input);

	// remove unnecessary { }
	//! change also `while () { if () {;} }` to `while () if () ;` but be careful about `if () { if () { } } else { }
	$shorten = 0;
	$opening = -1;
	foreach ($tokens as $i => $token) {
		if (in_array($token[0], array(T_IF, T_ELSE, T_ELSEIF, T_WHILE, T_DO, T_FOR, T_FOREACH), true)) {
			$shorten = ($token[0] == T_FOR ? 4 : 2);
			$opening = -1;
		} elseif (in_array($token[0], array(T_SWITCH, T_FUNCTION, T_CLASS, T_CLOSE_TAG), true)) {
			$shorten = 0;
		} elseif ($token === ';') {
			$shorten--;
		} elseif ($token === '{') {
			if ($opening < 0) {
				$opening = $i;
			} elseif ($shorten > 1) {
				$shorten = 0;
			}
		} elseif ($token === '}' && $opening >= 0 && $shorten == 1) {
			unset($tokens[$opening]);
			unset($tokens[$i]);
			$shorten = 0;
			$opening = -1;
		}
	}
	$tokens = array_values($tokens);

	foreach ($tokens as $i => $token) {
		if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
			$short_variables[$token[1]]++;
		}
	}

	foreach ($tokens as $i => $token) {
		if (
			$tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
			&& strlen(addcslashes($tokens[$i+3][1], "\\'")) < strlen($tokens[$i+3][1]) + 3
		) {
			$tokens[$i+2] = array(T_ECHO, 'echo');
			$tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . addcslashes($tokens[$i+3][1], "\\'") . "'");
			$tokens[$i+4] = array(0, ';');
		}
	}

	arsort($short_variables);
	$chars = implode(range('a', 'z')) . '_' . implode(range('A', 'Z'));
	//! preserve variable names between versions if possible
	$short_variables2 = array_splice($short_variables, strlen($chars));
	ksort($short_variables);
	ksort($short_variables2);
	$short_variables += $short_variables2;
	foreach (array_keys($short_variables) as $number => $key) {
		$short_variables[$key] = short_identifier($number, $chars); // could use also numbers and \x7f-\xff
	}

	$set = array_flip(preg_split('//', '!"#$%&\'()*+,-./:;<=>?@[]^`{|}'));
	$space = '';
	$output = '';
	$in_echo = false;
	$doc_comment = false; // include only first /**
	$next_pos = 0;
	foreach ($tokens as $i => $token) {
		if ($i < $next_pos) {
			continue;
		}
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
			$space = "\n";
		} else {
			if ($token[0] == T_DOC_COMMENT) {
				$doc_comment = true;
			}
			if ($token[0] == T_VAR || $token[0] == T_PUBLIC || $token[0] == T_PROTECTED || $token[0] == T_PRIVATE) {
				if ($token[0] == T_PUBLIC) {
					$token[1] = ($tokens[$i+2][1][0] == '$' ? 'var' : '');
				}
				$shortening = false;
			} elseif (!$shortening) {
				if ($token[1] == ';' || $token[0] == T_FUNCTION) {
					$shortening = true;
				}
			} elseif ($token[0] == T_ECHO) {
				$in_echo = true;
			} elseif ($token[1] == ';' && $in_echo) {
				$next_echo = next_token($tokens, $i, T_ECHO, array(T_WHITESPACE, T_COMMENT));
				if ($next_echo) {
					// join two consecutive echos
					$next_pos = $next_echo + 1;
					$token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
				} else {
					$in_echo = false;
				}
			} elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
				$token[1] = '$' . $short_variables[$token[1]];
			}
			if (isset($set[substr($output, -1)]) || isset($set[$token[1][0]])) {
				$space = '';
			}
			$output .= $space . $token[1];
			$space = '';
		}
	}
	return $output;
}

function next_token($tokens, $i, $search, $allowed = array()) {
	for ($i += 1; in_array($tokens[$i][0], $allowed); $i++) {
	}
	return ($tokens[$i][0] === $search ? $i : 0);
}

function short_identifier($number, $chars) {
	$return = '';
	while ($number >= 0) {
		$return .= $chars[$number % strlen($chars)];
		$number = floor($number / strlen($chars)) - 1;
	}
	return $return;
}
