<?php

/** Verify new versions from GitHub
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerVersionGithub extends Adminer\Plugin {

	function head($dark = null) {
		?>
<script <?php echo Adminer\nonce(); ?>>
verifyVersion = (current, url, token) => {
	// dummy value to prevent repeated verifications after AJAX failure
	cookie('adminer_version=0', 1);
	ajax('https://api.github.com/repos/vrana/adminer/releases/latest', request => {
		const response = JSON.parse(request.responseText);
		const version = response.tag_name.replace(/^v/, '');
		// we don't save to adminer.version because the response is not signed; also GitHub can handle our volume of requests
		// we don't display the version here because we don't have version_compare(); design.inc.php will display it on the next load
		cookie('adminer_version=' + version, 1);
	}, null, null);
};
</script>
<?php
	}

	function csp(&$csp) {
		$csp[0]["connect-src"] .= " https://api.github.com/repos/vrana/adminer/releases/latest";
	}

	protected $translations = array(
		'cs' => array('' => 'Kontrola nových verzí z GitHubu'),
		'de' => array('' => 'Neue Versionen von GitHub verifizieren'),
		'ja' => array('' => 'GitHub の新版を管理'),
		'pl' => array('' => 'Weryfikuj nowe wersje z GitHuba'),
	);
}
