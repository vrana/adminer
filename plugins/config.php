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
			if (!$config) {
				// this plugin itself defines config() so this branch is not currently used
				echo "<p>" . $this->lang(
					'Only some plugins support configuration, e.g. %s.',
					'<a href="https://github.com/vrana/adminer/blob/main/plugins/menu-links.php"' . Adminer\target_blank() . '>menu-links</a>'
				) . "\n";
			} else {
				echo "<form action='' method='post'>\n";
				echo "<table>\n";
				foreach (array_reverse($config) as $title => $html) { // Plugins::$append actually prepends
					echo "<tr><th>$title<td>$html\n";
				}
				echo "</table>\n";
				$plugins = $this->pluginsConfig();
				if ($plugins) {
					echo "<h3>" . $this->lang('Enabled plugins') . "</h3>\n";
					echo "<table>\n$plugins</table>\n";
				}
				echo "<p><input type='submit' value='" . $this->lang('Save') . "'>\n";
				echo Adminer\input_token();
				echo "</form>\n";
			}
			Adminer\page_footer('db');
			exit;
		}
	}

	function config() {
		$options = array(
			'' => $this->lang('Use %s if it exists', "adminer.css"),
			'builtin' => $this->lang('Use built-in design'),
		);
		return array($this->lang('Design') => Adminer\html_radios('config[design]', $options, Adminer\get_setting("design", "adminer_config"), "<br>"));
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
					$return[$class] = "<tr><th><input type='hidden' name='config[plugins][$class]' value='0'>"
						. Adminer\checkbox("config[plugins][$class]", 1, !in_array($plugin, $adminer->disabled, true), $class)
						. "<td>" . Adminer\Plugins::describe($plugin) . "\n"
					;
				}
			}
			ksort($return);
		}
		return implode($return);
	}

	function css() {
		if (Adminer\get_setting("design", "adminer_config") == "builtin") {
			return array();
		}
	}

	function pluginsLinks() {
		$link = preg_replace('~\b(db|ns)=[^&]*&~', '', Adminer\ME);
		echo "<p class='links hover'><a href='" . Adminer\h($link) . "config='>" . $this->lang('Configuration') . "</a>\n";
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
			'Design' => 'Vzhled',
			'Use %s if it exists' => 'Použít %s, pokud existuje',
			'Use built-in design' => 'Použít vestavěný vzhled',
			'Save' => 'Uložit',
			'Enabled plugins' => 'Povolené pluginy',
		),
		'de' => array(
			'' => 'Optionen durch den Endbenutzer konfigurieren und dies in einem Cookie speichern',
			'Configuration' => 'Konfiguration',
			'Configuration saved.' => 'Konfiguration gespeichert.',
			'Only some plugins support configuration, e.g. %s.' => 'Nur einige Plugins unterstützen die Konfiguration, z.B. %s.',
			'Design' => 'Design',
			'Use %s if it exists' => '%s verwenden, falls vorhanden',
			'Use built-in design' => 'Standard Design verwenden',
			'Save' => 'Speichern',
			'Enabled plugins' => 'Aktivierte Plugins', // Claude Opus 5.5
		),
		'hr' => array(
			'' => 'Postavljanje opcija krajnjim korisnicima i njihovo spremanje u cookie', // Claude Opus 5
			'Configuration saved.' => 'Konfiguracija je spremljena.',
			'Configuration' => 'Konfiguracija',
			'Only some plugins support configuration, e.g. %s.' => 'Samo neki dodaci podržavaju konfiguraciju, npr. %s.',
			'Use %s if it exists' => 'Koristi %s ako postoji',
			'Use built-in design' => 'Koristi ugrađeni dizajn',
			'Design' => 'Dizajn',
			'Save' => 'Spremi',
			'Enabled plugins' => 'Omogućeni dodaci', // Claude Opus 5.5
		),
		'ja' => array(
			'' => 'ユーザオプションを設定し cookie に保存',
			'Configuration' => '設定',
			'Configuration saved.' => '設定を保存しました。',
			'Only some plugins support configuration, e.g. %s.' => '設定変更に対応しているのは一部のプラグインのみです。例: %s。',
			'Design' => 'デザイン',
			'Use %s if it exists' => 'あれば %s を使う',
			'Use built-in design' => '組込みのデザインを使う',
			'Save' => '保存',
			'Enabled plugins' => '有効なプラグイン', // Claude Opus 5.5
		),
		'pl' => array(
			'' => 'Konfiguruj opcje dla użytkowników końcowych i przechowuj je w pliku cookie',
			'Configuration' => 'Konfiguracja',
			'Configuration saved.' => 'Konfiguracja zapisana.',
			'Only some plugins support configuration, e.g. %s.' => 'Tylko niektóre wtyczki obsługują konfigurację, np. %s.',
			'Design' => 'Wygląd',
			'Use %s if it exists' => 'Użyj %s, jeśli istnieje',
			'Use built-in design' => 'Użyj wbudowanego wyglądu',
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
			'Use %s if it exists' => '如果 %s 存在则使用它', // Claude Opus 5
			'Use built-in design' => '使用内置外观', // Claude Opus 5
			'Design' => '外观', // Claude Opus 5
			'Enabled plugins' => '已启用的插件', // Claude Opus 5.5
		),
	);
}
