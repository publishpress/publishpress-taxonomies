<?php

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Admin_Post_Settings
{
    private const STATUS_DEFAULT = 'default';
    private const STATUS_ENABLED = 'enabled';
    private const STATUS_DISABLED = 'disabled';

    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {

        if ((1 === (int) SimpleTags_Plugin::get_option_value('active_auto_links')) || (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms'))) {
            // Save tags from advanced input
            add_action('save_post', array( __CLASS__, 'save_post' ), 10, 1);
            // Box for advanced tags
            add_action('add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 1);
        }
    }

    /**
     * Register a new box for PublishPress Taxonomies settings
     *
     * @param string $post_type
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function add_meta_boxes($post_type)
    {

        $autoterm = taxopress_post_type_autoterms();
        $autolink = taxopress_post_type_autolink_autolink();

        if (!is_array($autoterm) && !is_array($autolink)) {
            return;
        }

        // Auto terms for this CPT ?
        add_meta_box('simpletags-settings', __('PublishPress Taxonomies', 'simple-tags'), array(
            __CLASS__,
            'metabox'
        ), $post_type, 'side', 'low');
    }

    /**
     * Build HTML of form
     *
     * @param object $post
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function metabox($post)
    {
        if (! isset($post->post_type)) {
            return;
        }

        wp_nonce_field('taxopress_post_settings', 'taxopress_post_settings_nonce');

        // Auto terms for this CPT ?
        if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
            $meta_value = self::get_status($post->ID, '_taxopress_autoterms_status', '_exclude_autotags');
            echo '<p>' . "\n";
            echo '<label for="taxopress_autoterms_status">' . esc_html__('Auto Terms', 'simple-tags') . '</label><br />' . "\n";
            echo '<select id="taxopress_autoterms_status" name="taxopress_autoterms_status">' . "\n";
            self::status_options($meta_value);
            echo '</select>' . "\n";
            echo '</p>' . "\n";
            echo '<input type="hidden" name="_meta_autotags" value="true" />';
        }

        if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_links')) {
            $meta_value = self::get_status($post->ID, '_taxopress_autolinks_status', '_exclude_autolinks');
            echo '<p>' . "\n";
            echo '<label for="taxopress_autolinks_status">' . esc_html__('Auto Links', 'simple-tags') . '</label><br />' . "\n";
            echo '<select id="taxopress_autolinks_status" name="taxopress_autolinks_status">' . "\n";
            self::status_options($meta_value);
            echo '</select>' . "\n";
            echo '</p>' . "\n";
            echo '<input type="hidden" name="_meta_autolink" value="true" />';
        }
    }

    /**
     * Save this settings in post meta, delete if no exclude, clean DB :)
     *
     * @param integer $object_id
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function save_post($object_id = 0)
    {
        if (
            ! isset($_POST['taxopress_post_settings_nonce'])
            || ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['taxopress_post_settings_nonce'])),
                'taxopress_post_settings'
            )
        ) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($object_id)) {
            return;
        }

        if (! current_user_can('edit_post', $object_id)) {
            return;
        }

        $has_autotags_meta = isset($_POST['_meta_autotags']) && 'true' === sanitize_text_field(wp_unslash($_POST['_meta_autotags']));
        $has_autolink_meta = isset($_POST['_meta_autolink']) && 'true' === sanitize_text_field(wp_unslash($_POST['_meta_autolink']));

        if ($has_autotags_meta) {
            self::save_status($object_id, 'taxopress_autoterms_status', '_taxopress_autoterms_status', '_exclude_autotags');
        }

        if ($has_autolink_meta) {
            self::save_status($object_id, 'taxopress_autolinks_status', '_taxopress_autolinks_status', '_exclude_autolinks');
        }
    }

    private static function get_status($post_id, $status_meta_key, $legacy_disable_meta_key = '')
    {
        $status = get_post_meta($post_id, $status_meta_key, true);

        if (self::is_valid_status($status)) {
            return $status;
        }

        if ($legacy_disable_meta_key && get_post_meta($post_id, $legacy_disable_meta_key, true)) {
            return self::STATUS_DISABLED;
        }

        return self::STATUS_DEFAULT;
    }

    private static function save_status($post_id, $post_key, $status_meta_key, $legacy_disable_meta_key = '')
    {
        $status = isset($_POST[$post_key]) ? sanitize_key(wp_unslash($_POST[$post_key])) : self::STATUS_DEFAULT;

        if (! self::is_valid_status($status)) {
            $status = self::STATUS_DEFAULT;
        }

        if (self::STATUS_DEFAULT === $status) {
            delete_post_meta($post_id, $status_meta_key);
        } else {
            update_post_meta($post_id, $status_meta_key, $status);
        }

        if ($legacy_disable_meta_key) {
            if (self::STATUS_DISABLED === $status) {
                update_post_meta($post_id, $legacy_disable_meta_key, true);
            } else {
                delete_post_meta($post_id, $legacy_disable_meta_key);
            }
        }
    }

    private static function status_options($current_status)
    {
        $statuses = [
            self::STATUS_DEFAULT  => esc_html__('Default', 'simple-tags'),
            self::STATUS_ENABLED  => esc_html__('Enable', 'simple-tags'),
            self::STATUS_DISABLED => esc_html__('Disable', 'simple-tags'),
        ];

        foreach ($statuses as $status => $label) {
            echo '<option value="' . esc_attr($status) . '"' . selected($current_status, $status, false) . '>' . esc_html($label) . '</option>' . "\n";
        }
    }

    private static function is_valid_status($status)
    {
        return in_array($status, [self::STATUS_DEFAULT, self::STATUS_ENABLED, self::STATUS_DISABLED], true);
    }
}
