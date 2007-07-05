<?php
if (isset($_POST["server"])) {
	$_SESSION["username"] = $_POST["username"];
	$_SESSION["password"] = $_POST["password"];
	header("Location: " . ((string) $_GET["server"] === $_POST["server"] ? preg_replace('~(\\?)logout=&|[?&]logout=~', '\\1', $_SERVER["REQUEST_URI"]) : preg_replace('~^[^?]*/([^?]*).*~', '\\1' . (strlen($_POST["server"]) ? '?server=' . urlencode($_POST["server"]) : '') . (strlen(SID) ? (strlen($_POST["server"]) ? "&" : "?") . SID : ""), $_SERVER["REQUEST_URI"])));
	exit;
} elseif (isset($_GET["logout"])) {
	unset($_SESSION["username"]);
	unset($_SESSION["password"]);
}

if (isset($_GET["logout"]) || !@mysql_connect($_GET["server"], $_SESSION["username"], $_SESSION["password"])) {
	page_header(lang('Login'), "auth");
	if (isset($_GET["logout"])) {
		echo "<p class='message'>" . lang('Logout successful.') . "</p>\n";
	} elseif (isset($_SESSION["username"])) {
		echo "<p class='error'>" . lang('Invalid credentials.') . "</p>\n";
	}
	?>
	<form action="" method="post">
	<table border="0" cellspacing="0" cellpadding="2">
	<tr><th><?php echo lang('Server'); ?>:</th><td><input name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" maxlength="60" /></td></tr>
	<tr><th><?php echo lang('Username'); ?>:</th><td><input name="username" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" maxlength="16" /></td></tr>
	<tr><th><?php echo lang('Password'); ?>:</th><td><input type="password" name="password" /></td></tr>
	<tr><th><?php
	foreach ($_POST as $key => $val) { // expired session
		if (!is_array($val)) {
			echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '" />';
		} else {
			foreach ($val as $key2 => $val2) {
				if (!is_array($val2)) {
					echo '<input type="hidden" name="' . htmlspecialchars($key . "[$key2]") . ' value="' . htmlspecialchars($val2) . '" />';
				} else {
					foreach ($val2 as $key3 => $val3) {
						echo '<input type="hidden" name="' . htmlspecialchars($key . "[$key2][$key3]") . ' value="' . htmlspecialchars($val3) . '" />';
					}
				}
			}
		}
	}
	?>
	</th><td><input type="submit" value="<?php echo lang('Login'); ?>" /></td></tr>
	</table>
	</form>
	<?php
	page_footer("auth");
	exit;
}
