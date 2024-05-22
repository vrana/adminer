// Adminer specific functions

/** Load syntax highlighting
* @param string first three characters of database system version
* @param [boolean]
*/
function bodyLoad(version, maria) {
    if (window.jush) {
        jush.create_links = ' target="_blank" rel="noreferrer noopener"';
        
        if (version) {
            for (let key in jush.urls) {
                let obj = jush.urls[key];
                
                if (typeof obj !== 'string') {
                    obj = obj[0];
                    
                    if (maria) {
                        for (let i = 1; i < obj.length; i++) {
                            obj[i] = obj[i]
                                .replace(/\.html/, '/')
                                .replace(/-type-syntax/, '-data-types')
                                .replace(/numeric-(data-types)/, '$1-$&')
                                .replace(/#statvar_.*/, '#$$1');
                        }
                    }
                }
                
                jush.urls[key] = (maria 
                    ? obj.replace(/dev\.mysql\.com\/doc\/mysql\/en\//, 'mariadb.com/kb/en/library/') 
                    : obj
                )
                .replace(/\/doc\/mysql/, `/doc/refman/${version}`)
                .replace(/\/docs\/current/, `/docs/${version}`);
            }
        }
        
        if (window.jushLinks) {
            jush.custom_links = jushLinks;
        }
        
        jush.highlight_tag('code', 0);
        highlightTextAreas();
    }
}

/** Highlight textarea tags with a class name starting with 'jush-' */
function highlightTextAreas() {
    const tags = qsa('textarea');
    for (let i = 0; i < tags.length; i++) {
        if (/(^|\s)jush-/.test(tags[i].className)) {
            const pre = jush.textarea(tags[i]);
            if (pre) {
                setupSubmitHighlightInput(pre);
            }
        }
    }
}

/** Get value of dynamically created form field */
function formField(form, name) {
    // Required in IE < 8, form.elements[name] doesn't work
    for (let i = 0; i < form.length; i++) {
        if (form[i].name === name) {
            return form[i];
        }
    }
}

/** Try to change input type to password or to text */
function typePassword(el, disable) {
    try {
        el.type = disable ? 'text' : 'password';
    } catch (e) {
        // Handle exception
    }
}

/** Install toggle handler */
function messagesPrint(el) {
    const els = qsa('.toggle', el);
    for (let i = 0; i < els.length; i++) {
        els[i].onclick = partial(toggle, els[i].getAttribute('href').substr(1));
    }
}

/** Hide or show some login rows for selected driver */
function loginDriver(driver) {
    const trs = parentTag(driver, 'table').rows;
    const disabled = /sqlite/.test(selectValue(driver));
    alterClass(trs[1], 'hidden', disabled); // 1 - row with server
    trs[1].getElementsByTagName('input')[0].disabled = disabled;
}

// Other utility functions...

var dbCtrl;
var dbPrevious = {};

/** Check if database should be opened to a new window */
function dbMouseDown(event) {
    dbCtrl = isCtrl(event);
    if (dbPrevious[this.name] === undefined) {
        dbPrevious[this.name] = this.value;
    }
}

/** Load database after selecting it */
function dbChange() {
    if (dbCtrl) {
        this.form.target = '_blank';
    }
    this.form.submit();
    this.form.target = '';
    if (dbCtrl && dbPrevious[this.name] !== undefined) {
        this.value = dbPrevious[this.name];
        dbPrevious[this.name] = undefined;
    }
}

/** Check whether the query will be executed with index */
function selectFieldChange() {
    const form = this.form;
    const ok = (function () {
        const inputs = qsa('input', form);
        for (let i = 0; i < inputs.length; i++) {
            if (inputs[i].value && /^fulltext/.test(inputs[i].name)) {
                return true;
            }
        }
        let ok = form.limit.value;
        const selects = qsa('select', form);
        let group = false;
        const columns = {};
        for (let i = 0; i < selects.length; i++) {
            const select = selects[i];
            const col = selectValue(select);
            let match = /^(where.+)col\]/.exec(select.name);
            if (match) {
                const op = selectValue(form[match[1] + 'op]']);
                const val = form[match[1] + 'val]'].value;
                if (col in indexColumns && (!/LIKE|REGEXP/.test(op) || (op === 'LIKE' && val.charAt(0) !== '%'))) {
                    return true;
                } else if (col || val) {
                    ok = false;
                }
            }
            if ((match = /^(columns.+)fun\]/.exec(select.name))) {
                if (/^(avg|count|count distinct|group_concat|max|min|sum)$/.test(col)) {
                    group = true;
                }
                const val = selectValue(form[match[1] + 'col]']);
                if (val) {
                    columns[col && col !== 'count' ? '' : val] = 1;
                }
            }
            if (col && /^order/.test(select.name)) {
                if (!(col in indexColumns)) {
                    ok = false;
                }
                break;
            }
        }
        if (group) {
            for (const col in columns) {
                if (!(col in indexColumns)) {
                    ok = false;
                }
            }
        }
        return ok;
    })();
    setHtml('noindex', ok ? '' : '!');
}

// More functions...

