<?php

/** Use Prism for syntax highlighting, disables highlighting in <textarea>
* @link https://prismjs.com/
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerPrism {
	private $root;
	private $minified;
	private $theme;

	function __construct($root = "https://cdn.jsdelivr.net/npm/prismjs@1", $minified = ".min", $theme = "prism") {
		$this->root = $root;
		$this->minified = $minified;
		$this->theme = $theme;
	}

	function syntaxHighlighting($tableStatuses) {
		echo "<style>@import url($this->root/themes/$this->theme$this->minified.css);</style>\n";
		echo Adminer\script_src("$this->root/prism$this->minified.js");
		echo Adminer\script_src("$this->root/components/prism-json$this->minified.js");
		echo Adminer\script_src("$this->root/components/prism-sql$this->minified.js");
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
<?php
		return true;
	}
}
