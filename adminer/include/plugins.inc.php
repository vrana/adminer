<?php
namespace Adminer;

class Plugins {
	/** @var true[] */ private static array $append = array('dumpFormat' => true, 'dumpOutput' => true, 'editRowPrint' => true, 'editFunctions' => true); // these hooks expect the value to be appended to the result

	/** @var list<object> @visibility protected(set) */ public array $plugins;
	/** @visibility protected(set) */ public string $error = ''; // HTML
	/** @var list<object>[] */ private array $hooks = array();

	/** Register plugins
	* @param ?list<object> $plugins object instances or null to autoload plugins from adminer-plugins/
	*/
	function __construct(?array $plugins) {
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
					// we need to use reflection because PHP 7.1 throws ArgumentCountError for missing arguments but older versions issue a warning
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

		$adminer = new Adminer;
		$plugins[] = $adminer;
		$reflection = new \ReflectionObject($adminer);
		foreach ($reflection->getMethods() as $method) {
			foreach ($plugins as $plugin) {
				$name = $method->getName();
				if (method_exists($plugin, $name)) {
					$this->hooks[$name][] = $plugin;
				}
			}
		}
	}

	/**
	* @param literal-string $name
	* @param mixed[] $params
	* @return mixed
	*/
	function __call(string $name, array $params) {
		$args = array();
		foreach ($params as $key => $val) {
			// some plugins accept params by reference - we don't need to propage it outside, just to the other plugins
			$args[] = &$params[$key];
		}
		$return = null;
		foreach ($this->hooks[$name] as $plugin) {
			$value = call_user_func_array(array($plugin, $name), $args);
			if ($value !== null) {
				if (!self::$append[$name]) { // non-null value from non-appending method short-circuits the other plugins
					return $value;
				}
				$return = $value + (array) $return;
			}
		}
		return $return;
	}
}
