<?php

/** Get e-mail subject and message from database (Adminer Editor)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEmailTable extends Adminer\Plugin {
	protected $table, $id, $title, $subject, $message;

	/**
	* @param string $table quoted table name
	* @param string $id quoted column name
	* @param string $title quoted column name
	* @param string $subject quoted column name
	* @param string $message quoted column name
	*/
	function __construct($table = "email", $id = "id", $title = "subject", $subject = "subject", $message = "message") {
		$this->table = $table;
		$this->id = $id;
		$this->title = $title;
		$this->subject = $subject;
		$this->message = $message;
	}

	function selectEmailPrint($emailFields, $columns) {
		if ($emailFields) {
			Adminer\print_fieldset("email", ('E-mail'));
			echo "<div>\n";
			echo Adminer\script("qsl('div').onkeydown = partial(bodyKeydown, 'email');");
			echo "<p>" . ('From') . ": <input name='email_from' value='" . Adminer\h($_POST ? $_POST["email_from"] : $_COOKIE["adminer_email"]) . "'>\n";
			echo ('Subject') . ": <select name='email_id'><option>" . Adminer\optionlist(Adminer\get_key_vals("SELECT $this->id, $this->title FROM $this->table ORDER BY $this->title"), $_POST["email_id"], true) . "</select>\n";
			echo "<p>" . ('Attachments') . ": <input type='file' name='email_files[]'>";
			echo Adminer\script("qsl('input').onchange = function () {
	this.onchange = function () { };
	const el = this.cloneNode(true);
	el.value = '';
	this.parentNode.appendChild(el);
};");
			echo "<p>" . (count($emailFields) == 1 ? Adminer\input_hidden("email_field", key($emailFields)) : Adminer\html_select("email_field", $emailFields));
			echo "<input type='submit' name='email' value='" . ('Send') . "'>" . Adminer\confirm();
			echo "</div>\n";
			echo "</div></fieldset>\n";
			return true;
		}
	}

	function selectEmailProcess($where, $foreignKeys) {
		if ($_POST["email_id"]) {
			$result = Adminer\connection()->query("SELECT $this->subject, $this->message FROM $this->table WHERE $this->id = " . Adminer\q($_POST["email_id"]));
			$row = $result->fetch_row();
			$_POST["email_subject"] = $row[0];
			$_POST["email_message"] = $row[1];
		}
	}

	protected $translations = array(
		'cs' => array('' => 'Získá předmět a zprávu e-mailu z databáze (Adminer Editor)'),
		'de' => array('' => 'E-Mail-Betreff und Nachricht aus der Datenbank abrufen (Adminer Editor)'),
		'pl' => array('' => 'Pobieraj temat i wiadomość e-mail z bazy danych (Adminer Editor)'),
		'ro' => array('' => 'Obțineți subiectul e-mailului și mesajul din baza de date (Adminer Editor)'),
		'ja' => array('' => 'メールの件名と本文をデータベースから取得 (Adminer Editor)'),
	);
}
