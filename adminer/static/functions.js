
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
	var els = qsa(selector, context);
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
	var args = Array.apply(null, arguments).slice(1);
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
	var args = Array.apply(null, arguments);
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
	for (var key in source) {
		target[key] = source[key];
	}
}

/** Add or remove CSS class
* @param HTMLElement
* @param string
* @param [bool]
*/
function alterClass(el, className, enable) {
	if (el) {
		el.className = el.className.replace(RegExp('(^|\\s)' + className + '(\\s|$)'), '$2') + (enable ? ' ' + className : '');
	}
}

/** Toggle visibility
* @param string
* @return boolean false
*/
function toggle(id) {
	var el = qs('#' + id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return false;
}

/** Set permanent cookie
* @param string
* @param number
* @param string optional
*/
function cookie(assign, days) {
	var date = new Date();
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
	var iframe = document.createElement('iframe');
	iframe.src = 'https://www.adminer.org/version/?current=' + current;
	iframe.frameBorder = 0;
	iframe.marginHeight = 0;
	iframe.scrolling = 'no';
	iframe.style.width = '7ex';
	iframe.style.height = '1.25em';
	if (window.postMessage && window.addEventListener) {
		iframe.style.display = 'none';
		addEventListener('message', function (event) {
			if (event.origin == 'https://www.adminer.org') {
				var match = /version=(.+)/.exec(event.data);
				if (match) {
					cookie('adminer_version=' + match[1], 1);
					ajax(url + 'script=version', function () {
					}, event.data + '&token=' + token);
				}
			}
		}, false);
	}
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
	var selected = select.options[select.selectedIndex];
	return ((selected.attributes.value || {}).specified ? selected.value : selected.text);
}

/** Verify if element has a specified tag name
* @param HTMLElement
* @param string regular expression
* @return bool
*/
function isTag(el, tag) {
	var re = new RegExp('^(' + tag + ')$', 'i');
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
	var tr = parentTag(el, 'tr');
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
	var el = qs('#' + id);
	if (el) {
		var inputs = qsa('input', el.parentNode.parentNode);
		for (var i = 0; i < inputs.length; i++) {
			var input = inputs[i];
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
	var elems = this.form.elements;
	for (var i=0; i < elems.length; i++) {
		if (name.test(elems[i].name)) {
			elems[i].checked = this.checked;
			trCheck(elems[i]);
		}
	}
}

/** Check all rows in <table class="checkable">
*/
function tableCheck() {
	var inputs = qsa('table.checkable td:first-child input');
	for (var i=0; i < inputs.length; i++) {
		trCheck(inputs[i]);
	}
}

/** Uncheck single element
* @param string
*/
function formUncheck(id) {
	var el = qs('#' + id);
	el.checked = false;
	trCheck(el);
}

/** Get number of checked elements matching given name
* @param HTMLInputElement
* @param RegExp
* @return number
*/
function formChecked(el, name) {
	var checked = 0;
	var elems = el.form.elements;
	for (var i=0; i < elems.length; i++) {
		if (name.test(elems[i].name) && elems[i].checked) {
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
	var td = parentTag(getTarget(event), 'td');
	var text;
	if (td && (text = td.getAttribute('data-text'))) {
		if (selectClick.call(td, event, +text, td.getAttribute('data-warning'))) {
			return;
		}
	}
	click = (click || !window.getSelection || getSelection().isCollapsed);
	var el = getTarget(event);
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

var lastChecked;

/** Shift-click on checkbox for multiple selection.
* @param MouseEvent
* @this HTMLInputElement
*/
function checkboxClick(event) {
	if (!this.name) {
		return;
	}
	if (event.shiftKey && (!lastChecked || lastChecked.name == this.name)) {
		var checked = (lastChecked ? lastChecked.checked : true);
		var inputs = qsa('input', parentTag(this, 'table'));
		var checking = !lastChecked;
		for (var i=0; i < inputs.length; i++) {
			var input = inputs[i];
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
	var el = qs('[id="' + id.replace(/[\\"]/g, '\\$&') + '"]'); // database name is used as ID
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
	var pos = 0;
	while (el = el.previousSibling) {
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
	var a = getTarget(event);
	if (isTag(a, 'a|span') && a.offsetLeft + a.offsetWidth > a.parentNode.offsetWidth - 15) { // 15 - ellipsis
		this.style.overflow = 'visible';
	}
}

/** Hide items in menu
* @this HTMLElement
*/
function menuOut() {
	this.style.overflow = 'auto';
}



/** Add row in select fieldset
* @this HTMLSelectElement
*/
function selectAddRow() {
	var field = this;
	var row = cloneNode(field.parentNode);
	field.onchange = selectFieldChange;
	field.onchange();
	var selects = qsa('select', row);
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/[a-z]\[\d+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = qsa('input', row);
	for (var i=0; i < inputs.length; i++) {
		inputs[i].name = inputs[i].name.replace(/[a-z]\[\d+/, '$&1');
		inputs[i].className = '';
		if (inputs[i].type == 'checkbox') {
			inputs[i].checked = false;
		} else {
			inputs[i].value = '';
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
		this.onsearch = function () {
		};
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



/** Toggles column context menu
* @param [string] extra class name
* @this HTMLElement
*/
function columnMouse(className) {
	var spans = qsa('span', this);
	for (var i=0; i < spans.length; i++) {
		if (/column/.test(spans[i].className)) {
			spans[i].className = 'column' + (className || '');
		}
	}
}



/** Fill column in search field
* @param string
* @return boolean false
*/
function selectSearch(name) {
	var el = qs('#fieldset-search');
	el.className = '';
	var divs = qsa('div', el);
	for (var i=0; i < divs.length; i++) {
		var div = divs[i];
		var el = qs('[name$="[col]"]', div);
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

/** Return event target
* @param Event
* @return HTMLElement
*/
function getTarget(event) {
	return event.target || event.srcElement;
}



/** Send form by Ctrl+Enter on <select> and <textarea>
* @param KeyboardEvent
* @param [string]
* @return boolean
*/
function bodyKeydown(event, button) {
	eventStop(event);
	var target = getTarget(event);
	if (target.jushTextarea) {
		target = target.jushTextarea;
	}
	if (isCtrl(event) && (event.keyCode == 13 || event.keyCode == 10) && isTag(target, 'select|textarea|input')) { // 13|10 - Enter
		target.blur();
		if (button) {
			target.form[button].click();
		} else {
			if (target.form.onsubmit) {
				target.form.onsubmit();
			}
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
	var target = getTarget(event);
	if ((isCtrl(event) || event.shiftKey) && target.type == 'submit' && isTag(target, 'input')) {
		target.form.target = '_blank';
		setTimeout(function () {
			// if (isCtrl(event)) { focus(); } doesn't work
			target.form.target = '';
		}, 0);
	}
}



/** Change focus by Ctrl+Up or Ctrl+Down
* @param KeyboardEvent
* @return boolean
*/
function editingKeydown(event) {
	if ((event.keyCode == 40 || event.keyCode == 38) && isCtrl(event)) { // 40 - Down, 38 - Up
		var target = getTarget(event);
		var sibling = (event.keyCode == 40 ? 'nextSibling' : 'previousSibling');
		var el = target.parentNode.parentNode[sibling];
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
	var input = this.form[this.name.replace(/^function/, 'fields')];
	if (input) { // undefined with the set data type
		if (selectValue(this)) {
			if (input.origType === undefined) {
				input.origType = input.type;
				input.origMaxLength = input.getAttribute('data-maxlength');
			}
			input.removeAttribute('data-maxlength');
			input.type = 'text';
		} else if (input.origType) {
			input.type = input.origType;
			if (input.origMaxLength >= 0) {
				input.setAttribute('data-maxlength', input.origMaxLength);
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
	var fnSelect = this.previousSibling.firstChild;
	if (fnSelect.selectedIndex < first) {
		fnSelect.selectedIndex = first;
	}
}

/** Add new field in schema-less edit
* @this HTMLInputElement
*/
function fieldChange() {
	var row = cloneNode(parentTag(this, 'tr'));
	var inputs = qsa('input', row);
	for (var i = 0; i < inputs.length; i++) {
		inputs[i].value = '';
	}
	// keep value in <select> (function)
	parentTag(this, 'table').appendChild(row);
	this.oninput = function () { };
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
	var request = (window.XMLHttpRequest ? new XMLHttpRequest() : (window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : false));
	if (request) {
		var ajaxStatus = qs('#ajaxstatus');
		if (message) {
			ajaxStatus.innerHTML = '<div class="message">' + message + '</div>';
			ajaxStatus.className = ajaxStatus.className.replace(/ hidden/g, '');
		} else {
			ajaxStatus.className += ' hidden';
		}
		request.open((data ? 'POST' : 'GET'), url);
		if (data) {
			request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}
		request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		request.onreadystatechange = function () {
			if (request.readyState == 4) {
				if (/^2/.test(request.status)) {
					callback(request);
				} else {
					ajaxStatus.innerHTML = (request.status ? request.responseText : '<div class="error">' + offlineMessage + '</div>');
					ajaxStatus.className = ajaxStatus.className.replace(/ hidden/g, '');
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
	return !ajax(url, function (request) {
		var data = window.JSON ? JSON.parse(request.responseText) : eval('(' + request.responseText + ')');
		for (var key in data) {
			setHtml(key, data[key]);
		}
	});
}

/** Save form contents through AJAX
* @param HTMLFormElement
* @param string
* @param [HTMLInputElement]
* @return boolean
*/
function ajaxForm(form, message, button) {
	var data = [];
	var els = form.elements;
	for (var i = 0; i < els.length; i++) {
		var el = els[i];
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
	
	var url = form.action;
	if (!/post/i.test(form.method)) {
		url = url.replace(/\?.*/, '') + '?' + data;
		data = '';
	}
	return ajax(url, function (request) {
		setHtml('ajaxstatus', request.responseText);
		if (window.jush) {
			jush.highlight_tag(qsa('code', qs('#ajaxstatus')), 0);
		}
		messagesPrint(qs('#ajaxstatus'));
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
	var td = this;
	var target = getTarget(event);
	if (!isCtrl(event) || isTag(td.firstChild, 'input|textarea') || isTag(target, 'a')) {
		return;
	}
	if (warning) {
		alert(warning);
		return true;
	}
	var original = td.innerHTML;
	text = text || /\n/.test(original);
	var input = document.createElement(text ? 'textarea' : 'input');
	input.onkeydown = function (event) {
		if (!event) {
			event = window.event;
		}
		if (event.keyCode == 27 && !event.shiftKey && !event.altKey && !isCtrl(event)) { // 27 - Esc
			inputBlur.apply(input);
			td.innerHTML = original;
		}
	};
	var pos = event.rangeOffset;
	var value = (td.firstChild && td.firstChild.alt) || td.textContent || td.innerText;
	input.style.width = Math.max(td.clientWidth - 14, 20) + 'px'; // 14 = 2 * (td.border + td.padding + input.border)
	if (text) {
		var rows = 1;
		value.replace(/\n/g, function () {
			rows++;
		});
		input.rows = rows;
	}
	if (qsa('i', td).length) { // <i> - NULL
		value = '';
	}
	if (document.selection) {
		var range = document.selection.createRange();
		range.moveToPoint(event.clientX, event.clientY);
		var range2 = range.duplicate();
		range2.moveToElementText(td);
		range2.setEndPoint('EndToEnd', range);
		pos = range2.text.length;
	}
	td.innerHTML = '';
	td.appendChild(input);
	setupSubmitHighlight(td);
	input.focus();
	if (text == 2) { // long text
		return ajax(location.href + '&' + encodeURIComponent(td.id) + '=', function (request) {
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
	if (document.selection) {
		var range = document.selection.createRange();
		range.moveEnd('character', -input.value.length + pos);
		range.select();
	}
	return true;
}



/** Load and display next page in select
* @param number
* @param string
* @return boolean false for success
* @this HTMLLinkElement
*/
function selectLoadMore(limit, loading) {
	var a = this;
	var title = a.innerHTML;
	var href = a.href;
	a.innerHTML = loading;
	if (href) {
		a.removeAttribute('href');
		return !ajax(href, function (request) {
			var tbody = document.createElement('tbody');
			tbody.innerHTML = request.responseText;
			qs('#table').appendChild(tbody);
			if (tbody.children.length < limit) {
				a.parentNode.removeChild(a);
			} else {
				a.href = href.replace(/\d+$/, function (page) {
					return +page + 1;
				});
				a.innerHTML = title;
			}
		});
	}
}



/** Stop event propagation
* @param Event
*/
function eventStop(event) {
	if (event.stopPropagation) {
		event.stopPropagation();
	} else {
		event.cancelBubble = true;
	}
}



/** Setup highlighting of default submit button on form field focus
* @param HTMLElement
*/
function setupSubmitHighlight(parent) {
	for (var key in { input: 1, select: 1, textarea: 1 }) {
		var inputs = qsa(key, parent);
		for (var i = 0; i < inputs.length; i++) {
			setupSubmitHighlightInput(inputs[i])
		}
	}
}

/** Setup submit highlighting for single element
* @param HTMLElement
*/
function setupSubmitHighlightInput(input) {
	if (!/submit|image|file/.test(input.type)) {
		addEvent(input, 'focus', inputFocus);
		addEvent(input, 'blur', inputBlur);
	}
}

/** Highlight default submit button
* @this HTMLInputElement
*/
function inputFocus() {
	var submit = findDefaultSubmit(this);
	if (submit) {
		alterClass(submit, 'default', true);
	}
}

/** Unhighlight default submit button
* @this HTMLInputElement
*/
function inputBlur() {
	var submit = findDefaultSubmit(this);
	if (submit) {
		alterClass(submit, 'default');
	}
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
	var inputs = qsa('input', el.form);
	for (var i = 0; i < inputs.length; i++) {
		var input = inputs[i];
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
	if (el.addEventListener) {
		el.addEventListener(action, handler, false);
	} else {
		el.attachEvent('on' + action, handler);
	}
}

/** Defer focusing element
* @param HTMLElement
*/
function focus(el) {
	setTimeout(function () { // this has to be an anonymous function because Firefox passes some arguments to setTimeout callback
		el.focus();
	}, 0);
}

/** Clone node and setup submit highlighting
* @param HTMLElement
* @return HTMLElement
*/
function cloneNode(el) {
	var el2 = el.cloneNode(true);
	var selector = 'input, select';
	var origEls = qsa(selector, el);
	var cloneEls = qsa(selector, el2);
	for (var i=0; i < origEls.length; i++) {
		var origEl = origEls[i];
		for (var key in origEl) {
			if (/^on/.test(key) && origEl[key]) {
				cloneEls[i][key] = origEl[key];
			}
		}
	}
	setupSubmitHighlight(el2);
	return el2;
}

oninput = function (event) {
	var target = event.target;
	var maxLength = target.getAttribute('data-maxlength');
	alterClass(target, 'maxlength', target.value && maxLength != null && target.value.length > maxLength); // maxLength could be 0
};
