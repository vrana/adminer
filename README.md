# Adminer

**Adminer** is a full-featured database management tool written in PHP. It consists of a single file ready to deploy
to the target server. **Adminer Editor** offers data manipulation for end-users.

https://www.adminer.org/

- **Supports:** MySQL, MariaDB, PostgreSQL, CockroachDB, SQLite, MS SQL, Oracle
- **Plugins for:** Elasticsearch, SimpleDB, MongoDB, Firebird, ClickHouse
- **Requirements:** PHP 5.3+

## Screenshot
![screenshot](https://www.adminer.org/static/screenshots/table.png)

## Installation
If downloaded from Git then run: `git submodule update --init`

- `adminer/index.php` - Run development version of Adminer
- `editor/index.php` - Run development version of Adminer Editor
- `editor/example.php` - Example customization
- `plugins/readme.txt` - Plugins for Adminer and Adminer Editor
- `adminer/plugin.php` - Plugin demo
- `adminer/sqlite.php` - Development version of Adminer with SQLite allowed
- `editor/sqlite.php` - Development version of Editor with SQLite allowed
- `adminer/designs.php` - Development version of Adminer with `adminer.css` switcher
- `compile.php` - Create a single file version
- `lang.php` - Update translations
- `tests/*.html` - Katalon Recorder test suites
