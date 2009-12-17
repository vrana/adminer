document.body.className = 'js';

function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
}

function verifyVersion() {
	document.cookie = 'adminer_version=0';
	var script = document.createElement('script');
	script.src = 'https://adminer.svn.sourceforge.net/svnroot/adminer/trunk/version.js';
	document.body.appendChild(script);
}

function formCheck(el, name) {
	var elems = el.form.elements;
	for (var i=0; i < elems.length; i++) {
		if (name.test(elems[i].name)) {
			elems[i].checked = el.checked;
		}
	}
}

function formUncheck(id) {
	document.getElementById(id).checked = false;
}

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

function tableClick(event) {
	var el = event.target || event.srcElement;
	while (!/^tr$/i.test(el.tagName)) {
		if (/^(table|a|input)$/i.test(el.tagName)) {
			return;
		}
		el = el.parentNode;
	}
	el = el.firstChild.firstChild;
	el.click && el.click();
	el.onclick && el.onclick();
}



function selectAddRow(field) {
	var row = field.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/[a-z]\[[0-9]+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var inputs = row.getElementsByTagName('input');
	if (inputs.length) {
		inputs[0].name = inputs[0].name.replace(/[a-z]\[[0-9]+/, '$&1');
		inputs[0].value = '';
		inputs[0].className = '';
	}
	field.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}
