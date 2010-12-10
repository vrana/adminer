// Adminer specific functions

/** Load syntax highlighting
* @param string first three characters of database system version
* @param string 'http' or 'https' - used after compilation
*/
function bodyLoad(version, protocol) {
	var jushRoot = '../externals/jush/';
	var script = document.createElement('script');
	script.src = jushRoot + 'jush.js';
	script.onload = function () {
		if (window.jush) { // IE runs in case of an error too
			jush.create_links = ' target="_blank"';
			jush.urls.sql[0] = 'http://dev.mysql.com/doc/refman/' + version + '/en/$key';
			jush.urls.sql_sqlset = jush.urls.sql[0];
			jush.urls.sqlset[0] = jush.urls.sql[0];
			jush.urls.sqlstatus[0] = jush.urls.sql[0];
			jush.urls.pgsql[0] = 'http://www.postgresql.org/docs/' + version + '/static/$key';
			jush.urls.pgsql_pgsqlset = jush.urls.pgsql[0];
			jush.urls.pgsqlset[0] = 'http://www.postgresql.org/docs/' + version + '/static/runtime-config-$key.html#GUC-$1';
			jush.style(jushRoot + 'jush.css');
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



var added = '.', rowCount;

/** Escape string to use in regular expression
* @param string
* @return string
*/
function reEscape(s) {
	return s.replace(/[\[\]\\^$*+?.(){|}]/, '\\$&');
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
	var table = reEscape(field.value);
	var column = '';
	var match;
	if ((match = /(.+)_(.+)/.exec(table)) || (match = /(.*[a-z])([A-Z].*)/.exec(table))) { // limited to single word columns
		table = match[1];
		column = match[2];
	}
	var plural = '(?:e?s)?';
	var tabCol = table + plural + '_?' + column;
	var re = new RegExp('(^' + idfEscape(table + plural) + '`' + idfEscape(column) + '$' // table_column
		+ '|^' + idfEscape(tabCol) + '`' // table
		+ '|^' + idfEscape(column + plural) + '`' + idfEscape(table) + '$' // column_table
		+ ')|`' + idfEscape(tabCol) + '$' // column
	, 'i');
	var candidate; // don't select anything with ambiguous match (like column `id`)
	for (var i = opts.length; i--; ) {
		if (!/`/.test(opts[i].value)) { // common type
			if (i == opts.length - 2 && candidate && !match[1] && name == 'fields[1]') { // single target table, link to column, first field - probably `id`
				return false;
			}
			break;
		}
		if (match = re.exec(opts[i].value)) {
			if (candidate) {
				return false;
			}
			candidate = i;
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
	var row = button.parentNode.parentNode;
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
	button.parentNode.parentNode.style.display = 'none';
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
	var row = el.parentNode.parentNode.cloneNode(true);
	row.firstChild.firstChild.value = '';
	el.parentNode.parentNode.parentNode.appendChild(row);
	el.onchange = function () {};
}



/** Add row for foreign key
* @param HTMLSelectElement
*/
function foreignAddRow(field) {
	field.onchange = function () { };
	var row = field.parentNode.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/\]/, '1$&');
		selects[i].selectedIndex = 0;
	}
	field.parentNode.parentNode.parentNode.appendChild(row);
}



/** Add row for indexes
* @param HTMLSelectElement
*/
function indexesAddRow(field) {
	field.onchange = function () { };
	var row = field.parentNode.parentNode.cloneNode(true);
	var spans = row.getElementsByTagName('span');
	for (var i=0; i < spans.length - 1; i++) {
		row.removeChild(spans[i]);
	}
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/indexes\[\d+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var input = row.getElementsByTagName('input')[0];
	input.name = input.name.replace(/indexes\[\d+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.parentNode.appendChild(row);
}

/** Add column for index
* @param HTMLSelectElement
*/
function indexesAddColumn(field) {
	field.onchange = function () { };
	var column = field.parentNode.cloneNode(true);
	var select = column.getElementsByTagName('select')[0];
	select.name = select.name.replace(/\]\[\d+/, '$&1');
	select.selectedIndex = 0;
	var input = column.getElementsByTagName('input')[0];
	input.name = input.name.replace(/\]\[\d+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.appendChild(column);
	select = field.form[field.name.replace(/\].*/, '][type]')];
	if (!select.selectedIndex) {
		select.selectedIndex = 3;
	}
}



var that, x, y, em, tablePos;

/** Get mouse position
* @param HTMLElement
* @param MouseEvent
*/
function schemaMousedown(el, event) {
	that = el;
	x = event.clientX - el.offsetLeft;
	y = event.clientY - el.offsetTop;
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
				var div2 = document.getElementById((divs[i].id.substr(0, 4) == 'refs' ? 'refd' : 'refs') + divs[i].id.substr(4));
				var ref = (tablePos[divs[i].title] ? tablePos[divs[i].title] : [ div2.parentNode.offsetTop / em, 0 ]);
				var left1 = -1;
				var isTop = true;
				var id = divs[i].id.replace(/^ref.(.+)-.+/, '$1');
				if (divs[i].parentNode != div2.parentNode) {
					left1 = Math.min(0, ref[1] - left) - 1;
					divs[i].style.left = left1 + 'em';
					divs[i].getElementsByTagName('div')[0].style.width = -left1 + 'em';
					var left2 = Math.min(0, left - ref[1]) - 1;
					div2.style.left = left2 + 'em';
					div2.getElementsByTagName('div')[0].style.width = -left2 + 'em';
					isTop = (div2.offsetTop + ref[0] * em > divs[i].offsetTop + top * em);
				}
				if (!lineSet[id]) {
					var line = document.getElementById(divs[i].id.replace(/^....(.+)-\d+$/, 'refl$1'));
					var shift = ev.clientY - y - that.offsetTop;
					line.style.left = (left + left1) + 'em';
					if (isTop) {
						line.style.top = (line.offsetTop + shift) / em + 'em';
					}
					if (divs[i].parentNode != div2.parentNode) {
						line = line.getElementsByTagName('div')[0];
						line.style.height = (line.offsetHeight + (isTop ? -1 : 1) * shift) / em + 'em';
					}
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
*/
function schemaMouseup(ev) {
	if (that !== undefined) {
		ev = ev || event;
		tablePos[that.firstChild.firstChild.firstChild.data] = [ (ev.clientY - y) / em, (ev.clientX - x) / em ];
		that = undefined;
		var s = '';
		for (var key in tablePos) {
			s += '_' + key + ':' + Math.round(tablePos[key][0] * 10000) / 10000 + 'x' + Math.round(tablePos[key][1] * 10000) / 10000;
		}
		cookie('adminer_schema=' + encodeURIComponent(s.substr(1)), 30, '; path="' + location.pathname + location.search + '"');
	}
}
