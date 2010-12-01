<?php
/** Print HTML header
* @param string used in title, breadcrumb and heading
* @param string
* @param mixed array("key" => "link=desc", "key2" => array("link", "desc")), null for nothing, false for driver only, true for driver and server
* @param string used after colon in title and heading
* @return null
*/
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $LANG, $HTTPS, $adminer, $connection, $drivers;
	header("Content-Type: text/html; charset=utf-8");
	$adminer->headers();
	$title_all = $title . ($title2 != "" ? ": " . h($title2) : "");
	$title_page = $title_all . (SERVER != "" && SERVER != "localhost" ? h(" - " . SERVER) : "") . " - " . $adminer->name();
	if (is_ajax()) {
		header("X-AJAX-Title: " . rawurlencode($title_page));
		if ($_GET["ajax"]) {
			header("X-AJAX-Redirect: " . remove_from_uri("ajax"));
		}
	} else {
		$protocol = ($HTTPS ? "https" : "http");
		?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="<?php echo $LANG; ?>" dir="<?php echo lang('ltr'); ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Content-Script-Type" content="text/javascript">
<meta name="robots" content="noindex">
<title><?php echo $title_page; ?></title>
<link rel="shortcut icon" type="image/x-icon" href="../adminer/static/favicon.ico">
<link rel="stylesheet" type="text/css" href="../adminer/static/default.css<?php // Ondrej Valka, http://valka.info ?>">
<?php if (file_exists("adminer.css")) { ?>
<link rel="stylesheet" type="text/css" href="adminer.css">
<?php } ?>

<body class="<?php echo lang('ltr'); ?>" onclick="return bodyClick(event, '<?php echo js_escape(DB); ?>', '<?php echo js_escape($_GET["ns"]); ?>');" onload="bodyLoad('<?php echo (is_object($connection) ? substr($connection->server_info, 0, 3) : ""); ?>', '<?php echo $protocol; ?>');<?php echo (isset($_COOKIE["adminer_version"]) ? "" : " verifyVersion('$protocol');"); ?>">
<script type="text/javascript" src="../adminer/static/functions.js"></script>
<script type="text/javascript" src="static/editing.js"></script>

<div id="content">
<?php
	}
	if (isset($breadcrumb)) {
		$link = substr(preg_replace('~(username|db|ns)=[^&]*&~', '', ME), 0, -1);
		echo '<p id="breadcrumb"><a href="' . ($link ? h($link) : ".") . '">' . $drivers[DRIVER] . '</a> &raquo; ';
		$link = substr(preg_replace('~(db|ns)=[^&]*&~', '', ME), 0, -1);
		$server = (SERVER != "" ? h(SERVER) : lang('Server'));
		if ($breadcrumb === false) {
			echo "$server\n";
		} else {
			echo "<a href='" . ($link ? h($link) : ".") . "'>$server</a> &raquo; ";
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
	if ($_SESSION["messages"]) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $_SESSION["messages"]) . "</div>\n";
		$_SESSION["messages"] = array();
	}
	$databases = &get_session("dbs");
	if (DB != "" && $databases && !in_array(DB, $databases, true)) {
		$databases = null;
	}
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
	if (!is_ajax()) {
		?>
</div>

<?php switch_lang(); ?>
<div id="menu">
<?php $adminer->navigation($missing); ?>
</div>
<?php
	}
}
