<?php
namespace Adminer;

if (substr(VERSION, -4) != '-dev') {
	if ($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
		header("HTTP/1.1 304 Not Modified");
		exit;
	}
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + 365*24*60*60) . " GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: immutable");
}

ini_set("zlib.output_compression", '1');

if ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo decompress_string(compile_file('../adminer/static/default.css;../adminer/static/jush/jush.css', 'minify_css'));
} elseif ($_GET["file"] == "dark.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo decompress_string(compile_file('../adminer/static/dark.css;../adminer/static/jush/jush-dark.css', 'minify_css'));
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo decompress_string(compile_file('../adminer/static/functions.js;static/editing.js', 'minify_js'));
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo decompress_string(compile_file('../adminer/static/jush/modules/jush.js;
../adminer/static/jush/modules/jush-autocomplete-sql.js;
../adminer/static/jush/modules/jush-textarea.js;
../adminer/static/jush/modules/jush-txt.js;
../adminer/static/jush/modules/jush-json.js;
../adminer/static/jush/modules/jush-sql.js;
../adminer/static/jush/modules/jush-pgsql.js;
../adminer/static/jush/modules/jush-sqlite.js;
../adminer/static/jush/modules/jush-mssql.js;
../adminer/static/jush/modules/jush-oracle.js', 'minify_js'));
} elseif ($_GET["file"] == "logo.png") {
	header("Content-Type: image/png");
	echo compile_file('../adminer/static/logo.png');
}
exit;
