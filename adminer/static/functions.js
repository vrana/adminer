'use strict';

/** Get first element by selector
* @param string
* @param [HTMLElement] defaults to document
* @return HTMLElement
*/
function qs(selector, context) {
	return (context || document).querySelector(selector);
}

/** Get last element by selector
* @param string
* @param [HTMLElement] defaults to document
* @return HTMLElement
*/
function qsl(selector, context) {
	const els = qsa(selector, context);
	return els[els.length - 1];
}

/** Get all elements by selector
* @param string
* @param [HTMLElement] defaults to document
* @return NodeList
*/
function qsa(selector, context) {
	return (context || document).querySelectorAll(selector);
}

/** Return a function calling fn with the next arguments
* @param function
* @param ...
* @return function with preserved this
*/
function partial(fn) {
	const args = Array.apply(null, arguments).slice(1);
	return function () {
		return fn.apply(this, args);
	};
}

/** Return a function calling fn with the first parameter and then the next arguments
* @param function
* @param ...
* @return function with preserved this
*/
function partialArg(fn) {
	const args = Array.apply(null, arguments);
	return function (arg) {
		args[0] = arg;
		return fn.apply(this, args);
	};
}

/** Assign values from source to target
* @param Object
* @param Object
*/
function mixin(target, source) {
	for (const key in source) {
		target[key] = source[key];
	}
}

/** Add or remove CSS class
* @param HTMLElement
* @param string
* @param [boolean]
*/
function alterClass(el, className, enable) {
	if (el) {
		el.classList[enable ? 'add' : 'remove'](className);
	}
}

/** Toggle visibility
* @param string
* @return boolean false
*/
function toggle(id) {
	const el = qs('#' + id);
	el && el.classList.toggle('hidden');
	return false;
}

/** Set permanent cookie
* @param string
* @param number
*/
function cookie(assign, days) {
	const date = new Date();
	date.setDate(date.getDate() + days);
	document.cookie = assign + '; expires=' + date;
}

/** Verify current Adminer version
* @param string
* @param string own URL base
* @param string
*/
function verifyVersion(current, url, token) {
	cookie('adminer_version=0', 1);
	const iframe = document.createElement('iframe');
	iframe.src = 'https://www.adminer.org/version/?current=' + current;
	iframe.frameBorder = 0;
	iframe.marginHeight = 0;
	iframe.scrolling = 'no';
	iframe.style.width = '7ex';
	iframe.style.height = '1.25em';
	iframe.style.display = 'none';
	addEventListener('message', event => {
		if (event.origin == 'https://www.adminer.org') {
			const match = /version=(.+)/.exec(event.data);
			if (match) {
				cookie('adminer_version=' + match[1], 1);
				ajax(url + 'script=version', () => { }, event.data + '&token=' + token);
			}
		}
	}, false);
	qs('#version').appendChild(iframe);
}

/** Get value of select
* @param HTMLElement <select> or <input>
* @return string
*/
function selectValue(select) {
	if (!select.selectedIndex) {
		return select.value;
	}
	const selected = select.options[select.selectedIndex];
	return ((selected.attributes.value || {}).specified ? selected.value : selected.text);
}

/** Verify if element has a specified tag name
* @param HTMLElement
* @param string regular expression
* @return boolean
*/
function isTag(el, tag) {
	const re = new RegExp('^(' + tag + ')$', 'i');
	return el && re.test(el.tagName);
}

/** Get parent node with specified tag name
* @param HTMLElement
* @param string regular expression
* @return HTMLElement
*/
function parentTag(el, tag) {
	while (el && !isTag(el, tag)) {
		el = el.parentNode;
	}
	return el;
}

/** Set checked class
* @param HTMLInputElement
*/
function trCheck(el) {
	const tr = parentTag(el, 'tr');
	alterClass(tr, 'checked', el.checked);
	if (el.form && el.form['all'] && el.form['all'].onclick) { // Opera treats form.all as document.all
		el.form['all'].onclick();
	}
}

/** Fill number of selected items
* @param string
* @param string
* @uses thousandsSeparator
*/
function selectCount(id, count) {
	setHtml(id, (count === '' ? '' : '(' + (count + '').replace(/\B(?=(\d{3})+$)/g, thousandsSeparator) + ')'));
	const el = qs('#' + id);
	if (el) {
		for (const input of qsa('input', el.parentNode.parentNode)) {
			if (input.type == 'submit') {
				input.disabled = (count == '0');
			}
		}
	}
}

/** Check all elements matching given name
* @param RegExp
* @this HTMLInputElement
*/
function formCheck(name) {
	for (const elem of this.form.elements) {
		if (name.test(elem.name)) {
			elem.checked = this.checked;
			trCheck(elem);
		}
	}
}

/** Check all rows in <table class="checkable">
*/
function tableCheck() {
	for (const input of qsa('table.checkable td:first-child input')) {
		trCheck(input);
	}
}

/** Uncheck single element
* @param string
*/
function formUncheck(id) {
	const el = qs('#' + id);
	el.checked = false;
	trCheck(el);
}

/** Get number of checked elements matching given name
* @param HTMLInputElement
* @param RegExp
* @return number
*/
function formChecked(input, name) {
	let checked = 0;
	for (const el of input.form.elements) {
		if (name.test(el.name) && el.checked) {
			checked++;
		}
	}
	return checked;
}

/** Select clicked row
* @param MouseEvent
* @param [boolean] force click
*/
function tableClick(event, click) {
	const td = parentTag(event.target, 'td');
	let text;
	if (td && (text = td.dataset.text)) {
		if (selectClick.call(td, event, +text, td.dataset.warning)) {
			return;
		}
	}
	click = (click || !window.getSelection || getSelection().isCollapsed);
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
	if (click) {
		el.checked = !el.checked;
		el.onclick && el.onclick();
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
* @param MouseEvent
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
* @param string
* @param string undefined to set parentNode to empty string
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

/** Find node position
* @param Node
* @return number
*/
function nodePosition(el) {
	let pos = 0;
	while ((el = el.previousSibling)) {
		pos++;
	}
	return pos;
}

/** Go to the specified page
* @param string
* @param string
*/
function pageClick(href, page) {
	if (!isNaN(page) && page) {
		location.href = href + (page != 1 ? '&page=' + (page - 1) : '');
	}
}



/** Display items in menu
* @param MouseEvent
* @this HTMLElement
*/
function menuOver(event) {
	const a = event.target;
	if (isTag(a, 'a|span') && a.offsetLeft + a.offsetWidth > a.parentNode.offsetWidth - 15) { // 15 - ellipsis
		this.style.overflow = 'visible';
	}
}

/** Hide items in menu
* @this HTMLElement
*/
function menuOut() {
	this.style.overflow = 'hidden';
}



/** Add row in select fieldset
* @this HTMLSelectElement
*/
function selectAddRow() {
	const field = this;
	const row = cloneNode(field.parentNode);
	field.onchange = selectFieldChange;
	field.onchange();
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
	field.parentNode.parentNode.appendChild(row);
}

/** Prevent onsearch handler on Enter
* @param KeyboardEvent
* @this HTMLInputElement
*/
function selectSearchKeydown(event) {
	if (event.keyCode == 13 || event.keyCode == 10) {
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



/** Toggle column context menu
* @param [string] extra class name
* @this HTMLElement
*/
function columnMouse(className) {
	for (const span of qsa('span', this)) {
		if (/column/.test(span.className)) {
			span.className = 'column' + (className || '');
		}
	}
}



/** Fill column in search field
* @param string
* @return boolean false
*/
function selectSearch(name) {
	let el = qs('#fieldset-search');
	el.className = '';
	const divs = qsa('div', el);
	let i, div;
	for (i=0; i < divs.length; i++) {
		div = divs[i];
		el = qs('[name$="[col]"]', div);
		if (el && selectValue(el) == name) {
			break;
		}
	}
	if (i == divs.length) {
		div.firstChild.value = name;
		div.firstChild.onchange();
	}
	qs('[name$="[val]"]', div).focus();
	return false;
}


/** Check if Ctrl key (Command key on Mac) was pressed
* @param KeyboardEvent|MouseEvent
* @return boolean
*/
function isCtrl(event) {
	return (event.ctrlKey || event.metaKey) && !event.altKey; // shiftKey allowed
}



/** Send form by Ctrl+Enter on <select> and <textarea>
* @param KeyboardEvent
* @param [string]
* @return boolean
*/
function bodyKeydown(event, button) {
	eventStop(event);
	let target = event.target;
	if (target.jushTextarea) {
		target = target.jushTextarea;
	}
	if (isCtrl(event) && (event.keyCode == 13 || event.keyCode == 10) && isTag(target, 'select|textarea|input')) { // 13|10 - Enter
		target.blur();
		if (target.form[button]) {
			target.form[button].click();
		} else {
			target.form.dispatchEvent(new Event('submit', {bubbles: true}));
			target.form.submit();
		}
		target.focus();
		return false;
	}
	return true;
}

/** Open form to a new window on Ctrl+click or Shift+click
* @param MouseEvent
*/
function bodyClick(event) {
	const target = event.target;
	if ((isCtrl(event) || event.shiftKey) && target.type == 'submit' && isTag(target, 'input')) {
		target.form.target = '_blank';
		setTimeout(() => {
			// if (isCtrl(event)) { focus(); } doesn't work
			target.form.target = '';
		}, 0);
	}
}



/** Change focus by Ctrl+Shift+Up or Ctrl+Shift+Down
* @param KeyboardEvent
* @return boolean
*/
function editingKeydown(event) {
	if ((event.keyCode == 40 || event.keyCode == 38) && isCtrl(event)) { // 40 - Down, 38 - Up
		const target = event.target;
		const sibling = (event.keyCode == 40 ? 'nextSibling' : 'previousSibling');
		let el = target.parentNode.parentNode[sibling];
		if (el && (isTag(el, 'tr') || (el = el[sibling])) && isTag(el, 'tr') && (el = el.childNodes[nodePosition(target.parentNode)]) && (el = el.childNodes[nodePosition(target)])) {
			el.focus();
		}
		return false;
	}
	if (event.shiftKey && !bodyKeydown(event, 'insert')) {
		return false;
	}
	return true;
}

/** Disable maxlength for functions
* @this HTMLSelectElement
*/
function functionChange() {
	const input = this.form[this.name.replace(/^function/, 'fields')];
	if (input) { // undefined with the set data type
		if (selectValue(this)) {
			if (input.origType === undefined) {
				input.origType = input.type;
				input.origMaxLength = input.dataset.maxlength;
			}
			delete input.dataset.maxlength;
			input.type = 'text';
		} else if (input.origType) {
			input.type = input.origType;
			if (input.origMaxLength >= 0) {
				input.dataset.maxlength = input.origMaxLength;
			}
		}
		oninput({target: input});
	}
	helpClose();
}

/** Skip 'original' when typing
* @param number
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
	const row = cloneNode(parentTag(this, 'tr'));
	for (const input of qsa('input', row)) {
		input.value = '';
	}
	// keep value in <select> (function)
	parentTag(this, 'table').appendChild(row);
	this.oninput = () => { };
}



/** Create AJAX request
* @param string
* @param function (XMLHttpRequest)
* @param [string]
* @param [string]
* @return XMLHttpRequest or false in case of an error
* @uses offlineMessage
*/
function ajax(url, callback, data, message) {
	const request = new XMLHttpRequest();
	if (request) {
		const ajaxStatus = qs('#ajaxstatus');
		if (message) {
			ajaxStatus.innerHTML = '<div class="message">' + message + '</div>';
		}
		alterClass(ajaxStatus, 'hidden', !message);
		request.open((data ? 'POST' : 'GET'), url);
		if (data) {
			request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}
		request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		request.onreadystatechange = () => {
			if (request.readyState == 4) {
				if (/^2/.test(request.status)) {
					callback(request);
				} else if (message !== null) {
					ajaxStatus.innerHTML = (request.status ? request.responseText : '<div class="error">' + offlineMessage + '</div>');
					alterClass(ajaxStatus, 'hidden');
				}
			}
		};
		request.send(data);
	}
	return request;
}

/** Use setHtml(key, value) for JSON response
* @param string
* @return boolean false for success
*/
function ajaxSetHtml(url) {
	return !ajax(url, request => {
		const data = JSON.parse(request.responseText);
		for (const key in data) {
			setHtml(key, data[key]);
		}
	});
}

let editChanged; // used by plugins
let adminerHighlighter = els => {}; // overwritten by syntax highlighters

/** Save form contents through AJAX
* @param HTMLFormElement
* @param string
* @param [HTMLInputElement]
* @return boolean
*/
function ajaxForm(form, message, button) {
	let data = [];
	for (const el of form.elements) {
		if (el.name && !el.disabled) {
			if (/^file$/i.test(el.type) && el.value) {
				return false;
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
	return ajax(url, request => {
		const ajaxstatus = qs('#ajaxstatus');
		setHtml('ajaxstatus', request.responseText);
		if (qs('.message', ajaxstatus)) { // success
			editChanged = null;
		}
		adminerHighlighter(qsa('code', ajaxstatus));
		messagesPrint(ajaxstatus);
	}, data, message);
}



/** Display edit field
* @param MouseEvent
* @param number display textarea instead of input, 2 - load long text
* @param [string] warning to display
* @return boolean
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
		if (event.keyCode == 27 && !event.shiftKey && !event.altKey && !isCtrl(event)) { // 27 - Esc
			inputBlur.apply(input);
			td.innerHTML = original;
		}
	};

	const pos = getSelection().anchorOffset;
	let value = (td.firstChild && td.firstChild.alt) || td.textContent;
	const tdStyle = window.getComputedStyle(td, null);

	input.style.width = Math.max(td.clientWidth - parseFloat(tdStyle.paddingLeft) - parseFloat(tdStyle.paddingRight), (text ? 200 : 20)) + 'px';

	if (text) {
		let rows = 1;
		value.replace(/\n/g, () => {
			rows++;
		});
		input.rows = rows;
	}
	if (qsa('i', td).length) { // <i> - NULL
		value = '';
	}
	td.innerHTML = '';
	td.appendChild(input);
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
* @param number
* @param string
* @return boolean false for success
* @this HTMLLinkElement
*/
function selectLoadMore(limit, loading) {
	const a = this;
	const title = a.innerHTML;
	const href = a.href;
	a.innerHTML = loading;
	if (href) {
		a.removeAttribute('href');
		return !ajax(href, request => {
			const tbody = document.createElement('tbody');
			tbody.innerHTML = request.responseText;
			adminerHighlighter(qsa('code', tbody));
			qs('#table').appendChild(tbody);
			if (tbody.children.length < limit) {
				a.remove();
			} else {
				a.href = href.replace(/\d+$/, page => +page + 1);
				a.innerHTML = title;
			}
		});
	}
}



/** Stop event propagation
* @param Event
*/
function eventStop(event) {
	event.stopPropagation();
}



/** Setup highlighting of default submit button on form field focus
* @param HTMLElement
*/
function setupSubmitHighlight(parent) {
	for (const input of qsa('input, select, textarea', parent)) {
		setupSubmitHighlightInput(input);
	}
}

/** Setup submit highlighting for single element
* @param HTMLElement
*/
function setupSubmitHighlightInput(input) {
	if (!/submit|button|image|file/.test(input.type)) {
		addEvent(input, 'focus', inputFocus);
		addEvent(input, 'blur', inputBlur);
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
* @param HTMLElement
* @return HTMLInputElement
*/
function findDefaultSubmit(el) {
	if (el.jushTextarea) {
		el = el.jushTextarea;
	}
	if (!el.form) {
		return null;
	}
	for (const input of qsa('input', el.form)) {
		if (input.type == 'submit' && !input.style.zIndex) {
			return input;
		}
	}
}



/** Add event listener
* @param HTMLElement
* @param string without 'on'
* @param function
*/
function addEvent(el, action, handler) {
	el.addEventListener(action, handler, false);
}

/** Clone node and setup submit highlighting
* @param HTMLElement
* @return HTMLElement
*/
function cloneNode(el) {
	const el2 = el.cloneNode(true);
	const selector = 'input, select';
	const origEls = qsa(selector, el);
	const cloneEls = qsa(selector, el2);
	for (let i=0; i < origEls.length; i++) {
		const origEl = origEls[i];
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

addEvent(document, 'click', event => {
	if (!qs('#foot').contains(event.target)) {
		alterClass(qs('#foot'), 'foot', true);
	}
});
