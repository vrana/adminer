<?php

/** Get e-mail subject and message from database (Adminer Editor)
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerEmailTable {
	/** @access protected */
	var $table, $id, $title, $subject, $message;
	
	/**
	* @param string quoted table name
	* @param string quoted column name
	* @param string quoted column name
	* @param string quoted column name
	* @param string quoted column name
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
			print_fieldset("email", ('E-mail'));
			echo "<div>\n";
			echo script("qsl('div').onkeydown = partial(bodyKeydown, 'email');");
			echo "<p>" . ('From') . ": <input name='email_from' value='" . h($_POST ? $_POST["email_from"] : $_COOKIE["adminer_email"]) . "'>\n";
			echo ('Subject') . ": <select name='email_id'><option>" . optionlist(get_key_vals("SELECT $this->id, $this->title FROM $this->table ORDER BY $this->title"), $_POST["email_id"], true) . "</select>\n";
			echo "<p>" . ('Attachments') . ": <input type='file' name='email_files[]'>";
			echo script("qsl('input').onchange = function () {
	this.onchange = function () { };
	var el = this.cloneNode(true);
	el.value = '';
	this.parentNode.appendChild(el);
};");
			echo "<p>" . (count($emailFields) == 1 ? '<input type="hidden" name="email_field" value="' . h(key($emailFields)) . '">' : html_select("email_field", $emailFields));
			echo "<input type='submit' name='email' value='" . ('Send') . "'>" . confirm();
			echo "</div>\n";
			echo "</div></fieldset>\n";
			return true;
		}
	}
	
	function selectEmailProcess($where, $foreignKeys) {
		$connection = connection();
		if ($_POST["email_id"]) {
			$result = $connection->query("SELECT $this->subject, $this->message FROM $this->table WHERE $this->id = " . q($_POST["email_id"]));
			$row = $result->fetch_row();
			$_POST["email_subject"] = $row[0];
			$_POST["email_message"] = $row[1];
		}
	}
	
}
