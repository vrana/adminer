'use strict'; // Adminer specific functions

// eslint-disable-next-line no-unassigned-vars
let autocompleter; // set in adminer.inc.php

/** Load syntax highlighting
* @param {string} version first three characters of database system version
* @param {string} [vendor]
* @param {string} [jushSql] the language highlighting SQL in the current driver
*/
function syntaxHighlighting(version, vendor, jushSql) {
	addEventListener('DOMContentLoaded', () => {
		if (window.jush) {
			jush.create_links = 'target="_blank" rel="noreferrer noopener"';
			if (version) {
				for (let key in jush.urls) {
					let obj = jush.urls;
					if (typeof obj[key] != 'string') {
						obj = obj[key];
						key = 0;
					}
					// MariaDB page keys are resolved by jush itself from the 'mysql-key maria-key' entries
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
					let autocomplete = autocompleter;
					if (autocomplete) {
						// it completes SQL but routineLanguage() can switch the definition to another language, e.g. JavaScript in MySQL
						autocomplete = (state, before, after) => (tag.classList.contains('jush-' + jushSql) ? autocompleter(state, before, after) : {});
						autocomplete.openBy = autocompleter.openBy;
					}
					const pre = jush.textarea(tag, autocomplete);
					if (pre) {
						tag.jushPre = pre;
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

/** Try to change input type to password or to text
* @param {HTMLInputElement} el
* @param {boolean} [disable]
*/
function typePassword(el, disable) {
	try {
		el.type = (disable ? 'text' : 'password');
	} catch (e) { // empty
	}
}

/** Display the password as a text if it's hashed
* @this HTMLInputElement
*/
function hashedClick() {
	typePassword(this.form['pass'], this.checked);
}

/** Uncheck the All privileges checkbox after granting a single privilege
* @param {string} id
* @this HTMLInputElement
*/
function grantsClick(id) {
	if (this.checked) {
		formUncheck(id);
	}
}

/** Install handlers in messages
* @param {HTMLElement} [parent]
*/
function messagesPrint(parent) {
	copyCode(parent);
}

/** Copy code to clipboard
* @param {HTMLElement} [parent]
*/
function copyCode(parent) {
	for (const el of qsa('.icon-copy', parent)) {
		el.onclick = () => {
			const code = qs('code', el.parentElement);
			navigator.clipboard.writeText(code.dataset.full || code.innerText).then(() => {
				alterClass(el, 'copied', true);
				setTimeout(() => alterClass(el, 'copied'), 1000);
			}, () => {});
			return false;
		};
	}
}



/** Hide or show some login rows for selected driver
* @this HTMLSelectElement
*/
function loginDriver() {
	const trs = this.closest('table').rows;
	const disabled = /sqlite/.test(selectValue(this));
	alterClass(trs[1], 'hidden', disabled);	// 1 - row with server
	qs('input', trs[1]).disabled = disabled;
}



let dbCtrl;
const dbPrevious = {};

/** Check if database should be opened to a new window
* @param {MouseEvent} event
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
		if ([...qsa('input', form)].some(input => input.value && /^fulltext/.test(input.name))) {
			return true;
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
				if (col in indexColumns && (!/LIKE|REGEXP/.test(op) || (op == 'LIKE' && val[0] != '%'))) {
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

/** Close the help and add a row in select fieldset after selecting a function
* @this HTMLSelectElement
*/
function selectFunAddRow() {
	helpClose();
	fire(qsl('select, input', this.parentNode), 'change'); // qsl - the column is printed after the function
}



let added = '.';

/** Check if val is equal to a-delimiter-b where delimiter is '_', '' or big letter
* @param {string} val
* @param {string} a
* @param {string} b
* @return {boolean}
*/
function delimiterEqual(val, a, b) {
	return (val == a + '_' + b || val == a + b || val == a + b[0].toUpperCase() + b.slice(1));
}

/** Escape string to use as identifier
* @param {string} s
* @return {string}
*/
function idfEscape(s) {
	return s.replace(/`/, '``');
}



/** Set up event handlers for edit_fields(). */
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
			onchange: editingTypeChange
		});
	}
	const table = qs('#edit-fields');
	let dragged;
	const dragStart = el => {
		dragged = el.closest('tr');
		alterClass(table, 'dragging', true);
		alterClass(dragged, 'dragged', true);
	};
	const dragMove = el => { // returns whether the element is a valid drop target
		const row = el && el.closest('tr'); // el is null if the touch is outside the viewport
		if (!dragged || !row || !qs('[draggable]', row)) {
			return false;
		}
		if (row != dragged) {
			const rows = [...row.parentNode.children];
			row.parentNode.insertBefore(dragged, (rows.indexOf(row) < rows.indexOf(dragged) ? row : row.nextSibling));
		}
		return true;
	};
	const dragEnd = () => {
		alterClass(dragged, 'dragged');
		dragged = null;
		alterClass(table, 'dragging');
	};
	mixin(table, {
		ondragstart: e => {
			if (e.target.draggable) {
				e.dataTransfer.effectAllowed = 'move';
				dragStart(e.target);
			}
		},
		ondragend: dragEnd,
		ondragover: e => {
			if (dragMove(e.target)) {
				e.preventDefault();
			}
		}
	});
	// HTML5 drag and drop doesn't work on touch screens; addEventListener() because the handlers must not be passive
	table.addEventListener('touchstart', e => {
		const el = e.target.closest('button');
		if (el && el.draggable) {
			e.preventDefault(); // to not scroll the page
			dragStart(el);
		}
	}, {passive: false});
	table.addEventListener('touchmove', e => {
		if (dragged) {
			e.preventDefault();
			const touch = e.touches[0];
			dragMove(document.elementFromPoint(touch.clientX, touch.clientY));
		}
	}, {passive: false});
	table.addEventListener('touchend', dragEnd);
}

/** Handle clicks on fields editing
* @param {MouseEvent} event
* @return {boolean} false to cancel action
*/
function editingClick(event) {
	let el = event.target.closest('button');
	if (el) {
		const name = el.name;
		if (/^add\[/.test(name)) {
			editingAddRow.call(el, 1);
		} else if (/^drop_col\[/.test(name)) {
			editingRemoveRow.call(el, 'fields$1[field]');
		}
		return false;
	}
	el = event.target;
	if (!el.matches('input')) {
		el = el.closest('label');
		el = el && qs('input', el);
	}
	if (el) {
		if (el.name == 'auto_increment_col') {
			const field = el.form['fields[' + el.value + '][field]'];
			if (!field.value) {
				field.value = 'id';
				field.oninput();
			}
		}
	}
}

/** Handle input on fields editing
* @param {InputEvent} event
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
	const name = this.name.slice(0, -7);
	const type = this.form[name + '[type]'];
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
		const [, base, column] = match;
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
* @param {number} [focus]
* @return {boolean} false
* @this HTMLInputElement
*/
function editingAddRow(focus) {
	const match = /(\d+)(\.\d+)?/.exec(this.name);
	const x = match[0] + (match[2] ? added.slice(match[2].length) : added) + '1';
	const row = this.closest('tr');
	const row2 = cloneNode(row);
	let tags = qsa('select, input, button', row);
	let tags2 = qsa('select, input, button', row2);
	for (const [i, tag] of tags.entries()) {
		tags2[i].name = tag.name.replace(/[0-9.]+/, x);
		tags2[i].selectedIndex = (/\[(generated)/.test(tag.name) ? 0 : tag.selectedIndex);
	}
	tags = qsa('input', row);
	tags2 = qsa('input', row2);
	for (const [i, tag] of tags.entries()) {
		if (tag.name == 'auto_increment_col') {
			tags2[i].value = x;
			tags2[i].checked = false;
		}
		if (/\[(orig|field|comment|default)/.test(tag.name)) {
			tags2[i].value = '';
		}
		if (/\[(generated)/.test(tag.name)) {
			tags2[i].checked = false;
		}
	}
	tags[0].oninput = editingNameChange;
	row.parentNode.insertBefore(row2, row.nextSibling);
	if (focus) {
		tags2[0].oninput = editingNameChange;
		tags2[0].focus();
	}
	added += '0';
	maxFieldsCheck();
	return false;
}

/** Display the error about the number of fields if the form has too many columns */
function maxFieldsCheck() {
	const el = qs('#max-fields'); // only in create.inc.php, only if max_input_vars is set and only if the message is hidden
	// [orig] is printed for every column and editingRemoveRow() keeps it, so the removed columns are counted too
	if (el && qsa('#edit-fields [name$="[orig]"]').length > +el.dataset.columns) {
		alterClass(el, 'hidden');
		qs('#edit-fields').parentNode.after(el); // the top of the page is not visible after adding columns
	}
}

/** Add table row after the last field; used by drivers where columns can be added only to the end
* @return {boolean} false for success
* @this HTMLElement
*/
function editingAddLastRow() {
	const inputs = qsa('#edit-fields [name$="[field]"]');
	if (!inputs.length) {
		return true; // submit the form to add the row by PHP
	}
	return editingAddRow.call(inputs[inputs.length - 1], 1);
}

/** Remove table row for field
* @param {string} name regular expression replacement
* @return {boolean} false
* @this HTMLInputElement
*/
function editingRemoveRow(name) {
	const field = this.form[this.name.replace(/[^[]+(.+)/, name)];
	field.remove();
	this.closest('tr').hidden = true;
	return false;
}

let lastType = '';

/** Clear length and hide collation or unsigned
* @this HTMLSelectElement
*/
function editingTypeChange() {
	const type = this;
	const name = type.name.slice(0, -6);
	const text = selectValue(type);
	for (const el of type.form.elements) {
		if (el.name == name + '[length]') {
			if (!(
				(/(char|binary)$/.test(lastType) && /(char|binary)$/.test(text))
				|| (/(enum|set)$/.test(lastType) && /(enum|set)$/.test(text))
			)) {
				el.value = '';
			}
			el.oninput.call(el);
		}
		if (lastType == 'timestamp' && el.name == name + '[generated]' && /timestamp/i.test(type.form[name + '[default]'].value)) {
			el.checked = false;
			el.selectedIndex = 0;
		}
		// the expressions come from option_types(), the options of the other columns start with another name
		if (el.dataset.types && el.name.startsWith(name + '[')) {
			alterClass(el, 'hidden', !new RegExp(el.dataset.types).test(text));
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
		td.append(edit);
		this.hidden = true;
		edit.hidden = false;
		edit.focus();
	}
}

/** Get enum values
* @param {string} s
* @return {string} values separated by newlines
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
	field.hidden = false;
	this.hidden = true;
}

/** Show or hide selected table column
* @param {boolean} checked
* @param {number} column
*/
function columnShow(checked, column) {
	for (const tr of qsa('tr', qs('#edit-fields'))) {
		alterClass(qsa('td', tr)[column], 'hidden', !checked);
	}
}

/** Show or hide the clicked table column
* @param {number} column
* @this HTMLInputElement
*/
function columnShowClick(column) {
	columnShow(this.checked, column);
}

/** Show or hide index column options
* @this HTMLInputElement
*/
function indexOptionsShow() {
	for (const option of qsa('.idxopts')) {
		alterClass(option, 'hidden', !this.checked);
	}
}

/** Reload the page for the selected target
* @this HTMLSelectElement
*/
function foreignChange() {
	const form = this.form;
	form['change-js'].value = '1';
	form.submit();
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
	const tr = this.closest('tr');
	const row = cloneNode(tr);
	row.firstChild.firstChild.value = '';
	tr.parentNode.append(row);
	this.removeAttribute('data-oninput'); // the appended row adds the next one
}

/** Show or hide comment fields
* @param {boolean} [focus] whether to focus Comment if checked
* @this HTMLInputElement
*/
function editingCommentsClick(focus) {
	const comment = this.form['Comment'];
	columnShow(this.checked, 6);
	alterClass(comment, 'hidden', !this.checked);
	if (focus && this.checked) {
		comment.focus();
	}
}



/** Uncheck 'all' checkbox
* @param {MouseEvent} event
* @this HTMLTableElement
*/
function dumpClick(event) {
	let el = event.target.closest('label');
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
	const tr = this.closest('tr');
	const row = cloneNode(tr); // the clone keeps the attribute so that it adds the next row
	this.removeAttribute('data-onchange');
	for (const select of qsa('select', row)) {
		select.name = select.name.replace(/\d+]/, '1$&');
		select.selectedIndex = 0;
	}
	tr.parentNode.append(row);
}



/** Add row for indexes
* @this HTMLSelectElement
*/
function indexesAddRow() {
	const tr = this.closest('tr');
	const row = cloneNode(tr); // the clone keeps the attribute so that it adds the next row
	this.removeAttribute('data-onchange');
	for (const tag of qsa('select, input, button', row)) {
		tag.name = tag.name.replace(/\[\d+/, '$&1'); // indexes[$j] and drop_col[$j]
		if (tag.matches('select')) {
			tag.selectedIndex = 0;
		} else if (tag.matches('input')) {
			tag.value = '';
		}
	}
	tr.parentNode.append(row);
}

/** Change column in index, the last column also adds the next one
* @param {string} prefix
* @this HTMLSelectElement
*/
function indexesChangeColumn(prefix) {
	const field = this;
	const td = field.closest('td');
	const columns = [...qsa('select, input', td)].filter(column => /\[columns]/.test(column.name));
	if (columns[columns.length - 1] == field) { // the appended column becomes the last one so it adds the next
		const type = field.form[field.name.replace(/].*/, '][type]')];
		if (!type.selectedIndex) {
			while (selectValue(type) != "INDEX" && type.selectedIndex < type.options.length) {
				type.selectedIndex++;
			}
			fire(type, 'change');
		}
		const column = cloneNode(field.parentNode);
		for (const select of qsa('select', column)) {
			select.name = select.name.replace(/]\[\d+/, '$&1');
			select.selectedIndex = 0;
		}
		for (const input of qsa('input', column)) {
			input.name = input.name.replace(/]\[\d+/, '$&1');
			if (input.type != 'checkbox') {
				input.value = '';
			}
		}
		td.append(column);
	}
	const names = [];
	for (const column of columns) { // the appended column is empty so it doesn't matter that it's not in the list
		const value = selectValue(column);
		if (value) {
			names.push(value);
		}
	}
	field.form[field.name.replace(/].*/, '][name]')].value = prefix + names.join('_');
}



/** Update the form action
* @param {string} root
* @this HTMLFormElement
*/
function sqlSubmit(root) {
	const suffix = (this['limit'].value ? '&limit=' + +this['limit'].value : '')
		+ (this['error_stops'].checked ? '&error_stops=1' : '')
		+ (this['only_errors'].checked ? '&only_errors=1' : '');
	const action = root + '&sql=' + urlEscape(this['query'].value) + suffix;
	this.action = ((location.origin + location.pathname + action).length < 2000 // reasonable minimum is 2048
		? action
		: root + '&sql=' + suffix
	);
}

/** Export the result table by JS without re-running the query
* @param {MouseEvent} event
* @return {boolean} false when handled by JS
* @this HTMLInputElement
*/
function sqlExport(event) {
	const form = this.form;
	const format = form['format'].value;
	const output = form['output'].value;
	if (!/^(csv|csv;|tsv)$/.test(format) || !/^(text|file)$/.test(output)) {
		return true;
	}
	const div = form.previousElementSibling;
	const table = (div && div.classList.contains('scrollable') ? qs('table', div) : null);
	if (!table) {
		return true;
	}
	// <i> other than NULL means the value is not displayed fully
	if ([...qsa('i', table)].some(i => i.textContent != 'NULL')) {
		return true;
	}
	// save_settings() - the server-side export stores the settings too
	cookie(
		'adminer_import=' + encodeURIComponent('output=' + output + '&format=' + encodeURIComponent(format)),
		30 // 30 - $lifetime of cookie()
	);
	const tsv = (format == 'tsv');
	const quotable = new RegExp('["\n]|^0[^.]|\\.\\d*0$|' + (tsv ? '\t' : '[,;]|^$')); // dump_csv()
	let data = '\ufeff'; // UTF-8 byte order mark
	for (const row of qsa('tr', table)) {
		data += Array.from(row.children).map(cell => {
			const val = (qsa('i', cell).length ? '' : cell.textContent); // <i> - NULL
			return (quotable.test(val) ? '"' + val.replace(/"/g, '""') + '"' : val);
		}).join(format == 'csv' ? ',' : (tsv ? '\t' : ';')) + '\r\n';
	}
	const url = URL.createObjectURL(new Blob([data], {type: (output == 'file' ? 'text/csv' : 'text/plain') + '; charset=utf-8'}));
	if (output == 'file') {
		const a = document.createElement('a');
		a.href = url;
		a.download = 'sql.csv';
		document.body.append(a);
		a.click();
		a.remove();
		setTimeout(() => URL.revokeObjectURL(url));
	} else if (isCtrl(event) || event.shiftKey) {
		// the same modifiers open the server-side export in a new window in bodyClick(), submit the form if the pop-up is blocked
		// the URL is not revoked to not break the load of the new window
		return !open(url);
	} else {
		location.href = url;
	}
	return false;
}

/** Check if PHP can handle the uploaded files
* @param {number} count
* @param {string} countMessage
* @param {number} size
* @param {string} sizeMessage
* @this HTMLInputElement
*/
function fileChange(count, countMessage, size, sizeMessage) {
	if (this.files.length > count) {
		alert(countMessage);
	} else if (Array.from(this.files).reduce((sum, file) => sum + file.size, 0) > size) {
		alert(sizeMessage);
	}
}

/** Display the progress of the file upload
* @param {string} url
* @param {string} assign cookie identifying the session where PHP stores the progress - it uses the session name from php.ini which differs from Adminer's
* @this HTMLFormElement
*/
function uploadProgress(url, assign) {
	const progress = qs('progress', this);
	// the form can be submitted also without a file - by Run file in Import or by Save, Delete and Export in Select
	if (!progress || !Array.from(qsa('input[type=file]', this)).some(input => input.value)) {
		return;
	}
	cookie(assign, 1);
	let started = false;
	const poll = () => ajax(url, request => {
		const data = JSON.parse(request.responseText);
		if (!data.length) {
			if (started) {
				progress.value = 1;
				progress.textContent = '100%';
				cookie(assign, -1); // the session is no longer needed, don't keep its name taken
				return;
			}
		} else if (data[1]) {
			started = true;
			alterClass(progress, 'hidden');
			progress.value = data[0] / data[1];
			progress.textContent = Math.floor(100 * data[0] / data[1]) + '%';
		}
		setTimeout(poll, 1000);
	}, null, null); // null message - a failed poll would print an error and stop the polling
	setTimeout(poll, 1000);
}



/** Handle changing trigger time or event
* @param {string} tableRe string - a regular expression can't be passed in a data attribute
* @param {string} table
* @this HTMLSelectElement
*/
function triggerChange(tableRe, table) {
	const form = this.form;
	const formEvent = selectValue(form['Event']);
	if (new RegExp(tableRe).test(form['Trigger'].value)) {
		form['Trigger'].value = table + '_' + (selectValue(form['Timing'])[0] + formEvent[0]).toLowerCase();
	}
	alterClass(form['Of'], 'hidden', !/ OF/.test(formEvent));
}



/** Highlight the routine definition by the selected language
* @param {Object} jushLangs routine language => syntax highlighting language
* @this HTMLSelectElement
*/
function routineLanguage(jushLangs) {
	const jushClass = 'jush-' + jushLangs[selectValue(this)];
	const textarea = this.form['definition'];
	textarea.className = textarea.className.replace(/jush-\S+/, jushClass);
	const pre = textarea.jushPre;
	if (pre) {
		pre.className = pre.className.replace(/jush-\S+/, jushClass);
		textarea.onchange(); // highlights the <pre> again, jush.textarea() reads the language from the <textarea>
	}
}



let that, x, y; // em, tablePos and tablePosDefault defined in schema.inc.php

/** Get mouse position
* @param {MouseEvent} event
* @this HTMLElement
*/
function schemaMousedown(event) {
	if (event.button == 0) { // 0 - left button
		that = this;
		x = event.clientX - this.offsetLeft;
		y = event.clientY - this.offsetTop;
	}
}

/** Connect one end of a reference to the vertical line
* @param {HTMLElement} div .references inside the table
* @param {number} line position of the vertical line in em
* @param {number} table position of the table in em
*/
function schemaRef(div, line, table) {
	const left = line - table;
	const inner = div.querySelector('div');
	if (left > 0) { // the line is right of the table so the reference leaves it on its right edge, 100% is the width of the table
		div.style.left = '100%';
		div.style.width = 'calc(' + left + 'em - 100%)';
		inner.style.width = '100%';
	} else {
		div.style.left = left + 'em';
		div.style.width = '';
		inner.style.width = -left + 'em';
	}
}

/** Move object
* @param {MouseEvent} event
*/
function schemaMousemove(event) {
	if (that !== undefined) {
		const left = (event.clientX - x) / em;
		const top = (event.clientY - y) / em;
		const lineSet = { };
		for (const div of qsa('div', that)) {
			if (div.classList.contains('references')) {
				const div2 = qs('[id="' + (/^refs/.test(div.id) ? 'refd' : 'refs') + div.id.slice(4) + '"]');
				if (!div2) { // table in another schema
					continue;
				}
				const ref = (tablePos[div.title] || tablePosDefault[div.title] || [ div2.parentNode.offsetTop / em, div2.parentNode.offsetLeft / em ]);
				const id = div.id.replace(/^ref.(.+)-.+/, '$1');
				let lineLeft = left - 1; // a self-reference, the line is left of the table
				if (div.parentNode != div2.parentNode) {
					const refs = /^refs/.test(div.id); // refs is in the referencing table, refd in the referenced one
					const source = (refs ? left : ref[1]);
					const target = (refs ? ref[1] : left);
					const width = (refs ? that : div2.parentNode).offsetWidth / em;
					// 1 em right of the referencing table, unless the referenced table is not right of it - then left of both
					lineLeft = (target - 1 > source + width ? source + width + 1 : Math.min(source, target) - 1);
					schemaRef(div, lineLeft, left);
					schemaRef(div2, lineLeft, ref[1]);
				}
				if (!lineSet[id]) {
					const line = qs('[id="' + div.id.replace(/^....(.+)-.+$/, 'refl$1') + '"]');
					const top1 = top + div.offsetTop / em;
					let top2 = top + div2.offsetTop / em;
					if (div.parentNode != div2.parentNode) {
						top2 += ref[0] - top;
						line.querySelector('div').style.height = Math.abs(top1 - top2) + 'em';
					}
					line.style.left = lineLeft + 'em';
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
* @param {MouseEvent} event
* @param {string} db
*/
function schemaMouseup(event, db) {
	if (that !== undefined) {
		tablePos[that.firstChild.firstChild.firstChild.data] = [ (event.clientY - y) / em, (event.clientX - x) / em ];
		that = undefined;
		let s = '';
		for (const key in tablePos) {
			const [top, left] = tablePos[key];
			s += '_' + key + ':' + Math.round(top) + 'x' + Math.round(left);
		}
		s = s.slice(1);
		const link = qs('#schema-link');
		link.href = link.href.replace(/[^=]+$/, '') + urlEscape(s);
		// encodeURIComponent() instead of urlEscape() - it keeps ';' verbatim, which would end the cookie value
		cookie('adminer_schema-' + db + '=' + encodeURIComponent(s), 30); //! special chars in db
	}
}



let helpOpen, helpIgnore; // when mouse outs <option> then it mouse overs border of <select> - ignore it

/** Display help
* @param {string} text
* @param {number} side 1 to display on left side, 0 on top
* @param {MouseEvent} event
* @this HTMLElement
*/
function helpMouseover(text, side, event) {
	const target = event.target;
	if (!text) {
		helpClose();
	} else if (window.jush && (!helpIgnore || this != target)) {
		helpOpen = 1;
		const help = qs('#help');
		help.textContent = text;
		jush.highlight_tag([ help ]);
		alterClass(help, 'hidden');
		const rect = target.getBoundingClientRect();
		const body = document.documentElement;
		help.style.top = (body.scrollTop + rect.top - (side ? (help.offsetHeight - target.offsetHeight) / 2 : help.offsetHeight)) + 'px';
		help.style.left = (body.scrollLeft + rect.left - (side ? help.offsetWidth : (help.offsetWidth - target.offsetWidth) / 2)) + 'px';
	}
}

/** Display help with the hovered value
* @param {string} regexp regular expression transforming the value, empty to display the value itself
* @param {string} replacement can use $& and $1
* @param {MouseEvent} event
* @this HTMLSelectElement
*/
function helpValueMouseover(regexp, replacement, event) {
	// target - the hovered <option> in an open <select>
	const value = event.target.value;
	// new RegExp() - a regular expression can't be passed in a data attribute
	// 1 - an open <select> would cover the help displayed on top
	helpMouseover.call(this, (value && regexp ? value.replace(new RegExp(regexp), replacement) : value), 1, event);
}

/** Close help after timeout
* @param {MouseEvent} event
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

/** Keep the help open while hovering it */
function helpKeep() {
	helpOpen = 1;
}

/** Close help */
function helpClose() {
	alterClass(qs('#help'), 'hidden', true);
}
