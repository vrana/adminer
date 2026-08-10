<?php
namespace Adminer;

page_header(lang('Database schema'), "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

/** Get the column of a table, the referenced tables are placed right of the tables referencing them
* @param array<string, mixed[]> $referenced target table => referencing tables
* @param int[] $columns table => column, the computed columns are added to it
*/
function schema_column(string $table, array $referenced, array &$columns): int {
	if (!isset($columns[$table])) {
		$columns[$table] = 0; // stops the recursion in reference cycles
		foreach ((array) idx($referenced, $table) as $name => $refs) {
			if ($name != $table) { // a self-reference doesn't move the table
				$columns[$table] = max($columns[$table], schema_column($name, $referenced, $columns) + 1);
			}
		}
	}
	return $columns[$table];
}

/** @var array{float, float}[] */
$table_pos = array();
$table_pos_js = array();
$table_pos_default = array();
/** @var float[][] */
$field_pos = array(); // table => field => position
$SCHEMA = ($_GET["schema"] ?: $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]); // $_COOKIE["adminer_schema"] was used before 3.2.0 //! ':' in table name
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array((float) $match[2], (float) $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

/** @var array{fields:Field[], pos:array{float, float}, references:string[][][]}[] */
$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
/** @var array<string, mixed[][]> */
$foreign_keys = array(); // table => foreign keys
$all_fields = driver()->allFields();
/** @var bool[] */
$hidden = array(); // tables hidden by a plugin, their references are not displayed either
$table_statuses = array();
foreach (table_status('', true) as $table => $table_status) {
	if (!is_view($table_status)) {
		if (adminer()->tableName($table_status) != "") {
			$table_statuses[$table] = $table_status;
		} else {
			$hidden[$table] = true;
		}
	}
}
foreach ($table_statuses as $table => $table_status) {
	$pos = 0;
	$schema[$table]["fields"] = array();
	foreach ($all_fields[$table] as $field) {
		$pos += 1.25;
		$field_pos[$table][$field["field"]] = $pos;
		$schema[$table]["fields"][$field["field"]] = $field;
	}
	foreach (adminer()->foreignKeys($table) as $val) {
		// the target must be displayed too, otherwise the reference would be drawn as a line ending in nothing
		if ($val["db"] == "" && $val["ns"] == "" && !$hidden[$val["table"]]) {
			$foreign_keys[$table][] = $val;
			$referenced[$val["table"]][$table] = array(); // the lefts are computed after the layout
		}
	}
}

// arrange the tables in a grid, the columns are given by the foreign keys
/** @var int[] */
$columns = array(); // table => column
$grid = array(); // column => tables
/** @var float[] */
$widths = array(); // column => width in em
/** @var int[] */
$gutters = array(); // column => number of reference lines left of the column
foreach (array_keys($schema) as $name) {
	schema_column($name, $referenced, $columns);
}
arsort($columns); // process the rightmost tables first, their columns are final when the tables referencing them are moved
foreach ($columns as $name => $column) {
	// move the table as far right as its references allow, a reference spanning several columns would be drawn over the tables in between
	$min = null;
	foreach ((array) idx($foreign_keys, $name) as $val) {
		if ($val["table"] != $name && $schema[$val["table"]]) {
			$min = ($min === null ? $columns[$val["table"]] : min($min, $columns[$val["table"]]));
		}
	}
	$columns[$name] = max($column, (int) $min - 1);
}
foreach ($schema as $name => $table) {
	$column = $columns[$name];
	$grid[$column][] = $name;
	$text_width = .75 * strlen($name); // the table name is bold so its characters are wider than the columns
	foreach ($table["fields"] as $field) {
		$text_width = max($text_width, .65 * strlen($field["field"]));
	}
	// strlen() overestimates multi-byte names on purpose, a too narrow column would be overlapped by the reference lines
	$widths[$column] = max(idx($widths, $column, 0), ceil($text_width) + 1); // whole em keeps the computed positions free of rounding errors
}
foreach ($foreign_keys as $name => $vals) {
	foreach ($vals as $val) {
		// the line is drawn right of the referencing table, or left of it if the referenced table is not in a column to the right
		$gutter = $columns[$name] + (idx($columns, $val["table"], $columns[$name]) > $columns[$name] ? 1 : 0);
		$gutters[$gutter] = idx($gutters, $gutter, 0) + 1;
	}
}
ksort($grid);

$height = 0;
$width = 0;
$column_left = 0;
$prev_column = null;
/** @var float[] */
$table_left = array(); // table => left
/** @var float[] */
$table_width = array(); // table => width of its column
foreach ($grid as $column => $tables) {
	if ($prev_column !== null) {
		// 1 em for the reference line, .7 em of space, round() to not print the rounding errors of .1
		$column_left = round($column_left + $widths[$prev_column] + 1.7 + idx($gutters, $column, 0) * .1, 1);
		// order the tables by the average position of the tables they are connected to
		$order = array();
		foreach ($tables as $name) {
			$sum = 0;
			$count = 0;
			foreach (array_merge(array_column((array) idx($foreign_keys, $name), "table"), array_keys((array) idx($referenced, $name))) as $name2) {
				if ($schema[$name2] && $columns[$name2] < $column) {
					$sum += $schema[$name2]["pos"][0];
					$count++;
				}
			}
			$order[$name] = ($count ? $sum / $count : $height); // tables without a placed neighbor go to the bottom
		}
		asort($order);
		$tables = array_keys($order);
	}
	$top = 0;
	foreach ($tables as $name) {
		$pos = 1.25 * count($schema[$name]["fields"]);
		$schema[$name]["pos"] = ($table_pos[$name] ?: array($top, $column_left)); // the saved position wins but the table still occupies its slot
		$table_left[$name] = $schema[$name]["pos"][1];
		$table_width[$name] = $widths[$column];
		$top += 2.5 + $pos;
		$height = max($height, $schema[$name]["pos"][0] + 2.5 + $pos);
		$width = max($width, round($schema[$name]["pos"][1] + $widths[$column], 1));
		if (!$table_pos[$name]) {
			$table_pos_default[] = "\n\t'" . js_escape($name) . "': [ " . $schema[$name]["pos"][0] . ", " . $schema[$name]["pos"][1] . " ]";
		}
	}
	$prev_column = $column;
}

// draw the reference lines 1 em right of the referencing table, the referenced table connects to them by its left edge
/** @var array<numeric-string, bool> */
$lefts = array();
$base_lefts = array();
foreach ($foreign_keys as $name => $vals) {
	foreach ($vals as $val) {
		$target_left = idx($table_left, $val["table"], $table_left[$name]);
		$source_right = $table_left[$name] + $table_width[$name];
		$right = ($target_left - 1 > $source_right); // a self-reference or a dragged table can put the referenced table elsewhere, then draw the line left of both, as before 6.0.1
		$left = ($right ? $source_right + 1 : min($table_left[$name], $target_left) - 1);
		$base = idx($base_lefts, (string) $left, 0);
		$base_lefts[(string) $left] = $base + 1;
		$left = round($right ? min($left + $base * .1, $target_left - 1) : $left - $base * .1, 1);
		while ($lefts[(string) $left]) {
			// find free $left
			$left -= .0001;
		}
		$schema[$name]["references"][$val["table"]][(string) $left] = array($val["source"], $val["target"]);
		$referenced[$val["table"]][$name][(string) $left] = $val["target"];
		$lefts[(string) $left] = true;
	}
}

?>
<div id="schema" style="height: <?php echo $height; ?>em; width: <?php echo $width; ?>em;">
<script<?php echo nonce(); ?>>
const tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
const tablePosDefault = {<?php echo implode(",", $table_pos_default) . "\n"; ?>};
const em = qs('#schema').offsetHeight / <?php echo $height; ?>;
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, '<?php echo js_escape(DB); ?>');
</script>
<?php
foreach ($schema as $name => $table) {
	// the width is definite so that the browser doesn't have to measure the table to resolve the 100% of the references
	echo "<div class='table'" . on('mousedown', 'schemaMousedown') . " style='top: " . $table["pos"][0] . "em; left: " . $table["pos"][1] . "em; width: " . $table_width[$name] . "em;'>";
	echo '<a href="' . h(ME) . 'table=' . url_escape($name) . '"><b>' . h($name) . "</b></a>";

	foreach ($table["fields"] as $field) {
		$val = '<span' . type_class($field["type"])
			. ' title="' . h($field["type"] . ($field["length"] ? "($field[length])" : "") . ($field["null"] ? " NULL" : '')) . '">'
			. h($field["field"]) . '</span>';
		echo "<br>" . ($field["primary"] ? "<i>$val</i>" : $val);
	}

	foreach ((array) $table["references"] as $target_name => $refs) {
		foreach ($refs as $left => $ref) {
			$left1 = $left - $table["pos"][1];
			// a positive $left1 means that the target is in a column to the right, then the line leaves this table on its right edge
			// 100% is the width of the table which is not known here, calc() lets the browser compute it
			$style = ($left1 > 0 ? "left: 100%; width: calc($left1" . "em - 100%)" : "left: $left1" . "em");
			$width = ($left1 > 0 ? "100%" : (-$left1) . "em");
			$i = 0;
			foreach ($ref[0] as $source) {
				echo "\n<div class='references' title='" . h($target_name) . "' id='refs$left-" . ($i++) . "' style='$style"
					. "; top: " . $field_pos[$name][$source] . "em; padding-top: .5em;'>"
					. "<div style='border-top: 1px solid gray; width: $width;'></div></div>"
				;
			}
		}
	}

	foreach ((array) $referenced[$name] as $target_name => $refs) {
		foreach ($refs as $left => $targets) {
			$left1 = $left - $table["pos"][1];
			$i = 0;
			foreach ($targets as $target) {
				echo "\n<div class='references arrow' title='" . h($target_name) . "' id='refd$left-" . ($i++) . "' style='left: $left1" . "em; top: " . $field_pos[$name][$target] . "em;'>"
					. "<div style='height: .5em; border-bottom: 1px solid gray; width: " . (-$left1) . "em;'></div>"
					. "</div>"
				;
			}
		}
	}

	echo "\n</div>\n";
}

foreach ($schema as $name => $table) {
	foreach ((array) $table["references"] as $target_name => $refs) {
		if ($schema[$target_name]) { // otherwise table in another schema
			foreach ($refs as $left => $ref) {
				$min_pos = $height;
				$max_pos = -10;
				foreach ($ref[0] as $key => $source) {
					$pos1 = $table["pos"][0] + $field_pos[$name][$source];
					$pos2 = $schema[$target_name]["pos"][0] + $field_pos[$target_name][$ref[1][$key]];
					$min_pos = min($min_pos, $pos1, $pos2);
					$max_pos = max($max_pos, $pos1, $pos2);
				}
				echo "<div class='references' id='refl$left' style='left: $left" . "em; top: $min_pos"
					. "em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: " . ($max_pos - $min_pos) . "em;'></div></div>\n";
			}
		}
	}
}
?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . url_escape($SCHEMA)); ?>" id="schema-link"><?php echo lang('Permanent link'); ?></a>
