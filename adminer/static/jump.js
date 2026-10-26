'use strict'; // the box searching tables, schemas, databases and the sections of the database page, opened by Ctrl+K

let jumpColumn = 0; // the action chosen by Left and Right, kept when moving between rows
const jumpSections = []; // [label, URL] of the sections and the links of the database page, filled by Adminer::navigation() and plugins

/** Set up the box searching tables, schemas and databases */
function jumpSetup() {
	const input = qs('#jump input');
	input.placeholder += (/^(Mac|iP)/.test(navigator.platform) ? ' (⌘K)' : ' (Ctrl+K)');
	input.onfocus = jumpInput;
	input.onblur = () => {
		jumpToggle(false);
		alterClass(qs('#jump'), 'fixed');
	};
}

/** Focus the search box by Ctrl+K
* @param {KeyboardEvent} event
* @return {boolean|undefined} false if the box was focused
*/
function jumpShortcut(event) {
	const input = qs('#jump input');
	if (input && isCtrl(event) && !event.shiftKey && event.key.toLowerCase() == 'k') {
		const box = input.parentNode;
		alterClass(box, 'fixed', box.getBoundingClientRect().bottom < 0); // show it at the top of the scrolled page instead of scrolling up
		input.focus();
		input.select();
		return false;
	}
}

/** Get the URL of a page with the parameters of the search form
* @param {string|string[]} params appended to the URL, e.g. 'query=x'
* @param {boolean} [keepDb] keep db and ns
* @return {string}
*/
function jumpUrl(params, keepDb) {
	const parts = [];
	for (const field of qsa('input', qs('#jump-form'))) {
		if (keepDb || !/^(db|ns)$/.test(field.name)) {
			parts.push(urlEscape(field.name) + '=' + urlEscape(field.value));
		}
	}
	return '?' + parts.concat(params).join('&');
}

/** Get the items to search: tables from the menu, schemas and databases from their selects
* @return {{tables: JumpItem[], others: JumpItem[]}}
* @typedef {{name: string, links: Array<string[]>, kind?: string, search?: boolean}} JumpItem links are [href, label], the first one without a label opens the name
*/
function jumpItems() {
	const input = qs('#jump input');
	const tables = [];
	for (const li of qsa('#tables li')) { // also the items hidden by the plugin tables-filter
		const select = qs('a.select', li);
		const main = qs('a.structure, a.view, span', li);
		if (select) {
			const href = select.getAttribute('href');
			const links = (main && main.matches('a') ? [[main.getAttribute('href')], [href, input.dataset.select]] : [[href]]);
			if (!main || !main.matches('.view')) {
				links.push([href.replace('&select=', '&edit='), input.dataset.insert]);
			}
			tables.push({name: (main || select).textContent.trim(), links}); // textContent - the plugin tables-filter wraps the matches in <strong>
		}
	}
	const db = qs('#jump-form [name="db"]');
	const others = [];
	for (const option of qsa('#dbs select[name="ns"] option')) {
		if (option.value != '' && !option.selected) {
			others.push({name: option.value, links: [[jumpUrl(['db=' + urlEscape(db.value), 'ns=' + urlEscape(option.value)])]], kind: input.dataset.schema});
		}
	}
	for (const option of qsa('#dbs select[name="db"] option')) {
		if (option.value != '' && !option.selected) {
			others.push({name: option.value, links: [[jumpUrl('db=' + urlEscape(option.value))]], kind: input.dataset.database});
		}
	}
	return {tables, others};
}

/** Print the items matching the search box
* @this HTMLInputElement
*/
function jumpInput() {
	const query = this.value; // not trim() - a leading space finds the second word, e.g. " view" in "Create view"
	if (query == '') {
		jumpToggle(false);
		return;
	}
	const lower = query.toLowerCase();
	const filter = items => {
		const starts = items.filter(item => item.name.toLowerCase().startsWith(lower));
		return starts.concat(items.filter(item => item.name.toLowerCase().indexOf(lower) > 0));
	};
	const items = jumpItems();
	const tables = filter(items.tables);
	const sections = filter(jumpSections.map(([name, url]) => ({name, links: [[url]]})));
	const others = filter(items.others);
	if (items.tables.length && this.dataset.search) { // also if no table name matches
		sections.push({name: query, links: [[jumpUrl('query=' + urlEscape(query), true), this.dataset.search]], search: true});
	}
	let list = qs('#jump-list');
	if (!list) {
		list = ce('ul', {
			id: 'jump-list',
			role: 'listbox',
			onmousedown: event => event.preventDefault(), // keep the focus in the box so that the click follows the link
			onmouseover: event => {
				const a = event.target.closest('a');
				if (a) {
					const li = a.closest('li');
					jumpSelect([...li.parentNode.children].indexOf(li), [...qsa('a', li)].indexOf(a));
				}
			},
		});
		this.parentNode.appendChild(list);
	}
	const highlight = name => {
		const parts = [];
		let start = 0;
		for (let pos; (pos = name.toLowerCase().indexOf(lower, start)) != -1; start = pos + lower.length) {
			parts.push(name.slice(start, pos), ce('strong', {}, name.substr(pos, lower.length)));
		}
		parts.push(name.slice(start));
		return parts;
	};
	list.textContent = '';
	for (const item of tables.concat(sections, others)) {
		const li = ce('li', {id: 'jump-' + list.children.length, role: 'option'});
		for (const [href, label] of item.links) {
			let a;
			if (item.search) {
				a = ce('a', {href}, label + ': ', ce('b', {}, item.name));
			} else if (label) {
				a = ce('a', {href, className: 'jump-action'}, ce('small', {}, label));
			} else {
				a = ce('a', {href}, ...highlight(item.name));
			}
			li.append(a, ' ');
		}
		if (item.kind) {
			li.append(ce('small', {}, item.kind));
		}
		list.append(li);
	}
	jumpToggle(list.children.length > 0);
	jumpSelect(0, jumpColumn);
}

/** Highlight a link in the list of the search box
* @param {number} row index of <li>, -1 for none
* @param {number} column index of <a> in the row, moved to the last one if there are fewer
*/
function jumpSelect(row, column) {
	const input = qs('#jump input');
	const list = qs('#jump-list');
	input.removeAttribute('aria-activedescendant');
	for (const li of qsa('li', list)) {
		li.setAttribute('aria-selected', false);
		alterClass(qs('.current', li), 'current');
	}
	const li = list.children[row];
	if (li) {
		const links = qsa('a', li);
		li.setAttribute('aria-selected', true);
		alterClass(links[Math.min(column, links.length - 1)], 'current', true);
		input.setAttribute('aria-activedescendant', li.id);
		// not scrollIntoView({block: 'nearest'}) - unsupported in Safari < 14
		if (li.offsetTop < list.scrollTop) {
			list.scrollTop = li.offsetTop;
		} else if (li.offsetTop + li.offsetHeight > list.scrollTop + list.clientHeight) {
			list.scrollTop = li.offsetTop + li.offsetHeight - list.clientHeight;
		}
	}
}

/** Show or hide the list of the search box
* @param {boolean} open
*/
function jumpToggle(open) {
	alterClass(qs('#jump-list'), 'hidden', !open);
	qs('#jump input').setAttribute('aria-expanded', open);
}

/** Move in the list of the search box by arrows, open the link by Enter, leave the box by Esc
* @param {KeyboardEvent} event
* @return {boolean|undefined} false if the key was handled
* @this HTMLInputElement
*/
function jumpKeydown(event) {
	const list = qs('#jump-list');
	if (event.isComposing || event.altKey) {
		return;
	}
	if (isEscape(event)) {
		this.blur(); // also closes the list and unfloats the box
		return false;
	}
	if (!list || list.classList.contains('hidden')) {
		return;
	}
	const rows = list.children;
	const li = qs('[aria-selected="true"]', list);
	const row = [...rows].indexOf(li);
	const links = (li ? qsa('a', li) : []);
	const column = [...links].indexOf(qs('.current', li || list));
	if (/^Arrow(Down|Up)$/.test(event.key) && !isCtrl(event)) {
		jumpSelect((row + (event.key == 'ArrowUp' ? rows.length - 1 : 1)) % rows.length, jumpColumn);
		return false;
	}
	if (/^Arrow(Left|Right)$/.test(event.key) && !isCtrl(event) && !event.shiftKey && links.length > 1) {
		jumpColumn = Math.max(0, Math.min(links.length - 1, column + ((event.key == 'ArrowLeft') == (document.dir != 'rtl') ? -1 : 1)));
		jumpSelect(row, jumpColumn);
		return false;
	}
	if (event.key == 'Enter' && links[column]) {
		const href = links[column].href;
		if (isCtrl(event) || event.shiftKey) {
			open(href);
		} else {
			location.href = href;
		}
		return false;
	}
}
