(function ($) {
    'use strict';
    $(function () {
        var settings = window.taxopressPostsAutoterms;
        var heading = $('.wrap > h1.wp-heading-inline').first();
        if (!settings || !heading.length) {
            return;
        }
        var button = $('<button>', {
            type: 'button',
            class: 'page-title-action',
            text: settings.run
        });
        var addPost = heading.siblings('.page-title-action').first();
        button.insertAfter(addPost.length ? addPost : heading);
        var choice = $('<select>', {'aria-label': settings.configuration});
        settings.configurations.forEach(function (configuration) {
            $('<option>', {value: configuration.id, text: configuration.title}).appendTo(choice);
        });
        if (settings.configurations.length > 1) {
            choice.insertAfter(button);
        }
        var notice = $('<div>', {
            class: 'notice notice-info inline',
            role: 'status',
            'aria-live': 'polite'
        }).hide();
        var message = $('<p>').appendTo(notice);
        notice.insertAfter(heading.siblings('.wp-header-end').first().length
            ? heading.siblings('.wp-header-end').first() : heading);

        function format(template, first, second) {
            return template.replace('%1$d', first).replace('%2$d', second);
        }
        button.on('click', function () {
            var ids = $('#the-list input[name="post[]"]:checked').map(function () {
                return this.value;
            }).get();
            notice.show();
            if (!ids.length) {
                message.text(settings.selectPosts);
                return;
            }
            var configurationId = choice.val();
            var processed = 0;
            var added = 0;
            button.prop('disabled', true);
            choice.prop('disabled', true);
            function finish() {
                button.prop('disabled', false);
                choice.prop('disabled', false);
            }
            function next() {
                message.text(format(settings.progress, processed, ids.length));
                $.post(settings.url, {
                    action: 'taxopress_run_selected_autoterms',
                    nonce: settings.nonce,
                    post_id: ids[processed],
                    configuration_id: configurationId
                }).done(function (response) {
                    if (!response || !response.success) {
                        message.text(format(settings.progress, processed, ids.length) + ' ' +
                            (response && response.data && response.data.message
                                ? response.data.message : settings.failed));
                        finish();
                        return;
                    }
                    processed++;
                    if (response.data.added) {
                        added++;
                    }
                    if (processed < ids.length) {
                        next();
                    } else {
                        message.text(format(settings.complete, processed, added));
                        finish();
                    }
                }).fail(function (xhr) {
                    var response = xhr.responseJSON;
                    message.text(format(settings.progress, processed, ids.length) + ' ' +
                        (response && response.data && response.data.message
                            ? response.data.message : settings.failed));
                    finish();
                });
            }
            next();
        });
    });
})(jQuery);
