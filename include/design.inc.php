<?php
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $SELF, $LANG, $VERSION;
	header("Content-Type: text/html; charset=utf-8");
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $LANG; ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Script-Type" content="text/javascript" />
<meta name="robots" content="noindex" />
<title><?php echo $title . (strlen($title2) ? ": " . htmlspecialchars($title2) : "") . " - " . lang('phpMinAdmin'); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
<link rel="stylesheet" type="text/css" href="default.css<?php // Ondrej Valka, http://valka.info ?>" />
<?php if (file_exists("phpMinAdmin.css")) { ?>
<link rel="stylesheet" type="text/css" href="phpMinAdmin.css" />
<?php } ?>
</head>

<body onload="load_jush();<?php echo (isset($_COOKIE["phpMinAdmin_version"]) ? "" : " verify_version('$VERSION');"); ?>">
<script type="text/javascript" src="functions.js"></script>

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
				if (strlen($val)) {
					echo '<a href="' . htmlspecialchars("$SELF$key=") . ($key != "privileges" ? urlencode($val) : "") . '">' . htmlspecialchars($val) . '</a> &raquo; ';
				}
			}
		}
		echo "$title</p>\n";
	}
	echo "<h2>$title" . (strlen($title2) ? ": " . htmlspecialchars($title2) : "") . "</h2>\n";
	if ($_SESSION["messages"]) {
		echo "<p class='message'>" . implode("</p>\n<p class='message'>", $_SESSION["messages"]) . "</p>\n";
		$_SESSION["messages"] = array();
	}
	$databases = &$_SESSION["databases"][$_GET["server"]];
	if (strlen($_GET["db"]) && $databases && !in_array($_GET["db"], $databases, true)) {
		$databases = null;
	}
	if (isset($databases) && !isset($_GET["sql"]) && !isset($_SESSION["coverage"])) {
		session_write_close();
	}
	if ($error) {
		echo "<p class='error'>$error</p>\n";
	}
}

function page_footer($missing = false) {
	global $SELF, $dbh, $VERSION;
	?>
</div>

<?php switch_lang(); ?>
<div id="menu">
<h1><a href="http://www.phpminadmin.net/"><?php echo lang('phpMinAdmin'); ?></a> <?php echo $VERSION; ?></h1>
<div class="version"><a href='http://www.phpminadmin.net/#download' id="version"><?php echo (version_compare($VERSION, $_COOKIE["phpMinAdmin_version"]) < 0 ? htmlspecialchars($_COOKIE["phpMinAdmin_version"]) : ""); ?></a></div>
<?php if ($missing != "auth") { ?>
<form action="" method="post">
<p>
<a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo lang('Dump'); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]; ?>" />
<input type="submit" name="logout" value="<?php echo lang('Logout'); ?>" />
</p>
</form>
<form action="">
<p><?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" /><?php } ?>
<?php if (get_databases()) { ?>
<select name="db" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)</option><?php echo optionlist(get_databases(), $_GET["db"]); ?></select>
<?php } else { ?>
<input name="db" value="<?php echo htmlspecialchars($_GET["db"]); ?>" /> <input type="submit" value="<?php echo lang('Use'); ?>" />
<?php } ?>
<?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value="" /><?php } ?>
<?php if (isset($_GET["schema"])) { ?><input type="hidden" name="schema" value="" /><?php } ?>
<?php if (isset($_GET["dump"])) { ?><input type="hidden" name="dump" value="" /><?php } ?>
</p>
<?php if (get_databases()) { ?>
<noscript><p><input type="submit" value="<?php echo lang('Use'); ?>" /></p></noscript>
<?php } ?>
</form>
<?php
		if ($missing != "db" && strlen($_GET["db"])) {
			$result = $dbh->query("SHOW TABLE STATUS");
			if (!$result->num_rows) {
				echo "<p class='message'>" . lang('No tables.') . "</p>\n";
			} else {
				echo "<p>\n";
				while ($row = $result->fetch_assoc()) {
					echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row["Name"]) . '">' . lang('select') . '</a> ';
					echo '<a href="' . htmlspecialchars($SELF) . (isset($row["Rows"]) ? 'table' : 'view') . '=' . urlencode($row["Name"]) . '">' . htmlspecialchars($row["Name"]) . "</a><br />\n";
				}
				echo "</p>\n";
			}
			echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a></p>\n";
			$result->free();
		}
	}
	?>
</div>

</body>
</html>
<?php
}
