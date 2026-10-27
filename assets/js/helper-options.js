// Switches option section
jQuery(document).ready(function() {
    // Hide all by default
    jQuery('.group').hide();

    // Display active group
    var activetab = '';
    if (typeof(localStorage) != 'undefined' && localStorage != null) {
        activetab = localStorage.getItem("activetab");
    }

    // Check if current tab is accessible
    if (activetab && !jQuery(activetab + '-tab').is(':visible')) {
        // Switch to first available tab if current tab is hidden
        activetab = jQuery('.nav-tab-wrapper a:visible:first').attr('href');
        if (typeof(localStorage) != 'undefined' && localStorage != null) {
            localStorage.setItem("activetab", activetab);
        }
    }

    // allow default tab from parameter
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('active_tab')) {
        activetab = '#' + urlParams.get('active_tab');
    }
    
    if (activetab !== '' && jQuery(activetab).length) {
        jQuery(activetab).fadeIn();
    } else {
        jQuery('.group:first').fadeIn();
    }

    if (activetab !== '' && jQuery(activetab + '-tab').length) {
        jQuery(activetab + '-tab').addClass('nav-tab-active').attr('aria-current', 'true');
    } else {
        jQuery('.nav-tab-wrapper a:first').addClass('nav-tab-active').attr('aria-current', 'true');
    }

    jQuery('.nav-tab-wrapper a').click(function(evt) {
        jQuery('.nav-tab-wrapper a').removeClass('nav-tab-active').removeAttr('aria-current');
        // Keep focus on the activated tab (no blur) so keyboard users keep their place.
        jQuery(this).addClass('nav-tab-active').attr('aria-current', 'true');
        var clicked_group = jQuery(this).attr('href');
        if (typeof(localStorage) != 'undefined' && localStorage != null) {
            localStorage.setItem("activetab", jQuery(this).attr('href'));
        }
        jQuery('.group').hide();
        jQuery(clicked_group).fadeIn();
        evt.preventDefault();
    });
});

jQuery(document).ready(function($) {
    
    $('.text-color').wpColorPicker();

    $('.simple-tags-dismiss-rating').on('click', function(e) {
      e.preventDefault();

      localStorage.setItem('simple-tags-dismiss-rating', true);
      $('.simple-tags-review-box').hide();

      return false;
    });

    if (localStorage.getItem('simple-tags-dismiss-rating')) {
      $('.simple-tags-review-box').hide();
    }
});