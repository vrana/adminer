<?php
namespace Adminer;

class Plugins extends Adminer {
	public $plugins; ///< @var list<object> @visibility protected(set)

	/** Register plugins
	* @param list<object> object instances or null to autoload plugins from adminer-plugins/
	*/
	function __construct($plugins) {
		if ($plugins === null) {
			$plugins = array();
			$basename = "adminer-plugins";
			if (is_dir($basename)) {
				foreach (glob("$basename/*.php") as $filename) {
					$include = include_once "./$filename";
				}
			}
			$help = " href='https://www.adminer.org/plugins/#use'" . target_blank();
			if (file_exists("$basename.php")) {
				$include = include_once "./$basename.php"; // example: return array(new AdminerLoginOtp($secret))
				if (is_array($include)) {
					foreach ($include as $plugin) {
						$plugins[get_class($plugin)] = $plugin;
					}
				} else {
					$this->error .= lang('%s must <a%s>return an array</a>.', "<b>$basename.php</b>", $help) . "<br>";
				}
			}
			foreach (get_declared_classes() as $class) {
				if (!$plugins[$class] && preg_match('~^Adminer\w~i', $class)) {
					$reflection = new \ReflectionClass($class);
					$constructor = $reflection->getConstructor();
					if ($constructor && $constructor->getNumberOfRequiredParameters()) {
						$this->error .= lang('<a%s>Configure</a> %s in %s.', $help, "<b>$class</b>", "<b>$basename.php</b>") . "<br>";
					} else {
						$plugins[$class] = new $class;
					}
				}
			}
		}
		$this->plugins = $plugins;
	}

	private function callParent($function, $args) {
		return call_user_func_array(array('parent', $function), $args);
	}

	private function applyPlugin($function, $params) {
		$args = array();
		foreach ($params as $key => $val) {
			// some plugins accept params by reference - we don't need to propage it outside, just to the other plugins
			$args[] = &$params[$key];
		}
		foreach ($this->plugins as $plugin) {
			if (method_exists($plugin, $function)) {
				$return = call_user_func_array(array($plugin, $function), $args);
				if ($return !== null) {
					return $return;
				}
			}
		}
		return $this->callParent($function, $args);
	}

	private function appendPlugin($function, $args) {
		$return = $this->callParent($function, $args);
		foreach ($this->plugins as $plugin) {
			if (method_exists($plugin, $function)) {
				$value = call_user_func_array(array($plugin, $function), $args);
				if ($value) {
					$return += $value;
				}
			}
		}
		return $return;
	}

	// appendPlugin

	function dumpFormat() {
		$args = func_get_args();
		return $this->appendPlugin(__FUNCTION__, $args);
	}

	function dumpOutput() {
		$args = func_get_args();
		return $this->appendPlugin(__FUNCTION__, $args);
	}

	function editRowPrint($table, $fields, $row, $update) {
		$args = func_get_args();
		return $this->appendPlugin(__FUNCTION__, $args);
	}

	function editFunctions($field) {
		$args = func_get_args();
		return $this->appendPlugin(__FUNCTION__, $args);
	}

	// applyPlugin

	function name() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function credentials() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function connectSsl() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function permanentLogin($create = false) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function bruteForceKey() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function serverName($server) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function database() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function schemas() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function databases($flush = true) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function queryTimeout() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function headers() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function csp() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function head($dark = null) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function css() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function loginForm() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function loginFormField($name, $heading, $value) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function login($login, $password) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function tableName($tableStatus) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function fieldName($field, $order = 0) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLinks($tableStatus, $set = "") {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function foreignKeys($table) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function backwardKeys($table, $tableName) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function backwardKeysPrint($backwardKeys, $row) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectQuery($query, $start, $failed = false) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function sqlCommandQuery($query) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function sqlPrintAfter() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function rowDescription($table) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function rowDescriptions($rows, $foreignKeys) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLink($val, $field) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectVal($val, $link, $field, $original) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function editVal($val, $field) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function tableStructurePrint($fields, $tableStatus = null) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function tableIndexesPrint($indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectColumnsPrint($select, $columns) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectSearchPrint($where, $columns, $indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectOrderPrint($order, $columns, $indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLimitPrint($limit) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLengthPrint($text_length) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectActionPrint($indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectCommandPrint() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectImportPrint() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectEmailPrint($emailFields, $columns) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectColumnsProcess($columns, $indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectSearchProcess($fields, $indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectOrderProcess($fields, $indexes) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLimitProcess() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectLengthProcess() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectEmailProcess($where, $foreignKeys) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function selectQueryBuild($select, $where, $group, $order, $limit, $page) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function messageQuery($query, $time, $failed = false) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function editInput($table, $field, $attrs, $value) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function editHint($table, $field, $value) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function processInput($field, $value, $function = "") {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpDatabase($db) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpTable($table, $style, $is_view = 0) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpData($table, $style, $query) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpFilename($identifier) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpHeaders($identifier, $multi_table = false) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function dumpFooter() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function importServerPath() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function homepage() {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function navigation($missing) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function syntaxHighlighting($tables) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function databasesPrint($missing) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}

	function tablesPrint($tables) {
		$args = func_get_args();
		return $this->applyPlugin(__FUNCTION__, $args);
	}
}
