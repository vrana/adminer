<?php
// Entry point for tests/elastic.spec.js, the sources are in a sibling directory.
// To create Adminer just for Elasticsearch, run `../compile.php elastic`.

chdir(__DIR__ . "/../adminer"); // the pages are included relative to the working directory
if (strpos(file_get_contents("index.php"), 'Adminer\DIR') !== false) { // a compiled index.php doesn't use it
	define('Adminer\DIR', "../adminer/"); // used also in the URLs of the static files
}

function adminer_object() {
	include_once "../plugins/drivers/elastic.php";
	return new Adminer\Plugins(array(
		new Adminer\Password('$2y$08$M1tB8YNu9lmg8Tl6filA5eaHQrRSIchz9wy6Mh/Nza59ZZyzIjXo6'), // password_hash() of YOUR_PASSWORD_HERE typed by the tests
	));
}

include "./index.php";
