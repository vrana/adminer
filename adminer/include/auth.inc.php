<?php
if (isset($_POST["server"])) {
	session_regenerate_id(); // defense against session fixation
	$_SESSION["usernames"][$_POST["server"]] = $_POST["username"];
	$_SESSION["passwords"][$_POST["server"]] = $_POST["password"];
	if ($_POST["permanent"]) {
		cookie("adminer_permanent",
			base64_encode($_POST["server"])
			. ":" . base64_encode($_POST["username"])
			. ":" . base64_encode(encrypt_string($_POST["password"], $adminer->permanentLogin()))
		);
	}
	if (count($_POST) == ($_POST["permanent"] ? 4 : 3)) { // 3 - server, username, password
		$location = ((string) $_GET["server"] === $_POST["server"] ? remove_from_uri(session_name()) : preg_replace('~^([^?]*).*~', '\\1', ME) . ($_POST["server"] != "" ? '?server=' . urlencode($_POST["server"]) : ''));
		if (SID_FORM) {
			$pos = strpos($location, '?');
			$location = ($pos ? substr_replace($location, SID . "&", $pos + 1, 0) : "$location?" . SID);
		}
		redirect($location);
	}
	$_GET["server"] = $_POST["server"]; //! used also in ME
} elseif ($_POST["logout"]) {
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
		cookie("adminer_permanent", "");
		redirect(substr(preg_replace('~db=[^&]*&~', '', ME), 0, -1), lang('Logout successful.'));
	}
} elseif ($_COOKIE["adminer_permanent"] && !isset($_SESSION["usernames"][$_GET["server"]])) {
	list($server, $username, $cipher) = array_map('base64_decode', explode(":", $_COOKIE["adminer_permanent"]));
	if (($_GET["server"] == "" && !$_POST) || $server == $_GET["server"]) {
		session_regenerate_id(); // defense against session fixation
		$_SESSION["usernames"][$server] = $username;
		$_SESSION["passwords"][$server] = decrypt_string($cipher, $adminer->permanentLogin());
		if ($server != $_GET["server"]) {
			redirect(preg_replace('~^([^?]*).*~', '\\1', ME) . '?server=' . urlencode($server));
		}
	}
}

function auth_error($exception = null) {
	global $connection, $adminer;
	$session_name = session_name();
	$username = $_SESSION["usernames"][$_GET["server"]];
	unset($_SESSION["usernames"][$_GET["server"]]);
	page_header(lang('Login'), (isset($username) ? h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')))
		: (!$_COOKIE[$session_name] && $_GET[$session_name] && ini_get("session.use_only_cookies") ? lang('Session support must be enabled.')
		: (($_COOKIE[$session_name] || $_GET[$session_name]) && !isset($_SESSION["passwords"]) ? lang('Session expired, please login again.')
	: ""))), null);
	echo "<form action='' method='post'>\n";
	$adminer->loginForm($username);
	echo "<div>";
	hidden_fields($_POST, array("server", "username", "password")); // expired session
	echo "</div>\n";
	echo "</form>\n";
	page_footer("auth");
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

if (!$_SESSION["tokens"][$_GET["server"]]) {
	$_SESSION["tokens"][$_GET["server"]] = rand(1, 1e6); // defense against cross-site request forgery
}
if (isset($_POST["server"]) && $_POST["token"]) {
	$_POST["token"] = $_SESSION["tokens"][$_GET["server"]];
}
$token = $_SESSION["tokens"][$_GET["server"]]; ///< @var string CSRF protection
$error = ($_POST ///< @var string
	? ($_POST["token"] == $token ? "" : lang('Invalid CSRF token. Send the form again.'))
	: ($_SERVER["REQUEST_METHOD"] != "POST" ? "" : lang('Too big POST data. Reduce the data or increase the %s configuration directive.', '"post_max_size"')) // posted form with no data means that post_max_size exceeded because Adminer always sends token at least
);
