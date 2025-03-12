<?php
function adminer_object() {
	// required to run any plugin
	include_once "../plugins/plugin.php";

	// autoloader
	foreach (glob("../plugins/*.php") as $filename) {
		include_once $filename;
	}

	// enable extra drivers just by including them
	//~ include "../plugins/drivers/simpledb.php";

	$plugins = array(
		// specify enabled plugins here
		new AdminerDatabaseHide(array('information_schema')),
		new AdminerDumpJson,
		new AdminerDumpBz2,
		new AdminerDumpZip,
		new AdminerDumpXml,
		new AdminerDumpAlter,
		//~ new AdminerSqlLog("past-" . rtrim(`git describe --tags --abbrev=0`) . ".sql"),
		//~ new AdminerTinymce("../externals/tinymce/jscripts/tiny_mce/tiny_mce_dev.js"),
		new AdminerFileUpload(""),
		new AdminerJsonColumn,
		new AdminerSlugify,
		new AdminerTranslation,
		new AdminerForeignSystem,
		new AdminerEnumOption,
		new AdminerTablesFilter,
		new AdminerEditForeign,
	);

	/* It is possible to combine customization and plugins:
	class AdminerCustomization extends AdminerPlugin {
	}
	return new AdminerCustomization($plugins);
	*/

	return new AdminerPlugin($plugins);
}

// include original Adminer or Adminer Editor (usually named adminer.php)
include "./index.php";
