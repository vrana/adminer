<?php
function adminer_object() {
	// required to run any plugin
	include_once "../plugins/plugin.php";
	
	// autoloader
	foreach (glob("../plugins/*.php") as $filename) {
		include_once $filename;
	}
	
	/* It is possible to combine customization and plugins:
	class AdminerCustomization extends AdminerPlugin {
	}
	return new AdminerCustomization($plugins);
	*/
	
	return new AdminerPlugin(array(
		// specify enabled plugins here
		new AdminerDumpZip,
		new AdminerDumpXml,
		new AdminerTinymce("../externals/tinymce/jscripts/tiny_mce/tiny_mce_dev.js"),
		new AdminerFileUpload(""),
		new AdminerSlugify,
		new AdminerTranslation,
		new AdminerForeignSystem,
		new AdminerEnumOption,
	));
}

// include original Adminer or Adminer Editor (usually named adminer.php)
include "./index.php";
