# Adminer
**Adminer** is a full-featured database management tool written in PHP. It consists of a single file ready to deploy to the target server.
**Adminer Editor** offers data manipulation for end-users.

[Official Website](https://www.adminer.org/)

## Features
- **Supports:** MySQL, MariaDB, PostgreSQL, CockroachDB, SQLite, MS SQL, Oracle
- **Plugins for:** Elasticsearch, OpenSearch, SimpleDB, MongoDB, Redis, Firebird, ClickHouse, IGDB, IMAP
- **Requirements:** PHP 5.3+ (compiled file), PHP 7.4+ (source codes)

## Screenshot
![Table structure](https://www.adminer.org/static/screenshots/table.png)

## Installation
If downloaded from Git then run: `git submodule update --init` (or `composer install`)

- `adminer/index.php` - Run development version of Adminer
- `editor/index.php` - Run development version of Adminer Editor, needs `adminer/` next to it
- `editor/example.php` - Example customization
- `compile.php` - Create a single file version
- `lang.php` - Update translations
- `tests/*.spec.js` - Playwright end-to-end tests

## Deployment

Adminer is also available as an [official Docker image](https://hub.docker.com/_/adminer), and can be deployed with one click on [Easypanel](https://easypanel.io/) using its [official Adminer template](https://easypanel.io/templates/adminer).

## Plugins
There are several plugins distributed with Adminer, as well as many user-contributed plugins listed on the [Adminer Plugins page](https://www.adminer.org/plugins/).
