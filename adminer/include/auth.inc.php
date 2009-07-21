<?php
$ignore = array("server", "username", "password");
$session_name = session_name();
if (ini_get("session.use_trans_sid") && isset($_POST[$session_name])) {
	$ignore[] = $session_name;
}
if (isset($_POST["server"])) {
	if (isset($_COOKIE[$session_name]) || isset($_POST[$session_name])) {
		session_regenerate_id(); // defense against session fixation
		$_SESSION["usernames"][$_POST["server"]] = $_POST["username"];
		$_SESSION["passwords"][$_POST["server"]] = $_POST["password"];
		$_SESSION["tokens"][$_POST["server"]] = rand(1, 1e6); // defense against cross-site request forgery
		if (count($_POST) == count($ignore)) {
			$location = ((string) $_GET["server"] === $_POST["server"] ? remove_from_uri() : preg_replace('~^[^?]*/([^?]*).*~', '\\1', $_SERVER["REQUEST_URI"]) . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : ''));
			if (!isset($_COOKIE[$session_name])) {
				$location .= (strpos($location, "?") === false ? "?" : "&") . SID;
			}
			header("Location: " . (strlen($location) ? $location : "."));
			exit;
		}
		if ($_POST["token"]) {
			$_POST["token"] = $_SESSION["tokens"][$_POST["server"]];
		}
	}
	$_GET["server"] = $_POST["server"];
} elseif (isset($_POST["logout"])) {
	if ($_POST["token"] != $_SESSION["tokens"][$_GET["server"]]) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("usernames", "passwords", "databases", "tokens", "history") as $val) {
			unset($_SESSION[$val][$_GET["server"]]);
		}
		redirect(substr($SELF, 0, -1), lang('Logout successful.'));
	}
}

function auth_error($exception = null) {
	global $ignore, $dbh;
	$username = $_SESSION["usernames"][$_GET["server"]];
	unset($_SESSION["usernames"][$_GET["server"]]);
	page_header(lang('Login'), (isset($username) ? htmlspecialchars($exception ? $exception->getMessage() : (is_string($dbh) ? $dbh : lang('Invalid credentials.'))) : (isset($_POST["server"]) ? lang('Sessions must be enabled.') : ($_POST ? lang('Session expired, please login again.') : ""))), null);
	echo "<form action='' method='post'>\n";
	adminer_login_form($login);
	echo "<p>\n";
	hidden_fields($_POST, $ignore); // expired session
	foreach ($_FILES as $key => $val) {
		echo '<input type="hidden" name="files[' . htmlspecialchars($key) . ']" value="' . ($val["error"] ? $val["error"] : base64_encode(file_get_contents($val["tmp_name"]))) . '">';
	}
	echo "<input type='submit' value='" . lang('Login') . "'>\n</form>\n";
	page_footer("auth");
}

$username = &$_SESSION["usernames"][$_GET["server"]];
if (!isset($username)) {
	$username = $_GET["username"]; // default username can be passed in URL
}
$dbh = (isset($username) ? connect() : '');
if (is_string($dbh) || !adminer_login($username, $_SESSION["passwords"][$_GET["server"]])) {
	auth_error();
	exit;
}
unset($username);
