<?php

/**
 * Register media tags taxonomy
 */

add_action('init', function () {
    if ((int)get_option('taxopress_media_tag_deleted') === 0) {
        $labels = [
            "name" => __("Media Tags", "simple-tags"),
            "singular_name" => __("Media Tag", "simple-tags"),
            "menu_name" => __("Media Tags", "simple-tags"),
            "all_items" => __("All Media Tags", "simple-tags"),
            "edit_item" => __("Edit Media Tag", "simple-tags"),
            "view_item" => __("View Media Tag", "simple-tags"),
            "update_item" => __("Update Media Tag name", "simple-tags"),
            "add_new_item" => __("Add new Media Tag", "simple-tags"),
            "new_item_name" => __("New Media Tag name", "simple-tags"),
            "parent_item" => __("Parent Media Tag", "simple-tags"),
            "parent_item_colon" => __("Parent Media Tag:", "simple-tags"),
            "search_items" => __("Search Media Tags", "simple-tags"),
            "popular_items" => __("Popular Media Tags", "simple-tags"),
            "separate_items_with_commas" => __("Separate Media Tags with commas", "simple-tags"),
            "add_or_remove_items" => __("Add or remove Media Tags", "simple-tags"),
            "choose_from_most_used" => __("Choose from the most used Media Tags", "simple-tags"),
            "not_found" => __("No Media Tags found", "simple-tags"),
            "no_terms" => __("No Media Tags", "simple-tags"),
            "items_list_navigation" => __("Media Tags list navigation", "simple-tags"),
            "items_list" => __("Media Tags list", "simple-tags"),
            "back_to_items" => __("Back to Media Tags", "simple-tags"),
        ];

        $args = [
            "label" => __("Media Tags", "simple-tags"),
            "labels" => $labels,
            "public" => true,
            "publicly_queryable" => true,
            "hierarchical" => false,
            "show_ui" => true,
            "show_in_menu" => true,
            "show_in_nav_menus" => true,
            "query_var" => true,
            "update_count_callback" => '_update_generic_term_count',
            "rewrite" => [ 'slug' => 'media_tag', 'with_front' => true, ],
            "show_admin_column" => false,
            "show_in_rest" => true,
            "rest_base" => "media_tag",
            "rest_controller_class" => "WP_REST_Terms_Controller",
            "show_in_quick_edit" => false,
        ];
        register_taxonomy("media_tag", [ "attachment" ], $args);
    }
}, 0);

/**
 * TaxoPress log post types
 */
add_action('init', function () {

    // set up labels
    $labels = array(
        'name' => __('TaxoPress Logs', 'simple-tags'),
        'singular_name' => __('TaxoPress Logs', 'simple-tags'),
        'search_items' => __('Search TaxoPress Logs', 'simple-tags'),
        'all_items' => __('TaxoPress Logs', 'simple-tags'),
        'edit_item' => __('Edit TaxoPress Logs', 'simple-tags'),
        'update_item' => __('Update TaxoPress Logs', 'simple-tags'),
        'add_new_item' => __('Add New TaxoPress Logs', 'simple-tags'),
        'new_item_name' => __('New TaxoPress Logs', 'simple-tags'),
        'menu_name' => __('TaxoPress Logs', 'simple-tags')
    );

    register_post_type('taxopress_logs', array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => false,
        'capability_type' => 'post',
        'hierarchical' => false,
        'rewrite' => array('slug' => 'taxopress_logs'),
        'query_var' => false,
        'show_in_nav_menus' => false,
        'menu_icon' => 'dashicons-editor-justify',
        'supports' => array(
            'title',
            'editor',
            'author',
        ),
    ));
}, 0);
