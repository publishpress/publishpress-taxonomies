function addTag(tag, custom_taxonomy = false, term_id = false) {
  // Trim tag
  tag = tag.replace(/^\s+/, '').replace(/\s+$/, '');

  //set taxonomy
  if (custom_taxonomy) {
      custom_taxonomy = custom_taxonomy;
  } else {
      custom_taxonomy = 'post_tag';
  }

  if (document.getElementById('adv-tags-input')) { // Tags input from PublishPress Taxonomies

    var tag_entry = document.getElementById('adv-tags-input');
    if (tag_entry.value.length > 0 && !tag_entry.value.match(/,\s*$/)) {
      tag_entry.value += ', ';
    }

    var re = new RegExp(tag + ',');
    if (!tag_entry.value.match(re)) {
      tag_entry.value += tag + ', ';
    }

  } else if (document.getElementById('new-tag-'+custom_taxonomy)) { // Default tags input from WordPress

    tag.replace(/\s+,+\s*/g, ',').replace(/,+/g, ',').replace(/,+\s+,+/g, ',').replace(/,+\s*$/g, '').replace(/^\s*,+/g, '');
    if (jQuery('#new-tag-'+custom_taxonomy).val() === '') {
      jQuery('#new-tag-'+custom_taxonomy).val(tag);
      jQuery('#new-tag-'+custom_taxonomy).closest('div').find('input[type="button"').trigger('click');
    } else {
      var current_tags = jQuery('#new-tag-' + custom_taxonomy).val();
      var current_tags_array = current_tags.split(',');
      if (!current_tags_array.includes(tag) && !current_tags_array.includes(' ' + tag)) {
        jQuery('#new-tag-' + custom_taxonomy).val(jQuery('#new-tag-' + custom_taxonomy).val() + ', ' + tag);
        jQuery('#new-tag-'+custom_taxonomy).closest('div').find('input[type="button"').trigger('click');
      }
    }
    //jQuery('.tagadd').WithSelect()

  } else if (term_id && document.getElementById('' + custom_taxonomy + '-' + term_id)) { // Maybe is hierarchical taxonomy type
    jQuery('#' + custom_taxonomy + '-' + term_id).find('input:first').attr('checked', true);
  } else if (typeof wp.data != 'undefined' && typeof wp.data.select('core') != 'undefined' && typeof wp.data.select('core/edit-post') != 'undefined' && typeof wp.data.select('core/editor') != 'undefined') { // Gutenberg
    
    // Get current taxonomy
    var tags_taxonomy = wp.data.select('core').getTaxonomy(''+custom_taxonomy+'');
    var tag_rest_base = tags_taxonomy && tags_taxonomy.rest_base;
    var hierarchical = tags_taxonomy && tags_taxonomy.hierarchical ? tags_taxonomy.hierarchical : false;
    var tags = tag_rest_base && wp.data.select('core/editor').getEditedPostAttribute(tag_rest_base);
    
    //console.log(tags);

    //clean tag of & to enable sending in ajax parameter
    tag = tag.replace('&amp;', 'simpletagand');

    var newTags = JSON.parse(JSON.stringify(tags));


    if (hierarchical === true) {

        newTags.push(Number(term_id));
        newTags = newTags.filter(onlyUnique);

        var new_tag = {};
        new_tag[tag_rest_base] = newTags;
        wp.data.dispatch('core/editor').editPost(new_tag);
      
        // open the tags panel
        if (wp.data.select('core/edit-post').isEditorPanelOpened('taxonomy-panel-'+custom_taxonomy) === false) {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
        } else {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
        }

      return;
    }
    
    jQuery.ajax({
      url: ajaxurl + '?' + jQuery.param({
        action: 'simpletags',
        stags_action: 'maybe_create_tag',
        tag: tag,
        nonce: stHelperAddTagsL10n.nonce
      }),
      cache: false,
      //async: false,
      dataType: 'json'
    }).done(function (result) {
      if (result.success && result.data.term_id > 0) {
        newTags.push(result.data.term_id);
        newTags = newTags.filter(onlyUnique);

        var new_tag = {};
        new_tag[tag_rest_base] = newTags;

        wp.data.dispatch('core/editor').editPost(new_tag);

        // open the tags panel
        if (wp.data.select('core/edit-post').isEditorPanelOpened('taxonomy-panel-'+custom_taxonomy) === false) {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
        } else {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-'+custom_taxonomy);
        }

        // See : https://riad.blog/2018/06/07/efficient-client-data-management-for-wordpress-plugins/
      }
    }).fail(function () {
      console.log('error when trying to create tag');
    });

  } else {

    console.log('no tags input found...');

  }
}
function onlyUnique(value, index, self) {
  return self.indexOf(value) === index;
}
