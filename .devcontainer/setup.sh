#!/bin/sh -e
# Everything the container needs once, run by devcontainer.json after it is created.

# The drivers the end-to-end tests connect with. The image is the official PHP one underneath, so
# the extensions are built with its own tool; only PostgreSQL needs a library for it, MySQL is
# served by the mysqlnd already in there.
sudo apt-get update -qq
sudo apt-get install -y -qq libpq-dev
# -E: docker-php-ext-* write to $PHP_INI_DIR, which plain sudo drops from the environment.
sudo -E docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mysqli pdo_mysql

# Adminer has no dependencies; this initializes the submodules, the dev version needs jush for
# syntax highlighting, and it installs the ESLint used by `composer check`.
composer install --no-interaction

# The tools `composer check` runs. They are Adminer's, not the repository's, which is why they are
# installed for the user and not required by composer.json.
composer global require --no-interaction phpstan/phpstan squizlabs/php_codesniffer

# The tests have a composer.json of their own, see tests/e2e/README.md.
composer install -d tests/e2e --no-interaction

# The Playwright server, then the libraries the browser needs and the browser itself - the only
# one the tests use, the default installs three.
php tests/e2e/vendor/bin/playwright-install
cd tests/e2e/vendor/playwright-php/playwright/bin
# The libraries are apt packages, so root, and PATH is passed along because Node is installed for
# the user and sudo would not find it. --no-install so that this is the Playwright the tests run,
# not whichever one npx would fetch.
sudo -E env "PATH=$PATH" npx --no-install playwright install-deps chromium
# The browser itself as the user, so that it lands in the cache the tests read.
npx --no-install playwright install chromium
