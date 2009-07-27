<?php
function conversion_date($match) {
	return ($match["p1"] ? $match["p1"] : ($match["p2"] < 70 ? 20 : 19) . $match["p2"]) . "-$match[p3]$match[p4]-$match[p5]$match[p6]";
}
