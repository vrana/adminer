<?php
page_header(lang('View') . ": " . htmlspecialchars($_GET["view"]));
echo "<pre class='jush-sql'>" . htmlspecialchars(preg_replace('~^.* AS ~U', '', $mysql->result($mysql->query("SHOW CREATE VIEW " . idf_escape($_GET["view"])), 1))) . "</pre>\n";
