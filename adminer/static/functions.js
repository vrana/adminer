
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

/** Set checked class
* @param HTMLInputElement
*/
function trCheck(el) {
	var tr = el.parentNode.parentNode;
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
	var click = true;
	var el = event.target || event.srcElement;
	while (!/^tr$/i.test(el.tagName)) {
		if (/^table$/i.test(el.tagName)) {
			return;
		}
		if (/^(a|input|textarea)$/i.test(el.tagName)) {
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
		if (!ajaxSend(href)) {
			location.href = href;
		}
	}
}



/** Add row in select fieldset
* @param HTMLSelectElement
*/
function selectAddRow(field) {
	field.onchange = function () { };
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



/** Abort AJAX request
* @uses ajaxRequest
*/
function ajaxAbort() {
	ajaxRequest.onreadystatechange = null;
	if (ajaxRequest.abort) {
		ajaxRequest.abort();
	}
}



/** Send form by Ctrl+Enter on <select> and <textarea>
* @param KeyboardEvent
* @param [string]
* @return boolean
*/
function bodyKeydown(event, button) {
	var target = event.target || event.srcElement;
	if (event.keyCode == 27 && !event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey) { // 27 - Esc
		ajaxAbort();
		document.body.className = document.body.className.replace(/ loading/g, '');
		onblur = function () { };
		if (originalFavicon) {
			replaceFavicon(originalFavicon);
		}
	}
	if (event.ctrlKey && (event.keyCode == 13 || event.keyCode == 10) && !event.altKey && !event.metaKey && /select|textarea|input/i.test(target.tagName)) { // 13|10 - Enter, shiftKey allowed
		target.blur();
		if (!ajaxForm(target.form, (button ? button + '=1' : ''))) {
			if (button) {
				target.form[button].click();
			} else {
				target.form.submit();
			}
		}
		return false;
	}
	return true;
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

var originalFavicon;

/** Replace favicon
* @param string
*/
function replaceFavicon(href) {
	var favicon = document.getElementById('favicon');
	if (favicon) {
		favicon.href = href;
		favicon.parentNode.appendChild(favicon); // to replace the icon in Firefox
	}
}

var ajaxRequest = {};

/** Safely load content to #content
* @param string
* @param [string]
* @param [boolean]
* @param [boolean]
* @return XMLHttpRequest or false in case of an error
* @uses ajaxRequest
*/
function ajaxSend(url, data, popState, noscroll) {
	if (!history.pushState) {
		return false;
	}
	ajaxAbort();
	onblur = function () {
		if (!originalFavicon) {
			originalFavicon = (document.getElementById('favicon') || {}).href;
		}
		replaceFavicon(document.getElementById('loader').firstChild.src);
	};
	document.body.className += ' loading';
	ajaxRequest = ajax(url, function (request) {
		var title = request.getResponseHeader('X-AJAX-Title');
		if (title) {
			document.title = decodeURIComponent(title);
		}
		var redirect = request.getResponseHeader('X-AJAX-Redirect');
		if (redirect) {
			return ajaxSend(redirect, '', popState);
		}
		onblur = function () { };
		if (originalFavicon) {
			replaceFavicon(originalFavicon);
		}
		if (!popState) {
			if (data || url != location.href) {
				history.pushState(data, '', url); //! remember window position
			}
		}
		if (!noscroll && !/&order/.test(url)) {
			scrollTo(0, 0);
		}
		setHtml('content', (request.status ? request.responseText : '<p class="error">' + noResponse));
		document.body.className = document.body.className.replace(/ loading/g, '');
		var content = document.getElementById('content');
		var scripts = content.getElementsByTagName('script');
		var length = scripts.length; // required to avoid infinite loop
		for (var i=0; i < length; i++) {
			var script = document.createElement('script');
			script.text = scripts[i].text;
			content.appendChild(script);
		}
		
		var as = document.getElementById('menu').getElementsByTagName('a');
		var href = location.href.replace(/(&(sql=|dump=|(select|table)=[^&]*)).*/, '$1');
		for (var i=0; i < as.length; i++) {
			as[i].className = (href == as[i].href ? 'active' : '');
		}
		var dump = document.getElementById('dump');
		if (dump) {
			var match = /&(select|table)=([^&]+)/.exec(href);
			dump.href = dump.href.replace(/[^=]+$/, '') + (match ? match[2] : '');
		}
		//! modify Change database hidden fields
		
		if (window.jush) {
			jush.highlight_tag('code', 0);
		}
	}, data);
	return ajaxRequest;
}

/** Revive page from history
* @param PopStateEvent|history
* @uses ajaxRequest
*/
onpopstate = function (event) {
	if ((ajaxRequest.send || event.state) && !/#/.test(location.href)) {
		ajaxSend(location.href, (event.state && confirm(areYouSure) ? event.state : ''), 1); // 1 - disable pushState
	} else {
		ajaxRequest.send = true; // to enable AJAX for next call of this function
	}
};

/** Send form by AJAX GET
* @param HTMLFormElement
* @param [string]
* @param [boolean]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxForm(form, data, noscroll) {
	if ((/&(database|scheme|create|view|sql|user|dump|call)=/.test(location.href) && !/\./.test(data)) || (form.onsubmit && form.onsubmit() === false)) { // . - type="image"
		return false;
	}
	var params = [ ];
	for (var i=0; i < form.elements.length; i++) {
		var el = form.elements[i];
		if (/file/i.test(el.type) && el.value) {
			return false;
		} else if (el.name && (!/checkbox|radio|submit|file/i.test(el.type) || el.checked)) {
			params.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(/select/i.test(el.tagName) ? selectValue(el) : el.value));
		}
	}
	if (data) {
		params.push(data);
	}
	if (form.method == 'post') {
		return ajaxSend((/\?/.test(form.action) ? form.action : location.href), params.join('&'), false, noscroll); // ? - always part of Adminer URL
	}
	return ajaxSend((form.action || location.href).replace(/\?.*/, '') + '?' + params.join('&'), '', false, noscroll);
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



/** Load link by AJAX
* @param MouseEvent
* @param string
* @param string
* @return boolean
*/
function bodyClick(event, db, ns) {
	if (event.button || event.shiftKey || event.altKey || event.metaKey) {
		return;
	}
	if (event.getPreventDefault ? event.getPreventDefault() : event.returnValue === false || event.defaultPrevented) {
		return false;
	}
	var el = event.target || event.srcElement;
	if (/^a$/i.test(el.parentNode.tagName)) {
		el = el.parentNode;
	}
	if (/^a$/i.test(el.tagName) && !/:|#|&download=/i.test(el.getAttribute('href')) && /[&?]username=/.test(el.href) && !event.ctrlKey) {
		var match = /&db=([^&]*)/.exec(el.href);
		var match2 = /&ns=([^&]*)/.exec(el.href);
		return !(db == (match ? decodeURIComponent(match[1]) : '') && ns == (match2 ? decodeURIComponent(match2[1]) : '') && ajaxSend(el.href));
	}
	if (/^input$/i.test(el.tagName) && /image|submit/.test(el.type)) {
		if (event.ctrlKey) {
			el.form.target = '_blank';
		} else {
			return !ajaxForm(el.form, (el.name ? encodeURIComponent(el.name) + (el.type == 'image' ? '.x' : '') + '=1' : ''), el.type == 'image');
		}
	}
	return true;
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
