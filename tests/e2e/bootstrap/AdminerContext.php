<?php
namespace Adminer\Tests;

require_once dirname(__DIR__) . '/harness/fixture.php';

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Playwright\Page\PageInterface;
use RuntimeException;

/** The steps every feature is written in
*
* One instance per scenario, so $page is a browser nobody else has touched. The database and the
* web server are not: they are booted once per driver and kept in $fixtures, which is by far the
* slowest part of a run and has nothing a scenario can spoil - Adminer is stateless and the only
* table written to is emptied by the scenario writing it.
*
* Which driver an instance is for comes from the Behat suite it belongs to, see tests/e2e/behat.yml.
*/
class AdminerContext implements Context {
	/** @var array<string, E2eFixture> what e2e_boot() returned, per driver */
	private static array $fixtures = array();

	/** @var E2eFixture */
	private array $fix;

	private PageInterface $page;

	private string $driver;

	/** @var int the web server of this driver, its own so that the suites can share a process */
	private int $port;

	public function __construct(string $driver, int $port) {
		$this->driver = $driver;
		$this->port = $port;
	}

	#[BeforeScenario]
	public function open(): void {
		putenv("ADMINER_E2E_DRIVER=$this->driver"); // read by e2e_driver()
		if (!isset(self::$fixtures[$this->driver])) {
			self::$fixtures[$this->driver] = e2e_boot($this->port);
		}
		$this->fix = self::$fixtures[$this->driver];
		$this->page = e2e_browser()->newPage();
		$this->page->setViewportSize(1280, 900);
	}

	#[AfterScenario]
	public function close(): void {
		$this->page->context()->close();
	}

	/** Save the page a step failed on, so that a failure in CI is more than a line of text
	*
	* isset() because booting the fixture can fail before there is a page, and a second error
	* thrown from here would be the only one reported.
	*/
	#[AfterStep]
	public function report(AfterStepScope $scope): void {
		if (!$scope->getTestResult()->isPassed() && isset($this->page)) {
			$name = basename($scope->getFeature()->getFile(), ".feature") . "-" . $scope->getStep()->getLine();
			e2e_report($this->page, $this->fix, $name);
		}
	}

	#[Given('I am logged in to the demo database')]
	public function logIn(): void {
		e2e_login($this->page, $this->fix);
	}

	/** Arranged without the browser: the scenarios share a database, and one which writes has to
	* start from a known state whatever ran before it
	*/
	#[Given('the :table table is empty')]
	public function tableIsEmpty(string $table): void {
		e2e_connect($this->fix['driver'], $this->fix['database'])->exec("DELETE FROM $table");
	}

	#[When('I log out')]
	public function logOut(): void {
		$this->page->click("#logout");
		$this->page->waitForLoadState("networkidle");
	}

	#[When('I open the :table table')]
	public function openTable(string $table): void {
		$this->go(array("select" => $table));
	}

	#[When('I open the :table table in the :schema schema')]
	public function openTableInSchema(string $table, string $schema): void {
		$this->go(array("ns" => $schema, "select" => $table));
	}

	#[When('I open the insert form for the :table table')]
	public function openInsertForm(string $table): void {
		$this->go(array("edit" => $table));
	}

	#[When('I reload the page')]
	public function reload(): void {
		$this->page->reload();
		$this->page->waitForLoadState("networkidle");
	}

	#[When('I sort by the :column column')]
	public function sortBy(string $column): void {
		// The heading of the column, not the search or descending link in the span next to it.
		$this->page->click("[id=\"th[$column]\"] > a");
		$this->page->waitForLoadState("networkidle");
	}

	#[When('I search for :value in :column')]
	public function search(string $value, string $column): void {
		$this->page->click('legend a[href="#fieldset-search"]'); // the form is behind its legend
		$this->page->locator('select[name="where[0][col]"]')->selectOption($column);
		$this->page->locator('select[name="where[0][op]"]')->selectOption("=");
		$this->page->locator('input[name="where[0][val]"]')->fill($value);
		$this->page->click('input[type=submit][value="Select"]');
		$this->page->waitForLoadState("networkidle");
	}

	#[When('I switch to the :schema schema')]
	public function switchSchema(string $schema): void {
		$this->page->locator("[name=ns]")->selectOption($schema);
		// The selector reloads the page on its own, a beat after selectOption() returns.
		$this->page->waitForURL("**ns=$schema**");
		$this->page->waitForLoadState("networkidle");
	}

	/** first(), because the step says first: the selector matches the edit link of every row, and a
	* locator which resolves to more than one is a strict mode violation, not a click on the one at
	* the top. It only ever worked on a table holding a single row.
	*/
	#[When('I edit the first row')]
	public function editFirstRow(): void {
		$this->page->locator("#table tbody tr a.edit")->first()->click();
		$this->page->waitForLoadState("networkidle");
	}

	/** The columns are a textarea in one driver and an input in another, so the selectors name no tag */
	#[When('I fill :field with :value')]
	public function fill(string $field, string $value): void {
		$this->page->locator("[name=\"fields[$field]\"]")->fill($value);
	}

	#[When('I pick :value for :field')]
	public function pick(string $value, string $field): void {
		$this->page->click("[name=\"fields[$field][]\"][value=\"val-$value\"]");
	}

	/** Save is the only submit button without a name, the others save and continue */
	#[When('I save the form')]
	public function save(): void {
		$this->page->click('#form input[type=submit]:not([name])');
		$this->page->waitForLoadState("networkidle");
	}

	#[When('I delete the row')]
	public function delete(): void {
		// The button asks for a confirmation on click, which submitting the form directly skips -
		// the dialog is the browser's, the deletion is Adminer's and that is what is being tested.
		// Deferred, because the navigation it starts would destroy the context this runs in.
		$this->page->evaluate(/** @lang JavaScript */ "() => {
			const button = document.querySelector('#form [name=delete]');
			setTimeout(() => button.form.requestSubmit(button), 0);
		}");
		// Adminer sends the browser to the data list once the row is gone. Waiting for that rather
		// than for a quiet network is what makes this reliable: a deferred submit has not
		// necessarily started navigating by the time the network first falls silent.
		$this->page->waitForURL("**select=**");
		$this->page->waitForLoadState("networkidle");
	}

	#[Then('/^the table lists (\d+) rows?$/')]
	public function tableLists(int $count): void {
		$rows = $this->rows();
		if (count($rows) != $count) {
			throw new RuntimeException("the table lists " . count($rows) . " rows: " . implode(" | ", $rows));
		}
	}

	#[Then('row :number contains :text')]
	public function rowContains(int $number, string $text): void {
		$rows = $this->rows();
		$row = ($rows[$number - 1] ?? "");
		if (strpos($row, $text) === false) {
			throw new RuntimeException("row $number is '$row'");
		}
	}

	#[Then('the URL contains :text')]
	public function urlContains(string $text): void {
		if (strpos($this->page->url(), $text) === false) {
			throw new RuntimeException("the URL is " . $this->page->url());
		}
	}

	/** Read through a locator, not an evaluate: a locator waits for the element and survives a page
	* which is still navigating, where an evaluate dies as "Execution context was destroyed"
	*/
	#[Then('the breadcrumb shows the demo database')]
	public function breadcrumbShowsDatabase(): void {
		$breadcrumb = (string) $this->page->locator("#breadcrumb")->textContent();
		if (strpos($breadcrumb, E2E_DATABASE) === false) {
			throw new RuntimeException("the breadcrumb is '$breadcrumb', the title " . $this->page->title());
		}
	}

	#[Then('the table list contains :table')]
	public function tableListContains(string $table): void {
		if (!in_array($table, $this->tables(), true)) {
			throw new RuntimeException("the table list is " . implode(", ", $this->tables()));
		}
	}

	#[Then('the table list does not contain :table')]
	public function tableListDoesNotContain(string $table): void {
		if (in_array($table, $this->tables(), true)) {
			throw new RuntimeException("the table list is " . implode(", ", $this->tables()));
		}
	}

	#[Then('the login form is shown')]
	public function loginFormIsShown(): void {
		if (!$this->page->evaluate(/** @lang JavaScript */ "() => !!document.querySelector('[name=\"auth[driver]\"]')")) {
			throw new RuntimeException("the page is " . $this->page->title());
		}
	}

	#[Then('the schema selector offers :schema')]
	public function schemaSelectorOffers(string $schema): void {
		$schemas = $this->page->evaluate(/** @lang JavaScript */ "() => Array.from(document.querySelectorAll('[name=ns] option')).map(o => o.value)");
		if (!in_array($schema, (array) $schemas, true)) {
			throw new RuntimeException("the schemas offered are " . implode(", ", (array) $schemas));
		}
	}

	#[Then('the :field field offers :values')]
	public function fieldOffers(string $field, string $values): void {
		$offered = implode(", ", array_map(
			function ($value) {
				return preg_replace('~^val-~', '', $value);
			},
			(array) $this->page->evaluate("() => Array.from(document.querySelectorAll('[name=\"fields[$field][]\"]')).map(i => i.value)")
		));
		if ($offered !== $values) {
			throw new RuntimeException("the $field field offers $offered");
		}
	}

	#[Then('the :field field has :value picked')]
	public function fieldHasPicked(string $field, string $value): void {
		$picked = $this->page->evaluate("() => (document.querySelector('[name=\"fields[$field][]\"]:checked') || {}).value || 'nothing'");
		if ($picked !== "val-$value") {
			throw new RuntimeException("the $field field has $picked picked");
		}
	}

	#[Then('the field :field contains :value')]
	public function fieldContains(string $field, string $value): void {
		$actual = $this->page->locator("[name=\"fields[$field]\"]")->inputValue();
		if ($actual !== $value) {
			throw new RuntimeException("the $field field contains '$actual'");
		}
	}

	/** Go to a page of the demo database
	* @param string[] $params
	*/
	private function go(array $params): void {
		$this->page->goto(e2e_url($this->fix, $params));
		$this->page->waitForLoadState("networkidle");
	}

	/** Get the rows as they read on the screen, whitespace collapsed the way a person sees it
	* @return string[]
	*/
	private function rows(): array {
		return (array) $this->page->evaluate(/** @lang JavaScript */ "() => Array.from(document.querySelectorAll('#table tbody tr')).map(tr => tr.textContent.replace(/\\s+/g, ' ').trim())");
	}

	/** @return string[] */
	private function tables(): array {
		return (array) $this->page->evaluate(/** @lang JavaScript */ "() => Array.from(document.querySelectorAll('#tables a')).map(a => a.textContent.trim())");
	}
}
