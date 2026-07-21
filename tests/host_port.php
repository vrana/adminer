#!/usr/bin/env php
<?php
namespace Adminer;

require __DIR__ . "/../adminer/include/errors.inc.php";
require __DIR__ . "/../adminer/include/functions.inc.php";

$tests = array(
	'' => array('', ''),
	'localhost' => array('localhost', ''),
	'localhost:3307' => array('localhost', '3307'),
	'127.0.0.1' => array('127.0.0.1', ''),
	'::1' => array('::1', ''),
	'2001:0db8::1428:57ab' => array('2001:0db8::1428:57ab', ''),
	'fe80::1' => array('fe80::1', ''),
	'[2001:0db8::1428:57ab]' => array('2001:0db8::1428:57ab', ''),
	'[2001:0db8::1428:57ab]:3307' => array('2001:0db8::1428:57ab', '3307'),
	':/tmp/mysql.sock' => array('', '/tmp/mysql.sock'), // https://github.com/vrana/adminer/pull/1199
	'/tmp' => array('/tmp', ''), // PostgreSQL socket
	'/tmp:5433' => array('/tmp', '5433'), // PostgreSQL socket /tmp/.s.PGSQL.5433
	'https://elastic' => array('https://elastic', ''),
	'https://elastic:8000' => array('https://elastic', '8000'),
	'http://127.0.0.1:22' => array('http://127.0.0.1', '22'), // https://github.com/vrana/adminer/security/advisories/GHSA-37gx-66gx-rxgh
	'ssl://redis' => array('ssl://redis', ''),
	':/cloudsql/project:region:instance' => array('', '/cloudsql/project:region:instance'), // https://github.com/vrana/adminer/pull/1305
	'stack_service' => array('stack_service', ''), // https://github.com/vrana/adminer/commit/3faf095#r193072212
	':3307' => array('', '3307'),
	// invalid
	'other-host:/tmp/mysql.sock' => array('other-host:/tmp/mysql.sock', ''), // host with socket isn't supported
	'localhost:2200e-2' => array('localhost:2200e-2', ''),
	':' => array(':', ''),
	// rejected by auth.inc.php
	'[a]b:80' => array('[a]b', '80'),
	'https://[::1]:80' => array('https://[::1]:80', ''),
	// rejected by elastic.php
	'http://localhost:9200/elastic/' => array('http://localhost:9200/elastic/', ''), // legitimate (behind a reverse proxy) but not supported
	'http://localhost:22/elastic/:9200' => array('http://localhost:22/elastic/:9200', ''),
	'localhost:22/elastic/:9200' => array('localhost:22/elastic/:9200', ''),
	'[localhost:22]' => array('localhost:22', ''),
);

foreach ($tests as $server => $expected) {
	$actual = host_port($server);
	if ($actual !== $expected) {
		echo "$server results in " . implode(" : ", $actual) . "\n";
	}
}
