// to hide elements displayed by JavaScript
document.body.className += ' js';

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
function cookie(assign, days, params) {
	var date = new Date();
	date.setDate(date.getDate() + days);
	document.cookie = assign + '; expires=' + date + (params || '');
}

/** Verify current Adminer version
* @param string 'http' or 'https'
*/
function verifyVersion(protocol) {
	cookie('adminer_version=0', 1);
	var script = document.createElement('script');
	script.src = protocol + '://www.adminer.org/version.php';
	document.body.appendChild(script);
}

/** Get value of select
* @param HTMLSelectElement
* @return string
*/
function selectValue(select) {
	return (select.value !== undefined ? select.value : select.options[select.selectedIndex].text);
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
		}
	}
}

/** Uncheck single element
* @param string
*/
function formUncheck(id) {
	document.getElementById(id).checked = false;
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
	var el = event.target || event.srcElement;
	while (!/^tr$/i.test(el.tagName)) {
		if (/^(table|a|input|textarea)$/i.test(el.tagName)) {
			return;
		}
		el = el.parentNode;
	}
	el = el.firstChild.firstChild;
	el.click && el.click();
	el.onclick && el.onclick();
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
			el.innerHTML = html.replace(/<noscript>.*<\/noscript>/i, ''); // required for Google Chrome // hopes that there will be only one <noscript> on each line
		}
	}
}

/** Go to the specified page
* @param string
* @param string
* @param [MouseEvent]
*/
function pageClick(href, page, event) {
	if (!isNaN(page) && page) {
		href += (page != 1 ? '&page=' + (page - 1) : '');
		if (!ajaxMain(href, '', event)) {
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



/** Handle Ctrl+Enter and optionally Tab in textarea
* @param HTMLTextAreaElement
* @param KeyboardEvent
* @param boolean handle also Tab
* @param HTMLInputElement submit button
* @return boolean
*/
function textareaKeypress(target, event, tab, button) {
	if (tab && event.keyCode == 9 && !event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey) {
		// inspired by http://pallieter.org/Projects/insertTab/
		if (target.setSelectionRange) {
			var start = target.selectionStart;
			target.value = target.value.substr(0, start) + '\t' + target.value.substr(target.selectionEnd);
			target.setSelectionRange(start + 1, start + 1);
			return false; //! still loses focus in Opera, can be solved by handling onblur
		} else if (target.createTextRange) {
			document.selection.createRange().text = '\t';
			return false;
		}
	}
	if (event.ctrlKey && (event.keyCode == 13 || event.keyCode == 10) && !event.altKey && !event.metaKey) { // shiftKey allowed
		if (button) {
			button.click();
		} else if (!target.form.onsubmit || target.form.onsubmit() !== false) {
			target.form.submit();
		}
	}
	return true;
}



/** Create AJAX request
* @param string
* @param function (text)
* @param [string]
* @return XMLHttpRequest or false in case of an error
*/
function ajax(url, callback, data) {
	var xmlhttp = (window.XMLHttpRequest ? new XMLHttpRequest() : (window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : false));
	if (xmlhttp) {
		xmlhttp.open((data ? 'POST' : 'GET'), url);
		if (data) {
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}
		xmlhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xmlhttp.onreadystatechange = function (text) {
			if (xmlhttp.readyState == 4) {
				var redirect = xmlhttp.getResponseHeader('X-AJAX-Redirect');
				if (redirect && history.replaceState) {
					history.replaceState(null, '', redirect);
				}
				var title = xmlhttp.getResponseHeader('X-AJAX-Title');
				if (title) {
					document.title = decodeURIComponent(title);
				}
				callback(xmlhttp.responseText);
			}
		};
		xmlhttp.send(data);
	}
	return xmlhttp;
}

/** Use setHtml(key, value) for JSON response
* @param string
* @return XMLHttpRequest or false in case of an error
*/
function ajaxSetHtml(url) {
	return ajax(url, function (text) {
		var data = eval('(' + text + ')');
		for (var key in data) {
			setHtml(key, data[key]);
		}
	});
}

var ajaxState = 0;
var ajaxTimeout;

/** Safely load content to #content
* @param string
* @param [string]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxSend(url, data) {
	var currentState = ++ajaxState;
	clearTimeout(ajaxTimeout);
	ajaxTimeout = setTimeout(function () {
		setHtml('content', '<img src="../adminer/static/loader.gif" alt="">');
	}, 500); // defer displaying loader
	return ajax(url, function (text) {
		if (currentState == ajaxState) {
			clearTimeout(ajaxTimeout);
			setHtml('content', text);
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
				if (href == as[i].href) {
					as[i].className = 'active';
				} else if (as[i].className == 'active') {
					as[i].className = '';
				}
			}
			//! modify Export link and Change database hidden fields
			
			if (window.jush) {
				jush.highlight_tag('code', 0);
			}
		}
	}, data);
}

/** Load content to #content
* @param string
* @param [string]
* @param [MouseEvent]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxMain(url, data, event) {
	if (!history.pushState || (event && (event.ctrlKey || event.shiftKey || event.metaKey))) {
		return false;
	}
	history.pushState(data, '', url);
	return ajaxSend(url, data);
}

/** Revive page from history
* @param PopStateEvent
*/
window.onpopstate = function (event) {
	if (ajaxState || event.state) {
		ajaxSend(location.href, event.state);
	}
}

/** Send form by AJAX GET
* @param HTMLFormElement
* @param [string]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxForm(form, data) {
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
		return ajaxMain((/\?/.test(form.action) ? form.action : location.href), params.join('&')); // ? - always part of Adminer URL
	}
	return ajaxMain((form.action || location.pathname) + '?' + params.join('&'));
}



/** Display edit field
* @param HTMLElement
* @param MouseEvent
* @param number display textarea instead of input, 2 - load long text
*/
function selectDblClick(td, event, text) {
	td.ondblclick = function () { };
	var pos = event.rangeOffset;
	var value = (td.firstChild.alt ? td.firstChild.alt : (td.textContent ? td.textContent : td.innerText));
	if (value == '\u00A0' || td.getElementsByTagName('i').length) { // &nbsp; or i - NULL
		value = '';
	}
	var input = document.createElement(text ? 'textarea' : 'input');
	input.style.width = Math.max(td.clientWidth - 14, 20) + 'px'; // 14 = 2 * (td.border + td.padding + input.border)
	if (text) {
		var rows = 1;
		value.replace(/\n/g, function () {
			rows++;
		});
		input.rows = rows;
		input.onkeypress = function (event) {
			return textareaKeypress(input, event || window.event, false, document.getElementById('save'));
		};
	}
	if (document.selection) {
		var range = document.selection.createRange();
		range.moveToPoint(event.x, event.y);
		var range2 = range.duplicate();
		range2.moveToElementText(td);
		range2.setEndPoint('EndToEnd', range);
		pos = range2.text.length;
	}
	td.innerHTML = '';
	td.appendChild(input);
	input.focus();
	if (text == 2) { // long text
		return ajax(location.href + '&' + encodeURIComponent(td.id) + '=', function (text) {
			input.value = text;
			input.name = td.id;
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
* @return bool
*/
function bodyClick(event, db, ns) {
	if (event.button) {
		return;
	}
	if (event.getPreventDefault ? event.getPreventDefault() : event.returnValue === false) {
		return false;
	}
	var el = event.target || event.srcElement;
	if (/^a$/i.test(el.parentNode.tagName)) {
		el = el.parentNode;
	}
	if (/^a$/i.test(el.tagName) && !/^https?:|#|&download=/i.test(el.getAttribute('href')) && /[&?]username=/.exec(el.href)) {
		var match = /&db=([^&]*)/.exec(el.href);
		var match2 = /&ns=([^&]*)/.exec(el.href);
		return !(db == (match ? match[1] : '') && ns == (match2 ? match2[1] : '') && ajaxMain(el.href, '', event));
	}
	if (/^input$/i.test(el.tagName) && /submit|image/.test(el.type) && el.name != 'logout' && !/&(database|scheme|create|view|sql|user|dump)=/.test(location.href)) {
		return !ajaxForm(el.form, (el.name ? encodeURIComponent(el.name) + '=1' : ''));
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
