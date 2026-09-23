<?php

/**
 * Generate a unique term slug should user decide to copy same term more than once.
 *
 * @param string $slug The initial slug to check
 * @param string $taxonomy The taxonomy to check against
 * @return string Unique slug
 */
function taxopress_get_unique_term_slug($slug, $taxonomy)
{
    $original_slug = $slug;
    $count = 1;

    while (term_exists($slug, $taxonomy)) {
        $slug = $original_slug . '-' . $count;
        $count++;
    }

    return $slug;
}

/**
 * Check the current user's native capabilities for a taxonomy operation.
 *
 * @param string       $taxonomy   Taxonomy name.
 * @param string|array $capabilities Taxonomy capability property or properties.
 * @return bool
 */
function taxopress_current_user_can_for_taxonomy($taxonomy, $capabilities)
{
    $taxonomy_object = get_taxonomy(sanitize_key($taxonomy));
    if (!$taxonomy_object) {
        return false;
    }

    foreach ((array) $capabilities as $capability) {
        if (
            empty($taxonomy_object->cap->{$capability})
            || !current_user_can($taxonomy_object->cap->{$capability})
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Fetch post IDs for Terms screen bulk/row actions without hydrating WP_Post objects.
 *
 * @param array $args Optional WP_Query arguments.
 * @return int[]
 */
function taxopress_get_post_ids_for_terms_action($args = [])
{
    $batch_size = 500;
    $defaults = [
        'post_type'              => 'any',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    $post_ids = [];
    $page = 1;

    do {
        $query_args = array_merge(
            $defaults,
            $args,
            [
                'posts_per_page' => $batch_size,
                'paged'          => $page,
            ]
        );
        $batch_post_ids = get_posts($query_args);

        foreach ($batch_post_ids as $post_id) {
            $post_ids[] = (int) $post_id;
        }

        $page++;
    } while (count($batch_post_ids) === $batch_size);

    return $post_ids;
}

/**
 * Preserve the current Terms screen filters when row actions redirect/reload.
 *
 * @param array $extra Extra query arguments.
 * @return array
 */
function taxopress_get_terms_screen_query_args($extra = [])
{
    $preserve_keys = [
        'terms_filter_post_type',
        'terms_filter_taxonomy',
        'taxonomy_type',
        'taxopress_terms_taxonomy',
        'taxopress_show_all',
        'orderby',
        'order',
        'paged',
        's',
    ];

    $query_args = ['page' => 'st_terms'];

    foreach ($preserve_keys as $key) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserving current non-state-changing table filters.
        if (isset($_REQUEST[$key]) && is_scalar($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserving current non-state-changing table filters.
            $value = sanitize_text_field(wp_unslash($_REQUEST[$key]));

            if (in_array($key, ['taxopress_show_all', 'paged'], true)) {
                $query_args[$key] = absint($value);
            } elseif (in_array($key, ['terms_filter_post_type', 'terms_filter_taxonomy', 'taxonomy_type', 'taxopress_terms_taxonomy', 'orderby', 'order'], true)) {
                $query_args[$key] = sanitize_key($value);
            } else {
                $query_args[$key] = sanitize_text_field($value);
            }
        }
    }

    return array_merge($query_args, $extra);
}

/**
 * Keep a copied term near the original in saved manual order when that order exists.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param int    $original_term_id Original term ID.
 * @param int    $copied_term_id Copied term ID.
 */
function taxopress_place_copied_term_near_original($taxonomy, $original_term_id, $copied_term_id)
{
    $taxonomy = sanitize_key($taxonomy);
    $original_term_id = (int) $original_term_id;
    $copied_term_id = (int) $copied_term_id;

    if (empty($taxonomy) || $original_term_id <= 0 || $copied_term_id <= 0) {
        return;
    }

    $option_name = 'taxopress_term_order_' . $taxonomy;
    $custom_order = array_values(array_filter(array_map('intval', (array) get_option($option_name, []))));

    if (empty($custom_order)) {
        return;
    }

    $custom_order = array_values(array_diff($custom_order, [$copied_term_id]));
    $original_position = array_search($original_term_id, $custom_order, true);

    if ($original_position === false) {
        $custom_order[] = $copied_term_id;
    } else {
        $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();
        $order_setting = isset($taxonomy_settings[$taxonomy]['order']) ? strtolower($taxonomy_settings[$taxonomy]['order']) : 'asc';
        $insert_position = ($order_setting === 'desc') ? $original_position + 1 : $original_position;
        array_splice($custom_order, $insert_position, 0, [$copied_term_id]);
    }

    update_option($option_name, array_values(array_unique($custom_order)));
}

/**
 * Handle the save and deletion of terms data.
 */
function taxopress_process_terms()
{

    if (wp_doing_ajax()) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    if (empty($_GET)) {
        return;
    }

    if (!isset($_GET['page'])) {
        return;
    }
    if ('st_terms' !== $_GET['page']) {
        return;
    }

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-delete-terms') {
        $nonce = !empty($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'terms-action-request-nonce') && isset($_REQUEST['taxopress_terms'])) {
            $term = get_term(sanitize_text_field(wp_unslash($_REQUEST['taxopress_terms'])));
            if (
                !$term
                || is_wp_error($term)
                || !taxopress_current_user_can_for_taxonomy($term->taxonomy, 'delete_terms')
            ) {
                wp_die(esc_html__('Permission denied.', 'simple-tags'), '', ['response' => 403]);
            }
            wp_delete_term($term->term_id, $term->taxonomy);
        }
        add_action('admin_notices', "taxopress_term_delete_success_admin_notice");
        add_filter('removable_query_args', 'taxopress_delete_terms_filter_removable_query_args');
    }

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-remove-from-posts') {
        $nonce = !empty($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'terms-action-request-nonce') && isset($_REQUEST['taxopress_terms'])) {
            $term = get_term(sanitize_text_field(wp_unslash($_REQUEST['taxopress_terms'])));
            if (
                !$term
                || is_wp_error($term)
                || !taxopress_current_user_can_for_taxonomy($term->taxonomy, 'assign_terms')
            ) {
                wp_die(esc_html__('Permission denied.', 'simple-tags'), '', ['response' => 403]);
            }
            $args = array(
                'post_type' => 'any',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Necessary to filter posts by specific term for accurate removal
                'tax_query' => array(
                    array(
                        'taxonomy' => $term->taxonomy,
                        'field' => 'id',
                        'terms' => $term->term_id
                    )
                )
            );
            $post_ids = taxopress_get_post_ids_for_terms_action($args);
            $counter = 0;
            foreach ($post_ids as $post_id) {
                if (!current_user_can('edit_post', $post_id)) {
                    continue;
                }
                $remove = wp_remove_object_terms($post_id, $term->term_id, $term->taxonomy);
                if ($remove) {
                    clean_object_term_cache($post_id, $term->taxonomy);
                    clean_term_cache($term->term_id, $term->taxonomy);
                    $counter++;
                }
            }
        }
        add_action('admin_notices', "taxopress_term_posts_remov_success_admin_notice");
        add_filter('removable_query_args', 'taxopress_delete_terms_filter_removable_query_args');
    }

    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-copy-term') {
        $nonce = !empty($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'terms-action-request-nonce') && isset($_REQUEST['taxopress_terms'])) {
            $term = get_term(sanitize_text_field(wp_unslash($_REQUEST['taxopress_terms'])));

            if (
                !$term
                || is_wp_error($term)
                || !taxopress_current_user_can_for_taxonomy(
                    $term->taxonomy,
                    ['edit_terms', 'assign_terms']
                )
            ) {
                wp_die(esc_html__('Permission denied.', 'simple-tags'), '', ['response' => 403]);
            }

            $taxopress_term_name = $term->name . ' Copy';
            $base_slug = $term->slug . '-copy';
            $taxopress_term_slug = taxopress_get_unique_term_slug($base_slug, $term->taxonomy);
            $taxopress_term_data = wp_insert_term($taxopress_term_name, $term->taxonomy, [
                'slug' => $taxopress_term_slug,
                'description' => $term->description,
                'parent' => $term->parent,
            ]);

            if (!is_wp_error($taxopress_term_data)) {
                $taxopress_term_id = $taxopress_term_data['term_id'];
                taxopress_place_copied_term_near_original($term->taxonomy, $term->term_id, $taxopress_term_id);
                $_REQUEST['taxopress_copied_term_id'] = $taxopress_term_id;
                $_REQUEST['taxopress_original_term_id'] = $term->term_id;
                $_REQUEST['taxopress_copied_taxonomy'] = $term->taxonomy;

                $args = array(
                    'post_type' => 'any',
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Necessary to filter posts by specific term for accurate term copying
                    'tax_query' => array(
                        array(
                            'taxonomy' => $term->taxonomy,
                            'field' => 'id',
                            'terms' => $term->term_id
                        )
                    )
                );
                $post_ids = taxopress_get_post_ids_for_terms_action($args);

                foreach ($post_ids as $post_id) {
                    if (!current_user_can('edit_post', $post_id)) {
                        continue;
                    }
                    wp_set_object_terms($post_id, $taxopress_term_id, $term->taxonomy, true);
                }
            }
        }
        add_action('admin_notices', "taxopress_term_copy_success_admin_notice");
        add_filter('removable_query_args', 'taxopress_delete_terms_filter_removable_query_args');
    }
}
add_action('admin_init', 'taxopress_process_terms', 8);

/**
 * Successful term copy callback.
 */
function taxopress_term_copy_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Term copied successfully.', 'simple-tags'), true);
}

/**
 * Successful deleted callback.
 */
function taxopress_term_delete_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Term deleted successfully.', 'simple-tags'), false);
}

/**
 * Successful remove term from posts callback.
 */
function taxopress_term_posts_remov_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Term removed from all posts successfully.', 'simple-tags'), true);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_delete_terms_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'action',
        'taxopress_terms',
        '_wpnonce',
        'taxopress_copied_term_id',
        'taxopress_original_term_id',
        'taxopress_copied_taxonomy',
    ]);
}
