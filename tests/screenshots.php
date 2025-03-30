#!/usr/bin/env php
<?php
foreach (array(
	'create' => array(1106, 412),
	'dark' => array(816, 750),
	'database' => array(896, 666),
	'db' => array(1258, 752),
	'dump' => array(784, 450),
	'edit' => array(1006, 336),
	'login' => array(628, 326),
	'select' => array(924, 810),
	'schema' => array(690, 406),
	'sql' => array(870, 788),
	'table' => array(816, 750),
) as $filename => list($w, $h)) {
	$im = imagecreatefrompng("screenshots/$filename.png");
	$im2 = imagecreatetruecolor($w, $h);
	imagecopy($im2, $im, 0, 0, 0, 0, $w, $h);
	imagepng($im2, "cropped/$filename.png");
}
