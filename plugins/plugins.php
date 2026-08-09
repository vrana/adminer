<?php

/** Link the plugins usable in each part of the interface
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPlugins extends Adminer\Plugin {
	/** @var string[][] plugins printing in the same place, in the name of the hook they print in */
	private $plugins = array(
		'pluginsLinks' => array('adminer.js', 'before-unload', 'config', 'dark-switcher', 'database-hide', 'designs', 'frames', 'remote-color', 'timeout', 'version-github', 'version-noverify'),
		'loginForm' => array('editor-setup', 'login-ip', 'login-otp', 'login-passkey', 'login-password-less', 'login-reverse-proxy', 'login-servers', 'login-ssl', 'login-table'),
		'tablesPrint' => array('editor-views', 'menu-links', 'tables-filter'),
		'selectLinks' => array('backward-keys', 'foreign-system', 'row-numbers', 'select-image'),
		'selectEmailPrint' => array('select-email'),
		'editRowPrint' => array('edit-foreign', 'edit-textarea', 'enum-option', 'file-upload', 'slugify'),
		'tableStructurePrint' => array('table-structure'),
		'tableIndexesPrint' => array('table-indexes-structure'),
		'sqlPrintAfter' => array('highlight-codemirror', 'highlight-monaco', 'highlight-prism', 'sql-gemini', 'sql-log'),
		'importPrint' => array('import-csv'),
		'dumpPrint' => array('dump-alter', 'dump-bz2', 'dump-date', 'dump-json', 'dump-xml', 'dump-zip'),
	);

	/** @var bool[] */ private $loaded;

	function pluginsLinks() {
		$this->printPlugins('pluginsLinks');
	}

	function loginForm() {
		$this->printPlugins('loginForm');
	}

	function tablesPrint($tables) {
		$this->printPlugins('tablesPrint');
	}

	function selectLinks($tableStatus, $set = "") {
		if (isset($_GET["select"])) { // the same hook prints the structure page which advertises its own plugins
			$this->printPlugins('selectLinks');
		}
	}

	function selectEmailPrint($emailFields, $columns) {
		$this->printPlugins('selectEmailPrint');
	}

	function editRowPrint($table, $fields, $row, $update) {
		$this->printPlugins('editRowPrint');
	}

	function tableStructurePrint($fields, $tableStatus = null) {
		$this->printPlugins('tableStructurePrint');
	}

	function tableIndexesPrint($indexes, $tableStatus) {
		$this->printPlugins('tableIndexesPrint');
	}

	function sqlPrintAfter() {
		if ($this->printPlugins('sqlPrintAfter')) {
			echo "<p>"; // the hook is called in a paragraph ended by the printed links
		}
	}

	function importPrint() {
		$this->printPlugins('importPrint');
	}

	function dumpPrint() {
		$this->printPlugins('dumpPrint');
	}

	/** Print links to the not loaded plugins printing in the same place
	* @param string $hook key of $this->plugins
	* @return bool whether anything was printed
	*/
	private function printPlugins($hook) {
		$loaded = $this->loaded();
		$links = array();
		foreach ($this->plugins[$hook] as $plugin) {
			if (!$loaded[$plugin]) {
				$links[] = "<a href='https://www.adminer.org/plugins/#$plugin'" . Adminer\target_blank() . ">$plugin</a>";
			}
		}
		if (!$links) {
			return false;
		}
		echo "<p><b>" . $this->lang('Plugins') . "</b>: " . implode(", ", $links) . "\n";
		return true;
	}

	/** Get the loaded plugins
	* @return bool[] file name of the plugin without extension in key
	*/
	private function loaded() {
		if (!isset($this->loaded)) {
			$this->loaded = array();
			$adminer = Adminer\adminer();
			if ($adminer instanceof Adminer\Plugins) { // the plugins are not available when adminer_object() returns a plain Adminer
				foreach ($adminer->plugins as $plugin) {
					$reflection = new ReflectionObject($plugin);
					$this->loaded[basename((string) $reflection->getFileName(), '.php')] = true;
				}
			}
		}
		return $this->loaded;
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Odkazuje na pluginy použitelné v jednotlivých částech rozhraní', // Claude Opus 5
			'Plugins' => 'Pluginy', // Claude Opus 5
		),
	);
}
