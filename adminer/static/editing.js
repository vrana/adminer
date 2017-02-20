// Adminer specific functions

/** Load syntax highlighting
* @param string first three characters of database system version
*/
function bodyLoad(version) {
	if (window.jush) {
		jush.create_links = ' target="_blank" rel="noreferrer"';
		if (version) {
			for (var key in jush.urls) {
				var obj = jush.urls;
				if (typeof obj[key] != 'string') {
					obj = obj[key];
					key = 0;
				}
				obj[key] = obj[key]
					.replace(/\/doc\/mysql/, '/doc/refman/' + version) // MySQL
					.replace(/\/docs\/current/, '/docs/' + version) // PostgreSQL
				;
			}
		}
		if (window.jushLinks) {
			jush.custom_links = jushLinks;
		}
		jush.highlight_tag('code', 0);
		var tags = document.getElementsByTagName('textarea');
		for (var i = 0; i < tags.length; i++) {
			if (/(^|\s)jush-/.test(tags[i].className)) {
				var pre = jush.textarea(tags[i]);
				if (pre) {
					setupSubmitHighlightInput(pre);
				}
			}
		}
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



var dbCtrl;
var dbPrevious = {};

/** Check if database should be opened to a new window
* @param MouseEvent
* @param HTMLSelectElement
*/
function dbMouseDown(event, el) {
	dbCtrl = isCtrl(event);
	if (dbPrevious[el.name] == undefined) {
		dbPrevious[el.name] = el.value;
	}
}

/** Load database after selecting it
* @param HTMLSelectElement
*/
function dbChange(el) {
	if (dbCtrl) {
		el.form.target = '_blank';
	}
	el.form.submit();
	el.form.target = '';
	if (dbCtrl && dbPrevious[el.name] != undefined) {
		el.value = dbPrevious[el.name];
		dbPrevious[el.name] = undefined;
	}
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
* @return boolean
*/
function editingAddRow(button, focus) {
	var match = /(\d+)(\.\d+)?/.exec(button.name);
	var x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	var row = parentTag(button, 'tr');
	var row2 = cloneNode(row);
	var tags = row.getElementsByTagName('select');
	var tags2 = row2.getElementsByTagName('select');
	for (var i=0; i < tags.length; i++) {
		tags2[i].name = tags[i].name.replace(/[0-9.]+/, x);
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
	tags[0].onkeyup = function () {
	};
	row.parentNode.insertBefore(row2, row.nextSibling);
	if (focus) {
		input.onchange = function () {
			editingNameChange(input);
		};
		input.onkeyup = function () {
		};
		input.focus();
	}
	added += '0';
	rowCount++;
	return true;
}

/** Remove table row for field
* @param HTMLInputElement
* @param string
* @return boolean
*/
function editingRemoveRow(button, name) {
	var field = formField(button.form, button.name.replace(/[^\[]+(.+)/, name));
	field.parentNode.removeChild(field);
	parentTag(button, 'tr').style.display = 'none';
	return true;
}

/** Move table row for field
* @param HTMLInputElement
* @param boolean direction to move row, true for up or false for down
* @return boolean
*/
function editingMoveRow(button, dir){
	var row = parentTag(button, 'tr');
	if (!('nextElementSibling' in row)) {
		return false;
	}
	row.parentNode.insertBefore(row, dir
		? row.previousElementSibling
		: row.nextElementSibling ? row.nextElementSibling.nextElementSibling : row.parentNode.firstChild);
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
		if (el.name == name + '[length]') {
			if (!(
				(/(char|binary)$/.test(lastType) && /(char|binary)$/.test(text))
				|| (/(enum|set)$/.test(lastType) && /(enum|set)$/.test(text))
			)) {
				el.value = '';
			}
			el.onchange.apply(el);
		}
		if (lastType == 'timestamp' && el.name == name + '[has_default]' && /timestamp/i.test(formField(type.form, name + '[default]').value)) {
			el.checked = false;
		}
		if (el.name == name + '[collation]') {
			alterClass(el, 'hidden', !/(char|text|enum|set)$/.test(text));
		}
		if (el.name == name + '[unsigned]') {
			alterClass(el, 'hidden', !/((^|[^o])int|float|double|decimal)$/.test(text));
		}
		if (el.name == name + '[on_update]') {
			alterClass(el, 'hidden', !/timestamp|datetime/.test(text)); // MySQL supports datetime since 5.6.5
		}
		if (el.name == name + '[on_delete]') {
			alterClass(el, 'hidden', !/`/.test(text));
		}
	}
	helpClose();
}

/** Mark length as required
* @param HTMLInputElement
*/
function editingLengthChange(el) {
	alterClass(el, 'required', !el.value.length && /var(char|binary)$/.test(selectValue(el.parentNode.previousSibling.firstChild)));
}

/** Edit enum or set
* @param HTMLInputElement
*/
function editingLengthFocus(field) {
	var td = field.parentNode;
	if (/(enum|set)$/.test(selectValue(td.previousSibling.firstChild))) {
		var edit = document.getElementById('enum-edit');
		var val = field.value;
		edit.value = (/^'.+'$/.test(val) ? val.substr(1, val.length - 2).replace(/','/g, "\n").replace(/''/g, "'") : val); //! doesn't handle 'a'',''b' correctly
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
	field.value = (/^'[^\n]+'$/.test(val) ? val : "'" + val.replace(/\n+$/, '').replace(/'/g, "''").replace(/\n/g, "','") + "'");
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
		alterClass(trs[i].getElementsByTagName('td')[column], 'hidden', !checked);
	}
}

/** Hide column with default values in narrow window
*/
function editingHideDefaults() {
	if (innerWidth < document.documentElement.scrollWidth) {
		document.getElementById('form')['defaults'].checked = false;
		columnShow(false, 5);
	}
}

/** Display partition options
* @param HTMLSelectElement
*/
function partitionByChange(el) {
	var partitionTable = /RANGE|LIST/.test(selectValue(el));
	alterClass(el.form['partitions'], 'hidden', partitionTable || !el.selectedIndex);
	alterClass(document.getElementById('partition-table'), 'hidden', !partitionTable);
	helpClose();
}

/** Add next partition row
* @param HTMLInputElement
*/
function partitionNameChange(el) {
	var row = cloneNode(parentTag(el, 'tr'));
	row.firstChild.firstChild.value = '';
	parentTag(el, 'table').appendChild(row);
	el.onchange = function () {};
}



/** Add row for foreign key
* @param HTMLSelectElement
*/
function foreignAddRow(field) {
	field.onchange = function () { };
	var row = cloneNode(parentTag(field, 'tr'));
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
	var row = cloneNode(parentTag(field, 'tr'));
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
	var names = [];
	for (var tag in { 'select': 1, 'input': 1 }) {
		var columns = parentTag(field, 'td').getElementsByTagName(tag);
		for (var i=0; i < columns.length; i++) {
			if (/\[columns\]/.test(columns[i].name)) {
				var value = selectValue(columns[i]);
				if (value) {
					names.push(value);
				}
			}
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
		while (selectValue(select) != "INDEX" && select.selectedIndex < select.options.length) {
			select.selectedIndex++;
		}
		select.onchange();
	}
	var column = cloneNode(field.parentNode);
	var selects = column.getElementsByTagName('select');
	for (var i = 0; i < selects.length; i++) {
		select = selects[i];
		select.name = select.name.replace(/\]\[\d+/, '$&1');
		select.selectedIndex = 0;
	}
	var inputs = column.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; i++) {
		var input = inputs[i];
		input.name = input.name.replace(/\]\[\d+/, '$&1');
		if (input.type != 'checkbox') {
			input.value = '';
		}
	}
	parentTag(field, 'td').appendChild(column);
	field.onchange();
}



/** Handle changing trigger time or event
* @param RegExp
* @param string
* @param HTMLFormElement
*/
function triggerChange(tableRe, table, form) {
	var formEvent = selectValue(form['Event']);
	if (tableRe.test(form['Trigger'].value)) {
		form['Trigger'].value = table + '_' + (selectValue(form['Timing']).charAt(0) + formEvent.charAt(0)).toLowerCase();
	}
	alterClass(form['Of'], 'hidden', formEvent != 'UPDATE OF');
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



var helpOpen, helpIgnore; // when mouse outs <option> then it mouse overs border of <select> - ignore it

/** Display help
* @param HTMLElement
* @param MouseEvent
* @param string
* @param bool display on left side (otherwise on top)
*/
function helpMouseover(el, event, text, side) {
	var target = getTarget(event);
	if (!text) {
		helpClose();
	} else if (window.jush && (!helpIgnore || el != target)) {
		helpOpen = 1;
		var help = document.getElementById('help');
		help.innerHTML = text;
		jush.highlight_tag([ help ]);
		alterClass(help, 'hidden');
		var rect = target.getBoundingClientRect();
		var body = document.documentElement;
		help.style.top = (body.scrollTop + rect.top - (side ? (help.offsetHeight - target.offsetHeight) / 2 : help.offsetHeight)) + 'px';
		help.style.left = (body.scrollLeft + rect.left - (side ? help.offsetWidth : (help.offsetWidth - target.offsetWidth) / 2)) + 'px';
	}
}

/** Close help after timeout
* @param HTMLElement
* @param MouseEvent
*/
function helpMouseout(el, event) {
	helpOpen = 0;
	helpIgnore = (el != getTarget(event));
	setTimeout(function () {
		if (!helpOpen) {
			helpClose();
		}
	}, 200);
}

/** Close help
*/
function helpClose() {
	alterClass(document.getElementById('help'), 'hidden', true);
}
