'use strict';

/** Get first element by selector
* @param {string} selector
* @param {HTMLElement} [context=document]
* @return {HTMLElement}
*/
function qs(selector, context = document) {
	return context.querySelector(selector);
}

/** Get last element by selector
* @param {string} selector
* @param {HTMLElement} [context=document]
* @return {HTMLElement}
*/
function qsl(selector, context) {
	const els = qsa(selector, context);
	return els[els.length - 1];
}

/** Get all elements by selector
* @param {string} selector
* @param {HTMLElement} [context=document]
* @return {NodeList}
*/
function qsa(selector, context = document) {
	return context.querySelectorAll(selector);
}

/** Return a function calling fn with the next arguments
* @param {function} fn
* @param {...*} args
* @return {function} with preserved this
*/
function partial(fn, ...args) {
	return function () {
		return fn.apply(this, args);
	};
}

/** Assign values from source to target
* @param {Object} target
* @param {Object} source
*/
function mixin(target, source) {
	// a shortcut for Object.assign() saving bytes in the inline scripts, used also by external plugins
	Object.assign(target, source);
}

/** Add or remove CSS class
* @param {?HTMLElement} el
* @param {string} className
* @param {boolean} [enable]
*/
function alterClass(el, className, enable) {
	if (el) {
		el.classList.toggle(className, !!enable); // !! - undefined would toggle
	}
}

/** Toggle visibility
* @param {string} id
* @return {boolean} false
*/
function toggle(id) {
	const el = qs('#' + id);
	el && el.classList.toggle('hidden');
	return false;
}

/** Escape string to use in a query string; counterpart of PHP url_escape()
* @param {string} string
* @return {string}
*/
function urlEscape(string) {
  // unlike encodeURIComponent(), escapes only the characters misinterpreted by PHP or by the URL syntax and those rewritten by the browser
	return encodeURIComponent(string)
		.replace(/'/g, '%27') // encodeURIComponent() keeps it but the browser escapes it anyway
		.replace(/%20/g, '+')
		// $,/:;@[\]^`{|} - the browser keeps them verbatim; urlSeparators are set by arg_separator.input
		.replace(/%(24|2C|2F|3A|3B|40|5[BCDE]|60|7[BCD])/g, (all, hex) => {
			const char = String.fromCharCode(parseInt(hex, 16));
			return (urlSeparators.indexOf(char) < 0 ? char : all);
		})
	;
}

/** Get value from a query string; counterpart of PHP urldecode()
* @param {string} string
* @return {string}
*/
function urlUnescape(string) {
	return decodeURIComponent(string.replace(/\+/g, ' '));
}

/** Serialize form fields to a query string
* @param {HTMLFormElement} form
* @param {HTMLElement} [submitter] the button which sent the form
* @param {boolean} [defaults] skip the fields holding the value from their data-default
* @return {?string} null if the form contains a file
*/
function formData(form, submitter, defaults) {
	const data = [];
	for (const el of form.elements) {
		if (el.name && !el.disabled) {
			if (/^file$/i.test(el.type) && el.value) {
				return null;
			}
			if (!/^(checkbox|radio|submit|file)$/i.test(el.type) || el.checked || el == submitter) {
				const value = selectValue(el);
				if (!defaults || el.getAttribute('data-default') !== value) { // getAttribute() returns null for a field which must be always sent
					data.push(urlEscape(el.name) + '=' + urlEscape(value));
				}
			}
		}
	}
	return data.join('&');
}

/** Set permanent cookie
* @param {string} assign
* @param {number} days
*/
function cookie(assign, days) {
	const date = new Date();
	date.setDate(date.getDate() + days);
	document.cookie = assign
		+ '; expires=' + date
		+ '; path=' + location.pathname.replace(/[;,]/g, encodeURIComponent) // default path is without the trailing slash
	;
}

/** Verify current Adminer version
* @param {string} current
*/
function verifyVersion(current) {
	cookie('adminer_version=0', 1);
	ajax('https://www.adminer.org/version/?current=' + current, request => {
		const json = JSON.parse(request.responseText);
		cookie('adminer_version=' + (json.version || current), 7); // empty if there's no newer version
		qs('#version').textContent = json.version;
	}, '', null); // null - a failed check must not report being offline
}

/** Get value of select
* @param {HTMLElement} select <select> or <input>
* @return {string}
*/
function selectValue(select) {
	if (!select.selectedIndex) {
		return select.value;
	}
	const selected = select.options[select.selectedIndex];
	return ((selected.attributes.value || {}).specified ? selected.value : selected.text);
}

/** Set checked class
* @param {HTMLInputElement} el
*/
function trCheck(el) {
	const tr = el.closest('tr');
	alterClass(tr, 'checked', el.checked);
	fire(el.form && el.form['all'], 'click'); // the element named all counts the selected items, its handler is registered by a data attribute
}

/** Fill number of selected items
* @param {string} id
* @param {string|number} count
* @uses thousandsSeparator
*/
function selectCount(id, count) {
	setHtml(id, (count === '' ? '' : '(' + (count + '').replace(/\B(?=(\d{3})+$)/g, thousandsSeparator) + ')'));
	const el = qs('#' + id);
	if (el) {
		for (const input of qsa('input[type=submit]', el.parentNode.parentNode)) {
			input.disabled = (count == '0');
		}
		fire(document.activeElement, 'focus'); // the default submit button changes with the disabled buttons
	}
}

/** Check all elements matching given name
* @param {string} name regular expression
* @this HTMLInputElement
*/
function formCheck(name) {
	const re = new RegExp(name); // string - a regular expression can't be passed in a data attribute
	for (const elem of this.form.elements) {
		if (re.test(elem.name)) {
			elem.checked = this.checked;
			trCheck(elem);
		}
	}
}

/** Check all rows in <table class="checkable">  */
function tableCheck() {
	qsa('table.checkable td:first-child input').forEach(trCheck);
	onpageshow = tableCheck; // once the browser restores the checkboxes while browsing history
}

/** Uncheck single element
* @param {string} id
*/
function formUncheck(id) {
	const el = qs('#' + id);
	el.checked = false;
	trCheck(el);
}

/** Get number of checked elements matching given name
* @param {HTMLInputElement} input
* @param {RegExp} name
* @return {number}
*/
function formChecked(input, name) {
	return [...input.form.elements].filter(el => name.test(el.name) && el.checked).length;
}

/** Fill number of selected databases
* @this HTMLInputElement
*/
function countDbs() {
	selectCount('selected', formChecked(this, /^db/));
}

/** Fill numbers of selected tables
* @param {number} tables number of tables in the database
* @this HTMLInputElement
*/
function countTables(tables) {
	const checked = formChecked(this, /^(tables|views)\[/);
	selectCount('selected', checked);
	selectCount('selected2', formChecked(this, /^tables\[/) || tables); // search is performed in all tables if none is selected
	selectCount('selected3', checked);
}

/** Fill numbers of selected rows
* @param {string} rows number of rows in the whole result
* @this HTMLInputElement
*/
function countRows(rows) {
	const checked = formChecked(this, /^check/);
	selectCount('selected', this.checked ? rows : checked);
	selectCount('selected2', this.checked || !checked ? rows : checked); // the command is performed on the whole result if no row is selected
}

/** Ask for confirmation before submit
* @param {string} message
* @return {boolean} false to cancel the submit
*/
function confirmClick(message) {
	// window.confirm can't be registered as the handler itself - it throws Illegal invocation when apply() passes the element as this
	return confirm(message);
}

/** Select clicked row
* @param {MouseEvent} event
*/
function tableClick(event) {
	const td = event.target.closest('td');
	let text;
	if (td && (text = td.dataset.text)) {
		if (selectClick.call(td, event, +text, td.dataset.warning)) {
			return;
		}
	}
	// dblclick undoes the check by the first click of the double click - the user is only selecting a word
	let click = (event.type == 'dblclick' || getSelection().isCollapsed);
	let el = event.target.closest('tr, table, a, input, textarea'); // the other elements handle the click themselves
	if (el && !el.matches('tr')) {
		if (el.type != 'checkbox') {
			return;
		}
		checkboxClick.call(el, event);
		click = false;
		el = el.closest('tr');
	}
	if (!el) { // Ctrl+click on text fields hides the element
		return;
	}
	el = qs('input[type=checkbox]', el.firstChild); // not firstChild - the first cell of select also contains the edit link
	if (!el) { // the header row of the process list and of the database list has an empty first cell
		return;
	}
	if (click) {
		el.checked = !el.checked;
		fire(el, 'click'); // the handler can be registered by a data attribute
	}
	if (el.name == 'check[]') {
		el.form['all'].checked = false;
		formUncheck('all-page');
	}
	if (/^(tables|views)\[\]$/.test(el.name)) {
		formUncheck('check-all');
	}
	trCheck(el);
}

let lastChecked;

/** Shift-click on checkbox for multiple selection.
* @param {MouseEvent} event
* @this HTMLInputElement
*/
function checkboxClick(event) {
	if (!this.name) {
		return;
	}
	if (event.shiftKey && (!lastChecked || lastChecked.name == this.name)) {
		const checked = (lastChecked ? lastChecked.checked : true);
		let checking = !lastChecked;
		for (const input of qsa('input', this.closest('table'))) {
			if (input.name === this.name) {
				if (checking) {
					input.checked = checked;
					trCheck(input);
				}
				if (input === this || input === lastChecked) {
					if (checking) {
						break;
					}
					checking = true;
				}
			}
		}
	} else {
		lastChecked = this;
	}
}

/** Set HTML code of an element
* @param {string} id
* @param {?string} html undefined to set parentNode to empty string
*/
function setHtml(id, html) {
	const el = qs('[id="' + id.replace(/[\\"]/g, '\\$&') + '"]'); // database name is used as ID
	if (el) {
		if (html == null) {
			el.parentNode.innerHTML = '';
		} else {
			el.innerHTML = html;
		}
	}
}



/** Display items in menu
* @param {MouseEvent} event
* @this HTMLElement
*/
function menuOver(event) {
	const a = event.target;
	if (a.matches('a, span') && a.offsetLeft + a.offsetWidth > a.parentNode.offsetWidth) {
		this.style.overflow = 'visible';
	}
}

/** Hide items in menu
* @this HTMLElement
*/
function menuOut() {
	this.style.overflow = 'hidden';
}

/** Toggle the menu on small screens */
function menuToggle() {
	const foot = qs('#foot');
	const opened = !foot.classList.toggle('foot');
	qs('#menuopen button').setAttribute('aria-expanded', opened);
	if (opened) {
		foot.tabIndex = -1; // to make it focusable
		foot.focus(); // to continue tabbing in the menu
	}
}

/** Close the menu on small screens */
function menuClose() {
	const foot = qs('#foot');
	if (foot && !foot.classList.contains('foot')) {
		foot.classList.add('foot');
		const button = qs('#menuopen button');
		button.setAttribute('aria-expanded', false);
		if (foot.contains(document.activeElement)) { // the focused element is hidden now
			button.focus();
		}
	}
}



/** Submit the form of the changed field
* @this HTMLElement
*/
function formSubmit() {
	this.form.submit();
}

/** Disable Save and continue editing after changing a value identifying the row
* @param {Event} event
* @this HTMLTableRowElement
*/
function whereChange(event) {
	// isTrusted - fire() dispatches change to apply the initially selected function, which is not a change by the user
	if (event.isTrusted !== false) {
		// the WHERE condition in the URL is not updated by the AJAX save so the next save would not match the row
		qs('#form [name=insert]').disabled = true;
	}
}

/** Add row in select fieldset
* @this HTMLSelectElement
*/
function selectAddRow() {
	const field = this;
	const row = cloneNode(field.parentNode); // the clone keeps the attribute so that it adds the next row
	field.setAttribute('data-onchange', 'selectFieldChange()');
	fire(field, 'change');
	for (const select of qsa('select', row)) {
		select.name = select.name.replace(/[a-z]\[\d+/, '$&1');
		select.selectedIndex = 0;
	}
	for (const input of qsa('input', row)) {
		input.name = input.name.replace(/[a-z]\[\d+/, '$&1');
		input.className = '';
		if (input.type == 'checkbox') {
			input.checked = false;
		} else {
			input.value = '';
		}
	}
	field.parentNode.parentNode.append(row);
}

/** Rerun the handler of the first field in the row, it checks whether an index will be used
* @this HTMLElement
*/
function selectFirstChange() {
	fire(this.parentNode.firstChild, 'change');
}

/** Prevent the search handler on Enter, clear the field by Esc
* @param {KeyboardEvent} event
* @this HTMLInputElement
*/
function selectSearchKeydown(event) {
	if (event.key == 'Enter') {
		this.removeAttribute('data-onsearch');
	} else if (isEscape(event)) {
		// the same as clicking the cancel button; Firefox displays none and dispatches no search event, WebKit does both and repeats this
		this.value = '';
		selectSearchSearch.call(this);
	}
}

/** Clear column name after resetting search
* @this HTMLInputElement
*/
function selectSearchSearch() {
	if (!this.value) {
		this.parentNode.firstChild.selectedIndex = 0;
	}
}



/** Fill column in search field
* @param {string} name
* @return {boolean} false
*/
function selectSearch(name) {
	const fieldset = qs('#fieldset-search');
	fieldset.className = '';
	const divs = qsa('div', fieldset);
	let div = [...divs].find(row => {
		const col = qs('[name$="[col]"]', row);
		return col && selectValue(col) == name;
	});
	if (!div) { // use the last empty row
		div = divs[divs.length - 1];
		div.firstChild.value = name;
		fire(div.firstChild, 'change');
	}
	qs('[name$="[val]"]', div).focus();
	return false;
}


/** Check if Ctrl key (Command key on Mac) was pressed
* @param {KeyboardEvent|MouseEvent} event
* @return {boolean}
*/
function isCtrl(event) {
	return (event.ctrlKey || event.metaKey) && !event.altKey; // shiftKey allowed
}

/** Check if Esc was pressed alone
* @param {KeyboardEvent} event
* @return {boolean}
*/
function isEscape(event) {
	return event.key == 'Escape' && !event.shiftKey && !event.altKey && !isCtrl(event);
}



/** Send form by Ctrl+Enter on <select>, <textarea> and <input>
* @param {string} button name of the submit button to click, empty for the implicit one
* @param {KeyboardEvent} event
* @return {boolean} false if the form was sent
*/
function submitKeydown(button, event) {
	let target = event.target;
	if (target.jushTextarea) {
		target = target.jushTextarea;
	}
	if (isCtrl(event) && event.key == 'Enter' && target.matches('select, textarea, input')) {
		target.blur();
		if (target.form[button]) {
			target.form[button].click();
		} else {
			if (fire(target.form, 'submit')) { // submit() doesn't dispatch the event
				target.form.submit();
			}
		}
		target.focus();
		return false;
	}
}

/** Close the menu by Esc, send form by Ctrl+Enter
* @param {KeyboardEvent} event
* @return {boolean}
*/
function bodyKeydown(event) {
	if (isEscape(event)) {
		menuClose();
	}
	if (!delegateEvent(event)) { // a delegated handler sends the form by its own button
		return submitKeydown('', event);
	}
}

/** Toggle visibility by .toggle links, close the menu, open form to a new window on Ctrl+click or Shift+click
* @param {MouseEvent} event
*/
function bodyClick(event) {
	delegateEvent(event);
	const target = event.target;
	const toggler = target.closest && target.closest('.toggle'); // closest && - the target can be a text node, e.g. from fire()
	if (toggler) {
		toggle(toggler.getAttribute('href').slice(1));
		event.preventDefault();
	}
	if ((isCtrl(event) || event.shiftKey) && target.type == 'submit' && target.matches('input')) { // type - the target can be a text node without matches()
		target.form.target = '_blank';
		setTimeout(() => {
			// if (isCtrl(event)) { focus(); } doesn't work
			target.form.target = '';
		}, 0);
	}
	const foot = qs('#foot'); // null if fire() dispatches a click before the menu is printed at the end of the page
	if (foot && !foot.contains(target) && !qs('#menuopen').contains(target)) { // menuopen - delegateEvent() has toggled the menu by it already
		menuClose();
	}
}

/** Highlight the value exceeding the maximum length
* @param {HTMLInputElement} el
*/
function maxlengthCheck(el) {
	const maxLength = el.dataset.maxlength;
	alterClass(el, 'maxlength', el.value && maxLength != null && el.value.length > maxLength); // maxLength could be 0
}

/** Send GET forms with minimal URL escaping
* @param {SubmitEvent} event
* @return {boolean} false if the form was sent by JS
*/
function bodySubmit(event) {
	if (delegateEvent(event)) { // a data-onsubmit handler has handled the event
		return true;
	}
	const form = event.target;
	if (/^get$/i.test(form.method)) { // the browser would serialize the form with full escaping
		const data = formData(form, event.submitter, true); // submitter is undefined in Chrome < 81, no GET form uses a named submit button
		if (data !== null) {
			const url = form.action.replace(/\?.*/, '') + '?' + data;
			if (form.target) { // set by Ctrl+click or Shift+click; open() is not blocked, the click activation is still valid
				open(url, form.target);
			} else {
				location.href = url;
			}
			return false;
		}
	}
}

/** Highlight too long values
* @param {InputEvent} event
*/
function bodyInput(event) {
	delegateEvent(event);
	maxlengthCheck(event.target);
}

/** Call handlers registered by data-on<event> attributes between the event target and the <html> element
* @param {Event} event
* @return {boolean} true if a handler has handled the event, never false - it is registered by a property, which would preventDefault()
*/
function delegateEvent(event) {
	const attr = 'data-on' + event.type;
	const selector = '[' + attr + ']';
	// closest() - much faster than getAttribute() on every ancestor, target.closest - the target can be a text node
	let el = event.target.closest && event.target.closest(selector);
	while (el) {
		const value = el.getAttribute(attr);
		const match = /^(\w+)\((.*)\)$/.exec(value); // e.g. confirmClick("Are you sure?")
		// only a function declared by Adminer or by a plugin - it creates a non-configurable property of window, built-ins are configurable or not own properties at all, so window['eval'] or window['setTimeout'] is never used
		const desc = (match ? Object.getOwnPropertyDescriptor(window, match[1]) : null);
		const handler = (desc && !desc.configurable && typeof desc.value == 'function' ? desc.value : null);
		let args;
		try {
			args = (handler ? JSON.parse('[' + match[2] + ']') : null); // JSON.parse() - the arguments must never be evaluated as code
		} catch (e) { // empty - an invalid value is reported below together with an unknown handler
		}
		if (args) {
			const result = handler.apply(el, args.concat(event));
			if (result === false) {
				event.preventDefault();
			}
			if (result !== undefined) {
				return true; // the handler handled the event, don't call the handlers on ancestor elements
			}
		} else {
			console.error('Invalid handler in ' + attr + "='" + value + "'", el); // not lang() - it reports a bug in Adminer or in a plugin, the element would do nothing at all otherwise
		}
		el = el.parentElement && el.parentElement.closest(selector); // parentElement - closest() starts at the element itself
	}
}



/** Change focus by Ctrl+Shift+Up or Ctrl+Shift+Down
* @param {KeyboardEvent} event
* @return {boolean}
*/
function editingKeydown(event) {
	if (/^Arrow(Down|Up)$/.test(event.key) && isCtrl(event)) {
		const target = event.target;
		const cell = target.closest('td, th'); // not parentNode - the NULL checkbox is wrapped in a <label>
		const cells = [...cell.parentNode.children];
		const sibling = (event.key == 'ArrowUp' ? 'previousElementSibling' : 'nextElementSibling');
		let row = cell.parentNode;
		do {
			row = row[sibling];
		} while (row && row.hidden); // skip removed columns
		const cell2 = (row ? row.children[cells.indexOf(cell)] : null);
		if (cell2) {
			// the element at the same position can be hidden, the cell displays e.g. collation or ON DELETE by the column type
			const name = target.name.replace(/.*(\[[^[]+])$/, '$1');
			const el = [qs('[name$="' + name + '"]', cell2), ...qsa('input, select, textarea, button', cell2)].find(el2 => el2 && el2.offsetParent);
			if (el) {
				el.focus();
			}
		}
		return false;
	}
	if (event.shiftKey) {
		return submitKeydown('insert', event);
	}
}

/** Change the value field to a plain <input> for the SQL function and disable maxlength for functions
* @this HTMLSelectElement
*/
function functionChange() {
	let input = this.form[this.name.replace(/^function/, 'fields')];
	if (input) { // undefined with the set data type
		const func = selectValue(this);
		if (func == 'SQL') { // raw expression - use a plain <input>, e.g. instead of <select> from the edit-foreign plugin
			if (!input.origElement) {
				const text = document.createElement('input');
				text.name = input.name;
				text.value = selectValue(input);
				text.origElement = input;
				input.replaceWith(text);
				input = text;
			}
		} else if (input.origElement) { // revive the original element (keeps its type, e.g. number for +)
			input.replaceWith(input.origElement);
			input = input.origElement;
		}
		if (func) { // the length is not limited when a function is applied
			if (input.origMaxLength === undefined) {
				input.origMaxLength = input.dataset.maxlength;
			}
			delete input.dataset.maxlength;
		} else if (input.origMaxLength >= 0) {
			input.dataset.maxlength = input.origMaxLength;
			delete input.origMaxLength;
		}
		alterClass(input, 'hidden', /^(now|getdate|current_date|current_timestamp|uuid)$/.test(func)); // these functions take no argument
		maxlengthCheck(input); // not fire() - the input event would also run skipOriginal() on the parent <td>
	}
	helpClose();
}

/** Skip 'original' when typing
* @param {number} first
* @this HTMLTableCellElement
*/
function skipOriginal(first) {
	const fnSelect = qs('select', this.previousSibling);
	if (fnSelect.selectedIndex < first) {
		fnSelect.selectedIndex = first;
	}
}

/** Add new field in schema-less edit
* @this HTMLInputElement
*/
function fieldChange() {
	const tr = this.closest('tr');
	const row = cloneNode(tr);
	for (const input of qsa('input', row)) {
		input.value = '';
	}
	// keep value in <select> (function)
	tr.parentNode.append(row);
	this.removeAttribute('data-oninput'); // the appended row adds the next one
}



/** Create AJAX request
* @param {string} url
* @param {function(XMLHttpRequest)} callback
* @param {?string} [data]
* @param {?string} [message] null to not report errors
* @return {XMLHttpRequest}
* @uses offlineMessage
*/
function ajax(url, callback, data, message) {
	const request = new XMLHttpRequest();
	const ajaxStatus = qs('#ajaxstatus');
	// empty the live region instead of hiding it, display: none would remove it from the accessibility tree
	ajaxStatus.innerHTML = (message ? '<div class="message">' + message + '</div>' : '');
	request.open((data ? 'POST' : 'GET'), url);
	if (data) {
		request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	}
	if (new URL(url, location).origin == location.origin) { // cross-origin would be preflighted and is_ajax() is only ours
		request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	}
	request.onreadystatechange = () => {
		if (request.readyState == 4) {
			if (/^2/.test(request.status)) {
				callback(request);
			} else if (message !== null) {
				ajaxStatus.innerHTML = (request.status ? request.responseText : '<div class="error">' + offlineMessage + '</div>');
			}
		}
	};
	request.send(data);
	return request;
}

/** Use setHtml(key, value) for JSON response
* @param {string} url
* @return {boolean} false
*/
function ajaxSetHtml(url) {
	ajax(url, request => {
		const data = JSON.parse(request.responseText);
		for (const key in data) {
			setHtml(key, data[key]);
		}
	});
	return false;
}

let editChanged; // used by plugins
let adminerHighlighter = () => {}; // overwritten by syntax highlighters

/** Save form contents through AJAX
* @param {string} message
* @return {boolean} false when sent by AJAX
* @this HTMLInputElement submit button
*/
function ajaxForm(message) {
	const button = this;
	const form = button.form;
	let data = formData(form, button);
	if (data === null) {
		return true; // files can't be sent by AJAX, submit the form
	}

	let url = form.action;
	if (!/post/i.test(form.method)) {
		url = url.replace(/\?.*/, '') + '?' + data;
		data = '';
	}
	ajax(url, request => {
		const ajaxstatus = qs('#ajaxstatus');
		setHtml('ajaxstatus', request.responseText);
		if (qs('.message', ajaxstatus)) { // success
			editChanged = null;
		}
		adminerHighlighter(qsa('code', ajaxstatus));
		messagesPrint(ajaxstatus);
	}, data, message);
	return false;
}



/** Display edit field
* @param {MouseEvent} event
* @param {number} text display textarea instead of input, 2 - load long text
* @param {string} [warning] warning to display
* @return {boolean}
* @this HTMLElement
*/
function selectClick(event, text, warning) {
	const td = this;
	const target = event.target;
	if (!isCtrl(event) || (td.firstElementChild && td.firstElementChild.matches('input, textarea')) || target.matches('a')) {
		return;
	}
	if (warning) {
		alert(warning);
		return true;
	}
	const original = td.innerHTML;
	text = text || /\n/.test(original);
	const input = document.createElement(text ? 'textarea' : 'input');
	input.onkeydown = event => {
		if (isEscape(event)) {
			inputBlur();
			td.innerHTML = original;
		}
	};

	const pos = getSelection().anchorOffset;
	let value = (td.firstChild && td.firstChild.alt) || td.textContent;
	const tdStyle = window.getComputedStyle(td, null);

	input.style.width = Math.max(td.clientWidth - parseFloat(tdStyle.paddingLeft) - parseFloat(tdStyle.paddingRight), (text ? 200 : 20)) + 'px';

	if (text) {
		input.rows = value.split('\n').length;
	}
	if (qsa('i', td).length) { // <i> - NULL
		value = '';
	}
	td.innerHTML = '';
	td.append(input);
	const save = qs('#save');
	if (save) { // missing if a plugin returns false from selectCommandPrint()
		save.disabled = false;
	}
	setupSubmitHighlight(td);
	input.focus();
	if (text == 2) { // long text
		return ajax(location.href + '&' + urlEscape(td.id) + '=', request => {
			if (request.responseText) {
				input.value = request.responseText;
				input.name = td.id;
			}
		});
	}
	input.value = value;
	input.name = td.id;
	input.selectionStart = pos;
	input.selectionEnd = pos;
	return true;
}



/** Load and display next page in select
* @param {string} loading
* @return {boolean} false
* @this HTMLLinkElement
*/
function selectLoadMore(loading) {
	const a = this;
	const title = a.innerHTML;
	const href = a.href;
	if (href) {
		ajax(href, request => {
			const tbody = document.createElement('tbody');
			tbody.innerHTML = request.responseText;
			adminerHighlighter(qsa('code', tbody));
			qs('#table').tBodies[0].append(...tbody.children); // keep the rows in the original TBODY to continue the .odds highlighting
			const next = request.getResponseHeader('X-Next-Page'); // the response contains only the rows so the URL of the following page is sent in a header
			if (next) {
				a.href = next;
				a.innerHTML = title;
			} else {
				a.remove();
			}
		});
		a.innerHTML = loading;
		a.removeAttribute('href');
		return false;
	}
}



/** Setup highlighting of default submit button on form field focus
* @param {HTMLElement} parent
*/
function setupSubmitHighlight(parent) {
	qsa('input, select, textarea', parent).forEach(setupSubmitHighlightInput);
}

/** Setup submit highlighting for single element
* @param {HTMLElement} input
*/
function setupSubmitHighlightInput(input) {
	if (!/submit|button|image|file/.test(input.type)) {
		addEvent(input, 'focus', inputFocus);
		addEvent(input, 'blur', inputBlur);
		if (document.activeElement === input) {
			fire(input, 'focus'); // focus event was missed, e.g. jush.textarea() focuses the <pre> before this
		}
	}
}

/** Highlight default submit button
* @this HTMLInputElement
*/
function inputFocus() {
	inputBlur();
	alterClass(findDefaultSubmit(this), 'default', true);
}

/** Unhighlight default submit button */
function inputBlur() {
	alterClass(qs('input.default'), 'default'); // not findDefaultSubmit() - some buttons could get disabled since the highlighting
}

/** Find submit button used by Enter
* @param {HTMLElement} el
* @return {?HTMLInputElement}
*/
function findDefaultSubmit(el) {
	if (el.jushTextarea) {
		el = el.jushTextarea;
	}
	return (el.form ? qs('input[type=submit]:not([hidden]):enabled', el.form) : null); // the browser also skips disabled buttons in the implicit submission
}



/** Add event listener
* @param {HTMLElement} el
* @param {string} action without 'on'
* @param {function} handler
*/
function addEvent(el, action, handler) {
	el.addEventListener(action, handler);
}

/** Dispatch an event to run the handlers registered by the data attributes
* @param {?HTMLElement} el
* @param {string} type event name without 'on'
* @return {boolean} false if a handler has canceled the event
*/
function fire(el, type) {
	// bubbles - the handlers are delegated from the document; cancelable - the caller can skip the default action
	return (el ? el.dispatchEvent(new Event(type, {bubbles: true, cancelable: true})) : true);
}

/** Clone node and setup submit highlighting
* @param {HTMLElement} el
* @return {HTMLElement}
*/
function cloneNode(el) {
	const el2 = el.cloneNode(true);
	const selector = 'input, select, button';
	const origEls = qsa(selector, el);
	const cloneEls = qsa(selector, el2);
	for (const [i, origEl] of origEls.entries()) {
		for (const key in origEl) {
			if (/^on/.test(key) && origEl[key]) {
				cloneEls[i][key] = origEl[key];
			}
		}
	}
	setupSubmitHighlight(el2);
	return el2;
}

// documentElement - this file is loaded in <head> where document.body doesn't exist yet
alterClass(document.documentElement, 'js', true);
alterClass(document.documentElement, 'nojs'); // two calls, not classList.replace() - unsupported in Chrome < 61
alterClass(document.documentElement, 'insecure', !navigator.clipboard); // clipboard is undefined also in an insecure context

mixin(document, {
	onchange: delegateEvent,
	onclick: bodyClick, // calls delegateEvent() itself
	ondblclick: delegateEvent,
	oninput: bodyInput, // calls delegateEvent() itself
	onkeydown: bodyKeydown, // calls delegateEvent() itself
	onmousedown: delegateEvent,
	onmouseout: delegateEvent,
	onmouseover: delegateEvent,
	onsearch: delegateEvent, // WebKit only, it bubbles
	onsubmit: bodySubmit, // calls delegateEvent() itself
});
