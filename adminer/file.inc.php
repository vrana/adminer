<?php
// caching headers added in compile.php

if ($_GET["file"] == "favicon.ico") {
	header("Content-Type: image/x-icon");
	echo lzw_decompress(compile_file('../adminer/static/favicon.ico', 'lzw_compress'));
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress(compile_file('../adminer/static/default.css;../vendor/vrana/jush/jush.css', 'minify_css'));
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress(compile_file('../adminer/static/functions.js;static/editing.js', 'minify_js'));
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress(compile_file('../vendor/vrana/jush/modules/jush.js;../vendor/vrana/jush/modules/jush-textarea.js;../vendor/vrana/jush/modules/jush-txt.js;../vendor/vrana/jush/modules/jush-js.js;../vendor/vrana/jush/modules/jush-sql.js;../vendor/vrana/jush/modules/jush-pgsql.js;../vendor/vrana/jush/modules/jush-sqlite.js;../vendor/vrana/jush/modules/jush-mssql.js;../vendor/vrana/jush/modules/jush-oracle.js;../vendor/vrana/jush/modules/jush-simpledb.js', 'minify_js'));
} else {
	header("Content-Type: image/gif");
	switch ($_GET["file"]) {
		case "plus.gif": echo compile_file('../adminer/static/plus.gif'); break;
		case "cross.gif": echo compile_file('../adminer/static/cross.gif'); break;
		case "up.gif": echo compile_file('../adminer/static/up.gif'); break;
		case "down.gif": echo compile_file('../adminer/static/down.gif'); break;
		case "arrow.gif": echo compile_file('../adminer/static/arrow.gif'); break;
	}
}
exit;
