<?php
$connection = '';

$token = $_SESSION["token"];
if (!$_SESSION["token"]) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}

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
	$_SESSION["pwds"][$auth["driver"]][$auth["server"]][$auth["username"]] = $auth["password"];
	$_SESSION["db"][$auth["driver"]][$auth["server"]][$auth["username"]][$auth["db"]] = true;
	if ($auth["permanent"]) {
		$key = base64_encode($auth["driver"]) . "-" . base64_encode($auth["server"]) . "-" . base64_encode($auth["username"]) . "-" . base64_encode($auth["db"]);
		$private = $adminer->permanentLogin();
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($auth["password"], $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (count($_POST) == 1 // 1 - auth
		|| DRIVER != $auth["driver"]
		|| SERVER != $auth["server"]
		|| $_GET["username"] !== $auth["username"] // "0" == "00"
		|| DB != $auth["db"]
	) {
		redirect(auth_url($auth["driver"], $auth["server"], $auth["username"], $auth["db"]));
	}
} elseif ($_POST["logout"]) {
	if ($token && $_POST["token"] != $token) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("pwds", "db", "dbs", "queries") as $key) {
			set_session($key, null);
		}
		unset_permanent();
		redirect(substr(preg_replace('~(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang('Logout successful.'));
	}
} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = $adminer->permanentLogin(); // try to decode even if not set
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($driver, $server, $username, $db) = array_map('base64_decode', explode("-", $key));
		$_SESSION["pwds"][$driver][$server][$username] = decrypt_string(base64_decode($cipher), $private);
		$_SESSION["db"][$driver][$server][$username][$db] = true;
	}
}

function unset_permanent() {
	global $permanent;
	foreach ($permanent as $key => $val) {
		list($driver, $server, $username) = array_map('base64_decode', explode("-", $key));
		if ($driver == DRIVER && $server == SERVER && $db == $_GET["username"]) {
			unset($permanent[$key]);
		}
	}
	cookie("adminer_permanent", implode(" ", $permanent));
}

function auth_error($exception = null) {
	global $connection, $adminer, $token;
	$session_name = session_name();
	$error = "";
	if (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_bool("session.use_only_cookies")) {
		$error = lang('Session support must be enabled.');
	} elseif (isset($_GET["username"])) {
		if (($_COOKIE[$session_name] || $_GET[$session_name]) && !$token) {
			$error = lang('Session expired, please login again.');
		} else {
			$password = &get_session("pwds");
			if ($password !== null) {
				$error = h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')));
				$password = null;
			}
			unset_permanent();
		}
	}
	page_header(lang('Login'), $error, null);
	echo "<form action='' method='post'>\n";
	$adminer->loginForm();
	echo "<div>";
	hidden_fields($_POST, array("auth")); // expired session
	echo "</div>\n";
	echo "</form>\n";
	page_footer("auth");
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
if (is_string($connection) || !$adminer->login($_GET["username"], get_session("pwds"))) {
	auth_error();
	exit;
}

$token = $_SESSION["token"]; ///< @var string CSRF protection
if ($auth && $_POST["token"]) {
	$_POST["token"] = $token; // reset token after explicit login
}
$error = ($_POST ///< @var string
	? ($_POST["token"] == $token ? "" : lang('Invalid CSRF token. Send the form again.'))
	: ($_SERVER["REQUEST_METHOD"] != "POST" ? "" : lang('Too big POST data. Reduce the data or increase the %s configuration directive.', '"post_max_size"')) // posted form with no data means that post_max_size exceeded because Adminer always sends token at least
);
