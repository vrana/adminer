<?php
function adminer_errors($errno, $errstr) {
	return !!preg_match('~^(Trying to access array offset on( value of type)? null|Undefined (array key|property))~', $errstr);
}

error_reporting(6135); // errors and warnings
set_error_handler('adminer_errors', E_WARNING);
