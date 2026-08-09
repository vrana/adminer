# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**First-time setup:**
```bash
git submodule update --init --recursive   # Initialize submodules (jush, JsShrink, PhpShrink)
composer install                          # Does the same through the `submodules` script, plus PHPCS, PHPStan and the npm packages needed by ESLint
```

**Development server:**
```bash
php -S 127.0.0.1:8000
```
Browse to `http://127.0.0.1:8000/adminer/` for the dev version.

**Build (compile single-file distribution):**
```bash
composer compile                   # All drivers, all languages → adminer.php
php compile.php mysql              # MySQL driver only
php compile.php mysql en           # MySQL + English only
php compile.php editor mysql       # Adminer Editor with MySQL
```

**Code quality:**
```bash
composer check                                     # Runs phpcs + phpstan + eslint
vendor/bin/phpcs --standard=conf/phpcs.xml         # PHP code style (PSR-12 based, tab-indented)
vendor/bin/phpstan analyse -c conf/phpstan.neon    # Static analysis (level 6)
```

**Clean:**
```bash
composer clean    # Remove all compiled adminer*.php and editor*.php
```

**Tests:** Browser-based end-to-end tests in `tests/*.spec.js`, one file per driver, run headless by `composer e2e` (Playwright, needs the dev server on `http://127.0.0.1:8000` and the database servers set up as described in `tests/README.md`; the `native` and `pdo` projects test both PHP extensions). Standalone unit tests: `tests/unit/compress.php` (string compression round-trip and pure-PHP inflate fallback), `tests/unit/host_port.php` (host:port parsing) and `tests/unit/url.php` (URL escaping) – they print errors and exit 0 when OK, run them all by `composer test` or individually by `php`.

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

## Code Conventions (see docs/developing.md for full details)

**Indentation:** Tabs, not spaces – `Generic.WhiteSpace.DisallowSpaceIndent` is enforced despite PSR-12 base.

**Escaping:**
- `h($val)` – HTML output (like `htmlspecialchars`, escaping `"` and `'`)
- `q($val)` – SQL string values
- `idf_escape($val)` – SQL identifiers (column/table names)

**Translations:** Always use `lang('...')` with **single quotes** – the string extractor requires literal single-quoted strings.
Plugins must ship their own `$translations` array and call `$this->lang('...')`, even for a string that already exists in Adminer's translations.
Plugins are not compiled but compilation converts core `lang()` identifiers to numbers, so `Adminer\lang('...')` in a plugin silently returns untranslated English.

**Array access:** Use bare `$_GET["key"]` (not `isset()` or `??`). Adminer silences undefined-key warnings intentionally via `adminer/include/errors.inc.php`. Never use `$_REQUEST`.

**Empty checks:** Use `$var != ""` not `!$var` – table names can be `"0"`, which is falsy.

**Control flow:** Always use `{}` blocks. Use `elseif` (not `else if`).

**Naming:** Functions and variables use `snake_case`; class methods use `camelCase` (except `Db` and driver classes which use `snake_case` to match mysqli conventions).

**JavaScript:** ES6 (ES2015) only – no `?.`, `??`, `??=`, `async`/`await` or ES2017+ built-ins like `Object.entries()`, in `adminer/static/*.js`, `editor/static/*.js`, plugins and inline `script()`. Newer syntax is a parse error, so one modern token disables all of Adminer's JavaScript. `conf/eslint.config.mjs` pins `ecmaVersion` (it catches syntax, not built-ins). Browser APIs stay at the same generation (~Safari 10, Chrome 54, Firefox 50); feature-detect anything newer instead of using it outright – `fetch` and `navigator.clipboard` already are.

**Comments:** `//!` = TODO, `//~` = debug code. Doc-comments are imperative ("Get" not "Gets"), no trailing period, `@param` only when type is more specific than the declaration.

**Commit style:** `Area: Message` format (e.g., `MySQL: Fix connection timeout`). Bug fixes append `(fix #n)`. Update `CHANGELOG.md` with user-visible changes.

**Changelog subsections:** A release section is the main list, optionally followed by `### Plugins` and `### Internal` – in this order, no blank line before a heading. The main list ends with the newly translated languages. `### Plugins` holds everything about plugins: the plugin API (a documented interface, so it belongs in the changelog), changes of bundled plugins prefixed `Plugin <name>: `, and `New plugin: ` – entries there are not prefixed `Plugins: `. `### Internal` is for changes a user cannot observe: build and compilation, dev tooling, tests, code organization, refactoring; compilation fixes with a visible symptom stay in the main list. Accessibility attributes, new translations and skin-affecting HTML/CSS restructuring are deliberately not recorded at all. (Releases before 5.0.0 also have a historic `### Customization`, the former name of the plugin API.)

**CSS skins:** Default styles are `adminer/static/{default,dark}.css`, but users apply alternative skins by dropping an `adminer.css` (or `adminer-dark.css`) next to the deployed script. These skins target Adminer's HTML structure, class names, and IDs. Bundled examples live in `designs/`, but many more exist in the wild (gallery: https://www.adminer.org/#extras) and can't be updated in lockstep. Avoid breaking them: don't rename or drop existing selectors, IDs, or class names, or restructure HTML, without good reason – prefer additive changes.
