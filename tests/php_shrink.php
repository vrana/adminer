<?php
include __DIR__ . "/../adminer/include/errors.inc.php";
include __DIR__ . "/../php_shrink.inc.php";

function check($code, $expected) {
	$shrinked = php_shrink("<?php\n$code");
	if ("<?php\n" . str_replace(" ", "\n", $expected) . "" != $shrinked) {
		$backtrace = reset(debug_backtrace());
		echo "$backtrace[file]:$backtrace[line]:" . str_replace("\n", " ", substr($shrinked, 6)) . "\n";
	}
}

check('$ab = 1;', '$a=1;');
check('function f($ab, $cd = 1) { return $ab; }', 'function f($a,$b=1){return$a;}');
check('class C { var $ab = 1; }', 'class C{var$ab=1;}');
check('class C { public $ab = 1; }', 'class C{var$ab=1;}');
check('class C { protected $ab = 1; }', 'class C{protected$ab=1;}');
check('class C { private $ab = 1; }', 'class C{private$ab=1;}');
check('class C { private $ab = 1; }', 'class C{private$ab=1;}');
check('class C { private function f($ab) { return $ab; }}', 'class C{private function f($a){return$a;}}');
check('class C { public function f($ab) { return $ab; }}', 'class C{function f($a){return$a;}}');
check('class C { private static $ab; }', 'class C{private static$ab;}');
check('new \stdClass;', 'new \stdClass;');
