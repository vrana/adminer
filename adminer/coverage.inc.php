<?php
function xhtml_open_tags($s) {
	// returns array of opened tags in $s
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

page_header("Coverage", (extension_loaded("xdebug") ? "" : "Xdebug has to be enabled."));

if ($_GET["coverage"] === "0") {
	unset($_SESSION["coverage"]); // disable coverage if it is not available
	if (extension_loaded("xdebug")) {
		$_SESSION["coverage"] = array();
		echo "<p class='message'>Coverage started.</p>\n";
	}
} elseif (preg_match('~^(include/)?[-_.a-z0-9]+$~i', $_GET["coverage"])) {
	// highlight single file
	$filename = $_GET["coverage"];
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
	// display list of files
	echo "<table cellspacing='0'>\n";
	foreach (array_merge(glob("*.php"), glob("include/*.php")) as $filename) {
		$cov = $_SESSION["coverage"][realpath($filename)];
		$ratio = 0;
		if (isset($cov)) {
			$values = array_count_values($cov);
			$ratio = round(100 - 100 * $values[-1] / count($cov));
		}
		echo "<tr><td align='right' style='background-color: " . ($ratio < 50 ? "Red" : ($ratio < 75 ? "#FFEA20" : "#A7FC9D")) . ";'>$ratio%</td><th><a href=\"" . htmlspecialchars($SELF) . "coverage=$filename\">$filename</a></th></tr>\n";
	}
	echo "</table>\n";
	echo '<p><a href="' . htmlspecialchars($SELF) . 'coverage=0">Start new coverage</a></p>' . "\n";
}
page_footer("auth");
exit;
