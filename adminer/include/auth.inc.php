<?php
$connection = '';

$has_token = $_SESSION["token"];
if (!$has_token) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}
$token = get_token(); ///< @var string CSRF protection

$permanent = array();
if ($_COOKIE["adminer_permanent"]) {
	foreach (explode(" ", $_COOKIE["adminer_permanent"]) as $val) {
		list($key) = explode(":", $val);
		$permanent[$key] = $val;
	}
}

$auth = $_POST["auth"];
if ($auth) {
	session_regenerate_id(); // defense against session fixation
	$driver = $auth["driver"];
	$server = $auth["server"];
	$username = $auth["username"];
	$password = $auth["password"];
	$db = $auth["db"];
	set_password($driver, $server, $username, $password);
	$_SESSION["db"][$driver][$server][$username][$db] = true;
	if ($auth["permanent"]) {
		$key = base64_encode($driver) . "-" . base64_encode($server) . "-" . base64_encode($username) . "-" . base64_encode($db);
		$private = $adminer->permanentLogin(true);
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($password, $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (count($_POST) == 1 // 1 - auth
		|| DRIVER != $driver
		|| SERVER != $server
		|| $_GET["username"] !== $username // "0" == "00"
		|| DB != $db
	) {
		redirect(auth_url($driver, $server, $username, $db));
	}
	
} elseif ($_POST["logout"]) {
	if ($has_token && !verify_token()) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("pwds", "db", "dbs", "queries") as $key) {
			set_session($key, null);
		}
		unset_permanent();
		redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang('Logout successful.'));
	}
	
} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = $adminer->permanentLogin();
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		set_password($vendor, $server, $username, decrypt_string(base64_decode($cipher), $private));
		$_SESSION["db"][$vendor][$server][$username][$db] = true;
	}
}

function unset_permanent() {
	global $permanent;
	foreach ($permanent as $key => $val) {
		list($vendor, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		if ($vendor == DRIVER && $server == SERVER && $username == $_GET["username"] && $db == DB) {
			unset($permanent[$key]);
		}
	}
	cookie("adminer_permanent", implode(" ", $permanent));
}

function auth_error($exception = null) {
	global $connection, $adminer, $has_token;
	$session_name = session_name();
	$error = "";
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = lang('Session support must be enabled.');
	} elseif (isset($_GET["username"])) {
		if (($_COOKIE[$session_name] || $_GET[$session_name]) && !$has_token) {
			$error = lang('Session expired, please login again.');
		} else {
			$password = get_password();
			if ($password !== null) {
				$error = h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')));
				if ($password === false) {
					$error .= '<br>' . lang('Master password expired. <a href="http://www.adminer.org/en/extension/" target="_blank">Implement</a> %s method to make it permanent.', '<code>permanentLogin()</code>');
				}
				set_password(DRIVER, SERVER, $_GET["username"], null);
			}
			unset_permanent();
		}
	}
	$params = session_get_cookie_params();
	cookie("adminer_key", ($_COOKIE["adminer_key"] ? $_COOKIE["adminer_key"] : rand_string()), $params["lifetime"]);
	page_header(lang('Login'), $error, null);
	echo "<form action='' method='post'>\n";
	$adminer->loginForm();
	echo "<div>";
	hidden_fields($_POST, array("auth")); // expired session
	echo "</div>\n";
	echo "</form>\n";
	page_footer("auth");
}

function set_password($vendor, $server, $username, $password) {
	$_SESSION["pwds"][$vendor][$server][$username] = ($_COOKIE["adminer_key"]
		? array(encrypt_string($password, $_COOKIE["adminer_key"]))
		: $password
	);
}

function get_password() {
	$return = get_session("pwds");
	if (is_array($return)) {
		$return = ($_COOKIE["adminer_key"]
			? decrypt_string($return[0], $_COOKIE["adminer_key"])
			: false
		);
	}
	return $return;
}

if (isset($_GET["username"])) {
	if (!class_exists("Min_DB")) {
		unset($_SESSION["pwds"][DRIVER]);
		unset_permanent();
		page_header(lang('No extension'), lang('None of the supported PHP extensions (%s) are available.', implode(", ", $possible_drivers)), false);
		page_footer("auth");
		exit;
	}
	$connection = connect();
}

if (!is_object($connection) || !$adminer->login($_GET["username"], get_password())) {
	auth_error();
	exit;
}

$driver = new Min_Driver($connection);

if ($auth && $_POST["token"]) {
	$_POST["token"] = $token; // reset token after explicit login
}

$error = ''; ///< @var string
if ($_POST) {
	if (!verify_token()) {
		$ini = "max_input_vars";
		$max_vars = ini_get($ini);
		if (extension_loaded("suhosin")) {
			foreach (array("suhosin.request.max_vars", "suhosin.post.max_vars") as $key) {
				$val = ini_get($key);
				if ($val && (!$max_vars || $val < $max_vars)) {
					$ini = $key;
					$max_vars = $val;
				}
			}
		}
		$error = (!$_POST["token"] && $max_vars
			? lang('Maximum number of allowed fields exceeded. Please increase %s.', "'$ini'")
			: lang('Invalid CSRF token. Send the form again.')
		);
	}
	
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
	// posted form with no data means that post_max_size exceeded because Adminer always sends token at least
	$error = lang('Too big POST data. Reduce the data or increase the %s configuration directive.', "'post_max_size'");
	if (isset($_GET["sql"])) {
		$error .= ' ' . lang('You can upload a big SQL file via FTP and import it from server.');
	}
}
