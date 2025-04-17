<?php
namespace Adminer;

/** Print HTML header
* @param string $title used in title, breadcrumb and heading, should be HTML escaped
* @param mixed $breadcrumb ["key" => "link", "key2" => ["link", "desc"]], null for nothing, false for driver only, true for driver and server
* @param string $title2 used after colon in title and heading, should be HTML escaped
*/
function page_header(string $title, string $error = "", $breadcrumb = array(), string $title2 = ""): void {
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	if (!ob_get_level()) {
		ob_start('ob_gzhandler', 4096);
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . adminer()->name());
	// initial-scale=1 is the default but Chrome 134 on iOS is not able to zoom out without it
	?>
<!DOCTYPE html>
<html lang="<?php echo LANG; ?>" dir="<?php echo lang('ltr'); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" href="../adminer/static/default.css">
<?php

	$css = adminer()->css();
	if (is_int(key($css))) { // legacy return value
		$css = array_fill_keys($css, 'light');
	}
	$has_light = in_array('light', $css) || in_array('', $css);
	$has_dark = in_array('dark', $css) || in_array('', $css);
	$dark = ($has_light
		? ($has_dark ? null : false) // both styles - autoswitching, only adminer.css - light
		: ($has_dark ?: null) // only adminer-dark.css - dark, neither - autoswitching
	);
	$media = " media='(prefers-color-scheme: dark)'";
	if ($dark !== false) {
		echo "<link rel='stylesheet'" . ($dark ? "" : $media) . " href='../adminer/static/dark.css'>\n";
	}
	echo "<meta name='color-scheme' content='" . ($dark === null ? "light dark" : ($dark ? "dark" : "light")) . "'>\n";

	// this is matched by compile.php
	echo script_src("../adminer/static/functions.js");
	echo script_src("static/editing.js");
	if (adminer()->head($dark)) {
		echo "<link rel='icon' href='data:image/gif;base64,R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n";
		echo "<link rel='apple-touch-icon' href='../adminer/static/logo.png'>\n";
	}
	foreach ($css as $url => $mode) {
		$attrs = ($mode == 'dark' && !$dark
			? $media
			: ($mode == 'light' && $has_dark ? " media='(prefers-color-scheme: light)'" : "")
		);
		echo "<link rel='stylesheet'$attrs href='" . h($url) . "'>\n";
	}
	echo "\n<body class='" . lang('ltr') . " nojs";
	adminer()->bodyClass();
	echo "'>\n";
	$filename = get_temp_dir() . "/adminer.version";
	if (!$_COOKIE["adminer_version"] && function_exists('openssl_verify') && file_exists($filename) && filemtime($filename) + 86400 > time()) { // 86400 - 1 day in seconds
		$version = unserialize(file_get_contents($filename));
		$public = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwqWOVuF5uw7/+Z70djoK
RlHIZFZPO0uYRezq90+7Amk+FDNd7KkL5eDve+vHRJBLAszF/7XKXe11xwliIsFs
DFWQlsABVZB3oisKCBEuI71J4kPH8dKGEWR9jDHFw3cWmoH3PmqImX6FISWbG3B8
h7FIx3jEaw5ckVPVTeo5JRm/1DZzJxjyDenXvBQ/6o9DgZKeNDgxwKzH+sw9/YCO
jHnq1cFpOIISzARlrHMa/43YfeNRAm/tsBXjSxembBPo7aQZLAWHmaj5+K19H10B
nCpz9Y++cipkVEiKRGih4ZEvjoFysEOdRLj6WiD/uUNky4xGeA6LaJqh5XpkFkcQ
fQIDAQAB
-----END PUBLIC KEY-----
";
		if (openssl_verify($version["version"], base64_decode($version["signature"]), $public) == 1) {
			$_COOKIE["adminer_version"] = $version["version"]; // doesn't need to send to the browser
		}
	}
	echo script("mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick"
		. (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '" . VERSION . "', '" . js_escape(ME) . "', '" . get_token() . "')")
		. "});
document.body.classList.replace('nojs', 'js');
const offlineMessage = '" . js_escape(lang('You are offline.')) . "';
const thousandsSeparator = '" . js_escape(lang(',')) . "';")
	;
	echo "<div id='help' class='jush-" . JUSH . " jsonly hidden'></div>\n";
	echo script("mixin(qs('#help'), {onmouseover: () => { helpOpen = 1; }, onmouseout: helpMouseout});");
	echo "<div id='content'>\n";
	echo "<span id='menuopen' class='jsonly'>" . icon("move", "", "menu", "") . "</span>" . script("qs('#menuopen').onclick = event => { qs('#foot').classList.toggle('foot'); event.stopPropagation(); }");
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ?: ".") . '">' . get_driver(DRIVER) . '</a> » ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = adminer()->serverName(SERVER);
		$server = ($server != "" ? $server : lang('Server'));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link) . "' accesskey='1' title='Alt+Shift+1'>$server</a> » ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> » ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> » ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> » ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' class='jsonly hidden'></div>\n";
	restart_session();
	page_messages($error);
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define('Adminer\PAGE_HEADER', 1);
}

/** Send HTTP headers */
function page_headers(): void {
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach (adminer()->csp(csp()) as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	adminer()->headers();
}

/** Get Content Security Policy headers
* @return list<string[]> of arrays with directive name in key, allowed sources in value
*/
function csp(): array {
	return array(
		array(
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'", // 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"connect-src" => "'self'",
			"frame-src" => "https://www.adminer.org",
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get a CSP nonce
* @return string Base64 value
*/
function get_nonce(): string {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages */
function page_messages(string $error): void {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = idx($_SESSION["messages"], $uri);
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
	if (adminer()->error) { // separate <div>
		echo "<div class='error'>" . adminer()->error . "</div>\n";
	}
}

/** Print HTML footer
* @param ''|'auth'|'db'|'ns' $missing
*/
function page_footer(string $missing = ""): void {
	echo "</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";
	adminer()->navigation($missing);
	echo "</div>\n";
	if ($missing != "auth") {
		?>
<form action="" method="post">
<p class="logout">
<span><?php echo h($_GET["username"]) . "\n"; ?></span>
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" id="logout">
<?php echo input_token(); ?>
</form>
<?php
	}
	echo "</div>\n\n";
	echo script("setupSubmitHighlight(document);");
}
