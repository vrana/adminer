<?php
// To create Adminer just for Elasticsearch, run `../compile.php elastic`.

function adminer_object() {
	include_once "../plugins/drivers/elastic.php";
	return new Adminer\Adminer;
}

include "./index.php";
