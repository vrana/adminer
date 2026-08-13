<?php
function adminer_object() {
	return new Adminer\Plugins(array(
		// TODO: inline the result of password_hash() so that the password is not visible in source codes
		new Adminer\Password(password_hash("YOUR_PASSWORD_HERE", PASSWORD_DEFAULT)),
	));
}

include "./index.php";
