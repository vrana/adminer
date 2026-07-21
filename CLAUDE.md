# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**First-time setup:**
```bash
make initialize   # Initialize git submodules (jush, JsShrink, PhpShrink)
```

**Development server:**
```bash
make server       # Start PHP server at http://127.0.0.1:8000
```
Browse to `http://127.0.0.1:8000/adminer/` for the dev version.

**Build (compile single-file distribution):**
```bash
make compile                       # All drivers, all languages → adminer.php
php compile.php mysql              # MySQL driver only
php compile.php mysql en           # MySQL + English only
php compile.php editor mysql       # Adminer Editor with MySQL
composer compile                   # Equivalent to make compile
```

**Code quality:**
```bash
composer check                     # Runs phpcs + phpstan
phpcs                              # PHP code style (PSR-12 based, tab-indented)
phpstan analyse -c phpstan.neon    # Static analysis (level 6)
```

**Clean:**
```bash
make clean        # Remove adminer.php
composer clean    # Remove all compiled adminer*.php and editor*.php
```

**Tests:** Browser-based end-to-end tests in `tests/*.html` (Katalon Recorder format). No unit test runner. Standalone unit tests: `tests/compress.php` (string compression round-trip and pure-PHP inflate fallback) and `tests/host_port.php` (host:port parsing) – run directly with `php`, they print errors and exit 0 when OK.

## Architecture

Adminer is a database management tool deployable as a **single PHP file** (`adminer.php`), compiled from modular source by `compile.php`.

### Entry points
- `adminer/index.php` – dev version; routes requests via `$_GET` parameter presence (e.g., `?select=table`, `?indexes=table`, `?dump=`)
- `editor/index.php` – Adminer Editor variant (data manipulation only, no DDL)
- `adminer.php` – compiled single-file production version

### Four main classes (`Adminer` namespace)
- **`Adminer`** (`adminer/include/adminer.inc.php`) – ~80 overridable methods for all UI/behavior; this is what plugins hook into
- **`Plugins`** (`adminer/include/plugins.inc.php`) – plugin manager; `__call()` chains registered plugins until one returns non-null
- **`Driver`** (`adminer/include/driver.inc.php`) – database driver interface; static registry of available drivers
- **`Db`** (`adminer/include/db.inc.php`) – low-level DB connection abstraction; always exactly one instance per driver

### Plugin system
Plugins are PHP classes implementing any methods from `Adminer`.
The `Plugins` manager discovers them from an `adminer-plugins/` directory or `adminer-plugins.php` file alongside the deployed PHP file.
Most hooks short-circuit on first non-null return; `dumpFormat`, `dumpOutput`, `editRowPrint`, `editFunctions`, and `config` aggregate across all plugins.

Built-in plugins live in `plugins/`. Plugin drivers (Elasticsearch, MongoDB, Redis, etc.) live in `plugins/drivers/`.

### Driver system
Core SQL drivers: `adminer/drivers/{mysql,pgsql,sqlite,mssql,oracle}.inc.php`
Plugin drivers: `plugins/drivers/{elastic,mongo,redis,igdb,imap,firebird,clickhouse,simpledb}.php`

Each driver registers via `add_driver("key", "Label")` and implements a `Db` class with `attach()`, `quote()`, `select_db()`, `query()`.

### Compilation
`compile.php` inlines all `include` files, minifies CSS/JS, deflate-compresses translations, and optionally runs PhpShrink to strip PHP 7.4 type declarations (making the output PHP 5.3 compatible). Source requires PHP 7.4+.

## Code Conventions (see developing.md for full details)

**Indentation:** Tabs, not spaces – `Generic.WhiteSpace.DisallowSpaceIndent` is enforced despite PSR-12 base.

**Escaping:**
- `h($val)` – HTML output (like `htmlspecialchars`, escaping `"` and `'`)
- `q($val)` – SQL string values
- `idf_escape($val)` – SQL identifiers (column/table names)

**Translations:** Always use `lang('...')` with **single quotes** – the string extractor requires literal single-quoted strings.

**Array access:** Use bare `$_GET["key"]` (not `isset()` or `??`). Adminer silences undefined-key warnings intentionally via `adminer/include/errors.inc.php`. Never use `$_REQUEST`.

**Empty checks:** Use `$var != ""` not `!$var` – table names can be `"0"`, which is falsy.

**Control flow:** Always use `{}` blocks. Use `elseif` (not `else if`).

**Naming:** Functions and variables use `snake_case`; class methods use `camelCase` (except `Db` and driver classes which use `snake_case` to match mysqli conventions).

**Comments:** `//!` = TODO, `//~` = debug code. Doc-comments are imperative ("Get" not "Gets"), no trailing period, `@param` only when type is more specific than the declaration.

**Commit style:** `Area: Message` format (e.g., `MySQL: Fix connection timeout`). Bug fixes append `(fix #n)`. Update `CHANGELOG.md` with user-visible changes.
