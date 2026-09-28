<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

/**
 * Fetch our PublishPress Taxonomies Terms Display option.
 *
 * @return mixed
 */
function taxopress_get_posttags_data()
{
    return array_filter((array)apply_filters(
        'taxopress_get_posttags_data',
        get_option('taxopress_posttagss', []),
        get_current_blog_id()
    ));
}

/**
 * Get the selected posttags from the $_POST global.
 *
 * @return bool|string False on no result, sanitized posttags if set.
 * @internal
 *
 */
function taxopress_get_current_posttags()
{

    $posttagss = false;

    if (!empty($_GET) && isset($_GET['taxopress_posttags'])) {
        $posttagss = sanitize_text_field(wp_unslash($_GET['taxopress_posttags']));
    } else {
        $posttagss = taxopress_get_posttags_data();
        if (!empty($posttagss)) {
            // Will return the first array key.
            $posttagss = key($posttagss);
        }
    }

    /**
     * Filters the current posttags to edit.
     *
     * @param string $posttagss posttags slug.
     */
    return apply_filters('taxopress_current_posttags', $posttagss);
}


/**
 * Handle the save and deletion of posttags data.
 */
function taxopress_process_posttags()
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
    if ('st_post_tags' !== $_GET['page']) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    if (isset($_GET['new_posttags'])) {
        if ((int)$_GET['new_posttags'] === 1) {
            add_action('admin_notices', "taxopress_posttags_update_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_saved_posttags_filter_removable_query_args');
        }
    }

    if (isset($_GET['deleted_posttags'])) {
        if ((int)$_GET['deleted_posttags'] === 1) {
            add_action('admin_notices', "taxopress_posttags_delete_success_admin_notice");
            add_filter('removable_query_args', 'taxopress_deleted_posttags_filter_removable_query_args');
        }
    }


    if (!empty($_POST) && isset($_POST['posttags_submit'])) {
        $result = '';
        if (isset($_POST['posttags_submit'])) {
            check_admin_referer('taxopress_addedit_posttags_nonce_action', 'taxopress_addedit_posttags_nonce_field');
            $result = taxopress_update_posttags($_POST);
        }

        if ($result) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'               => 'st_post_tags',
                        'add'                => 'new_item',
                        'action'             => 'edit',
                        'taxopress_posttags' => $result,
                        'new_posttags'       => 1,
                    ],
                    taxopress_admin_url('admin.php')
                )
            );

            exit();
        }
    } elseif (isset($_REQUEST['action']) && $_REQUEST['action'] === 'taxopress-delete-posttags') {
        $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
        if (wp_verify_nonce($nonce, 'posttags-action-request-nonce')) {
            taxopress_action_delete_posttags(sanitize_text_field(wp_unslash($_REQUEST['taxopress_posttags'])));
        }
        add_filter('removable_query_args', 'taxopress_delete_posttags_filter_removable_query_args');
    }
}


/**
 * Create default post tags.
 */
function taxopress_create_default_post_tags()
{

    if (wp_doing_ajax()) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if ((int)get_option('taxopress_default_posttagss') > 0) {
        return;
    }

    if (count(taxopress_get_posttags_data()) > 0) {
        return;
    }

    if (!current_user_can('simple_tags')) {
        return;
    }

    // Get options
    $options = SimpleTags_Plugin::get_option();

    // Default values
    $defaults = [
        'before'    => __('Tags: ', 'simple-tags'),
        'separator' => ', ',
        'after'     => '<br />',
        'xformat'   => __('<a href="%tag_link%" title="%tag_name_attribute%" class="st-post-tags t%tag_scale%" style="%tag_size% %tag_color%" %tag_rel%>%tag_name%</a>', 'simple-tags'),
        'notagtext' => __('No tag for this post.', 'simple-tags'),
        'number'    => 0,
        'format'    => '',
        'smallest'  => 12,
        'largest'   => 12,
        'unit'      => 'pt',
        'mincolor'  => '#353535',
        'maxcolor'  => '#000000',
        'color'     => 1,
        'selectionby' => 'count',
        'selection'   => 'desc',
        'orderby'     => 'random',
        'order'       => 'asc',
        'limit_days'  => 0,
    ];
    // Get values in DB
    $defaults['before']    = $options['tt_before'];
    $defaults['separator'] = $options['tt_separator'];
    $defaults['after']     = $options['tt_after'];
    $defaults['xformat']   = $options['tt_xformat'];
    $defaults['notagtext'] = $options['tt_notagstext'];
    $defaults['number']    = (int)$options['tt_number'];
    $defaults['selectionby'] = $options['tt_selectionby'];
    $defaults['selection']   = $options['tt_selection'];
    $defaults['orderby']     = $options['tt_orderby'];
    $defaults['order']       = $options['tt_order'];
    $defaults['limit_days']  = (int)$options['tt_limit_days'];

    $post_tags_default                                     = [];
    $post_tags_default['taxopress_post_tags']['title']     = esc_html__('Terms for Current Post', 'simple-tags');
    $post_tags_default['taxopress_post_tags']['taxonomy']  = 'post_tag';
    $post_tags_default['taxopress_post_tags']['embedded']  = [];
    $post_tags_default['taxopress_post_tags']['number']    = $defaults['number'];
    $post_tags_default['taxopress_post_tags']['separator'] = $defaults['separator'];
    $post_tags_default['taxopress_post_tags']['after']     = $defaults['after'];
    $post_tags_default['taxopress_post_tags']['before']    = $defaults['before'];
    $post_tags_default['taxopress_post_tags']['notagtext'] = $defaults['notagtext'];
    $post_tags_default['taxopress_post_tags']['xformat']   = $defaults['xformat'];
    $post_tags_default['taxopress_post_tags']['smallest']    = $defaults['smallest'];
    $post_tags_default['taxopress_post_tags']['largest']     = $defaults['largest'];
    $post_tags_default['taxopress_post_tags']['unit']        = $defaults['unit'];
    $post_tags_default['taxopress_post_tags']['mincolor']   = $defaults['mincolor'];
    $post_tags_default['taxopress_post_tags']['maxcolor']   = $defaults['maxcolor'];
    $post_tags_default['taxopress_post_tags']['color']      = $defaults['color'];
    $post_tags_default['taxopress_post_tags']['format']     = $defaults['format'];
    $post_tags_default['taxopress_post_tags']['selectionby'] = $defaults['selectionby'];
    $post_tags_default['taxopress_post_tags']['selection']   = $defaults['selection'];
    $post_tags_default['taxopress_post_tags']['orderby']     = $defaults['orderby'];
    $post_tags_default['taxopress_post_tags']['order']       = $defaults['order'];
    $post_tags_default['taxopress_post_tags']['limit_days']  = $defaults['limit_days'];
    $result                                                = taxopress_update_posttags($post_tags_default);
    update_option('taxopress_default_posttagss', $result);
}


/**
 * Successful update callback.
 */
function taxopress_posttags_update_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Settings updated successfully.', 'simple-tags'));
}

/**
 * Successful deleted callback.
 */
function taxopress_posttags_delete_success_admin_notice()
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo taxopress_admin_notices_helper(esc_html__('Shortcode entry successfully deleted.', 'simple-tags'), false);
}

/**
 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
 *
 * @link https://core.trac.wordpress.org/ticket/23367
 *
 * @param string[] $args Array of removable query arguments.
 * @return string[] Updated array of removable query arguments.
 */
function taxopress_saved_posttags_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'new_posttags',
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
function taxopress_deleted_posttags_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'deleted_posttags',
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
function taxopress_delete_posttags_filter_removable_query_args(array $args)
{
    return array_merge($args, [
        'action',
        'taxopress_posttags',
        '_wpnonce',
    ]);
}

/**
 * Add to or update our PublishPress Taxonomies option with new data.
 *
 *
 * @param array $data Array of posttags data to update. Optional.
 * @return bool|string False on failure, string on success.
 * @internal
 *
 */
function taxopress_update_posttags($data = [])
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

    $posttagss = taxopress_get_posttags_data();

    $title                                = $data['taxopress_post_tags']['title'];
    $title                                = str_replace('"', '', htmlspecialchars_decode($title));
    $title                                = htmlspecialchars($title, ENT_QUOTES);
    $title                                = trim($title);
    $data['taxopress_post_tags']['title'] = stripslashes_deep($title);

    $xformat                                = $data['taxopress_post_tags']['xformat'];
    $data['taxopress_post_tags']['xformat'] = stripslashes_deep($xformat);
    $data['taxopress_post_tags']['embedded']  = isset($data['embedded']) ? $data['embedded'] : [];

    if (isset($data['edited_posttags'])) {
        $posttags_id             = $data['edited_posttags'];
        $posttagss[$posttags_id] = $data['taxopress_post_tags'];
        $success                 = update_option('taxopress_posttagss', $posttagss);
        //return 'update_success';
    } else {
        $posttags_id                       = (int)get_option('taxopress_posttags_ids_increament') + 1;
        $data['taxopress_post_tags']['ID'] = $posttags_id;
        $posttagss[$posttags_id]           = $data['taxopress_post_tags'];
        $success                           = update_option('taxopress_posttagss', $posttagss);
        $update_id                         = update_option('taxopress_posttags_ids_increament', $posttags_id);
        //return 'add_success';
    }

    return $posttags_id;
}


/**
 * Delete our custom taxonomy from the array of taxonomies.
 * @return bool|string False on failure, string on success.
 */
function taxopress_action_delete_posttags($posttags_id)
{
    $posttagss = taxopress_get_posttags_data();

    if (array_key_exists($posttags_id, $posttagss)) {
        unset($posttagss[$posttags_id]);
        $success = update_option('taxopress_posttagss', $posttagss);
    }

    if (isset($success)) {
        add_action('admin_notices', "taxopress_taxdeleted_admin_notice");
        wp_safe_redirect(
            add_query_arg(
                [
                    'page'             => 'st_post_tags',
                    'deleted_posttags' => 1,
                ],
                taxopress_admin_url('admin.php')
            )
        );
        exit();
    }
}

function taxopress_posttags_shortcode($atts)
{
    extract(shortcode_atts([
        'id' => 0
    ], $atts));

    $posttags_id = $id;
    $posttagss   = taxopress_get_posttags_data();

    ob_start();
    if (array_key_exists($posttags_id, $posttagss)) {
        $posttags_arg = build_query($posttagss[$posttags_id]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SimpleTags_Client_PostTags::extendedPostTags($posttags_arg);
    } else {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo esc_html__('Invalid post terms ID.', 'simple-tags');
    }

    $html = ob_get_clean();

    return $html;
}

/**
 * Auto add current tags post to post content
 *
 * @param string $content
 *
 * @return string
 */
function taxopress_posttags_the_content($content = '')
{

    $post_tags = taxopress_get_posttags_data();

    if (count($post_tags) > 0) {
        foreach ($post_tags as $post_tag) {
            // Get option
            $embedded = (isset($post_tag['embedded']) && is_array($post_tag['embedded']) && count($post_tag['embedded']) > 0) ? $post_tag['embedded'] : false;

            if (!$embedded) {
                continue;
            }

            $marker = false;
            if (is_feed() && in_array('feed', $embedded)) {
                $marker = true;
            } elseif (is_home() && in_array('homeonly', $embedded)) {
                $marker = true;
            } elseif (is_feed() && in_array('blogonly', $embedded)) {
                $marker = true;
            } elseif (is_singular() && in_array('singleonly', $embedded)) {
                $marker = true;
            } elseif (is_singular() && in_array(get_post_type(), $embedded)) {
                $marker = true;
            }
            if (true === $marker) {
                $posttags_arg = build_query($post_tag);
                $content      .= SimpleTags_Client_PostTags::extendedPostTags($posttags_arg);
            }
        }
    }

    return $content;
}
