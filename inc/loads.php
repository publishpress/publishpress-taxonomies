<?php

// TaxoPress AI Module
require_once STAGS_DIR . '/modules/taxopress-ai/taxopress-ai.php';

require STAGS_DIR . '/inc/hidden-terms.php';

require STAGS_DIR . '/inc/utilities.php'; // Internal functions
require STAGS_DIR . '/inc/functions.inc.php'; // Internal functions
require STAGS_DIR . '/inc/taxonomies-functions.php'; // Taxonomy functions
require STAGS_DIR . '/inc/tag-clouds-functions.php'; // Tag cloud functions
require STAGS_DIR . '/inc/post-tags-functions.php'; // Post tags functions
require STAGS_DIR . '/inc/related-posts-functions.php'; // Related posts functions
require STAGS_DIR . '/inc/autolinks-functions.php'; // Auto links functions
require STAGS_DIR . '/inc/autoterms-functions.php'; // Auto terms functions
require STAGS_DIR . '/inc/terms-functions.php'; // terms functions
require STAGS_DIR . '/inc/functions.deprecated.php'; // Deprecated functions
require STAGS_DIR . '/inc/functions.tpl.php';  // Templates functions

require STAGS_DIR . '/inc/class.plugin.php';
require STAGS_DIR . '/inc/class.client.php';
require STAGS_DIR . '/inc/class.client.tagcloud.php';
require STAGS_DIR . '/inc/class.client.schedule.php';
require STAGS_DIR . '/inc/class.widgets.php';
require STAGS_DIR . '/inc/class.shortcode_widgets.php';
require STAGS_DIR . '/inc/posts-tags-widget.php';
require STAGS_DIR . '/inc/related-posts-widget.php';

require STAGS_DIR . '/inc/schedule.php';

//include blocks
require STAGS_DIR . '/blocks/related-posts.php';
require STAGS_DIR . '/blocks/post-tags.php';
require STAGS_DIR . '/blocks/tag-clouds.php';

//include ajax
require STAGS_DIR . '/inc/ajax-request.php';

//review request
require STAGS_DIR . '/review-request/review.php';
