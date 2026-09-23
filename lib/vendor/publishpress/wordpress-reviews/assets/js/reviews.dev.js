(function ($) {
    'use strict';

    function initReviewNotices() {
        $('.pp-wordpress-review-config').each(function () {
            var $notice = $(this);

            function dismiss(reason) {
                $.ajax({
                    method: 'POST',
                    dataType: 'json',
                    url: window.ajaxurl,
                    data: {
                        action: $notice.data('action'),
                        nonce: $notice.data('nonce'),
                        group: $notice.data('group'),
                        code: $notice.data('code'),
                        priority: $notice.data('priority'),
                        reason: reason
                    }
                });
            }

            $notice.on('click', '.pp-wordpress-review-dismiss', function () {
                var reason = $(this).data('reason');

                $notice.fadeTo(100, 0, function () {
                    $notice.slideUp(100, function () {
                        $notice.remove();
                    });
                });
                dismiss(reason);
            });

            window.setTimeout(function () {
                $notice.find('button.notice-dismiss').on('click', function () {
                    dismiss('maybe_later');
                });
            }, 1000);
        });
    }

    $(initReviewNotices);
}(jQuery));
