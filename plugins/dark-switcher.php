<?php

/** Allow switching light and dark mode
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDarkSwitcher extends Adminer\Plugin {

	function head($dark = null) {
		?>
<script <?php echo Adminer\nonce(); ?>>
let adminerDark;

function adminerDarkSwitch() {
	adminerDark = !adminerDark;
	adminerDarkSet();
}

function adminerDarkSet() {
	qsa('link[href*="dark.css"]').forEach(link => link.media = (adminerDark ? '' : 'never'));
	qs('meta[name="color-scheme"]').content = (adminerDark ? 'dark' : 'light');
	cookie('adminer_dark=' + (adminerDark ? 1 : 0), 30);
}

const saved = document.cookie.match(/adminer_dark=(\d)/);
if (saved) {
	adminerDark = +saved[1];
	adminerDarkSet();
} else {
	adminerDark = +matchMedia('(prefers-color-scheme: dark)').matches;
}
</script>
<?php
	}

	function navigation($missing) {
		echo "<big style='position: fixed; bottom: .5em; right: .5em; cursor: pointer;'>☀</big>"
			. Adminer\script("adminerDarkSet(); qsl('big').onclick = adminerDarkSwitch;") . "\n"
		;
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/dark-switcher.gif";
	}

	protected $translations = array(
		'cs' => array('' => 'Dovoluje přepínání světlého a tmavého vzhledu'),
		'de' => array('' => 'Umschalten zwischen hellem und dunklem Design erlauben'),
		'ja' => array('' => 'ダークモードへの切替え'),
		'pl' => array('' => 'Zezwalaj na przełączanie trybu jasnego i ciemnego'),
	);
}
