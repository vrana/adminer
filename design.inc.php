<?php
function page_header($title, $error = "", $breadcrumb = array(), $title2 = "") {
	global $SELF, $LANG;
	header("Content-Type: text/html; charset=utf-8");
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $LANG; ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="Content-Script-Type" content="text/javascript" />
<meta name="robots" content="noindex" />
<title><?php echo $title . (strlen($title2) ? ": " . htmlspecialchars($title2) : "") . " - " . lang('phpMinAdmin') . " 1.9.0-dev"; ?></title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
<link rel="stylesheet" type="text/css" href="default.css" /><?php // Ondrej Valka, http://valka.info ?>
</head>

<body>
<script type="text/javascript">
document.body.className = 'js';
function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
}
</script>

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
	if (!$_SESSION["tokens"][$_GET["server"]]["?logout"]) {
		$_SESSION["tokens"][$_GET["server"]]["?logout"] = rand(1, 1e6);
	}
	if (isset($_SESSION["databases"][$_GET["server"]]) && !isset($_GET["sql"])) {
		session_write_close();
	}
	if ($error) {
		echo "<p class='error'>$error</p>\n";
	}
}

function page_footer($missing = false) {
	global $SELF, $mysql;
	?>
</div>

<?php switch_lang(); ?>
<div id="menu">
<h1><a href="http://phpminadmin.sourceforge.net"><?php echo lang('phpMinAdmin'); ?></a></h1>
<?php if ($missing != "auth") { ?>
<form action="" method="post">
<p>
<a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>dump=<?php echo urlencode(isset($_GET["table"]) ? $_GET["table"] : $_GET["select"]); ?>"><?php echo lang('Dump'); ?></a>
<input type="hidden" name="token" value="<?php echo $_SESSION["tokens"][$_GET["server"]]["?logout"]; ?>" />
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
			$result = $mysql->query("SHOW TABLE STATUS");
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

<?php if ($_COOKIE["highlight"] == "jush") { ?>
<script type="text/javascript" src="http://jush.sourceforge.net/jush.js"></script>
<script type="text/javascript">
if (typeof jush != 'undefined') {
	jush.style('http://jush.sourceforge.net/jush.css');
	jush.highlight_tag('pre');
	jush.highlight_tag('code');
}
</script>
<?php } ?>

</body>
</html>
<?php
}
