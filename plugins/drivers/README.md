Using drivers: https://www.adminer.org/plugins/#use

Developing drivers: https://www.adminer.org/en/drivers/

Use the same type declarations as the parent classes and as adminer/drivers/mysql.inc.php.

The released drivers are built by `php compile.php drivers` which strips the types unsupported by PHP 5, the same as in the compiled Adminer.
The sources in this directory therefore work only with the development version.
