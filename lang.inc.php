<?php
static $translations = array(
	'en' => array(),
	'cs' => array(), // Jakub Vrána - http://php.vrana.cz
	'sk' => array(), // Ivan Suchy - http://www.ivansuchy.com
);

function lang($idf, $number = null) {
	global $LANG, $translations;
	$translation = $translations[$LANG][$idf];
	if (is_array($translation) && $translation) {
		switch ($LANG) {
			case 'cs': $pos = ($number == 1 ? 0 : (!$number || $number >= 5 ? 2 : 1)); break;
			case 'sk': $pos = ($number == 1 ? 0 : (!$number || $number >= 5 ? 2 : 1)); break;
			default: $pos = ($number == 1 ? 0 : 1);
		}
		$translation = $translation[$pos];
	}
	$args = func_get_args();
	array_shift($args);
	return vsprintf(($translation ? $translation : $idf), $args);
}

function switch_lang() {
	global $translations;
	echo "<p>" . lang('Language') . ":";
	$base = remove_from_uri("lang");
	foreach ($translations as $lang => $val) {
		echo ' <a href="' . htmlspecialchars($base . (strpos($base, "?") !== false ? "&" : "?")) . "lang=$lang\">$lang</a>";
	}
	echo "</p>\n";
}

if (isset($_GET["lang"])) {
	setcookie("lang", $_GET["lang"], strtotime("+1 month"), preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]));
	$_COOKIE["lang"] = $_GET["lang"];
	$_SESSION["lang"] = $_GET["lang"];
}

if (isset($translations[$_COOKIE["lang"]])) {
	$LANG = $_COOKIE["lang"];
} elseif (isset($translations[$_SESSION["lang"]])) {
	$LANG = $_SESSION["lang"];
} else {
	$accept_language = array();
	preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~', $_SERVER["HTTP_ACCEPT_LANGUAGE"], $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$accept_language[$match[1]] = ($match[3] ? $match[3] : 1);
	}
	arsort($accept_language);
	$LANG = "en";
	foreach ($accept_language as $lang => $q) {
		if (isset($translations[$lang])) {
			$LANG = $lang;
			break;
		}
		$lang = preg_replace('~-.*~', '', $LANG);
		if (!isset($accept_language[$lang]) && isset($translations[$lang])) {
			$LANG = $lang;
			break;
		}
	}
}
