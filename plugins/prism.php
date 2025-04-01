<?php

/** Use Prism for syntax highlighting and Prism Code Editor for <textarea>
* @link https://prismjs.com/
* @link https://prism-code-editor.netlify.app/
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPrism {
	private $editorRoot;
	private $minified;
	private $theme;
	private $prismRoot; //! use editor also for syntax highlighting

	function __construct($editorRoot = "https://cdn.jsdelivr.net/npm/prism-code-editor@3/dist", $minified = ".min", $theme = "prism", $prismRoot = "https://cdn.jsdelivr.net/npm/prismjs@1") {
		$this->editorRoot = $editorRoot;
		$this->minified = $minified;
		$this->theme = $theme;
		$this->prismRoot = $prismRoot;
	}

	function syntaxHighlighting($tableStatuses) {
		echo "<style>@import url($this->prismRoot/themes/$this->theme$this->minified.css);</style>\n";
		echo Adminer\script_src("$this->prismRoot/prism$this->minified.js");
		echo Adminer\script_src("$this->prismRoot/components/prism-json$this->minified.js");
		echo Adminer\script_src("$this->prismRoot/components/prism-sql$this->minified.js");
		?>
<script <?php echo Adminer\nonce(); ?>>
function changeClass(el) {
	el.className = el.className
		.replace(/jush-js/, 'language-json')
		.replace(/jush-\w*sql/, 'language-sql')
	;
	return el;
}
qsa('code').forEach(changeClass);
adminerHighlighter = els => els.forEach(el => Prism.highlightElement(changeClass(el)));
</script>

<link rel="stylesheet" href="<?php echo $this->editorRoot; ?>/layout.min.css">
<link rel="stylesheet" href="<?php echo $this->editorRoot; ?>/themes/<?php echo $this->theme . $this->minified; ?>.css">
<script type="module"<?php echo Adminer\nonce(); ?>>
import { editorFromPlaceholder } from '<?php echo $this->editorRoot; ?>/index.js'
import '<?php echo $this->editorRoot; ?>/prism/languages/sql.js'
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
}
