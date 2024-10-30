// Editor specific functions

function messagesPrint() {
}

function selectFieldChange() {
}

// Help.
(function() {
	window.initHelpPopup = function () {
	};

	window.initHelpFor = function(element, content, side = false) {
	};
})();

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
	var el = event.target;
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
	var el = this.cloneNode(true);
	this.onchange = function () { };
	el.value = '';
	this.parentNode.appendChild(el);
}
