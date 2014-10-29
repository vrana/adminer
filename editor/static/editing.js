// Editor specific functions

function selectFieldChange() {
}

var helpOpen;

function helpMouseover() {
}

function helpMouseout() {
}

function whisperClick(event, field) {
	var el = getTarget(event);
	if (isTag(el, 'a') && !(event.button || event.shiftKey || event.altKey || isCtrl(event))) {
		field.value = el.firstChild.data;
		field.previousSibling.value = decodeURIComponent(el.href.replace(/.*=/, ''));
		field.nextSibling.style.display = 'none';
		return false;
	}
}

function whisper(url, field) {
	if (field.orig != field.value) { // ignore arrows, Shift, ...
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
}
