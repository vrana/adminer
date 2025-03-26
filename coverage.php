<!DOCTYPE html>
<html lang="cs">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Coverage</title>

<body>

<?php
include "./adminer/include/errors.inc.php";

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

$coverage_filename = sys_get_temp_dir() . "/adminer.coverage";
if (!extension_loaded("xdebug")) {
	echo "<p class='error'>Xdebug has to be enabled.\n";
} elseif ($_GET["coverage"] === "0") {
	file_put_contents($coverage_filename, serialize(array()));
	echo "<p class='message'>Coverage started.\n";
} elseif (preg_match('~^(adminer|editor)/(include/|drivers/)?[-_.a-z0-9]+$~i', $_GET["coverage"])) {
	// highlight single file
	$filename = $_GET["coverage"];
	$coverage = (file_exists($coverage_filename) ? unserialize(file_get_contents($coverage_filename)) : array());
	$file = explode("\n", substr(highlight_file($filename, true), 5, -6)); // unwrap <pre></pre>
	$prev_color = null;
	$s = "";
	echo "<pre>";
	for ($l=0; $l <= count($file); $l++) {
		$line = $file[$l];
		$color = "#C0FFC0"; // tested
		switch ($coverage[realpath($filename)][$l+1] ?? null) {
			case -1: // untested
				$color = "#FFC0C0";
				break;
			case -2: // dead code
				$color = "Silver";
				break;
			case null: // not executable
				$color = "";
				break;
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
			echo "</div>";
			$s = ($open_tags ? "<" . implode("><", $open_tags) . ">" : "");
			$prev_color = $color;
		}
		$s .= "$line\n";
	}
	echo "</pre>";
} else {
	if (file_exists($coverage_filename)) {
		// display list of files
		$coverage = unserialize(file_get_contents($coverage_filename));
		echo "<table border='1' cellspacing='0'>\n";
		foreach (array_merge(glob("adminer/*.php"), glob("adminer/drivers/*.php"), glob("adminer/include/*.php"), glob("editor/*.php"), glob("editor/include/*.php")) as $filename) {
			$cov = $coverage[realpath($filename)];
			$ratio = 0;
			if (is_array($cov)) {
				$values = array_count_values($cov);
				$ratio = round(100 - 100 * $values[-1] / (count($cov) - $values[-2]));
			}
			echo "<tr><td align='right' style='background-color: " . ($ratio < 50 ? "Red" : ($ratio < 75 ? "#FFEA20" : "#A7FC9D")) . ";'>$ratio%<td><a href='coverage.php?coverage=$filename'>$filename</a>\n";
		}
		echo "</table>\n";
	}
	echo "<p><a href='coverage.php?coverage=0'>Start new coverage</a>\n";
}
