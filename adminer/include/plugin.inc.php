<?php
namespace Adminer;

abstract class Plugin {
	/** @var array<literal-string, string|list<string>>[] */ protected static $translations = array(); // key is language code

	/** Translate a string from static::$translations; use Adminer\lang() for strings used by Adminer
	* @param literal-string $idf
	* @param float|string $number
	*/
	protected function lang(string $idf, $number = null) {
		$args = func_get_args();
		$args[0] = idx(static::$translations[LANG], $idf) ?: $idf;
		return call_user_func_array('Adminer\lang_format', $args);
	}
}
