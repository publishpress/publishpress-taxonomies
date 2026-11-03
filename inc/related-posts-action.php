<?php

defined('ABSPATH') || exit;

add_action('admin_init', 'taxopress_process_relatedpost', 8);
add_action('admin_init', 'taxopress_create_default_related_post', 8);
add_shortcode('taxopress_relatedposts', 'taxopress_relatedposts_shortcode');
add_filter('the_content', 'taxopress_relatedposts_the_content', 999992);
