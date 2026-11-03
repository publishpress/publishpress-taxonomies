<?php

defined('ABSPATH') || exit;

add_action('admin_init', 'taxopress_flush_rewrite_rules');
add_action('admin_init', 'taxopress_process_taxonomy', 8);
add_action('init', 'taxopress_do_convert_taxonomy_terms');
add_action('init', 'taxopress_create_custom_taxonomies', 9);  // Leave on standard init for legacy purposes.
add_action('init', 'unregister_tags', 999);
add_action('init', 'taxopress_recreate_custom_taxonomies', 999);
add_action('save_post', 'taxopress_set_default_taxonomy_terms', 100, 2);
add_action('restrict_manage_posts', 'taxopress_get_dropdown');
add_filter('wp_dropdown_cats', 'taxopress_filter_dropdown_cats');
add_filter('get_terms_args', 'taxopress_get_terms_args', 5, 2);
add_filter('get_terms', 'taxopress_filter_terms', 5, 4);
add_filter('get_the_terms', 'taxopress_terms_order_frontend', 5, 3);
