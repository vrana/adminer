<?php
function auth_error($exception = null) {
	page_header(lang('Login'), htmlspecialchars(lang('Invalid credentials.'), null));
	page_footer("auth");
}

$dbh = connect();
if (is_string($dbh)) {
	auth_error();
	exit;
}
$_SESSION["tokens"][$_GET["server"]] = rand(1, 1e6); // defense against cross-site request forgery
