<?php
// not used in a single language version

$langs = array(
	'en' => 'English', // Jakub Vrána - http://php.vrana.cz
	'cs' => 'Čeština', // Jakub Vrána - http://php.vrana.cz
	'sk' => 'Slovenčina', // Ivan Suchy - http://www.ivansuchy.com, Juraj Krivda - http://www.jstudio.cz
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'es' => 'Español', // Klemens Häckel - http://clickdimension.wordpress.com
	'de' => 'Deutsch', // Klemens Häckel - http://clickdimension.wordpress.com
	'fr' => 'Français', // Francis Gagné, Aurélien Royer
	'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
	'et' => 'Eesti', // Priit Kallas
	'hu' => 'Magyar', // Borsos Szilárd (Borsosfi) - http://www.borsosfi.hu, info@borsosfi.hu
	'ca' => 'Català', // Joan Llosas
	'ru' => 'Русский язык', // Maksim Izmaylov
	'zh' => '简体中文', // Mr. Lodar
	'zh-tw' => '繁體中文', // http://tzangms.com
	'ja' => '日本語', // Hitoshi Ozawa - http://sourceforge.jp/projects/oss-ja-jpn/releases/
	'ta' => 'த‌மிழ்', // G. Sampath Kumar, Chennai, India, sampathkumar11@gmail.com
	'ar' => 'العربية', // Y.M Amine - Algeria - nbr7@live.fr
);

function lang($idf, $number = null) {
	global $LANG, $translations;
	$translation = $translations[$idf];
	if (is_array($translation) && $translation) {
		$pos = ($number == 1 || (!$number && $LANG == 'fr') ? 0 : ((!$number || $number >= 5) && ereg('cs|sk|ru', $LANG) ? 2 : 1)); // French treat zero as singular, Slavic languages use different form for 2, 3, 4
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
	echo lang('Language') . ": " . html_select("lang", $langs, $LANG, "this.form.submit();");
	echo " <input type='submit' value='" . lang('Use') . "' class='hidden'>\n";
	echo "</div>\n</form>\n";
}

if (isset($_GET["lang"])) {
	$_COOKIE["adminer_lang"] = $_GET["lang"];
	$_SESSION["lang"] = $_GET["lang"]; // cookies may be disabled
}

$LANG = "en";
if (isset($langs[$_COOKIE["adminer_lang"]])) {
	cookie("adminer_lang", $_COOKIE["adminer_lang"]);
	$LANG = $_COOKIE["adminer_lang"];
} elseif (isset($langs[$_SESSION["lang"]])) {
	$LANG = $_SESSION["lang"];
} else {
	$accept_language = array();
	preg_match_all('~([-a-z]+)(;q=([0-9.]+))?~', str_replace("_", "-", strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"])), $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$accept_language[$match[1]] = (isset($match[3]) ? $match[3] : 1);
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
