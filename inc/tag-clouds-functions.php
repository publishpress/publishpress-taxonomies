<?php

/**
 * Fetch our TAXOPRESS Terms Display option.
 *
 * @return mixed
 */
function taxopress_get_tagcloud_data()
{
    return array_filter((array)apply_filters('taxopress_get_tagcloud_data', get_option('taxopress_tagclouds', []), get_current_blog_id()));
}

/**
 * Get the selected tagcloud from the $_POST global.
 *
 * @return bool|string False on no result, sanitized tagcloud if set.
 * @internal
 *
 */
function taxopress_get_current_tagcloud()
{

    $tagclouds = false;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for display selection, no state change
    if (!empty($_GET) && isset($_GET['taxopress_termsdisplay'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tagclouds = sanitize_text_field(wp_unslash($_GET['taxopress_termsdisplay']));
    } else {
        $tagclouds = taxopress_get_tagcloud_data();
        if (!empty($tagclouds)) {
            // Will return the first array key.
            $tagclouds = key($tagclouds);
        }
    }

    /**
     * Filters the current tagcloud to edit.
     *
     * @param string $tagclouds tagcloud slug.
     */
    return apply_filters('taxopress_current_tagcloud', $tagclouds);
}

/**
 * Handle the save and deletion of tagcloud data.
 */
function taxopress_process_tagcloud()
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
    if ('st_terms_display' !== $_GET['page']) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    if (isset($_GET['new_tagcloud'])) {
        if ((int)$_GET['new_tagcloud'] === 1) {
            add_action('admin_notices', "taxopress_termsdisplay_update_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_saved_tagcloud_filter_removable_query_args');
        }
    }

    if (isset($_GET['deleted_tagcloud'])) {
        if ((int)$_GET['deleted_tagcloud'] === 1) {
            add_action('admin_notices', "taxopress_termsdisplay_delete_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_deleted_tagcloud_filter_removable_query_args');
        }
    }


    if (!empty($_POST) && isset($_POST['tagcloud_submit'])) {
        $result = '';
        if (isset($_POST['tagcloud_submit'])) {
            check_admin_referer('taxopress_addedit_tagcloud_nonce_action', 'taxopress_addedit_tagcloud_nonce_field');
            $result = taxopress_update_tagcloud($_POST);
        }

        if ($result) {
            wp_safe_redirect(
                add_query_arg(
                    [
                    'page'               => 'st_terms_display',
                    'add'                => 'new_item',
                    'action'             => 'edit',
                    'taxopress_termsdisplay' => $result,
                    'new_tagcloud'       => 1,
                    ],
                    taxopress_admin_url('admin.php')
                )
            );

            exit();
        }
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-delete-tagcloud') {
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
        if ($nonce && wp_verify_nonce($nonce, 'tagcloud-action-request-nonce')) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Verified by nonce check above
            if (isset($_REQUEST['taxopress_termsdisplay'])) {
                taxopress_action_delete_tagcloud(sanitize_text_field(wp_unslash($_REQUEST['taxopress_termsdisplay'])));
            }
        }
        add_filter('removable_query_args', 'taxopress_delete_tagcloud_filter_removable_query_args');
    }
}



/**
 * Create default cloud tag.
 */
function taxopress_create_default_tag_cloud()
{

    if (wp_doing_ajax()) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if ((int)get_option('taxopress_default_tagclouds') > 0) {
        return;
    }

    if (count(taxopress_get_tagcloud_data()) > 0) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    $default = [];
    $default['taxopress_tag_cloud']['title'] = esc_html__('Terms Display', 'simple-tags');
    $default['taxopress_tag_cloud']['post_type'] = '';
    $default['taxopress_tag_cloud']['before']   = '';
    $default['taxopress_tag_cloud']['after']    = '';
    $default['taxopress_tag_cloud']['taxonomy'] = 'post_tag';
    $default['taxopress_tag_cloud']['max'] = 20;
    $default['taxopress_tag_cloud']['selectionby'] = 'count';
    $default['taxopress_tag_cloud']['selection'] = 'desc';
    $default['taxopress_tag_cloud']['orderby'] = 'random';
    $default['taxopress_tag_cloud']['order'] = 'desc';
    $default['taxopress_tag_cloud']['smallest'] = 12;
    $default['taxopress_tag_cloud']['largest'] = 12;
    $default['taxopress_tag_cloud']['unit'] = 'pt';
    $default['taxopress_tag_cloud']['format'] = 'border';
    $default['taxopress_tag_cloud']['color'] = 1;
    $default['taxopress_tag_cloud']['mincolor'] = '#353535';
    $default['taxopress_tag_cloud']['maxcolor'] = '#000000';
    $default['taxopress_tag_cloud']['xformat'] = '<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>';
    $default['taxopress_tag_cloud']['limit_days'] = 0;
    $default['taxopress_tag_cloud']['term_selection_mode'] = 'automatic';
    $default['taxopress_tag_cloud']['include_terms'] = '';
    $default['tagcloud_submit'] = 'Add Terms Display';
    $default['cpt_tax_status'] = 'new';
    $result = taxopress_update_tagcloud($default);
    update_option('taxopress_default_tagclouds', $result);
}


/**
 * Add to or update our TAXOPRESS option with new data.
 *
 *
 * @param array $data Array of tagcloud data to update. Optional.
 * @return bool|string False on failure, string on success.
 * @internal
 *
 */
function taxopress_update_tagcloud($data = [])
{
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            $sanitized_data[$key] = taxopress_sanitize_text_field($value);
        } else {
            $new_value = [];
            foreach ($data[$key] as $option_key => $option_value) {
                if ($option_key === 'xformat') {
                    $new_value[$option_key] = taxopress_sanitize_text_field($option_value);
                } else {
                    $new_value[$option_key] = taxopress_sanitize_text_field($option_value);
                }
            }
            $sanitized_data[$key] = $new_value;
        }
    }
    $data = $sanitized_data;

    $tagclouds          = taxopress_get_tagcloud_data();

    $title                        = $data['taxopress_tag_cloud']['title'];
    $title                        = str_replace('"', '', htmlspecialchars_decode($title));
    $title                        = htmlspecialchars($title, ENT_QUOTES);
    $title                        = trim($title);
    $data['taxopress_tag_cloud']['title'] = stripslashes_deep($title);

    $xformat                       = $data['taxopress_tag_cloud']['xformat'];
    $data['taxopress_tag_cloud']['xformat'] = stripslashes_deep($xformat);

    if (!empty($data['taxopress_tag_cloud']['color'])) {
        $data['taxopress_tag_cloud']['color'] = taxopress_disp_boolean($data['taxopress_tag_cloud']['color']);
    }

    if (isset($data['edited_tagcloud'])) {
        $tagcloud_id = $data['edited_tagcloud'];
        $tagclouds[$tagcloud_id] = $data['taxopress_tag_cloud'];
        $success = update_option('taxopress_tagclouds', $tagclouds);
    } else {
        $tagcloud_id = (int)get_option('taxopress_tagcloud_ids_increament') + 1;
        $data['taxopress_tag_cloud']['ID'] = $tagcloud_id;
        $tagclouds[$tagcloud_id] = $data['taxopress_tag_cloud'];
        $success = update_option('taxopress_tagclouds', $tagclouds);
        $update_id = update_option('taxopress_tagcloud_ids_increament', $tagcloud_id);
    }
    return $tagcloud_id;
}

/**
 * Successful update callback.
 */
function taxopress_termsdisplay_update_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Settings updated successfully.', 'simple-tags'));
}

/**
 * Successful deleted callback.
 */
function taxopress_termsdisplay_delete_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Terms Display successfully deleted.', 'simple-tags'), false);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_saved_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'new_tagcloud',
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
function taxopress_deleted_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'deleted_tagcloud',
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
function taxopress_delete_tagcloud_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'action',
        'taxopress_termsdisplay',
        '_wpnonce',
    ]);
}

/**
 * Delete our custom taxonomy from the array of taxonomies.
 * @return bool|string False on failure, string on success.
 */
function taxopress_action_delete_tagcloud($tagcloud_id)
{
    $tagclouds = taxopress_get_tagcloud_data();

    if (array_key_exists($tagcloud_id, $tagclouds)) {
        unset($tagclouds[$tagcloud_id]);
        $success = update_option('taxopress_tagclouds', $tagclouds);
    }

    if (isset($success)) {
        add_action('admin_notices', "taxopress_taxdeleted_admin_notice");
        wp_safe_redirect(
            add_query_arg(
                [
                'page'               => 'st_terms_display',
                'deleted_tagcloud'   => 1,
                ],
                taxopress_admin_url('admin.php')
            )
        );
        exit();
    }
}

function taxopress_termsdisplay_shortcode($atts)
{
    extract(shortcode_atts(array(
        'id' => 0
    ), $atts));

    $tagcloud_id = $id;
    $tagclouds = taxopress_get_tagcloud_data();

    ob_start();
    if (array_key_exists($tagcloud_id, $tagclouds)) {
        $tagclouds[$tagcloud_id]['number'] = $tagclouds[$tagcloud_id]['max'];
        if (!isset($tagclouds[$tagcloud_id]['color'])) {
            $tagclouds[$tagcloud_id]['color'] = 'false';
        }
        $tagcloud_arg = build_query($tagclouds[$tagcloud_id]);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SimpleTags_Client_TagCloud::extendedTagCloud($tagcloud_arg);
    }

    $html = ob_get_clean();
    return $html;
}
