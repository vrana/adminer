<?php

/**
 * Suggests fields and tablenames
 * @author Andrea Mariani, fasys.it
 */
class AdminerSuggestTableField
{
	public function head(){
		if (!isset($_GET['sql'])) {
			return;
		}

		$suggests = [
            '___mysql___' => [
                'DELETE FROM', 'DISTINCT', 'EXPLAIN', 'FROM', 'GROUP BY', 'HAVING', 'INSERT INTO', 'INNER JOIN', 'IGNORE',
                'LIMIT', 'LEFT JOIN', 'NULL', 'ORDER BY', 'ON DUPLICATE KEY UPDATE', 'SELECT', 'UPDATE', 'WHERE',
            ]
        ];

		foreach (array_keys(tables_list()) as $table) {
			$suggests['___tables___'][] = $table;
			foreach (fields($table) as $field => $foo) {
				$suggests[$table][] = $field;
			}
		}

    ?>
    <style>
        #suggest_tablefields_container{min-width:150px;margin:0;padding:0;overflow-y:auto;position:absolute;}
        #suggest_tablefields{list-style:none;}
        #suggest_tablefields dd{margin:0;}
        #suggest_tablefields dd strong{background-color:#ff0;}
        #suggest_search{width:90%;}
        /*textarea.sqlarea {display: block!important;}*/
    </style>
    <script<?php echo nonce(); ?> type="text/javascript">

        function domReady(fn) {
            document.addEventListener("DOMContentLoaded", fn)
            if (document.readyState === "interactive" || document.readyState === "complete" ) {
                fn()
            }
        }

        function insertNodeAtCaret(node) {
            if (typeof window.getSelection != "undefined") {
                var sel = window.getSelection()
                if (sel.rangeCount) {
                    var range = sel.getRangeAt(0)
                    range.collapse(false)
                    range.insertNode(node)
                    range = range.cloneRange()
                    range.selectNodeContents(node)
                    range.collapse(false)
                    sel.removeAllRanges()
                    sel.addRange(range)
                }
            } else if (typeof document.selection != "undefined" && document.selection.type != "Control") {
                var html = (node.nodeType == 1) ? node.outerHTML : node.data
                var id = "marker_" + ("" + Math.random()).slice(2)
                html += '<span id="' + id + '"></span>'
                var textRange = document.selection.createRange()
                textRange.collapse(false)
                textRange.pasteHTML(html)
                var markerSpan = document.getElementById(id)
                textRange.moveToElementText(markerSpan)
                textRange.select()
                markerSpan.parentNode.removeChild(markerSpan)
            }
        }

        function getTable(suggests, tableName){
            var table =  "<dt><strong>"+ tableName +"</strong></dt>"
            for(var k in suggests[tableName]){
                table += "<dd><a href='#' data-text='"+ tableName + "`.`" + suggests[tableName][k] +"'>"+ suggests[tableName][k] +"</a></dd>"
            }
            return table
        }

        function compile(data){
            document.getElementById('suggest_tablefields').innerHTML = data
            document.getElementById('suggest_search').value = '';
            //console.log(data)
        }

        domReady(() => {
            const suggests = JSON.parse('<?php echo json_encode($suggests) ?>')
            const form = document.getElementById('form')
            const sqlarea = document.getElementsByClassName('sqlarea')[0]
            form.style.position = "relative"

            var suggests_mysql = ""

            suggests_mysql += "<dt><strong><?php echo lang('Tables') ?></strong></dt>"
            for(var k in suggests['___tables___']){
                suggests_mysql += "<dd><a href='#' data-table='1'>"+ suggests['___tables___'][k] +"</a></dd>"
            }
            suggests_mysql += "<dt><strong><?php echo lang('SQL command') ?></strong></dt>"
            for(var k in suggests['___mysql___']){
                suggests_mysql += "<dd><a href='#' data-nobt='1'>"+ suggests['___mysql___'][k] +"</a></dd>"
            }

            form.insertAdjacentHTML('afterbegin', '<dl id="suggest_tablefields_container" style="height:'+ sqlarea.offsetHeight +'px;top:0;left:'+ (sqlarea.offsetWidth + 3) +'px"><input autocomplete="off" id="suggest_search" type="text" placeholder="<?php echo lang('Search') ?>..."/><div id="suggest_tablefields"></div></dl>')
            compile(suggests_mysql)


            document.addEventListener('click', function (event) {
                if(event.target.getAttribute('id') === 'suggest_search'){
                    return
                }
                if (event.target.matches('.jush-custom')) {
                    var table = getTable(suggests, event.target.textContent)
                    compile(table)
                    return
                }

                if (!event.target.matches('#suggest_tablefields') && !event.target.matches('a') && !event.target.matches('strong') && !event.target.matches('.sqlarea') && !event.target.matches('.jush-sql_code') && !event.target.matches('.jush-bac') && !event.target.matches('.jush-op')){
                    compile(suggests_mysql)
                    return
                }

            }, false)

            document.getElementById('suggest_tablefields').addEventListener('click', function (event){
                if(event.target.matches('a') || event.target.matches('strong')){
                    var target, text, bt = "`"
                    if(event.target.matches('strong')) {
                        target = event.target = event.target.parentElement
                    }
                    else{
                        target = event.target
                    }

                    text = target.textContent
                    sqlarea.focus()

                    if(target.getAttribute("data-text")){
                        text = target.getAttribute("data-text")
                    }
                    if(target.getAttribute("data-nobt")){
                        bt = ""
                    }

                    insertNodeAtCaret(document.createTextNode(bt + text + bt + " "))

                    if(target.getAttribute("data-table")){
                        var table = getTable(suggests, target.textContent)
                        compile(table)
                    }

                    sqlarea.dispatchEvent(new KeyboardEvent('keyup'))
                }
            }, false)


            document.getElementById('suggest_search').addEventListener('keyup', function () {
                var value = this.value.toLowerCase()

                if (value != '') {
                    var reg = (value + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, '\\$1')
                    reg = new RegExp('('+ reg + ')', 'gi')
                }

                var tables = qsa('dd a', qs('#suggest_tablefields'))
                for (var i = 0; i < tables.length; i++) {
                    var a = tables[i]
                    var text = tables[i].textContent
                    if (value == '') {
                        tables[i].className = ''
                        a.innerHTML = text
                    } else {
                        tables[i].className = (text.toLowerCase().indexOf(value) == -1 ? 'hidden' : '')
                        a.innerHTML = text.replace(reg, '<strong>$1</strong>')
                    }
                }

            }, false)

        })

    </script>
    <?php
	}
}
