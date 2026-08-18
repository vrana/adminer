#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../../adminer/include/errors.inc.php";
require __DIR__ . "/../../adminer/include/functions.inc.php";

// Test splitting the server name to scheme, host, port, socket and path.
// Prints found errors, prints nothing and exits with 0 if everything is OK.

/** Get the parts as a string, e.g. "scheme=https host=::1 port=8000 path=/x" */
function join_server(?array $parts): string {
	if (!$parts) {
		return 'invalid';
	}
	$return = array();
	foreach ($parts as $key => $val) {
		if ($val != "") {
			$return[] = "$key=$val";
		}
	}
	return implode(" ", $return);
}

$tests = array(
	'' => '',
	'localhost' => 'host=localhost',
	'localhost:3307' => 'host=localhost port=3307',
	'127.0.0.1' => 'host=127.0.0.1',
	'::1' => 'host=::1',
	'2001:0db8::1428:57ab' => 'host=2001:0db8::1428:57ab',
	'fe80::1' => 'host=fe80::1',
	'::ffff:127.0.0.1' => 'host=::ffff:127.0.0.1',
	'1:2:3:4:5:6:7:8' => 'host=1:2:3:4:5:6:7:8',
	'1:2:3:4:5:6:1.2.3.4' => 'host=1:2:3:4:5:6:1.2.3.4',
	'[2001:0db8::1428:57ab]' => 'host=2001:0db8::1428:57ab',
	'[2001:0db8::1428:57ab]:3307' => 'host=2001:0db8::1428:57ab port=3307',
	'[::1]:3307' => 'host=::1 port=3307',
	'https://[::1]:8080/x' => 'scheme=https host=::1 port=8080 path=/x',
	':/tmp/mysql.sock' => 'socket=/tmp/mysql.sock', // https://github.com/vrana/adminer/pull/1199
	'/tmp' => 'host=/tmp', // PostgreSQL socket
	'/tmp:5433' => 'host=/tmp port=5433', // PostgreSQL socket /tmp/.s.PGSQL.5433
	'https://elastic' => 'scheme=https host=elastic',
	'https://elastic:8000' => 'scheme=https host=elastic port=8000',
	'HTTP://Elastic' => 'scheme=http host=Elastic', // the scheme is case insensitive
	'http://localhost:9200/elastic/' => 'scheme=http host=localhost port=9200 path=/elastic/', // Elasticsearch behind a reverse proxy
	'localhost:1521/XE' => 'host=localhost port=1521 path=/XE', // Oracle Easy Connect
	'http://127.0.0.1:22' => 'scheme=http host=127.0.0.1 port=22', // https://github.com/vrana/adminer/security/advisories/GHSA-37gx-66gx-rxgh
	'ssl://redis' => 'scheme=ssl host=redis',
	':/cloudsql/project:region:instance' => 'socket=/cloudsql/project:region:instance', // https://github.com/vrana/adminer/pull/1305
	'stack_service' => 'host=stack_service', // https://github.com/vrana/adminer/commit/3faf095#r193072212
	':3307' => 'port=3307',
	// the port must be reported so that the privileged ports can be rejected
	// https://github.com/vrana/adminer/security/advisories/GHSA-rwxg-xph9-82cj
	'127.0.0.1:80/x' => 'host=127.0.0.1 port=80 path=/x',
	'10.0.0.5:22/x' => 'host=10.0.0.5 port=22 path=/x',
	'localhost:22/elastic/' => 'host=localhost port=22 path=/elastic/',
	'abcd:22' => 'host=abcd port=22', // it is not an IPv6 address, MySQL would connect to the port 22
	'10:22' => 'host=10 port=22',
	// invalid
	'127.0.0.1:80x' => 'invalid',
	'127.0.0.1:80.' => 'invalid',
	'localhost:2200e-2' => 'invalid',
	'localhost:' => 'invalid',
	'other-host:/tmp/mysql.sock' => 'invalid', // host with socket isn't supported
	':' => 'invalid',
	':memory:' => 'invalid', // SQLite doesn't use parse_server()
	'[a]b:80' => 'invalid',
	'[localhost:22]' => 'invalid',
	'1:2:3' => 'invalid', // invalid IPv6
	'localhost:22/elastic/:9200' => 'invalid', // the path can't contain :
	'http://localhost:22/elastic/:9200' => 'invalid',
	'localhost/../x' => 'host=localhost path=/../x', //! the path isn't normalized
);

$errors = 0;

foreach ($tests as $server => $expected) {
	$actual = join_server(parse_server(strval($server)));
	if ($actual !== $expected) {
		echo "$server results in $actual, expected $expected\n";
		$errors++;
	}
}

// Verify the server against the parts supported by a driver, the same as SqlDriver::connect().
/** @param list<string> $schemes */
function server_error(string $server, array $schemes = array(), bool $socket = false, bool $path = false): string {
	$parts = parse_server($server);
	if (
		!$parts
		|| ($parts["scheme"] && !in_array($parts["scheme"], $schemes))
		|| ($parts["socket"] && !$socket)
		|| ($parts["path"] && !$path)
		|| (substr($parts["host"], 0, 1) == "/" && !$socket)
	) {
		return 'Invalid server.';
	}
	if ($parts["port"] != "" && ($parts["port"] < 1024 || $parts["port"] > 65535)) {
		return 'Connecting to privileged ports is not allowed.';
	}
	return '';
}

$mysql = array(array(), true, false); // $serverSchemes, $serverSocket, $serverPath
$elastic = array(array("http", "https"), false, true);

$checks = array(
	// [$server, $driver, $expected error]
	array('localhost:3307', $mysql, ''),
	array(':/tmp/mysql.sock', $mysql, ''),
	array('/tmp:5433', $mysql, ''),
	array('[::1]:3307', $mysql, ''),
	array('127.0.0.1:80/x', $mysql, 'Invalid server.'), // GHSA-rwxg-xph9-82cj, MySQL supports no path
	array('10.0.0.5:22/x', $mysql, 'Invalid server.'),
	array('abcd:22', $mysql, 'Connecting to privileged ports is not allowed.'),
	array('10:22', $mysql, 'Connecting to privileged ports is not allowed.'),
	array('127.0.0.1:80', $mysql, 'Connecting to privileged ports is not allowed.'),
	array('127.0.0.1:0080', $mysql, 'Connecting to privileged ports is not allowed.'),
	array('127.0.0.1:99999', $mysql, 'Connecting to privileged ports is not allowed.'),
	array('127.0.0.1:80x', $mysql, 'Invalid server.'),
	array('[localhost:22]', $mysql, 'Invalid server.'),
	array('https://elastic:8000', $mysql, 'Invalid server.'), // MySQL doesn't support a scheme
	array('localhost:8080/x', $mysql, 'Invalid server.'), // MySQL doesn't support a path
	array('http://localhost:9200/elastic/', $elastic, ''),
	array('https://elastic', $elastic, ''),
	array('ssl://elastic', $elastic, 'Invalid server.'), // only http and https
	array(':/tmp/socket', $elastic, 'Invalid server.'),
	array('/tmp', $elastic, 'Invalid server.'),
	array('http://localhost:22/elastic/', $elastic, 'Connecting to privileged ports is not allowed.'),
);

foreach ($checks as $check) {
	list($server, $driver, $expected) = $check;
	$actual = server_error($server, $driver[0], $driver[1], $driver[2]);
	if ($actual !== $expected) {
		echo "$server results in " . ($actual ?: 'no error') . ", expected " . ($expected ?: 'no error') . "\n";
		$errors++;
	}
}

exit($errors ? 1 : 0);
