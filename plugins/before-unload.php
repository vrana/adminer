<?php

/** Display a confirmation before leaving the page if a form field was changed
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerBeforeUnload extends Adminer\Plugin {

	function head($dark = null) {
		?>
<script <?php echo Adminer\nonce(); ?>>
// editChange is declared in functions.js
// ajaxForm sets editChange to null on success

addEvent(document, 'change', event => {
	const el = event.target;
	// isTrusted - fire() dispatches change to run the delegated handlers, which is not a change by the user
	// auth[ - browsers pre-fill the login form, there's nothing to lose there anyway
	if (event.isTrusted !== false && el.form && /post/i.test(el.form.method) && !/^auth\[/.test(el.name)) {
		editChanged = true;
	}
});

addEvent(document, 'submit', () => {
	editChanged = null;
});

// all modern browsers ignore string returned from here
onbeforeunload = () => editChanged;
</script>
<?php
	}

	protected $translations = array(
		'cs' => array('' => 'Zobrazí potvrzení před opuštěním stránky, pokud bylo změněno formulářové políčko'),
		'de' => array('' => 'Zeigt eine Bestätigung an, bevor die Seite verlassen wird, wenn ein Formularfeld geändert wurde'),
		'ja' => array('' => 'フォームの項目が変更されている場合、ページを離れる前に確認を表示'), // Claude Opus 5
		'pl' => array('' => 'Wyświetlaj potwierdzenie przed opuszczeniem strony, jeśli pole formularza zostało zmienione'), // Claude Opus 5
		'ro' => array('' => 'Afișează o confirmare înainte de părăsirea paginii dacă un câmp din formular a fost modificat'), // Claude Opus 5
		'sk' => array('' => 'Zobrazí potvrdenie pred opustením stránky, pokiaľ bolo zmenené formulárové políčko'), // Claude Opus 5
		'hr' => array('' => 'Prikazuje potvrdu prije napuštanja stranice ako je polje obrasca promijenjeno'),
		'zh' => array('' => '表单字段被修改后，离开页面前显示确认'),
	);
}
