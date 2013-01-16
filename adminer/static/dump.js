function get_from_name(tag, name, document, undefined) {
  var dom = document.getElementsByTagName(tag);
  for (var i = 0; i < dom.length; ++i) {
    if (typeof(dom[i].getAttribute("name")) != "undefined" && dom[i].getAttribute("name") == name) {
      return dom[i];
    }
  }
  return;
}
var data_style = get_from_name("select", "data_style", document);
data_style.onchange = function() {
  var separate_insert = document.getElementById("separate_insert_container");
  if (this.value != undefined && this.value.contains("INSERT")) {
    separate_insert.style.visibility = "inherit";
  } else {
    separate_insert.style.visibility = "hidden";
  }
}
data_style.onchange();