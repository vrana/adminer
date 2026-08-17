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
<html lang='<?php echo LANG; ?>' dir='<?php echo lang('ltr'); ?>' class='<?php echo lang('ltr'); ?> nojs'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" href="<?php echo DIR; ?>static/default.css">
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
		echo "<link rel='stylesheet'" . ($dark ? "" : $media) . " href='" . DIR . "static/dark.css'>\n";
	}
	echo "<meta name='color-scheme' content='" . ($dark === null ? "light dark" : ($dark ? "dark" : "light")) . "'>\n";

	// this is matched by compile.php
	echo script_src(DIR . "static/functions.js");
	echo script_src("static/editing.js");
	if (adminer()->head($dark)) {
		echo "<link rel='icon' href='data:image/gif;base64,"
			. "R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n";
		echo "<link rel='apple-touch-icon' href='" . DIR . "static/logo.png'>\n";
	}
	foreach ($css as $url => $mode) {
		$attrs = ($mode == 'dark' && !$dark
			? $media
			: ($mode == 'light' && $has_dark ? " media='(prefers-color-scheme: light)'" : "")
		);
		echo "<link rel='stylesheet'$attrs href='" . h($url) . "'>\n";
	}
	echo "\n<body class='";
	adminer()->bodyClass();
	echo "'>\n";
	// the event handlers and the <html> classes are registered by functions.js
	echo script((isset($_COOKIE["adminer_version"]) || !adminer()->verifyVersion() ? "" : "onload = partial(verifyVersion, '" . VERSION . "');\n") . "
const offlineMessage = '" . js_escape(lang('You are offline.')) . "';
const thousandsSeparator = '" . js_escape(lang(',')) . "';
const urlSeparators = '" . js_escape(ini_get("arg_separator.input")) . "';");
	echo "<div id='help' class='jush-" . JUSH . " jsonly hidden'" . on('mouseover', 'helpKeep') . on('mouseout', 'helpMouseout') . "></div>\n";
	echo "<div id='content'>\n";
	echo "<span id='menuopen' class='jsonly'" . on('click', 'menuToggle') . "><button title='" . lang('Menu') . "' class='icon icon-move' aria-expanded='false'></button></span>\n";
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ?: ".") . '">' . get_driver(DRIVER) . '</a> » ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = adminer()->serverName(SERVER);
		$server = ($server != "" ? $server : lang('Server'));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . h($link . (DB != "" && support("single_db") ? "&db=" : "")) . "' accesskey='1' title='Alt+Shift+1'>$server</a> » ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . url_escape(DB) . (support("scheme") ? "&ns=" : "") . (support("single_table") ? "&select=" : "")) . '">' . h(DB) . '</a> » ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> » ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . url_escape(is_array($val) ? $val[0] : $val) . "'>$desc</a> » ";
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	echo "<div id='ajaxstatus' role='status' class='jsonly'></div>\n";
	restart_session();
	page_messages($error);
	if (!defined('Adminer\DIR')) { // only the compiled version serves the files itself, the development version leaves them to the web server
		service_worker();
	}
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	define('Adminer\PAGE_HEADER', 1);
	// let the browser download the CSS and JS while we are running the queries for the page body
	ob_flush();
	flush();
}

/** Print the script maintaining the service worker caching the static files */
function service_worker(): void {
	$code = (has_passwords() // the worker belongs to all connections at once, so it is removed only after logging out of the last one; the login form must not register it back
		? "navigator.serviceWorker.register('" . js_escape(preg_replace('~\?.*~', '', ME) . "?file=worker.js&version=" . VERSION) . "', {scope: location.pathname}).catch(() => {});"
		: "navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));"
	);
	echo script("if (navigator.serviceWorker) {\n\t$code\n}");
}

/** Check whether any connection is logged in */
function has_passwords(): bool {
	foreach ((array) $_SESSION["pwds"] as $servers) {
		foreach ($servers as $usernames) {
			foreach ($usernames as $password) {
				if ($password !== null) { // logging out sets the password to null, it doesn't remove the key
					return true;
				}
			}
		}
	}
	return false;
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
			// 'self' is a fallback for browsers not supporting 'strict-dynamic', 'unsafe-inline' is a fallback for browsers not supporting 'nonce-'
			"script-src" => "'self' 'unsafe-inline' 'nonce-" . get_nonce() . "' 'strict-dynamic'",
			"connect-src" => "'self' https://www.adminer.org",
			"frame-src" => "https://www.adminer.org", // version check without JS
			"object-src" => "'none'",
			"base-uri" => "'none'",
			"form-action" => "'self'",
		),
	);
}

/** Get names and checksums of the used designs
* @return array<string, string[]> filename in key, [design name, hex crc32] in value, name is empty for designs without the marker
*/
function design_checksums(): array {
	$used = array();
	foreach (array_keys(adminer()->css()) as $url) {
		$used[preg_replace('~\?.*~', '', $url)] = true;
	}
	$return = array();
	foreach (array("adminer.css", "adminer-dark.css") as $filename) {
		if ($used[$filename] && file_exists($filename)) {
			preg_match('~^/\* Adminer design ([-\w]+) \*/~', file_get_contents($filename), $match);
			$return[$filename] = array((string) $match[1], Plugins::checksum($filename));
		}
	}
	return $return;
}

/** Get checksums of official designs shipped with this Adminer version
* @return string[] design name with filename in key, hex crc32 in value
*/
function official_design_checksums(): array {
	// inlined by compile.php
	$return = array();
	foreach (glob("../designs/*/*.css") as $filename) {
		if (preg_match('~^/\* Adminer design ([-\w]+) \*/~', file_get_contents($filename), $match)) {
			$return["$match[1]/" . basename($filename)] = Plugins::checksum($filename);
		}
	}
	return $return;
}

/** Get HTML displaying a new version even without JavaScript
* @return string noscript iframe or empty string
*/
function version_iframe(): string {
	return (isset($_COOKIE["adminer_version"]) || !adminer()->verifyVersion()
		? ""
		: "<noscript><iframe sandbox src='https://www.adminer.org/version/?current=" . VERSION . "&amp;noscript=1'></iframe></noscript>"
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
<span title="<?php echo lang('Username'); ?>"><?php echo h($_GET["username"]) . "\n"; ?></span>
<input type='submit' name='logout' value='<?php echo lang('Logout'); ?>' id='logout'>
<?php echo input_token(); ?>
</form>
<?php
	}
	echo "</div>\n\n";
	echo script("setupSubmitHighlight(document);");
}
