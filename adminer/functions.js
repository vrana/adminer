document.body.className = 'js';

function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
}

function verify_version(version) {
	document.cookie = 'adminer_version=0';
	var script = document.createElement('script');
	script.src = 'http://www.adminer.org/version.php?version=' + version;
	document.body.appendChild(script);
}

function load_jush() {
	var script = document.createElement('script');
	script.src = '../externals/jush/jush.js';
	script.onload = function () {
		jush.style('../externals/jush/jush.css');
		jush.highlight_tag('pre');
		jush.highlight_tag('code');
	}
	script.onreadystatechange = function () {
		if (script.readyState == 'loaded' || script.readyState == 'complete') {
			script.onload();
		}
	}
	document.body.appendChild(script);
}

function form_check(el, name) {
	var elems = el.form.elements;
	for (var i=0; i < elems.length; i++) {
		if (name.test(elems[i].name)) {
			elems[i].checked = el.checked;
		}
	}
}

function form_uncheck(id) {
	document.getElementById(id).checked = false;
}



function select_add_row(field) {
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
