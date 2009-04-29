<?php
$langs = array(
	'en' => 'English', // Jakub Vrána - http://php.vrana.cz
	'cs' => 'Čeština', // Jakub Vrána - http://php.vrana.cz
	'sk' => 'Slovenčina', // Ivan Suchy - http://www.ivansuchy.com
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'es' => 'Español', // Klemens Häckel - http://clickdimension.wordpress.com
	'de' => 'Deutsch', // Klemens Häckel - http://clickdimension.wordpress.com
	'zh' => '简体中文', // Mr. Lodar
	'fr' => 'Français', // Francis Gagné
	'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
	'et' => 'Eesti', // Priit Kallas
	'ru' => 'Русский язык', // Juraj Hajdúch
);

function lang($idf, $number = null) {
	global $LANG, $translations;
	$translation = $translations[$idf];
	if (is_array($translation) && $translation) {
		$translation = $translation[($number == 1 ? 0 : ((!$number || $number >= 5) && ereg('cs|sk|ru', $LANG) ? 2 : 1))];
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
	foreach ($accept_language as $key => $q) {
		if (isset($langs[$key])) {
			$LANG = $key;
			break;
		}
		$key = preg_replace('~-.*~', '', $key);
		if (!isset($accept_language[$key]) && isset($langs[$key])) {
			$LANG = $key;
			break;
		}
	}
}
