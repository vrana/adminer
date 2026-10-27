<?php
namespace Adminer;

// served before the authentication because the manifest is fetched without connecting to a database
header("Content-Type: application/manifest+json; charset=utf-8");
header("Cache-Control: no-cache"); // the manifest depends on the host and the language
echo json_encode(adminer()->manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
