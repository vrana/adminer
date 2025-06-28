<?php

/** Configure menu table links; combinable with AdminerConfig
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMenuLinks extends Adminer\Plugin {
	private $menu;

	/** @param ''|'table'|'select'|'auto' $menu see config() for explanation */
	function __construct($menu = '') {
		$this->menu = $menu;
	}

	function config() {
		$options = array(
			'select' => $this->lang('Select data'),
			'table' => $this->lang('Show structure'),
			'' => $this->lang('Both'),
			'auto' => $this->lang('Auto (select on select page, structure otherwise)'),
		);
		$menu = Adminer\get_setting("menu", "adminer_config", $this->menu);
		return array($this->lang('Menu table links') => Adminer\html_radios('config[menu]', $options, $menu, "<br>"));
	}

	function tablesPrint(array $tables) {
		$menu = Adminer\get_setting("menu", "adminer_config", $this->menu);
		$titles = array(
			'select' => $this->lang('Select data'),
			'table' => $this->lang('Show structure'),
		);
		// this is copied from Adminer::tablesPrint()
		echo "<ul id='tables'>" . Adminer\script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$table = "$table"; // do not highlight "0" as active everywhere
			$name = Adminer\adminer()->tableName($status);
			if ($name != "" && !$status["partition"]) {
				echo '<li>';
				if (!$menu) {
					echo '<a href="' . Adminer\h(Adminer\ME) . 'select=' . urlencode($table) . '"'
						. Adminer\bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
						. " title='$titles[select]'>" . $this->lang('select') . "</a> "
					;
				}
				$actives = array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"], $_GET["check"], $_GET["view"]);
				if ($menu) {
					$actives[] = $_GET["select"];
					$actives[] = $_GET["edit"];
				}
				$link =
					($menu == 'select' ? 'select' :
					($menu != 'auto' ? 'table' :
					($_GET["select"] ? 'select' : 'table')
				));
				$class = ($link == "select" ? "select" : (Adminer\is_view($status) ? "view" : "structure"));
				echo (Adminer\support("table") || Adminer\support("indexes") || $menu
					? '<a href="' . Adminer\h(Adminer\ME) . "$link=" . urlencode($table) . '"'
						. Adminer\bold(in_array($table, $actives), $class)
						. " title='$titles[$link]'>$name</a>"
					: "<span>$name</span>"
				);
				echo "\n";
			}
		}
		echo "</ul>\n";
		return true;
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/menu-links.png";
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Konfigurace odkazů na tabulky v menu; kombinovatelné s AdminerConfig',
			'Menu table links' => 'Odkazy na tabulky v menu',
			'Both' => 'Oboje',
			'Auto (select on select page, structure otherwise)' => 'Auto (vypsat na výpisech, jinak struktura)',
			// this is copied from adminer/lang/
			'select' => 'vypsat',
			'Select data' => 'Vypsat data',
			'Show structure' => 'Zobrazit strukturu',
		),
		'pl' => array(
			'Menu table links' => 'Linki do tabel w menu',
			'Both' => 'Obie',
			'Auto (select on select page, structure otherwise)' => 'Auto (pokaż na stronie przeglądania, w przeciwnym razie struktura)',
			// this is copied from adminer/lang/
			'select' => 'przeglądaj',
			'Select data' => 'Pokaż dane',
			'Show structure' => 'Struktura tabeli',
		),
		'de' => array(
			'' => 'Menü- und Tabellen-Links konfigurieren. Kombinierbar mit AdminerConfig',
			'Both' => 'Beide',
			'Auto (select on select page, structure otherwise)' => 'Auto (Auswahl auf der ausgewählten Seite, sonst Struktur)',
			'Menu table links' => 'Links verwenden in „Tabelle“',
			// this is copied from adminer/lang/
			'select' => 'zeigen',
			'Select data' => 'Daten auswählen',
			'Show structure' => 'Struktur anzeigen',
		),
		'ja' => array(
			'' => 'メニュー内テーブルへのリンク設定; AdminerConfig との併用可',
			'Both' => '両方',
			'Auto (select on select page, structure otherwise)' => '自動 (選択ページでは選択、それ以外では構造)',
			'Menu table links' => 'メニューテーブルへのリンク',
			// this is copied from adminer/lang/
			'select' => '選択',
			'Select data' => 'データ',
			'Show structure' => '構造',
		),
	);
}
