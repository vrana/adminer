<?php
function adminer_object() {
	include_once "../plugins/plugin.php";
	include_once "../plugins/designs.php";
	$designs = array();
	foreach (glob("../designs/*", GLOB_ONLYDIR) as $dirname) {
		foreach (array("", "-dark") as $mode) {
			$filename = "$dirname/adminer$mode.css";
			if (file_exists($filename)) {
				$designs[$filename] = basename($dirname);
			}
		}
	}
	return new AdminerPlugin(array(
		new AdminerDesigns($designs),
	));
}

include "./index.php";
