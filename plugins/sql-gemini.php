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
			$prompt = "I have a database with this structure:\n\n";
			foreach (Adminer\tables_list() as $table => $type) {
				$prompt .= Adminer\create_sql($table, false, "CREATE") . ";\n\n";
			}
			$prompt .= "Give me this SQL query and nothing else:\n\n$_POST[gemini]";
			//~ echo $prompt; exit;
			$context = stream_context_create(array("http" => array(
				"method" => "POST",
				"header" => array("User-Agent: AdminerSqlGemini", "Content-Type: application/json"),
				"content" => '{"contents": [{"parts":[{"text": ' . json_encode($prompt) . '}]}]}',
			)));
			$response = json_decode(file_get_contents("https://generativelanguage.googleapis.com/v1beta/models/$this->model:generateContent?key=$this->apiKey", false, $context));
			$text = $response->candidates[0]->content->parts[0]->text;
			echo preg_replace('~```sql\n(.*\n)```~s', '\1', $text) . "\n";
			exit;
		}
	}

	function sqlPrintAfter() {
		echo "<p><textarea name='gemini' rows='5' cols='50' title='AI prompt'>" . Adminer\h($_POST["gemini"]) . "</textarea>\n";
		echo "<p><input type='button' value='Gemini'>" . Adminer\script("qsl('input').onclick = function () { ajax(
			'',
			req => {
				qs('textarea.sqlarea').value = req.responseText;
				const sqlarea = qs('pre.sqlarea');
				sqlarea.textContent = req.responseText;
				sqlarea.oninput(); // syntax highlighting
				alterClass(qs('#ajaxstatus'), 'hidden', true);
			},
			'gemini=' + encodeURIComponent(this.form['gemini'].value),
			'Just a sec…' // this is the phrase used by Google Gemini
		); }");
	}
}
