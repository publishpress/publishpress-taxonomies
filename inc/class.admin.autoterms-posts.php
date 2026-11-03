<?php

defined('ABSPATH') || exit;

/**
 * Run opted-in Auto Terms configurations on selected rows of the Posts list.
 */
class TaxoPress_Autoterms_Posts
{
    public static function register()
    {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_taxopress_run_selected_autoterms', [__CLASS__, 'ajax']);
    }

    /**
     * Return only enabled configurations and authorized target taxonomies.
     *
     * @param string $post_type Current post type.
     * @return array
     */
    public static function configurations($post_type)
    {
        if (
            !current_user_can('simple_tags')
            || 1 !== (int) SimpleTags_Plugin::get_option_value('active_auto_terms')
        ) {
            return [];
        }
        $enabled = [];
        foreach (taxopress_get_autoterm_data() as $id => $configuration) {
            if (
                empty($configuration['autoterm_for_posts_screen'])
                || !in_array($post_type, (array) ($configuration['post_types'] ?? []), true)
                || empty($configuration['taxonomy'])
            ) {
                continue;
            }
            $taxonomies = array_unique(array_merge(
                [$configuration['taxonomy']],
                (array) ($configuration['additional_taxonomies'] ?? [])
            ));
            foreach ($taxonomies as $taxonomy) {
                $object = get_taxonomy($taxonomy);
                if (
                    !$object || !is_object_in_taxonomy($post_type, $taxonomy)
                    || empty($object->cap->assign_terms) || !current_user_can($object->cap->assign_terms)
                    || empty($object->cap->edit_terms) || !current_user_can($object->cap->edit_terms)
                ) {
                    continue 2;
                }
            }
            $enabled[$id] = $configuration;
        }
        return $enabled;
    }

    public static function enqueue($hook)
    {
        $screen = get_current_screen();
        if ($hook !== 'edit.php' || !$screen || $screen->base !== 'edit') {
            return;
        }
        $configurations = self::configurations($screen->post_type);
        if (!$configurations) {
            return;
        }
        $choices = [];
        foreach ($configurations as $id => $configuration) {
            $choices[] = [
                'id' => (int) $id,
                'title' => html_entity_decode($configuration['title'], ENT_QUOTES, 'UTF-8'),
            ];
        }
        wp_enqueue_script(
            'taxopress-posts-autoterms',
            STAGS_URL . '/assets/js/posts-autoterms.js',
            ['jquery'],
            STAGS_VERSION,
            true
        );
        wp_localize_script('taxopress-posts-autoterms', 'taxopressPostsAutoterms', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('taxopress-run-selected-autoterms'),
            'configurations' => $choices,
            'run' => __('Run Auto Terms', 'simple-tags'),
            'configuration' => __('Auto Terms configuration', 'simple-tags'),
            'selectPosts' => __('Select at least one post first.', 'simple-tags'),
            // translators: 1: processed posts, 2: selected posts.
            'progress' => __('Posts processed: %1$d of %2$d.', 'simple-tags'),
            // translators: 1: processed posts, 2: posts with terms added.
            'complete' => __('Finished. Posts processed: %1$d. Posts with terms added: %2$d. Check Auto Terms Logs for details.', 'simple-tags'),
            'failed' => __('Auto Terms stopped. The request failed; previously completed posts were not rolled back.', 'simple-tags'),
        ]);
    }

    public static function ajax()
    {
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' !== $request_method) {
            wp_send_json_error(['message' => __('A POST request is required.', 'simple-tags')], 405);
        }
        check_ajax_referer('taxopress-run-selected-autoterms', 'nonce');
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $configuration_id = isset($_POST['configuration_id']) ? absint($_POST['configuration_id']) : 0;
        $result = self::process($post_id, $configuration_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }
        wp_send_json_success($result);
    }

    /**
     * Process exactly one post with freshly checked permissions and configuration.
     *
     * @param int $post_id Selected post.
     * @param int $configuration_id Enabled Auto Terms configuration.
     * @return array|WP_Error
     */
    public static function process($post_id, $configuration_id)
    {
        if (!current_user_can('simple_tags') || !current_user_can('edit_post', $post_id)) {
            return new WP_Error('permission_denied', __('You cannot run Auto Terms on this post.', 'simple-tags'));
        }
        $post = get_post($post_id);
        if (!$post || in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], true)) {
            return new WP_Error('invalid_post', __('This post cannot be processed.', 'simple-tags'));
        }
        $configurations = self::configurations($post->post_type);
        if (!isset($configurations[$configuration_id])) {
            return new WP_Error('invalid_configuration', __('This Auto Terms configuration is disabled or unavailable for this post.', 'simple-tags'));
        }
        $configuration = $configurations[$configuration_id];
        if (!in_array($post->post_status, (array) ($configuration['post_status'] ?? ['publish']), true)) {
            return new WP_Error('invalid_status', __('This post status is not enabled in the Auto Terms configuration.', 'simple-tags'));
        }
        // The token prevents this request from removing a newer request's lock.
        $lock_key = 'taxopress_posts_autoterms_lock_' . $post_id . '_' . $configuration_id;
        $lock = get_option($lock_key, []);
        if (is_array($lock) && !empty($lock['time']) && $lock['time'] < time() - 300) {
            delete_option($lock_key);
        }
        $token = wp_generate_uuid4();
        if (!add_option($lock_key, ['token' => $token, 'time' => time()], '', false)) {
            return new WP_Error('already_running', __('Auto Terms is already processing this post. Please retry later.', 'simple-tags'));
        }
        try {
            $configuration['replace_type'] = $configuration['post_replace_type'] ?? '';
            $added = SimpleTags_Client_Autoterms::auto_terms_post(
                $post,
                $configuration['taxonomy'],
                $configuration,
                false,
                'save_posts',
                'st_autoterms'
            );
            return ['post_id' => $post_id, 'added' => $added === true];
        } catch (Throwable $error) {
            return new WP_Error('processing_failed', __('Auto Terms could not process this post. Check Auto Terms Logs for details.', 'simple-tags'));
        } finally {
            $lock = get_option($lock_key, []);
            if (is_array($lock) && ($lock['token'] ?? '') === $token) {
                delete_option($lock_key);
            }
        }
    }
}
