<?php
namespace Adminer;

error_reporting(24575); // all but E_DEPRECATED (overriding mysqli methods without types is deprecated)
set_error_handler(function ($errno, $errstr) {
	// "offset on null" mutes $_GET["fields"][0] if there's no ?fields[]= (62017e3 is a wrong fix for this)
	// "Undefined array key" mutes $_GET["q"] if there's no ?q=
	// "Undefined offset" and "Undefined index" are older messages for the same thing
	return !!preg_match('~^(Trying to access array offset on( value of type)? null|Undefined (array key|offset|index))~', $errstr);
}, E_WARNING | E_NOTICE); // warning since PHP 8.0
