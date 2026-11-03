jQuery(document).ready(function () {

  if (stHelperClickTagsL10n.state === 'hide') {
    // Display initial link
    jQuery('#st-clicks-tags .inside').html('' + stHelperClickTagsL10n.click_tags_options + ' <a href="#st_click_tags" id="open_clicktags">' + stHelperClickTagsL10n.show_txt + '</a><span id="close_clicktags">' + stHelperClickTagsL10n.hide_txt + '</span><div class="container_clicktags" role="group" aria-label="' + stHelperClickTagsL10n.arial_label + '"></div>');
  } else {
    jQuery('#st-clicks-tags .inside').html('' + stHelperClickTagsL10n.click_tags_options + ' <a href="#st_click_tags" id="open_clicktags">' + stHelperClickTagsL10n.show_txt + '</a><span id="close_clicktags">' + stHelperClickTagsL10n.hide_txt + '</span><div class="container_clicktags" role="group" aria-label="' + stHelperClickTagsL10n.arial_label + '"></div>');
  }
  jQuery('#st-clicks-tags .handle-actions').prepend(html_entity_decode(stHelperClickTagsL10n.edit_metabox_link));

  // Take current post ID
  current_post_id = jQuery('#post_ID').val();

  if (stHelperClickTagsL10n.state === 'show') {
    load_click_tags();
  }

  // Show click tags
  jQuery('#open_clicktags').click(function (event) {
    event.preventDefault();
    load_click_tags();
    return false;
  });

});

function load_click_tags(search = '') {
  if (search) {
    jQuery(".click-tag-search-box").css("background", "url(" + stHelperClickTagsL10n.search_icon + ") no-repeat 99%");
  }
  var click_tags_taxonomy = jQuery('.click_tags_taxonomy option:selected').val();
  var click_tags_method   = jQuery('.click_tags_method option:selected').val();
  var click_tags_order    = jQuery('.click_tags_order option:selected').val();
  var click_tags_limit    = jQuery('.click_tags_limit').val();

  jQuery('#st-clicks-tags .container_clicktags')
    .fadeIn('slow')
    .load(ajaxurl + '?' + jQuery.param({
      action: 'simpletags',
      stags_action: 'click_tags',
      post_id: current_post_id,
      click_tags_taxonomy: click_tags_taxonomy,
      click_tags_method: click_tags_method,
      click_tags_order: click_tags_order,
      click_tags_limit: click_tags_limit,
      q: search,
      nonce: stHelperClickTagsL10n.nonce
    }), function () {
      jQuery('#st-clicks-tags .container_clicktags span').click(function (event) {
        event.preventDefault();
        var taxonomy = jQuery(this).attr('data-taxonomy');
        var term_id = jQuery(this).attr('data-term_id');
        addTag(this.textContent, taxonomy, term_id);
        jQuery(this).addClass('used_term');
        jQuery(this).attr('aria-pressed', 'true');
      });

      jQuery('#open_clicktags').hide();
      jQuery('#close_clicktags').show();
      jQuery(".click-tag-search-box").css("background", "");
    });
}

/**
 * The html_entity_decode() php function on JS :)
 *
 * See : https://github.com/hirak/phpjs
 *
 * @param str
 * @returns {string | *}
 */
function html_entity_decode(str) {
  var parser = new DOMParser();
  var safeString = String(str).replace(/</g, '&lt;').replace(/>/g, '&gt;');
  var parsed = parser.parseFromString(safeString, 'text/html');

  return parsed.documentElement.textContent || '';
}

//inititiate click tags search when user start typying
    //setup before functions
    var typingTimer;                //timer identifier
    var doneTypingInterval = 500;  //time in ms

    //on keyup, start the countdown
    jQuery(document).on('keyup', '.click-tag-search-box', function () {
        clearTimeout(typingTimer);
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    //on dropdown change, start search
    jQuery(document).on('change', '.click_tags_taxonomy, .click_tags_method, .click_tags_order, .click_tags_limit', function () {
      load_click_tags(jQuery('.click-tag-search-box').val());
    });

    //user is "finished typing," do something
    function doneTyping() {
      load_click_tags(jQuery('.click-tag-search-box').val());
    }
