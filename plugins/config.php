<?php

/** Configure options by end-users and store them to a cookie
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerConfig extends Adminer\Plugin {

	function headers() {
		static $called; // this function is called from page_header() and it also calls page_header()
		if (isset($_GET["config"]) && !$called && Adminer\connection()) {
			$called = true;
			if ($_GET["config"]) { // using $_GET allows sharing links between devices but doesn't protect against same-site RF; CSRF is protected by SameSite cookies
				Adminer\save_settings($_GET["config"], "adminer_config");
				Adminer\redirect(null, $this->lang('Configuration saved.'));
			}
			Adminer\page_header($this->lang('Configuration'));
			$config = Adminer\adminer()->config();
			if (!$config) {
				// this plugin itself defines config() so this branch is not currently used
				echo "<p>" . $this->lang('Only some plugins support configuration, e.g. %s.', '<a href="https://github.com/vrana/adminer/blob/master/plugins/menu-links.php"' . Adminer\target_blank() . '>menu-links</a>') . "\n";
			} else {
				echo "<form action=''>\n";
				Adminer\hidden_fields_get();
				echo "<table>\n";
				foreach (array_reverse($config) as $title => $html) { // Plugins::$append actually prepends
					echo "<tr><th>$title<td>$html\n";
				}
				echo "</table>\n";
				echo "<p><input type='submit' value='" . Adminer\lang('Save') . "'>\n";
				echo "</form>\n";
			}
			Adminer\page_footer('db');
			exit;
		}
	}

	function config() {
		$options = array(
			'' => $this->lang('Use %s if exists', "adminer.css"),
			'builtin' => $this->lang('Use builtin design'),
		);
		return array($this->lang('Design') => Adminer\html_radios('config[design]', $options, Adminer\get_setting("design", "adminer_config"), "<br>"));
	}

	function css() {
		if (Adminer\get_setting("design", "adminer_config") == "builtin") {
			return array();
		}
	}

	function navigation() {
		if (Adminer\connection()) { // don't display on login page
			$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', Adminer\ME), 0, -1);
			?>
<style>
#configlink { position: absolute; top: -2.6em; left: 17.8em; }
#configlink a { font-size: 150%; }
@media all and (max-width: 800px) {
	#configlink { top: 5em; left: auto; right: 20px; }
}
</style>
<?php
			echo "<div id='configlink'><a href='" . Adminer\h($link) . "&config=' title='" . $this->lang('Configuration') . "'>⚙</a></div>\n";
		}
	}

	protected static $translations = array(
		'cs' => array(
			'Configuration' => 'Konfigurace',
			'Configuration saved.' => 'Konfigurace uložena.',
			'Only some plugins support configuration, e.g. %s.' => 'Konfiguraci podporují jen některé pluginy, např. %s.',
			'Design' => 'Vzhled',
			'Use %s if exists' => 'Použít %s, pokud existuje',
			'Use builtin design' => 'Použít vestavěný vzhled',
		),
		'pl' => array(
			'Configuration' => 'Konfiguracja',
			'Configuration saved.' => 'Konfiguracja zapisana.',
			'Only some plugins support configuration, e.g. %s.' => 'Tylko niektóre wtyczki obsługują konfigurację, np. %s.',
			'Design' => 'Wygląd',
			'Use %s if exists' => 'Użyj %s, jeśli istnieje',
			'Use builtin design' => 'Użyj wbudowanego wyglądu',
		),
		'de' => array(
			'Configuration' => 'Konfiguration',
			'Configuration saved.' => 'Konfiguration gespeichert.',
			'Only some plugins support configuration, e.g. %s.' => 'Nur einige Plugins unterstützen die Konfiguration, z.B. %s.',
			'Design' => 'Design',
			'Use %s if exists' => '%s verwenden, falls vorhanden',
			'Use builtin design' => 'Standard Design verwenden',
		),
	);
}
