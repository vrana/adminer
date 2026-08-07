<?php
namespace Adminer;

// coverage is used in tests and removed in compilation
if (extension_loaded("xdebug") && file_exists(sys_get_temp_dir() . "/adminer.coverage")) {
	function save_coverage(): void {
		$coverage_filename = sys_get_temp_dir() . "/adminer.coverage";
		$coverage = unserialize(file_get_contents($coverage_filename));
		foreach (xdebug_get_code_coverage() as $filename => $lines) {
			foreach ($lines as $l => $val) {
				if (!idx($coverage[$filename], $l) || $val > 0) {
					$coverage[$filename][$l] = $val;
				}
			}
			file_put_contents($coverage_filename, serialize($coverage));
		}
	}
	// Xdebug marks the not executed lines when it compiles a file, OPcache serves the cached files without compiling them
	\ini_set("opcache.enable", '0'); // PHP allows only disabling this setting at runtime; Adminer\ini_set() is not defined yet
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	register_shutdown_function('Adminer\save_coverage');
}
