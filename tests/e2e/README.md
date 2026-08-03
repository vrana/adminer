# End-to-end tests

Scenarios written in [Gherkin](https://docs.behat.org/en/latest/user_guide/gherkin.html) and run by
[Behat](https://docs.behat.org/), which drives a real browser through the dev version of Adminer,
served by the PHP web server, against a database started in Docker by
[Testcontainers](https://testcontainers.com/).

They have a `composer.json` of their own, so that Adminer itself keeps having no dependencies at
all and the tests can ask for the PHP 8.2 their own dependencies need - Adminer's sources require
7.4 and compile down to 5.3.

## Installing

Docker has to be running - the databases are containers - and there are two ways to get everything
else.

**In the dev container**, which needs nothing installed but Docker and brings PHP with the database
extensions, Composer, Node and the browser:

```bash
npx @devcontainers/cli up --workspace-folder .   # or open the repository in an IDE which reads it
```

`.devcontainer/setup.sh` then installs the rest. Commands are run inside it:

```bash
npx @devcontainers/cli exec --workspace-folder . composer e2e
```

**Or with your own PHP 8.2+**, Composer and Node:

```bash
composer install                # Adminer: no dependencies, but it initializes the submodules
composer install -d tests/e2e   # Behat, Playwright and Testcontainers
composer browser -d tests/e2e   # the Chromium the scenarios are run in
```

## Running

From the root of the repository:

```bash
composer e2e                                   # every scenario against every driver
composer e2e-visual                            # the same in a browser you can watch
composer e2e -- --suite=pgsql                  # one driver only
composer e2e -- tests/e2e/features/shared/login.feature  # one feature
composer e2e -- --name "Sorting reorders"      # one scenario, by name
composer e2e -- --stop-on-failure              # stop at the first failure, for a fast loop
composer e2e -- -di                            # every step that can be written
```

In the dev container the browser draws on a desktop inside the container - it has no screen of its
own, Playwright will not start a headed browser without one, and nothing running in there can open
a window on the machine hosting it. Watching it is therefore a browser on <http://localhost:6080>,
password `vscode`, and starting both is one command from the host rather than from inside:

```bash
open http://localhost:6080 &&    # macOS; xdg-open on Linux
    npx @devcontainers/cli exec --workspace-folder . composer e2e-visual
```

Some editors open that page themselves when they forward the port; the command does not care
either way, and neither does `composer e2e-visual` run any other way.

Or `composer test -d tests/e2e`, which is the same Behat with the same configuration.

A step which fails leaves a screenshot and the HTML of the page in `artifacts/`, which the
[workflow](/.github/workflows/e2e.yml) uploads.

## Layout

    composer.json      the dependencies of the tests, Adminer has none
    features/shared/   what every driver does, run against each of them
    features/pgsql/    what only PostgreSQL has
    features/mysql/    what only MySQL has
    bootstrap/         AdminerContext.php - the steps they are written in
    harness/           fixture.php - the database, the web server, the browser, logging in
    seed/              the rows each driver starts with
    artifacts/         what a failed step left behind, ignored by Git

Where a feature sits is what decides which drivers run it, and it is the first thing to get right:
a feature in `pgsql/` cannot be run against MySQL by forgetting anything, and a driver added later
picks up everything in `shared/` without a single file being edited.

## Adding a scenario

Write it in the feature file of its subject, in steps which already exist - `behat -di` lists them
all. A step nobody has needed yet is added to `AdminerContext`, named for what the user is doing
rather than for what the browser is doing: `When I sort by the "name" column`, not `When I click
the second heading`.

A feature in `shared/` has to stick to what all drivers do - it is run against every one of them.
Something a single driver has goes in the directory named after it:
[pgsql/schema.feature](features/pgsql/schema.feature) switches PostgreSQL schemas,
[mysql/enum.feature](features/mysql/enum.feature) edits a MySQL enum. Anything else in a step
would be an `if` on the driver, which is the thing these directories exist to avoid.

A feature which fits some drivers but not all - two of three, once there are three - is the one
case for a tag, filtered per suite in [behat.yml](behat.yml). There is none yet, and one mechanism
is better than two until there is.

The scenarios share one database per driver and run in the order they are written, which is not an
order to depend on: a scenario which writes starts with `Given the "notes" table is empty` and
leaves only tables no other scenario reads.

## Seed data

The rows the scenarios expect are in `seed/<driver>.sql`.
The tables the shared features read are the same in each of them, in that driver's own types; a
table only one driver has goes after them, in that driver's file alone.
Write the data in English, but keep the accents - they are what a broken encoding loses.

## Adding a driver

Add `seed/<driver>.sql` with the same tables as the others, a line to `e2e_driver()` and the
container to `e2e_database()` in [harness/fixture.php](harness/fixture.php), a suite to
[behat.yml](behat.yml) with a port of its own, and `features/<driver>/` when it has something the
others do not. Everything in `features/shared/` then runs against it as it is.

## Why it is set up this way

**A `composer.json` of its own.** Adminer has no dependencies, and a database manager which is one
PHP file is entitled to keep it that way; the tests need Behat, Playwright and Testcontainers, which
is another thirty-odd packages. Keeping them here means `composer install` at the root still
installs nothing, and the published package carries none of it. It costs a second install step.

**PHP 8.2 as the floor.** The dependencies require it - Behat asks for `>=8.2 <8.6`, Playwright for
`^8.2` - and this is a codebase Adminer's own rules do not apply to: nothing here is compiled, and
nothing here ships, so the sources may use what the language offers rather than what a PHP 5.3
target allows.

**8.2 and not the newest.** The newest stable is PHP 8.5 and the dependencies allow it, so the
tests are checked against the whole range (`conf/phpstan-e2e.neon` analyzes as both 8.2 and 8.5)
and run on whatever the developer has. Requiring 8.5 would rule out everyone whose distribution
ships something older, and for this code it would buy near nothing: it is glue - browser calls,
string comparisons, exceptions - and the newer features have nothing to hold on to.

**An IDE will still complain.** PhpStorm and IntelliJ have one PHP language level per project and
take it from the root `composer.json`, which says 7.4 - so `#[When(...)]` and everything else newer
than that is marked in these files even though it runs. A `composer.json` here does not change it;
what does is either raising the language level, or restricting the "not supported in current
language level" inspection to a scope which leaves `tests/e2e` out. The second keeps Adminer's own
sources guarded, which is the reason the level is 7.4 in the first place.

