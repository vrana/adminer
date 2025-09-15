'use strict'; // Adminer specific functions

let autocompleter; // set in adminer.inc.php

/** Load syntax highlighting
* @param string first three characters of database system version
* @param [string]
*/
function syntaxHighlighting(version, vendor) {
	addEventListener('DOMContentLoaded', () => {
		if (window.jush) {
			jush.create_links = 'target="_blank" rel="noreferrer noopener"';
			if (version) {
				for (let key in jush.urls) {
					let obj = jush.urls;
					if (typeof obj[key] != 'string') {
						obj = obj[key];
						key = 0;
						if (vendor == 'maria') {
							for (let i = 1; i < obj.length; i++) {
								obj[i] = obj[i]
									.replace('.html', '/')
									.replace('-type-syntax', '-data-types')
									.replace(/numeric-(data-types)/, '$1-$&')
									.replace(/replication-options-(master|binary-log)\//, 'replication-and-binary-log-system-variables/')
									.replace('server-options/', 'server-system-variables/')
									.replace('innodb-parameters/', 'innodb-system-variables/')
									.replace(/#(statvar|sysvar|option_mysqld)_(.*)/, '#$2')
									.replace(/#sysvar_(.*)/, '#$1')
								;
							}
						}
					}

					obj[key] = (vendor == 'maria' ? obj[key].replace('dev.mysql.com/doc/mysql', 'mariadb.com/kb') : obj[key]) // MariaDB
						.replace('/doc/mysql', '/doc/refman/' + version) // MySQL
					;
					if (vendor != 'cockroach') {
						obj[key] = obj[key].replace('/docs/current', '/docs/' + version); // PostgreSQL
					}
				}
			}
			if (window.jushLinks) {
				jush.custom_links = jushLinks;
			}
			jush.highlight_tag('code', 0);
			adminerHighlighter = els => jush.highlight_tag(els, 0);
			for (const tag of qsa('textarea')) {
				if (/(^|\s)jush-/.test(tag.className)) {
					const pre = jush.textarea(tag, autocompleter);
					if (pre) {
						setupSubmitHighlightInput(pre);
						tag.onchange = () => {
							pre.textContent = tag.value;
							pre.oninput();
						};
					}
				}
			}
		}
	});
}

/** Get value of dynamically created form field
* @param HTMLFormElement
* @param string
* @return HTMLElement
*/
function formField(form, name) {
	// required in IE < 8, form.elements[name] doesn't work
	for (let i=0; i < form.length; i++) {
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
	} catch (e) { // empty
	}
}

/** Install toggle handler
* @param [HTMLElement]
*/
function messagesPrint(parent) {
	for (const el of qsa('.toggle', parent)) {
		el.onclick = partial(toggle, el.getAttribute('href').substr(1));
	}
	for (const el of qsa('.copy', parent)) {
		el.onclick = () => {
			navigator.clipboard.writeText(qs('code', el.parentElement).innerText).then(() => el.textContent = '✓');
			setTimeout(() => el.textContent = '🗐', 1000);
			return false;
		};
	}
}



/** Hide or show some login rows for selected driver
* @param HTMLSelectElement
*/
function loginDriver(driver) {
	const trs = parentTag(driver, 'table').rows;
	const disabled = /sqlite/.test(selectValue(driver));
	alterClass(trs[1], 'hidden', disabled);	// 1 - row with server
	trs[1].getElementsByTagName('input')[0].disabled = disabled;
}



let dbCtrl;
const dbPrevious = {};

/** Check if database should be opened to a new window
* @param MouseEvent
* @this HTMLSelectElement
*/
function dbMouseDown(event) {
	// Firefox: mouse-down event does not contain pressed key information for OPTION.
	// Chrome: mouse-down event has inherited key information from SELECT.
	// So we ignore the event for OPTION to work Ctrl+click correctly everywhere.
	if (event.target.tagName == "OPTION") {
		return;
	}

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
	const form = this.form;
	const ok = (() => {
		for (const input of qsa('input', form)) {
			if (input.value && /^fulltext/.test(input.name)) {
				return true;
			}
		}
		let ok = form.limit.value;
		let group = false;
		const columns = {};
		for (const select of qsa('select', form)) {
			const col = selectValue(select);
			let match = /^(where.+)col]/.exec(select.name);
			if (match) {
				const op = selectValue(form[match[1] + 'op]']);
				const val = form[match[1] + 'val]'].value;
				if (col in indexColumns && (!/LIKE|REGEXP/.test(op) || (op == 'LIKE' && val.charAt(0) != '%'))) {
					return true;
				} else if (col || val) {
					ok = false;
				}
			}
			if ((match = /^(columns.+)fun]/.exec(select.name))) {
				if (/^(avg|count|count distinct|group_concat|max|min|sum)$/.test(col)) {
					group = true;
				}
				const val = selectValue(form[match[1] + 'col]']);
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
			for (const col in columns) {
				if (!(col in indexColumns)) {
					ok = false;
				}
			}
		}
		return ok;
	})();
	setHtml('noindex', (ok ? '' : '!'));
}



let added = '.', rowCount;

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
	for (const el of qsa('[name$="[field]"]')) {
		el.oninput = function () {
			editingNameChange.call(this);
			if (!this.defaultValue) {
				editingAddRow.call(this);
			}
		};
	}
	for (const el of qsa('[name$="[length]"]')) {
		mixin(el, {onfocus: editingLengthFocus, oninput: editingLengthChange});
	}
	for (const el of qsa('[name$="[type]"]')) {
		mixin(el, {
			onfocus: function () {
				lastType = selectValue(this);
			},
			onchange: editingTypeChange,
			onmouseover: function (event) {
				helpMouseover.call(this, event, event.target.value, 1);
			},
			onmouseout: helpMouseout
		});
	}
}

/** Handle clicks on fields editing
* @param MouseEvent
* @return boolean false to cancel action
*/
function editingClick(event) {
	let el = parentTag(event.target, 'button');
	if (el) {
		const name = el.name;
		if (/^add\[/.test(name)) {
			editingAddRow.call(el, 1);
		} else if (/^up\[/.test(name)) {
			editingMoveRow.call(el, 1);
		} else if (/^down\[/.test(name)) {
			editingMoveRow.call(el);
		} else if (/^drop_col\[/.test(name)) {
			editingRemoveRow.call(el, 'fields$1[field]');
		}
		return false;
	}
	el = event.target;
	if (!isTag(el, 'input')) {
		el = parentTag(el, 'label');
		el = el && qs('input', el);
	}
	if (el) {
		const name = el.name;
		if (name == 'auto_increment_col') {
			const field = el.form['fields[' + el.value + '][field]'];
			if (!field.value) {
				field.value = 'id';
				field.oninput();
			}
		}
	}
}

/** Handle input on fields editing
* @param InputEvent
*/
function editingInput(event) {
	const el = event.target;
	if (/\[default]$/.test(el.name)) {
		 el.previousElementSibling.checked = true;
		 el.previousElementSibling.selectedIndex = Math.max(el.previousElementSibling.selectedIndex, 1);
	}
}

/** Detect foreign key
* @this HTMLInputElement
*/
function editingNameChange() {
	const name = this.name.substr(0, this.name.length - 7);
	const type = formField(this.form, name + '[type]');
	const opts = type.options;
	let candidate; // don't select anything with ambiguous match (like column `id`)
	const val = this.value;
	for (let i = opts.length; i--; ) {
		const match = /(.+)`(.+)/.exec(opts[i].value);
		if (!match) { // common type
			if (candidate && i == opts.length - 2 && val == opts[candidate].value.replace(/.+`/, '') && name == 'fields[1]') { // single target table, link to column, first field - probably `id`
				return;
			}
			break;
		}
		const base = match[1];
		const column = match[2];
		for (const table of [ base, base.replace(/s$/, ''), base.replace(/es$/, '') ]) {
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
	const match = /(\d+)(\.\d+)?/.exec(this.name);
	const x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	const row = parentTag(this, 'tr');
	const row2 = cloneNode(row);
	let tags = qsa('select, input, button', row);
	let tags2 = qsa('select, input, button', row2);
	for (let i=0; i < tags.length; i++) {
		tags2[i].name = tags[i].name.replace(/[0-9.]+/, x);
		tags2[i].selectedIndex = (/\[(generated)/.test(tags[i].name) ? 0 : tags[i].selectedIndex);
	}
	tags = qsa('input', row);
	tags2 = qsa('input', row2);
	const input = tags2[0]; // IE loose tags2 after insertBefore()
	for (let i=0; i < tags.length; i++) {
		if (tags[i].name == 'auto_increment_col') {
			tags2[i].value = x;
			tags2[i].checked = false;
		}
		if (/\[(orig|field|comment|default)/.test(tags[i].name)) {
			tags2[i].value = '';
		}
		if (/\[(generated)/.test(tags[i].name)) {
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
	const field = formField(this.form, this.name.replace(/[^[]+(.+)/, name));
	field.remove();
	parentTag(this, 'tr').style.display = 'none';
	return false;
}

/** Move table row for field
* @param [boolean]
* @return boolean false for success
* @this HTMLInputElement
*/
function editingMoveRow(up){
	const row = parentTag(this, 'tr');
	if (!('nextElementSibling' in row)) {
		return true;
	}
	row.parentNode.insertBefore(row, up
		? row.previousElementSibling
		: row.nextElementSibling ? row.nextElementSibling.nextElementSibling : row.parentNode.firstChild);
	return false;
}

let lastType = '';

/** Clear length and hide collation or unsigned
* @this HTMLSelectElement
*/
function editingTypeChange() {
	const type = this;
	const name = type.name.substr(0, type.name.length - 6);
	const text = selectValue(type);
	for (const el of type.form.elements) {
		if (el.name == name + '[length]') {
			if (!(
				(/(char|binary)$/.test(lastType) && /(char|binary)$/.test(text))
				|| (/(enum|set)$/.test(lastType) && /(enum|set)$/.test(text))
			)) {
				el.value = '';
			}
			el.oninput.apply(el);
		}
		if (lastType == 'timestamp' && el.name == name + '[generated]' && /timestamp/i.test(formField(type.form, name + '[default]').value)) {
			el.checked = false;
			el.selectedIndex = 0;
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
	const td = this.parentNode;
	if (/^(enum|set)$/.test(selectValue(td.previousSibling.firstChild))) {
		const edit = qs('#enum-edit');
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
	const re = /(^|,)\s*'(([^\\']|\\.|'')*)'\s*/g;
	const result = [];
	let offset = 0;
	let match;
	while ((match = re.exec(s))) {
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
	const field = this.parentNode.firstChild;
	const val = this.value;
	field.value = (/^'[^\n]+'$/.test(val) ? val : val && "'" + val.replace(/\n+$/, '').replace(/'/g, "''").replace(/\\/g, '\\\\').replace(/\n/g, "','") + "'");
	field.style.display = 'inline';
	this.style.display = 'none';
}

/** Show or hide selected table column
* @param boolean
* @param number
*/
function columnShow(checked, column) {
	for (const tr of qsa('tr', qs('#edit-fields'))) {
		alterClass(qsa('td', tr)[column], 'hidden', !checked);
	}
}

/** Show or hide index column options
* @param boolean
*/
function indexOptionsShow(checked) {
	for (const option of qsa('.idxopts')) {
		alterClass(option, 'hidden', !checked);
	}
}

/** Display partition options
* @this HTMLSelectElement
*/
function partitionByChange() {
	const partitionTable = /RANGE|LIST/.test(selectValue(this));
	alterClass(this.form['partitions'], 'hidden', partitionTable || !this.selectedIndex);
	alterClass(qs('#partition-table'), 'hidden', !partitionTable);
	helpClose();
}

/** Add next partition row
* @this HTMLInputElement
*/
function partitionNameChange() {
	const row = cloneNode(parentTag(this, 'tr'));
	row.firstChild.firstChild.value = '';
	parentTag(this, 'table').appendChild(row);
	this.oninput = () => { };
}

/** Show or hide comment fields
* @param HTMLInputElement
* @param [boolean] whether to focus Comment if checked
*/
function editingCommentsClick(el, focus) {
	const comment = el.form['Comment'];
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
	let el = parentTag(event.target, 'label');
	if (el) {
		el = qs('input', el);
		const match = /(.+)\[]$/.exec(el.name);
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
	const row = cloneNode(parentTag(this, 'tr'));
	this.onchange = () => { };
	for (const select of qsa('select', row)) {
		select.name = select.name.replace(/\d+]/, '1$&');
		select.selectedIndex = 0;
	}
	parentTag(this, 'table').appendChild(row);
}



/** Add row for indexes
* @this HTMLSelectElement
*/
function indexesAddRow() {
	const row = cloneNode(parentTag(this, 'tr'));
	this.onchange = () => { };
	for (const select of qsa('select', row)) {
		select.name = select.name.replace(/indexes\[\d+/, '$&1');
		select.selectedIndex = 0;
	}
	for (const input of qsa('input', row)) {
		input.name = input.name.replace(/indexes\[\d+/, '$&1');
		input.value = '';
	}
	parentTag(this, 'table').appendChild(row);
}

/** Change column in index
* @param string name prefix
* @this HTMLSelectElement
*/
function indexesChangeColumn(prefix) {
	const names = [];
	for (const tag in { 'select': 1, 'input': 1 }) {
		for (const column of qsa(tag, parentTag(this, 'td'))) {
			if (/\[columns]/.test(column.name)) {
				const value = selectValue(column);
				if (value) {
					names.push(value);
				}
			}
		}
	}
	this.form[this.name.replace(/].*/, '][name]')].value = prefix + names.join('_');
}

/** Add column for index
* @param string name prefix
* @this HTMLSelectElement
*/
function indexesAddColumn(prefix) {
	const field = this;
	const select = field.form[field.name.replace(/].*/, '][type]')];
	if (!select.selectedIndex) {
		while (selectValue(select) != "INDEX" && select.selectedIndex < select.options.length) {
			select.selectedIndex++;
		}
		select.onchange();
	}
	const column = cloneNode(field.parentNode);
	for (const select of qsa('select', column)) {
		select.name = select.name.replace(/]\[\d+/, '$&1');
		select.selectedIndex = 0;
	}
	field.onchange = partial(indexesChangeColumn, prefix);
	for (const input of qsa('input', column)) {
		input.name = input.name.replace(/]\[\d+/, '$&1');
		if (input.type != 'checkbox') {
			input.value = '';
		}
	}
	parentTag(field, 'td').appendChild(column);
	field.onchange();
}



/** Update the form action
* @param HTMLFormElement
* @param string
*/
function sqlSubmit(form, root) {
	const action = root
		+ '&sql=' + encodeURIComponent(form['query'].value)
		+ (form['limit'].value ? '&limit=' + +form['limit'].value : '')
		+ (form['error_stops'].checked ? '&error_stops=1' : '')
		+ (form['only_errors'].checked ? '&only_errors=1' : '')
	;
	if ((document.location.origin + document.location.pathname + action).length < 2000) { // reasonable minimum is 2048
		form.action = action;
	}
}

/** Check if PHP can handle the uploaded files
* @param Event
* @param number
* @param string
* @param number
* @param string
*/
function fileChange(event, count, countMessage, size, sizeMessage) {
	if (event.target.files.length > count) {
		alert(countMessage);
	} else if (Array.from(event.target.files).reduce((sum, file) => sum + file.size, 0) > size) {
		alert(sizeMessage);
	}
}



/** Handle changing trigger time or event
* @param RegExp
* @param string
* @param HTMLFormElement
*/
function triggerChange(tableRe, table, form) {
	const formEvent = selectValue(form['Event']);
	if (tableRe.test(form['Trigger'].value)) {
		form['Trigger'].value = table + '_' + (selectValue(form['Timing']).charAt(0) + formEvent.charAt(0)).toLowerCase();
	}
	alterClass(form['Of'], 'hidden', !/ OF/.test(formEvent));
}



let that, x, y; // em and tablePos defined in schema.inc.php

/** Get mouse position
* @param MouseEvent
* @this HTMLElement
*/
function schemaMousedown(event) {
	if ((event.which || event.button) == 1) {
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
		const left = (event.clientX - x) / em;
		const top = (event.clientY - y) / em;
		const lineSet = { };
		for (const div of qsa('div', that)) {
			if (div.classList.contains('references')) {
				const div2 = qs('[id="' + (/^refs/.test(div.id) ? 'refd' : 'refs') + div.id.substr(4) + '"]');
				const ref = (tablePos[div.title] || [ div2.parentNode.offsetTop / em, 0 ]);
				let left1 = -1;
				const id = div.id.replace(/^ref.(.+)-.+/, '$1');
				if (div.parentNode != div2.parentNode) {
					left1 = Math.min(0, ref[1] - left) - 1;
					div.style.left = left1 + 'em';
					div.querySelector('div').style.width = -left1 + 'em';
					const left2 = Math.min(0, left - ref[1]) - 1;
					div2.style.left = left2 + 'em';
					div2.querySelector('div').style.width = -left2 + 'em';
				}
				if (!lineSet[id]) {
					const line = qs('[id="' + div.id.replace(/^....(.+)-.+$/, 'refl$1') + '"]');
					const top1 = top + div.offsetTop / em;
					let top2 = top + div2.offsetTop / em;
					if (div.parentNode != div2.parentNode) {
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
		let s = '';
		for (const key in tablePos) {
			s += '_' + key + ':' + Math.round(tablePos[key][0]) + 'x' + Math.round(tablePos[key][1]);
		}
		s = encodeURIComponent(s.substr(1));
		const link = qs('#schema-link');
		link.href = link.href.replace(/[^=]+$/, '') + s;
		cookie('adminer_schema-' + db + '=' + s, 30); //! special chars in db
	}
}



let helpOpen, helpIgnore; // when mouse outs <option> then it mouse overs border of <select> - ignore it

/** Display help
* @param MouseEvent
* @param string
* @param bool display on left side (otherwise on top)
* @this HTMLElement
*/
function helpMouseover(event, text, side) {
	const target = event.target;
	if (!text) {
		helpClose();
	} else if (window.jush && (!helpIgnore || this != target)) {
		helpOpen = 1;
		const help = qs('#help');
		help.innerHTML = text;
		jush.highlight_tag([ help ]);
		alterClass(help, 'hidden');
		const rect = target.getBoundingClientRect();
		const body = document.documentElement;
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
	helpIgnore = (this != event.target);
	setTimeout(() => {
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
