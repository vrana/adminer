# Tests

The end-to-end tests in this directory connect to a database server of the tested driver, all of them using the database `adminer_test` - the screenshots and OpenSearch, which has no databases, are the exceptions.

## Running

The tests are stored in `tests/*.spec.js`, one file per driver plus [plugins.spec.js](/tests/plugins.spec.js), and run by [Playwright](https://playwright.dev/) in a headless browser:

- `composer e2e` runs all drivers, both with the native extension and with PDO.
- `composer e2e mysql` runs only the files matching mysql.
- `composer e2e -- mysql --project=native` runs only the native extension - Composer passes options through only after `--`.

Use `composer e2e -- --ui` to watch a test, `--headed --debug` to step through it; a failed run stores a trace in `tests/results/`, open it by `npx playwright show-trace`.
A new test can be recorded by `npx playwright codegen http://127.0.0.1:8000/adminer/`.
The helpers in [adminer.js](/tests/adminer.js) cover what Adminer does repeatedly: `link()` and `button()` take the first match because Adminer prints some links in the menu as well, and `setValue()` fills a field which jush replaces by a highlighted editor.

Each file logs in once and the tests inside it run in the order they are written, so a failing test stops the rest of the file.
The first test also removes what an interrupted run left behind, mostly by dropping the whole `adminer_test` database, so two runs of the same driver must never overlap.
The bulk table operations need a second target, so they create and drop `adminer_test2` - a database in MySQL and MariaDB, a schema in PostgreSQL, CockroachDB and MS SQL.
Every test fails also on a PHP error printed to any response and on a browser console error or an uncaught JavaScript exception, even if the page otherwise looks right.
Everything runs in a single worker, which takes about twenty minutes for all drivers with both extensions.
Parallelism would help little: the drivers use different database servers but they all share the `adminer_test` database name, the `native` and `pdo` projects of one driver work with the very same data, and the requests would queue in the development server anyway, because it handles one at a time unless `PHP_CLI_SERVER_WORKERS` is set (which needs `fork()`, so not on Windows).

## Development Server

The tests expect Adminer at <http://127.0.0.1:8000> (or at `ADMINER_URL`), served from the repository root by `php -S 127.0.0.1:8000`.
`display_errors` must be on, otherwise the tests never see the PHP errors they look for in the responses, and `file_uploads` must stay on (the default), otherwise the import tests find a disabled file field.
The tests fill in the standard login form, so a plugin changing it breaks them - `AdminerLoginServers` for example replaces the server field by a list.

The `native` and `pdo` projects run the same tests with both extensions of the driver, so PHP needs `mysqli` and `pdo_mysql`, `pgsql` and `pdo_pgsql`, `sqlite3` and `pdo_sqlite`, `sqlsrv` and `pdo_sqlsrv`.

## MySQL

The tests leave the server field empty, so MySQL must listen on the default host and port.
They log in as `ODBC` with the password `ODBC` and expect the server to reject a wrong one.
They create and drop the database, a user, events and routines, and they list the processes, so the user needs everything:

```sql
CREATE USER 'ODBC'@'localhost' IDENTIFIED BY 'ODBC';
GRANT ALL PRIVILEGES ON *.* TO 'ODBC'@'localhost' WITH GRANT OPTION;
```

The check constraint test requires MySQL 8.0.16.
One test logs in to [editor/example.php](/editor/example.php), which connects with the same credentials to the same database.

## MariaDB

A MariaDB server on `localhost:3307`, which the tests fill in the server field, with the same user as MySQL.

## PostgreSQL

PostgreSQL 12+ (the tests use generated columns) on the default host and port, where `pg_hba.conf` must allow a password login.
The tests create the database themselves so the role needs `CREATEDB`, and PostgreSQL stores the name as it is written, so it must be quoted:

```sql
CREATE ROLE "ODBC" LOGIN PASSWORD 'ODBC' CREATEDB;
```

## CockroachDB

A node on `localhost:26257`, started insecure - it accepts any password but the user still has to exist:

```sql
CREATE USER "ODBC";
ALTER USER "ODBC" CREATEDB;
```

Run them by `cockroach sql --insecure --host=localhost:26257`.

## SQLite

No server is needed.
The tests log in through [tests/sqlite.php](/tests/sqlite.php), which accepts the password `YOUR_PASSWORD_HERE`, and create `adminer_test.sqlite` in [tests/](/tests/) - the driver resolves a relative filename against the directory of the script, so PHP must be able to write there.

## MS SQL

A server listening on the default host and port with SQL Server authentication - SQL Server Express uses a dynamic port, so it must be configured for 1433, the tests leave the server field empty.
The database must exist before the run because the login can't create it - the first test only drops the tables left there by an interrupted run:

```sql
CREATE DATABASE adminer_test;
CREATE LOGIN ODBC WITH PASSWORD = 'ODBC', DEFAULT_DATABASE = adminer_test, CHECK_POLICY = OFF;
USE adminer_test;
CREATE USER ODBC FOR LOGIN ODBC;
ALTER ROLE db_owner ADD MEMBER ODBC;
```

The password policy is disabled because `ODBC` doesn't satisfy it.

## OpenSearch

An OpenSearch server on the default `localhost:9200` without the security plugin, so that it needs no user and no certificate.
The tests log in through [tests/elastic.php](/tests/elastic.php) with the password `YOUR_PASSWORD_HERE` verified by `Adminer\Password`, because Adminer refuses a server accepting any password.
They create and drop the index `interprets` themselves and leave the other indexes alone, e.g. `top_queries` of the query-insights plugin.

The same driver serves Elasticsearch, which is not covered by the tests - only the system name in the breadcrumb differs.
A server requiring a password needs a user created there instead:

```sh
curl -u elastic:PASSWORD -X POST https://localhost:9200/_security/user/ODBC \
	-H 'Content-Type: application/json' -d '{"password": "ODBC12", "roles": ["superuser"]}'
```

PHP needs `allow_url_fopen` and, with a `https://` server, must trust its certificate, e.g. by pointing `openssl.cafile` to the CA generated by the server.
This driver is tested only with the `native` project because it doesn't use any PHP extension.

## Plugins

[plugins.spec.js](/tests/plugins.spec.js) covers the bundled plugins which no driver test can reach, above all the `dumpFormat` and `dumpOutput` hooks, which are aggregated across all plugins instead of stopping at the first one.
It logs in through [tests/plugins.php](/tests/plugins.php), an entry point instantiating a fixed set of plugins - passing them to `Plugins` explicitly skips the autoload from `adminer-plugins/`, so the run doesn't depend on what is deployed there.
It uses the MySQL server and the `adminer_test` database, creating the tables by SQL, and it runs only with the `native` project because the plugins don't depend on the extension.

## Screenshots

[screenshots.spec.js](/tests/screenshots.spec.js) takes the pictures published on [adminer.org](https://www.adminer.org/) and stores them in `tests/screenshots/`, `bin/screenshots.php` of the website repository crops them afterwards.
It displays the MySQL database `adminer_demo`, which holds the same data as the demo and is filled from `mysql.sql` of the demo.adminer.org deployment; the only thing it changes there is altering `posts`, which displays a message on the table page:

```sh
mysql -e "CREATE DATABASE adminer_demo"
mysql adminer_demo < ../adminer-demo/mysql.sql
```

They log in as `adminer` with the password `adminer`, a user with privileges only on this database, so that the server overview doesn't list all the other databases of the server:

```sql
CREATE USER 'adminer'@'localhost' IDENTIFIED BY 'adminer';
GRANT ALL PRIVILEGES ON adminer_demo.* TO 'adminer'@'localhost';
```

No plugin may be deployed because it would show in the pictures.
