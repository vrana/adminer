<?php
// Entry point for tests/plugins.spec.js with a fixed set of plugins, unaffected by the local adminer-plugins/ directory.

function adminer_object() {
	foreach (array('dump-json', 'dump-xml', 'dump-zip', 'edit-foreign', 'import-csv', 'config') as $plugin) {
		include_once "../plugins/$plugin.php";
	}
	return new Adminer\Plugins(array(
		new AdminerDumpJson,
		new AdminerDumpXml,
		new AdminerDumpZip,
		new AdminerEditForeign,
		new AdminerImportCsv,
		new AdminerConfig,
	));
}

include "./index.php";
