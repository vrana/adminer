<?php
namespace Adminer;

// served before the authentication because the manifest is fetched without connecting to a database
header("Content-Type: application/manifest+json; charset=utf-8");
header("Cache-Control: no-cache"); // the manifest depends on the host and the language
echo json_encode(adminer()->manifest(), 64 | 256); // 64 - JSON_UNESCAPED_SLASHES, 256 - JSON_UNESCAPED_UNICODE available since PHP 5.4
exit;
