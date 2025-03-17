# Adminer

**Adminer** is a full-featured database management tool written in PHP.
It consists of a single file ready to deploy to the target server.
**Adminer Editor** offers data manipulation for end-users.

https://www.adminer.org/

- **Supports:** MySQL, MariaDB, PostgreSQL, CockroachDB, SQLite, MS SQL, Oracle
- **Plugins for:** Elasticsearch, SimpleDB, MongoDB, Firebird, ClickHouse, IMAP
- **Requirements:** PHP 5.3+

## Screenshot
![screenshot](https://www.adminer.org/static/screenshots/table.png)

## Installation
If downloaded from Git then run: `git submodule update --init`

- `adminer/index.php` - Run development version of Adminer
- `editor/index.php` - Run development version of Adminer Editor
- `editor/example.php` - Example customization
- `adminer/sqlite.php` - Development version of Adminer with SQLite allowed
- `editor/sqlite.php` - Development version of Editor with SQLite allowed
- `adminer/designs.php` - Development version of Adminer with `adminer.css` switcher
- `compile.php` - Create a single file version
- `lang.php` - Update translations
- `tests/*.html` - Katalon Recorder test suites

## Plugins
There are [several plugins](plugins/) distributed with Adminer and there are also many user-contributed plugins linked from https://www.adminer.org/plugins/.
To use a plugin, simply upload it to `adminer-plugins/` next to `adminer.php`.

```
- adminer.php
- adminer-plugins
    - config.php
    - dump-xml.php
    - login-password-less.php
```

Some plugins require configuration. To use them, you need to create another file in `adminer-plugins/`:

```php
<?php // config.php
require_once __DIR__ . "/login-password-less.php";

return array(
    new AdminerLoginPasswordLess('$2y$07$Czp9G/aLi3AnaUqpvkF05OHO1LMizrAgMLvnaOdvQovHaRv28XDhG'),
);
```
