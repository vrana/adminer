<?php
namespace Adminer;

// this session uses the name from php.ini because PHP stores the progress there, its cookie is created by uploadProgress()
// without the cookie there's nothing to read and session_start() would only create an empty session and send a cookie with the default parameters
$progress = null;
if (!defined("SID") && $_COOKIE[SESSION_NAME] != "") {
	session_start();
	$progress = $_SESSION[ini_get("session.upload_progress.prefix") . $_GET["upload"]];
}
header("Content-Type: application/json; charset=utf-8");
echo json_encode(isset($progress["bytes_processed"]) ? array($progress["bytes_processed"], $progress["content_length"]) : array());
exit;
