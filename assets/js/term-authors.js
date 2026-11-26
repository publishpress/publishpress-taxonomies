(function ($) {
  'use strict';

  $(function () {
    var nativeFilter = $('#taxopress-native-author-filter');
    var nativeToolbar = $('#posts-filter .tablenav.top');
    if (nativeFilter.length && !nativeToolbar.length && $('#posts-filter .wp-list-table').length) {
      nativeToolbar = $('<div>', { class: 'tablenav top' }).insertBefore('#posts-filter .wp-list-table');
    }
    if (nativeFilter.length && nativeToolbar.length) {
      nativeFilter.prependTo(nativeToolbar).prop('hidden', false);
      var authorSelect = nativeFilter.find('[name="taxopress_author"]');
      var searchForm = $('.search-form').has('.search-box');
      var searchAuthor = $('<input>', { type: 'hidden', name: 'taxopress_author', value: authorSelect.val() });
      searchForm.append(searchAuthor);
      authorSelect.on('change', function () {
        searchAuthor.val($(this).val());
      });
      nativeFilter.on('click', 'button', function () {
        // Native term lists submit bulk actions by POST and search through a separate GET form.
        // Build a read-only URL so both forms, sort links, and pagination retain this filter.
        var destination = new URL(window.location.href);
        var sourceForm = nativeFilter.closest('form');
        ['taxonomy', 'post_type', 'orderby', 'order'].forEach(function (name) {
          var input = sourceForm.find('[name="' + name + '"]').first();
          if (input.length && input.val()) {
            destination.searchParams.set(name, input.val());
          }
        });
        var search = searchForm.find('[name="s"]').val();
        if (search !== undefined) {
          destination.searchParams.set('s', search);
        }
        if (authorSelect.val() === '0') {
          destination.searchParams.delete('taxopress_author');
        } else {
          destination.searchParams.set('taxopress_author', authorSelect.val());
        }
        ['paged', 'action', 'action2', 'tag_ID', 'delete_tags[]', '_wpnonce', '_wp_http_referer'].forEach(function (name) {
          destination.searchParams.delete(name);
        });
        window.location.assign(destination.toString());
      });
    }

    if (typeof inlineEditTax === 'undefined') {
      return;
    }

    var originalEdit = inlineEditTax.edit;
    inlineEditTax.edit = function (id) {
      var result = originalEdit.apply(this, arguments);
      var termId = typeof id === 'object' ? this.getId(id) : id;
      var row = $(this.what + termId);
      var editor = $('#edit-' + termId);
      var data = row.find('.taxopress-term-author-data');
      var inlineData = $('#inline_' + termId);
      var taxonomy = inlineData.find('.taxonomy').text();
      var authorId = inlineData.find('.taxopress-term-author-id').text() || data.attr('data-author-id') || '0';

      if (taxonomy) {
        editor.find('[name="edit_taxonomy"]').val(taxonomy);
      }
      editor.find('[name="taxopress_term_author"]').val(authorId);
      updateAuthorField(editor);
      return result;
    };

    function updateAuthorField(editor) {
      var taxonomy = editor.find('[name="edit_taxonomy"]');
      var enabled = !taxonomy.length || taxonomy.find(':selected').attr('data-term-authors') === '1';
      var field = editor.find('.taxopress-term-author-field');
      field.prop('hidden', !enabled);
      field.find(':input').prop('disabled', !enabled);
    }

    $(document).on('change', '.inline-edit-row [name="edit_taxonomy"]', function () {
      updateAuthorField($(this).closest('.inline-edit-row'));
    });
  });
})(jQuery);
