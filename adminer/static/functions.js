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

/** Verify if element has a specified tag name
* @param {?HTMLElement} el
* @param {string} tag regular expression
* @return {boolean}
*/
function isTag(el, tag) {
	const re = new RegExp('^(' + tag + ')$', 'i');
	return el && re.test(el.tagName);
}

/** Get parent node with specified tag name
* @param {?HTMLElement} el
* @param {string} tag regular expression
* @return {?HTMLElement}
*/
function parentTag(el, tag) {
	while (el && !isTag(el, tag)) {
		el = el.parentNode;
	}
	return el;
}

/** Set checked class
* @param {HTMLInputElement} el
*/
function trCheck(el) {
	const tr = parentTag(el, 'tr');
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
	const td = parentTag(event.target, 'td');
	let text;
	if (td && (text = td.dataset.text)) {
		if (selectClick.call(td, event, +text, td.dataset.warning)) {
			return;
		}
	}
	// dblclick undoes the check by the first click of the double click - the user is only selecting a word
	let click = (event.type == 'dblclick' || getSelection().isCollapsed);
	let el = event.target;
	while (!isTag(el, 'tr')) {
		if (isTag(el, 'table|a|input|textarea')) {
			if (el.type != 'checkbox') {
				return;
			}
			checkboxClick.call(el, event);
			click = false;
		}
		el = el.parentNode;
		if (!el) { // Ctrl+click on text fields hides the element
			return;
		}
	}
	el = el.firstChild.firstChild;
	if (!el || el.type != 'checkbox') { // the header row of the process list and of the database list has an empty first cell
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
		for (const input of qsa('input', parentTag(this, 'table'))) {
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
	if (isTag(a, 'a|span') && a.offsetLeft + a.offsetWidth > a.parentNode.offsetWidth) {
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

/** Disable Save and continue edit after changing a value identifying the row
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

/** Prevent onsearch handler on Enter
* @param {KeyboardEvent} event
* @this HTMLInputElement
*/
function selectSearchKeydown(event) {
	if (event.key == 'Enter') {
		this.onsearch = () => { };
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



/** Send form by Ctrl+Enter on <select> and <textarea>, close the menu by Esc
* @param {KeyboardEvent} event
* @param {string} [button]
* @return {boolean}
*/
function bodyKeydown(event, button) {
	event.stopPropagation();
	if (event.key == 'Escape' && !event.shiftKey && !event.altKey && !isCtrl(event)) {
		menuClose();
	}
	let target = event.target;
	if (target.jushTextarea) {
		target = target.jushTextarea;
	}
	if (isCtrl(event) && event.key == 'Enter' && isTag(target, 'select|textarea|input')) {
		target.blur();
		if (target.form[button]) {
			target.form[button].click();
		} else {
			fire(target.form, 'submit'); // submit() doesn't dispatch the event
			target.form.submit();
		}
		target.focus();
		return false;
	}
	return true;
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
	if ((isCtrl(event) || event.shiftKey) && target.type == 'submit' && isTag(target, 'input')) {
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

/** Call handlers registered by data-on<event> attributes between the event target and the <html> element
* @param {Event} event
*/
function delegateEvent(event) {
	const attr = 'data-on' + event.type;
	for (let el = event.target; el && el.getAttribute; el = el.parentNode) {
		const value = el.getAttribute(attr);
		const match = (value ? /^(\w+)\((.*)\)$/.exec(value) : null); // e.g. confirmClick("Are you sure?")
		// only a function declared by Adminer or by a plugin - it creates a non-configurable property of window, built-ins are configurable or not own properties at all, so window['eval'] or window['setTimeout'] is never used
		const desc = (match ? Object.getOwnPropertyDescriptor(window, match[1]) : null);
		const handler = (desc && !desc.configurable && typeof desc.value == 'function' ? desc.value : null);
		let args;
		try {
			args = (handler ? JSON.parse('[' + match[2] + ']') : null); // JSON.parse() - the arguments must never be evaluated as code
		} catch (e) { // empty - an invalid value is reported below together with an unknown handler
		}
		if (args) {
			// the handler gets the arguments from the attribute followed by the event
			const result = handler.apply(el, args.concat(event));
			if (result === false) {
				event.preventDefault();
			}
			if (result !== undefined) {
				break; // the handler handled the event, don't call the handlers on ancestor elements
			}
		} else if (value) {
			// not lang() - it reports a bug in Adminer or in a plugin, the element would do nothing at all otherwise
			console.error('Invalid handler in ' + attr + "='" + value + "'", el);
		}
	}
}



/** Change focus by Ctrl+Shift+Up or Ctrl+Shift+Down
* @param {KeyboardEvent} event
* @return {boolean}
*/
function editingKeydown(event) {
	if (/^Arrow(Down|Up)$/.test(event.key) && isCtrl(event)) {
		const target = event.target;
		const cell = parentTag(target, 'td|th'); // not parentNode - the NULL checkbox is wrapped in a <label>
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
		return bodyKeydown(event, 'insert');
	}
	return true;
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
		oninput({target: input});
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
	const tr = parentTag(this, 'tr');
	const row = cloneNode(tr);
	for (const input of qsa('input', row)) {
		input.value = '';
	}
	// keep value in <select> (function)
	tr.parentNode.append(row);
	this.oninput = () => { };
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
	let data = [];
	for (const el of form.elements) {
		if (el.name && !el.disabled) {
			if (/^file$/i.test(el.type) && el.value) {
				return true; // files can't be sent by AJAX, submit the form
			}
			if (!/^(checkbox|radio|submit|file)$/i.test(el.type) || el.checked || el == button) {
				data.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(isTag(el, 'select') ? selectValue(el) : el.value));
			}
		}
	}
	data = data.join('&');

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
	if (!isCtrl(event) || isTag(td.firstChild, 'input|textarea') || isTag(target, 'a')) {
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
		if (event.key == 'Escape' && !event.shiftKey && !event.altKey && !isCtrl(event)) {
			inputBlur.call(input);
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
	setupSubmitHighlight(td);
	input.focus();
	if (text == 2) { // long text
		return ajax(location.href + '&' + encodeURIComponent(td.id) + '=', request => {
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
* @param {number} limit
* @param {string} loading
* @return {boolean} false
* @this HTMLLinkElement
*/
function selectLoadMore(limit, loading) {
	const a = this;
	const title = a.innerHTML;
	const href = a.href;
	if (href) {
		ajax(href, request => {
			const tbody = document.createElement('tbody');
			tbody.innerHTML = request.responseText;
			adminerHighlighter(qsa('code', tbody));
			const rows = tbody.children.length;
			qs('#table').tBodies[0].append(...tbody.children); // keep the rows in the original TBODY to continue the .odds highlighting
			if (rows < limit) {
				a.remove();
			} else {
				a.href = href.replace(/\d+$/, page => +page + 1); //! update &next=
				a.innerHTML = title;
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
			inputFocus.call(input); // focus event was missed, e.g. jush.textarea() focuses the <pre> before this
		}
	}
}

/** Highlight default submit button
* @this HTMLInputElement
*/
function inputFocus() {
	alterClass(findDefaultSubmit(this), 'default', true);
}

/** Unhighlight default submit button
* @this HTMLInputElement
*/
function inputBlur() {
	alterClass(findDefaultSubmit(this), 'default');
}

/** Find submit button used by Enter
* @param {HTMLElement} el
* @return {?HTMLInputElement}
*/
function findDefaultSubmit(el) {
	if (el.jushTextarea) {
		el = el.jushTextarea;
	}
	return (el.form ? qs('input[type=submit]:not([hidden])', el.form) : null);
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
*/
function fire(el, type) {
	if (el) {
		el.dispatchEvent(new Event(type, {bubbles: true})); // bubbles - the handlers are delegated from the document
	}
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

oninput = event => {
	const target = event.target;
	const maxLength = target.dataset.maxlength;
	alterClass(target, 'maxlength', target.value && maxLength != null && target.value.length > maxLength); // maxLength could be 0
};

// documentElement - this file is loaded in <head> where document.body doesn't exist yet
alterClass(document.documentElement, 'js', true);
alterClass(document.documentElement, 'nojs'); // two calls, not classList.replace() - unsupported in Chrome < 61
alterClass(document.documentElement, 'insecure', !navigator.clipboard); // clipboard is undefined also in an insecure context

mixin(document, {
	onchange: delegateEvent,
	onclick: bodyClick, // calls delegateEvent() itself
	ondblclick: delegateEvent,
	onkeydown: bodyKeydown,
	onmouseout: delegateEvent,
	onmouseover: delegateEvent,
});
