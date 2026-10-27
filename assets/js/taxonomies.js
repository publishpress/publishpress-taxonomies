(function ($) {


  jQuery(document).ready(function ($) {


    // Taxonomy tab
    $('ul.st-taxonomy-tab li').on('click', function (e) {
      e.preventDefault()
      var tab_content = $(this).attr('data-content');

      $('.st-taxonomy-tab li').removeClass('active');
      $('.st-taxonomy-tab li').removeAttr('aria-current').children('a').removeAttr('aria-current');
      $(this).addClass('active');
      $(this).children('a').attr('aria-current', 'true');

      $('.st-taxonomy-content table').hide();
      $('.st-taxonomy-content table.' + tab_content).show();
      //set tab height
      if ($('.st-taxonomy-content').height() > $('.st-taxonomy-tab').height()) {
        $('.st-taxonomy-tab').css('height', $('.st-taxonomy-content').height());
      }
    })
    if ($('.st-taxonomy-content').length > 0) {
      //set tab height
      if ($('.st-taxonomy-content').height() > $('.st-taxonomy-tab').height()) {
        $('.st-taxonomy-tab').css('height', $('.st-taxonomy-content').height());
      }
    }


    if ($('.taxonomy_external_edit').length > 0) {
      $('table.taxonomy_permalinks').find("tr:first").hide()
    }

    if ('edit' === getParameterByName('action')) {
      // Store our original slug on page load for edit checking.
      var original_slug = $('#name').val()
    }

    //taxonomy var visibility toggle
    if ($('input[name="cpt_custom_tax[query_var]"]').length > 0) {
      if ($('input[name="cpt_custom_tax[query_var]"]').is(":checked")) {
        $('input[name="cpt_custom_tax[query_var_slug]"]').closest('tr').show();
      } else {
        $('input[name="cpt_custom_tax[query_var_slug]"]').closest('tr').hide();
      }
    }
    $('input[name="cpt_custom_tax[query_var]"]').on('change', function (e) {
      if ($(this).is(":checked")) {
        $('input[name="cpt_custom_tax[query_var_slug]"]').closest('tr').show();
      } else {
        $('input[name="cpt_custom_tax[query_var_slug]"]').closest('tr').hide();
      }
    })

    // Confirm our deletions
    $('.taxopress-delete-top, .taxopress-delete-bottom').on('click', function (e) {
      e.preventDefault()
      var msg = ''
      if (typeof taxopress_type_data !== 'undefined') {
        msg = taxopress_type_data.confirm
      } else if (typeof taxopress_tax_data !== 'undefined') {
        msg = taxopress_tax_data.confirm
      }
      var submit_delete_warning = $('<div class="taxopress-submit-delete-dialog">' + msg + '</div>').appendTo('#poststuff').dialog({
        'dialogClass': 'wp-dialog',
        'modal': true,
        'autoOpen': true,
        'buttons': {
          "OK": function () {
            var form = $(e.target).closest('form')
            $(e.target).unbind('click').click()
          },
          "Cancel": function () {
            $(this).dialog('close')
          }
        }
      })
    })

    // Switch spaces for underscores on our slug fields.
    $('#name').on('keyup', function (e) {
      $('#st-tags-slug-error-input').addClass('hidemessage')
      var value, original_value
      value = original_value = $(this).val()
      if (e.keyCode !== 9 && e.keyCode !== 37 && e.keyCode !== 38 && e.keyCode !== 39 && e.keyCode !== 40) {
        value = value.replace(/ /g, "_")
        value = value.toLowerCase()
        value = replaceDiacritics(value)
        value = transliterate(value)

        if (value) {
          if (!value.match(/^[a-z0-9_]+$/i)) {
            //value = replaceSpecialCharacters(value)
            $('#st-tags-slug-error-input').removeClass('hidemessage')
          }
        }
        if (value !== original_value) {
          $(this).prop('value', value)
        }
      }

      //Displays a message if slug changes.
      if (typeof original_slug !== 'undefined') {
        var $slugchanged = $('#slugchanged')
        if (value != original_slug) {
          $slugchanged.removeClass('hidemessage')
        } else {
          $slugchanged.addClass('hidemessage')
        }
      }

      var $slugexists = $('#slugexists')
      if (typeof taxopress_type_data != 'undefined') {
        if (taxopress_type_data.existing_post_types.hasOwnProperty(value) && value !== original_slug) {
          $slugexists.removeClass('hidemessage')
        } else {
          $slugexists.addClass('hidemessage')
        }
      }
      if (typeof taxopress_tax_data != 'undefined') {
        if (taxopress_tax_data.existing_taxonomies.hasOwnProperty(value) && value !== original_slug) {
          $slugexists.removeClass('hidemessage')
        } else {
          $slugexists.addClass('hidemessage')
        }
      }
    })

    // Replace diacritic characters with latin characters.
    function replaceDiacritics(s) {
      var diacritics = [
        /[\300-\306]/g, /[\340-\346]/g,  // A, a
        /[\310-\313]/g, /[\350-\353]/g,  // E, e
        /[\314-\317]/g, /[\354-\357]/g,  // I, i
        /[\322-\330]/g, /[\362-\370]/g,  // O, o
        /[\331-\334]/g, /[\371-\374]/g,  // U, u
        /[\321]/g, /[\361]/g, // N, n
        /[\307]/g, /[\347]/g  // C, c
      ]

      var chars = ['A', 'a', 'E', 'e', 'I', 'i', 'O', 'o', 'U', 'u', 'N', 'n', 'C', 'c']

      for (var i = 0; i < diacritics.length; i++) {
        s = s.replace(diacritics[i], chars[i])
      }

      return s
    }

    function replaceSpecialCharacters(s) {
      if ('cpt-ui_page_taxopress_manage_post_types' === window.pagenow) {
        s = s.replace(/[^a-z0-9\s-]/gi, '_')
      } else {
        s = s.replace(/[^a-z0-9\s]/gi, '_')
      }

      return s
    }

    function composePreviewContent(value) {
      if (!value) {
        return ''
      } else if (0 === value.indexOf('dashicons-')) {
        return $('<div class="dashicons-before"><br></div>').addClass(value)
      } else {
        return $('<img />').attr('src', value)
      }
    }

    function isEmptyOrSpaces(str) {
      return str === null || str.match(/^ *$/) !== null;
    }

    var cyrillic = {
      "Ё": "YO",
      "Й": "I",
      "Ц": "TS",
      "У": "U",
      "К": "K",
      "Е": "E",
      "Н": "N",
      "Г": "G",
      "Ш": "SH",
      "Щ": "SCH",
      "З": "Z",
      "Х": "H",
      "Ъ": "'",
      "ё": "yo",
      "й": "i",
      "ц": "ts",
      "у": "u",
      "к": "k",
      "е": "e",
      "н": "n",
      "г": "g",
      "ш": "sh",
      "щ": "sch",
      "з": "z",
      "х": "h",
      "ъ": "'",
      "Ф": "F",
      "Ы": "I",
      "В": "V",
      "А": "a",
      "П": "P",
      "Р": "R",
      "О": "O",
      "Л": "L",
      "Д": "D",
      "Ж": "ZH",
      "Э": "E",
      "ф": "f",
      "ы": "i",
      "в": "v",
      "а": "a",
      "п": "p",
      "р": "r",
      "о": "o",
      "л": "l",
      "д": "d",
      "ж": "zh",
      "э": "e",
      "Я": "Ya",
      "Ч": "CH",
      "С": "S",
      "М": "M",
      "И": "I",
      "Т": "T",
      "Ь": "'",
      "Б": "B",
      "Ю": "YU",
      "я": "ya",
      "ч": "ch",
      "с": "s",
      "м": "m",
      "и": "i",
      "т": "t",
      "ь": "'",
      "б": "b",
      "ю": "yu"
    }

    function transliterate(word) {
      return word.split('').map(function (char) {
        return cyrillic[char] || char
      }).join("")
    }

    if (undefined != wp.media) {
      var _custom_media = true,
        _orig_send_attachment = wp.media.editor.send.attachment
    }

    function getParameterByName(name, url) {
      if (!url) url = window.location.href
      name = name.replace(/[\[\]]/g, "\\$&")
      var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url)
      if (!results) return null
      if (!results[2]) return ''
      return decodeURIComponent(results[2].replace(/\+/g, " "))
    }

    $('#taxopress_choose_icon').on('click', function (e) {
      e.preventDefault()

      var button = $(this)
      var id = jQuery('#menu_icon').attr('id')
      _custom_media = true
      wp.media.editor.send.attachment = function (props, attachment) {
        if (_custom_media) {
          $("#" + id).val(attachment.url).change()
        } else {
          return _orig_send_attachment.apply(this, [props, attachment])
        }
      }

      wp.media.editor.open(button)
      return false
    })

    $('#menu_icon').on('change', function () {
      var value = $(this).val().trim()
      $('#menu_icon_preview').html(composePreviewContent(value))
    })

    $('.taxopress-taxonomy-submit').on('click', function (e) {
      $('.taxonomy-required-field').html('');


      var fields = $(".taxopress-section").find("select, textarea, input").serializeArray(),
        field_label,
        field_object,
        field_error_count = 0,
        field_error_message = '<ul>';


      if (!$('#st-tags-slug-error-input').hasClass('hidemessage')) {
        field_error_count = 1;
        field_error_message += '<li>' + $('#st-tags-slug-error-input').html() + ' <span class="required">*</span></li>';
      }


      $.each(fields, function (i, field) {
        field_object = $('input[name="' + field.name + '"]');
        if (field_object.attr('required')) {
          if (!field.value) {
            field_label = field_object.closest('tr').find('label').html();
            field_error_count = 1;
            field_error_message += '<li>' + field_label + ' is required <span class="required">*</span></li>';
          } else if (isEmptyOrSpaces(field.value)) {
            field_label = field_object.closest('tr').find('label').html();
            field_error_count = 1;
            field_error_message += '<li>' + field_label + ' is required <span class="required">*</span></li>';
          }
        }
      });

      if( $('input[name="cpt_custom_tax[name]"]').val() && !isNaN($('input[name="cpt_custom_tax[name]"]').val()) ){
        field_error_count = 1;
        field_error_message += '<li>' + taxopress_tax_data.integer_error + ' <span class="required">*</span></li>';
      }

      field_error_message += '</ul>';



      if (field_error_count > 0) {
        e.preventDefault();
        // Display the alert
        $('#taxopress-modal-alert-content').html(field_error_message);
        $('[data-remodal-id=taxopress-modal-alert]').remodal().open();
      }


    })


    $('#name, #label, #singular_label').on('change paste', function (e) {
      if ('edit' === getParameterByName('action')) {
        return
      }
      var slug = $('#name').val()
      var plural = $('#label').val()
      var singular = $('#singular_label').val()
      var fields = $('.taxonomy_labels input[type="text"]')

      if ('' === slug) {
        return
      }
      if ('' === plural) {
        plural = slug
      }
      if ('' === singular) {
        singular = slug
      }

      $(fields).each(function (i, el) {

        var newval = $(el).data('label')
        var plurality = $(el).data('plurality')
        if ('undefined' !== newval) {
          // "slug" is our placeholder from the labels.
          if ('plural' === plurality) {
            newval = newval.replace(/item/gi, plural)
          } else {
            newval = newval.replace(/item/gi, singular)
          }
          //if ( $(el).val() === '' || $(el).val() === slug )
          {
            $(el).val(newval)
          }
        }
      })
    })


    // Change taxonomy type and post type filter position
    if ($('.taxopress-taxonomy-type-wrap').length > 0 && $('.tablenav .bulkactions').length > 0) {
      var $bulkActions = $('.tablenav .bulkactions');

      var $filtersWrapper = $('<div class="taxopress-taxonomy-filters"></div>');

      if ($('.taxopress-taxonomy-type-wrap').length > 0) {
        $filtersWrapper.append($('.taxopress-taxonomy-type-wrap').children());
        $('.taxopress-taxonomy-type-wrap').remove();
      }

      if ($('.taxopress-taxonomy-post-type-wrap').length > 0) {
        $filtersWrapper.append($('.taxopress-taxonomy-post-type-wrap').children());
        $('.taxopress-taxonomy-post-type-wrap').remove();
      }

      $bulkActions.html('');
      $bulkActions.append($filtersWrapper);
    } else if ($('.taxopress-taxonomy-type-wrap').length > 0 && $('.tablenav-pages.no-pages').length > 0) {
      $('.tablenav').hide();
      $('.taxopress-taxonomy-type-wrap').attr('style', 'margin-bottom: 10px;');
    }

    // Change taxonomy type
    $('.taxopress-taxonomy-type').on('change', function (e) {
      var taxonomy_type = $(this).val();
      insert_param_to_url('taxonomy_type', taxonomy_type)
    })

    // Change taxonomy post type
    $('.taxopress-taxonomy-post-type').on('change', function (e) {
      var post_type = $(this).val();
      insert_param_to_url('taxopress_taxonomy_post_type', post_type);
    });

    function insert_param_to_url(key, value) {
      key = encodeURIComponent(key);
      value = encodeURIComponent(value);

      // kvp looks like ['key1=value1', 'key2=value2', ...]
      var kvp = document.location.search.substr(1).split('&');
      let i = 0;

      for (; i < kvp.length; i++) {
        if (kvp[i].startsWith(key + '=')) {
          let pair = kvp[i].split('=');
          pair[1] = value;
          kvp[i] = pair.join('=');
          break;
        }
      }

      if (i >= kvp.length) {
        kvp[kvp.length] = [key, value].join('=');
      }

      // can return this or...
      let params = kvp.join('&');

      // reload page with new params
      document.location.search = params;
    }


  })

})(jQuery)
