<?php
/** Shared fixture for the end-to-end tests
*
* What the scenarios in tests/e2e/features/ need before the first step can run: a seeded throwaway
* database and the dev version of Adminer served from the repository. AdminerContext calls
* e2e_boot() once per driver, then e2e_login() to log a page in, e2e_url() to build a link into
* the demo database and e2e_report() to save a page a scenario failed on.
*
* Which driver is being tested arrives in ADMINER_E2E_DRIVER, set by the context from the Behat
* suite it belongs to; ADMINER_E2E_SERVER points at a database which is already running, for a run
* which should not start one of its own.
*/

require dirname(__DIR__) . '/vendor/autoload.php';

use Playwright\Browser\BrowserContextInterface;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Page\PageInterface;
use Playwright\PlaywrightFactory;
use Symfony\Component\Process\Process;
use Testcontainers\Modules\MySQLContainer;
use Testcontainers\Modules\PostgresContainer;

const E2E_DATABASE = 'adminer_e2e';
// Any password would do, an empty one would not: Adminer refuses to connect to a server which
// accepts one, so the tests would never get past the login form.
const E2E_PASSWORD = 'e2e';

/** Get the driver the current process tests, and how to start, seed and log into it
*
* The key is what Adminer calls the driver in the URL and in the login form, which is not always
* the name of the database - MySQL is "server" there for backwards compatibility.
* @return E2eDriver
*/
function e2e_driver(): array {
	$drivers = array(
		// MySQLContainer's password is its own default, the one PostgresContainer takes as an argument.
		"pgsql" => array("key" => "pgsql", "port" => 5432, "username" => "adminer", "password" => E2E_PASSWORD),
		"mysql" => array("key" => "server", "port" => 3306, "username" => "root", "password" => "root"),
	);
	$name = getenv("ADMINER_E2E_DRIVER") ?: "pgsql";
	if (!isset($drivers[$name])) {
		throw new RuntimeException("unknown driver '$name', use " . implode(" or ", array_keys($drivers)));
	}
	return array("name" => $name) + $drivers[$name];
}

/** Start the database and seed it, or reuse one which is already running
* @param E2eDriver $driver
* @return string hostname:port to connect to
*/
function e2e_database(array $driver): string {
	$running = getenv("ADMINER_E2E_SERVER");
	if ($running) {
		return $running;
	}
	// The container is named, and a name is looked up rather than a port, so that a run reuses the
	// database a killed run left behind instead of starting one of its own - and so that it can
	// never latch onto somebody else's database which happens to be on the port we would have
	// picked. Whichever run starts it stops it; a reused one is left to the run which did.
	$name = "adminer-e2e-{$driver['name']}";
	$host = getenv("TESTCONTAINERS_HOST_OVERRIDE") ?: "127.0.0.1";
	$server = e2e_running($name, $driver["port"], $host);
	if (!$server) {
		$container = ($driver["name"] == "pgsql"
			? new PostgresContainer("18-alpine", $driver["username"], $driver["password"], E2E_DATABASE)
			: (new MySQLContainer("8"))->withMySQLDatabase(E2E_DATABASE)
		);
		$started = $container->withName($name)->start();
		register_shutdown_function(function () use ($started) {
			$started->stop();
		});
		$server = $started->getHost() . ":" . $started->getMappedPort($driver["port"]);
	}
	// Always, reused or not: the seed drops what it creates, so this is what a rerun starts from.
	e2e_connect($driver, $server)->exec(file_get_contents(dirname(__DIR__) . "/seed/{$driver['name']}.sql"));
	return $server;
}

/** Get where a container of that name publishes the port, or nothing if it is not running
* @return ?string hostname:port
*/
function e2e_running(string $name, int $port, string $host): ?string {
	$published = new Process(array("docker", "port", $name, "$port/tcp"));
	$published->run();
	if (!$published->isSuccessful() || !preg_match('~:(\d+)~', $published->getOutput(), $match)) {
		return null;
	}
	return "$host:$match[1]";
}

/** Connect to the demo database without a browser, to seed it and to arrange what a scenario needs
* @param E2eDriver $driver
* @param string $server hostname:port
*/
function e2e_connect(array $driver, string $server): PDO {
	list($host, $port) = explode(":", $server);
	return new PDO(
		"{$driver['name']}:host=$host;port=$port;dbname=" . E2E_DATABASE,
		$driver["username"],
		$driver["password"]
	);
}

/** Serve the dev version of Adminer and return everything a test needs
* @return E2eFixture
*/
function e2e_boot(int $port = 18080): array {
	$root = dirname(__DIR__, 3);
	$artifacts = dirname(__DIR__) . "/artifacts";
	@mkdir($artifacts, 0777, true);
	$driver = e2e_driver();
	$database = e2e_database($driver);

	// The next free one, not the one asked for: a run which was killed leaves its server behind,
	// and every scenario would then wait for a port it is never going to get.
	$port = e2e_free_port($port);
	$server = new Process(array(PHP_BINARY, "-S", "127.0.0.1:$port", "-t", $root));
	// The server logs every request it serves to stderr and nothing here ever reads that pipe, so it
	// fills - sixty-four kilobytes in, the server blocks writing to it and stops answering. From the
	// browser that looks like a page which commits and then never finishes loading, a scenario or
	// two into the run, and the log is of no use to a test anyway.
	$server->disableOutput();
	$server->start();

	$base = "http://127.0.0.1:$port/adminer/";
	$context = stream_context_create(array("http" => array("timeout" => 1, "ignore_errors" => true)));
	$deadline = time() + 15;
	// The login form, not merely an answer: anything else listening on the port would answer too,
	// and every scenario would then fail at logging in to a page which is not Adminer.
	while (strpos((string) @file_get_contents($base, false, $context), "auth[driver]") === false) {
		if (time() > $deadline) {
			$server->stop();
			throw new RuntimeException("Adminer is not answering on port $port, is something else using it?");
		}
		usleep(200000);
	}
	return compact('root', 'artifacts', 'server', 'base', 'database', 'driver');
}

/** Get a port nothing is listening on yet, starting from the one asked for */
function e2e_free_port(int $port): int {
	for ($i = 0; $i < 20; $i++) {
		$socket = @stream_socket_server("tcp://127.0.0.1:" . ($port + $i), $errno, $error);
		if ($socket) {
			fclose($socket);
			return $port + $i;
		}
	}
	throw new RuntimeException("no free port between $port and " . ($port + 19));
}

/** Build a link into the demo database
* @param E2eFixture $fix
* @param string[] $params e.g. array("select" => "users")
*/
function e2e_url(array $fix, array $params): string {
	$connection = array(
		$fix['driver']["key"] => $fix['database'],
		"username" => $fix['driver']["username"],
		"db" => E2E_DATABASE,
	);
	if ($fix['driver']["name"] == "pgsql") {
		$connection["ns"] = "public"; // PostgreSQL addresses tables in a schema
	}
	// array_merge, so that a test can point somewhere else than the defaults above.
	return $fix['base'] . "?" . http_build_query(array_merge($connection, $params));
}

/** Log a page into the demo database
*
* Verified and retried because the wait below is a guess: Adminer rebuilds the driver's fields
* on change and a value filled in the middle of that is posted empty, which comes back as the
* login page again. Whatever the test does next then fails on an empty page, which reads as
* anything but a login problem.
* @param E2eFixture $fix
*/
function e2e_login(PageInterface $page, array $fix): void {
	for ($attempt = 1; $attempt <= 3; $attempt++) {
		$page->goto($fix['base']);
		$page->locator('select[name="auth[driver]"]')->selectOption($fix['driver']["key"]);
		usleep(400000 * $attempt); // let the rebuild settle, and wait longer each time round
		$page->locator('input[name="auth[server]"]')->fill($fix['database']);
		$page->locator('input[name="auth[username]"]')->fill($fix['driver']["username"]);
		$page->locator('input[name="auth[password]"]')->fill($fix['driver']["password"]);
		$page->locator('input[name="auth[db]"]')->fill(E2E_DATABASE);
		// Submit the form instead of clicking the button: the rebuild leaves the button
		// intermittently unclickable, and this does not depend on its markup or its label.
		$page->evaluate(/** @lang JavaScript */ "() => document.querySelector('[name=\"auth[driver]\"]').form.requestSubmit()");
		$page->waitForLoadState("networkidle");
		// A rejected login comes back as the login page and its title says so. title() is a driver
		// call and survives a page which is still committing, which an evaluate() does not.
		if (strpos($page->title(), "Login") !== 0) {
			// Wait for the menu of the page the login lands on before handing it over. Adminer
			// answers a good login with a redirect, and a quiet network is reached between its two
			// requests, so returning on that alone gives the next step a page which navigates out
			// from under it - and a step which reads through evaluate() dies there as "Execution
			// context was destroyed". A locator waits for the element across the navigation.
			$page->locator("#menu")->waitFor();
			return;
		}
	}
	throw new RuntimeException("could not log in after 3 attempts");
}

/** Get a browser context of this scenario's own, hidden unless the run asked to watch it
*
* One browser for the whole run, one context per scenario: a context is what holds the cookies, so
* a scenario still logs in on a session nobody else has touched, but the browser and the Node
* process driving it are started once instead of per scenario.
*
* `composer test-visual` sets the variable below; it shows the browser and slows it down enough to
* follow, which is how a scenario is written and how a failing one is understood.
*/
function e2e_browser(): BrowserContextInterface {
	// The client as well as the browser: it closes the connection to the Node process when it is
	// collected, and the browser is then talking to nobody.
	static $client = null, $browser = null;
	if (!$browser) {
		$headed = (bool) getenv("ADMINER_E2E_HEADED");
		// Longer than the 30 seconds Playwright gives an action, and deliberately not equal to it:
		// both sides default to 30, so a step which ran out of time raced the answer against our own
		// giving up on it. The answer arrived with nobody waiting for it, the next request read the
		// one before, and a single slow step failed the whole rest of the run instead of only itself.
		$client = PlaywrightFactory::create(new PlaywrightConfig(timeoutMs: 60000));
		$chromium = $client->chromium();
		$chromium->withHeadless(!$headed)->withSlowMo($headed ? 300 : 0);
		$browser = $chromium->launch();
		register_shutdown_function(function () use (&$client) {
			$client->close();
		});
	}
	$context = $browser->newContext();
	// Adminer asks adminer.org whether a newer version is out, once per browser, and remembers the
	// answer in this cookie. A context has no cookies, so every scenario asked again - and
	// waitForLoadState("networkidle") then waits on a request to somebody else's server before any
	// step can run. Setting the cookie is what stops the check being emitted at all; the value is
	// never read, only its presence.
	$context->addCookies(array(array(
		"name" => "adminer_version",
		"value" => "0",
		"domain" => "127.0.0.1",
		"path" => "/",
	)));
	return $context;
}

/** Save the failed page as a picture and as HTML
*
* A failure in CI is otherwise a line of text about a page nobody can open anymore; the workflow
* uploads this directory when the tests fail. Nothing here may throw - it runs while a scenario is
* already failing and its own exception would replace the message saying what went wrong.
* @param E2eFixture $fix
*/
function e2e_report(PageInterface $page, array $fix, string $name): void {
	try {
		$path = "{$fix['artifacts']}/{$fix['driver']['name']}-$name";
		$page->screenshot("$path.png");
		file_put_contents("$path.html", $page->content());
	} catch (Throwable $e) {
		fwrite(STDERR, "could not save the failed page: " . $e->getMessage() . "\n");
	}
}
