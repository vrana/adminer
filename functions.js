document.body.className = 'js';

function toggle(id) {
	var el = document.getElementById(id);
	el.className = (el.className == 'hidden' ? '' : 'hidden');
	return true;
}

function verify_version(version) {
	document.cookie = 'phpMinAdmin_version=0';
	var script = document.createElement('script');
	script.src = 'http://www.phpminadmin.net/version.php?version=' + version;
	document.body.appendChild(script);
}

function load_jush() {
	var script = document.createElement('script');
	script.src = 'externals/jush/jush.js';
	script.onload = function () {
		jush.style('externals/jush/jush.css');
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



function where_change(op) {
	for (var i=0; i < op.form.elements.length; i++) {
		var el = op.form.elements[i];
		if (el.name == op.name.substr(0, op.name.length - 4) + '[val]') {
			el.className = (/NULL$/.test(op.options[op.selectedIndex].text) ? 'hidden' : '');
		}
	}
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



var added = '.', row_count;

function editing_add_row(button, allowed) {
	if (allowed && row_count >= allowed) {
		return false;
	}
	var match = /([0-9]+)(\.[0-9]+)?/.exec(button.name);
	var x = match[0] + (match[2] ? added.substr(match[2].length) : added) + '1';
	var row = button.parentNode.parentNode;
	var row2 = row.cloneNode(true);
	var tags = row.getElementsByTagName('select');
	var tags2 = row2.getElementsByTagName('select');
	for (var i=0; i < tags.length; i++) {
		tags[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		tags2[i].selectedIndex = tags[i].selectedIndex;
	}
	tags = row.getElementsByTagName('input');
	for (var i=0; i < tags.length; i++) {
		if (tags[i].name == 'auto_increment_col') {
			tags[i].value = x;
			tags[i].checked = false;
		}
		tags[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		if (/\[(orig|field|comment)/.test(tags[i].name)) {
			tags[i].value = '';
		}
	}
	row.parentNode.insertBefore(row2, row);
	tags[0].focus();
	added += '0';
	row_count++;
	return true;
}

function editing_remove_row(button) {
	var field = button.form[button.name.replace(/drop_col(.+)/, 'fields$1[field]')];
	field.parentNode.removeChild(field);
	button.parentNode.parentNode.style.display = 'none';
	//! should change class="odd" of next rows
	return true;
}

function editing_type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	for (var i=0; i < type.form.elements.length; i++) {
		var el = type.form.elements[i];
		if (el.name == name + '[collation]') {
			el.className = (/char|text|enum|set/.test(type.options[type.selectedIndex].text) ? '' : 'hidden');
		}
		if (el.name == name + '[unsigned]') {
			el.className = (/int|float|double|decimal/.test(type.options[type.selectedIndex].text) ? '' : 'hidden');
		}
	}
}

function column_comments_click(checked) {
	var trs = document.getElementById('edit-fields').getElementsByTagName('tr');
	for (var i=0; i < trs.length; i++) {
		trs[i].getElementsByTagName('td')[5].className = (checked ? '' : 'hidden');
	}
}

function partition_by_change(el) {
	var partition_table = /RANGE|LIST/.test(el.options[el.selectedIndex].text);
	el.form['partitions'].className = (partition_table || !el.selectedIndex ? 'hidden' : '');
	document.getElementById('partition-table').className = (partition_table ? '' : 'hidden');
}

function partition_name_change(el) {
	var row = el.parentNode.parentNode.cloneNode(true);
	row.firstChild.firstChild.value = '';
	el.parentNode.parentNode.parentNode.appendChild(row);
	el.onchange = function () {};
}



function foreign_add_row(field) {
	var row = field.parentNode.parentNode.cloneNode(true);
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/\]/, '1$&');
		selects[i].selectedIndex = 0;
	}
	field.parentNode.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}



function indexes_add_row(field) {
	var row = field.parentNode.parentNode.cloneNode(true);
	var spans = row.getElementsByTagName('span');
	row.getElementsByTagName('td')[1].innerHTML = '<span>' + spans[spans.length - 1].innerHTML + '</span>';
	var selects = row.getElementsByTagName('select');
	for (var i=0; i < selects.length; i++) {
		selects[i].name = selects[i].name.replace(/indexes\[[0-9]+/, '$&1');
		selects[i].selectedIndex = 0;
	}
	var input = row.getElementsByTagName('input')[0];
	input.name = input.name.replace(/indexes\[[0-9]+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.parentNode.appendChild(row);
	field.onchange = function () { };
}

function indexes_add_column(field) {
	var column = field.parentNode.cloneNode(true);
	var select = column.getElementsByTagName('select')[0];
	select.name = select.name.replace(/\]\[[0-9]+/, '$&1');
	select.selectedIndex = 0;
	var input = column.getElementsByTagName('input')[0];
	input.name = input.name.replace(/\]\[[0-9]+/, '$&1');
	input.value = '';
	field.parentNode.parentNode.appendChild(column);
	field.onchange = function () { };
}



var that, x, y, em, table_pos;

function schema_mousedown(el, event) {
	that = el;
	x = event.clientX - el.offsetLeft;
	y = event.clientY - el.offsetTop;
}

function schema_mousemove(ev) {
	if (that !== undefined) {
		ev = ev || event;
		var left = (ev.clientX - x) / em;
		var top = (ev.clientY - y) / em;
		var divs = that.getElementsByTagName('div');
		var line_set = { };
		for (var i=0; i < divs.length; i++) {
			if (divs[i].className == 'references') {
				var div2 = document.getElementById((divs[i].id.substr(0, 4) == 'refs' ? 'refd' : 'refs') + divs[i].id.substr(4));
				var ref = (table_pos[divs[i].title] ? table_pos[divs[i].title] : [ div2.parentNode.offsetTop / em, 0 ]);
				var left1 = -1;
				var is_top = true;
				var id = divs[i].id.replace(/^ref.(.+)-.+/, '$1');
				if (divs[i].parentNode != div2.parentNode) {
					left1 = Math.min(0, ref[1] - left) - 1;
					divs[i].style.left = left1 + 'em';
					divs[i].getElementsByTagName('div')[0].style.width = -left1 + 'em';
					var left2 = Math.min(0, left - ref[1]) - 1;
					div2.style.left = left2 + 'em';
					div2.getElementsByTagName('div')[0].style.width = -left2 + 'em';
					is_top = (div2.offsetTop + ref[0] * em > divs[i].offsetTop + top * em);
				}
				if (!line_set[id]) {
					var line = document.getElementById(divs[i].id.replace(/^....(.+)-[0-9]+$/, 'refl$1'));
					var shift = ev.clientY - y - that.offsetTop;
					line.style.left = (left + left1) + 'em';
					if (is_top) {
						line.style.top = (line.offsetTop + shift) / em + 'em';
					}
					if (divs[i].parentNode != div2.parentNode) {
						line = line.getElementsByTagName('div')[0];
						line.style.height = (line.offsetHeight + (is_top ? -1 : 1) * shift) / em + 'em';
					}
					line_set[id] = true;
				}
			}
		}
		that.style.left = left + 'em';
		that.style.top = top + 'em';
	}
}

function schema_mouseup(ev) {
	if (that !== undefined) {
		ev = ev || event;
		table_pos[that.firstChild.firstChild.firstChild.data] = [ (ev.clientY - y) / em, (ev.clientX - x) / em ];
		that = undefined;
		var date = new Date();
		date.setMonth(date.getMonth() + 1);
		var s = '';
		for (var key in table_pos) {
			s += '_' + key + ':' + Math.round(table_pos[key][0] * 10000) / 10000 + 'x' + Math.round(table_pos[key][1] * 10000) / 10000;
		}
		document.cookie = 'schema=' + encodeURIComponent(s.substr(1)) + '; expires=' + date + '; path=' + location.pathname + location.search;
	}
}
