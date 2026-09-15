<?php

/** Visualize PostgreSQL EXPLAIN output with PEV2.
* @link https://github.com/sivaramasubramanian/pev2
* @link https://www.adminer.org/plugins/#use
* @license https://www.postgresql.org/about/licence/ PostgreSQL License
*/
class AdminerExplainPev2 extends Adminer\Plugin {
	const PEV2_ROOT = "https://cdn.jsdelivr.net/npm/pev2@1.3.0";
	const VUE_URL = "https://cdn.jsdelivr.net/npm/vue@3.2.37/dist/vue.esm-browser.prod.js";
	const BOOTSTRAP_CSS_URL = "https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/css/bootstrap.min.css";
	const FONTAWESOME_CSS_URL = "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-svg-core@6.1.1/styles.css";

	private $root;

	function __construct($root = null) {
		$this->root = rtrim(($root ?: self::PEV2_ROOT), "/");
	}

	function explain($connection, $query, $orgtables) {
		if (!defined('Adminer\\DRIVER') || Adminer\DRIVER != "pgsql") {
			return null;
		}

		$legacy = Adminer\explain($connection, $query);
		if (!$legacy) {
			return "";
		}
		ob_start();
		Adminer\print_select_result($legacy, $connection, $orgtables);
		$legacy = ob_get_clean();

		$modeRequested = isset($_POST["pev2_analyze"]);
		$analyze = ($modeRequested && $_POST["pev2_analyze"] == "1");
		$options = ($analyze ? "ANALYZE, COSTS, VERBOSE, BUFFERS, FORMAT JSON" : "COSTS, FORMAT JSON");
		$result = $connection->query("EXPLAIN ($options) $query");
		if (!$result) {
			return $legacy;
		}
		$row = $result->fetch_row();
		if (!$row) {
			return $legacy;
		}

		static $count = 0, $importMap = false;
		$count++;
		$id = "pev2-$count";
		$plan = $row[0];
		$root = Adminer\h($this->root);
		$vue = Adminer\h(self::VUE_URL);
		$bootstrap = Adminer\h(self::BOOTSTRAP_CSS_URL);
		$fontawesome = Adminer\h(self::FONTAWESOME_CSS_URL);
		ob_start();
		?>
<?php if (!$importMap) { $importMap = true; ?>
<script type='importmap'<?php echo Adminer\nonce(); ?>>{"imports":{"vue":"<?php echo $vue; ?>"}}</script>
<?php } ?>
<div class='explain-pev2-tabs' data-explain-pev2<?php echo ($modeRequested ? " data-initial-tab='pev2'" : ""); ?>>
	<p>
		<button type='button' data-explain-tab='legacy'><?php echo $this->lang('Standard'); ?></button>
		<button type='button' data-explain-tab='pev2'>PEV2</button>
	</p>
	<div data-explain-panel='legacy'>
		<?php echo $legacy; ?>
	</div>
	<div data-explain-panel='pev2' hidden>
		<div class='pev2-modal' role='dialog' aria-modal='true' aria-label='PEV2'>
			<p>
				<button type='button' data-explain-tab='legacy'><?php echo $this->lang('Standard'); ?></button>
				<button type='button' class='pev2-close' data-explain-tab='legacy'
					title='<?php echo $this->lang('Close'); ?>'
					aria-label='<?php echo $this->lang('Close'); ?>'>&times;</button>
			</p>
			<form action='' method='post' class='pev2-options'>
				<?php
				echo Adminer\input_hidden('query', $query);
				echo Adminer\input_hidden('limit', (isset($_POST['limit']) ? $_POST['limit'] : ''));
				echo Adminer\input_token();
				?>
				<button type='submit' name='pev2_analyze' value='0'<?php echo (!$analyze ? " class='active'" : ""); ?>>
					<?php echo $this->lang('Estimate'); ?>
				</button>
				<button type='submit' name='pev2_analyze' value='1'
					title='<?php echo $this->lang('Analyze title'); ?>'<?php echo ($analyze ? " class='active'" : ""); ?>>
					<?php echo $this->lang('Analyze'); ?>
				</button>
			</form>
			<div id='<?php echo $id; ?>' class='pev2-container'
				data-plan-source='<?php echo Adminer\h($plan); ?>'
				data-plan-query='<?php echo Adminer\h($query); ?>'></div>
		</div>
	</div>
</div>
<style>
.explain-pev2-tabs > p { margin: .5em 0; }
.explain-pev2-tabs button.active { font-weight: bold; }
.pev2-modal {
	position: fixed;
	z-index: 1000;
	inset: 0;
	display: flex;
	flex-direction: column;
	padding: 1em;
	background: Canvas;
	color: CanvasText;
}
.pev2-modal > p {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin: 0 0 .5em;
}
.pev2-options {
	display: inline-flex;
	gap: .25em;
	margin: 0 0 .5em;
}
.pev2-options button.active { font-weight: bold; }
.pev2-container { flex: 1 1 auto; min-height: 0; }
.pev2-close { font-size: 1.75em; line-height: 1; }
</style>
<script<?php echo Adminer\nonce(); ?>>
(() => {
	const root = document.currentScript.previousElementSibling.previousElementSibling;
	const show = tab => {
		root.dataset.active = tab;
		for (const button of root.querySelectorAll('[data-explain-tab]')) button.classList.toggle('active', button.dataset.explainTab === tab);
		for (const panel of root.querySelectorAll('[data-explain-panel]')) panel.hidden = panel.dataset.explainPanel !== tab;
		root.dispatchEvent(new CustomEvent('adminer-explain-tab', { detail: tab }));
	};
	for (const button of root.querySelectorAll('[data-explain-tab]')) button.addEventListener('click', () => show(button.dataset.explainTab));
	const form = root.querySelector('.pev2-options');
	form.addEventListener('submit', async event => {
		event.preventDefault();
		const data = new FormData(form);
		data.set('pev2_analyze', event.submitter.value);
		for (const button of form.querySelectorAll('button')) button.disabled = true;
		try {
			const response = await fetch(form.action || location.href, { method: 'POST', body: data, credentials: 'same-origin' });
			if (!response.ok) throw new Error(response.status);
			const document2 = new DOMParser().parseFromString(await response.text(), 'text/html');
			const next = document2.querySelector('[data-explain-pev2]');
			const nextHost = next && next.querySelector('.pev2-container');
			const nextForm = next && next.querySelector('.pev2-options');
			if (!nextHost || !nextForm) throw new Error('PEV2 plan missing');
			const host = root.querySelector('.pev2-container');
			host.dataset.planSource = nextHost.dataset.planSource;
			host.dataset.planQuery = nextHost.dataset.planQuery;
			form.innerHTML = nextForm.innerHTML;
			root.dispatchEvent(new CustomEvent('adminer-explain-plan'));
			show('pev2');
		} catch (error) {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'pev2_analyze';
			input.value = event.submitter.value;
			form.append(input);
			form.submit();
		}
	});
	show(root.dataset.initialTab || 'legacy');
})();
</script>
<script type='module'<?php echo Adminer\nonce(); ?>>
import { createApp, h } from 'vue';
import { Plan } from '<?php echo $root; ?>/dist/pev2.es.js';
const host = document.getElementById('<?php echo $id; ?>');
const tabs = host.closest('[data-explain-pev2]');
let shadow, app;
const mount = () => {
	if (app) return;
	if (!shadow) shadow = host.attachShadow({ mode: 'open' });
 	shadow.innerHTML = `
<link rel="stylesheet" href="<?php echo $root; ?>/dist/style.css">
<link rel="stylesheet" href="<?php echo $bootstrap; ?>">
<link rel="stylesheet" href="<?php echo $fontawesome; ?>">
<div id="app" style="display:flex;height:100%"></div>`;
	const target = shadow.getElementById('app');
	app = createApp({ render: () => h(Plan, { planSource: host.dataset.planSource, planQuery: host.dataset.planQuery }) });
	app.mount(target);
};
tabs.addEventListener('adminer-explain-tab', event => { if (event.detail === 'pev2') mount(); });
tabs.addEventListener('adminer-explain-plan', () => {
	if (app) app.unmount();
	app = null;
	mount();
});
if (tabs.dataset.active === 'pev2') mount();
</script>
<?php
		return ob_get_clean();
	}

	protected $translations = array(
		'en' => array('' => 'Visualize PostgreSQL EXPLAIN with PEV2', 'Standard' => 'Standard', 'Estimate' => 'Estimate', 'Analyze' => 'ANALYZE', 'Analyze title' => 'Executes the query and may be slow'),
		'cs' => array('' => 'Zobrazí PostgreSQL EXPLAIN pomocí PEV2', 'Standard' => 'Standardní', 'Estimate' => 'Odhad', 'Analyze' => 'ANALYZE', 'Analyze title' => 'Spustí dotaz a může být pomalé'),
		'de' => array('' => 'Zeigt PostgreSQL EXPLAIN mit PEV2 an', 'Standard' => 'Standard', 'Estimate' => 'Schätzung', 'Analyze title' => 'Führt die Abfrage aus und kann langsam sein'),
		'pl' => array('' => 'Wyświetla PostgreSQL EXPLAIN za pomocą PEV2', 'Standard' => 'Standardowy', 'Estimate' => 'Szacunek', 'Analyze title' => 'Wykonuje zapytanie i może działać wolno'),
		'ja' => array('' => 'PEV2 で PostgreSQL EXPLAIN を表示', 'Standard' => '標準', 'Estimate' => '見積もり', 'Analyze title' => 'クエリを実行するため時間がかかることがあります'),
		'hr' => array('' => 'Prikazuje PostgreSQL EXPLAIN pomoću PEV2', 'Standard' => 'Standardni', 'Estimate' => 'Procjena', 'Analyze title' => 'Izvršava upit i može biti sporo'),
	);
}
