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
			'select' => Adminer\lang('Select data'),
			'table' => Adminer\lang('Show structure'),
			'' => $this->lang('Both'),
			'auto' => $this->lang('Auto (select on select page, structure otherwise)'),
		);
		$menu = Adminer\get_setting("menu", "adminer_config") ?: $this->menu;
		return array($this->lang('Menu table links') => Adminer\html_radios('config[menu]', $options, $menu, "<br>"));
	}

	function tablesPrint(array $tables) {
		$menu = Adminer\get_setting("menu", "adminer_config") ?: $this->menu;
		$titles = array(
			'select' => Adminer\lang('Select data'),
			'table' => Adminer\lang('Show structure'),
		);
		// this is copied from Adminer::tablesPrint()
		echo "<ul id='tables'>" . Adminer\script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$name = Adminer\adminer()->tableName($status);
			if ($name != "") {
				echo '<li>';
				if (!$menu) {
					echo '<a href="' . Adminer\h(Adminer\ME) . 'select=' . urlencode($table) . '"'
						. Adminer\bold($_GET["select"] == $table || $_GET["edit"] == $table, "select")
						. " title='$titles[select]'>" . Adminer\lang('select') . "</a> "
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

	protected static $translations = array(
		'cs' => array(
			'Menu table links' => 'Odkazy na tabulky v menu',
			'Both' => 'Oboje',
			'Auto (select on select page, structure otherwise)' => 'Auto (vypsat na výpisech, jinak struktura)',
		),
		'pl' => array(
			'Menu table links' => 'Linki do tabel w menu',
			'Both' => 'Obie',
			'Auto (select on select page, structure otherwise)' => 'Auto (pokaż na stronie przeglądania, w przeciwnym razie struktura)',
		),
		'de' => array(
			'Both' => 'Beide',
			'Auto (select on select page, structure otherwise)' => 'Auto (Auswahl auf der ausgewählten Seite, sonst Struktur)',
			'Menu table links' => 'Links verwenden in „Tabelle“',
		),
	);
}
