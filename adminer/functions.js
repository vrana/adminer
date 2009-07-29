document.body.className = 'js';

function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
}

function load_script(src, onload) {
	var script = document.createElement('script');
	script.src = src;
	script.onload = onload;
	script.onreadystatechange = function () {
		if (script.readyState == 'loaded' || script.readyState == 'complete') {
			onload();
		}
	}
	document.body.appendChild(script);
}

function verify_version(version) {
	document.cookie = 'adminer_version=0';
	load_script('https://adminer.svn.sourceforge.net/svnroot/adminer/released.js', function () {
		document.cookie = 'adminer_version=' + released;
		var re = /^([0-9]+)\.([0-9]+)\.([0-9]+)(.*)/;
		var v1 = re.exec(version);
		var v2 = re.exec(released);
		if (v1 && v2 && (+v1[1] < +v2[1]
			|| (v1[1] == v2[1] && (+v1[2] < +v2[2]
			|| (v1[2] == v2[2] && (+v1[3] < +v2[3]
			|| (v1[3] == v2[3] && v1[4]
		))))))) {
			document.getElementById('version').innerHTML = released;
		}
	});
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
