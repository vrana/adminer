<?php
error_reporting(E_ALL & ~E_NOTICE);
if (!ini_get("session.auto_start")) {
	session_name("phpMinAdmin_SID");
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

if ($_GET["start"]) {
	$_SESSION["coverage"] = array();
	header("Location: .");
	exit;
} elseif ($_GET["filename"]) {
	$filename = basename($_GET["filename"]);
	$coverage = $_SESSION["coverage"][realpath($filename)];
	$file = explode("<br />", highlight_file($filename, true));
	unset($prev_color);
	$s = "";
	for ($l=0; $l <= count($file); $l++) {
		$line = $file[$l];
		$color = "#C0FFC0"; // tested
		switch ($coverage[$l+1]) {
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
} elseif (isset($_SESSION["coverage"])) {
	echo "<ul>\n";
	foreach (glob("*.php") as $filename) {
		if ($filename{0} != "_") {
			$coverage = $_SESSION["coverage"][realpath($filename)];
			echo "<li><a href='_coverage.php?filename=$filename'>$filename</a> (" . (isset($coverage) ? "tested" : "untested") . ")</li>\n";
		}
	}
	echo "</ul>\n";
}
?>
<p><a href="_coverage.php?start=1">Start new coverage</a> (requires <a href="http://www.xdebug.org">Xdebug</a>)</p>
