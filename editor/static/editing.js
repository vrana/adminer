// Editor specific functions

function messagesPrint() {
}

function selectFieldChange() {
}

var helpOpen;

function helpMouseover() {
}

function helpMouseout() {
}

/** Display typeahead
* @param string
* @this HTMLInputElement
*/
function whisper(url) {
	var field = this;
	field.orig = field.value;
	field.previousSibling.value = field.value; // accept number, reject string
	return ajax(url + encodeURIComponent(field.value), function (xmlhttp) {
		if (xmlhttp.status && field.orig == field.value) { // ignore old responses
			field.nextSibling.innerHTML = xmlhttp.responseText;
			field.nextSibling.style.display = '';
			var a = field.nextSibling.firstChild;
			if (a && a.firstChild.data == field.value) {
				field.previousSibling.value = decodeURIComponent(a.href.replace(/.*=/, ''));
				a.className = 'active';
			}
		}
	});
}

/** Select typeahead value
* @param MouseEvent
* @return boolean false for success
* @this HTMLDivElement
*/
function whisperClick(event) {
	var field = this.previousSibling;
	var el = getTarget(event);
	if (isTag(el, 'a') && !(event.button || event.shiftKey || event.altKey || isCtrl(event))) {
		field.value = el.firstChild.data;
		field.previousSibling.value = decodeURIComponent(el.href.replace(/.*=/, ''));
		field.nextSibling.style.display = 'none';
		return false;
	}
}

/** Add new attachment field
* @this HTMLInputElement
*/
function emailFileChange() {
	this.onchange = function () { };
	var el = this.cloneNode(true);
	el.value = '';
	this.parentNode.appendChild(el);
}
