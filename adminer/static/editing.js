// Adminer specific functions

function body_load() {
	var jush_root = '../externals/jush/';
	var script = document.createElement('script');
	script.src = jush_root + 'jush.js';
	script.onload = function () {
		if (window.jush) { // IE runs in case of an error too
			jush.create_links = ' target="_blank"';
			jush.style(jush_root + 'jush.css');
			jush.highlight_tag('pre');
			jush.highlight_tag('code');
		}
	};
	script.onreadystatechange = function () {
		if (/^(loaded|complete)$/.test(script.readyState)) {
			script.onload();
		}
	};
	document.body.appendChild(script);
}



function select_value(select) {
	return select.options[select.selectedIndex].text;
}

function form_field(form, name) {
	for (var i=0; i < form.length; i++) {
		if (form[i].name == name) {
			return form[i];
		}
	}
}

function type_password(el, disable) {
	try {
		el.type = (disable ? 'text' : 'password');
	} catch (e) {
	}
}



var added = '.', row_count;

function re_escape(s) {
	return s.replace(/[\[\]\\^$*+?.(){|}]/, '\\$&');
}

function idf_escape(s) {
	return '`' + s.replace(/`/, '``') + '`';
}

function editing_name_change(field) {
	var name = field.name.substr(0, field.name.length - 7);
	var type = form_field(field.form, name + '[type]');
	var opts = type.options;
	var table = re_escape(field.value);
	var column = '';
	var match;
	if ((match = /(.+)_(.+)/.exec(table)) || (match = /(.*[a-z])([A-Z].*)/.exec(table))) { // limited to single word columns
		table = match[1];
		column = match[2];
	}
	var plural = '(?:e?s)?';
	var tab_col = table + plural + '_?' + column;
	var re = new RegExp('(^' + idf_escape(table + plural) + '\\.' + idf_escape(column) + '$' // table_column
		+ '|^' + idf_escape(tab_col) + '\\.' // table
		+ '|^' + idf_escape(column + plural) + '\\.' + idf_escape(table) + '$' // column_table
		+ ')|\\.' + idf_escape(tab_col) + '$' // column
	, 'i');
	var candidate; // don't select anything with ambiguous match (like column `id`)
	for (var i = opts.length; i--; ) {
		if (opts[i].value.substr(0, 1) != '`') { // common type
			if (i == opts.length - 2 && candidate && !match[1] && name == 'fields[1]') { // single target table, link to column, first field - probably `id`
				return false;
			}
			break;
		}
		if (match = re.exec(opts[i].value)) {
			if (candidate) {
				return false;
			}
			candidate = i;
		}
	}
	if (candidate) {
		type.selectedIndex = candidate;
		type.onchange();
	}
}

function editing_add_row(button, allowed, focus) {
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
		tags2[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		tags2[i].selectedIndex = tags[i].selectedIndex;
	}
	tags = row.getElementsByTagName('input');
	tags2 = row2.getElementsByTagName('input');
	var input = tags2[0]; // IE loose tags2 after insertBefore()
	for (var i=0; i < tags.length; i++) {
		if (tags[i].name == 'auto_increment_col') {
			tags2[i].value = x;
			tags2[i].checked = false;
		}
		tags2[i].name = tags[i].name.replace(/([0-9.]+)/, x);
		if (/\[(orig|field|comment|default)/.test(tags[i].name)) {
			tags2[i].value = '';
		}
		if (/\[(has_default)/.test(tags[i].name)) {
			tags2[i].checked = false;
		}
	}
	tags[0].onchange = function () {
		editing_name_change(tags[0]);
	};
	row.parentNode.insertBefore(row2, row.nextSibling);
	if (focus) {
		input.onchange = function () {
			editing_name_change(input);
		};
		input.focus();
	}
	added += '0';
	row_count++;
	return true;
}

function editing_remove_row(button) {
	var field = form_field(button.form, button.name.replace(/drop_col(.+)/, 'fields$1[field]'));
	field.parentNode.removeChild(field);
	button.parentNode.parentNode.style.display = 'none';
	return true;
}

function editing_type_change(type) {
	var name = type.name.substr(0, type.name.length - 6);
	var text = select_value(type);
	for (var i=0; i < type.form.elements.length; i++) {
		var el = type.form.elements[i];
		if (el.name == name + '[collation]') {
			el.className = (/(char|text|enum|set)$/.test(text) ? '' : 'hidden');
		}
		if (el.name == name + '[unsigned]') {
			el.className = (/(int|float|double|decimal)$/.test(text) ? '' : 'hidden');
		}
	}
}

function editing_length_focus(field) {
	var td = field.parentNode;
	if (/enum|set/.test(select_value(td.previousSibling.firstChild))) {
		var edit = document.getElementById('enum-edit');
		var val = field.value;
		edit.value = (/^'.+','.+'$/.test(val) ? val.substr(1, val.length - 2).replace(/','/g, "\n").replace(/''/g, "'") : val);
		td.appendChild(edit);
		field.style.display = 'none';
		edit.style.display = 'inline';
		edit.focus();
	}
}

function editing_length_blur(edit) {
	var field = edit.parentNode.firstChild;
	var val = edit.value;
	field.value = (/\n/.test(val) ? "'" + val.replace(/\n+$/, '').replace(/'/g, "''").replace(/\n/g, "','") + "'" : val);
	field.style.display = 'inline';
	edit.style.display = 'none';
}

function column_show(checked, column) {
	var trs = document.getElementById('edit-fields').getElementsByTagName('tr');
	for (var i=0; i < trs.length; i++) {
		trs[i].getElementsByTagName('td')[column].className = (checked ? 'nowrap' : 'hidden');
	}
}

function partition_by_change(el) {
	var partition_table = /RANGE|LIST/.test(select_value(el));
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
	for (var i=0; i < spans.length - 1; i++) {
		row.removeChild(spans[i]);
	}
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
		document.cookie = 'adminer_schema=' + encodeURIComponent(s.substr(1)) + '; expires=' + date + '; path=' + location.pathname + location.search;
	}
}
