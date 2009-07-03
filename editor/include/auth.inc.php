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
