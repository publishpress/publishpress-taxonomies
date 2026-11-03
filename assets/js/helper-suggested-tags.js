jQuery(document).ready(function ($) {
  
  $("div[id*='suggestedtags-']").each(function () {
    var current_metabox    = $(this);
    var metabox_data_id    = current_metabox.find('.container_clicktags').attr('data-key_index');
    var click_terms        = stHelperSuggestedTagsL10n.click_terms;
    var click_term         = click_terms[metabox_data_id];
    var use_local          = Number(click_term.suggest_term_use_local);
    var use_dandelion      = Number(click_term.suggest_term_use_dandelion);
    var use_opencalais     = Number(click_term.suggest_term_use_opencalais);
    var term_source_count  = use_local + use_dandelion + use_opencalais;
    var source_select_style = '';//term_source_count > 1 ? '' : 'display:none;';
    var meta_title_content = stHelperSuggestedTagsL10n.content_bloc;
    var meta_title_block   = '';
    var meta_title_action  = '';
    var taxonomies         = click_term.taxonomy_labels;
    var suggest_terms_active = false;

    //prepare title
    meta_title_block       += '<img style="display:none;" class="st_ajax_loading" src="' + stHelperSuggestedTagsL10n.stag_url + '/assets/images/ajax-loader.gif" />';
    meta_title_block       += click_term.title;
    meta_title_block       += '<select style="' + source_select_style + '" class="term_suggestion_select" name="term_suggestion_select"  data-suggestterms="' + click_term.ID + '">';
    meta_title_block         += '<option value="" selected="selected">' + stHelperSuggestedTagsL10n.source_text + '</option>';
    if (use_local > 0) {
      suggest_terms_active = true;
      for (var key in taxonomies) {
        if (taxonomies.hasOwnProperty(key)) {
          meta_title_block       += '<option value="tags_from_local_db" data-taxonomy="' + key + '">' + stHelperSuggestedTagsL10n.local_term_text + ' (' + taxonomies[key] + ')</option>';
        }
      }
    }
    if (use_dandelion > 0) {
      suggest_terms_active = true;
      meta_title_block       += '<option value="tags_from_datatxt">' + stHelperSuggestedTagsL10n.dandelion_text + '</option>';
    }
    if (use_opencalais > 0) {
      suggest_terms_active = true;
      meta_title_block       += '<option value="tags_from_opencalais">' + stHelperSuggestedTagsL10n.opencalais_text + '</option>';
    }
    meta_title_block += '</select> <button class="term_suggestion_refresh">' + stHelperSuggestedTagsL10n.refresh_text + '</button>';
    
    //prepare action
    if (Number(stHelperSuggestedTagsL10n.manage_metabox) > 0) {
      meta_title_action += '<span class="edit-suggest-term-metabox">';
      meta_title_action += '<a href="' + stHelperSuggestedTagsL10n.manage_link + '&taxopress_suggestterms=' + click_term.ID + '">';
      meta_title_action += stHelperSuggestedTagsL10n.edit_metabox_text;
      meta_title_action += '</a>';
      meta_title_action += '</span>';
    }

    if (!suggest_terms_active) {
      meta_title_block = '';
    }

    //add data to metabox
    current_metabox.find('.hndle').html(html_entity_decode(meta_title_block));
    current_metabox.find('.inside .container_clicktags').html(meta_title_content);
    current_metabox.find('.handle-actions').prepend(html_entity_decode(meta_title_action));

  });

  // Generi call for autocomplete API
  jQuery('a.suggest-action-link').click(function (event) {
    event.preventDefault();

    jQuery('#st_ajax_loading').show();

    jQuery('#suggestedtags .container_clicktags').load(ajaxurl + '?action=simpletags&stags_action=' + jQuery(this).data('ajaxaction') + '&suggestterms=' + jQuery(this).data('suggestterms') + '&nonce=' + encodeURIComponent(st_admin_localize.check_nonce), {
      content: getContentFromEditor(),
      title: getTitleFromEditor()
    }, function () {
      registerClickTags();
    });
    return false;
  });

  jQuery('select.term_suggestion_select').click(function (e) {
    e.stopPropagation();
    var suggested_tags_div = $(this).closest('.postbox');
    suggested_tags_div.removeClass('close');
  });

  jQuery('button.term_suggestion_refresh').click(function (e) {
    e.stopPropagation();
    e.preventDefault();
    var suggested_tags_div = $(this).closest('.postbox');
    suggested_tags_div.removeClass('close');
    suggested_tags_div.find('select.term_suggestion_select').trigger('change');
  });
  
  jQuery('select.term_suggestion_select').change(function () {
    var suggested_tags_div = $(this).closest('.postbox');
    suggested_tags_div.removeClass('close');
    var suggestterms = Number(suggested_tags_div.find('select.term_suggestion_select').attr('data-suggestterms'));
    var selected_option = suggested_tags_div.find('select.term_suggestion_select :selected');
    var data_action = selected_option.val();
    var taxonomy = selected_option.attr('data-taxonomy');
    var current_post_id = jQuery('#post_ID').val();

    if (data_action == '') {
      suggested_tags_div.find('.container_clicktags').html(stHelperSuggestedTagsL10n.content_bloc);
      return;
    }
    
    suggested_tags_div.find('.st_ajax_loading').show();

    suggested_tags_div.find('.container_clicktags').load(ajaxurl + '?action=simpletags&stags_action=' + data_action + '&suggestterms=' + suggestterms + '&taxonomy=' + encodeURIComponent(taxonomy) + '&nonce=' + encodeURIComponent(st_admin_localize.check_nonce), {
      content: getContentFromEditor(),
      post_id: current_post_id,
      title: getTitleFromEditor()
    }, function () {
      registerClickTags();
    });
    return false;
  });


  //set local tags as default
  if (jQuery('.term_suggestion_select').length > 0) {
    setTimeout(taxopress_set_default_suggested_term, 2000);
  }
  function taxopress_set_default_suggested_term() {
    jQuery('.term_suggestion_select').each(function () {
      $(this).val($(this).closest('.postbox').find(".term_suggestion_select option:eq(1)").val()).trigger('change');
    });
      
  }

});

function getTitleFromEditor() {
  var data = '';

  try {
    data = wp.data.select('core/editor').getEditedPostAttribute('title');
  } catch (error) {
    data = jQuery('#title').val();
  }

  //fix elementor issue
  if (typeof data == "undefined") {
    data = jQuery('#title').val();
  }

  // Trim data
  data = data.replace(/^\s+/, '').replace(/\s+$/, '');
  if (data !== '') {
    data = strip_tags(data);
  }

  return data;
}

function getContentFromEditor() {
  var data = '';

  try { // Gutenberg
    data = wp.data.select('core/editor').getEditedPostAttribute('content');
  } catch (error) {
    try { // TinyMCE
      var ed = tinyMCE.activeEditor;
      if ('mce_fullscreen' == ed.id) {
        tinyMCE.get('content').setContent(ed.getContent({
          format: 'raw'
        }), {
          format: 'raw'
        });
      }
      tinyMCE.get('content').save();
      data = jQuery('#content').val();
    } catch (error) {
      try { // Quick Tags
        data = jQuery('#content').val();
      } catch (error) {}
    }
  }

  // Trim data
  data = data.replace(/^\s+/, '').replace(/\s+$/, '');
  if (data !== '') {
    data = strip_tags(data);
  }

  return data;
}
function registerClickTags() {
  jQuery('.container_clicktags span').click(function (event) {
    event.preventDefault();

    var taxonomy = jQuery(this).attr('data-taxonomy');
    var term_id = jQuery(this).attr('data-term_id');
    if (term_id > 0) {
      addTag(this.textContent, taxonomy, term_id);
    } else {
      addTag(this.textContent);
    }

    jQuery(this).addClass('used_term');
    jQuery(this).attr('aria-pressed', 'true');
  });

  jQuery('#st_ajax_loading').hide();
  if (jQuery('#suggestedtags .inside').css('display') != 'block') {
    jQuery('#suggestedtags').toggleClass('closed');
  }
  jQuery('.st_ajax_loading').hide();
  jQuery("div[id*='suggestedtags-']").each(function () {
    if (jQuery(this).find('.inside').css('display') != 'block') {
      jQuery(this).toggleClass('closed');
    }
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

/**
 * The strip_tags() php function on JS :)
 *
 * See : https://github.com/hirak/phpjs
 *
 * @param str
 * @returns {*}
 */
function strip_tags(str) {
  return str.replace(/&lt;\/?[^&gt;]+&gt;/gi, '');
}
