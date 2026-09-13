<?php
// Entry point for tests/plugins.spec.js with a fixed set of plugins, unaffected by the local adminer-plugins/ directory.

chdir(__DIR__ . "/../adminer"); // the pages are included relative to the working directory
define('Adminer\DIR', "../adminer/"); // used also in the URLs of the static files

function adminer_object() {
	foreach (array('backward-keys', 'config', 'dump-json', 'dump-zip', 'edit-foreign', 'import-csv', 'tables-filter') as $plugin) {
		include_once "../plugins/$plugin.php";
	}
	return new Adminer\Plugins(array(
		new AdminerBackwardKeys,
		new AdminerConfig,
		new AdminerDumpJson,
		new AdminerDumpZip,
		new AdminerEditForeign,
		new AdminerImportCsv,
		new AdminerTablesFilter,
	));
}

include "./index.php";
