<?php
page_header(lang('Database schema') . ": " . htmlspecialchars($_GET["db"]));

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
				$schema[$val["table"]]["referenced"][$row["Name"]][] = array_combine($val["target"], $val["source"]);
			}
		}
	}
}
$result->free();
?>
<div id="schema">
<?php
function schema_table($name, $table) {
	global $mysql, $SELF;
	static $top = 0;
	echo "<div style='top: $top" . "em;'>\n";
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
	}
	echo "</div>\n";
	$top += 2.5;
}
foreach ($schema as $name => $table) {
	schema_table($name, $table);
}
?>
</div>
