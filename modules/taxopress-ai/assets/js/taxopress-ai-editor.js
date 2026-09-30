(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  $(document).ready(function () {
    var removed_tax = taxoPressAIRequestAction.removed_tax;
    var current_screen = taxoPressAIRequestAction.current_screen;
    var renderedFiltersEnabled = null;

    function showResponseMessage(previewWrapper, content, messageClass, allowHtml) {
      var messageWrapper = previewWrapper.find('.taxopress-ai-fetch-result-msg');
      var message = $('<p>');

      if (allowHtml) {
        message.html(content || '');
      } else {
        message.text(content || '');
      }

      messageWrapper.empty().append(message);
      messageWrapper.removeClass('updated error').addClass(messageClass);
    }

    // Remove built in tax for block editor
    if (removed_tax.length > 0 && typeof wp.data !== 'undefined' && typeof wp.data.dispatch === 'function') {
      var editPostDispatch = wp.data.dispatch('core/edit-post');
      if (editPostDispatch && typeof editPostDispatch.removeEditorPanel === 'function') {
        for (let i = 0; i < removed_tax.length; i++) {
          editPostDispatch.removeEditorPanel('taxonomy-panel-' + removed_tax[i]);
        }
      }
    }

    if ($('#taxopress-ai-suggestedtags')) {
      //$('#taxopress-ai-suggestedtags').find('.handle-actions').prepend(html_entity_decode(taxoPressAIRequestAction.apiEditLink));
      if (typeof wp.data !== 'undefined' && typeof wp.data.select('core') !== 'undefined' && typeof wp.data.select('core/edit-post') !== 'undefined' && typeof wp.data.select('core/editor') !== 'undefined') {
        $('body').addClass('taxopress-block-editor');
      }
    }

    /**
     * Fetch post terms
     */
    $(document).on("click", ".taxopress-ai-fetch-button", function (event) {
      event.preventDefault();

      var button = $(this);
      var preview_wrapper = $(button).closest('.taxopress-ai-tab-content');
      var preview_ai = preview_wrapper.attr('data-ai-source');
      var preview_taxonomy = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').val();
      var taxonomy_rest = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').data("rest_base");
      var post_content = getContentFromEditor();
      var post_title = getTitleFromEditor();
      var preview_post = preview_wrapper.attr('data-post_id');
      var current_tags = (typeof wp.data !== 'undefined' && typeof wp.data.select('core/editor') !== 'undefined') ? wp.data.select('core/editor').getEditedPostAttribute(taxonomy_rest) : [];
      preview_wrapper.find('.taxopress-ai-fetch-result').html('');
      preview_wrapper.find('.taxopress-ai-fetch-result-msg').html('').removeClass('updated error');

      button.prop('disabled', true);
      preview_wrapper.find('.spinner').addClass('is-active');

      var search_text = preview_wrapper.find('.taxopress-taxonomy-search').val();
      var existing_terms_order = preview_wrapper.find('#existing_terms_order :selected').val();
      var existing_terms_orderby = preview_wrapper.find('#existing_terms_orderby :selected').val();
      var existing_terms_maximum_terms = preview_wrapper.find('#existing_terms_maximum_terms').val();
      var selected_autoterms = preview_wrapper.find('.taxopress-autoterms-options').val();

      //prepare ajax data
      var data = {
        action: "taxopress_ai_preview_feature",
        preview_ai: preview_ai,
        preview_taxonomy: preview_taxonomy,
        search_text: search_text,
        existing_terms_order: existing_terms_order,
        existing_terms_orderby: existing_terms_orderby,
        existing_terms_maximum_terms: existing_terms_maximum_terms,
        selected_autoterms: selected_autoterms,
        preview_post: preview_post,
        post_content: post_content,
        post_title: post_title,
        current_tags: current_tags,
        screen_source: current_screen,
        nonce: taxoPressAIRequestAction.nonce,
        preview_role: $('.preview-user-role-select').val(),
        post_type: $('.preview-post-types-select').val()
      };

      $.post(ajaxurl, data, function (response) {
        if (response.status === 'error') {
          showResponseMessage(preview_wrapper, response.content, 'error');
        } else {
          preview_wrapper.find('.taxopress-ai-fetch-result').html(response.content);
          autoterm_option_select2();
        }

        button.prop('disabled', false);
        preview_wrapper.find('.spinner').removeClass('is-active');
      });

    });

    /**
     * Create post terms
     */
    $(document).on("click", ".taxopress-ai-create-button", function (event) {
      event.preventDefault();

      var button = $(this);
      var preview_wrapper = $(button).closest('.taxopress-ai-tab-content');
      var taxonomy = preview_wrapper.find('.taxopress-ai-fetch-create-taxonomy :selected').val();

      var term_name = preview_wrapper.find('.taxopress-taxonomy-term-input').val();
      if (!term_name || term_name == '') {
        return;
      }
      
      var preview_post = preview_wrapper.attr('data-post_id');
      preview_wrapper.find('.taxopress-ai-fetch-result-msg').html('').removeClass('updated error');

      button.prop('disabled', true);
      preview_wrapper.find('.spinner').addClass('is-active');

      // get existing same taxonomy term on the page
      var existing_terms = [];
      var selected_terms = [];

      preview_wrapper.find('.result-terms .term-name').each(function () {
        var term = jQuery(this);
        if (term.attr('data-taxonomy') == taxonomy) {
          existing_terms.push(term.text().trim());
          if (term.closest('.result-terms').hasClass('used_term')) {
            selected_terms.push(term.attr('data-term_id'));
          }
        }
      });

      //prepare ajax data
      var data = {
        action: "taxopress_ai_add_new_term",
        taxonomy: taxonomy,
        term_name: term_name,
        post_id: preview_post,
        existing_terms: existing_terms,
        selected_terms: selected_terms,
        screen_source: current_screen,
        nonce: taxoPressAIRequestAction.nonce,
        preview_role: $('.preview-user-role-select').val(),
        post_type: $('.preview-post-types-select').val()
      };

      $.post(ajaxurl, data, function (response) {
        if (response.status === 'error') {
          showResponseMessage(preview_wrapper, response.content, 'error');
        } else {
          preview_wrapper.find('.taxopress-taxonomy-term-input').val('');
          preview_wrapper.find('.taxopress-ai-fetch-result').html(response.term_html);
          preview_wrapper.find('.result-terms .term-name[data-term_id="' + response.current_term + '"]').trigger('click');
        }

        button.prop('disabled', false);
        preview_wrapper.find('.spinner').removeClass('is-active');
      });

    });

    function isFiltersEnabled() {
      if (renderedFiltersEnabled !== null) {
        return renderedFiltersEnabled;
      }

      if (typeof taxoPressAIRequestAction === 'undefined') return false;
      const v = taxoPressAIRequestAction.metabox_filters_enabled;
      return v === true || v === 1 || v === '1' || v === 'true' || v === 'yes' || v === 'on';
    }

    function resolveTabKey($li) {
      const dataKey = $li.attr('data-content');
      if (dataKey) return dataKey;
      // Fallback to class-based mapping
      if ($li.hasClass('existing_terms_tab')) return 'existing_terms';
      if ($li.hasClass('post_terms_tab')) return 'post_terms';
      if ($li.hasClass('suggest_local_terms_tab')) return 'suggest_local_terms';
      if ($li.hasClass('create_terms_tab')) return 'create_terms';
      return '';
    }

    function setTabUi(tabKey) {
      const $all = $('.taxopress-tab-content-item');
      const $wrap = $('.taxopress-tab-content-item.' + tabKey);
      const filtersEnabled = isFiltersEnabled();

      $all.find('.existing-term-item').hide();
      $all.find('.create-term-item').hide();
      $all.find('.taxopress-ai-fetch-taxonomy-select').hide();
      //$all.find('.taxopress-ai-fetch-button').hide();
      $all.find('.taxopress-autoterms-options').hide();

      // Pick the active container by tab key
      if (tabKey === 'existing_terms') {
        if (filtersEnabled) {
          $wrap.find('.existing-term-item').show();
          $wrap.find('.taxopress-ai-fetch-taxonomy-select').show();
          //$wrap.find('.taxopress-ai-fetch-button').show();
        }
      } else if (tabKey === 'create_terms') {
        $wrap.find('.create-term-item').show();
        $wrap.find('.taxopress-ai-fetch-button').hide();
      } else {
        // post_terms or suggest_local_terms
        $wrap.find('.taxopress-ai-fetch-taxonomy-select').show();
        //$wrap.find('.taxopress-ai-fetch-button').show();
        if (tabKey === 'suggest_local_terms') {
          $wrap.find('.taxopress-autoterms-options').show();
        }
      }
    }

    function fetchPreviewTab(tabKey) {
      if (
        current_screen !== 'st_taxopress_ai' ||
        !['existing_terms', 'post_terms', 'suggest_local_terms'].includes(tabKey)
      ) {
        return;
      }

      var $button = $('.taxopress-preview-metabox-content .taxopress-ai-tab-content.' + tabKey)
        .find('.taxopress-ai-fetch-button');
      if ($button.length) {
        $button.trigger('click');
      }
    }

    // -------------------------------------------------------------
    //   Show/hide search box for eligible tab
    // -------------------------------------------------------------
    // In taxopress-ai-editor.js, update the tab click handler:
    $(document).on('click', 'ul.taxopress-tab.ai-integration-tab li', function () {
        const tabKey = resolveTabKey($(this));
        setTabUi(tabKey);
        fetchPreviewTab(tabKey);
        
        // Get current post ID and update all tabs
        const selectedPost = $('.preview-post-select').val();
        if (selectedPost) {
            $('.taxopress-ai-tab-content').each(function() {
                $(this).attr('data-post_id', selectedPost);
            });
        }
    });
    
    // Initialize on load for the active tab
    (function initTabUiOnLoad() {
      const $active = $('ul.taxopress-tab.ai-integration-tab li.active');
      const tabKey = resolveTabKey($active);
      if (tabKey) {
        setTabUi(tabKey);
        
        // Auto-trigger logic for existing_terms tab in dropdown mode
        if ($('.taxopress-post-suggestterm').hasClass('editor-screen')) {
          if ($active.hasClass('existing_terms_tab')) {
            if (typeof taxoPressAIRequestAction !== 'undefined' && 
                taxoPressAIRequestAction.current_screen !== 'st_taxopress_ai' &&
                taxoPressAIRequestAction.metabox_display_option === 'dropdown') {

              requestAnimationFrame(function() {
                setTimeout(function() {
                  var $existingTermsButton = $('.existing_terms .taxopress-ai-fetch-button');
                  if ($existingTermsButton.length > 0) {
                    $existingTermsButton.trigger('click');
                  }
                }, 300);
              });
            }
          }
        }
      }
    })(); 

    // -------------------------------------------------------------
    //  Select/de-select all tags tags
    // -------------------------------------------------------------
    $(document).on('click', '.previewed-tag-fieldset .ai-select-all', function () {
      var button = $(this);

      if (button.hasClass('all-selected')) {
        button.removeClass('all-selected');
        button.text(button.attr('data-select-all'));
        button.closest('.previewed-tag-fieldset').find('.result-terms').addClass('used_term').trigger('click');
      } else {
        button.addClass('all-selected');
        button.text(button.attr('data-deselect-all'));
        button.closest('.previewed-tag-fieldset').find('.result-terms').removeClass('used_term').trigger('click');
      }
    });

    // -------------------------------------------------------------
    //  Select or de-select term (click tags)
    // -------------------------------------------------------------
    $(document).on('click', '.taxopress-ai-fetch-result .result-terms:not(.disabled)', function () {

      var term_button = $(this);

      if (current_screen == 'st_taxopress_ai') {
        term_button.toggleClass('used_term');
        return;
      }

      var preview_wrapper = term_button.closest('.taxopress-ai-tab-content');
      var taxonomy = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').val();
      var taxonomy_rest = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').data("rest_base");
      var taxonomy_hierarchical = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').data("hierarchical");
      var preview_post = preview_wrapper.find('.preview-post-select').val();
      
      var this_result = term_button.find('.term-name');
      var this_selected = term_button.hasClass('used_term');
      var this_term_id = Number(this_result.attr('data-term_id'));
      var this_term_name = this_result.text();

      term_button.addClass('disabled');

      if (typeof wp.data !== 'undefined' && typeof wp.data.select('core') !== 'undefined' && typeof wp.data.select('core/edit-post') !== 'undefined' && typeof wp.data.select('core/editor') !== 'undefined') {
        if (!this_selected && this_term_id > 0) {
          selectGutenbergTerms([this_term_id], taxonomy_rest, taxonomy);
          term_button.toggleClass('used_term');
          term_button.removeClass('disabled');
        } else if (!this_selected && this_term_id === 0) {
          addNewGutenbergTerm(this_term_name, taxonomy)
            .then(term_id => {
              this_result.attr('data-term_id', term_id);
              selectGutenbergTerms([term_id], taxonomy_rest, taxonomy);
              term_button.toggleClass('used_term');
              term_button.removeClass('disabled');
            })
            .catch(error => {
              term_button.removeClass('disabled');
            });
        } else if (this_selected) {
          removeGutenbergTerms([this_term_id], taxonomy_rest, taxonomy);
          term_button.toggleClass('used_term');
          term_button.removeClass('disabled');
        }
      } else {

        if (taxonomy_hierarchical > 0) {
          if (this_selected) {
            $('#' + taxonomy + '-' + this_term_id).find('input:first').attr('checked', false);
            term_button.toggleClass('used_term');
            term_button.removeClass('disabled');
          } else if (!this_selected) {
            if (this_term_id > 0 && document.getElementById('' + taxonomy + '-' + this_term_id)) {
              $('#' + taxonomy + '-' + this_term_id).find('input:first').attr('checked', true);
              term_button.toggleClass('used_term');
              term_button.removeClass('disabled');
            } else {
              addNewClassicCategory(this_term_name, taxonomy, function (term_data) {
                if (term_data) {
                  this_result.attr('data-term_id', term_data.term_id);
                  var checklistSelector = '#' + taxonomy + 'checklist';
                  var categoryList = jQuery(checklistSelector);
                  var taxonomy_field_name = '';

                  if (taxonomy == 'category') {
                    taxonomy_field_name = 'post_category';
                  } else {
                    taxonomy_field_name = 'tax_input[' + taxonomy + ']';
                  }

                  var categoryItem = $('<li>').attr('id', taxonomy + '-' + term_data.term_id);
                  var categoryLabel = $('<label>').addClass('selectit');
                  var categoryInput = $('<input>', {
                    value: term_data.term_id,
                    type: 'checkbox',
                    name: taxonomy_field_name + '[]',
                    id: 'in-' + taxonomy + '-' + term_data.term_id
                  }).prop('checked', true);
                  categoryLabel.append(categoryInput).append(document.createTextNode(' ' + term_data.name));
                  categoryList.prepend(categoryItem.append(categoryLabel));

                  this_result.attr('data-term_id', term_data.term_id);
                  term_button.toggleClass('used_term');
                  term_button.removeClass('disabled');
                } else {
                  term_button.removeClass('disabled');
                }
              });
            }
          }

        } else {
          this_term_name = this_term_name.replace(/\s+,+\s*/g, ',').replace(/,+/g, ',').replace(/,+\s+,+/g, ',').replace(/,+\s*$/g, '').replace(/^\s*,+/g, '');

          if (this_selected) {
            jQuery('#tagsdiv-' + taxonomy + ' .tagchecklist').find('li').each(function () {
              var listItem = jQuery(this);
              var listItemText = listItem.contents().filter(function () {
                return this.nodeType === 3; // Filter out non-text nodes
              }).text().trim();

              if (listItemText == this_term_name) {
                var removeButton = listItem.find('.ntdelbutton');
                removeButton.trigger('click');
                term_button.toggleClass('used_term');
                term_button.removeClass('disabled');
              }
            });
          } else {
            var $tagInput = jQuery('#new-tag-' + taxonomy);
            if (!$tagInput.length) {
              term_button.toggleClass('used_term');
              term_button.removeClass('disabled');
              return;
            }

            var current_tags = $tagInput.val();
            if (typeof current_tags !== 'string') {
              current_tags = '';
            }

            if (current_tags === '') {
              $tagInput.val(this_term_name);
              var currentFocus = document.activeElement;
              $tagInput.closest('div').find('input[type="button"]').click();
              jQuery(currentFocus).focus();
            } else {
              var current_tags_array = current_tags.length ? current_tags.split(',') : [];
              if (!current_tags_array.includes(this_term_name) && !current_tags_array.includes(' ' + this_term_name)) {
                $tagInput.val(current_tags + ', ' + this_term_name);
                var currentFocus = document.activeElement;
                $tagInput.closest('div').find('input[type="button"]').click();
                jQuery(currentFocus).focus();
              }
            }
            term_button.toggleClass('used_term');
            term_button.removeClass('disabled');
          }
        }
      }

    });

    /**
     * Reset preview on taxonomy change
     */
    $(document).on("change", ".taxopress-ai-tab-content .taxopress-ai-fetch-taxonomy-select", function (event) {
      var preview_wrapper = $(this).closest('.taxopress-ai-tab-content');
      preview_wrapper.find('.taxopress-ai-fetch-result').html('');
      preview_wrapper.find('.taxopress-ai-fetch-result-msg').html('').removeClass('updated error');
    });

    function selectGutenbergTerms(tags, taxonomy_rest_base, taxonomy) {
      const currentTags = wp.data.select('core/editor').getEditedPostAttribute(taxonomy_rest_base);
      var updatedTags = [...currentTags, ...tags];
      updatedTags = updatedTags.filter(onlyUnique);

      wp.data.dispatch('core/editor').editPost({
        [taxonomy_rest_base]: updatedTags,
      });

      // close and open taxonomy panel to refresh
      if (wp.data.select('core/edit-post').isEditorPanelOpened('taxonomy-panel-' + taxonomy)) {
        wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-' + taxonomy);
        setTimeout(function () {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-' + taxonomy);
        }, 100);
      }
    }



    /**
     * Add ai select 2 post search if the div is present o the page
     */
    if ($('.taxopress-ai-post-search').length > 0) {
      taxopressPostSelect2($('.taxopress-ai-post-search'));
      function taxopressPostSelect2(selector) {
        selector.each(function () {
            var postsSearch = $(this).ppma_select2({
                placeholder: $(this).data("placeholder"),
                allowClear: $(this).data("allow-clear"),
                ajax: {
                    url: window.ajaxurl + "?action=taxopress_ai_post_search&nonce=" + $(this).data("nonce"),
                    dataType: "json",
                    data: function (params) {
                        var aiSource = $(this).closest(".taxopress-tab-content-item").data("ai-source");
                        var postType = $('.preview-post-types-select').val();
                        return {
                            q: params.term,
                            ai_source: aiSource,
                            post_type: postType
                        };
                    }
                }
            });
        });
      }


      /**
       * Reset selected post if post type changes
       */
      $(document).on("change", ".preview-post-types-select", function (event) {
        var post_select = $(this).closest('.taxopress-preview-filters').find('.preview-post-select');
        post_select.val('').empty().ppma_select2('destroy');
        taxopressPostSelect2($('.taxopress-ai-post-search'));
      });

      /**
       * Update selected post when change
       */
    $(document).on("change", ".taxopress-ai-post-search", function (event) {
        var selected_post = $(this).val();
        
        // Update all tab contents with selected post
        $('.taxopress-ai-tab-content').each(function() {
            $(this).attr('data-post_id', selected_post);
        });
    });
      // -------------------------------------------------------------
      //  Update selected tags post
      // -------------------------------------------------------------
      $(document).on('click', '.taxopress-tab-content-item .taxopress-ai-addtag-button', function (e) {
        e.preventDefault();
        var button = $(this);
        var preview_wrapper = button.closest('.taxopress-tab-content-item');
        var this_result = '';
        var this_term_id = '';
        var this_term_name = '';
        var this_selected = '';
        var term_data = '';
        var preview_taxonomy = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').val();
        var preview_taxonomy_label = preview_wrapper.find('.taxopress-ai-fetch-taxonomy-select :selected').text().trim();
        var preview_post = preview_wrapper.find('.preview-post-select').val();
        var preview_post_type_label = preview_wrapper.find('.preview-post-types-select :selected').text().trim();
        var preview_ai = preview_wrapper.attr('data-ai-source');

        button.prop('disabled', true);
        button.find('.spinner').addClass('is-active');

        var added_tags = [];
        var removed_tags = [];

        preview_wrapper.find('.result-terms').each(function () {
          this_result = $(this).find('.term-name');
          this_selected = $(this).hasClass('used_term');
          this_term_id = Number(this_result.attr('data-term_id'));
          this_term_name = this_result.text();
          term_data = {
            'term_id': this_term_id,
            'name': this_term_name
          };

          if (this_selected) {
            added_tags.push(term_data);
          } else if (this_term_id > 0) {
            removed_tags.push(term_data);
          }
        });

        //prepare ajax data
        var data = {
          action: "taxopress_ai_add_post_term",
          taxonomy: preview_taxonomy,
          taxonomy_label: preview_taxonomy_label,
          post_id: preview_post,
          tab_post_id: preview_wrapper.attr('data-post_id'),
          post_type_label: preview_post_type_label,
          added_tags: added_tags,
          removed_tags: removed_tags,
          screen_source: current_screen,
          nonce: taxoPressAIRequestAction.nonce,
          preview_role: $('.preview-user-role-select').val(),
          post_type: $('.preview-post-types-select').val()
        };

        $.post(ajaxurl, data, function (response) {
          if (response.status === 'error') {
            showResponseMessage(preview_wrapper, response.content, 'error');
          } else {
            showResponseMessage(preview_wrapper, response.content, 'updated', true);
          }

          button.prop('disabled', false);
          button.find('.spinner').removeClass('is-active');
        });

      });
    }

    /**
     * Add ai select 2 if the div is present on the page
     */
    if ($('.taxopress-ai-select2').length > 0) {
      $('.taxopress-ai-select2').ppma_select2({
        placeholder: $(this).data("placeholder"),
        allowClear: $(this).data("allow-clear"),
      });
    }

    function removeGutenbergTerms(tagsToRemove, taxonomy_rest_base, taxonomy) {
      const currentTags = wp.data.select('core/editor').getEditedPostAttribute(taxonomy_rest_base);
      const updatedTags = currentTags.filter((tag) => !tagsToRemove.includes(tag));

      wp.data.dispatch('core/editor').editPost({
        [taxonomy_rest_base]: updatedTags,
      });

      if (updatedTags.length === 0) {
        // close and open taxonomy panel to refresh
        if (wp.data.select('core/edit-post').isEditorPanelOpened('taxonomy-panel-' + taxonomy)) {
          wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-' + taxonomy);
          setTimeout(function () {
            wp.data.dispatch('core/edit-post').toggleEditorPanelOpened('taxonomy-panel-' + taxonomy);
          }, 10);
        }
      }
    }

    function addNewGutenbergTerm(term_name, taxonomy) {
      return new Promise((resolve, reject) => {
        const termData = {
          name: term_name,
        };

        wp.data.dispatch('core').saveEntityRecord('taxonomy', taxonomy, termData, {
          isAutosave: false,
          throwOnError: true,
        })
          .then(newCategory => {
            if (typeof newCategory === 'object' && newCategory.hasOwnProperty('id')) {
              resolve(newCategory.id);
            } else {
              reject(newCategory);
            }
          })
          .catch(error => {
            if (typeof error === 'object' && error.hasOwnProperty('data')) {
              resolve(error.data.term_id);
            } else {
              reject(error);
            }
          });
      });
    }

    function addNewClassicCategory(term_name, taxonomy, callback) {
      var data = {
        action: "taxopress_ai_add_new_term",
        taxonomy: taxonomy,
        term_name: term_name,
        nonce: taxoPressAIRequestAction.nonce,
      };

      $.post(ajaxurl, data, function (response) {
        if (response.status === 'error') {
          callback(null);
        } else {
          callback(response.term);
        }
      });
    }


    function getTitleFromEditor() {
      var data = '';

      if (current_screen == 'st_taxopress_ai') {
        return data;
      }

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

      if (current_screen == 'st_taxopress_ai') {
        return data;
      }

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
          } catch (error) { }
        }
      }

      // Trim data
      data = data.replace(/^\s+/, '').replace(/\s+$/, '');
      if (data !== '') {
        data = strip_tags(data);
      }

      return data;
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


    function onlyUnique(value, index, self) {
      return self.indexOf(value) === index;
    }

    function autoterm_option_select2() {
      $('.auto_term_terms_options.select').ppma_select2({
        placeholder: $(this).data("placeholder"),
      });
    }

    // Generic inline rename for all tab labels
    $(document).on('click', '.tp-rename-tab', function (e) {
    e.preventDefault();
    const $tab = $(this).closest('li');
    // reset any previous error
    $tab.find('.tp-rename-tab-error').hide().text('');
    $tab.find('.tp-tab-label').hide();
    $tab.find('.tp-rename-tab').hide();
    $tab.find('.tp-rename-inline-controls').show();
    $tab.find('.tp-rename-tab-input').focus().select();
    });

    $(document).on('click', '.tp-rename-tab-save', function (e) {
    e.preventDefault();
    const $tab = $(this).closest('li');
    const $input = $tab.find('.tp-rename-tab-input');
    const $error = $tab.find('.tp-rename-tab-error');
    const $renameButton = $tab.find('.tp-rename-tab');
    const tabType = $renameButton.data('tab');
    const newLabel = ($input.val() || '').trim();

    if (!newLabel) {
        $error.text(taxoPressAIRequestAction.label_empty_error).show();
        $input.focus();
        return;
    }
    if (newLabel.length > 30) {
        $error.text(taxoPressAIRequestAction.label_too_long_error).show();
        $input.focus();
        return;
    }

    // clear old error
    $error.hide().text('');

    const actionMap = {
        'existing_terms': 'taxopress_ai_save_existing_terms_label',
        'post_terms': 'taxopress_ai_save_post_terms_label',
        'suggest_local_terms': 'taxopress_ai_save_suggest_local_terms_label',
        'create_terms': 'taxopress_ai_save_create_terms_label'
    };

    const action = actionMap[tabType];
    if (!action) {
        $error.text(taxoPressAIRequestAction.unknown_tab_error).show();
        return;
    }

    $.post(ajaxurl, {
        action: action,
        nonce: taxoPressAIRequestAction.nonce,
        new_label: newLabel
    }).done(function (resp) {
        if (resp && resp.success && resp.data && resp.data.label) {
        $tab.find('.tp-tab-label').text(resp.data.label);
        $tab.find('.tp-tab-label').show();
        $tab.find('.tp-rename-tab').show();
        $tab.find('.tp-rename-inline-controls').hide();
        $error.hide().text('');
        } else {
        const msg = (resp && resp.data && resp.data.message) ? resp.data.message : taxoPressAIRequestAction.save_error;
        $error.text(msg).show();
        }
    }).fail(function (xhr) {
        let msg = taxoPressAIRequestAction.save_error;
        if (xhr && xhr.responseJSON && (xhr.responseJSON.data?.message || xhr.responseJSON.message)) {
        msg = xhr.responseJSON.data?.message || xhr.responseJSON.message;
        }
        $error.text(msg).show();
        // keep edit mode open on failure
    });
    });

    // Save on Enter, cancel on Escape
    $(document).on('keydown', '.tp-rename-tab-input', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        $(this).closest('li').find('.tp-rename-tab-save').trigger('click');
    } else if (e.key === 'Escape') {
        const $tab = $(this).closest('li');
        $tab.find('.tp-rename-tab-error').hide().text('');
        $tab.find('.tp-tab-label').show();
        $tab.find('.tp-rename-tab').show();
        $tab.find('.tp-rename-inline-controls').hide();
    }
    });


    // Update the preview button click handler
    $(document).on('click', '.preview-metabox-content', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $('<div class="spinner"></div>');
        
        var selectedPostType = $('.preview-post-types-select').val();
        var selectedRole = $('.preview-user-role-select').val();
        var selectedPost = $('.preview-post-select').val();
        var activeTabClass = $('ul.taxopress-tab.ai-integration-tab li.active').attr('class') || '';
        var activeTabMatch = activeTabClass.match(/(existing_terms|post_terms|suggest_local_terms|create_terms)_tab/);
        var activeTab = activeTabMatch ? activeTabMatch[0] : '';

        if (!selectedPost) {
            alert(taxoPressAIRequestAction.requiredSuffix);
            return;
        }

        // Add spinner and disable button
        $button.prop('disabled', true);
        $button.append($spinner.css('visibility', 'visible'));

        $.post(ajaxurl, {
            action: 'taxopress_preview_update',
            post_id: selectedPost,
            preview_role: selectedRole,
            post_type: selectedPostType,
            nonce: taxoPressAIRequestAction.nonce
        }, function(previewResp) {
                    // Replace the metabox content regardless; server decides what to show/hide
                    if (previewResp && previewResp.success && previewResp.data && previewResp.data.metabox_content) {
                        renderedFiltersEnabled = previewResp.data.metabox_filters_enabled === true;

                        // Replace the editor metabox area in the preview pane
                        $('.taxopress-preview-metabox-content').html(previewResp.data.metabox_content);

                        // Re-init any autoterm select2 options
                        if (typeof autoterm_option_select2 === 'function') {
                            autoterm_option_select2();
                        }

                        var refreshedTab = activeTab || $('ul.taxopress-tab.ai-integration-tab li.active').attr('data-content');
                        if (refreshedTab) {
                            refreshedTab = refreshedTab.replace('_tab', '');
                            setTabUi(refreshedTab);
                            fetchPreviewTab(refreshedTab);
                        }
                    }
        }).always(function() {
            $spinner.remove();
            $button.prop('disabled', false);
        });
    });

    // Override the existing post search functionality to use selected post type
    if ($.fn.ppma_select2) {
        $('.preview-post-select').ppma_select2({
            placeholder: $(this).data("placeholder"),
            allowClear: $(this).data("allow-clear"),
            ajax: {
                url: window.ajaxurl + "?action=taxopress_ai_post_search&nonce=" + $('.preview-post-select').data("nonce"),
                dataType: 'json',
                data: function(params) {
                    return {
                        q: params.term,
                        ai_source: 'preview',
                        post_type: $('.preview-post-types-select').val()
                    };
                }
            }
        });
    }

    (function initUrlParams() {
        if (typeof taxoPressAIRequestAction === 'undefined' || taxoPressAIRequestAction.current_screen !== 'st_taxopress_ai') {
            return;
        }

        var params = new URLSearchParams(window.location.search);
        var isPreviewTab = params.get('tab') === 'preview' || params.get('active_tab') === 'preview';
        
        if (!isPreviewTab) {
            var subtabEl = document.querySelector('.taxopress-active-subtab');
            if (subtabEl && subtabEl.value === 'preview') {
                isPreviewTab = true;
            }
        }

        // Only perform state storing / URL cleaning for the Preview tab
        if (!isPreviewTab) {
            return;
        }
        
        // Store state
        if (params.has('post_type') || params.has('role') || params.has('post') || params.has('active_tab')) {
            window.taxopressAIState = {
                post_type: params.get('post_type'),
                role: params.get('role'),
                post: params.get('post'),
                active_tab: params.get('active_tab')
            };

            // Clean URL
            var cleanParams = new URLSearchParams(window.location.search);
            cleanParams.delete('post_type');
            cleanParams.delete('role');
            cleanParams.delete('post');
            cleanParams.delete('active_tab');
            
            var newUrl = window.location.pathname;
            if (cleanParams.toString()) {
                newUrl += '?' + cleanParams.toString();
            }
            window.history.replaceState({}, '', newUrl);
        }

        // Apply stored state
        if (window.taxopressAIState) {
            if (window.taxopressAIState.post_type) {
                $('.preview-post-types-select').val(window.taxopressAIState.post_type).trigger('change');
            }
            
            if (window.taxopressAIState.role) {
                $('.preview-user-role-select').val(window.taxopressAIState.role).trigger('change');
            }
            
            if (window.taxopressAIState.post) {
                $('.taxopress-ai-tab-content').each(function() {
                    $(this).attr('data-post_id', window.taxopressAIState.post);
                });
                
                setTimeout(function() {
                    var $postSelect = $('.preview-post-select');
                    var currentOption = $postSelect.find('option:selected');
                    if (currentOption.length && currentOption.val() === window.taxopressAIState.post) {
                        $postSelect.trigger('change');
                    }

                    if (window.taxopressAIState.active_tab) {
                        $('ul.taxopress-tab.ai-integration-tab li.' + window.taxopressAIState.active_tab).click();
                    }
                }, 500);
            }
        }
    })();


    (function initRolePermissions() {
        if (typeof taxoPressAIRequestAction === 'undefined' || 
            taxoPressAIRequestAction.current_screen !== 'st_taxopress_ai') {
            return;
        }

        var $roleSelect = $('.preview-user-role-select');
        if ($roleSelect.length) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'taxopress_role_preview',
                    preview_role: $roleSelect.val(),
                    nonce: taxoPressAIRequestAction.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (!response.data.can_create_terms) {
                            $('.create_terms_tab').hide();
                        } else {
                            $('.create_terms_tab').show();
                        }

                        if (!response.data.can_edit_labels) {
                            $('.tp-rename-tab').hide();
                        } else {
                            $('.tp-rename-tab').show();
                        }

                        $('.taxopress-ai-fetch-taxonomy-select option, .taxopress-ai-fetch-create-taxonomy option').hide();
                        
                        response.data.allowed_taxonomies.forEach(function(tax) {
                            $('.taxopress-ai-fetch-taxonomy-select option[value="' + tax + '"], .taxopress-ai-fetch-create-taxonomy option[value="' + tax + '"]').show();
                        });

                        var $taxonomySelect = $('.taxopress-ai-fetch-taxonomy-select');
                        var currentTaxonomy = $taxonomySelect.val();
                        if (response.data.allowed_taxonomies.indexOf(currentTaxonomy) === -1) {
                            $taxonomySelect.val(response.data.allowed_taxonomies[0]).trigger('change');
                        }
                    }
                }
            });
        }
    })();

    $(document).on('change', '.preview-user-role-select', function() {
        var selectedRole = $(this).val();
        var taxonomy = $('.taxopress-ai-fetch-taxonomy-select').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'taxopress_role_preview',
                preview_role: selectedRole,
                taxonomy: taxonomy,
                nonce: taxoPressAIRequestAction.nonce
            },
            success: function(response) {
                if (!response.success) return;

                var allowed = Array.isArray(response.data.allowed_taxonomies) ? response.data.allowed_taxonomies : [];

                var $taxonomySelect = $('.taxopress-ai-fetch-taxonomy-select');
                var $createTaxonomySelect = $('.taxopress-ai-fetch-create-taxonomy');

                // Pick a value to show immediately: keep current if allowed, otherwise first allowed (or empty)
                var currentValue = $taxonomySelect.val();
                var newValue = (allowed.length && allowed.indexOf(currentValue) === -1) ? allowed[0] : currentValue || (allowed[0] || '');

                // Set selects first so the visible label is never empty
                $taxonomySelect.val(newValue);
                $createTaxonomySelect.val(newValue);

                // Hide all taxonomy options first (both fetch and create selects)
                $('.taxopress-ai-fetch-taxonomy-select option, .taxopress-ai-fetch-create-taxonomy option').hide();

                if (allowed.length) {
                    // Show only permitted taxonomies
                    allowed.forEach(function(tax) {
                        $('.taxopress-ai-fetch-taxonomy-select option[value="' + tax + '"], .taxopress-ai-fetch-create-taxonomy option[value="' + tax + '"]').show();
                    });
                } else {
                    // No allowed taxonomies — clear selects
                    $taxonomySelect.val('');
                    $createTaxonomySelect.val('');
                }

                // Trigger normal change and select2-specific event to ensure UI updates
                $taxonomySelect.trigger('change');
                $taxonomySelect.trigger('change.select2');
                $createTaxonomySelect.trigger('change');
                $createTaxonomySelect.trigger('change.select2');

                // Update UI based on permissions
                if (!response.data.can_create_terms) {
                    $('.create_terms_tab').hide();
                } else {
                    $('.create_terms_tab').show();
                }

                if (!response.data.can_edit_labels) {
                    $('.tp-rename-tab').hide();
                } else {
                    $('.tp-rename-tab').show();
                }
            }
        });
    });

    $('.taxopress-ai-post-type-tab-nav a').on('click', function(e) {
        e.preventDefault();

        $('.taxopress-ai-post-type-tab-nav a').removeClass('active');
        $('.post-type-content').removeClass('active').hide();

        $(this).addClass('active');
        var targetContent = $(this).data('content');
        if (targetContent) {
            $('#' + targetContent).addClass('active').show();

            var activePost = targetContent.replace(/^taxopress-ai-/, '').replace(/-content$/, '');
            $('.taxopress-active-posttype').val(activePost);
        }
    });

    $('.metabox-role-tab-nav a').on('click', function(e) {
        e.preventDefault();

        $('.metabox-role-tab-nav a').removeClass('active');
        $('.role-content').removeClass('active').hide();

        $(this).addClass('active');
        var targetContent = $(this).data('content');
        if (targetContent) {
            $('#' + targetContent).addClass('active').show();

            var activeRole = targetContent.replace(/^metabox-/, '').replace(/-content$/, '');
            $('.taxopress-active-role').val(activeRole);
        }
    });

    $(document).on('submit', 'form', function() {
        var pt = $('.taxopress-ai-post-type-tab-nav a.active').data('content');
        if (pt) $('.taxopress-active-posttype').val(pt.replace(/^taxopress-ai-/, '').replace(/-content$/, ''));
        var rl = $('.metabox-role-tab-nav a.active').data('content');
        if (rl) $('.taxopress-active-role').val(rl.replace(/^metabox-/, '').replace(/-content$/, ''));
    });


        function initializeFieldDependencies() {
        // Idempotent initializer - determine visibility strictly from checkbox states
        // All sub-containers are kept, we control st-subhide-content based on inputs
        $('[name$="_metabox"]').each(function () {
            var field_name = $(this).attr('name');
            var match = field_name.match(/enable_taxopress_ai_(\w+)_metabox/);
            if (!match) return;

            var postType = match[1];
            var isMetaboxChecked = $(this).prop('checked');

            var $metaboxFieldContainer = $('.enable_taxopress_ai_' + postType + '_metabox_field');
            if (isMetaboxChecked) {
                $metaboxFieldContainer.removeClass('st-subhide-content');
            } else {
                $metaboxFieldContainer.addClass('st-subhide-content');
            }

            // For each feature tab checkbox (existing/post/suggest/create) show/hide its subcontainer
            $('[name^="enable_taxopress_ai_' + postType + '_"][name$="_tab"]').each(function() {
                var tabChecked = $(this).prop('checked');
                var tabClass = '.' + $(this).attr('name') + '_field';
                if (tabChecked && isMetaboxChecked) {
                    $(tabClass).removeClass('st-subhide-content');
                } else {
                    $(tabClass).addClass('st-subhide-content');
                }
            });

            // Filters container: show only when
            //  - metabox is enabled for this post type
            //  - existing_terms tab is enabled for this post type
            //  - the filters checkbox itself is checked
            var $filtersCheckbox = $('[name="taxopress_ai_' + postType + '_metabox_filters"]');
            var filtersChecked = $filtersCheckbox.length ? $filtersCheckbox.prop('checked') : false;
            var existingTabCheckbox = $('[name="enable_taxopress_ai_' + postType + '_existing_terms_tab"]');
            var existingTabChecked = existingTabCheckbox.length ? existingTabCheckbox.prop('checked') : false;
            var $filtersContainer = $('.enable_taxopress_ai_' + postType + '_metabox_filters_field');

            if (isMetaboxChecked && existingTabChecked && filtersChecked) {
                $filtersContainer.removeClass('st-subhide-content');
            } else {
                $filtersContainer.addClass('st-subhide-content');
            }
        });
     }

         $(document).on('change', '.taxopress-ai-tab-content input[type="checkbox"], .taxopress-ai-tab-content-sub input[type="checkbox"]', function (e) {
        var $checkbox = $(this);
        var field_name = $checkbox.attr('name');
        if (!field_name) return;

        // If a whole-metabox toggle changed, re-evaluate that post type
        var metaboxMatch = field_name.match(/^enable_taxopress_ai_(\w+)_metabox$/);
        var filtersMatch = field_name.match(/^taxopress_ai_(\w+)_metabox_filters$/);
        var tabMatch = field_name.match(/^enable_taxopress_ai_(\w+)_(existing_terms|post_terms|suggest_local_terms|create_terms)_tab$/);

        var postType = null;
        if (metaboxMatch) postType = metaboxMatch[1];
        else if (filtersMatch) postType = filtersMatch[1];
        else if (tabMatch) postType = tabMatch[1];

        if (!postType) {
            // fallback to re-run global initializer
            initializeFieldDependencies();
            return;
        }

        // Recompute visibility for this postType only (reuse init logic)
        (function recomputeFor(postType) {
            var isMetaboxChecked = $('[name="enable_taxopress_ai_' + postType + '_metabox"]').prop('checked');
            var $metaboxFieldContainer = $('.enable_taxopress_ai_' + postType + '_metabox_field');
            if (isMetaboxChecked) {
                $metaboxFieldContainer.removeClass('st-subhide-content');
            } else {
                $metaboxFieldContainer.addClass('st-subhide-content');
            }

            $('[name^="enable_taxopress_ai_' + postType + '_"][name$="_tab"]').each(function() {
                var tabChecked = $(this).prop('checked');
                var tabClass = '.' + $(this).attr('name') + '_field';
                if (tabChecked && isMetaboxChecked) {
                    $(tabClass).removeClass('st-subhide-content');
                } else {
                    $(tabClass).addClass('st-subhide-content');
                }
            });

            var filtersChecked = $('[name="taxopress_ai_' + postType + '_metabox_filters"]').prop('checked');
            var existingTabChecked = $('[name="enable_taxopress_ai_' + postType + '_existing_terms_tab"]').prop('checked');
            var $filtersContainer = $('.enable_taxopress_ai_' + postType + '_metabox_filters_field');
            if (isMetaboxChecked && existingTabChecked && filtersChecked) {
                $filtersContainer.removeClass('st-subhide-content');
            } else {
                $filtersContainer.addClass('st-subhide-content');
            }
        })(postType);
    });

    $(document).on('click', '.taxopress-ai-post-type-tab-nav a', function() {
        setTimeout(function() {
            initializeFieldDependencies();
        }, 100);
    });

    if ($('.taxopress-ai-tab-content').length > 0) {
        initializeFieldDependencies();
    }

  });
})(jQuery);
