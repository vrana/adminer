
/** Toggle visibility
* @param string
* @return boolean
*/
function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
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
*/
function verifyVersion() {
	cookie('adminer_version=0', 1);
	var script = document.createElement('script');
	script.src = location.protocol + '//www.adminer.org/version.php';
	document.body.appendChild(script);
}

/** Get value of select
* @param HTMLSelectElement
* @return string
*/
function selectValue(select) {
	var selected = select.options[select.selectedIndex];
	return ((selected.attributes.value || {}).specified ? selected.value : selected.text);
}

/** Get parent node with specified tag name.
 * @param HTMLElement
 * @param string
 * @return HTMLElement
 */
function parentTag(el, tag) {
	var re = new RegExp('^' + tag + '$', 'i');
	while (!re.test(el.tagName)) {
		el = el.parentNode;
	}
	return el;
}

/** Set checked class
* @param HTMLInputElement
*/
function trCheck(el) {
	var tr = parentTag(el, 'tr');
	tr.className = tr.className.replace(/(^|\s)checked(\s|$)/, '$2') + (el.checked ? ' checked' : '');
}

/** Check all elements matching given name
* @param HTMLInputElement
* @param RegExp
*/
function formCheck(el, name) {
	var elems = el.form.elements;
	for (var i=0; i < elems.length; i++) {
		if (name.test(elems[i].name)) {
			elems[i].checked = el.checked;
			trCheck(elems[i]);
		}
	}
}

/** Check all rows in <table class="checkable">
*/
function tableCheck() {
	var tables = document.getElementsByTagName('table');
	for (var i=0; i < tables.length; i++) {
		if (/(^|\s)checkable(\s|$)/.test(tables[i].className)) {
			var trs = tables[i].getElementsByTagName('tr');
			for (var j=0; j < trs.length; j++) {
				trCheck(trs[j].firstChild.firstChild);
			}
		}
	}
}

/** Uncheck single element
* @param string
*/
function formUncheck(id) {
	var el = document.getElementById(id);
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
*/
function tableClick(event) {
	var click = (!window.getSelection || getSelection().isCollapsed);
	var el = event.target || event.srcElement;
	while (!/^tr$/i.test(el.tagName)) {
		if (/^(table|a|input|textarea)$/i.test(el.tagName)) {
			if (el.type != 'checkbox') {
				return;
			}
			checkboxClick(event, el);
			click = false;
		}
		el = el.parentNode;
	}
	el = el.firstChild.firstChild;
	if (click) {
		el.click && el.click();
		el.onclick && el.onclick();
	}
	trCheck(el);
}

var lastChecked;

/** Shift-click on checkbox for multiple selection.
 * @param MouseEvent
 * @param HTMLInputElement
 */
function checkboxClick(event, el) {
	if (!el.name) {
		return;
	}
	if (event.shiftKey && (!lastChecked || lastChecked.name == el.name)) {
		var checked = (lastChecked ? lastChecked.checked : true);
		var inputs = parentTag(el, 'table').getElementsByTagName('input');
		var checking = !lastChecked;
		for (var i=0; i < inputs.length; i++) {
			var input = inputs[i];
			if (input.name === el.name) {
				if (checking) {
					input.checked = checked;
					trCheck(input);
				}
				if (input === el || input === lastChecked) {
					if (checking) {
						break;
					}
					checking = true;
				}
			}
		}
	} else {
		lastChecked = el;
	}
}

/** Set HTML code of an element
* @param string
* @param string undefined to set parentNode to &nbsp;
*/
function setHtml(id, html) {
	var el = document.getElementById(id);
	if (el) {
		if (html == undefined) {
			el.parentNode.innerHTML = '&nbsp;';
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
* @param [MouseEvent]
*/
function pageClick(href, page, event) {
	if (!isNaN(page) && page) {
		href += (page != 1 ? '&page=' + (page - 1) : '');
		location.href = href;
	}
}



/** Display items in menu
* @param HTMLElement
* @param MouseEvent
*/
function menuOver(el, event) {
	var a = event.target;
	if (/^a$/i.test(a.tagName) && a.offsetLeft + a.offsetWidth > a.parentNode.offsetWidth) {
		el.style.overflow = 'visible';
	}
}

/** Hide items in menu
* @param HTMLElement
*/
function menuOut(el) {
	el.style.overflow = 'auto';
}



/** Add row in select fieldset
* @param HTMLSelectElement
*/
function selectAddRow(field) {
	field.onchange = function () {
		selectFieldChange(field.form);
	};
	field.onchange();
	var row = field.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/[a-z]\[\d+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = row.getElementsByTagName('input');
	if (inputs.length) {
		inputs[0].name = inputs[0].name.replace(/[a-z]\[\d+/, '$&1');
		inputs[0].value = '';
		inputs[0].className = '';
	}
	field.parentNode.parentNode.appendChild(row);
}



/** Toggles column context menu
 * @param HTMLElement
 * @param [string] extra class name
 */
function columnMouse(el, className) {
	var spans = el.getElementsByTagName('span');
	for (var i=0; i < spans.length; i++) {
		if (/column/.test(spans[i].className)) {
			spans[i].className = 'column' + (className || '');
		}
	}
}



/** Fill column in search field
 * @param string
 */
function selectSearch(name) {
	var el = document.getElementById('fieldset-search');
	el.className = '';
	var divs = el.getElementsByTagName('div');
	for (var i=0; i < divs.length; i++) {
		var div = divs[i];
		if (/select/i.test(div.firstChild.tagName) && selectValue(div.firstChild) == name) {
			break;
		}
	}
	if (i == divs.length) {
		div.firstChild.value = name;
		div.firstChild.onchange();
	}
	div.lastChild.focus();
}



/** Send form by Ctrl+Enter on <select> and <textarea>
* @param KeyboardEvent
* @param [string]
* @return boolean
*/
function bodyKeydown(event, button) {
	var target = event.target || event.srcElement;
	if (event.ctrlKey && (event.keyCode == 13 || event.keyCode == 10) && !event.altKey && !event.metaKey && /select|textarea|input/i.test(target.tagName)) { // 13|10 - Enter, shiftKey allowed
		target.blur();
		if (button) {
			target.form[button].click();
		} else {
			target.form.submit();
		}
		return false;
	}
	return true;
}

/** Open form to a new window on Ctrl+click or Shift+click
* @param MouseEvent
*/
function bodyClick(event) {
	var target = event.target || event.srcElement;
	if ((event.ctrlKey || event.shiftKey) && target.type == 'submit' && /input/i.test(target.tagName)) {
		target.form.target = '_blank';
		setTimeout(function () {
			// if (event.ctrlKey) { focus(); } doesn't work
			target.form.target = '';
		}, 0);
	}
}



/** Change focus by Ctrl+Up or Ctrl+Down
* @param KeyboardEvent
* @return boolean
*/
function editingKeydown(event) {
	if ((event.keyCode == 40 || event.keyCode == 38) && event.ctrlKey && !event.altKey && !event.metaKey) { // 40 - Down, 38 - Up, shiftKey allowed
		var target = event.target || event.srcElement;
		var sibling = (event.keyCode == 40 ? 'nextSibling' : 'previousSibling');
		var el = target.parentNode.parentNode[sibling];
		if (el && (/^tr$/i.test(el.tagName) || (el = el[sibling])) && /^tr$/i.test(el.tagName) && (el = el.childNodes[nodePosition(target.parentNode)]) && (el = el.childNodes[nodePosition(target)])) {
			el.focus();
		}
		return false;
	}
	if (event.shiftKey && !bodyKeydown(event, 'insert')) {
		eventStop(event);
		return false;
	}
	return true;
}

/** Disable maxlength for functions
* @param HTMLSelectElement
*/
function functionChange(select) {
	var input = select.form[select.name.replace(/^function/, 'fields')];
	if (selectValue(select)) {
		if (input.origMaxLength === undefined) {
			input.origMaxLength = input.maxLength;
		}
		input.removeAttribute('maxlength');
	} else if (input.origMaxLength >= 0) {
		input.maxLength = input.origMaxLength;
	}
}



/** Create AJAX request
* @param string
* @param function (XMLHttpRequest)
* @param [string]
* @return XMLHttpRequest or false in case of an error
*/
function ajax(url, callback, data) {
	var request = (window.XMLHttpRequest ? new XMLHttpRequest() : (window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : false));
	if (request) {
		request.open((data ? 'POST' : 'GET'), url);
		if (data) {
			request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}
		request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		request.onreadystatechange = function () {
			if (request.readyState == 4) {
				callback(request);
			}
		};
		request.send(data);
	}
	return request;
}

/** Use setHtml(key, value) for JSON response
* @param string
* @return XMLHttpRequest or false in case of an error
*/
function ajaxSetHtml(url) {
	return ajax(url, function (request) {
		if (request.status) {
			var data = eval('(' + request.responseText + ')');
			for (var key in data) {
				setHtml(key, data[key]);
			}
		}
	});
}



/** Display edit field
* @param HTMLElement
* @param MouseEvent
* @param number display textarea instead of input, 2 - load long text
*/
function selectDblClick(td, event, text) {
	if (/input|textarea/i.test(td.firstChild.tagName)) {
		return;
	}
	var original = td.innerHTML;
	text = text || /\n/.test(original);
	var input = document.createElement(text ? 'textarea' : 'input');
	input.onkeydown = function (event) {
		if (!event) {
			event = window.event;
		}
		if (event.keyCode == 27 && !(event.ctrlKey || event.shiftKey || event.altKey || event.metaKey)) { // 27 - Esc
			td.innerHTML = original;
		}
	};
	var pos = event.rangeOffset;
	var value = td.firstChild.alt || td.textContent || td.innerText;
	input.style.width = Math.max(td.clientWidth - 14, 20) + 'px'; // 14 = 2 * (td.border + td.padding + input.border)
	if (text) {
		var rows = 1;
		value.replace(/\n/g, function () {
			rows++;
		});
		input.rows = rows;
	}
	if (value == '\u00A0' || td.getElementsByTagName('i').length) { // &nbsp; or i - NULL
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
	input.focus();
	if (text == 2) { // long text
		return ajax(location.href + '&' + encodeURIComponent(td.id) + '=', function (request) {
			if (request.status) {
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
}



/** Load and display next page in select
* @param HTMLLinkElement
* @param string
* @param number
* @return boolean
*/
function selectLoadMore(a, limit, loading) {
	var title = a.innerHTML;
	var href = a.href;
	a.innerHTML = loading;
	if (href) {
		a.removeAttribute('href');
		return ajax(href, function (request) {
			document.getElementById('table').innerHTML += request.responseText;
			var rows = 0;
			request.responseText.replace(/(^|\n)<tr/g, function () {
				rows++;
			});
			if (rows < limit) {
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
