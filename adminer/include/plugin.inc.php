<?php
namespace Adminer;

// the overridable methods don't use return type declarations so that plugins can be compatible with PHP 5
abstract class Plugin {
	/** @var array<literal-string, string|list<string>>[] */ protected static $translations = array(); // key is language code

	/** Get plain text plugin description; empty string means to use the first line of class doc-comment
	* @return string
	*/
	function description() {
		return '';
	}

	/** Translate a string from static::$translations; use Adminer\lang() for strings used by Adminer
	* @param literal-string $idf
	* @param float|string $number
	*/
	protected function lang(string $idf, $number = null): string {
		$args = func_get_args();
		$args[0] = idx(static::$translations[LANG], $idf) ?: $idf;
		return call_user_func_array('Adminer\lang_format', $args);
	}
}
