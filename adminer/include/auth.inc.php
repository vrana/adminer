<?php
$ignore = array("server", "username", "password");
if (isset($_POST["server"])) {
	session_regenerate_id(); // defense against session fixation
	$_SESSION["usernames"][$_POST["server"]] = $_POST["username"];
	$_SESSION["passwords"][$_POST["server"]] = $_POST["password"];
	if (count($_POST) == count($ignore)) {
		$location = ((string) $_GET["server"] === $_POST["server"] ? remove_from_uri() : preg_replace('~^([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : ''));
		if (!isset($_COOKIE[session_name()])) {
			$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
		}
		redirect($location);
	}
	$_GET["server"] = $_POST["server"];
} elseif (isset($_POST["logout"])) {
	$token = $_SESSION["tokens"][$_GET["server"]];
	if ($token && $_POST["token"] != $token) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("usernames", "passwords", "databases", "tokens", "history") as $val) {
			unset($_SESSION[$val][$_GET["server"]]);
		}
		if (!isset($_SESSION["passwords"])) { // don't require login to logout
			$_SESSION["passwords"] = array();
		}
		redirect(substr(ME, 0, -1), lang('Logout successful.'));
	}
}

function auth_error($exception = null) {
	global $ignore, $connection, $adminer;
	$session_name = session_name();
	$username = $_SESSION["usernames"][$_GET["server"]];
	unset($_SESSION["usernames"][$_GET["server"]]);
	page_header(lang('Login'), (isset($username) ? h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')))
		: (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_get("session.use_only_cookies") ? lang('Session support must be enabled.')
		: (($_COOKIE[$session_name] || $_GET[$session_name]) && !isset($_SESSION["passwords"]) ? lang('Session expired, please login again.')
	: ""))), null);
	echo "<form action='' method='post'>\n";
	$adminer->loginForm($username);
	echo "<p>\n";
	hidden_fields($_POST, $ignore); // expired session
	foreach ($_FILES as $key => $val) {
		echo '<input type="hidden" name="files[' . h($key) . ']" value="' . ($val["error"] ? $val["error"] : base64_encode(file_get_contents($val["tmp_name"]))) . '">';
	}
	echo "<input type='submit' value='" . lang('Login') . "'>\n</form>\n";
	page_footer("auth");
}

if (!$_SESSION["tokens"][$_GET["server"]]) {
	$_SESSION["tokens"][$_GET["server"]] = rand(1, 1e6); // defense against cross-site request forgery
	if ($_POST["token"]) {
		$_POST["token"] = $_SESSION["tokens"][$_GET["server"]];
	}
}

$username = &$_SESSION["usernames"][$_GET["server"]];
if (!isset($username)) {
	$username = $_GET["username"]; // default username can be passed in URL
}
$connection = (isset($username) ? connect() : '');
if (is_string($connection) || !$adminer->login($username, $_SESSION["passwords"][$_GET["server"]])) {
	auth_error();
	exit;
}
unset($username);
