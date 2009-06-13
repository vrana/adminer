<?php
error_reporting(E_ALL & ~E_NOTICE);
if (!ini_get("session.auto_start")) {
	session_name("adminer_sid");
	session_set_cookie_params(ini_get("session.cookie_lifetime"), preg_replace('~_coverage\\.php(\\?.*)?$~', '', $_SERVER["REQUEST_URI"]));
	session_start();
}

function xhtml_open_tags($s) {
	$return = array();
	preg_match_all('~<([^>]+)~', $s, $matches);
	foreach ($matches[1] as $val) {
		if ($val{0} == "/") {
			array_pop($return);
		} elseif (substr($val, -1) != "/") {
			$return[] = $val;
		}
	}
	return $return;
}

if (!extension_loaded("xdebug")) {
	echo "<p>Xdebug has to be enabled.</p>\n";
}

if ($_GET["start"]) {
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
	$_SESSION["coverage"] = array();
	include "./adminer/index.php";
	header("Location: .");
	exit;
}
if (preg_match('~^(include/)?[-_.a-z0-9]+$~i', $_GET["filename"])) {
	$filename = "adminer/$_GET[filename]";
	$cov = $_SESSION["coverage"][realpath($filename)];
	$file = explode("<br />", highlight_file($filename, true));
	unset($prev_color);
	$s = "";
	for ($l=0; $l <= count($file); $l++) {
		$line = $file[$l];
		$color = "#C0FFC0"; // tested
		switch ($cov[$l+1]) {
			case -1: $color = "#FFC0C0"; break; // untested
			case -2: $color = "Silver"; break; // dead code
			case null: $color = ""; break; // not executable
		}
		if (!isset($prev_color)) {
			$prev_color = $color;
		}
		if ($prev_color != $color || !isset($line)) {
			echo "<div" . ($prev_color ? " style='background-color: $prev_color;'" : "") . ">" . $s;
			$open_tags = xhtml_open_tags($s);
			foreach (array_reverse($open_tags) as $tag) {
				echo "</" . preg_replace('~ .*~', '', $tag) . ">";
			}
			echo "</div>\n";
			$s = ($open_tags ? "<" . implode("><", $open_tags) . ">" : "");
			$prev_color = $color;
		}
		$s .= "$line<br />\n";
	}
} else {
	echo "<table border='0' cellspacing='0' cellpadding='1'>\n";
	foreach (array_merge(glob("adminer/*.php"), glob("adminer/include/*.php")) as $filename) {
		$cov = $_SESSION["coverage"][realpath($filename)];
		$filename = substr($filename, 8);
		$ratio = 0;
		if (isset($cov)) {
			$values = array_count_values($cov);
			$ratio = round(100 - 100 * $values[-1] / count($cov));
		}
		echo "<tr><td align='right' style='background-color: " . ($ratio < 50 ? "Red" : ($ratio < 75 ? "#FFEA20" : "#A7FC9D")) . ";'>$ratio%</td><td><a href='coverage.php?filename=$filename'>$filename</a></td></tr>\n";
	}
	echo "</table>\n";
	echo "<p><a href='coverage.php?start=1'>Start new coverage</a> (requires <a href='http://www.xdebug.org'>Xdebug</a>)</p>\n";
}
