<?php

function dump($value)
{
	echo "<pre>";
	var_export($value);
	echo "</pre>\n";
}

function dumpe($value)
{
	dump($value);
	exit;
}
