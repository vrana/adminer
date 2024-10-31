
/**
 * Returns the element found by given identifier.
 *
 * @param {string} id
 * @param {?HTMLElement} context Defaults to document.
 * @return {?HTMLElement}
 */
function gid(id, context = null) {
	return (context || document).getElementById(id);
}

/** Get first element by selector
* @param string
* @param [HTMLElement] defaults to document
* @return HTMLElement
*/
function qs(selector, context = null) {
	return (context || document).querySelector(selector);
}

/** Get last element by selector
* @param string
* @param [HTMLElement] defaults to document
* @return HTMLElement
*/
function qsl(selector, context = null) {
	var els = qsa(selector, context);
	return els[els.length - 1];
}

/** Get all elements by selector
* @param string
* @param [HTMLElement] defaults to document
* @return NodeList
*/
function qsa(selector, context = null) {
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

/**
 * Toggles visibility of element with ID.
 *
 * @param {string} id
 * @return {boolean} Always false.
 */
function toggle(id) {
	gid(id).classList.toggle("hidden");

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

/**
 * Verifies current Adminer version.
 *
 * @param currentVersion string
 * @param baseUrl string
 * @param token string
 */
function verifyVersion(currentVersion, baseUrl, token) {
	cookie('adminer_version=0', 1);

	ajax('https://api.github.com/repos/pematon/adminer/releases/latest', function (request) {
		const response = JSON.parse(request.responseText);

		const version = response.tag_name.replace(/^\D*/, '');
		if (!version) return;

		cookie('adminer_version=' + version, 1);

		const data = 'version=' + version + '&token=' + token;
		ajax(baseUrl + 'script=version', function () {}, data);

		if (currentVersion !== version) {
			gid('version').innerText = version;
		}
	});
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
* @return boolean
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
	tr.classList.toggle('checked', el.checked);
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
	var el = gid(id);
	if (el) {
		var inputs = qsa('input', el.parentNode.parentNode);
		for (var i = 0; i < inputs.length; i++) {
			var input = inputs[i];
			if (input.type === 'submit') {
				input.disabled = (count === '0');
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

/**
 * Uncheck single element.
 */
function formUncheck(id) {
	formUncheckAll("#" + id);
}

/**
 * Uncheck elements matched by selector.
 */
function formUncheckAll(selector) {
	for (const element of qsa(selector)) {
		element.checked = false;
		trCheck(element);
	}
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
	var td = parentTag(event.target, 'td');
	var text;
	if (td && (text = td.getAttribute('data-text'))) {
		if (selectClick.call(td, event, +text, td.getAttribute('data-warning'))) {
			return;
		}
	}
	click = (click || !window.getSelection || getSelection().isCollapsed);
	var el = event.target;
	while (!isTag(el, 'tr')) {
		if (isTag(el, 'table|a|input|textarea')) {
			if (el.type !== 'checkbox') {
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
	if (el.name === 'check[]') {
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
	if (event.shiftKey && (!lastChecked || lastChecked.name === this.name)) {
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
		location.href = href + (page !== 1 ? '&page=' + (page - 1) : '');
	}
}

let tablesFilterTimeout = null;
let tablesFilterValue = '';

function initTablesFilter(dbName) {
	if (sessionStorage) {
		document.addEventListener('DOMContentLoaded', function () {
			if (dbName === sessionStorage.getItem('adminer_tables_filter_db') && sessionStorage.getItem('adminer_tables_filter')) {
				gid('tables-filter').value = sessionStorage.getItem('adminer_tables_filter');
				filterTables();
			} else {
				sessionStorage.removeItem('adminer_tables_filter');
			}

			sessionStorage.setItem('adminer_tables_filter_db', dbName);
		});
	}

	const filterInput = gid('tables-filter');
	filterInput.addEventListener('input', function () {
		window.clearTimeout(tablesFilterTimeout);
		tablesFilterTimeout = window.setTimeout(filterTables, 200);
	});

	document.body.addEventListener('keydown', function(event) {
		if (isCtrl(event) && event.shiftKey && event.key.toUpperCase() === 'F') {
			filterInput.focus();
			filterInput.select();

			event.preventDefault();
		}
	});
}

function filterTables() {
	const value = gid('tables-filter').value.toLowerCase();
	if (value === tablesFilterValue) {
		return;
	}
	tablesFilterValue = value;

	let reg
	if (value !== '') {
		const valueExp = (`${value}`).replace(/[\\.+*?\[^\]$(){}=!<>|:]/g, '\\$&');
		reg = new RegExp(`(${valueExp})`, 'gi');
	}

	if (sessionStorage) {
		sessionStorage.setItem('adminer_tables_filter', value);
	}

	const tables = qsa('#tables li');
	for (let i = 0; i < tables.length; i++) {
		let a = qs('a[data-main="true"], span[data-main="true"]', tables[i]);

		let tableName = tables[i].dataset.tableName;
		if (tableName == null) {
			tableName = a.innerHTML.trim();

			tables[i].dataset.tableName = tableName;
		}

		if (value === "") {
			tables[i].classList.remove('hidden');
			a.innerHTML = tableName;
		} else if (tableName.toLowerCase().indexOf(value) >= 0) {
			tables[i].classList.remove('hidden');
			a.innerHTML = tableName.replace(reg, '<strong>$1</strong>');
		} else {
			tables[i].classList.add('hidden');
		}
	}
}

/** Display items in menu
* @param MouseEvent
* @this HTMLElement
*/
function menuOver(event) {
	var a = event.target;
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



/**
 * Adds row in select fieldset.
 *
 * @param {Event} event
 * @this HTMLSelectElement
 */
function selectAddRow(event) {
	const field = this;
	const row = cloneNode(field.parentNode);

	field.onchange = selectFieldChange;
	field.onchange(event);

	const selects = qsa('select', row);
	for (const select of selects) {
		select.name = select.name.replace(/[a-z]\[\d+/, '$&1');
		select.selectedIndex = 0;
	}

	const inputs = qsa('input', row);
	for (const input of inputs) {
		// Skip buttons.
		if (input.type === 'image') {
			continue;
		}

		input.name = input.name.replace(/[a-z]\[\d+/, '$&1');
		input.className = '';
		if (input.type === 'checkbox') {
			input.checked = false;
		} else {
			input.value = '';
		}
	}

	const button = qs('.remove', row);
	button.onclick = selectRemoveRow;

	const parent = field.parentNode.parentNode;
	if (parent.classList.contains("sortable")) {
		initSortableRow(field.parentElement);
	}

	parent.appendChild(row);
}

/**
 * Removes a row in select fieldset.
 *
 * @this HTMLInputElement
 * @return {boolean} Always false.
 */
function selectRemoveRow() {
	const row = this.parentNode;

	row.parentNode.removeChild(row);

	return false;
}

/** Prevent onsearch handler on Enter
* @param KeyboardEvent
* @this HTMLInputElement
*/
function selectSearchKeydown(event) {
	if (event.keyCode === 13 || event.keyCode === 10) {
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

// Sorting.
(function() {
	let placeholderRow = null, nextRow = null, dragHelper = null;
	let startY, minY, maxY;

	/**
	 * Initializes sortable list of DIV elements.
	 *
	 * @param {string} parentSelector
	 */
	window.initSortable = function(parentSelector) {
		const parent = qs(parentSelector);
		if (!parent) return;

		for (const row of parent.children) {
			if (!row.classList.contains("no-sort")) {
				initSortableRow(row);
			}
		}
	};

	/**
	 * Initializes one row of sortable parent.
	 *
	 * @param {HTMLElement} row
	 */
	window.initSortableRow = function(row) {
		row.classList.remove("no-sort");

		const handle = qs(".handle", row);
		handle.addEventListener("mousedown", (event) => { startSorting(row, event) });
		handle.addEventListener("touchstart", (event) => { startSorting(row, event) });
	};

	window.isSorting = function () {
		return dragHelper !== null;
	};

	function startSorting(row, event) {
		event.preventDefault();

		const pointerY = getPointerY(event);

		const parent = row.parentNode;
		startY = pointerY - getOffsetTop(row);
		minY = getOffsetTop(parent);
		maxY = minY + parent.offsetHeight - row.offsetHeight;

		placeholderRow = row.cloneNode(true);
		placeholderRow.classList.add("placeholder");
		parent.insertBefore(placeholderRow, row);

		nextRow = row.nextElementSibling;

		let top = pointerY - startY;
		let left = getOffsetLeft(row);
		let width = row.getBoundingClientRect().width;

		if (row.tagName === "TR") {
			const firstChild = row.firstElementChild;
			const borderWidth = (firstChild.offsetWidth - firstChild.clientWidth) / 2;
			const borderHeight = (firstChild.offsetHeight - firstChild.clientHeight) / 2;

			minY -= borderHeight;
			maxY -= borderHeight;
			top -= borderHeight;
			left -= borderWidth;
			width += 2 * borderWidth;

			for (const child of row.children) {
				child.style.width = child.getBoundingClientRect().width + "px";
			}

			dragHelper = document.createElement("table");
			dragHelper.appendChild(row);
		} else {
			dragHelper = row;
		}

		dragHelper.style.top = `${top}px`;
		dragHelper.style.left = `${left}px`;
		dragHelper.style.width = `${width}px`;
		dragHelper.classList.add("dragging");
		document.body.appendChild(dragHelper);

		window.addEventListener("mousemove", updateSorting);
		window.addEventListener("touchmove", updateSorting);

		window.addEventListener("mouseup", finishSorting);
		window.addEventListener("touchend", finishSorting);
		window.addEventListener("touchcancel", finishSorting);
	}

	function updateSorting(event) {
		const pointerY = getPointerY(event);

		let top = Math.min(Math.max(pointerY - startY, minY), maxY);
		dragHelper.style.top = `${top}px`;

		const parent = placeholderRow.parentNode;
		top = top - minY + parent.offsetTop;

		let sibling;
		if (top > placeholderRow.offsetTop + placeholderRow.offsetHeight / 2) {
			sibling = !nextRow.classList.contains("no-sort") ? nextRow.nextElementSibling : nextRow;
		} else if (top + placeholderRow.offsetHeight < placeholderRow.offsetTop + placeholderRow.offsetHeight / 2) {
			sibling = placeholderRow.previousElementSibling;
		} else {
			sibling = nextRow;
		}

		if (sibling !== nextRow) {
			const parent = placeholderRow.parentNode;

			nextRow = sibling;
			if (sibling) {
				parent.insertBefore(placeholderRow, nextRow);
			} else {
				parent.appendChild(placeholderRow);
			}
		}
	}

	function finishSorting() {
		dragHelper.classList.remove("dragging");
		dragHelper.style.top = null;
		dragHelper.style.left = null;
		dragHelper.style.width = null;

		placeholderRow.parentNode.insertBefore(dragHelper.tagName === "TABLE" ? dragHelper.firstChild : dragHelper, placeholderRow);
		placeholderRow.remove();

		placeholderRow = nextRow = dragHelper = null;

		window.removeEventListener("mousemove", updateSorting);
		window.removeEventListener("touchmove", updateSorting);

		window.removeEventListener("mouseup", finishSorting);
		window.removeEventListener("touchend", finishSorting);
		window.removeEventListener("touchcancel", finishSorting);
	}

	function getPointerY(event) {
		if (event.type.includes("touch")) {
			const touch = event.touches[0] || event.changedTouches[0];
			return touch.clientY;
		} else {
			return event.clientY;
		}
	}
})();




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
	var el = gid('fieldset-search');
	el.className = '';
	var divs = qsa('div', el);
	for (var i=0; i < divs.length; i++) {
		var div = divs[i];
		var el = qs('[name$="[col]"]', div);
		if (el && selectValue(el) === name) {
			break;
		}
	}
	if (i === divs.length) {
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
	var target = event.target;
	if (target.jushTextarea) {
		target = target.jushTextarea;
	}
	if (isCtrl(event) && (event.keyCode === 13 || event.keyCode === 10) && isTag(target, 'select|textarea|input')) { // 13|10 - Enter
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
	var target = event.target;
	if ((isCtrl(event) || event.shiftKey) && target.type === 'submit' && isTag(target, 'input')) {
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
	if ((event.keyCode === 40 || event.keyCode === 38) && isCtrl(event)) { // 40 - Down, 38 - Up
		var target = event.target;
		var sibling = (event.keyCode === 40 ? 'nextSibling' : 'previousSibling');
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

/**
 * Disables maxlength for functions and manages value visibility.
 *
 * @this HTMLSelectElement
 */
function functionChange() {
	const input = this.form[this.name.replace(/^function/, 'fields')];
	const value = selectValue(this);

	// Undefined with the set data type.
	if (!input) {
		return;
	}

	if (value) {
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

	// Hide input value if it will be not used by selected function.
	if (value === "NULL" || value === "now") {
		if (input.value !== "") {
			input.dataset.lastValue = input.value;
			input.value = "";
		}
	} else if (input.dataset.lastValue) {
		input.value = input.dataset.lastValue;
	}

	oninput({target: input});
}

/**
 * Unset 'original', 'NULL' and 'now' functions when typing.
 *
 * @param first number
 * @this HTMLTableCellElement
 */
function skipOriginal(first) {
	const fnSelect = this.previousSibling.firstChild;
	const value = selectValue(fnSelect);

	if (fnSelect.selectedIndex < first || value === "NULL" || value === "now") {
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
		var ajaxStatus = gid('ajaxstatus');
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
			if (request.readyState === 4) {
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
			if (!/^(checkbox|radio|submit|file)$/i.test(el.type) || el.checked || el === button) {
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
			jush.highlight_tag(qsa('code', gid('ajaxstatus')), 0);
		}
		messagesPrint(gid('ajaxstatus'));
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
	var target = event.target;
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
		if (event.keyCode === 27 && !event.shiftKey && !event.altKey && !isCtrl(event)) { // 27 - Esc
			inputBlur.apply(input);
			td.innerHTML = original;
		}
	};

	let pos = event.rangeOffset;
	let value = (td.firstChild && td.firstChild.alt) || td.textContent || td.innerText;
	const tdStyle = window.getComputedStyle(td, null);

	input.style.width = Math.max(td.clientWidth - parseFloat(tdStyle.paddingLeft) - parseFloat(tdStyle.paddingRight), 20) + 'px';

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
	if (text === 2) { // long text
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
			gid('table').appendChild(tbody);
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
	const submit = findDefaultSubmit(this);
	if (submit) {
		submit.classList.toggle('default', true);
	}
}

/** Unhighlight default submit button
* @this HTMLInputElement
*/
function inputBlur() {
	const submit = findDefaultSubmit(this);
	if (submit) {
		submit.classList.toggle('default', false);
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
		if (input.type === 'submit' && !input.style.zIndex) {
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

function getOffsetTop(element) {
	let box = element.getBoundingClientRect();

	return box.top + window.scrollY;
}

function getOffsetLeft(element) {
	let box = element.getBoundingClientRect();

	return box.left + window.scrollX;
}

oninput = function (event) {
	const target = event.target;
	const maxLength = target.getAttribute('data-maxlength');

	// maxLength could be 0
	target.classList.toggle('maxlength', target.value && maxLength != null && target.value.length > maxLength);
};
