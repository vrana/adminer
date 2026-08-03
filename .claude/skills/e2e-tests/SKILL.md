---
name: e2e-tests
description: Write or change Adminer's browser end-to-end tests in tests/e2e/ - Gherkin features run by Behat. Use when adding a scenario or a step, changing the seed data, adding a database driver to the suite, or debugging a failing e2e run.
---

# Writing Adminer's end-to-end tests

Gherkin scenarios run by Behat, driving Playwright against the dev version and a Testcontainers
database. [The README](../../../tests/e2e/README.md) has the layout and the commands - read it
first. This is what writing one has to get right.

## Running anything at all

If `php` is not on the PATH, everything goes through the dev container, which has PHP with the
database extensions, Composer, Node and the browser already in it:

```bash
npx @devcontainers/cli up --workspace-folder .                    # once, a few minutes
npx @devcontainers/cli exec --workspace-folder . composer e2e     # any command, inside
```

Attaching to the container by hand, note the user: `docker exec -u vscode`, never as root. The
browser is installed under `/home/vscode`, and root's run skips every scenario with "Executable
doesn't exist", which reads like a broken suite and is a wrong `-u`.

A `vendor/` left at the repository root from before the tests got their own composer.json shadows
their Behat - it is gitignored, so no checkout cleans it - and `composer e2e` then dies with
"`AdminerContext` context class not found" before a scenario runs. Delete it; a fresh root install
brings no packages, and behat then falls back to the autoloader in `tests/e2e/vendor` as intended.

Do not improvise a PHP instead - no source builds, no ad-hoc `docker run php:8.4-cli`. A bare PHP
image looks like it would work and cannot: it has no browser, and the databases Testcontainers
starts are on the host's daemon, which a container reaches only through the socket and
`TESTCONTAINERS_HOST_OVERRIDE`. Both are wired up in `.devcontainer/`, and nowhere else.

## Reach for an existing step first

`composer e2e -- -di` lists every step. A feature written in steps that
already exist is the point of the exercise; a new step is added to `AdminerContext` only when the
scenario genuinely does something new.

Name a step for what the **user** is doing, not for what the browser is doing:

- `When I sort by the "name" column` — not `When I click the second heading link`
- `Then the table lists 6 rows` — not `Then #table tbody tr has length 6`

A step that names a CSS selector has leaked the implementation into the feature file, where the
next person cannot read it.

## Where a scenario goes

The directory decides which drivers run a feature, so putting it in the right one is the first
decision, not an afterthought:

- `features/shared/` runs against **every** driver, so it may only use what all of them do.
- `features/<driver>/` runs against that one only - use it when the behaviour needs a type, a
  syntax or a page a single driver has, rather than putting an `if` on the driver in a step.

A feature that fits some drivers but not all is the one case for a tag, filtered per suite in
behat.yml. There is none yet; do not add the second mechanism before there is.

## Scenarios must not depend on each other

They share one database per driver and run in file order, which is not an order to rely on:

- A scenario that writes starts from a known state: `Given the "notes" table is empty`.
- It writes only to tables no other feature reads (`notes`, or a row it inserts itself).
- Never leave a seeded row changed - a scenario that edits `articles` inserts its own row instead.

## Seed data

`tests/e2e/seed/<driver>.sql`, one file per driver:

- **English**, and the same tables and rows in every file - the shared features compare against
  them. Only the types differ, and only where they have to.
- **Keep the accents** (`Amélie Fontaine`, `Eero Väisälä`). They are what a broken encoding loses,
  and asserting on them is free coverage of the whole path.
- Tables only one driver has go **after** the shared ones, in that driver's file alone, with a
  comment naming the feature that reads them.
- Order the rows so that sorting visibly reorders them - seeded by id, not alphabetically.

## Writing a step

In `tests/e2e/bootstrap/AdminerContext.php`, as a PHP attribute:

```php
#[When('I sort by the :column column')]
public function sortBy(string $column): void {
	$this->page->click("[id=\"th[$column]\"] > a");
	$this->page->waitForLoadState("networkidle");
}
```

- Throw `RuntimeException` to fail, and say what was found: `"row 1 is '$row'"`, not `"wrong row"`.
  Behat reports the step, the file and the line on its own.
- Annotate JavaScript with `/** @lang JavaScript */` directly before the string, including inside a
  call, so that an IDE highlights it.
- Arrange through `e2e_connect()` when the browser is not the point - emptying a table is setup,
  not behaviour.

## Selectors

- No tag name where the drivers differ: a `text` column is a textarea in one driver and an input
  in another, so `[name="fields[title]"]`, never `input[name="fields[title]"]`.
- Adminer's ids contain brackets: `[id="th[name]"] > a` is the sort link of a column, and the
  `> a` matters - the search and descending links are in a span next to it.
- Prefer what Adminer names (`#logout`, `#breadcrumb`, `#tables`, `#table`, `#form`) over positions.
- A selector which can match a row must say which one: `locator(...)->first()`, not `click(...)`.
  Matching two elements is a strict mode violation, and the client checks visibility before
  clicking, swallows the violation as "not visible yet" and retries - so it surfaces thirty seconds
  later as `Element not actionable`, about an element which is on screen and perfectly clickable.
  `#table tbody tr a.edit` is every row's edit link; it passed for months only because the tables it
  ran against held one row.

## Navigation

- After a click that navigates: `waitForLoadState("networkidle")`.
- After something JavaScript navigates on its own (a select that submits its form):
  `waitForURL("**ns=analytics**")` first - the URL changes a beat after the call returns, and
  waiting only for a quiet network reads the old page. This has already caused one failure that
  appeared on MySQL and not on PostgreSQL, purely because of timing.
- Never `evaluate()` something that navigates: the call dies with "Execution context was
  destroyed". Defer it with `setTimeout(..., 0)` and then wait for the URL.

## Style

`phpcs` runs over `tests/` with the repository's ruleset: tabs, `array()` and not `[]`, braces
always, lines under 200 characters. Doc-comments are imperative and have no trailing period.
`conf/phpstan-e2e.neon` checks the same directory apart from Adminer, because the tests are a PHP
8.2 codebase which never ships - they have their own `composer.json`, installed by
`composer install -d tests/e2e`.

## Checking the work

```bash
composer e2e -- tests/e2e/features/shared/<name>.feature   # the feature that was touched
composer e2e -- --stop-on-failure                   # stop at the first, for a fast loop
composer e2e -- --dry-run                           # every step resolves, no browser, no database
composer e2e -- -di                                 # every step that can be written
composer e2e                                        # both drivers, always before pushing
```

A failing step writes `tests/e2e/artifacts/<driver>-<feature>-<line>.{png,html}` - look at those
before guessing.

The whole suite is about a minute and a half. Anything near fifteen means it is not failing, it is
hanging - see below.

## When the run hangs instead of failing

Everything in this section was paid for once already; none of it is hypothetical.

**Reach for `--stop-on-failure` first.** A hanging suite spends thirty seconds per timeout and
cascades, so the same bug costs fifteen minutes to see once. Stopping at the first one turned that
into a seventy-second reproduction, and every experiment after it was cheap. Do this before
forming a theory.

**A timeout naming a JSON-RPC request number is not a test failure.** `JSON-RPC request 182 timed
out` means the PHP client stopped waiting, not that the browser did anything. It names no step and
no page, and `e2e_report()` cannot save a screenshot through a transport in that state, so the
artifacts are of the run before. The transport is deliberately given longer than an action
(`PlaywrightConfig(timeoutMs: 60000)` against Playwright's 30 000) so the answer is always read and
the real message - `page.goto: Timeout 30000ms exceeded` - reaches the report. Never make the two
equal again.

**Suspect the web server's stderr before suspecting the browser.** `php -S` logs every request it
serves, `Symfony\Process` holds that pipe and drains it only when something asks for the output,
and nothing does. Sixty-four kilobytes in - four or five scenarios - the pipe fills, the server
blocks writing to it and serves nothing more. `e2e_boot()` calls `disableOutput()` for exactly
this. It presents as a page that commits and never finishes loading, in a different scenario each
run depending on how many requests came before, which looks like flakiness and is arithmetic.

**A screenshot that also times out means the page is wedged, not slow.** When `page.screenshot`
times out next to the failing step, no navigation option will help - `domcontentloaded` hangs just
as `load` does. Look for what is holding the page, not for a better thing to wait on.

**Curl the dev server while it hangs.** One `curl` against the port the run is using separates a
wedged server from a wedged browser in seconds, and it is worth doing before any theory. It also
lies in one specific way: an already-accepted request is served from a buffer the server still has
room for, so a sub-millisecond answer does not fully clear it.

**Bisect by feature, not by reasoning.** `composer test -- --suite=pgsql features/a.feature
features/b.feature` runs any combination. Which pairs hang and which do not is worth more than any
amount of reading the harness - here it showed the count of requests mattered and their content
did not.

**A killed run leaks its `php -S`.** It dies with the PHP process which started it, and `pkill` and
Ctrl-C skip that, so debugging leaves servers on 18080 upward. `e2e_free_port()` walks past them and
the suite still runs, but check for strays before blaming a port.
