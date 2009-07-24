<?php
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $SELF, $LANG, $VERSION;
	header("Content-Type: text/html; charset=utf-8");
	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="<?php echo $LANG; ?>">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Content-Script-Type" content="text/javascript">
<meta name="robots" content="noindex">
<title><?php echo $title . (strlen($title2) ? ": " . htmlspecialchars($title2) : "") . (strlen($_GET["server"]) && $_GET["server"] != "localhost" ? htmlspecialchars("- $_GET[server]") : "") . " - " . adminer_name(); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="../adminer/favicon.ico">
<link rel="stylesheet" type="text/css" href="../adminer/default.css<?php // Ondrej Valka, http://valka.info ?>">
<?php if (file_exists("adminer.css")) { ?>
<link rel="stylesheet" type="text/css" href="adminer.css">
<?php } ?>

<body onload="body_load();<?php echo (isset($_COOKIE["adminer_version"]) ? "" : " verify_version('$VERSION');"); ?>">
<script type="text/javascript" src="../adminer/functions.js"></script>
<script type="text/javascript" src="editing.js"></script>

<div id="content">
<?php
	if (isset($breadcrumb)) {
		$link = substr(preg_replace('~db=[^&]*&~', '', $SELF), 0, -1);
		echo '<p id="breadcrumb"><a href="' . (strlen($link) ? htmlspecialchars($link) : ".") . '">' . (isset($_GET["server"]) ? htmlspecialchars($_GET["server"]) : lang('Server')) . '</a> &raquo; ';
		if (is_array($breadcrumb)) {
			if (strlen($_GET["db"])) {
				echo '<a href="' . htmlspecialchars(substr($SELF, 0, -1)) . '">' . htmlspecialchars($_GET["db"]) . '</a> &raquo; ';
			}
			foreach ($breadcrumb as $key => $val) {
				$desc = (is_array($val) ? $val[1] : $val);
				if (strlen($desc)) {
					echo '<a href="' . htmlspecialchars("$SELF$key=") . urlencode(is_array($val) ? $val[0] : $val) . '">' . htmlspecialchars($desc) . '</a> &raquo; ';
				}
			}
		}
		echo "$title\n";
	}
	echo "<h2>$title" . (strlen($title2) ? ": " . htmlspecialchars($title2) : "") . "</h2>\n";
	if ($_SESSION["messages"]) {
		echo "<div class='message'>" . implode("</div>\n<div class='message'>", $_SESSION["messages"]) . "</div>\n";
		$_SESSION["messages"] = array();
	}
	$databases = &$_SESSION["databases"][$_GET["server"]];
	if (strlen($_GET["db"]) && $databases && !in_array($_GET["db"], $databases, true)) {
		$databases = null;
	}
	if (isset($databases) && !isset($_GET["sql"])) {
		// improves concurrency if a user opens several pages at once
		session_write_close();
	}
	if ($error) {
		echo "<div class='error'>$error</div>\n";
	}
}

function page_footer($missing = false) {
	global $VERSION;
	?>
</div>

<?php switch_lang(); ?>
<div id="menu">
<h1><a href="http://www.adminer.org/" class="h1"><?php echo adminer_name(); ?></a> &nbsp; <?php echo $VERSION; ?> &nbsp;
<a href='http://www.adminer.org/#download' id="version"><?php echo (version_compare($VERSION, $_COOKIE["adminer_version"]) < 0 ? htmlspecialchars($_COOKIE["adminer_version"]) : ""); ?></a>
</h1>
<?php adminer_navigation($missing); ?>
</div>
<?php
}
