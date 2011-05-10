
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



/** Handle Tab and Esc in textarea
* @param HTMLTextAreaElement
* @param KeyboardEvent
* @return boolean
*/
function textareaKeydown(target, event) {
	if (!event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey) {
		if (event.keyCode == 9) { // 9 - Tab
			// inspired by http://pallieter.org/Projects/insertTab/
			if (target.setSelectionRange) {
				var start = target.selectionStart;
				var scrolled = target.scrollTop;
				target.value = target.value.substr(0, start) + '\t' + target.value.substr(target.selectionEnd);
				target.setSelectionRange(start + 1, start + 1);
				target.scrollTop = scrolled;
				return false; //! still loses focus in Opera, can be solved by handling onblur
			} else if (target.createTextRange) {
				document.selection.createRange().text = '\t';
				return false;
			}
		}
		if (event.keyCode == 27) { // 27 - Esc
			var els = target.form.elements;
			for (var i=1; i < els.length; i++) {
				if (els[i-1] == target) {
					els[i].focus();
					break;
				}
			}
			return false;
		}
	}
	return true;
}

/** Send form by Enter on <select>
* @param KeyboardEvent
* @return boolean
*/
function bodyKeydown(event) {
	var target = event.target || event.srcElement;
	if (event.ctrlKey && (event.keyCode == 13 || event.keyCode == 10) && !event.altKey && !event.metaKey && /select|textarea/i.test(target.tagName)) { // 13|10 - Enter, shiftKey allowed
		target.blur();
		if ((!target.form.onsubmit || target.form.onsubmit() !== false) && !ajaxForm(target.form)) {
			target.form.submit();
		}
		return false;
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
	var xmlhttp = (window.XMLHttpRequest ? new XMLHttpRequest() : (window.ActiveXObject ? new ActiveXObject('Microsoft.XMLHTTP') : false));
	if (xmlhttp) {
		xmlhttp.open((data ? 'POST' : 'GET'), url);
		if (data) {
			xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}
		xmlhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4) {
				callback(xmlhttp);
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
	return ajax(url, function (xmlhttp) {
		if (xmlhttp.status) {
			var data = eval('(' + xmlhttp.responseText + ')');
			for (var key in data) {
				setHtml(key, data[key]);
			}
		}
	});
}

/** Replace favicon
* @param string
*/
function replaceFavicon(href) {
	var favicon = document.getElementById('favicon');
	favicon.href = href;
	favicon.parentNode.appendChild(favicon); // to replace the icon in Firefox
}

var ajaxState = 0;

/** Safely load content to #content
* @param string
* @param [string]
* @param [boolean]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxSend(url, data, popState) {
	if (!history.pushState) {
		return false;
	}
	var currentState = ++ajaxState;
	onblur = function () {
		replaceFavicon('../adminer/static/loader.gif');
	};
	setHtml('loader', '<img src="../adminer/static/loader.gif" alt="">');
	return ajax(url, function (xmlhttp) {
		if (currentState == ajaxState) {
			var title = xmlhttp.getResponseHeader('X-AJAX-Title');
			if (title) {
				document.title = decodeURIComponent(title);
			}
			var redirect = xmlhttp.getResponseHeader('X-AJAX-Redirect');
			if (redirect) {
				return ajaxSend(redirect, '', popState);
			}
			onblur = function () { };
			replaceFavicon('../adminer/static/favicon.ico');
			if (!xmlhttp.status) {
				setHtml('loader', '');
			} else {
				if (!popState) {
					if (data || url != location.href) {
						history.pushState(data, '', url);
					}
					scrollTo(0, 0);
				}
				setHtml('content', xmlhttp.responseText);
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
				var dump = document.getElementById('dump');
				if (dump) {
					var match = /&(select|table)=([^&]+)/.exec(href);
					dump.href = dump.href.replace(/[^=]+$/, '') + (match ? match[2] : '');
				}
				//! modify Change database hidden fields
				
				if (window.jush) {
					jush.highlight_tag('code', 0);
				}
			}
		}
	}, data);
}

/** Revive page from history
* @param PopStateEvent|history
*/
onpopstate = function (event) {
	if (ajaxState || event.state) {
		ajaxSend(location.href, (event.state && confirm(areYouSure) ? event.state : ''), 1); // 1 - disable pushState
	} else {
		ajaxState++;
	}
}

/** Send form by AJAX GET
* @param HTMLFormElement
* @param [string]
* @return XMLHttpRequest or false in case of an error
*/
function ajaxForm(form, data) {
	if (/&(database|scheme|create|view|sql|user|dump|call)=/.test(location.href) && !/\./.test(data)) { // . - type="image"
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
		return ajaxSend((/\?/.test(form.action) ? form.action : location.href), params.join('&')); // ? - always part of Adminer URL
	}
	return ajaxSend((form.action || location.href).replace(/\?.*/, '') + '?' + params.join('&'));
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
	var input = document.createElement(text ? 'textarea' : 'input');
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
		return ajax(location.href + '&' + encodeURIComponent(td.id) + '=', function (xmlhttp) {
			if (xmlhttp.status) {
				input.value = xmlhttp.responseText;
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
* @return bool
*/
function bodyClick(event, db, ns) {
	if (event.button || event.ctrlKey || event.shiftKey || event.altKey || event.metaKey) {
		return;
	}
	if (event.getPreventDefault ? event.getPreventDefault() : event.returnValue === false) {
		return false;
	}
	var el = event.target || event.srcElement;
	if (/^a$/i.test(el.parentNode.tagName)) {
		el = el.parentNode;
	}
	if (/^a$/i.test(el.tagName) && !/^:|#|&download=/i.test(el.getAttribute('href')) && /[&?]username=/.test(el.href)) {
		var match = /&db=([^&]*)/.exec(el.href);
		var match2 = /&ns=([^&]*)/.exec(el.href);
		return !(db == (match ? match[1] : '') && ns == (match2 ? match2[1] : '') && ajaxSend(el.href));
	}
	if (/^input$/i.test(el.tagName) && /image|submit/.test(el.type)) {
		return !ajaxForm(el.form, (el.name ? encodeURIComponent(el.name) + (el.type == 'image' ? '.x' : '') + '=1' : ''));
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

/** Live filter for tables in db view
 * @param ev
 */
function tableLiveFilter(ev) {
	var inp = ev.target,
		val = inp.value,
		tblTables = inp.parentNode.nextSibling,
		rows = tblTables.tBodies[0].rows;

	if(val != '') {
		for(var i = 0; i < rows.length; i++) {
			var name = rows[i].children[1].firstChild.innerHTML;
			rows[i].style.display = (name.indexOf(val) == -1) ? 'none' : '';
		}
	}
	else {
		for(var i = 0; i < rows.length; i++) rows[i].style.display = '';
	}
}