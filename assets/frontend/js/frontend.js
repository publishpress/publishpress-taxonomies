jQuery(document).ready(function($) {
    // -------------------------------------------------------------
    //   Table List: See more / Close table toggle
    //   Scoped to the button's own table so several tables on one
    //   page work independently.
    // -------------------------------------------------------------
    $(document).on('click', '.taxopress-see-more-link', function (e) {
        e.preventDefault();

        var $button = $(this);
        var tableId = $button.attr('aria-controls');
        var $table = tableId ? $('#' + tableId) : $();
        if (!$table.length) {
            $table = $button.closest('table');
        }

        var expanded = $button.attr('aria-expanded') === 'true';
        var $rows = $table.find('.taxopress-table-row').slice(6);

        if (expanded) {
            $rows.addClass('hidden');
        } else {
            $rows.removeClass('hidden');
        }

        $button.attr('aria-expanded', expanded ? 'false' : 'true');
        $button.find('.taxopress-see-more-label').text(
            expanded ? $button.data('label-more') : $button.data('label-close')
        );
        $button.find('.taxopress-arrow')
            .toggleClass('right', expanded)
            .toggleClass('down', !expanded);
    });
});
