<?php

/** Configure menu table links; combinable with AdminerConfig
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMenuLinks extends Adminer\Plugin {
	private $menu;

	/** @param ''|'hover'|'table'|'select'|'auto' $menu see config() for explanation */
	function __construct($menu = '') {
		$this->menu = $menu;
	}

	function config() {
		$options = array(
			'select' => $this->lang('Select data'),
			'table' => $this->lang('Show structure'),
			'' => $this->lang('Both'),
			'hover' => $this->lang('Both, select on hover'),
			'auto' => $this->lang('Auto (select on select page, structure otherwise)'),
		);
		$menu = Adminer\get_setting("menu", "adminer_config", $this->menu);
		return array($this->lang('Menu table links') => Adminer\html_radios('config[menu]', $options, $menu, "<br>"));
	}

	function head($dark = null) {
		if (Adminer\get_setting("menu", "adminer_config", $this->menu) != 'hover') {
			return;
		}
		// Adminer marks the repeated links and checkboxes by the hover class and the columns holding a row action by the actions class but doesn't style them
		?>
<style>
/* opacity keeps the elements focusable and the layout stable */
@media (hover: hover) { .hover { opacity: 0; } }
tr:hover .hover, li:hover .hover, p:hover .hover, #fieldset-history div:hover .hover, .hover:focus, .hover:checked { opacity: 1; }
/* the action is printed outside the table so that it creates no empty column */
.actions td:last-child { position: relative; width: 0; padding: 0; border-width: 0; }
.actions td:last-child a { position: absolute; top: 0; padding: .2em .3em; }
/* bold select would be wider than the other ones and shift the table name, so the name is bold instead - not in Editor where the link is the name */
#tables a.select.active:not(:last-child) { font-weight: normal; }
#tables a.select.active ~ * { font-weight: bold; }
</style>
<?php
		//! table names in menu should be aligned left
		//! the column with edit link in select should be invisible (also checkboxes in db and elsewhere), same as the Alter links
		//! skins are not compatible with .actions
		//! consider hiding also the ?indexes=, ?foreign=, ... links (appear when hovering DIV around H3, looks weird if there are no objects)
	}

	function tablesPrint(array $tables) {
		$menu = Adminer\get_setting("menu", "adminer_config", $this->menu);
		$titles = array(
			'select' => $this->lang('Select data'),
			'table' => $this->lang('Show structure'),
		);
		$both = (!$menu || $menu == 'hover'); // 'hover' - like Adminer, the select link is shown on hovering the row
		// this is copied from Adminer::tablesPrint()
		echo "<ul id='tables'>" . Adminer\script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
		foreach ($tables as $table => $status) {
			$table = "$table"; // do not highlight "0" as active everywhere
			$name = Adminer\adminer()->tableName($status);
			if ($name != "" && !$status["partition"]) {
				echo '<li>';
				if ($both) {
					echo '<a href="' . Adminer\h(Adminer\ME) . 'select=' . urlencode($table) . '"'
						. Adminer\bold($_GET["select"] == $table || $_GET["edit"] == $table, "select" . ($menu ? " hover" : ""))
						. " title='$titles[select]'>" . $this->lang('select') . "</a> "
					;
				}
				$actives = array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"], $_GET["check"], $_GET["view"]);
				if (!$both) {
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
			'Both, select on hover' => 'Oboje, vypsat při najetí myší',
			'Auto (select on select page, structure otherwise)' => 'Auto (vypsat na výpisech, jinak struktura)',
			// this is copied from adminer/lang/
			'select' => 'vypsat',
			'Select data' => 'Vypsat data',
			'Show structure' => 'Zobrazit strukturu',
		),
		'pl' => array(
			'Menu table links' => 'Linki do tabel w menu',
			'Both' => 'Obie',
			'Both, select on hover' => 'Obie, przeglądaj po najechaniu myszą', // Claude Opus 5
			'Auto (select on select page, structure otherwise)' => 'Auto (pokaż na stronie przeglądania, w przeciwnym razie struktura)',
			// this is copied from adminer/lang/
			'select' => 'przeglądaj',
			'Select data' => 'Pokaż dane',
			'Show structure' => 'Struktura tabeli',
		),
		'de' => array(
			'' => 'Menü- und Tabellen-Links konfigurieren. Kombinierbar mit AdminerConfig',
			'Both' => 'Beide',
			'Both, select on hover' => 'Beide, zeigen beim Überfahren mit der Maus', // Claude Opus 5
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
			'Both, select on hover' => '両方 (選択はマウスオーバー時)', // Claude Opus 5
			'Auto (select on select page, structure otherwise)' => '自動 (選択ページでは選択、それ以外では構造)',
			'Menu table links' => 'メニューテーブルへのリンク',
			// this is copied from adminer/lang/
			'select' => '選択',
			'Select data' => 'データ',
			'Show structure' => '構造',
		),
		'hr' => array(
			'' => 'Prikazuje veze na odabir podataka ili strukturu tablice u izborniku',
			'Select data' => 'Odaberi podatke',
			'Show structure' => 'Prikaži strukturu',
			'Both' => 'Oboje',
			'Both, select on hover' => 'Oboje, odaberi prelaskom miša', // Claude Opus 5
			'Auto (select on select page, structure otherwise)' => 'Automatski (odabir na stranici odabira, inače struktura)',
			'Menu table links' => 'Veze tablice u izborniku',
			'select' => 'odaberi',
		),
	);
}
