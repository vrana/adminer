<?php
function page_header($title, $missing = false) {
	global $SELF;
	header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="cs">
<head>
<title><?php echo lang('phpMinAdmin') . ($title ? " - $title" : ""); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
BODY { color: Black; background-color: White; }
A { color: Blue; }
A:visited { color: Navy; }
H1 { font-size: 150%; margin: 0; }
H1 A { color: Black; }
H2 { font-size: 150%; margin-top: 0; }
.error { color: Red; }
.message { color: Green; }
#menu { float: left; width: 15em; overflow: auto; white-space: nowrap; }
#content { margin-left: 16em; }
</style>
</head>

<body>

<div id="menu">
<h1><a href="<?php echo htmlspecialchars(substr($SELF, 0, -1)); ?>"><?php echo lang('phpMinAdmin'); ?></a></h1>
<?php switch_lang(); ?>
<?php if ($missing != "auth") { ?>
<p>
<a href="<?php echo htmlspecialchars($SELF); ?>sql="><?php echo lang('SQL command'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>dump="><?php echo lang('Dump'); ?></a>
<a href="<?php echo htmlspecialchars($SELF); ?>logout="><?php echo lang('Logout'); ?></a>
</p>
<form action="" method="get">
<p><?php if (strlen($_GET["server"])) { ?><input type="hidden" name="server" value="<?php echo htmlspecialchars($_GET["server"]); ?>" /><?php } ?>
<select name="db" onchange="this.form.submit();"><option value="">(<?php echo lang('database'); ?>)</option>
<?php
		$result = mysql_query("SHOW DATABASES");
		while ($row = mysql_fetch_row($result)) {
			echo "<option" . ($row[0] == $_GET["db"] ? " selected='selected'" : "") . ">" . htmlspecialchars($row[0]) . "</option>\n";
		}
		mysql_free_result($result);
		?>
</select><?php if (isset($_GET["sql"])) { ?><input type="hidden" name="sql" value="" /><?php } ?></p>
<noscript><p><input type="submit" value="<?php echo lang('Use'); ?>" /></p></noscript>
</form>
<?php
		if ($missing != "db" && strlen($_GET["db"])) {
			$result = mysql_query("SHOW TABLES");
			if (!mysql_num_rows($result)) {
				echo "<p class='message'>" . lang('No tables.') . "</p>\n";
			} else {
				echo "<p>\n";
				while ($row = mysql_fetch_row($result)) {
					echo '<a href="' . htmlspecialchars($SELF) . 'select=' . urlencode($row[0]) . '">' . lang('select') . '</a> ';
					echo '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($row[0]) . '">' . htmlspecialchars($row[0]) . "</a><br />\n"; //! views
				}
				echo "</p>\n";
			}
			echo '<p><a href="' . htmlspecialchars($SELF) . 'create=">' . lang('Create new table') . "</a></p>\n"; //! rights
			mysql_free_result($result);
		}
	}
	?>
</div>

<div id="content">
<?php
	echo "<h2>$title</h2>\n";
	if ($_SESSION["message"]) {
		echo "<p class='message'>$_SESSION[message]</p>\n";
		$_SESSION["message"] = "";
	}
}

function page_footer() {
?>

</div>
</body>
</html>
<?php
}
