<?php

/** Display confirmation before unloading page if a form field was changed
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerBeforeUnload {

	function head($dark = null) {
		?>
<script <?php echo Adminer\nonce(); ?>>
// editChange is declared in functions.js
// ajaxForm sets editChange to null on success

addEvent(document, 'change', event => {
	const el = event.target;
	if (el.form && /post/i.test(el.form.method)) {
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
}
