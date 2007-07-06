<?php
if (isset($_POST["server"])) {
	session_regenerate_id();
	$_SESSION["usernames"][$_POST["server"]] = $_POST["username"];
	$_SESSION["passwords"][$_POST["server"]] = $_POST["password"];
	if (count($_POST) == 3) {
		header("Location: " . ((string) $_GET["server"] === $_POST["server"] ? preg_replace('~(\\?)logout=&|[?&]logout=~', '\\1', $_SERVER["REQUEST_URI"]) : preg_replace('~^[^?]*/([^?]*).*~', '\\1' . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : '') . (strlen(SID) ? (strlen($_POST["server"]) ? "&" : "?") . SID : ""), $_SERVER["REQUEST_URI"])));
		exit;
	}
	$_GET["server"] = $_POST["server"];
} elseif (isset($_GET["logout"])) {
	unset($_SESSION["usernames"][$_GET["server"]]);
	unset($_SESSION["passwords"][$_GET["server"]]);
}

$username = $_SESSION["usernames"][$_GET["server"]];
$password = $_SESSION["passwords"][$_GET["server"]];
if (isset($_GET["logout"]) || !@mysql_connect(
	(strlen($_GET["server"]) ? $_GET["server"] : ini_get("mysql.default_host")),
	(strlen("$_GET[server]$username") ? $username : ini_get("mysql.default_user")),
	(strlen("$_GET[server]$username$password") ? $password : ini_get("mysql.default_password")))
) {
	page_header(lang('Login'));
	if (isset($_GET["logout"])) {
		echo "<p class='message'>" . lang('Logout successful.') . "</p>\n";
	} elseif (isset($_SESSION["usernames"][$_GET["server"]])) {
		echo "<p class='error'>" . lang('Invalid credentials.') . "</p>\n";
	}
	?>
	<form action="" method="post">
	<table border="0" cellspacing="0" cellpadding="2">
	<tr><th><?php echo lang('Server'); ?>:</th><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" maxlength="60" /></td></tr>
	<tr><th><?php echo lang('Username'); ?>:</th><td><input name="username" value="<?php echo htmlspecialchars($_SESSION["usernames"][$_GET["server"]]); ?>" maxlength="16" /></td></tr>
	<tr><th><?php echo lang('Password'); ?>:</th><td><input type="password" name="password" /></td></tr>
	<tr><th><?php
	foreach ($_POST as $key => $val) { // expired session
		if (is_array($val)) {
			foreach ($val as $key2 => $val2) {
				if (!is_array($val2)) {
					echo '<input type="hidden" name="' . htmlspecialchars($key . "[$key2]") . ' value="' . htmlspecialchars($val2) . '" />';
				} else {
					foreach ($val2 as $key3 => $val3) {
						echo '<input type="hidden" name="' . htmlspecialchars($key . "[$key2][$key3]") . ' value="' . htmlspecialchars($val3) . '" />';
					}
				}
			}
		} elseif ($key != "server" && $key != "username" && $key != "password") {
			echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
		}
	}
	?></th><td><input type="submit" value="<?php echo lang('Login'); ?>" /></td></tr>
	</table>
	</form>
	<?php
	page_footer("auth");
	exit;
}
mysql_query("SET SQL_QUOTE_SHOW_CREATE=1");
