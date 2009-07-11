<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
$view = view($_GET["view"]);
echo "<pre class='jush-sql'>" . htmlspecialchars($view["select"]) . "</pre>\n";
echo '<p><a href="' . htmlspecialchars($SELF) . 'createv=' . urlencode($_GET["view"]) . '">' . lang('Alter view') . "</a>\n";
