#!/bin/sh

set -e

# Root directory.
BASEDIR=$( cd `dirname $0`/.. ; pwd )
cd "$BASEDIR"

php compile.php
php compile.php en
php compile.php de
php compile.php cs
php compile.php sk

php compile.php mysql
php compile.php mysql en
php compile.php mysql de
php compile.php mysql cs
php compile.php mysql sk

php compile.php editor
php compile.php editor en
php compile.php editor mysql
php compile.php editor mysql en
