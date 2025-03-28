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

if ($_GET["file"] == "favicon.ico") {
	header("Content-Type: image/x-icon");
	echo lzw_decompress(compile_file('../adminer/static/favicon.ico', 'lzw_compress'));
} elseif ($_GET["file"] == "default.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress(compile_file('../adminer/static/default.css;../externals/jush/jush.css', 'minify_css'));
} elseif ($_GET["file"] == "dark.css") {
	header("Content-Type: text/css; charset=utf-8");
	echo lzw_decompress(compile_file('../adminer/static/dark.css;../externals/jush/jush-dark.css', 'minify_css'));
} elseif ($_GET["file"] == "functions.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress(compile_file('../adminer/static/functions.js;static/editing.js', 'minify_js'));
} elseif ($_GET["file"] == "jush.js") {
	header("Content-Type: text/javascript; charset=utf-8");
	echo lzw_decompress(compile_file('../externals/jush/modules/jush.js;
../externals/jush/modules/jush-textarea.js;
../externals/jush/modules/jush-txt.js;
../externals/jush/modules/jush-js.js;
../externals/jush/modules/jush-sql.js;
../externals/jush/modules/jush-pgsql.js;
../externals/jush/modules/jush-sqlite.js;
../externals/jush/modules/jush-mssql.js;
../externals/jush/modules/jush-oracle.js;
../externals/jush/modules/jush-simpledb.js', 'minify_js'));
}
exit;
