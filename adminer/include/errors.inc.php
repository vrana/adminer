<?php
namespace Adminer;

error_reporting(24575); // all but E_DEPRECATED (overriding mysqli methods without types is deprecated)
set_error_handler(function ($errno, $errstr) {
	return !!preg_match('~^(Trying to access array offset on( value of type)? null|Undefined array key)~', $errstr);
}, E_WARNING | E_NOTICE); // warning since PHP 8.0
