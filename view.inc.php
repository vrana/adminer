<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
echo "<pre class='jush-sql'>" . htmlspecialchars(preg_replace('~^.* AS ~U', '', $mysql->result($mysql->query("SHOW CREATE VIEW " . idf_escape($_GET["view"])), 1))) . "</pre>\n";
echo '<p><a href="' . htmlspecialchars($SELF) . 'createv=' . urlencode($_GET["view"]) . '">' . lang('Alter view') . "</a></p>\n";
