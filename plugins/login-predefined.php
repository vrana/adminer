<?php

/** Login with predefined user set in env vars
* @link https://www.adminer.org/plugins/#use
* @author Rophy Tsai <rophy123@gmail.com>
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
*/
class PredefinedLogin {

	function credentials() {
		return array(SERVER, $_ENV["ADMINER_PREDEFINED_USER"], $_ENV["ADMINER_PREDEFINED_PASSWORD"]);
	}

	function loginFormField($name, $heading, $value) {
		if ($name == 'username' || $name == 'password') $value = 'Predefined <input type="hidden" name="auth['.$name.']" value="predefined">';
		return $heading . $value;
	}

}
