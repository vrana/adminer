<?php
/** Print HTML header
* @param string used in title, breadcrumb and heading, should be HTML escaped
* @param string
* @param mixed array("key" => "link", "key2" => array("link", "desc")), null for nothing, false for driver only, true for driver and server
* @param string used after colon in title and heading, should be HTML escaped
* @return null
*/
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $LANG, $VERSION, $adminer, $drivers, $jush;
	page_headers();
	if (is_ajax() && $error) {
		page_messages($error);
		exit;
	}
	$title_all = $title . ($title2 != "" ? ": $title2" : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . $adminer->name());
	?>
<!DOCTYPE html>
<html lang="<?php echo $LANG; ?>" dir="<?php echo lang('ltr'); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" type="text/css" href="../adminer/static/default.css">
<?php echo script_src("../adminer/static/functions.js"); ?>
<?php echo script_src("static/editing.js"); ?>
<?php if ($adminer->head()) { ?>
<link rel="shortcut icon" type="image/x-icon" href="../adminer/static/favicon.ico">
<link rel="apple-touch-icon" href="../adminer/static/favicon.ico">
<?php foreach ($adminer->css() as $css) { ?>
<link rel="stylesheet" type="text/css" href="<?php echo h($css); ?>">
<?php } ?>
<?php } ?>

<body class="<?php echo lang('ltr'); ?> nojs">
<?php
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
	?>
<script<?php echo nonce(); ?>>
mixin(document.body, {onkeydown: bodyKeydown, onclick: bodyClick<?php
	echo (isset($_COOKIE["adminer_version"]) ? "" : ", onload: partial(verifyVersion, '$VERSION', '" . js_escape(ME) . "', '" . get_token() . "')"); // $token may be empty in auth.inc.php
	?>});
document.body.className = document.body.className.replace(/ nojs/, ' js');
var offlineMessage = '<?php echo js_escape(lang('You are offline.')); ?>';
var thousandsSeparator = '<?php echo js_escape(lang(',')); ?>';
</script>

<div id="help" class="jush-<?php echo $jush; ?> jsonly hidden"></div>
<?php echo script("mixin(qs('#help'), {onmouseover: function () { helpOpen = 1; }, onmouseout: helpMouseout});"); ?>

<div id="content">
<?php
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~\b(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ? $link : ".") . '">' . $drivers[DRIVER] . '</a> &raquo; ';
		$link = substr(preg_replace('~\b(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = $adminer->serverName(SERVER);
		$server = ($server != "" ? $server : lang('Server'));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . ($link ? h($link) : ".") . "' accesskey='1' title='Alt+Shift+1'>$server</a> &raquo; ";
			if ($_GET["ns"] != "" || (DB != "" && is_array($breadcrumb))) {
				echo '<a href="' . h($link . "&db=" . urlencode(DB) . (support("scheme") ? "&ns=" : "")) . '">' . h(DB) . '</a> &raquo; ';
			}
			if (is_array($breadcrumb)) {
				if ($_GET["ns"] != "") {
					echo '<a href="' . h(substr(ME, 0, -1)) . '">' . h($_GET["ns"]) . '</a> &raquo; ';
				}
				foreach ($breadcrumb as $key => $val) {
					$desc = (is_array($val) ? $val[1] : h($val));
					if ($desc != "") {
						echo "<a href='" . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . "'>$desc</a> &raquo; ";
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
	define("PAGE_HEADER", 1);
}

/** Send HTTP headers
* @return null
*/
function page_headers() {
	global $adminer;
	header("Content-Type: text/html; charset=utf-8");
	header("Cache-Control: no-cache");
	header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
	header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	header("X-Content-Type-Options: nosniff");
	header("Referrer-Policy: origin-when-cross-origin");
	foreach ($adminer->csp() as $csp) {
		$header = array();
		foreach ($csp as $key => $val) {
			$header[] = "$key $val";
		}
		header("Content-Security-Policy: " . implode("; ", $header));
	}
	$adminer->headers();
}

/** Get Content Security Policy headers
* @return array of arrays with directive name in key, allowed sources in value
*/
function csp() {
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
function get_nonce() {
	static $nonce;
	if (!$nonce) {
		$nonce = base64_encode(rand_string());
	}
	return $nonce;
}

/** Print flash and error messages
* @param string
* @return null
*/
function page_messages($error) {
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = $_SESSION["messages"][$uri];
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>" . script("messagesPrint();");
		unset($_SESSION["messages"][$uri]);
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
}

/** Print HTML footer
* @param string "auth", "db", "ns"
* @return null
*/
function page_footer($missing = "") {
	global $adminer, $token;
	?>
</div>

<?php switch_lang(); ?>
<?php if ($missing != "auth") { ?>
<form action="" method="post">
<p class="logout">
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" id="logout">
<input type="hidden" name="token" value="<?php echo $token; ?>">
</p>
</form>
<?php } ?>
<div id="menu">
<?php $adminer->navigation($missing); ?>
</div>
<?php
	echo script("setupSubmitHighlight(document);");
}
