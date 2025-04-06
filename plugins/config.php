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
			if ($_POST) { //! check $error
				unset($_POST["token"]);
				Adminer\save_settings($_POST, "adminer_config");
				Adminer\redirect($_SERVER["REQUEST_URI"], $this->lang('Configuration saved.'));
			}
			Adminer\page_header($this->lang('Configuration'));
			$config = Adminer\adminer()->config();
			if (!$config) {
				echo "<p>" . $this->lang('Only some plugins support configuration, e.g. %s.', '<a href="https://github.com/vrana/adminer/blob/master/plugins/menu-links.php"' . Adminer\target_blank() . '>menu-links</a>') . "\n";
			} else {
				echo "<form action='' method='post'>\n";
				echo "<table>\n";
				foreach ($config as $title => $html) {
					echo "<tr><th>$title<td>$html\n";
				}
				echo "</table>\n";
				echo "<p><input type='submit' value='" . Adminer\lang('Save') . "'>\n";
				echo Adminer\input_token();
				echo "</form>\n";
			}
			Adminer\page_footer('db');
			exit;
		}
	}

	function navigation() {
		if (Adminer\connection()) {
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
		),
	);
}
