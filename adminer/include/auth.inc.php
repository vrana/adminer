<?php
$connection = '';

if (!$drivers) {
	page_header(lang('No extension'), lang('None of the supported PHP extensions (%s) are available.', implode(", ", $possible_drivers)), null);
	page_footer("auth");
	exit;
}

$token = $_SESSION["token"];
if (!$_SESSION["token"]) {
	$_SESSION["token"] = rand(1, 1e6); // defense against cross-site request forgery
}

if (isset($_POST["server"])) {
	session_regenerate_id(); // defense against session fixation
	$_SESSION["passwords"][$_POST["driver"]][$_POST["server"]][$_POST["username"]] = $_POST["password"];
	if ($_POST["permanent"]) {
		cookie("adminer_permanent", //! store separately for each driver, server and username to allow several permanent logins
			base64_encode($_POST["server"])
			. ":" . base64_encode($_POST["username"])
			. ":" . base64_encode(encrypt_string($_POST["password"], $adminer->permanentLogin()))
			. ":" . base64_encode($_POST["driver"])
		);
	}
	if (count($_POST) == ($_POST["permanent"] ? 5 : 4) // 4 - driver, server, username, password
		|| DRIVER != $_POST["driver"]
		|| SERVER != $_POST["server"]
		|| $_GET["username"] !== $_POST["username"] // "0" == "00"
	) {
		preg_match('~([^?]*)\\??(.*)~', remove_from_uri(implode("|", array_keys($drivers)) . "|username|" . session_name()), $match);
		redirect("$match[1]?"
			. (SID ? SID . "&" : "")
			. ($_POST["driver"] != "server" || $_POST["server"] != "" ? urlencode($_POST["driver"]) . "=" . urlencode($_POST["server"]) . "&" : "")
			. "username=" . urlencode($_POST["username"])
			. ($match[2] ? "&$match[2]" : "")
		);
	}
} elseif ($_POST["logout"]) {
	if ($token && $_POST["token"] != $token) {
		page_header(lang('Logout'), lang('Invalid CSRF token. Send the form again.'));
		page_footer("db");
		exit;
	} else {
		foreach (array("passwords", "databases", "history") as $key) {
			set_session($key, null);
		}
		cookie("adminer_permanent", "");
		redirect(substr(preg_replace('~(username|db|ns)=[^&]*&~', '', ME), 0, -1), lang('Logout successful.'));
	}
} elseif ($_COOKIE["adminer_permanent"]) {
	list($server, $username, $cipher, $system) = array_map('base64_decode', explode(":", $_COOKIE["adminer_permanent"])); // $driver is a global variable
	if ($server == SERVER && $username === $_GET["username"] && $system == DRIVER) {
		session_regenerate_id(); // defense against session fixation
		set_session("passwords", decrypt_string($cipher, $adminer->permanentLogin()));
	}
	//! redirect ?select=tab
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
			$password = get_session("passwords");
			if (isset($password)) {
				$error = h($exception ? $exception->getMessage() : (is_string($connection) ? $connection : lang('Invalid credentials.')));
			}
		}
	}
	page_header(lang('Login'), $error, null);
	echo "<form action='' method='post'>\n";
	$adminer->loginForm();
	echo "<div>";
	hidden_fields($_POST, array("driver", "server", "username", "password", "permanent")); // expired session
	echo "</div>\n";
	echo "</form>\n";
	page_footer("auth");
}

if (isset($_GET["username"]) && class_exists("Min_DB")) { // doesn't exists with passing wrong driver
	$connection = connect();
}
if (is_string($connection) || !$adminer->login($_GET["username"], get_session("passwords"))) {
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
