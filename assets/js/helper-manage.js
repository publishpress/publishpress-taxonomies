jQuery(document).ready(function() {
    jQuery(".tag-cloud-link").click(function(event) {
      event.preventDefault();
      var tag = jQuery(this).find('.row-title').html();
      if (!jQuery('#addterm_match').is(":hidden")) {
        st_add_term(tag, "addterm_match");
      }
      st_add_term(tag, "addterm_new");
      st_add_term(tag, "renameterm_old");
      st_add_term(tag, "mergeterm_old");
      st_add_term(tag, "remove_term");
      
        return false;
    });
});

// Add tag into input
function st_add_term(tag, name_element) {
    var input_element = document.getElementById(name_element);

    if (!input_element) {
        return false;
    }

    if (input_element.value.length > 0 && !input_element.value.match(/,\s*$/))
        input_element.value += ", ";

    var comma = new RegExp(tag + ",");
    if (!input_element.value.match(comma))
        input_element.value += tag + ", ";

    return true;
}
