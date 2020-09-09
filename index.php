<?php
function adminer_object() {	
    // required to run any plugin
    include_once "./plugins/plugin.php";

    // autoloader
    foreach (glob("plugins/*.php") as $filename) {
        include_once "./$filename";
    }

    $plugins = array(
        // specify enabled plugins here
        //new AdminerDumpXml,
        //new AdminerTinymce,
        //new AdminerFileUpload("data/"),
        //new AdminerSlugify,
        //new AdminerTranslation,
	//new AdminerForeignSystem,
      	//new AdminerHidePgSchemas,
    );

    /* It is possible to combine customization and plugins:
    class AdminerCustomization extends AdminerPlugin {
    }
    return new AdminerCustomization($plugins);
    */

    include_once "plugins/designs.php";
    $designs = array();
    foreach (glob("designs/*", GLOB_ONLYDIR) as $filename) {
        $designs["$filename/adminer.css"] = basename($filename);
    }
        $rdesigns = array(new AdminerDesigns($designs),);

    return new AdminerPlugin(array_merge($plugins, $rdesigns));
}

// include original Adminer or Adminer Editor
include "./adminer-4.7.7.php";
?> 
