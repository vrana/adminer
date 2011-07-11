<?php
// not used in a single language version

$langs = array(
	'en' => 'English', // Jakub Vrána - http://www.vrana.cz
	'cs' => 'Čeština', // Jakub Vrána - http://www.vrana.cz
	'sk' => 'Slovenčina', // Ivan Suchy - http://www.ivansuchy.com, Juraj Krivda - http://www.jstudio.cz
	'nl' => 'Nederlands', // Maarten Balliauw - http://blog.maartenballiauw.be
	'es' => 'Español', // Klemens Häckel - http://clickdimension.wordpress.com
	'de' => 'Deutsch', // Klemens Häckel - http://clickdimension.wordpress.com
	'fr' => 'Français', // Francis Gagné, Aurélien Royer
	'it' => 'Italiano', // Alessandro Fiorotto, Paolo Asperti
	'et' => 'Eesti', // Priit Kallas
	'hu' => 'Magyar', // Borsos Szilárd (Borsosfi) - http://www.borsosfi.hu, info@borsosfi.hu
	'pl' => 'Polski', // Radosław Kowalewski - http://srsbiz.pl/
	'ca' => 'Català', // Joan Llosas
	'pt' => 'Português', // Gian Live - gian@live.com
	'sl' => 'Slovenski', // Matej Ferlan - www.itdinamik.com, matej.ferlan@itdinamik.com
	'lt' => 'Lietuvių', // Paulius Leščinskas - http://www.lescinskas.lt
	'tr' => 'Türkçe', // Bilgehan Korkmaz - turktron.com
	'ro' => 'Limba Română', // .nick .messing - dot.nick.dot.messing@gmail.com
	'ru' => 'Русский язык', // Maksim Izmaylov
	'zh' => '简体中文', // Mr. Lodar
	'zh-tw' => '繁體中文', // http://tzangms.com
	'ja' => '日本語', // Hitoshi Ozawa - http://sourceforge.jp/projects/oss-ja-jpn/releases/
	'ta' => 'த‌மிழ்', // G. Sampath Kumar, Chennai, India, sampathkumar11@gmail.com
	'ar' => 'العربية', // Y.M Amine - Algeria - nbr7@live.fr
);

/** Get current language
* @return string
*/
function get_lang() {
	global $LANG;
	return $LANG;
}

/** Translate string
* @param string
* @param int
* @return string
*/
function lang($idf, $number = null) {
	global $LANG, $translations;
	$translation = $translations[$idf];
	if (is_array($translation)) {
		$pos = ($number == 1 ? 0
			: ($LANG == 'cs' || $LANG == 'sk' ? ($number && $number < 5 ? 1 : 2) // different forms for 1, 2-4, other
			: ($LANG == 'fr' ? (!$number ? 0 : 1) // different forms for 0-1, other
			: ($LANG == 'pl' ? ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2) // different forms for 1, 2-4, other
			: ($LANG == 'sl' ? ($number % 100 == 1 ? 0 : ($number % 100 == 2 ? 1 : ($number % 100 == 3 || $number % 100 == 4 ? 2 : 3))) // different forms for 1, 2, 3-4, other
			: ($LANG == 'lt' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1, 12-19, other
			: ($LANG == 'ru' ? ($number % 10 == 1 && $number % 100 != 11 ? 0 : ($number % 10 > 1 && $number % 10 < 5 && $number / 10 % 10 != 1 ? 1 : 2)) // different forms for 1, 2-4, other
			: 1
		))))))); // http://www.gnu.org/software/gettext/manual/html_node/Plural-forms.html
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
	echo lang('Language') . ": " . html_select("lang", $langs, $LANG, "var loc = location.search.replace(/[?&]lang=[^&]*/, ''); location.search = loc + (loc ? '&' : '') + 'lang=' + this.value;");
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
