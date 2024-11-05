<?php

function dump($value)
{
	$cli = PHP_SAPI == 'cli';

	if (!$cli)  {
		echo "<pre>";
	}

	var_export($value);

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
