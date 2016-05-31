<?php

/** Use filter in tables list
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, http://www.vrana.cz/
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTablesFilter {
	function tablesPrint($tables) { ?>
<p class="jsonly"><input id="filter-field" onkeyup="tablesFilterInput();">
<p id='tables' onmouseover='menuOver(this, event);' onmouseout='menuOut(this);'>
<?php
foreach ($tables as $table => $type) {
	echo '<span data-table-name="'.h($table).'"><a href="'.h(ME).'select='.urlencode($table).'"'.bold($_GET["select"] == $table).">".lang('select')."</a> ";
	echo '<a href="'.h(ME).'table='.urlencode($table).'"'.bold($_GET["table"] == $table).">".h($table)."</a><br></span>\n";
}
?>
<script type="text/javascript">
var tablesFilterTimeout = null;
var tablesFilterValue = '';

function tablesFilter(){
	var value = document.getElementById('filter-field').value.toLowerCase();
	if (value == tablesFilterValue) {
		return;
	}
	tablesFilterValue = value;
	if (value != '') {
		var reg = (value + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, '\\$1');
		reg = new RegExp('('+ reg + ')', 'gi');
	}
	var tables = document.getElementById('tables').getElementsByTagName('span');
	for (var i = 0; i < tables.length; i++) {
		var a = tables[i].getElementsByTagName('a')[1];
		var text = tables[i].getAttribute('data-table-name');
		if (value == '') {
			tables[i].className = '';
			a.innerHTML = text;
		} else {
			tables[i].className = (text.toLowerCase().indexOf(value) == -1 ? 'hidden' : '');
			a.innerHTML = text.replace(reg, '<strong>$1</strong>');
		}
	}
}

function tablesFilterInput() {
	window.clearTimeout(tablesFilterTimeout);
	tablesFilterTimeout = window.setTimeout(tablesFilter, 200);
}

if (document.getElementById('filter-field').value){
    tablesFilter();
}
</script>
<?php
		return true;
	}
}
