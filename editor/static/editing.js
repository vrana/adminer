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
* @param {string} url
* @this HTMLInputElement
*/
function whisper(url) {
	const field = this;
	field.orig = field.value;
	field.previousSibling.value = field.value; // accept number, reject string
	ajax(url + encodeURIComponent(field.value), xmlhttp => {
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
* @param {MouseEvent} event
* @return {boolean} false for success
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

// not in the object literal in functions.js - this file is loaded after it so the function wouldn't be defined yet
mixin(handlers, {
	whisperClick, // click
});
