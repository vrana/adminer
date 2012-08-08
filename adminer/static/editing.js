// Adminer specific functions

var jushRoot = '../externals/jush/'; // global variable to allow simple customization

/** Load syntax highlighting
* @param string first three characters of database system version
*/
function bodyLoad(version) {
	if (jushRoot) {
		// copy of jush.style to load JS and CSS at once
		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.type = 'text/css';
		link.href = jushRoot + 'jush.css';
		document.getElementsByTagName('head')[0].appendChild(link);
		
		var script = document.createElement('script');
		script.src = jushRoot + 'jush.js';
		script.onload = function () {
			if (window.jush) { // IE runs in case of an error too
				jush.create_links = ' target="_blank" rel="noreferrer"';
				jush.urls.sql_sqlset = jush.urls.sql[0] = jush.urls.sqlset[0] = jush.urls.sqlstatus[0] = 'http://dev.mysql.com/doc/refman/' + version + '/en/$key';
				var pgsql = 'http://www.postgresql.org/docs/' + version + '/static/';
				jush.urls.pgsql_pgsqlset = jush.urls.pgsql[0] = pgsql + '$key';
				jush.urls.pgsqlset[0] = pgsql + 'runtime-config-$key.html#GUC-$1';
				if (window.jushLinks) {
					jush.custom_links = jushLinks;
				}
				jush.highlight_tag('code', 0);
			}
		};
		script.onreadystatechange = function () {
			if (/^(loaded|complete)$/.test(script.readyState)) {
				script.onload();
			}
		};
		document.body.appendChild(script);
	}
}

/** Get value of dynamically created form field
* @param HTMLFormElement
* @param string
* @return HTMLElement
*/
function formField(form, name) {
	// required in IE < 8, form.elements[name] doesn't work
	for (var i=0; i < form.length; i++) {
		if (form[i].name == name) {
			return form[i];
		}
	}
}

/** Try to change input type to password or to text
* @param HTMLInputElement
* @param boolean
*/
function typePassword(el, disable) {
	try {
		el.type = (disable ? 'text' : 'password');
	} catch (e) {
	}
}

function loginDriver(driver) {
	var trs = parentTag(driver, 'table').rows;
	for (var i=1; i < trs.length - 1; i++) {
		trs[i].className = (/sqlite/.test(driver.value) ? 'hidden' : '');
	}
}



/** Handle Tab and Esc in textarea
* @param HTMLTextAreaElement
* @param KeyboardEvent
* @return boolean
*/
function textareaKeydown(target, event) {
	if (!event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey) {
		if (event.keyCode == 9) { // 9 - Tab
			// inspired by http://pallieter.org/Projects/insertTab/
			if (target.setSelectionRange) {
				var start = target.selectionStart;
				var scrolled = target.scrollTop;
				target.value = target.value.substr(0, start) + '\t' + target.value.substr(target.selectionEnd);
				target.setSelectionRange(start + 1, start + 1);
				target.scrollTop = scrolled;
				return false; //! still loses focus in Opera, can be solved by handling onblur
			} else if (target.createTextRange) {
				document.selection.createRange().text = '\t';
				return false;
			}
		}
		if (event.keyCode == 27) { // 27 - Esc
			var els = target.form.elements;
			for (var i=1; i < els.length; i++) {
				if (els[i-1] == target) {
					els[i].focus();
					break;
				}
			}
			return false;
		}
	}
	return true;
}



/** Check whether the query will be executed with index
* @param HTMLFormElement
*/
function selectFieldChange(form) {
	var ok = (function () {
		var inputs = form.getElementsByTagName('input');
		for (var i=0; i < inputs.length; i++) {
			if (inputs[i].value && /^fulltext/.test(inputs[i].name)) {
				return true;
			}
		}
		var ok = form.limit.value;
		var selects = form.getElementsByTagName('select');
		var group = false;
		var columns = {};
		for (var i=0; i < selects.length; i++) {
			var select = selects[i];
			var col = selectValue(select);
			var match = /^(where.+)col\]/.exec(select.name);
			if (match) {
				var op = selectValue(form[match[1] + 'op]']);
				var val = form[match[1] + 'val]'].value;
				if (col in indexColumns && (!/LIKE|REGEXP/.test(op) || (op == 'LIKE' && val.charAt(0) != '%'))) {
					return true;
				} else if (col || val) {
					ok = false;
				}
			}
			if ((match = /^(columns.+)fun\]/.exec(select.name))) {
				if (/^(avg|count|count distinct|group_concat|max|min|sum)$/.test(col)) {
					group = true;
				}
				var val = selectValue(form[match[1] + 'col]']);
				if (val) {
					columns[col && col != 'count' ? '' : val] = 1;
				}
			}
			if (col && /^order/.test(select.name)) {
				if (!(col in indexColumns)) {
					 ok = false;
				}
				break;
			}
		}
		if (group) {
			for (var col in columns) {
				if (!(col in indexColumns)) {
					ok = false;
				}
			}
		}
		return ok;
	})();
	setHtml('noindex', (ok ? '' : '!'));
}



var added = '.', rowCount;

/** Check if val is equal to a-delimiter-b where delimiter is '_', '' or big letter
* @param string
* @param string
* @param string
* @return boolean
*/
function delimiterEqual(val, a, b) {
	return (val == a + '_' + b || val == a + b || val == a + b.charAt(0).toUpperCase() + b.substr(1));
}

/** Escape string to use as identifier
* @param string
* @return string
*/
function idfEscape(s) {
	return s.replace(/`/, '``');
}

/** Detect foreign key
* @param HTMLInputElement
*/
function editingNameChange(field) {
	var name = field.name.substr(0, field.name.length - 7);
	var type = formField(field.form, name + '[type]');
	var opts = type.options;
	var candidate; // don't select anything with ambiguous match (like column `id`)
	var val = field.value;
	for (var i = opts.length; i--; ) {
		var match = /(.+)`(.+)/.exec(opts[i].value);
		if (!match) { // common type
			if (candidate && i == opts.length - 2 && val == opts[candidate].value.replace(/.+`/, '') && name == 'fields[1]') { // single target table, link to column, first field - probably `id`
				return;
			}
			break;
		}
		var table = match[1];
		var column = match[2];
		var tables = [ table, table.replace(/s$/, ''), table.replace(/es$/, '') ];
		for (var j=0; j < tables.length; j++) {
			table = tables[j];
			if (val == column || val == table || delimiterEqual(val, table, column) || delimiterEqual(val, column, table)) {
				if (candidate) {
					return;
				}
				candidate = i;
				break;
			}
		}
	}
	if (candidate) {
		type.selectedIndex = candidate;
		type.onchange();
	}
}

/** Add table row for next field
* @param HTMLInputElement
* @param boolean
* @param boolean
* @return boolean
*/
function editingAddRow(button, allowed, focus) {
	if (allowed && rowCount >= allowed) {
		return false;
	}
	var match = /(\d+)(\.\d+)?/.exec(button.name);
	var x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	var row = parentTag(button, 'tr');
	var row2 = row.cloneNode(true);
	var tags = row.getElementsByTagName('select');
	var tags2 = row2.getElementsByTagName('select');
	for (var i=0; i < tags.length; i++) {
		tags2[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		tags2[i].selectedIndex = tags[i].selectedIndex;
	}
	tags = row.getElementsByTagName('input');
	tags2 = row2.getElementsByTagName('input');
	var input = tags2[0]; // IE loose tags2 after insertBefore()
	for (var i=0; i < tags.length; i++) {
		if (tags[i].name == 'auto_increment_col') {
			tags2[i].value = x;
			tags2[i].checked = false;
		}
		tags2[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		if (/\[(orig|field|comment|default)/.test(tags[i].name)) {
			tags2[i].value = '';
		}
		if (/\[(has_default)/.test(tags[i].name)) {
			tags2[i].checked = false;
		}
	}
	tags[0].onchange = function () {
		editingNameChange(tags[0]);
	};
	row.parentNode.insertBefore(row2, row.nextSibling);
	if (focus) {
		input.onchange = function () {
			editingNameChange(input);
		};
		input.focus();
	}
	added += '0';
	rowCount++;
	return true;
}

/** Remove table row for field
* @param HTMLInputElement
* @return boolean
*/
function editingRemoveRow(button) {
	var field = formField(button.form, button.name.replace(/drop_col(.+)/, 'fields$1[field]'));
	field.parentNode.removeChild(field);
	parentTag(button, 'tr').style.display = 'none';
	return true;
}

var lastType = '';

/** Clear length and hide collation or unsigned
* @param HTMLSelectElement
*/
function editingTypeChange(type) {
	var name = type.name.substr(0, type.name.length - 6);
	var text = selectValue(type);
	for (var i=0; i < type.form.elements.length; i++) {
		var el = type.form.elements[i];
		if (el.name == name + '[length]' && !(
			(/(char|binary)$/.test(lastType) && /(char|binary)$/.test(text))
			|| (/(enum|set)$/.test(lastType) && /(enum|set)$/.test(text))
		)) {
			el.value = '';
		}
		if (lastType == 'timestamp' && el.name == name + '[has_default]' && /timestamp/i.test(formField(type.form, name + '[default]').value)) {
			el.checked = false;
		}
		if (el.name == name + '[collation]') {
			el.className = (/(char|text|enum|set)$/.test(text) ? '' : 'hidden');
		}
		if (el.name == name + '[unsigned]') {
			el.className = (/(int|float|double|decimal)$/.test(text) ? '' : 'hidden');
		}
		if (el.name == name + '[on_delete]') {
			el.className = (/`/.test(text) ? '' : 'hidden');
		}
	}
}

/** Edit enum or set
* @param HTMLInputElement
*/
function editingLengthFocus(field) {
	var td = field.parentNode;
	if (/(enum|set)$/.test(selectValue(td.previousSibling.firstChild))) {
		var edit = document.getElementById('enum-edit');
		var val = field.value;
		edit.value = (/^'.+','.+'$/.test(val) ? val.substr(1, val.length - 2).replace(/','/g, "\n").replace(/''/g, "'") : val);
		td.appendChild(edit);
		field.style.display = 'none';
		edit.style.display = 'inline';
		edit.focus();
	}
}

/** Finish editing of enum or set
* @param HTMLTextAreaElement
*/
function editingLengthBlur(edit) {
	var field = edit.parentNode.firstChild;
	var val = edit.value;
	field.value = (/\n/.test(val) ? "'" + val.replace(/\n+$/, '').replace(/'/g, "''").replace(/\n/g, "','") + "'" : val);
	field.style.display = 'inline';
	edit.style.display = 'none';
}

/** Show or hide selected table column
* @param boolean
* @param number
*/
function columnShow(checked, column) {
	var trs = document.getElementById('edit-fields').getElementsByTagName('tr');
	for (var i=0; i < trs.length; i++) {
		trs[i].getElementsByTagName('td')[column].className = (checked ? '' : 'hidden');
	}
}

/** Display partition options
* @param HTMLSelectElement
*/
function partitionByChange(el) {
	var partitionTable = /RANGE|LIST/.test(selectValue(el));
	el.form['partitions'].className = (partitionTable || !el.selectedIndex ? 'hidden' : '');
	document.getElementById('partition-table').className = (partitionTable ? '' : 'hidden');
}

/** Add next partition row
* @param HTMLInputElement
*/
function partitionNameChange(el) {
	var row = parentTag(el, 'tr').cloneNode(true);
	row.firstChild.firstChild.value = '';
	parentTag(el, 'table').appendChild(row);
	el.onchange = function () {};
}



/** Add row for foreign key
* @param HTMLSelectElement
*/
function foreignAddRow(field) {
	field.onchange = function () { };
	var row = parentTag(field, 'tr').cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/\]/, '1$&');
		selects[i].selectedIndex = 0;
	}
	parentTag(field, 'table').appendChild(row);
}



/** Add row for indexes
* @param HTMLSelectElement
*/
function indexesAddRow(field) {
	field.onchange = function () { };
	var row = parentTag(field, 'tr').cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/indexes\[\d+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = row.getElementsByTagName('input');
	for (var i=0; i < inputs.length; i++) {
		inputs[i].name = inputs[i].name.replace(/indexes\[\d+/, '$&1');
		inputs[i].value = '';
	}
	parentTag(field, 'table').appendChild(row);
}

/** Change column in index
* @param HTMLSelectElement
* @param string name prefix
*/
function indexesChangeColumn(field, prefix) {
	var columns = parentTag(field, 'td').getElementsByTagName('select');
	var names = [];
	for (var i=0; i < columns.length; i++) {
		var value = selectValue(columns[i]);
		if (value) {
			names.push(value);
		}
	}
	field.form[field.name.replace(/\].*/, '][name]')].value = prefix + names.join('_');
}

/** Add column for index
* @param HTMLSelectElement
* @param string name prefix
*/
function indexesAddColumn(field, prefix) {
	field.onchange = function () {
		indexesChangeColumn(field, prefix);
	};
	var select = field.form[field.name.replace(/\].*/, '][type]')];
	if (!select.selectedIndex) {
		select.selectedIndex = 3;
		select.onchange();
	}
	var column = field.parentNode.cloneNode(true);
	select = column.getElementsByTagName('select')[0];
	select.name = select.name.replace(/\]\[\d+/, '$&1');
	select.selectedIndex = 0;
	var input = column.getElementsByTagName('input')[0];
	input.name = input.name.replace(/\]\[\d+/, '$&1');
	input.value = '';
	parentTag(field, 'td').appendChild(column);
	field.onchange();
}



var that, x, y; // em and tablePos defined in schema.inc.php

/** Get mouse position
* @param HTMLElement
* @param MouseEvent
*/
function schemaMousedown(el, event) {
	if ((event.which ? event.which : event.button) == 1) {
		that = el;
		x = event.clientX - el.offsetLeft;
		y = event.clientY - el.offsetTop;
	}
}

/** Move object
* @param MouseEvent
*/
function schemaMousemove(ev) {
	if (that !== undefined) {
		ev = ev || event;
		var left = (ev.clientX - x) / em;
		var top = (ev.clientY - y) / em;
		var divs = that.getElementsByTagName('div');
		var lineSet = { };
		for (var i=0; i < divs.length; i++) {
			if (divs[i].className == 'references') {
				var div2 = document.getElementById((/^refs/.test(divs[i].id) ? 'refd' : 'refs') + divs[i].id.substr(4));
				var ref = (tablePos[divs[i].title] ? tablePos[divs[i].title] : [ div2.parentNode.offsetTop / em, 0 ]);
				var left1 = -1;
				var id = divs[i].id.replace(/^ref.(.+)-.+/, '$1');
				if (divs[i].parentNode != div2.parentNode) {
					left1 = Math.min(0, ref[1] - left) - 1;
					divs[i].style.left = left1 + 'em';
					divs[i].getElementsByTagName('div')[0].style.width = -left1 + 'em';
					var left2 = Math.min(0, left - ref[1]) - 1;
					div2.style.left = left2 + 'em';
					div2.getElementsByTagName('div')[0].style.width = -left2 + 'em';
				}
				if (!lineSet[id]) {
					var line = document.getElementById(divs[i].id.replace(/^....(.+)-.+$/, 'refl$1'));
					var top1 = top + divs[i].offsetTop / em;
					var top2 = top + div2.offsetTop / em;
					if (divs[i].parentNode != div2.parentNode) {
						top2 += ref[0] - top;
						line.getElementsByTagName('div')[0].style.height = Math.abs(top1 - top2) + 'em';
					}
					line.style.left = (left + left1) + 'em';
					line.style.top = Math.min(top1, top2) + 'em';
					lineSet[id] = true;
				}
			}
		}
		that.style.left = left + 'em';
		that.style.top = top + 'em';
	}
}

/** Finish move
* @param MouseEvent
* @param string
*/
function schemaMouseup(ev, db) {
	if (that !== undefined) {
		ev = ev || event;
		tablePos[that.firstChild.firstChild.firstChild.data] = [ (ev.clientY - y) / em, (ev.clientX - x) / em ];
		that = undefined;
		var s = '';
		for (var key in tablePos) {
			s += '_' + key + ':' + Math.round(tablePos[key][0] * 10000) / 10000 + 'x' + Math.round(tablePos[key][1] * 10000) / 10000;
		}
		s = encodeURIComponent(s.substr(1));
		var link = document.getElementById('schema-link');
		link.href = link.href.replace(/[^=]+$/, '') + s;
		cookie('adminer_schema-' + db + '=' + s, 30); //! special chars in db
	}
}
