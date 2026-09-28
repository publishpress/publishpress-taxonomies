<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPressVIPMinimum.Security.ProperEscapingFunction.htmlAttrNotByEscHTML -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.
//Taxopress auto terms => Auto terms all content ajax callback
add_action('wp_ajax_taxopress_autoterms_content_by_ajax', 'taxopress_autoterms_content_by_ajax');
function taxopress_autoterms_content_by_ajax()
{
    global $wpdb, $added_post_term, $empty_term_messages;

    $added_post_term = [];
    $empty_term_messages = [];

    // run a quick security check
    check_ajax_referer('st-admin-js', 'security');

    //instantiate response default value
    $response['status'] = 'error';
    $response['message'] = '';
    $response['content'] = '';
    $response['total'] = 0;
    $response['next_start'] = 0;
    $response['percentage'] = '';

    $autoterms      = taxopress_get_autoterm_data();
    $auto_term_id = !empty($_POST['taxopress_autoterm_content']['autoterm_id']) ? (int)$_POST['taxopress_autoterm_content']['autoterm_id'] : 0;

    $existing_terms_batches = !empty($_POST['taxopress_autoterm_content']['existing_terms_batches']) ? (int)$_POST['taxopress_autoterm_content']['existing_terms_batches'] : 0;
    $existing_terms_sleep = !empty($_POST['taxopress_autoterm_content']['existing_terms_sleep']) ? (int)$_POST['taxopress_autoterm_content']['existing_terms_sleep'] : 0;
    $limit_days = !empty($_POST['taxopress_autoterm_content']['limit_days']) ? (int)$_POST['taxopress_autoterm_content']['limit_days'] : 0;
    $autoterm_existing_content_exclude = !empty($_POST['taxopress_autoterm_content']['autoterm_existing_content_exclude']) ? (int)$_POST['taxopress_autoterm_content']['autoterm_existing_content_exclude'] : 0;

    $start_from = isset($_POST['start_from']) && (int)$_POST['start_from'] > 0 ? (int)$_POST['start_from'] : 0;
    $offset_start_from = max(0, $start_from);

    if (!current_user_can('simple_tags')) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('Permission denied.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    }

    $max_batch_size = max(1, (int) apply_filters('taxopress_autoterms_max_batch_size', 25));
    $max_batch_wait = max(0, (int) apply_filters('taxopress_autoterms_max_batch_wait', 60));

    if (1 > $existing_terms_batches) {
        $response['message'] = sprintf(
            esc_html__('The batch limit must be between 1 and %d posts.', 'simple-tags'),
            $max_batch_size
        );
        wp_send_json($response, 400);
    }

    if (0 > $existing_terms_sleep) {
        $response['message'] = sprintf(
            esc_html__('The batch wait time must be between 0 and %d seconds.', 'simple-tags'),
            $max_batch_wait
        );
        wp_send_json($response, 400);
    }

    // Clamp legacy saved values and hostile requests to the current safe limits.
    $existing_terms_batches = min($existing_terms_batches, $max_batch_size);
    $existing_terms_sleep = min($existing_terms_sleep, $max_batch_wait);

    if ($start_from === 0) {
        delete_option('tmp_auto_terms_st');
    }

    $auto_term_settings = [
        'autoterm_id' => $auto_term_id,
        'existing_terms_batches' => $existing_terms_batches,
        'existing_terms_sleep' => $existing_terms_sleep,
        'limit_days' => $limit_days,
        'autoterm_existing_content_exclude' => $autoterm_existing_content_exclude,
    ];
    update_option('taxopress_autoterms_content', $auto_term_settings);

    if (empty($auto_term_id)) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('Auto Term is required, kindly add an Auto Term from Auto Term menu.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    } elseif (empty($existing_terms_batches)) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('Limit per batches is required.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    } elseif (0 > $existing_terms_sleep) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('Batches wait time is required.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    }

    if ($auto_term_id && array_key_exists($auto_term_id, $autoterms)) {
        $autoterm_data       = $autoterms[$auto_term_id];
        $autoterm_data['existing_terms_batches'] = $existing_terms_batches;
        $autoterm_data['existing_terms_sleep'] = $existing_terms_sleep;
        $autoterm_data['limit_days'] = $limit_days;
        $autoterm_data['autoterm_exclude'] = $autoterm_existing_content_exclude;
        $autoterm_data['replace_type'] = isset($autoterm_data['existing_content_replace_type']) ? $autoterm_data['existing_content_replace_type'] : '';
        $autoterm_data['terms_limit'] = !empty($autoterm_data['existing_content_terms_limit']) ? $autoterm_data['existing_content_terms_limit'] : '';
    } else {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('Auto term settings not found', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    }

    if (empty($autoterm_data['post_types'])) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('The selected Auto Term setting is not enabled for any post type. Please select at least one post type in the Auto Term settings.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    }

    if (empty($autoterm_data['autoterm_for_existing_content'])) {
        $response['message'] = '<div class="taxopress-response-css red"><p>' . esc_html__('The selected Auto Term is not enabled for existing content. Please enable it in Auto Term settings.', 'simple-tags') . '</p><button type="button" class="notice-dismiss"></button></div>';
        wp_send_json($response);
    }

    $lock_key = 'taxopress_autoterms_scan_lock_' . $auto_term_id;
    $lock_ttl = max(30, (int) apply_filters('taxopress_autoterms_scan_lock_ttl', 300));
    $lock_token = wp_generate_uuid4();
    $existing_lock = get_option($lock_key, []);
    $existing_lock_time = is_array($existing_lock) && isset($existing_lock['time'])
        ? (int) $existing_lock['time']
        : 0;

    if ($existing_lock_time && $existing_lock_time < (time() - $lock_ttl)) {
        delete_option($lock_key);
    }

    if (!add_option($lock_key, ['token' => $lock_token, 'time' => time()], '', false)) {
        $response['message'] = esc_html__('This Auto Terms scan is already processing another batch. Please wait and try again.', 'simple-tags');
        wp_send_json($response, 409);
    }

    $limit = (isset($autoterm_data['existing_terms_batches']) && (int)$autoterm_data['existing_terms_batches'] > 0) ? (int)$autoterm_data['existing_terms_batches'] : 2;

    $sleep = (isset($autoterm_data['existing_terms_sleep']) && (int)$autoterm_data['existing_terms_sleep'] > 0) ? (int)$autoterm_data['existing_terms_sleep'] : 0;

    // Waiting is performed by the browser between requests so PHP workers are not held idle.
    $response['wait'] = $sleep;

    $limit_days     = (int) $autoterm_data['limit_days'];
    $limit_days_sql = '';
    if ($limit_days > 0) {
        $limit_days_sql = 'AND post_date > "' . gmdate('Y-m-d H:i:s', time() - $limit_days * 86400) . '"';
    }

    $post_types = $autoterm_data['post_types'];
    $post_status = isset($autoterm_data['post_status']) && is_array($autoterm_data['post_status']) ? $autoterm_data['post_status'] : ['publish'];

    $total = isset($_POST['total']) ? (int)$_POST['total'] : 0;

    $post_types = array_map('sanitize_key', (array) $post_types);
    $post_status = array_map('sanitize_key', (array) $post_status);

    $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
    $post_status_placeholders = implode(',', array_fill(0, count($post_status), '%s'));

    if ($autoterm_existing_content_exclude > 0) {
        $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} 
                    LEFT JOIN {$wpdb->postmeta} ON ( ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_taxopress_autotermed' ) 
                    WHERE post_type IN ({$post_types_placeholders}) 
                    AND {$wpdb->postmeta}.post_id IS NULL 
                    AND post_status IN ({$post_status_placeholders}) 
                    {$limit_days_sql} 
                    ORDER BY ID DESC 
                    LIMIT %d OFFSET %d";

        $prepare_args = array_merge($post_types, $post_status, [$limit, $offset_start_from]);
        $objects = (array) $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
    } else {
        $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} 
                    WHERE post_type IN ({$post_types_placeholders}) 
                    AND post_status IN ({$post_status_placeholders}) 
                    {$limit_days_sql} 
                    ORDER BY ID DESC 
                    LIMIT %d OFFSET %d";

        $prepare_args = array_merge($post_types, $post_status, [$limit, $offset_start_from]);
        $objects = (array) $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
    }

    $response_content = '';
    if (!empty($objects)) {
        foreach ($objects as $object) {
            update_post_meta($object->ID, '_taxopress_autotermed', 1);
            $post_object = get_post($object->ID);
            SimpleTags_Client_Autoterms::auto_terms_post($post_object, $autoterm_data['taxonomy'], $autoterm_data, true, 'existing_content', 'st_autoterms');
            $log_messages = !empty($empty_term_messages[$object->ID]['message']) ? $empty_term_messages[$object->ID]['message'] : [];
            $log_message_html = '';
            if (!empty($log_messages)) {
                $log_message_html .= '<div class="log-message-show-button"><a href="#" class="msg-show">' . sprintf(esc_html__('Show Log Messages (%1s)', 'simple-tags'), '<strong>' . count($log_messages) . '</strong>') . '</a></div>';
                $log_message_html .= '<div class="autoterm-log-message" style="display: none;"><ul>';
                $log_msg_lists = array_map(function ($item) {
                    return '<li class="log-message-list">' . $item . '</li>';
                }, $log_messages);
                $log_message_html .= join('', $log_msg_lists);
                $log_message_html .= '</ul></div>';
                $log_message_html .= '<div class="log-message-hide-button"><a href="#" class="msg-hide" style="display: none;">' . sprintf(esc_html__('Hide Log Messages (%1s)', 'simple-tags'), '<strong>' . count($log_messages) . '</strong>') . '</a></div>';
            }
            if (!empty($added_post_term[$object->ID])) {
                $tag_lists = array_map(function ($item) {
                    return '<span class="taxopress-term"><span class="term-name">' . $item . '</span></span>';
                }, $added_post_term[$object->ID]);
                $added_terms_html = join('', $tag_lists) . $log_message_html;
            } else {
                $added_terms_html = esc_html__('No terms added.', 'simple-tags') . $log_message_html;
                ;
            }
            $response_content .= '<li class="result-item">
                <fieldset>
                    <legend> 
                        <span class="result-title">
                            <a target="_blank" href="' . esc_url(get_edit_post_link($object->ID)) . '">' . esc_html($object->post_title) . '</a>
                        </span> 
                    </legend>
                    <div class="result-content">' . $added_terms_html . '</div>
                </fieldset></li>';
            unset($object);
        }

        $response['status'] = 'progress';
        $response['content'] = $response_content;
        $response['done'] = ($start_from + count($objects));
        $percentage = 100;
        $response['notice'] = '<div class="taxopress-response-css yellow"><p>' . sprintf(esc_html__('Please leave this screen running to continue the scan. To stop the scan, close this screen or click here: %1sStop%2s | %3sPause%4s', 'simple-tags'), '<a href="#" class="terminate-autoterm-scan">', '</a>', '<a href="#" class="pause-autoterm-scan" data-pause="0" data-pause-text="' . esc_html__('Pause', 'simple-tags') . '" data-resume-text="' . esc_html__('Resume', 'simple-tags') . '">', '</a>') . '</p></div>';
        $progress_message = '<div class="taxopress-response-css yellow"><p>' . sprintf(esc_html__('Progress Report: %s posts checked.', 'simple-tags'), '<strong>' . ($start_from + count($objects)) . '</strong>') . '</p></div>';
    } else {
        $counter = (int)get_option('tmp_auto_terms_st');
        delete_option('tmp_auto_terms_st');
        $percentage = 100;
        $response['status'] = 'sucess';
        $response['done'] = $total;
        $progress_message = '<div class="taxopress-response-css green"><p>' . sprintf(esc_html__('Completed: %s terms added from %s posts checked.', 'simple-tags'), $counter, ($start_from + count($objects))) . '</p><button type="button" class="notice-dismiss"></button></div>';
        $response['message'] = $progress_message;
    }
    $response['percentage'] = $progress_message;

    $current_lock = get_option($lock_key, []);
    if (is_array($current_lock) && isset($current_lock['token']) && $current_lock['token'] === $lock_token) {
        delete_option($lock_key);
    }

    wp_send_json($response);
}


//Taxopress search post call back
add_action('wp_ajax_taxopress_post_search', 'taxopress_post_search_callback');

function taxopress_post_search_callback()
{
    header('Content-Type: application/javascript');

    if (
        empty($_GET['nonce'])
        || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'taxopress-post-search')
    ) {
        wp_send_json_error(null, 403);
    }

    if (!current_user_can('simple_tags')) {
        wp_send_json_error(null, 403);
    }

    $search = !empty($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $post_type = !empty($_GET['post_types']) ? array_map('sanitize_text_field', wp_unslash($_GET['post_types'])) : 'any';

    $post_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => apply_filters('taxopress_filter_posts_search_result_limit', 20),
    ];

    if (!empty($search)) {
        $post_args['s'] = $search;
    }

    $posts = get_posts($post_args);
    $results = [];

    foreach ($posts as $post) {
        $results[] = [
            'id' => $post->ID,
            'text' => $post->post_title,
        ];
    }

    $response = [
        'results' => $results,
    ];
    echo wp_json_encode($response);
    exit;
}


//Taxopress search field call back
add_action('wp_ajax_taxopress_custom_fields_search', 'taxopress_custom_fields_search_callback');

function taxopress_custom_fields_search_callback()
{
    global $wpdb;

    header('Content-Type: application/javascript');

    if (
        empty($_GET['nonce'])
        || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'taxopress-custom-fields-search')
    ) {
        wp_send_json_error(null, 403);
    }

    if (!current_user_can('simple_tags')) {
        wp_send_json_error(null, 403);
    }

    $search = !empty($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

    $whereClause = '';
    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $whereClause = $wpdb->prepare("AND meta_key LIKE %s", $like);
    }

    $queryResults = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE 1=1 $whereClause ORDER BY meta_key ASC LIMIT 20");

    $results = [];
    if (!empty($queryResults)) {
        foreach ($queryResults as $queryResult) {
            $results[] = [
                'id' => $queryResult,
                'text' => $queryResult,
            ];
        }
    }

    $response = [
        'results' => $results,
    ];
    echo wp_json_encode($response);
    exit;
}
