<?php

/** AI prompt in SQL command generating the queries with Google Gemini
* Beware that this sends your whole database structure (not data) to Google Gemini.
* @link https://www.adminer.org/static/plugins/sql-gemini.gif
* @link https://gemini.google.com/
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerSqlGemini {
	private $apiKey;
	private $model;

	/**
	* @param string Get API key at: https://aistudio.google.com/apikey
	* @param string Available models: https://ai.google.dev/gemini-api/docs/models#available-models
	*/
	function __construct($apiKey, $model = "gemini-2.0-flash") {
		$this->apiKey = $apiKey;
		$this->model = $model;
	}

	function headers() {
		if ($_POST["gemini"] && !isset($_POST["query"])) {
			$prompt = "I have a " . Adminer\get_driver(Adminer\DRIVER) . " database with this structure:\n\n";
			foreach (Adminer\tables_list() as $table => $type) {
				$prompt .= Adminer\create_sql($table, false, "CREATE") . ";\n\n";
			}
			$prompt .= "Prefer returning more columns including primary key.\n\n";
			$prompt .= "Give me this SQL query and nothing else:\n\n$_POST[gemini]\n\n";
			//~ echo $prompt; exit;
			$context = stream_context_create(array("http" => array(
				"method" => "POST",
				"header" => array("User-Agent: AdminerSqlGemini", "Content-Type: application/json"),
				"content" => '{"contents": [{"parts":[{"text": ' . json_encode($prompt) . '}]}]}',
			)));
			$response = json_decode(file_get_contents("https://generativelanguage.googleapis.com/v1beta/models/$this->model:generateContent?key=$this->apiKey", false, $context));
			$text = $response->candidates[0]->content->parts[0]->text;
			$in_code = false;
			foreach (preg_split('~(^|\n)```(sql)?(\n|$)~', $text) as $part) {
				$part = trim($part);
				if ($part) {
					echo ($in_code ? $part : "/*\n$part\n*/") . "\n\n";
				}
				$in_code = !$in_code;
			}
			exit;
		}
	}

	function sqlPrintAfter() {
		echo "<p><textarea name='gemini' rows='5' cols='50' title='AI prompt'>" . Adminer\h($_POST["gemini"]) . "</textarea>\n";
		?>
<p><input type='button' value='Gemini'>
<script <?php echo Adminer\nonce(); ?>>
const geminiText = qsl('textarea');
const geminiButton = qsl('input');

function setSqlareaValue(value) {
	qs('textarea.sqlarea').value = value;
	qs('pre.sqlarea').textContent = value;
	qs('pre.sqlarea').oninput(); // syntax highlighting
}

geminiButton.onclick = () => {
	setSqlareaValue('-- Just a sec...'); // this is the phrase used by Google Gemini
	ajax(
		'',
		req => setSqlareaValue(req.responseText),
		'gemini=' + encodeURIComponent(geminiText.value)
	);
};

geminiText.onfocus = event => {
	alterClass(findDefaultSubmit(geminiText), 'default');
	alterClass(geminiButton, 'default', true);
	event.stopImmediatePropagation();
};

geminiText.onblur = () => {
	alterClass(geminiButton, 'default');
};

geminiText.onkeydown = event => {
	if (isCtrl(event) && (event.keyCode == 13 || event.keyCode == 10)) {
		geminiButton.onclick();
		event.stopPropagation();
	}
};
</script>
<?php
	}
}
