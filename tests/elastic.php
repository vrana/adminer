<?php
// Entry point for tests/elastic.spec.js, the sources are in a sibling directory.
// To create Adminer just for Elasticsearch, run `../compile.php elastic`.

chdir(__DIR__ . "/../adminer"); // the pages are included relative to the working directory
define('Adminer\DIR', "../adminer/"); // used also in the URLs of the static files

function adminer_object() {
	include_once "../plugins/drivers/elastic.php";
	return new Adminer\Plugins(array(
		new Adminer\Password('$2y$12$lFfTcGjzW1aO3wkgnLyE8uZfuUwmkrwXcZQwCh0qQLgawYWKJQKlm'), // password_hash() of YOUR_PASSWORD_HERE typed by the tests
	));
}

include "./index.php";
