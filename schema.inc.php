<?php
page_header(lang('Database schema'), array(), $_GET["db"]);

$schema = array();
$result = $mysql->query("SHOW TABLE STATUS");
while ($row = $result->fetch_assoc()) {
	if (!isset($row["Engine"])) { // view
		continue;
	}
	$schema[$row["Name"]]["fields"] = fields($row["Name"]);
	if ($row["Engine"] == "InnoDB") {
		foreach (foreign_keys($row["Name"]) as $val) {
			if (!$val["db"]) {
				$schema[$row["Name"]]["references"][$val["table"]][] = array_combine($val["source"], $val["target"]);
			}
		}
	}
}
$result->free();
?>
<div id="schema">
<?php
$top = 0;
$positions = array();
foreach ($schema as $name => $table) {
	echo "<div class='table' style='top: $top" . "em;'>\n";
	echo '<a href="' . htmlspecialchars($SELF) . 'table=' . urlencode($name) . '"><strong>' . htmlspecialchars($name) . "</strong></a><br />\n";
	foreach (fields($name) as $field) {
		$val = htmlspecialchars($field["field"]);
		if (preg_match('~char|text~', $field["type"])) {
			$val = "<span class='char'>$val</span>";
		} elseif (preg_match('~date|time|year~', $field["type"])) {
			$val = "<span class='date'>$val</span>";
		} elseif (preg_match('~binary|blob~', $field["type"])) {
			$val = "<span class='binary'>$val</span>";
		} elseif (preg_match('~enum|set~', $field["type"])) {
			$val = "<span class='enum'>$val</span>";
		}
		echo ($field["primary"] ? "<em>$val</em>" : $val) . "<br />\n";
		$top += 1.25;
		$positions[$name][$field["field"]] = $top;
	}
	echo "</div>\n";
	$top += 2.5;
}
$left = -14;
foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $ref) {
			$min_pos = $top;
			$max_pos = 0;
			foreach ($ref as $source => $target) {
				$pos1 = $positions[$name][$source];
				$pos2 = $positions[$target_name][$target];
				$min_pos = min($min_pos, $pos1, $pos2);
				$max_pos = max($max_pos, $pos1, $pos2);
				echo "<div style='left: " . ($left+1) . "px; top: $pos1" . "em; padding-top: .5em;'><div style='border-top: 1px solid Black; width: " . (-$left-2) . "px;'></div></div>\n";
				echo "<div style='left: " . ($left+1) . "px; top: $pos2" . "em; width: " . (-$left-2) . "px; height: 1.25em; background: url(arrow.gif) no-repeat right center;'><div style='height: .5em; border-bottom: 1px solid Black; width: " . (-$left-14) . "px;'></div></div>\n";
			}
			echo "<div style='left: $left" . "px; top: $min_pos" . "em; padding: .5em 0;' /><div style='border-right: 1px solid Black; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
			$left -= 2;
		}
	}
}
//! JavaScript for dragging tables
?>
</div>
