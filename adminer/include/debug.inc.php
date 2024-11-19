<?php

function dump($value)
{
	$cli = PHP_SAPI == 'cli';

	if (!$cli)  {
		echo "<pre>";
	}

	$export = var_export($value, true);
	$export = preg_replace('~=>\s+array\s*~', "=> array", $export);
	$export = preg_replace('~\(\s+\)~', "()", $export);
	echo $export;

	if (!$cli) {
		echo "</pre>";
	}

	echo "\n";
}

function dumpe($value)
{
	dump($value);
	exit;
}
