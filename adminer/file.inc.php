<?php
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");

if ($_GET["file"] == "favicon.ico") {
	header("Content-Type: image/x-icon");
	echo 'compile_file("../adminer/static/favicon.ico", "add_apo_slashes");';
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	?>compile_file("../adminer/static/default.css", "minify_css");<?php
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	?>compile_file("../adminer/static/functions.js", "jsShrink");compile_file("static/editing.js", "jsShrink");<?php
} else {
	header("Content-Type: image/gif");
	switch ($_GET["file"]) {
		case "plus.gif": echo 'compile_file("../adminer/static/plus.gif", "add_apo_slashes");'; break;
		case "cross.gif": echo 'compile_file("../adminer/static/cross.gif", "add_apo_slashes");'; break;
		case "up.gif": echo 'compile_file("../adminer/static/up.gif", "add_apo_slashes");'; break;
		case "down.gif": echo 'compile_file("../adminer/static/down.gif", "add_apo_slashes");'; break;
		case "arrow.gif": echo 'compile_file("../adminer/static/arrow.gif", "add_apo_slashes");'; break;
	}
}
exit;
