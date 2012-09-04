<?php
/** Print HTML header
* @param string used in title, breadcrumb and heading, should be HTML escaped
* @param string
* @param mixed array("key" => "link=desc", "key2" => array("link", "desc")), null for nothing, false for driver only, true for driver and server
* @param string used after colon in title and heading, will be HTML escaped
* @return null
*/
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $LANG, $adminer, $connection, $drivers;
	header("Content-Type: text/html; charset=utf-8");
	if ($adminer->headers()) {
		header("X-Frame-Options: deny"); // ClickJacking protection in IE8, Safari 4, Chrome 2, Firefox 3.6.9
		header("X-XSS-Protection: 0"); // prevents introducing XSS in IE8 by removing safe parts of the page
	}
	$title_all = $title . ($title2 != "" ? ": " . h($title2) : "");
	$title_page = strip_tags($title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . $adminer->name());
	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="<?php echo $LANG; ?>" dir="<?php echo lang('ltr'); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Content-Script-Type" content="text/javascript">
<meta name="robots" content="noindex">
<title><?php echo $title_page; ?></title>
<link rel="stylesheet" type="text/css" href="../adminer/static/default.css">
<script type="text/javascript" src="../adminer/static/functions.js"></script>
<script type="text/javascript" src="static/editing.js"></script>
<?php if ($adminer->head()) { ?>
<link rel="shortcut icon" type="image/x-icon" href="../adminer/static/favicon.ico" id="favicon">
<?php if (file_exists("adminer.css")) { ?>
<link rel="stylesheet" type="text/css" href="adminer.css">
<?php } ?>
<?php } ?>

<body class="<?php echo lang('ltr'); ?> nojs" onkeydown="bodyKeydown(event);" onclick="bodyClick(event);" onload="bodyLoad('<?php echo (is_object($connection) ? substr($connection->server_info, 0, 3) : ""); ?>');<?php echo (isset($_COOKIE["adminer_version"]) ? "" : " verifyVersion();"); ?>">
<script type="text/javascript">
document.body.className = document.body.className.replace(/ nojs/, ' js');
</script>

<div id="content">
<?php
	if ($breadcrumb !== null) {
		$link = substr(preg_replace('~(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . h($link ? $link : ".") . '">' . $drivers[DRIVER] . '</a> &raquo; ';
		$link = substr(preg_replace('~(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = (SERVER != "" ? h(SERVER) : lang('Server'));
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
					$desc = (is_array($val) ? $val[1] : $val);
					if ($desc != "") {
						echo '<a href="' . h(ME . "$key=") . urlencode(is_array($val) ? $val[0] : $val) . '">' . h($desc) . '</a> &raquo; ';
					}
				}
			}
			echo "$title\n";
		}
	}
	echo "<h2>$title_all</h2>\n";
	restart_session();
	$uri = preg_replace('~^[^?]*~', '', $_SERVER["REQUEST_URI"]);
	$messages = $_SESSION["messages"][$uri];
	if ($messages) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $messages) . "</div>\n";
		unset($_SESSION["messages"][$uri]);
	}
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
	stop_session();
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
	define("PAGE_HEADER", 1);
}

/** Print HTML footer
* @param string "auth", "db", "ns"
* @return null
*/
function page_footer($missing = "") {
	global $adminer;
	?>
</div>

<?php switch_lang(); ?>
<div id="menu">
<?php $adminer->navigation($missing); ?>
</div>
<?php
}
