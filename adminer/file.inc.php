<?php
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");

if ($_GET["file"] == "favicon.ico") {
	header("Content-Type: image/x-icon");
	echo base64_decode("compile_file('../adminer/static/favicon.ico', 'base64_encode');");
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	?>compile_file('../adminer/static/default.css', 'minify_css');<?php
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	?>compile_file('../adminer/static/functions.js', 'JSMin::minify');compile_file('static/editing.js', 'JSMin::minify');<?php
} else {
	header("Content-Type: image/gif");
	switch ($_GET["file"]) {
		case "plus.gif": echo base64_decode("compile_file('../adminer/static/plus.gif', 'base64_encode');"); break;
		case "cross.gif": echo base64_decode("compile_file('../adminer/static/cross.gif', 'base64_encode');"); break;
		case "up.gif": echo base64_decode("compile_file('../adminer/static/up.gif', 'base64_encode');"); break;
		case "down.gif": echo base64_decode("compile_file('../adminer/static/down.gif', 'base64_encode');"); break;
		case "arrow.gif": echo base64_decode("compile_file('../adminer/static/arrow.gif', 'base64_encode');"); break;
		case "loader.gif": echo base64_decode("compile_file('../adminer/static/loader.gif', 'base64_encode');"); break;
	}
}
exit;
