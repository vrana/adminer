<?php

/** Use filter in tables list
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTablesFilter {
	
	function tablesPrint($tables) {
		?>
<script type="text/javascript">
function tablesFilter(value) {
	var tables = document.getElementById('tables').getElementsByTagName('span');
	for (var i = tables.length; i--; ) {
		var a = tables[i].children[1];
		var text = a.innerText || a.textContent;
		tables[i].className = (text.indexOf(value) == -1 ? 'hidden' : '');
		a.innerHTML = text.replace(value, '<b>' + value + '</b>');
	}
}
</script>
<p class="jsonly"><input onkeyup="tablesFilter(this.value);">
<?php
		echo "<p id='tables' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>\n";
		foreach ($tables as $table => $type) {
			echo '<span><a href="' . h(ME) . 'select=' . urlencode($table) . '"' . bold($_GET["select"] == $table) . ">" . lang('select') . "</a> ";
			echo '<a href="' . h(ME) . 'table=' . urlencode($table) . '"' . bold($_GET["table"] == $table) . ">" . h($table) . "</a><br></span>\n";
		}
		return true;
	}
	
}
