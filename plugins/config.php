<?php

/** Let end users configure options and store them in a cookie
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
			if ($_POST["config"] && Adminer\verify_token()) { // a GET form would allow sharing links between devices but any page could change the settings by a link
				Adminer\save_settings($_POST["config"], "adminer_config");
				Adminer\redirect(Adminer\relative_uri(), $this->lang('Configuration saved.')); // the plugins were switched before saving
			}
			Adminer\page_header($this->lang('Configuration'));
			$config = Adminer\adminer()->config();
			$plugins = $this->pluginsConfig();
			if (!$config && !$plugins) {
				echo "<p>" . $this->lang(
					'Only some plugins support configuration, e.g. %s.',
					'<a href="https://github.com/vrana/adminer/blob/main/plugins/menu-links.php"' . Adminer\target_blank() . '>menu-links</a>'
				) . "\n";
			} else {
				echo "<form method='post'>\n";
				if ($config) {
					echo "<table>\n";
					foreach (array_reverse($config) as $title => $html) { // Plugins::$append actually prepends
						echo "<tr><th>$title<td>$html\n";
					}
					echo "</table>\n";
				}
				if ($plugins) {
					echo "<h3>" . $this->lang('Enabled plugins') . "</h3>\n";
					echo "<table>\n$plugins</table>\n";
					// only one syntax highlighter can be enabled
					echo Adminer\script('const highlighters = qsa(\'[type="checkbox"][name^="config[plugins][AdminerHighlight"]\');'
						. ' highlighters.forEach(input => input.addEventListener(\'click\', () => highlighters.forEach(other => other.checked = (other == input && input.checked))));')
					;
				}
				echo "<p><input type='submit' value='" . $this->lang('Save') . "'>\n";
				echo Adminer\input_token();
				echo "</form>\n";
			}
			Adminer\page_footer('db');
			exit;
		}
	}

	/** Get table rows with checkboxes switching the optional plugins
	* @return string HTML
	*/
	private function pluginsConfig() {
		$adminer = Adminer\adminer();
		$return = array();
		if ($adminer instanceof Adminer\Plugins) {
			foreach (array_merge($adminer->plugins, $adminer->disabled) as $plugin) {
				if ($plugin instanceof Adminer\Plugin && $plugin::$optional) {
					$class = get_class($plugin);
					// the hidden input saves an unchecked plugin
					$return[$class] = "<tr><th style='white-space: nowrap;'><input type='hidden' name='config[plugins][$class]' value='0'>"
						. Adminer\checkbox("config[plugins][$class]", 1, !in_array($plugin, $adminer->disabled, true), Adminer\Plugins::pluginName($plugin))
						. "<td>" . Adminer\Plugins::describe($plugin) . "\n"
					;
				}
			}
			ksort($return);
		}
		return implode($return);
	}

	function navigation($missing) {
		if ($missing != "auth") { // offer the page in the Ctrl+K box
			echo Adminer\script("jumpSections.push(" . json_encode(array($this->lang('Configuration'), $this->link()), 256) . ");");
		}
	}

	function pluginsLinks() {
		echo "<p class='links hover'><a href='" . Adminer\h($this->link()) . "'>" . $this->lang('Configuration') . "</a>\n";
	}

	/** Get the URL of the configuration page */
	private function link() {
		return preg_replace('~\b(db|ns)=[^&]*&~', '', Adminer\ME) . "config=";
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/config.webp";
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Konfigurace možností uživateli a jejich uložení do cookie',
			'Configuration' => 'Konfigurace',
			'Configuration saved.' => 'Konfigurace uložena.',
			'Only some plugins support configuration, e.g. %s.' => 'Konfiguraci podporují jen některé pluginy, např. %s.',
			'Save' => 'Uložit',
			'Enabled plugins' => 'Povolené pluginy',
		),
		'de' => array(
			'' => 'Optionen durch den Endbenutzer konfigurieren und dies in einem Cookie speichern',
			'Configuration' => 'Konfiguration',
			'Configuration saved.' => 'Konfiguration gespeichert.',
			'Only some plugins support configuration, e.g. %s.' => 'Nur einige Plugins unterstützen die Konfiguration, z.B. %s.',
			'Save' => 'Speichern',
			'Enabled plugins' => 'Aktivierte Plugins', // Claude Opus 5.5
		),
		'hr' => array(
			'' => 'Postavljanje opcija krajnjim korisnicima i njihovo spremanje u cookie', // Claude Opus 5
			'Configuration saved.' => 'Konfiguracija je spremljena.',
			'Configuration' => 'Konfiguracija',
			'Only some plugins support configuration, e.g. %s.' => 'Samo neki dodaci podržavaju konfiguraciju, npr. %s.',
			'Save' => 'Spremi',
			'Enabled plugins' => 'Omogućeni dodaci', // Claude Opus 5.5
		),
		'ja' => array(
			'' => 'ユーザオプションを設定し cookie に保存',
			'Configuration' => '設定',
			'Configuration saved.' => '設定を保存しました。',
			'Only some plugins support configuration, e.g. %s.' => '設定変更に対応しているのは一部のプラグインのみです。例: %s。',
			'Save' => '保存',
			'Enabled plugins' => '有効なプラグイン', // Claude Opus 5.5
		),
		'pl' => array(
			'' => 'Konfiguruj opcje dla użytkowników końcowych i przechowuj je w pliku cookie',
			'Configuration' => 'Konfiguracja',
			'Configuration saved.' => 'Konfiguracja zapisana.',
			'Only some plugins support configuration, e.g. %s.' => 'Tylko niektóre wtyczki obsługują konfigurację, np. %s.',
			'Save' => 'Zapisz zmiany',
			'Enabled plugins' => 'Włączone wtyczki', // Claude Opus 5.5
		),
		'ro' => array(
			'' => 'Configurarea opțiunilor de către utilizatori și salvarea lor într-un cookie', // Claude Opus 5
			'Enabled plugins' => 'Pluginuri activate', // Claude Opus 5.5
		),
		'sk' => array(
			'' => 'Konfigurácia možností používateľmi a ich uloženie do cookie', // Claude Opus 5
			'Enabled plugins' => 'Povolené pluginy', // Claude Opus 5.5
		),
		'zh' => array(
			'' => '让最终用户配置选项并保存到 cookie', // Claude Opus 5
			'Configuration saved.' => '配置已保存。', // Claude Opus 5
			'Configuration' => '配置', // Claude Opus 5
			'Only some plugins support configuration, e.g. %s.' => '只有部分插件支持配置，例如 %s。', // Claude Opus 5
			'Save' => '保存', // Claude Opus 5
			'Enabled plugins' => '已启用的插件', // Claude Opus 5.5
		),
	);
}
