// Adminer specific functions

/** Load syntax highlighting
* @param string first three characters of database system version
* @param [boolean]
*/
function bodyLoad(version, maria) {
	if (window.jush) {
		jush.create_links = ' target="_blank" rel="noreferrer noopener"';
		if (version) {
			for (var key in jush.urls) {
				var obj = jush.urls;
				if (typeof obj[key] != 'string') {
					obj = obj[key];
					key = 0;
					if (maria) {
						for (var i = 1; i < obj.length; i++) {
							obj[i] = obj[i]
								.replace(/\.html/, '/')
								.replace(/-type-syntax/, '-data-types')
								.replace(/numeric-(data-types)/, '$1-$&')
								.replace(/#statvar_.*/, '#$$1')
							;
						}
					}
				}
				obj[key] = (maria ? obj[key].replace(/dev\.mysql\.com\/doc\/mysql\/en\//, 'mariadb.com/kb/en/library/') : obj[key]) // MariaDB
					.replace(/\/doc\/mysql/, '/doc/refman/' + version) // MySQL
					.replace(/\/docs\/current/, '/docs/' + version) // PostgreSQL
				;
			}
		}
		if (window.jushLinks) {
			jush.custom_links = jushLinks;
		}
		jush.highlight_tag('code', 0);
		var tags = qsa('textarea');
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

/** Install toggle handler
* @param [HTMLElement]
*/
function messagesPrint(el) {
	var els = qsa('.toggle', el);
	for (var i = 0; i < els.length; i++) {
		els[i].onclick = partial(toggle, els[i].getAttribute('href').substr(1));
	}
}



/** Hide or show some login rows for selected driver	
* @param HTMLSelectElement	
*/	
function loginDriver(driver) {	
	var trs = parentTag(driver, 'table').rows;	
	var disabled = /sqlite/.test(selectValue(driver));	
	alterClass(trs[1], 'hidden', disabled);	// 1 - row with server
	trs[1].getElementsByTagName('input')[0].disabled = disabled;	
}



var dbCtrl;
var dbPrevious = {};

/** Check if database should be opened to a new window
* @param MouseEvent
* @this HTMLSelectElement
*/
function dbMouseDown(event) {
	dbCtrl = isCtrl(event);
	if (dbPrevious[this.name] == undefined) {
		dbPrevious[this.name] = this.value;
	}
}

/** Load database after selecting it
* @this HTMLSelectElement
*/
function dbChange() {
	if (dbCtrl) {
		this.form.target = '_blank';
	}
	this.form.submit();
	this.form.target = '';
	if (dbCtrl && dbPrevious[this.name] != undefined) {
		this.value = dbPrevious[this.name];
		dbPrevious[this.name] = undefined;
	}
}



/** Check whether the query will be executed with index
* @this HTMLElement
*/
function selectFieldChange() {
	var form = this.form;
	var ok = (function () {
		var inputs = qsa('input', form);
		for (var i=0; i < inputs.length; i++) {
			if (inputs[i].value && /^fulltext/.test(inputs[i].name)) {
				return true;
			}
		}
		var ok = form.limit.value;
		var selects = qsa('select', form);
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



/** Set up event handlers for edit_fields().
*/
function editFields() {
	var els = qsa('[name$="[field]"]');
	for (var i = 0; i < els.length; i++) {
		els[i].oninput = function () {
			editingNameChange.call(this);
			if (!this.defaultValue) {
				editingAddRow.call(this);
			}
		}
	}
	els = qsa('[name$="[length]"]');
	for (var i = 0; i < els.length; i++) {
		mixin(els[i], {onfocus: editingLengthFocus, oninput: editingLengthChange});
	}
	els = qsa('[name$="[type]"]');
	for (var i = 0; i < els.length; i++) {
		mixin(els[i], {
			onfocus: function () { lastType = selectValue(this); },
			onchange: editingTypeChange,
			onmouseover: function (event) { helpMouseover.call(this, event, getTarget(event).value, 1) },
			onmouseout: helpMouseout
		});
	}
}

/** Handle clicks on fields editing
* @param MouseEvent
* @return boolean false to cancel action
*/
function editingClick(event) {
	var el = getTarget(event);
	if (!isTag(el, 'input')) {
		el = parentTag(el, 'label');
		el = el && qs('input', el);
	}
	if (el) {
		var name = el.name;
		if (/^add\[/.test(name)) {
			editingAddRow.call(el, 1);
		} else if (/^up\[/.test(name)) {
			editingMoveRow.call(el, 1);
		} else if (/^down\[/.test(name)) {
			editingMoveRow.call(el);
		} else if (/^drop_col\[/.test(name)) {
			editingRemoveRow.call(el, 'fields\$1[field]');
		} else {
			if (name == 'auto_increment_col') {
				var field = el.form['fields[' + el.value + '][field]'];
				if (!field.value) {
					field.value = 'id';
					field.oninput();
				}
			}
			return;
		}
		return false;
	}
}

/** Handle input on fields editing
* @param InputEvent
*/
function editingInput(event) {
	var el = getTarget(event);
	if (/\[default\]$/.test(el.name)) {
		 el.previousSibling.checked = true;
	}
}

/** Detect foreign key
* @this HTMLInputElement
*/
function editingNameChange() {
	var name = this.name.substr(0, this.name.length - 7);
	var type = formField(this.form, name + '[type]');
	var opts = type.options;
	var candidate; // don't select anything with ambiguous match (like column `id`)
	var val = this.value;
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
* @param [boolean]
* @return boolean false
* @this HTMLInputElement
*/
function editingAddRow(focus) {
	var match = /(\d+)(\.\d+)?/.exec(this.name);
	var x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	var row = parentTag(this, 'tr');
	var row2 = cloneNode(row);
	var tags = qsa('select', row);
	var tags2 = qsa('select', row2);
	for (var i=0; i < tags.length; i++) {
		tags2[i].name = tags[i].name.replace(/[0-9.]+/, x);
		tags2[i].selectedIndex = tags[i].selectedIndex;
	}
	tags = qsa('input', row);
	tags2 = qsa('input', row2);
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
	tags[0].oninput = editingNameChange;
	row.parentNode.insertBefore(row2, row.nextSibling);
	if (focus) {
		input.oninput = editingNameChange;
		input.focus();
	}
	added += '0';
	rowCount++;
	return false;
}

/** Remove table row for field
* @param string regular expression replacement
* @return boolean false
* @this HTMLInputElement
*/
function editingRemoveRow(name) {
	var field = formField(this.form, this.name.replace(/[^\[]+(.+)/, name));
	field.parentNode.removeChild(field);
	parentTag(this, 'tr').style.display = 'none';
	return false;
}

/** Move table row for field
* @param [boolean]
* @return boolean false for success
* @this HTMLInputElement
*/
function editingMoveRow(up){
	var row = parentTag(this, 'tr');
	if (!('nextElementSibling' in row)) {
		return true;
	}
	row.parentNode.insertBefore(row, up
		? row.previousElementSibling
		: row.nextElementSibling ? row.nextElementSibling.nextElementSibling : row.parentNode.firstChild);
	return false;
}

var lastType = '';

/** Clear length and hide collation or unsigned
* @this HTMLSelectElement
*/
function editingTypeChange() {
	var type = this;
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
			el.oninput.apply(el);
		}
		if (lastType == 'timestamp' && el.name == name + '[has_default]' && /timestamp/i.test(formField(type.form, name + '[default]').value)) {
			el.checked = false;
		}
		if (el.name == name + '[collation]') {
			alterClass(el, 'hidden', !/(char|text|enum|set)$/.test(text));
		}
		if (el.name == name + '[unsigned]') {
			alterClass(el, 'hidden', !/(^|[^o])int(?!er)|numeric|real|float|double|decimal|money/.test(text));
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
* @this HTMLInputElement
*/
function editingLengthChange() {
	alterClass(this, 'required', !this.value.length && /var(char|binary)$/.test(selectValue(this.parentNode.previousSibling.firstChild)));
}

/** Edit enum or set
* @this HTMLInputElement
*/
function editingLengthFocus() {
	var td = this.parentNode;
	if (/(enum|set)$/.test(selectValue(td.previousSibling.firstChild))) {
		var edit = qs('#enum-edit');
		edit.value = enumValues(this.value);
		td.appendChild(edit);
		this.style.display = 'none';
		edit.style.display = 'inline';
		edit.focus();
	}
}

/** Get enum values
* @param string
* @return string values separated by newlines
*/
function enumValues(s) {
	var re = /(^|,)\s*'(([^\\']|\\.|'')*)'\s*/g;
	var result = [];
	var offset = 0;
	var match;
	while (match = re.exec(s)) {
		if (offset != match.index) {
			break;
		}
		result.push(match[2].replace(/'(')|\\(.)/g, '$1$2'));
		offset += match[0].length;
	}
	return (offset == s.length ? result.join('\n') : s);
}

/** Finish editing of enum or set
* @this HTMLTextAreaElement
*/
function editingLengthBlur() {
	var field = this.parentNode.firstChild;
	var val = this.value;
	field.value = (/^'[^\n]+'$/.test(val) ? val : val && "'" + val.replace(/\n+$/, '').replace(/'/g, "''").replace(/\\/g, '\\\\').replace(/\n/g, "','") + "'");
	field.style.display = 'inline';
	this.style.display = 'none';
}

/** Show or hide selected table column
* @param boolean
* @param number
*/
function columnShow(checked, column) {
	var trs = qsa('tr', qs('#edit-fields'));
	for (var i=0; i < trs.length; i++) {
		alterClass(qsa('td', trs[i])[column], 'hidden', !checked);
	}
}

/** Display partition options
* @this HTMLSelectElement
*/
function partitionByChange() {
	var partitionTable = /RANGE|LIST/.test(selectValue(this));
	alterClass(this.form['partitions'], 'hidden', partitionTable || !this.selectedIndex);
	alterClass(qs('#partition-table'), 'hidden', !partitionTable);
	helpClose();
}

/** Add next partition row
* @this HTMLInputElement
*/
function partitionNameChange() {
	var row = cloneNode(parentTag(this, 'tr'));
	row.firstChild.firstChild.value = '';
	parentTag(this, 'table').appendChild(row);
	this.oninput = function () {};
}

/** Show or hide comment fields
* @param HTMLInputElement
* @param [boolean] whether to focus Comment if checked
*/
function editingCommentsClick(el, focus) {
	var comment = el.form['Comment'];
	columnShow(el.checked, 6);
	alterClass(comment, 'hidden', !el.checked);
	if (focus && el.checked) {
		comment.focus();
	}
}



/** Uncheck 'all' checkbox
* @param MouseEvent
* @this HTMLTableElement
*/
function dumpClick(event) {
	var el = parentTag(getTarget(event), 'label');
	if (el) {
		el = qs('input', el);
		var match = /(.+)\[\]$/.exec(el.name);
		if (match) {
			checkboxClick.call(el, event);
			formUncheck('check-' + match[1]);
		}
	}
}



/** Add row for foreign key
* @this HTMLSelectElement
*/
function foreignAddRow() {
	var row = cloneNode(parentTag(this, 'tr'));
	this.onchange = function () { };
	var selects = qsa('select', row);
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/\]/, '1$&');
		selects[i].selectedIndex = 0;
	}
	parentTag(this, 'table').appendChild(row);
}



/** Add row for indexes
* @this HTMLSelectElement
*/
function indexesAddRow() {
	var row = cloneNode(parentTag(this, 'tr'));
	this.onchange = function () { };
	var selects = qsa('select', row);
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/indexes\[\d+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = qsa('input', row);
	for (var i=0; i < inputs.length; i++) {
		inputs[i].name = inputs[i].name.replace(/indexes\[\d+/, '$&1');
		inputs[i].value = '';
	}
	parentTag(this, 'table').appendChild(row);
}

/** Change column in index
* @param string name prefix
* @this HTMLSelectElement
*/
function indexesChangeColumn(prefix) {
	var names = [];
	for (var tag in { 'select': 1, 'input': 1 }) {
		var columns = qsa(tag, parentTag(this, 'td'));
		for (var i=0; i < columns.length; i++) {
			if (/\[columns\]/.test(columns[i].name)) {
				var value = selectValue(columns[i]);
				if (value) {
					names.push(value);
				}
			}
		}
	}
	this.form[this.name.replace(/\].*/, '][name]')].value = prefix + names.join('_');
}

/** Add column for index
* @param string name prefix
* @this HTMLSelectElement
*/
function indexesAddColumn(prefix) {
	var field = this;
	var select = field.form[field.name.replace(/\].*/, '][type]')];
	if (!select.selectedIndex) {
		while (selectValue(select) != "INDEX" && select.selectedIndex < select.options.length) {
			select.selectedIndex++;
		}
		select.onchange();
	}
	var column = cloneNode(field.parentNode);
	var selects = qsa('select', column);
	for (var i = 0; i < selects.length; i++) {
		select = selects[i];
		select.name = select.name.replace(/\]\[\d+/, '$&1');
		select.selectedIndex = 0;
	}
	field.onchange = partial(indexesChangeColumn, prefix);
	var inputs = qsa('input', column);
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



/** Updates the form action
* @param HTMLFormElement
* @param string
*/
function sqlSubmit(form, root) {
	if (encodeURIComponent(form['query'].value).length < 2e3) {
		form.action = root
			+ '&sql=' + encodeURIComponent(form['query'].value)
			+ (form['limit'].value ? '&limit=' + +form['limit'].value : '')
			+ (form['error_stops'].checked ? '&error_stops=1' : '')
			+ (form['only_errors'].checked ? '&only_errors=1' : '')
		;
	}
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
	alterClass(form['Of'], 'hidden', !/ OF/.test(formEvent));
}



var that, x, y; // em and tablePos defined in schema.inc.php

/** Get mouse position
* @param MouseEvent
* @this HTMLElement
*/
function schemaMousedown(event) {
	if ((event.which ? event.which : event.button) == 1) {
		that = this;
		x = event.clientX - this.offsetLeft;
		y = event.clientY - this.offsetTop;
	}
}

/** Move object
* @param MouseEvent
*/
function schemaMousemove(event) {
	if (that !== undefined) {
		var left = (event.clientX - x) / em;
		var top = (event.clientY - y) / em;
		var divs = qsa('div', that);
		var lineSet = { };
		for (var i=0; i < divs.length; i++) {
			if (divs[i].className == 'references') {
				var div2 = qs('[id="' + (/^refs/.test(divs[i].id) ? 'refd' : 'refs') + divs[i].id.substr(4) + '"]');
				var ref = (tablePos[divs[i].title] ? tablePos[divs[i].title] : [ div2.parentNode.offsetTop / em, 0 ]);
				var left1 = -1;
				var id = divs[i].id.replace(/^ref.(.+)-.+/, '$1');
				if (divs[i].parentNode != div2.parentNode) {
					left1 = Math.min(0, ref[1] - left) - 1;
					divs[i].style.left = left1 + 'em';
					divs[i].querySelector('div').style.width = -left1 + 'em';
					var left2 = Math.min(0, left - ref[1]) - 1;
					div2.style.left = left2 + 'em';
					div2.querySelector('div').style.width = -left2 + 'em';
				}
				if (!lineSet[id]) {
					var line = qs('[id="' + divs[i].id.replace(/^....(.+)-.+$/, 'refl$1') + '"]');
					var top1 = top + divs[i].offsetTop / em;
					var top2 = top + div2.offsetTop / em;
					if (divs[i].parentNode != div2.parentNode) {
						top2 += ref[0] - top;
						line.querySelector('div').style.height = Math.abs(top1 - top2) + 'em';
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
function schemaMouseup(event, db) {
	if (that !== undefined) {
		tablePos[that.firstChild.firstChild.firstChild.data] = [ (event.clientY - y) / em, (event.clientX - x) / em ];
		that = undefined;
		var s = '';
		for (var key in tablePos) {
			s += '_' + key + ':' + Math.round(tablePos[key][0] * 10000) / 10000 + 'x' + Math.round(tablePos[key][1] * 10000) / 10000;
		}
		s = encodeURIComponent(s.substr(1));
		var link = qs('#schema-link');
		link.href = link.href.replace(/[^=]+$/, '') + s;
		cookie('adminer_schema-' + db + '=' + s, 30); //! special chars in db
	}
}



var helpOpen, helpIgnore; // when mouse outs <option> then it mouse overs border of <select> - ignore it

/** Display help
* @param MouseEvent
* @param string
* @param bool display on left side (otherwise on top)
* @this HTMLElement
*/
function helpMouseover(event, text, side) {
	var target = getTarget(event);
	if (!text) {
		helpClose();
	} else if (window.jush && (!helpIgnore || this != target)) {
		helpOpen = 1;
		var help = qs('#help');
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
* @param MouseEvent
* @this HTMLElement
*/
function helpMouseout(event) {
	helpOpen = 0;
	helpIgnore = (this != getTarget(event));
	setTimeout(function () {
		if (!helpOpen) {
			helpClose();
		}
	}, 200);
}

/** Close help
*/
function helpClose() {
	alterClass(qs('#help'), 'hidden', true);
}
