<?php
function adminer_object() {
	// required to run any plugin
	include_once "../plugins/plugin.php";
	
	// autoloader
	foreach (glob("../plugins/*.php") as $filename) {
		include_once $filename;
	}
	
	$plugins = array(
		// specify enabled plugins here
		new AdminerDatabaseHide(array('information_schema')),
		new AdminerDumpJson,
		new AdminerDumpBz2,
		new AdminerDumpZip,
		new AdminerDumpXml,
		new AdminerDumpAlter,
		//~ new AdminerSqlLog("past-" . rtrim(`git describe --tags --abbrev=0`) . ".sql"),
		//~ new AdminerEditCalendar(script_src("../externals/jquery-ui/jquery-1.4.4.js") . script_src("../externals/jquery-ui/ui/jquery.ui.core.js") . script_src("../externals/jquery-ui/ui/jquery.ui.widget.js") . script_src("../externals/jquery-ui/ui/jquery.ui.datepicker.js") . script_src("../externals/jquery-ui/ui/jquery.ui.mouse.js") . script_src("../externals/jquery-ui/ui/jquery.ui.slider.js") . script_src("../externals/jquery-timepicker/jquery-ui-timepicker-addon.js") . "<link rel='stylesheet' href='../externals/jquery-ui/themes/base/jquery.ui.all.css'>\n<style>\n.ui-timepicker-div .ui-widget-header { margin-bottom: 8px; }\n.ui-timepicker-div dl { text-align: left; }\n.ui-timepicker-div dl dt { height: 25px; }\n.ui-timepicker-div dl dd { margin: -25px 0 10px 65px; }\n.ui-timepicker-div td { font-size: 90%; }\n</style>\n", "../externals/jquery-ui/ui/i18n/jquery.ui.datepicker-%s.js"),
		//~ new AdminerTinymce("../externals/tinymce/jscripts/tiny_mce/tiny_mce_dev.js"),
		//~ new AdminerWymeditor(array("../externals/wymeditor/src/jquery/jquery.js", "../externals/wymeditor/src/wymeditor/jquery.wymeditor.js", "../externals/wymeditor/src/wymeditor/jquery.wymeditor.explorer.js", "../externals/wymeditor/src/wymeditor/jquery.wymeditor.mozilla.js", "../externals/wymeditor/src/wymeditor/jquery.wymeditor.opera.js", "../externals/wymeditor/src/wymeditor/jquery.wymeditor.safari.js")),
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
