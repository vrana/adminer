<?php
// Regenerate Plugins::officialChecksums() in adminer/include/plugins.inc.php.
// Run this after changing any file in plugins/; Adminer uses the checksums to detect plugins not matching its version.
namespace Adminer;

$return = "";
$filenames = array_merge(glob(__DIR__ . "/plugins/*.php"), glob(__DIR__ . "/plugins/drivers/*.php"));
foreach ($filenames as $filename) {
	// this transformation is matched by Plugins::checksums()
	$file = str_replace("\r", "", file_get_contents($filename));
	$file = preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s', '', $file);
	$crc = dechex(crc32($file));
	$return .= "\t\t\t'" . basename($filename, '.php') . "' => '$crc',\n";
}

$path = __DIR__ . "/adminer/include/plugins.inc.php";
$plugins = preg_replace(
	'~(function officialChecksums\(\): array \{\n\t\treturn array\(\n).*?(\t\t\);)~s',
	"\$1$return\$2",
	file_get_contents($path),
	1,
	$count
);
if (!$count) {
	echo "officialChecksums() not found in $path\n";
	exit(1);
}
file_put_contents($path, $plugins);
echo count($filenames) . " plugins\n";
