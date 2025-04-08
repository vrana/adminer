<?php

/** Use VS Code's Monaco Editor for syntax highlighting and SQL <textarea>
* @link https://microsoft.github.io/monaco-editor/
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerMonaco extends Adminer\Plugin {
	private $root;

	function __construct($root = "https://cdn.jsdelivr.net/npm/monaco-editor@0.52/min/vs") {
		$this->root = $root;
	}

	function syntaxHighlighting($tableStatuses) {
		echo Adminer\script_src("$this->root/loader.js", true);
		?>
<script <?php echo Adminer\nonce(); ?>>
addEventListener('DOMContentLoaded', () => {
	require.config({ paths: { vs: '<?php echo $this->root; ?>' } });
	require(['vs/editor/editor.main'], function (monaco) {
		adminerHighlighter = els => els.forEach(el => {
			const lang = getMonacoLang(el);
			if (lang) {
				monaco.editor.colorize(el.textContent, lang).then(html => el.innerHTML = html);
			}
		});
		adminerHighlighter(qsa('code'));

		for (const el of qsa('textarea')) {
			const lang = getMonacoLang(el);
			if (lang) {
				const container = document.createElement('div');
				container.style.border = '1px inset #ccc';
				container.style.width = el.clientWidth + 'px';
				container.style.height = el.clientHeight + 'px';
				el.before(container);
				el.style.display = 'none';
				var editor = monaco.editor.create(container, {
					value: el.value,
					lineNumbers: 'off',
					glyphMargin: false,
					folding: false,
					lineDecorationsWidth: 1,
					minimap: {enabled: false},
					language: lang
				});
				editor.onDidChangeModelContent(() => el.value = editor.getValue());
				el.onchange = () => editor.setValue(el.value);
				monaco.editor.addKeybindingRules([
					{keybinding: monaco.KeyCode.Tab, command: null}
					//! Ctrl+Enter
				]);
			}
		}
	});

	function getMonacoLang(el) {
		return (
			/jush-js/.test(el.className) ? 'javascript' : (
			/jush-sql/.test(el.className) ? 'mysql' : (
			/jush-pgsql/.test(el.className) ? 'pgsql' : (
			/jush-(sqlite|mssql|oracle|clickhouse|firebird)/.test(el.className) ? 'sql' : (
			''
		)))));
	}
});
</script>
<?php
		return true;
	}

	protected $translations = array(
		'cs' => array('' => 'Použije Monaco Editor z VS Code pro zvýrazňování syntaxe a <textarea>'),
		'de' => array('' => 'Monaco-Editor von VS Code verwenden, für die Syntaxhervorhebung und SQL <textarea>'),
		'ja' => array('' => '構文や <textarea> の強調表示に VS Code の Monaco Editor を使用'),
		'pl' => array('' => 'Użyj Monaco Editora programu VS Code do podświetlania składni i <textarea> SQL'),
	);
}
