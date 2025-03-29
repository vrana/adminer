<?php
// To create Adminer just for Elasticsearch, run `../compile.php elastic`.

function adminer_object() {
	include_once "../plugins/login-password-less.php";
	include_once "../plugins/drivers/elastic.php";
	return new Adminer\Plugins(array(
			// TODO: inline the result of password_hash() so that the password is not visible in source codes
			new AdminerLoginPasswordLess(password_hash("YOUR_PASSWORD_HERE", PASSWORD_DEFAULT)),
	));
}

include "./index.php";
