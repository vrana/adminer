<?php
$langs = array(
	'en' => 'English',
	'cs' => 'Čeština', // Jakub Vrána - http://php.vrana.cz
	'sk' => 'Slovenčina', // Ivan Suchy - http://www.ivansuchy.com
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'es' => 'Español', // Klemens Häckel - http://clickdimension.wordpress.com
	'de' => 'Deutsch', // Klemens Häckel - http://clickdimension.wordpress.com
	'zh' => '简体中文', // Mr. Lodar
	'fr' => 'Français', // Francis Gagné
	'it' => 'Italiano', // Alessandro Fiorotto
	'et' => 'Eesti', // Priit Kallas
);

function lang($idf, $number = null) {
	global $LANG, $translations;
	$translation = $translations[$idf];
	if (is_array($translation) && $translation) {
		$pos = ($number == 1 ? 0 : 1);
		switch ($LANG) {
			case 'cs': $pos = ($number == 1 ? 0 : (!$number || $number >= 5 ? 2 : 1)); break;
			case 'sk': $pos = ($number == 1 ? 0 : (!$number || $number >= 5 ? 2 : 1)); break;
		}
		$translation = $translation[$pos];
	}
	$args = func_get_args();
	array_shift($args);
	return vsprintf((isset($translation) ? $translation : $idf), $args);
}

function switch_lang() {
	global $LANG, $langs;
	echo "<form action=''>\n<div id='lang'>";
	hidden_fields($_GET, array('lang'));
	echo lang('Language') . ": <select name='lang' onchange='this.form.submit();'>";
	foreach ($langs as $lang => $val) {
		echo "<option value='$lang'" . ($LANG == $lang ? " selected='selected'" : "") . ">$val</option>";
	}
	echo "</select>\n<noscript><div style='display: inline;'><input type='submit' value='" . lang('Use') . "' /></div></noscript>\n</div>\n</form>\n";
}

if (isset($_GET["lang"])) {
	$_COOKIE["lang"] = $_GET["lang"];
	$_SESSION["lang"] = $_GET["lang"];
}

$LANG = "en";
if (isset($langs[$_COOKIE["lang"]])) {
	setcookie("lang", $_GET["lang"], strtotime("+1 month"), preg_replace('~\\?.*~', '', $_SERVER["REQUEST_URI"]));
	$LANG = $_COOKIE["lang"];
} elseif (isset($langs[$_SESSION["lang"]])) {
	$LANG = $_SESSION["lang"];
} else {
	$accept_language = array();
	preg_match_all('~([-a-z_]+)(;q=([0-9.]+))?~', strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"]), $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$accept_language[str_replace("_", "-", $match[1])] = (isset($match[3]) ? $match[3] : 1);
	}
	arsort($accept_language);
	foreach ($accept_language as $lang => $q) {
		if (isset($langs[$lang])) {
			$LANG = $lang;
			break;
		}
		$lang = preg_replace('~-.*~', '', $lang);
		if (!isset($accept_language[$lang]) && isset($langs[$lang])) {
			$LANG = $lang;
			break;
		}
	}
}
