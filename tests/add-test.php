#!/usr/bin/env php
<?php
if ($argc != 2) {
	echo "Usage: cat | php add-test.php 'before'\n";
	echo "Purpose: Add the same test to all suites. 'before' is a regex for the following full test name (e.g. 'Clone').\n";
	exit(1);
}
$before = $argv[1];

echo "Paste test created for MySQL:\n";
$input = stream_get_contents(STDIN);

$urls = array( // this works for tests inside db, not e.g. for server overview
	"mysql" => "/adminer/?username=ODBC&db=adminer_test",
	"mariadb" => "/adminer/?server=localhost:3307&username=ODBC&db=adminer_test",
	"pgsql" => "/adminer/?pgsql=&username=ODBC&db=adminer_test&ns=public",
	"cockroachdb" => "/adminer/?pgsql=localhost:26257&username=ODBC&db=adminer_test&ns=public",
	"mssql" => "/adminer/?mssql=&username=ODBC&db=adminer_test&ns=dbo",
	"sqlite" => "/adminer/sqlite.php?sqlite=&username=ODBC&db=adminer_test.sqlite",
);

foreach ($urls as $driver => $url) {
	$filename = __DIR__ . "/$driver.html";
	$file = file_get_contents($filename);
	$test = str_replace(htmlspecialchars($urls['mysql']), htmlspecialchars($urls[$driver]), $input);
	$file = preg_replace("(<table.*\n.*>($before)<)", $test . '\0', $file);
	file_put_contents($filename, $file);
}

include __DIR__ . "/generate-pdo.php";
