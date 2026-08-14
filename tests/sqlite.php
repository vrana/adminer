<?php
// Entry point for tests/sqlite.spec.js, the sources are in a sibling directory.

chdir(__DIR__ . "/../adminer"); // the pages are included relative to the working directory
define('Adminer\DIR', "../adminer/"); // used also in the URLs of the static files

function adminer_object() {
	return new Adminer\Plugins(array(
		new Adminer\Password('$2y$12$lFfTcGjzW1aO3wkgnLyE8uZfuUwmkrwXcZQwCh0qQLgawYWKJQKlm'), // password_hash() of YOUR_PASSWORD_HERE typed by the tests
	));
}

include "./index.php";
