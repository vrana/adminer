<?php
// get path from first argument
$file = $argv[1];
error_log("Loading file: $file");
$path = dirname($file);

// clear file cache
clearstatcache();
$cwd = getcwd();
chdir($path);
$class = eval('?>' . file_get_contents($file));
chdir($cwd);
if (is_string($class) && class_exists($class)) {
    $instance = new $class;
    echo json_encode([
        'class' => $class,
        'instance' => $instance,
    ]);
} else {
    echo 'false';
}
