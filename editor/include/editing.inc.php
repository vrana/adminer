<?php
namespace Adminer;

/** @param string[] $paths JUSH => $path */
function doc_link(array $paths, string $text = ""): string {
	return "";
}

/** Check whether the column looks like boolean
* @param array{type: string, length?: string} $field single field returned from fields()
*/
function like_bool(array $field): bool {
	return $field["type"] == "bool" || (preg_match('~bit|tinyint~', $field["type"]) && $field["length"] == 1);
}
