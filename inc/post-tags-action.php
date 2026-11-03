<?php

defined('ABSPATH') || exit;

add_action('admin_init', 'taxopress_process_posttags', 8);
add_action('admin_init', 'taxopress_create_default_post_tags', 8);
add_shortcode('taxopress_postterms', 'taxopress_posttags_shortcode');
add_filter('the_content', 'taxopress_posttags_the_content', 999992);
