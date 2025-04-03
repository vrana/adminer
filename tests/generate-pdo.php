#!/usr/bin/env php
<?php
// Katalon Recorder has global variables: https://docs.katalon.com/katalon-platform/plugins-and-add-ons/katalon-recorder-extension/get-your-job-done/automate-scenarios/global-variables-in-katalon-recorder
// It's possible to use them in URL in Katalon Studio but apparently not in Recorder: https://forum.katalon.com/t/45673/2

chdir(__DIR__);
foreach (glob("*.html") as $filename) {
	if (!preg_match('~^pdo-|elastic|screenshots~', $filename)) {
		$file = file_get_contents($filename);
		$file = preg_replace_callback('~/((adminer|editor)/[^?<]*)(\??)~', function ($match) {
			return "/$match[1]?ext=pdo" . ($match[3] ? "&amp;" : "");
		}, $file);
		$file = str_replace("<tr><td>open</td><td>/coverage.php?coverage=0</td><td></td></tr>\n", "", $file);
		$file = str_replace("<tr><td>click</td><td>link=Explain</td><td></td></tr>\n<tr><td>verifyTextPresent</td><td>Clustered Index Scan</td><td></td></tr>\n", "", $file); // MS SQL PDO doesn't support EXPLAIN
		preg_match_all("~//input\[@value='Login']~", $file, $matches, PREG_OFFSET_CAPTURE);
		list($val, $offset) = $matches[0][count($matches[0]) > 1 ? 1 : 0]; // MySQL log-ins three times, we check the second one
		$file = substr_replace($file, "</td><td></td></tr>\n<tr><td>verifyTextPresent</td><td>PDO_", $offset + strlen($val), 0);
		file_put_contents("pdo-$filename", $file);
	}
}
