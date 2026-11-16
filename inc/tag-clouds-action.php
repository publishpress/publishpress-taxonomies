<?php

defined('ABSPATH') || exit;

add_action('admin_init', 'taxopress_process_tagcloud', 8);
add_action('admin_init', 'taxopress_create_default_tag_cloud', 8);
add_shortcode('taxopress_termsdisplay', 'taxopress_termsdisplay_shortcode');
