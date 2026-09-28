<?php

/**
 * Construct a dropdown of our taxonomies so users can select which to edit.
 *
 *
 * @param array $taxonomies Array of taxonomies that are registered. Optional.
 */
function taxopress_taxonomies_dropdown($taxonomies = [])
{

    $ui = new taxopress_admin_ui();

    if (!empty($taxonomies)) {
        $select            = [];
        $select['options'] = [];

        foreach ($taxonomies as $tax) {
            $text                = !empty($tax['label']) ? esc_html($tax['label']) : esc_html($tax['name']);
            $select['options'][] = [
                'attr' => $tax['name'],
                'text' => $text,
            ];
        }

        $current            = taxopress_get_current_taxonomy();
        $select['selected'] = $current;

        /**
         * Filters the taxonomy dropdown options before rendering.
         *
         * @param array $select Array of options for the dropdown.
         * @param array $taxonomies Array of original passed in post types.
         */
        $select = apply_filters('taxopress_taxonomies_dropdown_options', $select, $taxonomies);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $ui->get_select_input([
            'namearray'  => 'taxopress_selected_taxonomy',
            'name'       => 'taxonomy',
            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'wrap'       => false,
        ]);
    }
}

/**
 * Get the selected taxonomy from the $_POST global.
 *
 *
 * @param bool $taxonomy_deleted Whether or not a taxonomy was recently deleted. Optional. Default false.
 * @return bool|string False on no result, sanitized taxonomy if set.
 * @internal
 *
 */
function taxopress_get_current_taxonomy($taxonomy_deleted = false)
{

    $tax = false;

    if (!empty($_POST)) {
        if (!empty($_POST['taxopress_select_taxonomy_nonce_field'])) {
            check_admin_referer('taxopress_select_taxonomy_nonce_action', 'taxopress_select_taxonomy_nonce_field');
        }
        if (isset($_POST['taxopress_selected_taxonomy']['taxonomy'])) {
            $tax = sanitize_text_field(wp_unslash($_POST['taxopress_selected_taxonomy']['taxonomy']));
        } elseif ($taxonomy_deleted) {
            $taxonomies = taxopress_get_taxonomy_data();
            $tax        = key($taxonomies);
        } elseif (isset($_POST['cpt_custom_tax']['name'])) {
            // Return the submitted value.
            if (!in_array($_POST['cpt_custom_tax']['name'], taxopress_reserved_taxonomies(), true)) {
                $tax = sanitize_text_field(wp_unslash($_POST['cpt_custom_tax']['name']));
            } else {
                // Return the original value since user tried to submit a reserved term.
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified by check_admin_referer
                $tax = isset($_POST['tax_original']) ? sanitize_text_field(wp_unslash($_POST['tax_original'])) : '';
            }
        }
    } elseif (!empty($_GET) && isset($_GET['taxopress_taxonomy'])) {
        $tax = sanitize_text_field(wp_unslash($_GET['taxopress_taxonomy']));
    } else {
        $taxonomies = taxopress_get_taxonomy_data();
        if (!empty($taxonomies)) {
            // Will return the first array key.
            $tax = key($taxonomies);
        }
    }

    /**
     * Filters the current taxonomy to edit.
     *
     * @param string $tax Taxonomy slug.
     */
    return apply_filters('taxopress_current_taxonomy', $tax);
}

/**
 * Delete our custom taxonomy from the array of taxonomies.
 *
 *
 * @param array $data The $_POST values. Optional.
 * @return bool|string False on failure, string on success.
 * @internal
 *
 */
function taxopress_delete_taxonomy($data = [])
{

    if (is_string($data) && taxonomy_exists($data)) {
        $data = [
            'cpt_custom_tax' => [
                'name' => $data,
            ],
        ];
    }

    // Check if they selected one to delete.
    if (empty($data['cpt_custom_tax']['name'])) {
        return taxopress_admin_notices(
            'error',
            '',
            false,
            esc_html__('Please provide a taxonomy to delete', 'simple-tags')
        );
    }

    /**
     * Fires before a taxonomy is deleted from our saved options.
     *
     *
     * @param array $data Array of taxonomy data we are deleting.
     */
    do_action('taxopress_before_delete_taxonomy', $data);

    $taxonomies = taxopress_get_taxonomy_data();
    $external_taxonomies = taxopress_get_extername_taxonomy_data();

    if (array_key_exists(strtolower($data['cpt_custom_tax']['name']), $taxonomies)) {
        unset($taxonomies[$data['cpt_custom_tax']['name']]);

        /**
         * Filters whether or not 3rd party options were saved successfully within taxonomy deletion.
         *
         * @param bool $value Whether or not someone else saved successfully. Default false.
         * @param array $taxonomies Array of our updated taxonomies data.
         * @param array $data Array of submitted taxonomy to update.
         */
        if (false === ($success = apply_filters('taxopress_taxonomy_delete_tax', false, $taxonomies, $data))) {
            $success = update_option('taxopress_taxonomies', $taxonomies);
        }
    }

    if (array_key_exists(strtolower($data['cpt_custom_tax']['name']), $external_taxonomies)) {
        unset($external_taxonomies[$data['cpt_custom_tax']['name']]);

        /**
         * Filters whether or not 3rd party options were saved successfully within taxonomy deletion.
         *
         * @param bool $value Whether or not someone else saved successfully. Default false.
         * @param array $external_taxonomies Array of our updated taxonomies data.
         * @param array $data Array of submitted taxonomy to update.
         */
        if (false === ($success = apply_filters('taxopress_taxonomy_delete_tax', false, $external_taxonomies, $data))) {
            $success = update_option('taxopress_external_taxonomies', $external_taxonomies);
        }
    }

    if ($data['cpt_custom_tax']['name'] === 'media_tag') {
        // phpcs:ignore WordPressVIPMinimum.Performance.TaxonomyMetaInOptions.PossibleTermMetaInOptions -- Default term stored in options for compatibility
        $success = update_option('taxopress_media_tag_deleted', 1);
    }

    // phpcs:ignore WordPressVIPMinimum.Performance.TaxonomyMetaInOptions.PossibleTermMetaInOptions -- Default term stored in options for compatibility
    delete_option("default_term_{$data['cpt_custom_tax']['name']}");

    /**
     * Fires after a taxonomy is deleted from our saved options.
     *
     *
     * @param array $data Array of taxonomy data that was deleted.
     */
    do_action('taxopress_after_delete_taxonomy', $data);

    // Used to help flush rewrite rules on init.
    set_transient('taxopress_flush_rewrite_rules', 'true', 5 * 60);

    if (isset($success)) {
        return 'delete_success';
    }

    return 'delete_fail';
}

/**
 * Add to or update our PublishPress Taxonomies option with new data.
 *
 *
 * @param array $data Array of taxonomy data to update. Optional.
 * @return bool|string False on failure, string on success.
 * @internal
 *
 */
function taxopress_update_taxonomy($data = [])
{

    //update our custom checkbox value if not checked

    if (!isset($data['cpt_custom_tax']['hierarchical'])) {
        $data['cpt_custom_tax']['hierarchical'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['rewrite'])) {
        $data['cpt_custom_tax']['rewrite'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['rewrite_withfront'])) {
        $data['cpt_custom_tax']['rewrite_withfront'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['rewrite_hierarchical'])) {
        $data['cpt_custom_tax']['rewrite_hierarchical'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_ui'])) {
        $data['cpt_custom_tax']['show_ui'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_in_menu'])) {
        $data['cpt_custom_tax']['show_in_menu'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_in_nav_menus'])) {
        $data['cpt_custom_tax']['show_in_nav_menus'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_admin_column'])) {
        $data['cpt_custom_tax']['show_admin_column'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_in_rest'])) {
        $data['cpt_custom_tax']['show_in_rest'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['show_in_quick_edit'])) {
        $data['cpt_custom_tax']['show_in_quick_edit'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['public'])) {
        $data['cpt_custom_tax']['public'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['publicly_queryable'])) {
        $data['cpt_custom_tax']['publicly_queryable'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['query_var'])) {
        $data['cpt_custom_tax']['query_var'] = 0;
    }
    if (!isset($data['cpt_custom_tax']['include_in_result'])) {
        $data['cpt_custom_tax']['include_in_result'] = 0;
    }
    if (! isset($data['cpt_custom_tax']['show_in_filter'])) {
        $data['cpt_custom_tax']['show_in_filter'] = 0;
    }
    if (! isset($data['cpt_custom_tax']['order'])) {
        $data['cpt_custom_tax']['order'] = 'asc';
    }
    if (! isset($data['cpt_custom_tax']['orderby'])) {
        $data['cpt_custom_tax']['orderby'] = 'term_id';
    }
    if (! isset($data['cpt_custom_tax']['enable_taxopress_ordering'])) {
        $data['cpt_custom_tax']['enable_taxopress_ordering'] = 0;
    }

    /**
     * Fires before a taxonomy is updated to our saved options.
     *
     *
     * @param array $data Array of taxonomy data we are updating.
     */
    do_action('taxopress_before_update_taxonomy', $data);

    // They need to provide a name.
    if (empty($data['cpt_custom_tax']['name'])) {
        return taxopress_admin_notices('error', '', false, esc_html__('Please provide a taxonomy name', 'simple-tags'));
    }

    if (!isset($data['taxonomy_external_edit'])) {
        // Maybe a little harsh, but we shouldn't be saving THAT frequently.
        // phpcs:ignore WordPressVIPMinimum.Performance.TaxonomyMetaInOptions.PossibleTermMetaInOptions -- Default term stored in options for compatibility
        delete_option("default_term_{$data['cpt_custom_tax']['name']}");
    }

    if (!isset($data['taxonomy_external_edit'])) {
        if (!empty($data['tax_original']) && $data['tax_original'] !== $data['cpt_custom_tax']['name']) {
            if (!empty($data['update_taxonomy'])) {
                add_filter('taxopress_convert_taxonomy_terms', '__return_true');
            }
        }
    }

    $sanitized_data = [];
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            $sanitized_data[$key] = taxopress_sanitize_text_field($value);
        } else {
            $new_value = [];
            foreach ($data[$key] as $option_key => $option_value) {
                $new_value[$option_key] = taxopress_sanitize_text_field($option_value);
            }
            $sanitized_data[$key] = $new_value;
        }
    }
    $data = $sanitized_data;

    if (
        false !== strpos($data['cpt_custom_tax']['name'], '\'') ||
        false !== strpos($data['cpt_custom_tax']['name'], '\"') ||
        false !== strpos($data['cpt_custom_tax']['rewrite_slug'], '\'') ||
        false !== strpos($data['cpt_custom_tax']['rewrite_slug'], '\"')
    ) {
        add_filter('taxopress_custom_error_message', 'taxopress_slug_has_quotes');

        return 'error';
    }

    $taxonomies          = taxopress_get_taxonomy_data();
    $external_taxonomies = taxopress_get_extername_taxonomy_data();


    if (!isset($data['taxonomy_external_edit'])) {
        /**
         * Check if we already have a post type of that name.
         *
         * @param bool $value Assume we have no conflict by default.
         * @param string $value Post type slug being saved.
         * @param array $post_types Array of existing post types from PublishPress Taxonomies.
         */
        $slug_exists = apply_filters(
            'taxopress_taxonomy_slug_exists',
            false,
            $data['cpt_custom_tax']['name'],
            $taxonomies
        );
        if (true === $slug_exists) {
            add_filter('taxopress_custom_error_message', 'taxopress_slug_matches_taxonomy');

            return 'error';
        }
    }

    foreach ($data['cpt_tax_labels'] as $key => $label) {
        if (empty($label)) {
            unset($data['cpt_tax_labels'][$key]);
        }
        $label                        = str_replace('"', '', htmlspecialchars_decode($label));
        $label                        = htmlspecialchars($label, ENT_QUOTES);
        $label                        = trim($label);
        $data['cpt_tax_labels'][$key] = stripslashes_deep($label);
    }

    $label = ucwords(str_replace('_', ' ', $data['cpt_custom_tax']['name']));
    if (!empty($data['cpt_custom_tax']['label'])) {
        $label = str_replace('"', '', htmlspecialchars_decode($data['cpt_custom_tax']['label']));
        $label = htmlspecialchars(stripslashes($label), ENT_QUOTES);
    }

    $name = trim($data['cpt_custom_tax']['name']);

    $singular_label = ucwords(str_replace('_', ' ', $data['cpt_custom_tax']['name']));
    if (!empty($data['cpt_custom_tax']['singular_label'])) {
        $singular_label = str_replace('"', '', htmlspecialchars_decode($data['cpt_custom_tax']['singular_label']));
        $singular_label = htmlspecialchars(stripslashes($singular_label));
    }
    $description           = sanitize_textarea_field(stripslashes_deep($data['cpt_custom_tax']['description']));
    $query_var_slug        = trim($data['cpt_custom_tax']['query_var_slug']);
    $rewrite_slug          = trim($data['cpt_custom_tax']['rewrite_slug']);
    $rest_base             = trim($data['cpt_custom_tax']['rest_base']);
    $rest_controller_class = trim($data['cpt_custom_tax']['rest_controller_class']);
    $show_quickpanel_bulk  = !empty($data['cpt_custom_tax']['show_in_quick_edit']) ? taxopress_disp_boolean($data['cpt_custom_tax']['show_in_quick_edit']) : '';
    $show_in_filter        = !empty($data['cpt_custom_tax']['show_in_filter']) ? taxopress_disp_boolean($data['cpt_custom_tax']['show_in_filter']) : '';
    $order                = !empty($data['cpt_custom_tax']['order']) ? sanitize_text_field($data['cpt_custom_tax']['order']) : 'asc';
    $orderby              = !empty($data['cpt_custom_tax']['orderby']) ? sanitize_text_field($data['cpt_custom_tax']['orderby']) : 'term_id';
    $enable_taxopress_ordering = !empty($data['cpt_custom_tax']['enable_taxopress_ordering']) ? taxopress_disp_boolean($data['cpt_custom_tax']['enable_taxopress_ordering']) : 0;
    $default_term          = trim($data['cpt_custom_tax']['default_term']);

    $meta_box_cb = trim($data['cpt_custom_tax']['meta_box_cb']);
    // We may or may not need to force a boolean false keyword.
    $maybe_false = strtolower(trim($data['cpt_custom_tax']['meta_box_cb']));
    if ('false' === $maybe_false) {
        $meta_box_cb = $maybe_false;
    }

    $internal_taxonomy_edit = true;

    if (isset($data['taxonomy_external_edit']) || $name === 'media_tag') {
        $internal_taxonomy_edit = false;
    }

    if ($internal_taxonomy_edit) {
        $taxonomies[$data['cpt_custom_tax']['name']] = [
            'name'                  => $name,
            'label'                 => $label,
            'singular_label'        => $singular_label,
            'description'           => $description,
            'public'                => taxopress_disp_boolean($data['cpt_custom_tax']['public']),
            'publicly_queryable'    => taxopress_disp_boolean($data['cpt_custom_tax']['publicly_queryable']),
            'include_in_result'     => taxopress_disp_boolean($data['cpt_custom_tax']['include_in_result']),
            'hierarchical'          => taxopress_disp_boolean($data['cpt_custom_tax']['hierarchical']),
            'show_ui'               => taxopress_disp_boolean($data['cpt_custom_tax']['show_ui']),
            'show_in_menu'          => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_menu']),
            'show_in_nav_menus'     => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_nav_menus']),
            'query_var'             => taxopress_disp_boolean($data['cpt_custom_tax']['query_var']),
            'query_var_slug'        => $query_var_slug,
            'rewrite'               => taxopress_disp_boolean($data['cpt_custom_tax']['rewrite']),
            'rewrite_slug'          => $rewrite_slug,
            'rewrite_withfront'     => $data['cpt_custom_tax']['rewrite_withfront'],
            'rewrite_hierarchical'  => $data['cpt_custom_tax']['rewrite_hierarchical'],
            'show_admin_column'     => taxopress_disp_boolean($data['cpt_custom_tax']['show_admin_column']),
            'show_in_rest'          => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_rest']),
            'show_in_quick_edit'    => $show_quickpanel_bulk,
            'rest_base'             => $rest_base,
            'rest_controller_class' => $rest_controller_class,
            'labels'                => $data['cpt_tax_labels'],
            'meta_box_cb'           => $meta_box_cb,
            'default_term'          => $default_term,
            'show_in_filter'        => $show_in_filter,
            'order'                 => $order,
            'orderby'               => $orderby,
            'enable_taxopress_ordering' => $enable_taxopress_ordering,
        ];

        $taxonomies[$data['cpt_custom_tax']['name']]['object_types'] = isset($data['cpt_post_types']) ? $data['cpt_post_types'] : '';


        /**
         * Filters final data to be saved right before saving taxoomy data.
         *
         * @param array $taxonomies Array of final taxonomy data to save.
         * @param string $name Taxonomy slug for taxonomy being saved.
         */
        $taxonomies = apply_filters('taxopress_pre_save_taxonomy', $taxonomies, $name);

        /**
         * Filters whether or not 3rd party options were saved successfully within taxonomy add/update.
         *
         * @param bool $value Whether or not someone else saved successfully. Default false.
         * @param array $taxonomies Array of our updated taxonomies data.
         * @param array $data Array of submitted taxonomy to update.
         */
        if (false === ($success = apply_filters('taxopress_taxonomy_update_save', false, $taxonomies, $data))) {
            $success = update_option('taxopress_taxonomies', $taxonomies);
        }
    } else {
        $external_taxonomies[$data['cpt_custom_tax']['name']] = [
            'name'                  => $name,
            'label'                 => $label,
            'singular_label'        => $singular_label,
            'description'           => $description,
            'public'                => taxopress_disp_boolean($data['cpt_custom_tax']['public']),
            'publicly_queryable'    => taxopress_disp_boolean($data['cpt_custom_tax']['publicly_queryable']),
            'include_in_result'     => taxopress_disp_boolean($data['cpt_custom_tax']['include_in_result']),
            'hierarchical'          => taxopress_disp_boolean($data['cpt_custom_tax']['hierarchical']),
            'show_ui'               => taxopress_disp_boolean($data['cpt_custom_tax']['show_ui']),
            'show_in_menu'          => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_menu']),
            'show_in_nav_menus'     => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_nav_menus']),
            'query_var'             => taxopress_disp_boolean($data['cpt_custom_tax']['query_var']),
            'query_var_slug'        => $query_var_slug,
            'rewrite'               => taxopress_disp_boolean($data['cpt_custom_tax']['rewrite']),
            'rewrite_slug'          => $rewrite_slug,
            'rewrite_withfront'     => $data['cpt_custom_tax']['rewrite_withfront'],
            'rewrite_hierarchical'  => $data['cpt_custom_tax']['rewrite_hierarchical'],
            'show_admin_column'     => taxopress_disp_boolean($data['cpt_custom_tax']['show_admin_column']),
            'show_in_rest'          => taxopress_disp_boolean($data['cpt_custom_tax']['show_in_rest']),
            'show_in_quick_edit'    => $show_quickpanel_bulk,
            'rest_base'             => $rest_base,
            'rest_controller_class' => $rest_controller_class,
            'labels'                => $data['cpt_tax_labels'],
            'meta_box_cb'           => $meta_box_cb,
            'default_term'          => $default_term,
            'show_in_filter'        => $show_in_filter,
            'order'                 => $order,
            'orderby'               => $orderby,
            'enable_taxopress_ordering' => $enable_taxopress_ordering,
        ];

        $external_taxonomies[$data['cpt_custom_tax']['name']]['object_types'] = isset($data['cpt_post_types']) ? $data['cpt_post_types'] : [];
        $success                                                              = update_option(
            'taxopress_external_taxonomies',
            $external_taxonomies
        );
    }

    /**
     * Fires after a taxonomy is updated to our saved options.
     *
     *
     * @param array $data Array of taxonomy data that was updated.
     */
    do_action('taxopress_after_update_taxonomy', $data);

    // Used to help flush rewrite rules on init.
    set_transient('taxopress_flush_rewrite_rules', 'true', 5 * 60);

    if (isset($success) && 'new' === $data['cpt_tax_status']) {
        return 'add_success';
    }

    return 'update_success';
}

/**
 * Return an array of names that users should not or can not use for taxonomy names.
 *
 * @return array $value Array of names that are recommended against.
 */
function taxopress_reserved_taxonomies()
{

    $reserved = [
        'action',
        'attachment',
        'attachment_id',
        'author',
        'author_name',
        'calendar',
        'cat',
        'category',
        'category__and',
        'category__in',
        'category__not_in',
        'category_name',
        'comments_per_page',
        'comments_popup',
        'customize_messenger_channel',
        'customized',
        'cpage',
        'day',
        'debug',
        'error',
        'exact',
        'feed',
        'fields',
        'hour',
        'include',
        'link_category',
        'm',
        'minute',
        'monthnum',
        'more',
        'name',
        'nav_menu',
        'nonce',
        'nopaging',
        'offset',
        'order',
        'orderby',
        'p',
        'page',
        'page_id',
        'paged',
        'pagename',
        'pb',
        'perm',
        'post',
        'post__in',
        'post__not_in',
        'post_format',
        'post_mime_type',
        'post_status',
        'post_tag',
        'post_type',
        'posts',
        'posts_per_archive_page',
        'posts_per_page',
        'preview',
        'robots',
        's',
        'search',
        'second',
        'sentence',
        'showposts',
        'static',
        'subpost',
        'subpost_id',
        'tag',
        'tag__and',
        'tag__in',
        'tag__not_in',
        'tag_id',
        'tag_slug__and',
        'tag_slug__in',
        'taxonomy',
        'tb',
        'term',
        'theme',
        'type',
        'types',
        'w',
        'withcomments',
        'withoutcomments',
        'year',
        'output',
    ];

    /**
     * Filters the list of reserved post types to check against.
     * 3rd party plugin authors could use this to prevent duplicate post types.
     *
     *
     * @param array $value Array of post type slugs to forbid.
     */
    $custom_reserved = apply_filters('taxopress_reserved_taxonomies', []);

    if (is_string($custom_reserved) && !empty($custom_reserved)) {
        $reserved[] = $custom_reserved;
    } elseif (is_array($custom_reserved) && !empty($custom_reserved)) {
        foreach ($custom_reserved as $slug) {
            $reserved[] = $slug;
        }
    }

    return $reserved;
}

/**
 * Convert taxonomies.
 *
 * @param string $original_slug Original taxonomy slug. Optional. Default empty string.
 * @param string $new_slug New taxonomy slug. Optional. Default empty string.
 * @internal
 *
 */
function taxopress_convert_taxonomy_terms($original_slug = '', $new_slug = '')
{
    global $wpdb;

    $args = [
        'taxonomy'   => $original_slug,
        'hide_empty' => false,
        'fields'     => 'ids',
    ];

    $term_ids = get_terms($args);

    if (is_int($term_ids)) {
        $term_ids = (array)$term_ids;
    }

    if (is_array($term_ids) && !empty($term_ids)) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Term IDs are integers from array, safely imploded
        $term_ids = implode(',', $term_ids);

        $query = "UPDATE `{$wpdb->term_taxonomy}` SET `taxonomy` = %s WHERE `taxonomy` = %s AND `term_id` IN ( {$term_ids} )";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Term IDs safely imploded; direct query necessary for bulk taxonomy update
        $wpdb->query($wpdb->prepare($query, $new_slug, $original_slug));
    }
    taxopress_delete_taxonomy($original_slug);
}

/**
 * Checks if we are trying to register an already registered taxonomy slug.
 *
 * @param bool $slug_exists Whether or not the post type slug exists. Optional. Default false.
 * @param string $taxonomy_slug The post type slug being saved. Optional. Default empty string.
 * @param array $taxonomies Array of plugin-registered post types. Optional.
 *
 * @return bool
 */
function taxopress_check_existing_taxonomy_slugs($slug_exists = false, $taxonomy_slug = '', $taxonomies = [])
{

    // If true, then we'll already have a conflict, let's not re-process.
    if (true === $slug_exists) {
        return $slug_exists;
    }

    // Check if PublishPress Taxonomies has already registered this slug.
    if (array_key_exists(strtolower($taxonomy_slug), $taxonomies)) {
        return true;
    }

    // Check if we're registering a reserved post type slug.
    if (in_array($taxonomy_slug, taxopress_reserved_taxonomies())) {
        return true;
    }

    // Check if other plugins have registered this same slug.
    $public                = get_taxonomies(['_builtin' => false, 'public' => true]);
    $private               = get_taxonomies(['_builtin' => false, 'public' => false]);
    $registered_taxonomies = array_merge($public, $private);
    if (in_array($taxonomy_slug, $registered_taxonomies)) {
        return true;
    }

    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Reference comment for logic tracking
    // If we're this far, it's false.
    return $slug_exists;
}

add_filter('taxopress_taxonomy_slug_exists', 'taxopress_check_existing_taxonomy_slugs', 10, 3);

/**
 * Handle the save and deletion of taxonomy data.
 */
function taxopress_process_taxonomy()
{

    if (wp_doing_ajax()) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if (empty($_GET)) {
        return;
    }

    if (!isset($_GET['page'])) {
        return;
    }
    if ('st_taxonomies' !== $_GET['page']) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    if (isset($_GET['new_taxonomy'])) {
        if ((int)$_GET['new_taxonomy'] === 1) {
            add_action('admin_notices', "taxopress_add_success_message_admin_notice");
            add_filter('removable_query_args', 'taxopress_filter_removable_query_args_3');
        }
    }

    if (!empty($_POST) && (isset($_POST['cpt_submit']) || isset($_POST['cpt_delete']))) {
        $result = '';
        if (isset($_POST['cpt_submit'])) {
            check_admin_referer('taxopress_addedit_taxonomy_nonce_action', 'taxopress_addedit_taxonomy_nonce_field');
            $result = taxopress_update_taxonomy($_POST);
        } elseif (isset($_POST['cpt_delete'])) {
            check_admin_referer('taxopress_addedit_taxonomy_nonce_action', 'taxopress_addedit_taxonomy_nonce_field');
            $result = taxopress_delete_taxonomy($_POST);
            add_filter('taxopress_taxonomy_deleted', '__return_true');
        }

        if ($result && is_callable("taxopress_{$result}_admin_notice")) {
            if ($result === 'add_success') {
                taxopress_add_success_admin_notice();
            } else {
                add_action('admin_notices', "taxopress_{$result}_admin_notice");
            }
        }

        if (isset($_POST['cpt_delete'])) {
            wp_safe_redirect(
                add_query_arg(
                    ['page' => 'st_taxonomies'],
                    taxopress_admin_url('admin.php?page=st_taxonomies')
                )
            );
            exit();
        }
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-deactivate-taxonomy') {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified below
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'taxonomy-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified above
            $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash($_REQUEST['taxonomy'])) : '';
            taxopress_deactivate_taxonomy($taxonomy);
            add_action('admin_notices', "taxopress_deactivated_admin_notice");
        }
        add_filter('removable_query_args', 'taxopress_filter_removable_query_args');
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-reactivate-taxonomy') {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified below
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'taxonomy-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified above
            $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash($_REQUEST['taxonomy'])) : '';
            taxopress_activate_taxonomy($taxonomy);
            add_action('admin_notices', "taxopress_activated_admin_notice");
        }
        add_filter('removable_query_args', 'taxopress_filter_removable_query_args');
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-delete-taxonomy') {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified below
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'taxonomy-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified above
            $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash($_REQUEST['taxonomy'])) : '';
            taxopress_action_delete_taxonomy($taxonomy);
        }
        add_filter('removable_query_args', 'taxopress_filter_removable_query_args');
    } elseif (isset($_REQUEST['action2']) && $_REQUEST['action2'] === 'taxopress-reactivate-taxonomy') {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified below
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'taxonomy-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified above
            $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash($_REQUEST['taxonomy'])) : '';
            taxopress_activate_taxonomy($taxonomy);
            add_action('admin_notices', "taxopress_activated_admin_notice");
        }
        add_filter('removable_query_args', 'taxopress_filter_removable_query_args_2');
    } elseif (isset($_REQUEST['action2']) && $_REQUEST['action2'] === 'taxopress-deactivate-taxonomy') {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified below
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'taxonomy-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified above
            $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash($_REQUEST['taxonomy'])) : '';
            taxopress_deactivate_taxonomy($taxonomy);
            add_action('admin_notices', "taxopress_deactivated_admin_notice");
        }
        add_filter('removable_query_args', 'taxopress_filter_removable_query_args_2');
    }
}


/**
 * Handle the conversion of taxonomy terms.
 *
 * This function came to be because we needed to convert AFTER registration.
 */
function taxopress_do_convert_taxonomy_terms()
{

    /**
     * Whether or not to convert taxonomy terms.
     *
     * @param bool $value Whether or not to convert.
     */
    if (apply_filters('taxopress_convert_taxonomy_terms', false)) {
        check_admin_referer('taxopress_addedit_taxonomy_nonce_action', 'taxopress_addedit_taxonomy_nonce_field');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified by check_admin_referer above
        $tax_original = isset($_POST['tax_original']) ? sanitize_text_field(wp_unslash($_POST['tax_original'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Nonce verified by check_admin_referer above
        $cpt_name = isset($_POST['cpt_custom_tax']['name']) ? sanitize_text_field(wp_unslash($_POST['cpt_custom_tax']['name'])) : '';
        taxopress_convert_taxonomy_terms($tax_original, $cpt_name);
    }
}

/**
 * Handles slug_exist checks for cases of editing an existing taxonomy.
 *
 * @param bool $slug_exists Current status for exist checks.
 * @param string $taxonomy_slug Taxonomy slug being processed.
 * @param array $taxonomies PublishPress Taxonomies taxonomies.
 * @return bool
 */
function taxopress_updated_taxonomy_slug_exists($slug_exists, $taxonomy_slug = '', $taxonomies = [])
{
    if (
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Function called after nonce verification in parent handler
        (isset($_POST['cpt_tax_status']) && 'edit' === $_POST['cpt_tax_status']) && !in_array($taxonomy_slug, taxopress_reserved_taxonomies(), true) &&
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        (isset($_POST['tax_original']) && $taxonomy_slug === $_POST['tax_original'])
    ) {
        $slug_exists = false;
    }

    return $slug_exists;
}

add_filter('taxopress_taxonomy_slug_exists', 'taxopress_updated_taxonomy_slug_exists', 11, 3);


/**
 * Return boolean status depending on passed in value.
 *
 * @param mixed $bool_text text to compare to typical boolean values.
 * @return bool Which bool value the passed in value was.
 */
function get_taxopress_disp_boolean($bool_text)
{
    $bool_text = (string)$bool_text;
    if (empty($bool_text) || '0' === $bool_text || 'false' === $bool_text) {
        return false;
    }

    return true;
}

/**
 * Return string versions of boolean values.
 *
 * @param string $bool_text String boolean value.
 * @return string standardized boolean text.
 */
function taxopress_disp_boolean($bool_text)
{
    $bool_text = (string)$bool_text;
    if (empty($bool_text) || '0' === $bool_text || 'false' === $bool_text) {
        return 'false';
    }

    return 'true';
}

/**
 * Conditionally flushes rewrite rules if we have reason to.
 */
function taxopress_flush_rewrite_rules()
{

    if (wp_doing_ajax()) {
        return;
    }

    /*
     * Wise men say that you should not do flush_rewrite_rules on init or admin_init. Due to the nature of our plugin
     * and how new post types or taxonomies can suddenly be introduced, we need to...potentially. For this,
     * we rely on a short lived transient. Only 5 minutes life span. If it exists, we do a soft flush before
     * deleting the transient to prevent subsequent flushes. The only times the transient gets created, is if
     * post types or taxonomies are created, updated, deleted, or imported. Any other time and this condition
     * should not be met.
     */
    if ('true' === ($flush_it = get_transient('taxopress_flush_rewrite_rules'))) {
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Necessary for custom taxonomy registration
        flush_rewrite_rules(false);
        // So we only run this once.
        delete_transient('taxopress_flush_rewrite_rules');
    }
}

/**
 * Return the current action being done within PublishPress Taxonomies context.
 *
 * @return string Current action being done by PublishPress Taxonomies
 */
function taxopress_get_current_action()
{
    $current_action = '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for display only, no state change
    if (!empty($_GET) && isset($_GET['action'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_action .= esc_textarea(sanitize_text_field(wp_unslash($_GET['action'])));
    }

    return $current_action;
}

/**
 * Return an array of all taxonomy slugs from Custom Post Type UI.
 *
 * @return array PublishPress Taxonomies taxonomy slugs.
 */
function taxopress_get_taxonomy_slugs()
{
    $taxonomies = get_option('taxopress_taxonomies');
    if (!empty($taxonomies)) {
        return array_keys($taxonomies);
    }

    return [];
}

/**
 * Return the appropriate admin URL depending on our context.
 *
 * @param string $path URL path.
 * @return string
 */
function taxopress_admin_url($path)
{
    if (is_multisite() && is_network_admin()) {
        return network_admin_url($path);
    }

    return admin_url($path);
}

/**
 * Construct action tag for `<form>` tag.
 *
 * @param object|string $ui PublishPress Taxonomies Admin UI instance. Optional. Default empty string.
 * @return string
 */
function taxopress_get_post_form_action($ui = '')
{
    /**
     * Filters the string to be used in an `action=""` attribute.
     */
    return apply_filters('taxopress_post_form_action', '', $ui);
}

/**
 * Display action tag for `<form>` tag.
 *
 * @param object $ui PublishPress Taxonomies Admin UI instance.
 */
function taxopress_post_form_action($ui)
{
    echo esc_attr(taxopress_get_post_form_action($ui));
}

/**
 * Fetch our PublishPress Taxonomies taxonomies option.
 *
 * @return mixed
 */
function taxopress_get_taxonomy_data()
{
    $data = apply_filters('taxopress_get_taxonomy_data', get_option('taxopress_taxonomies', []), get_current_blog_id());
    return is_array($data) ? $data : [];
}

/**
 * Fetch both internal and external edited PublishPress Taxonomies taxonomies
 *
 * @return mixed
 */
function taxopress_get_all_edited_taxonomy_data()
{
    $internal_taxonomies = (array) taxopress_get_taxonomy_data();
    $external_taxonomies = (array) taxopress_get_extername_taxonomy_data();

    $all_taxonomies = array_merge($internal_taxonomies, $external_taxonomies);

    return array_filter($all_taxonomies);
}




/**
 * Fetch our PublishPress Taxonomies taxonomies option.
 *
 * @return mixed
 */
function taxopress_get_extername_taxonomy_data()
{
    return array_filter((array)apply_filters(
        'taxopress_get_extername_taxonomy_data',
        get_option('taxopress_external_taxonomies', []),
        get_current_blog_id()
    ));
}


/**
 * Checks if a taxonomy is already registered.
 *
 * @param string $slug Taxonomy slug to check. Optional. Default empty string.
 * @param array|string $data Taxonomy data being utilized. Optional.
 *
 * @return mixed
 */
function taxopress_get_taxonomy_exists($slug = '', $data = [])
{

    /**
     * Filters the boolean value for if a taxonomy exists for 3rd parties.
     *
     * @param string $slug Taxonomy slug to check.
     * @param array|string $data Taxonomy data being utilized.
     */
    return apply_filters('taxopress_get_taxonomy_exists', taxonomy_exists($slug), $data);
}

/**
 * Secondary admin notices function for use with admin_notices hook.
 *
 * Constructs admin notice HTML.
 *
 * @param string $message Message to use in admin notice. Optional. Default empty string.
 * @param bool $success Whether or not a success. Optional. Default true.
 * @return mixed
 */
function taxopress_admin_notices_helper($message = '', $success = true)
{

    $class   = [];
    $class[] = $success ? 'updated' : 'error';
    $class[] = 'notice is-dismissible taxopress-notice';

    $messagewrapstart = '<div id="message" class="' . esc_attr(implode(' ', $class)) . '"><p>';

    $messagewrapend = '</p></div>';

    $action = '';

    /**
     * Filters the custom admin notice for PublishPress Taxonomies.
     *
     *
     * @param string $value Complete HTML output for notice.
     * @param string $action Action whose message is being generated.
     * @param string $message The message to be displayed.
     * @param string $messagewrapstart Beginning wrap HTML.
     * @param string $messagewrapend Ending wrap HTML.
     */
    return apply_filters(
        'taxopress_admin_notice',
        $messagewrapstart . $message . $messagewrapend,
        $action,
        $message,
        $messagewrapstart,
        $messagewrapend
    );
}

/**
 * Grab post type or taxonomy slug from $_POST global, if available.
 *
 * @return string
 * @internal
 *
 */
function taxopress_get_object_from_post_global()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Helper function called after nonce verification in parent handler
    if (isset($_POST['cpt_custom_post_type']['name'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return sanitize_text_field(wp_unslash($_POST['cpt_custom_post_type']['name']));
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Helper function called after nonce verification in parent handler
    if (isset($_POST['cpt_custom_tax']['name'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return sanitize_text_field(wp_unslash($_POST['cpt_custom_tax']['name']));
    }

    return esc_html__('Object', 'simple-tags');
}

/**
 * Successful add callback.
 */
function taxopress_add_success_admin_notice()
{
    //redirect to new taxonomy if success
    wp_safe_redirect(
        add_query_arg(
            [
                'page'               => 'st_taxonomies',
                'add'                => 'taxonomy',
                'action'             => 'edit',
                'taxopress_taxonomy' => taxopress_get_object_from_post_global(),
                'new_taxonomy'       => 1,
            ],
            taxopress_admin_url('admin.php')
        )
    );
    exit();
}

/**
 * Successful add callback.
 */
function taxopress_add_success_message_admin_notice()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for display only, no state change
    $taxonomy = isset($_GET['taxopress_taxonomy']) ? sanitize_text_field(wp_unslash($_GET['taxopress_taxonomy'])) : '';
    echo wp_kses_post(
        taxopress_admin_notices_helper(
            sprintf(
                esc_html__('%s has been successfully added', 'simple-tags'),
                $taxonomy
            )
        )
    );
}

/**
 * Fail to add callback.
 */
function taxopress_add_fail_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        sprintf(
            esc_html__('%s has failed to be added', 'simple-tags'),
            taxopress_get_object_from_post_global()
        ),
        false
    );
}

/**
 * Successful update callback.
 */
function taxopress_update_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        sprintf(
            esc_html__('%s has been successfully updated', 'simple-tags'),
            taxopress_get_object_from_post_global()
        )
    );
}

/**
 * Fail to update callback.
 */
function taxopress_update_fail_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        sprintf(
            esc_html__('%s has failed to be updated', 'simple-tags'),
            taxopress_get_object_from_post_global()
        ),
        false
    );
}

/**
 * Successful delete callback.
 */
function taxopress_delete_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        sprintf(
            esc_html__('%s has been successfully deleted', 'simple-tags'),
            taxopress_get_object_from_post_global()
        )
    );
}

/**
 * Fail to delete callback.
 */
function taxopress_delete_fail_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        sprintf(
            esc_html__('%s has failed to be deleted', 'simple-tags'),
            taxopress_get_object_from_post_global()
        ),
        false
    );
}


function taxopress_nonce_fail_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        esc_html__('Nonce failed verification', 'simple-tags'),
        false
    );
}

/**
 * Returns error message for if trying to register existing taxonomy.
 *
 * @return string
 */
function taxopress_slug_matches_taxonomy()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return sprintf(
        esc_html__('Please choose a different taxonomy name. %s is already registered.', 'simple-tags'),
        taxopress_get_object_from_post_global()
    );
}


/**
 * Returns error message for if not providing a post type to associate taxonomy to.
 *
 * @return string
 */
function taxopress_empty_cpt_on_taxonomy()
{
    return esc_html__('Please provide a post type to attach to.', 'simple-tags');
}

/**
 * Returns error message for if trying to register post type with matching page slug.
 *
 * @return string
 */
function taxopress_slug_matches_page()
{
    $slug         = taxopress_get_object_from_post_global();
    $matched_slug = get_page_by_path(
        taxopress_get_object_from_post_global()
    );
    if ($matched_slug instanceof WP_Post) {
        $slug = sprintf(
            '<a href="%s">%s</a>',
            get_edit_post_link($matched_slug->ID),
            taxopress_get_object_from_post_global()
        );
    }

    return sprintf(
        esc_html__(
            'Please choose a different post type name. %s matches an existing page slug, which can cause conflicts.',
            'simple-tags'
        ),
        $slug
    );
}

/**
 * Returns error message for if trying to use quotes in slugs or rewrite slugs.
 *
 * @return string
 */
function taxopress_slug_has_quotes()
{
    return sprintf(
        esc_html__('Please do not use quotes in post type/taxonomy names or rewrite slugs', 'simple-tags'),
        taxopress_get_object_from_post_global()
    );
}

/**
 * Error admin notice.
 */
function taxopress_error_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        apply_filters('taxopress_custom_error_message', ''),
        false
    );
}

/**
 * Returns saved values for single taxonomy from PublishPress Taxonomies settings.
 *
 * @param string $taxonomy Taxonomy to retrieve PublishPress Taxonomies object for.
 * @return string
 */
function taxopress_get_taxopress_taxonomy_object($taxonomy = '')
{
    $taxonomies = get_option('taxopress_taxonomies');

    // phpcs:ignore WordPressVIPMinimum.Functions.CheckReturnValue -- get_option returns false or array, checked before use
    if (is_array($taxonomies) && array_key_exists($taxonomy, $taxonomies)) {
        return $taxonomies[$taxonomy];
    }

    return '';
}


/**
 * Register our users' custom taxonomies.
 *
 * @internal
 */
function taxopress_create_custom_taxonomies()
{
    $taxes = get_option('taxopress_taxonomies');

    if (empty($taxes)) {
        return;
    }

    /**
     * Fires before the start of the taxonomy registrations.
     *
     * @param array $taxes Array of taxonomies to register.
     */
    do_action('taxopress_pre_register_taxonomies', $taxes);

    if (is_array($taxes)) {
        foreach ($taxes as $tax) {
            /**
             * Filters whether or not to skip registration of the current iterated taxonomy.
             *
             * Dynamic part of the filter name is the chosen taxonomy slug.
             *
             * @param bool $value Whether or not to skip the taxonomy.
             * @param array $tax Current taxonomy being registered.
             */
            if ((bool)apply_filters("taxopress_disable_{$tax['name']}_tax", false, $tax)) {
                continue;
            }

            /**
             * Filters whether or not to skip registration of the current iterated taxonomy.
             *
             * @param bool $value Whether or not to skip the taxonomy.
             * @param array $tax Current taxonomy being registered.
             */
            if ((bool)apply_filters('taxopress_disable_tax', false, $tax)) {
                continue;
            }

            taxopress_register_single_taxonomy($tax);
        }
    }

    /**
     * Fires after the completion of the taxonomy registrations.
     *
     * @param array $taxes Array of taxonomies registered.
     */
    do_action('taxopress_post_register_taxonomies', $taxes);
}

/**
 * Helper function to register the actual taxonomy.
 *
 * @param array $taxonomy Taxonomy array to register. Optional.
 * @return null Result of register_taxonomy.
 * @internal
 *
 */
function taxopress_register_single_taxonomy($taxonomy = [])
{
    $labels = [
        'name'          => $taxonomy['label'],
        'singular_name' => $taxonomy['singular_label'],
    ];

    $description = '';
    if (!empty($taxonomy['description'])) {
        $description = $taxonomy['description'];
    }

    $preserved        = taxopress_get_preserved_keys('taxonomies');
    $preserved_labels = taxopress_get_preserved_labels();
    foreach ($taxonomy['labels'] as $key => $label) {
        if (!empty($label)) {
            $labels[$key] = $label;
        } elseif (empty($label) && in_array($key, $preserved, true)) {
            $singular_or_plural = (in_array(
                $key,
                array_keys($preserved_labels['taxonomies']['plural'])
            )) ? 'plural' : 'singular';
            $label_plurality    = ('plural' === $singular_or_plural) ? $taxonomy['label'] : $taxonomy['singular_label'];
            $labels[$key]       = sprintf($preserved_labels['taxonomies'][$singular_or_plural][$key], $label_plurality);
        }
    }

    $rewrite = get_taxopress_disp_boolean($taxonomy['rewrite']);
    if (false !== get_taxopress_disp_boolean($taxonomy['rewrite'])) {
        $rewrite               = [];
        $rewrite['slug']       = !empty($taxonomy['rewrite_slug']) ? $taxonomy['rewrite_slug'] : $taxonomy['name'];
        $rewrite['with_front'] = true;
        if (isset($taxonomy['rewrite_withfront'])) {
            $rewrite['with_front'] = ('false' === taxopress_disp_boolean($taxonomy['rewrite_withfront'])) ? false : true;
        }
        $rewrite['hierarchical'] = false;
        if (isset($taxonomy['rewrite_hierarchical'])) {
            $rewrite['hierarchical'] = ('true' === taxopress_disp_boolean($taxonomy['rewrite_hierarchical'])) ? true : false;
        }
    }

    if (in_array($taxonomy['query_var'], ['true', 'false', '0', '1'], true)) {
        $taxonomy['query_var'] = get_taxopress_disp_boolean($taxonomy['query_var']);
    }
    if (true === $taxonomy['query_var'] && !empty($taxonomy['query_var_slug'])) {
        $taxonomy['query_var'] = $taxonomy['query_var_slug'];
    }

    $public             = (!empty($taxonomy['public']) && false === get_taxopress_disp_boolean($taxonomy['public'])) ? false : true;
    $publicly_queryable = (!empty($taxonomy['publicly_queryable']) && false === get_taxopress_disp_boolean($taxonomy['publicly_queryable'])) ? false : true;
    if (empty($taxonomy['publicly_queryable'])) {
        $publicly_queryable = $public;
    }

    $show_admin_column = (!empty($taxonomy['show_admin_column']) && false !== get_taxopress_disp_boolean($taxonomy['show_admin_column'])) ? true : false;

    $show_in_menu = (!empty($taxonomy['show_in_menu']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_menu'])) ? true : false;

    if (empty($taxonomy['show_in_menu'])) {
        $show_in_menu = get_taxopress_disp_boolean($taxonomy['show_ui']);
    }

    $show_in_nav_menus = (!empty($taxonomy['show_in_nav_menus']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_nav_menus'])) ? true : false;
    if (empty($taxonomy['show_in_nav_menus'])) {
        $show_in_nav_menus = $public;
    }

    $show_in_rest = (!empty($taxonomy['show_in_rest']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_rest'])) ? true : false;

    $show_in_quick_edit = (!empty($taxonomy['show_in_quick_edit']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_quick_edit'])) ? true : false;

    $show_in_filter = (!empty($taxonomy['show_in_filter']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_filter'])) ? true : false;

    $rest_base = null;
    if (!empty($taxonomy['rest_base'])) {
        $rest_base = $taxonomy['rest_base'];
    }

    $rest_controller_class = null;
    if (!empty($post_type['rest_controller_class'])) {
        $rest_controller_class = $post_type['rest_controller_class'];
    }

    $meta_box_cb = null;
    if (!empty($taxonomy['meta_box_cb'])) {
        $meta_box_cb = (false !== get_taxopress_disp_boolean($taxonomy['meta_box_cb'])) ? $taxonomy['meta_box_cb'] : false;
    }
    $default_term = null;
    if (!empty($taxonomy['default_term'])) {
        $term_parts = explode(',', $taxonomy['default_term']);
        $term_parts = array_filter($term_parts);
        if (!empty($term_parts)) {
            $default_term = [];
            foreach ($term_parts as $term_part) {
                $default_term[] = ['name' => $term_part, 'slug' => $term_part];
            }
        }
    }

    $args = [
        'labels'                => $labels,
        'label'                 => $taxonomy['label'],
        'description'           => $description,
        'public'                => $public,
        'publicly_queryable'    => $publicly_queryable,
        'hierarchical'          => get_taxopress_disp_boolean($taxonomy['hierarchical']),
        'show_ui'               => get_taxopress_disp_boolean($taxonomy['show_ui']),
        'show_in_menu'          => $show_in_menu,
        'show_in_nav_menus'     => $show_in_nav_menus,
        'query_var'             => $taxonomy['query_var'],
        'rewrite'               => $rewrite,
        'show_admin_column'     => $show_admin_column,
        'show_in_rest'          => $show_in_rest,
        'rest_base'             => $rest_base,
        'rest_controller_class' => $rest_controller_class,
        'show_in_quick_edit'    => $show_in_quick_edit,
        'show_in_filter'        => $show_in_filter,
        'meta_box_cb'           => $meta_box_cb,
        'default_term'          => $default_term,
    ];

    $object_type = !empty($taxonomy['object_types']) ? $taxonomy['object_types'] : '';

    /**
     * Filters the arguments used for a taxonomy right before registering.
     *
     * @param array $args Array of arguments to use for registering taxonomy.
     * @param string $value Taxonomy slug to be registered.
     * @param array $taxonomy Original passed in values for taxonomy.
     * @param array $object_type Array of chosen post types for the taxonomy.
     */
    $args = apply_filters('taxopress_pre_register_taxonomy', $args, $taxonomy['name'], $taxonomy, $object_type);

    return register_taxonomy($taxonomy['name'], $object_type, $args);
}


/**
 * Return a notice based on conditions.
 *
 * @param string $action The type of action that occurred. Optional. Default empty string.
 * @param string $object_type Whether it's from a post type or taxonomy. Optional. Default empty string.
 * @param bool $success Whether the action succeeded or not. Optional. Default true.
 * @param string $custom Custom message if necessary. Optional. Default empty string.
 * @return bool|string false on no message, else HTML div with our notice message.
 */
function taxopress_admin_notices($action = '', $object_type = '', $success = true, $custom = '')
{
    $class       = [];
    $class[]     = $success ? 'updated' : 'error';
    $class[]     = 'notice is-dismissible taxopress-notice';
    $object_type = esc_attr($object_type);

    $messagewrapstart = '<div id="message" class="' . implode(' ', $class) . '"><p>';
    $message          = '';

    $messagewrapend = '</p></div>';

    if ('add' === $action) {
        if ($success) {
            $message .= sprintf(__('%s has been successfully added', 'simple-tags'), $object_type);
        } else {
            $message .= sprintf(__('%s has failed to be added', 'simple-tags'), $object_type);
        }
    } elseif ('update' === $action) {
        if ($success) {
            $message .= sprintf(__('%s has been successfully updated', 'simple-tags'), $object_type);
        } else {
            $message .= sprintf(__('%s has failed to be updated', 'simple-tags'), $object_type);
        }
    } elseif ('delete' === $action) {
        if ($success) {
            $message .= sprintf(__('%s has been successfully deleted', 'simple-tags'), $object_type);
        } else {
            $message .= sprintf(__('%s has failed to be deleted', 'simple-tags'), $object_type);
        }
    } elseif ('import' === $action) {
        if ($success) {
            $message .= sprintf(__('%s has been successfully imported', 'simple-tags'), $object_type);
        } else {
            $message .= sprintf(__('%s has failed to be imported', 'simple-tags'), $object_type);
        }
    } elseif ('error' === $action) {
        if (!empty($custom)) {
            $message = $custom;
        }
    }

    if ($message) {

        /**
         * Filters the custom admin notice for PublishPress Taxonomies.
         *
         * @param string $value Complete HTML output for notice.
         * @param string $action Action whose message is being generated.
         * @param string $message The message to be displayed.
         * @param string $messagewrapstart Beginning wrap HTML.
         * @param string $messagewrapend Ending wrap HTML.
         */
        return apply_filters(
            'taxopress_admin_notice',
            $messagewrapstart . $message . $messagewrapend,
            $action,
            $message,
            $messagewrapstart,
            $messagewrapend
        );
    }

    return false;
}

/**
 * Return array of keys needing preserved.
 *
 * @param string $type Type to return. Either 'post_types' or 'taxonomies'. Optional. Default empty string.
 * @return array Array of keys needing preservered for the requested type.
 */
function taxopress_get_preserved_keys($type = '')
{
    $preserved_labels = [
        'post_types' => [
            'add_new_item',
            'edit_item',
            'new_item',
            'view_item',
            'view_items',
            'all_items',
            'search_items',
            'not_found',
            'not_found_in_trash',
        ],
        'taxonomies' => [
            'search_items',
            'popular_items',
            'all_items',
            'parent_item',
            'parent_item_colon',
            'edit_item',
            'update_item',
            'add_new_item',
            'new_item_name',
            'separate_items_with_commas',
            'add_or_remove_items',
            'choose_from_most_used',
        ],
    ];

    return !empty($type) ? $preserved_labels[$type] : [];
}

/**
 * Return label for the requested type and label key.
 *
 * @param string $type Type to return. Either 'post_types' or 'taxonomies'. Optional. Default empty string.
 * @param string $key Requested label key. Optional. Default empty string.
 * @param string $plural Plural verbiage for the requested label and type. Optional. Default empty string.
 * @param string $singular Singular verbiage for the requested label and type. Optional. Default empty string.
 * @return string Internationalized default label.
 * @deprecated
 *
 */
function taxopress_get_preserved_label($type = '', $key = '', $plural = '', $singular = '')
{
    $preserved_labels = [
        'post_types' => [
            'add_new_item'       => sprintf(__('Add new %s', 'simple-tags'), $singular),
            'edit_item'          => sprintf(__('Edit %s', 'simple-tags'), $singular),
            'new_item'           => sprintf(__('New %s', 'simple-tags'), $singular),
            'view_item'          => sprintf(__('View %s', 'simple-tags'), $singular),
            'view_items'         => sprintf(__('View %s', 'simple-tags'), $plural),
            'all_items'          => sprintf(__('All %s', 'simple-tags'), $plural),
            'search_items'       => sprintf(__('Search %s', 'simple-tags'), $plural),
            'not_found'          => sprintf(__('No %s found.', 'simple-tags'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in trash.', 'simple-tags'), $plural),
        ],
        'taxonomies' => [
            'search_items'               => sprintf(__('Search %s', 'simple-tags'), $plural),
            'popular_items'              => sprintf(__('Popular %s', 'simple-tags'), $plural),
            'all_items'                  => sprintf(__('All %s', 'simple-tags'), $plural),
            'parent_item'                => sprintf(__('Parent %s', 'simple-tags'), $singular),
            'parent_item_colon'          => sprintf(__('Parent %s:', 'simple-tags'), $singular),
            'edit_item'                  => sprintf(__('Edit %s', 'simple-tags'), $singular),
            'update_item'                => sprintf(__('Update %s', 'simple-tags'), $singular),
            'add_new_item'               => sprintf(__('Add new %s', 'simple-tags'), $singular),
            'new_item_name'              => sprintf(__('New %s name', 'simple-tags'), $singular),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'simple-tags'), $plural),
            'add_or_remove_items'        => sprintf(__('Add or remove %s', 'simple-tags'), $plural),
            'choose_from_most_used'      => sprintf(__('Choose from the most used %s', 'simple-tags'), $plural),
        ],
    ];

    return $preserved_labels[$type][$key];
}

/**
 * Returns an array of translated labels, ready for use with sprintf().
 *
 * Replacement for taxopress_get_preserved_label for the sake of performance.
 *
 * @return array
 */
function taxopress_get_preserved_labels()
{
    return [
        'post_types' => [
            'singular' => [
                'add_new_item' => esc_html__('Add new %s', 'simple-tags'),
                'edit_item'    => esc_html__('Edit %s', 'simple-tags'),
                'new_item'     => esc_html__('New %s', 'simple-tags'),
                'view_item'    => esc_html__('View %s', 'simple-tags'),
            ],
            'plural'   => [
                'view_items'         => esc_html__('View %s', 'simple-tags'),
                'all_items'          => esc_html__('All %s', 'simple-tags'),
                'search_items'       => esc_html__('Search %s', 'simple-tags'),
                'not_found'          => esc_html__('No %s found.', 'simple-tags'),
                'not_found_in_trash' => esc_html__('No %s found in trash.', 'simple-tags'),
            ],
        ],
        'taxonomies' => [
            'singular' => [
                'parent_item'       => esc_html__('Parent %s', 'simple-tags'),
                'parent_item_colon' => esc_html__('Parent %s:', 'simple-tags'),
                'edit_item'         => esc_html__('Edit %s', 'simple-tags'),
                'update_item'       => esc_html__('Update %s', 'simple-tags'),
                'add_new_item'      => esc_html__('Add new %s', 'simple-tags'),
                'new_item_name'     => esc_html__('New %s name', 'simple-tags'),
            ],
            'plural'   => [
                'search_items'               => esc_html__('Search %s', 'simple-tags'),
                'popular_items'              => esc_html__('Popular %s', 'simple-tags'),
                'all_items'                  => esc_html__('All %s', 'simple-tags'),
                'separate_items_with_commas' => esc_html__('Separate %s with commas', 'simple-tags'),
                'add_or_remove_items'        => esc_html__('Add or remove %s', 'simple-tags'),
                'choose_from_most_used'      => esc_html__('Choose from the most used %s', 'simple-tags'),
            ],
        ],
    ];
}


function get_all_taxopress_taxonomies_request()
{

    $category              = get_taxonomies(
        ['name' => 'category'],
        'objects'
    );
    $post_tag              = get_taxonomies(
        ['name' => 'post_tag'],
        'objects'
    );

    $public  = get_taxonomies(['public' => true], 'objects');
    $private = get_taxonomies(['public' => false], 'objects');

    if (!array_key_exists('category', $public) && !array_key_exists('category', $public)) {
        $public = array_merge($category, $public);
    }
    if (!array_key_exists('post_tag', $public) && !array_key_exists('post_tag', $public)) {
        $public = array_merge($post_tag, $public);
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for filtering display, no state change
    if (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'all') {
        $registered_taxonomies = array_merge($public, $private);
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    } elseif (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'private') {
        $registered_taxonomies = $private;
    } else {
        $registered_taxonomies = $public;
    }

    return $registered_taxonomies;
}


function get_all_taxopress_taxonomies()
{

    $registered_taxonomies = get_taxonomies([], 'objects');

    return $registered_taxonomies;
}


function get_all_taxopress_public_taxonomies()
{
    return get_taxonomies(['public' => true], 'objects');
}

/**
 * Return an array of all deactivated taxonomy.
 *
 * @return array PublishPress Taxonomies taxonomy.
 */
function taxopress_get_deactivated_taxonomy()
{
    $taxonomies = get_option('taxopress_deactivated_taxonomies');
    if (!empty($taxonomies)) {
        return (array)$taxonomies;
    }

    return [];
}

/**
 * None callback.
 */
function taxopress_noaction_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        esc_html__('Kindly select an action in bulk action dropdown!', 'simple-tags'),
        false
    );
}

/**
 * None callback.
 */
function taxopress_none_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(
        esc_html__('Kindly select atleast one taxonomy to proceed', 'simple-tags'),
        false
    );
}

/**
 * Deactivated callback.
 */
function taxopress_deactivated_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Taxonomy has been successfully deactivated', 'simple-tags'));
}

/**
 * Activated callback.
 */
function taxopress_activated_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Taxonomy has been successfully activated', 'simple-tags'));
}

/**
 * Delete callback.
 */
function taxopress_taxdeleted_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Taxonomy has been successfully deleted', 'simple-tags'));
}

/**
 * Deactivated taxonomy.
 *
 * @return array PublishPress Taxonomies taxonomy.
 */
function taxopress_deactivate_taxonomy($term_object)
{
    $all_taxonomies   = (array)get_option('taxopress_deactivated_taxonomies');
    $all_taxonomies[] = $term_object;
    $all_taxonomies   = array_unique(array_filter($all_taxonomies));
    $success          = update_option('taxopress_deactivated_taxonomies', $all_taxonomies);
}

/**
 * Activate taxonomy.
 *
 * @return array PublishPress Taxonomies taxonomy.
 */
function taxopress_activate_taxonomy($term_object)
{
    $all_taxonomies = (array)get_option('taxopress_deactivated_taxonomies');
    if (($key = array_search($term_object, $all_taxonomies)) !== false) {
        unset($all_taxonomies[$key]);
        $success = update_option('taxopress_deactivated_taxonomies', $all_taxonomies);
    }
}


/**
 * Fetch our PublishPress Taxonomies disabled taxonomies option.
 *
 * @return mixed
 */
function taxopress_get_deactivated_taxonomy_data()
{
    return apply_filters(
        'taxopress_get_deactivated_taxonomy_data',
        get_option('taxopress_deactivated_taxonomies', []),
        get_current_blog_id()
    );
}


/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'action',
        'taxonomy',
        '_wpnonce',
    ]);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_filter_removable_query_args_2(array $args)
{
    return array_merge($args, [
        'action2',
        'taxonomy',
        '_wpnonce',
    ]);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_filter_removable_query_args_3(array $args)
{
    return array_merge($args, [
        'new_taxonomy',
    ]);
}


/**
 * Delete our custom taxonomy from the array of taxonomies.
 * @return bool|string False on failure, string on success.
 */
function taxopress_action_delete_taxonomy($term_object)
{

    $data = [
        'cpt_custom_tax' => [
            'name' => $term_object,
        ],
    ];
    // Check if they selected one to delete.
    if (empty($data['cpt_custom_tax']['name'])) {
        return taxopress_admin_notices(
            'error',
            '',
            false,
            esc_html__('Please provide a taxonomy to delete', 'simple-tags')
        );
    }

    /**
     * Fires before a taxonomy is deleted from our saved options.
     *
     *
     * @param array $data Array of taxonomy data we are deleting.
     */
    do_action('taxopress_before_delete_taxonomy', $data);

    $taxonomies = taxopress_get_taxonomy_data();

    if (array_key_exists(strtolower($data['cpt_custom_tax']['name']), $taxonomies)) {
        unset($taxonomies[$data['cpt_custom_tax']['name']]);

        /**
         * Filters whether or not 3rd party options were saved successfully within taxonomy deletion.
         *
         * @param bool $value Whether or not someone else saved successfully. Default false.
         * @param array $taxonomies Array of our updated taxonomies data.
         * @param array $data Array of submitted taxonomy to update.
         */
        if (false === ($success = apply_filters('taxopress_taxonomy_delete_tax', false, $taxonomies, $data))) {
            $success = update_option('taxopress_taxonomies', $taxonomies);
        }
    }
    // phpcs:ignore WordPressVIPMinimum.Performance.TaxonomyMetaInOptions.PossibleTermMetaInOptions -- Default term stored in options for compatibility
    delete_option("default_term_{$data['cpt_custom_tax']['name']}");

    /**
     * Fires after a taxonomy is deleted from our saved options.
     *
     *
     * @param array $data Array of taxonomy data that was deleted.
     */
    do_action('taxopress_after_delete_taxonomy', $data);

    // Used to help flush rewrite rules on init.
    set_transient('taxopress_flush_rewrite_rules', 'true', 5 * 60);

    if (isset($success)) {
        add_action('admin_notices', "taxopress_taxdeleted_admin_notice");

        return 'delete_success';
    }

    add_action('admin_notices', "taxopress_delete_fail_admin_notice");

    return 'delete_fail';
}

function unregister_tags()
{
    global $remove_current_taxonomy;

    $all_taxonomies = (array)get_option('taxopress_deactivated_taxonomies');
    $all_taxonomies = array_unique(array_filter($all_taxonomies));

    if (count($all_taxonomies) > 0) {
        foreach ($all_taxonomies as $taxonomy) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for page check, no state change
            if (!empty($_GET) && isset($_GET['page']) && 'st_taxonomies' == $_GET['page']) {
                $remove_current_taxonomy = $taxonomy;
                add_action('admin_menu', 'taxopress_remove_taxonomy_from_menus');
            } else {
                taxopress_unregister_taxonomy($taxonomy);
            }
        }
    }
}

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Reference comment for context
// Remove menu
function taxopress_remove_taxonomy_from_menus()
{
    global $remove_current_taxonomy;
    remove_menu_page('edit-tags.php?taxonomy=' . $remove_current_taxonomy);
}


function taxopress_unregister_taxonomy($taxonomy)
{
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', esc_html__('Invalid taxonomy.'));
    }

    $taxonomy_object = get_taxonomy($taxonomy);

    // Do not allow unregistering internal taxonomies.
    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Legacy validation logic kept for reference
    /*if ( $taxonomy_object->_builtin ) {
        return new WP_Error( 'invalid_taxonomy', esc_html__( 'Unregistering a built-in taxonomy is not allowed.' ) );
    }*/

    global $wp_taxonomies;

    // Instead of removing totally, keep minimal structure but disable functionality
    if (isset($wp_taxonomies[$taxonomy])) {
        $wp_taxonomies[$taxonomy]->public = false;
        $wp_taxonomies[$taxonomy]->show_ui = false;
        $wp_taxonomies[$taxonomy]->show_in_menu = false;
        $wp_taxonomies[$taxonomy]->show_in_nav_menus = false;
        $wp_taxonomies[$taxonomy]->show_in_rest = false;
        $wp_taxonomies[$taxonomy]->show_tagcloud = false;
        $wp_taxonomies[$taxonomy]->show_in_quick_edit = false;
        $wp_taxonomies[$taxonomy]->show_admin_column = false;
        $wp_taxonomies[$taxonomy]->query_var = false;

        // Remove rewrite rules
        if ($taxonomy_object) {
            $taxonomy_object->remove_rewrite_rules();
            $taxonomy_object->remove_hooks();
        }

        // Remove custom taxonomy default term option.
        if (!empty($taxonomy_object->default_term)) {
            delete_option('default_term_' . $taxonomy_object->name);
        }
    }

    /**
     * Fires after a taxonomy is unregistered.
     *
     * @param string $taxonomy Taxonomy name.
     */
    do_action('unregistered_taxonomy', $taxonomy);

    return true;
}


function taxopress_convert_external_taxonomy($taxonomy_object, $request_tax)
{

    if (array_key_exists($request_tax, taxopress_get_extername_taxonomy_data())) {
        return taxopress_get_extername_taxonomy_data()[$request_tax];
    }

    $taxonomy_data = (array)$taxonomy_object;

    foreach ($taxonomy_data as $key => $value) {
        //change label to array
        if ($key === 'labels') {
            $taxonomy_data[$key] = (array)$value;
        }
        //change cap to array
        if ($key === 'cap') {
            $taxonomy_data[$key] = (array)$value;
        }
        //change default terms to strings
        if ($key === 'default_term') {
            if (is_array($value) && count($value) > 0) {
                $taxonomy_data[$key] = join(',', array_filter($value));
            }
        }
        //set query var value if not empty
        if ($key === 'query_var') {
            if (empty(trim($value))) {
                $taxonomy_data['query_var']      = 'false';
                $taxonomy_data['query_var_slug'] = '';
            } else {
                $taxonomy_data['query_var']      = 'true';
                $taxonomy_data['query_var_slug'] = $value;
            }
        }
        //set rewrite
        if ($key === 'rewrite') {
            if (!empty($value) && is_array($value)) {
                if (count($value) > 0) {
                    foreach ($value as $holdkey => $holdvalue) {
                        if ($holdkey === 'with_front') {
                            $taxonomy_data['rewrite_withfront'] = is_bool($holdvalue) ? taxopress_disp_boolean($holdvalue) : $holdvalue;
                        } else {
                            $taxonomy_data[$key . '_' . $holdkey] = is_bool($holdvalue) ? taxopress_disp_boolean($holdvalue) : $holdvalue;
                        }
                    }
                }
                $taxonomy_data[$key] = (count($value) > 0) ? 'true' : 'false';
            } else {
                $taxonomy_data[$key]                   = 'false';
                $taxonomy_data['rewrite_hierarchical'] = '';
                $taxonomy_data['rewrite_withfront']    = '';
            }
        }
        //dispose bool value
        if (is_bool($value)) {
            $taxonomy_data[$key] = taxopress_disp_boolean($value);
        }
    }
    //add singular label
    $taxonomy_data['singular_label'] = $taxonomy_data['labels']['singular_name'];
    //add object terms
    $taxonomy_data['object_types'] = $taxonomy_data['object_type'];

    return $taxonomy_data;
}

/**
 * Register our users' custom taxonomies.
 *
 * @internal
 */
function taxopress_recreate_custom_taxonomies()
{
    $taxes = taxopress_get_extername_taxonomy_data();

    if (empty($taxes)) {
        return;
    }
    /**
     * Fires before the start of the taxonomy registrations.
     *
     * @param array $taxes Array of taxonomies to register.
     */
    do_action('taxopress_pre_register_taxonomies', $taxes);

    if (is_array($taxes)) {
        foreach ($taxes as $tax) {
            if ($tax['name'] === 'media_tag' && (int)get_option('taxopress_media_tag_deleted') > 0) {
                continue;
            }

            taxopress_re_register_single_taxonomy($tax);
        }
    }

    /**
     * Fires after the completion of the taxonomy registrations.
     *
     * @param array $taxes Array of taxonomies registered.
     */
    do_action('taxopress_post_register_taxonomies', $taxes);
}

/**
 * Helper function to register the actual taxonomy.
 *
 * @param array $taxonomy Taxonomy array to register. Optional.
 * @return null Result of register_taxonomy.
 * @internal
 *
 */
function taxopress_re_register_single_taxonomy($taxonomy)
{

    $labels = [
        'name'          => $taxonomy['label'],
        'singular_name' => $taxonomy['singular_label'],
    ];

    $description = '';
    if (!empty($taxonomy['description'])) {
        $description = $taxonomy['description'];
    }

    $preserved        = taxopress_get_preserved_keys('taxonomies');
    $preserved_labels = taxopress_get_preserved_labels();
    foreach ($taxonomy['labels'] as $key => $label) {
        if (!empty($label)) {
            $labels[$key] = $label;
        } elseif (empty($label) && in_array($key, $preserved, true)) {
            $singular_or_plural = (in_array(
                $key,
                array_keys($preserved_labels['taxonomies']['plural'])
            )) ? 'plural' : 'singular';
            $label_plurality    = ('plural' === $singular_or_plural) ? $taxonomy['label'] : $taxonomy['singular_label'];
            $labels[$key]       = sprintf($preserved_labels['taxonomies'][$singular_or_plural][$key], $label_plurality);
        }
    }

    $rewrite = get_taxopress_disp_boolean($taxonomy['rewrite']);
    if (false !== get_taxopress_disp_boolean($taxonomy['rewrite'])) {
        $rewrite               = [];
        $rewrite['slug']       = !empty($taxonomy['rewrite_slug']) ? $taxonomy['rewrite_slug'] : $taxonomy['name'];
        $rewrite['with_front'] = true;
        if (isset($taxonomy['rewrite_withfront'])) {
            $rewrite['with_front'] = ('false' === taxopress_disp_boolean($taxonomy['rewrite_withfront'])) ? false : true;
        }
        $rewrite['hierarchical'] = false;
        if (isset($taxonomy['rewrite_hierarchical'])) {
            $rewrite['hierarchical'] = ('true' === taxopress_disp_boolean($taxonomy['rewrite_hierarchical'])) ? true : false;
        }
    }

    if (in_array($taxonomy['query_var'], ['true', 'false', '0', '1'], true)) {
        $taxonomy['query_var'] = get_taxopress_disp_boolean($taxonomy['query_var']);
    }
    if (true === $taxonomy['query_var'] && !empty($taxonomy['query_var_slug'])) {
        $taxonomy['query_var'] = $taxonomy['query_var_slug'];
    }

    $public             = (!empty($taxonomy['public']) && false === get_taxopress_disp_boolean($taxonomy['public'])) ? false : true;
    $publicly_queryable = (!empty($taxonomy['publicly_queryable']) && false === get_taxopress_disp_boolean($taxonomy['publicly_queryable'])) ? false : true;
    if (empty($taxonomy['publicly_queryable'])) {
        $publicly_queryable = $public;
    }

    $show_admin_column = (!empty($taxonomy['show_admin_column']) && false !== get_taxopress_disp_boolean($taxonomy['show_admin_column'])) ? true : false;

    $show_in_menu = (!empty($taxonomy['show_in_menu']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_menu'])) ? true : false;

    if (empty($taxonomy['show_in_menu'])) {
        $show_in_menu = get_taxopress_disp_boolean($taxonomy['show_ui']);
    }

    $show_in_nav_menus = (!empty($taxonomy['show_in_nav_menus']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_nav_menus'])) ? true : false;
    if (empty($taxonomy['show_in_nav_menus'])) {
        $show_in_nav_menus = $public;
    }

    $show_in_rest = (!empty($taxonomy['show_in_rest']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_rest'])) ? true : false;

    $show_in_quick_edit = (!empty($taxonomy['show_in_quick_edit']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_quick_edit'])) ? true : false;

    $show_in_filter = (!empty($taxonomy['show_in_filter']) && false !== get_taxopress_disp_boolean($taxonomy['show_in_filter'])) ? true : false;

    $rest_base = null;
    if (!empty($taxonomy['rest_base'])) {
        $rest_base = $taxonomy['rest_base'];
    }

    $rest_controller_class = null;
    if (!empty($post_type['rest_controller_class'])) {
        $rest_controller_class = $post_type['rest_controller_class'];
    }

    $meta_box_cb = null;
    if (!empty($taxonomy['meta_box_cb'])) {
        $meta_box_cb = (false !== get_taxopress_disp_boolean($taxonomy['meta_box_cb'])) ? $taxonomy['meta_box_cb'] : false;
    }
    $default_term = null;

    if (!empty($taxonomy['default_term'])) {
        $term_parts = explode(',', $taxonomy['default_term']);
        $term_parts = array_filter($term_parts);
        if (!empty($term_parts)) {
            $default_term = [];
            foreach ($term_parts as $term_part) {
                $default_term[] = ['name' => $term_part, 'slug' => $term_part];
            }
        }
    }

    $args = [
        'labels'                => $labels,
        'label'                 => $taxonomy['label'],
        'description'           => $description,
        'public'                => $public,
        'publicly_queryable'    => $publicly_queryable,
        'hierarchical'          => get_taxopress_disp_boolean($taxonomy['hierarchical']),
        'show_ui'               => get_taxopress_disp_boolean($taxonomy['show_ui']),
        'show_in_menu'          => $show_in_menu,
        'show_in_nav_menus'     => $show_in_nav_menus,
        'query_var'             => $taxonomy['query_var'],
        'rewrite'               => $rewrite,
        'show_admin_column'     => $show_admin_column,
        'show_in_rest'          => $show_in_rest,
        'rest_base'             => $rest_base,
        'rest_controller_class' => $rest_controller_class,
        'show_in_quick_edit'    => $show_in_quick_edit,
        'show_in_filter'        => $show_in_filter,
        'meta_box_cb'           => $meta_box_cb,
        'default_term'          => $default_term,
    ];

    $object_type = !empty($taxonomy['object_types']) ? $taxonomy['object_types'] : '';

    /**
     * Filters the arguments used for a taxonomy right before registering.
     *
     * @param array $args Array of arguments to use for registering taxonomy.
     * @param string $value Taxonomy slug to be registered.
     * @param array $taxonomy Original passed in values for taxonomy.
     * @param array $object_type Array of chosen post types for the taxonomy.
     */
    $args = apply_filters('taxopress_pre_register_taxonomy', $args, $taxonomy['name'], $taxonomy, $object_type);

    return register_taxonomy($taxonomy['name'], $object_type, $args);
}

/**
 * Set post taxonomy default term
 *
 * @param integer $post_id
 * @param object $post
 * @return void
 */
function taxopress_set_default_taxonomy_terms($post_id, $post)
{
    if ('auto-draft' === $post->post_status) {
        $taxonomies = get_object_taxonomies($post->post_type, 'object');
        foreach ($taxonomies as $taxonomy => $tax_object) {
            if (!empty($tax_object->default_term)) {
                if (is_array($tax_object->default_term)) {
                    $new_terms = [];
                    foreach ($tax_object->default_term as $term => $option) {
                        if (is_array($option) && isset($option['name'])) {
                            $new_terms[] = trim($option['name']);
                        }
                    }
                    if (!empty($new_terms)) {
                        wp_set_object_terms($post_id, $new_terms, $taxonomy, true);
                    }
                }
            }
        }
    }
}

function taxopress_show_all_cpt_in_archive_result($request_tax)
{

    $taxonomies = taxopress_get_taxonomy_data();

    $current = false;
    if ($request_tax && is_array($taxonomies) && array_key_exists($request_tax, $taxonomies)) {
        $current       = $taxonomies[$request_tax];
    } elseif (taxonomy_exists($request_tax)) {
        //not out taxonomy
        $external_taxonomy = get_taxonomies(['name' => $request_tax], 'objects');
        if (isset($external_taxonomy) > 0) {
            $current       = taxopress_convert_external_taxonomy(
                $external_taxonomy[$request_tax],
                $request_tax
            );
        }
    }

    $status = isset($current) && isset($current['include_in_result']) ? get_taxopress_disp_boolean($current['include_in_result']) : false;

    return $status;
}

/**
 * Filter the dropdown cats to remove the value="0" to solve issue with filter when
 * tag=0 https://github.com/publishpress/publishpress-taxonomies/issues/2372
 * @param string $output
 * @return string
 */
function taxopress_filter_dropdown_cats($output)
{

    if (strpos($output, 'taxopress-select2-term-filter') !== false) {
        $output = str_replace(['value="0"', "value='0'"], ['value=""', "value=''"], $output);
    }

    return $output;
}

/* Show taxonomy filter on post list */
function taxopress_filter_dropdown($taxonomy, $show_filter)
{

    $show_filter   = get_taxopress_disp_boolean($show_filter);

    if ($show_filter == true) {
        wp_dropdown_categories(
            array(
                'show_option_all' => sprintf(__('All %s', 'simple-tags'), $taxonomy->label),
                'orderby'         => 'name',
                'order'           => 'ASC',
                'hide_empty'      => false,
                'hide_if_empty'   => true,
                // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions.RestrictedFilter -- Using FILTER_UNSAFE_RAW intentionally to preserve user-supplied query var format
                'selected'        => sanitize_text_field(filter_input(INPUT_GET, $taxonomy->query_var, FILTER_UNSAFE_RAW)),
                'hierarchical'    => true,
                'name'            => $taxonomy->query_var,
                'taxonomy'        => $taxonomy->name,
                'value_field'     => 'slug',
                'id'              => $taxonomy->name,
                'class'           => 'taxopress-select2-term-filter'
            )
        );
    }
}

function taxopress_get_all_taxonomies()
{
    $custom_taxonomies = get_taxonomies(['_builtin' => false], 'objects');
    $builtin_taxonomies = get_taxonomies(['_builtin' => true], 'objects');
    return array_merge($custom_taxonomies, $builtin_taxonomies);
}

function taxopress_get_dropdown()
{

    global $pagenow, $typenow;

    if (is_admin()) {
        $type = 'post';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for post type filtering, no state change
        if (isset($_GET['post_type'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $type = sanitize_text_field(wp_unslash($_GET['post_type']));
        }

        $taxonomies = taxopress_get_all_edited_taxonomy_data();

        if (!empty($taxonomies)) {
            $all_taxonomies    = taxopress_get_all_taxonomies();

            foreach ($all_taxonomies as $taxonomy) {
                $taxonomy_name = $taxonomy->name;

                if (array_key_exists($taxonomy_name, $taxonomies)) {
                    $current = isset($taxonomies[ $taxonomy_name ]) ? $taxonomies[ $taxonomy_name ] : '';

                    if (is_array($current) && array_key_exists('show_in_filter', $current)) {
                        if (isset($current['object_types']) && !empty($current['object_types'])) {
                            foreach ($current['object_types'] as $object_type) {
                                //Media Page
                                if ($pagenow === 'upload.php') {
                                    if ($object_type == "attachment") {
                                        taxopress_filter_dropdown($taxonomy, $current['show_in_filter']);
                                    }
                                } else {
                                    if ($object_type == $type) {
                                        taxopress_filter_dropdown($taxonomy, $current['show_in_filter']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Helper to sort terms based on PublishPress Taxonomies settings.
 */
function taxopress_sort_terms_by_settings($terms, $taxonomy, $settings = [], $is_admin = false)
{
    static $custom_orders_cache = [];

    if (!is_array($terms) || empty($terms) || empty($taxonomy)) {
        return $terms;
    }

    // Default fallback settings
    $valid_orderby = ['name', 'term_id', 'ID', 'count', 'random', 'taxopress_term_order'];
    $valid_order = ['asc', 'desc'];
    $default_orderby = 'name';
    $default_order = 'asc';

    $orderby_setting = isset($settings['orderby']) && in_array($settings['orderby'], $valid_orderby, true)
        ? $settings['orderby']
        : $default_orderby;

    $order_setting = isset($settings['order']) && in_array(strtolower($settings['order']), $valid_order, true)
        ? strtolower($settings['order'])
        : $default_order;

    // Custom order logic
    if ($orderby_setting === 'taxopress_term_order') {
        if (!isset($custom_orders_cache[$taxonomy])) {
            $custom_orders_cache[$taxonomy] = get_option('taxopress_term_order_' . $taxonomy, []);
        }
        $custom_order = $custom_orders_cache[$taxonomy];
        if (!empty($custom_order)) {
            $terms_by_id = [];
            foreach ($terms as $term) {
                if (is_object($term) && isset($term->term_id)) {
                    $terms_by_id[$term->term_id] = $term;
                }
            }
            $ordered_terms = [];
            foreach ($custom_order as $term_id) {
                if (isset($terms_by_id[$term_id])) {
                    $ordered_terms[] = $terms_by_id[$term_id];
                    unset($terms_by_id[$term_id]);
                }
            }
            // Append missing terms
            foreach ($terms_by_id as $term) {
                $ordered_terms[] = $term;
            }

            if (count($ordered_terms) !== count($terms)) {
                foreach ($terms as $term) {
                    if (!in_array($term, $ordered_terms, true)) {
                        $ordered_terms[] = $term;
                    }
                }
            }

            return $order_setting === 'desc' ? array_reverse($ordered_terms) : $ordered_terms;
        }
    }

    // Built-in sorting fallbacks
    usort($terms, function ($a, $b) use ($orderby_setting, $order_setting) {
        $get = fn ($term, $key) => is_object($term) && isset($term->$key) ? $term->$key : null;

        switch ($orderby_setting) {
            case 'term_id':
            case 'ID':
                return ($order_setting === 'desc' ? -1 : 1) * ((int) $get($a, 'term_id') - (int) $get($b, 'term_id'));

            case 'count':
                return ($order_setting === 'desc' ? -1 : 1) * ((int) $get($a, 'count') - (int) $get($b, 'count'));

            case 'name':
                $a_name = (string) ($get($a, 'name') ?? '');
                $b_name = (string) ($get($b, 'name') ?? '');
                return ($order_setting === 'desc')
                    ? strcasecmp($b_name, $a_name)
                    : strcasecmp($a_name, $b_name);
        }

        return 0;
    });


    if ($orderby_setting === 'random') {
        shuffle($terms);
    }

    return $terms;
}

function taxopress_get_terms_args($args, $taxonomies)
{
    if (!is_admin()) {
        return $args;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit-tags') {
        return $args;
    }

    $tax = is_array($taxonomies) ? reset($taxonomies) : $taxonomies;
    if (!$tax) {
        return $args;
    }

    $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();
    $settings = $taxonomy_settings[$tax] ?? [];

    // Only run if enabled
    if (!isset($settings['enable_taxopress_ordering']) || empty($settings['enable_taxopress_ordering'])) {
        return $args;
    }

    // Validate/sanitize
    $valid_orderby = ['name', 'term_id', 'ID', 'count', 'random', 'taxopress_term_order'];
    $valid_order = ['asc', 'desc'];

    $args['orderby'] = isset($settings['orderby']) && in_array($settings['orderby'], $valid_orderby, true)
        ? $settings['orderby']
        : 'name';

    $args['order'] = isset($settings['order']) && in_array(strtolower($settings['order']), $valid_order, true)
        ? strtolower($settings['order'])
        : 'asc';

    return $args;
}

function taxopress_filter_terms($terms, $taxonomies, $args, $term_query)
{
    $tax = is_array($taxonomies) ? reset($taxonomies) : $taxonomies;
    if (!$tax || empty($terms)) {
        return $terms;
    }

    $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();
    $settings = $taxonomy_settings[$tax] ?? [];

    // Only run if enabled
    if (!isset($settings['enable_taxopress_ordering']) || empty($settings['enable_taxopress_ordering'])) {
        return $terms;
    }

    return taxopress_sort_terms_by_settings($terms, $tax, $settings, true);
}

function taxopress_terms_order_frontend($terms, $post_id, $taxonomy)
{
    if (!is_array($terms) || empty($terms) || empty($taxonomy)) {
        return $terms;
    }

    $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();
    $settings = $taxonomy_settings[$taxonomy] ?? [];

    //Only run if enabled
    if (!isset($settings['enable_taxopress_ordering']) || empty($settings['enable_taxopress_ordering'])) {
        return $terms;
    }

    return taxopress_sort_terms_by_settings($terms, $taxonomy, $settings, false);
}
