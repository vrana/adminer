<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="cs">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Coverage</title>
</head>

<body>

<?php
function xhtml_open_tags($s) {
	// returns array of opened tags in $s
	$return = array();
	preg_match_all('~<([^>]+)~', $s, $matches);
	foreach ($matches[1] as $val) {
		if ($val[0] == "/") {
			array_pop($return);
		} elseif (substr($val, -1) != "/") {
			$return[] = $val;
		}
	}
	return $return;
}

$coverage_filename = sys_get_temp_dir() . "/adminer_coverage.ser";
if (!extension_loaded("xdebug")) {
	echo "<p class='error'>Xdebug has to be enabled.</p>\n";
} elseif ($_GET["coverage"] === "0") {
	file_put_contents($coverage_filename, serialize(array()));
	echo "<p class='message'>Coverage started.</p>\n";
} elseif (preg_match('~^(adminer|editor)/(include/)?[-_.a-z0-9]+$~i', $_GET["coverage"])) {
	// highlight single file
	$filename = $_GET["coverage"];
	$coverage = (file_exists($coverage_filename) ? unserialize(file_get_contents($coverage_filename)) : array());
	$file = explode("<br />", highlight_file($filename, true));
	$prev_color = null;
	$s = "";
	for ($l=0; $l <= count($file); $l++) {
		$line = $file[$l];
		$color = "#C0FFC0"; // tested
		switch ($coverage[realpath($filename)][$l+1]) {
			case -1: $color = "#FFC0C0"; break; // untested
			case -2: $color = "Silver"; break; // dead code
			case null: $color = ""; break; // not executable
		}
		if ($prev_color === null) {
			$prev_color = $color;
		}
		if ($prev_color != $color || $line === null) {
			echo "<div" . ($prev_color ? " style='background-color: $prev_color;'" : "") . ">$s";
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
	if (file_exists($coverage_filename)) {
		// display list of files
		$coverage = unserialize(file_get_contents($coverage_filename));
		echo "<table border='1' cellspacing='0'>\n";
		foreach (array_merge(glob("adminer/*.php"), glob("adminer/include/*.php"), glob("editor/*.php"), glob("editor/include/*.php")) as $filename) {
			$cov = $coverage[realpath($filename)];
			$ratio = 0;
			if (is_array($cov)) {
				$values = array_count_values($cov);
				$ratio = round(100 - 100 * $values[-1] / (count($cov) - $values[-2]));
			}
			echo "<tr><td align='right' style='background-color: " . ($ratio < 50 ? "Red" : ($ratio < 75 ? "#FFEA20" : "#A7FC9D")) . ";'>$ratio%</td><td><a href='coverage.php?coverage=$filename'>$filename</a></td></tr>\n";
		}
		echo "</table>\n";
	}
	echo "<p><a href='coverage.php?coverage=0'>Start new coverage</a></p>\n";
}
?>

</body>
</html>
