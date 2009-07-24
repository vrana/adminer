<?php
// coverage is used in tests and removed in compilation
if (extension_loaded("xdebug") && function_exists('mysql_query') && mysql_query('SELECT 1 FROM adminer_test.coverage LIMIT 0')) {
	function save_coverage() {
		$coverage = array();
		$result = mysql_query("SELECT filename, coverage_serialize FROM adminer_test.coverage");
		while ($row = mysql_fetch_assoc($result)) {
			$coverage[$row["filename"]] = unserialize($row["coverage_serialize"]);
		}
		mysql_free_result($result);
		foreach (xdebug_get_code_coverage() as $filename => $lines) {
			foreach ($lines as $l => $val) {
				if (!$coverage[$filename][$l] || $val > 0) {
					$coverage[$filename][$l] = $val;
				}
			}
			mysql_query("
				REPLACE adminer_test.coverage (filename, coverage_serialize)
				VALUES ('" . mysql_real_escape_string($filename) . "', '" . mysql_real_escape_string(serialize($coverage[$filename])) . "')
			");
		}
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	register_shutdown_function('save_coverage');
}
