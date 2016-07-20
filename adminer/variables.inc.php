<?php
$status = isset($_GET["status"]);
page_header($status ? lang('Status') : lang('Variables'));
$adminer->startLinks();

$variables = ($status ? show_status() : show_variables());
if (!$variables) {
	echo "<p class='message'>" . lang('No rows.') . "\n";
} else {
	echo "<table cellspacing='0'>\n";
	foreach ($variables as $key => $val) {
		echo "<tr>";
		echo "<th><code class='jush-" . $jush . ($status ? "status" : "set") . "'>" . h($key) . "</code>";
		echo "<td>" . nbsp($val);
		if (is_numeric($val))
		{
			if (($val >= 1024) && (substr($key, -5) == "_size" || strpos($key, "_bytes")))
			{
				if ($val >= 1073741824)
					$val_descr = number_format($val / 1073741824, 2) . ' GB';
				else if ($val >= 1048576)
					$val_descr = number_format($val / 1048576, 2) . ' MB';
				else
					$val_descr = number_format($val / 1024, 2) . ' KB';
				echo " ({$val_descr})";
			}
			else if (($val >= 60) && (strpos($key, "_timeout") || strpos($key, "_interval")))
			{
				// use date() for compatibility with old PHP
				$reset_hours = 365*86400-3600;
				$val_descr = "";

				if ($val >= 10000 && ((intval($val / 10000) * 10000) == $val))			// check miliseconds
				{
					if ($val > 1000000)
						$val_descr = date("H:i:s", $val/1000000+$reset_hours);
					else
						$val_descr = number_format($val / 1000, 3) . " ms";
				}
				else
				{
					$days = round($val / 86400);
					$val -= $days * 86400;
					$val_descr = ($days ? $days."d " : "") . date("H:i:s", $val+$reset_hours);
				}
				echo " ({$val_descr})";
			}
		}
	}
	echo "</table>\n";
}
