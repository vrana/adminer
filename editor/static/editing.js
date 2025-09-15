'use strict'; // Editor specific functions

function messagesPrint() {
}

function selectFieldChange() {
}

let helpOpen;

function helpMouseover() {
}

function helpMouseout() {
}

function helpClose() {
}

/** Display typeahead
* @param string
* @this HTMLInputElement
*/
function whisper(url) {
	const field = this;
	field.orig = field.value;
	field.previousSibling.value = field.value; // accept number, reject string
	return ajax(url + encodeURIComponent(field.value), xmlhttp => {
		if (xmlhttp.status && field.orig == field.value) { // ignore old responses
			field.nextSibling.innerHTML = xmlhttp.responseText;
			field.nextSibling.style.display = '';
			const a = field.nextSibling.firstChild;
			if (a && a.firstChild.data == field.value) {
				field.previousSibling.value = decodeURIComponent(a.href.replace(/.*=/, ''));
				a.classList.add('active');
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
	const field = this.previousSibling;
	const el = event.target;
	if (isTag(el, 'a') && !(event.button || event.shiftKey || event.altKey || isCtrl(event))) {
		field.value = el.firstChild.data;
		field.previousSibling.value = decodeURIComponent(el.href.replace(/.*=/, ''));
		field.nextSibling.style.display = 'none';
		return false;
	}
}
