<?php
function adminer_object() {
	include_once "../plugins/designs.php";
	$designs = array();
	foreach (glob("../designs/*/*.css") as $filename) {
		$designs[$filename] = basename(dirname($filename));
	}
	return new Adminer\Plugins(array(
		new AdminerDesigns($designs),
	));
}

include "./index.php";
