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

if (isset($_POST["server"])) {
	session_regenerate_id(); // defense against session fixation
	$_SESSION["pwds"][$_POST["driver"]][$_POST["server"]][$_POST["username"]] = $_POST["password"];
	if ($_POST["permanent"]) {
		$key = base64_encode($_POST["driver"]) . "-" . base64_encode($_POST["server"]) . "-" . base64_encode($_POST["username"]);
		$private = $adminer->permanentLogin();
		$permanent[$key] = "$key:" . base64_encode($private ? encrypt_string($_POST["password"], $private) : "");
		cookie("adminer_permanent", implode(" ", $permanent));
	}
	if (count($_POST) == ($_POST["permanent"] ? 5 : 4) // 4 - driver, server, username, password
		|| DRIVER != $_POST["driver"]
		|| SERVER != $_POST["server"]
		|| $_GET["username"] !== $_POST["username"] // "0" == "00"
	) {
		redirect(auth_url($_POST["driver"], $_POST["server"], $_POST["username"]));
	}
} elseif ($_POST["logout"]) {
	if ($token && $_POST["token"] != $token) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("pwds", "dbs", "queries") as $key) {
			set_session($key, null);
		}
		$key = base64_encode(DRIVER) . "-" . base64_encode(SERVER) . "-" . base64_encode($_GET["username"]);
		if ($permanent[$key]) {
			unset($permanent[$key]);
			cookie("adminer_permanent", implode(" ", $permanent));
		}
		redirect(substr(preg_replace('~(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang('Logout successful.'));
	}
} elseif ($permanent && !$_SESSION["pwds"]) {
	session_regenerate_id();
	$private = $adminer->permanentLogin(); // try to decode even if not set
	foreach ($permanent as $key => $val) {
		list(, $cipher) = explode(":", $val);
		list($driver, $server, $username) = array_map('base64_decode', explode("-", $key));
		$_SESSION["pwds"][$driver][$server][$username] = decrypt_string(base64_decode($cipher), $private);
	}
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
			if (isset($password)) {
				$error = h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')));
				$password = null;
			}
		}
	}
	page_header(lang('Login'), $error, null);
	echo "<form action='' method='post' onclick='eventStop(event);'>\n";
	$adminer->loginForm();
	echo "<div>";
	hidden_fields($_POST, array("driver", "server", "username", "password", "permanent")); // expired session
	echo "</div>\n";
	echo "</form>\n";
	page_footer("auth");
}

if (isset($_GET["username"])) {
	if (!class_exists("Min_DB")) {
		unset($_SESSION["pwds"][DRIVER]); //! remove also from adminer_permanent
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
if (isset($_POST["server"]) && $_POST["token"]) {
	$_POST["token"] = $token; // reset token after explicit login
}
$error = ($_POST ///< @var string
	? ($_POST["token"] == $token ? "" : lang('Invalid CSRF token. Send the form again.'))
	: ($_SERVER["REQUEST_METHOD"] != "POST" ? "" : lang('Too big POST data. Reduce the data or increase the %s configuration directive.', '"post_max_size"')) // posted form with no data means that post_max_size exceeded because Adminer always sends token at least
);
