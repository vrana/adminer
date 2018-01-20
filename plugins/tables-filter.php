<?php

/** Use filter in tables list
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerTablesFilter {
	function tablesPrint($tables) { ?>
<script<?php echo nonce(); ?>>
var tablesFilterTimeout = null;
var tablesFilterValue = '';

function tablesFilter(){
	var value = qs('#filter-field').value.toLowerCase();
	if (value == tablesFilterValue) {
		return;
	}
	tablesFilterValue = value;
	if (value != '') {
		var reg = (value + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, '\\$1');
		reg = new RegExp('('+ reg + ')', 'gi');
	}
	if (sessionStorage) {
		sessionStorage.setItem('adminer_tables_filter', value);
	}
	var tables = qsa('li', qs('#tables'));
	for (var i = 0; i < tables.length; i++) {
		var a = qsa('a', tables[i])[1];
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

if (sessionStorage){
	var db = qs('#dbs').querySelector('select');
	db = db.options[db.selectedIndex].text;
	if (db == sessionStorage.getItem('adminer_tables_filter_db') && sessionStorage.getItem('adminer_tables_filter')){
		qs('#filter-field').value = sessionStorage.getItem('adminer_tables_filter');
		tablesFilter();
	}
	sessionStorage.setItem('adminer_tables_filter_db', db);
}
</script>
<p class="jsonly"><input id="filter-field" autocomplete="off"><?php echo script("qs('#filter-field').oninput = tablesFilterInput;"); ?>
<ul id='tables'>
<?php
echo script("mixin(qs('#tables'), {onmouseover: menuOver, onmouseout: menuOut});");
foreach ($tables as $table => $status) {
	echo '<li data-table-name="' . h($table) . '"><a href="' . h(ME) . 'select=' . urlencode($table) . '"' . bold($_GET["select"] == $table || $_GET["edit"] == $table, "select") . ">" . lang('select') . "</a> ";
	$name = h($status["Name"]);
	echo (support("table") || support("indexes")
		? '<a href="' . h(ME) . 'table=' . urlencode($table) . '"'
			. bold(in_array($table, array($_GET["table"], $_GET["create"], $_GET["indexes"], $_GET["foreign"], $_GET["trigger"])), (is_view($status) ? "view" : "structure"))
			. " title='" . lang('Show structure') . "'>$name</a>"
		: "<span>$name</span>"
	) . "\n";
}
?>
</ul>
<?php
		return true;
	}
}
