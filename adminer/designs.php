<?php
function adminer_object() {
	include_once "../plugins/plugin.php";
	include_once "../plugins/designs.php";
	$designs = array();
	foreach (glob("../designs/*", GLOB_ONLYDIR) as $filename) {
		$designs["$filename/adminer.css"] = basename($filename);
	}
	return new AdminerPlugin(array(
		new AdminerDesigns($designs),
	));
}

include "./index.php";
