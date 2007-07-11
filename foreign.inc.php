<?php

page_header(lang('Foreign key') . ": " . htmlspecialchars($_GET["foreign"]));

if (strlen($_GET["name"])) {
	$foreign_keys = foreign_keys($_GET["foreign"]);
	$foreign_key = $foreign_keys[$_GET["name"]];
}
