<?php

/** Use Prism Code Editor for syntax highlighting and <textarea>
* @link https://prism-code-editor.netlify.app/
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPrism extends Adminer\Plugin {
	private $editorRoot;
	private $minified;
	private $theme;

	function __construct($editorRoot = "https://cdn.jsdelivr.net/npm/prism-code-editor@3/dist", $minified = ".min", $theme = "prism") {
		$this->editorRoot = $editorRoot;
		$this->minified = $minified;
		$this->theme = $theme;
	}

	function syntaxHighlighting($tableStatuses) {
		?>
<style>
@import url(<?php echo "$this->editorRoot/layout$this->minified.css"; ?>);
@import url(<?php echo "$this->editorRoot/themes/$this->theme$this->minified.css"; ?>);
.prism-code-editor { border: 1px inset #ccc; resize: both; }
</style>
<script type="module"<?php echo Adminer\nonce(); ?>>
import { editorFromPlaceholder } from '<?php echo $this->editorRoot; ?>/index.js';
import { highlightText } from '<?php echo $this->editorRoot; ?>/prism/index.js';
import '<?php echo $this->editorRoot; ?>/prism/languages/json.js';
import '<?php echo $this->editorRoot; ?>/prism/languages/sql.js';

adminerHighlighter = els => els.forEach(el => {
	const mode = (
		/jush-js/.test(el.className) ? 'json' : (
		/jush-(\w*sql|oracle|clickhouse|firebird)/.test(el.className) ? 'sql' : (
		''
	)));
	if (mode) {
		el.innerHTML = highlightText(el.textContent, mode);
	}
});
adminerHighlighter(qsa('code'));

const el = document.querySelector('.sqlarea');
if (el) {
	const name = el.name;
	const width = el.clientWidth;
	const height = el.clientHeight;
	const editor = editorFromPlaceholder('.sqlarea', { language: 'sql', lineNumbers: false });
	editor.wrapper.parentElement.style.width = width + 'px';
	editor.wrapper.style.height = height + 'px';
	editor.textarea.name = name;
	editor.textarea.className = 'sqlarea';
	editor.textarea.onchange = editor.update;
}
</script>
<?php
		return true;
	}

	protected $translations = array(
		'cs' => array('' => 'Použije Prism Code Editor pro zvýrazňování syntaxe a <textarea>'),
		'de' => array('' => 'Prism Code Editor verwenden, für die Syntaxhervorhebung und <textarea>'),
		'ja' => array('' => '構文や <textarea> の強調表示に Prism Code Editor を使用'),
		'pl' => array('' => 'Użyj Prism Code Editora do podświetlania składni i <textarea>'),
	);
}
