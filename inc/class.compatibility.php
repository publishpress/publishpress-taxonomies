<?php

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Compatibility
{
    /**
     * admin_init hook callback
     *
     * @since 0.1
     */
    public static function admin_init()
    {
        // Not on ajax
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Check activation
        if (! current_user_can('activate_plugins')) {
            return;
        }

        trigger_error(sprintf(esc_html__('TaxoPress requires PHP version %s or greater to be activated.'), esc_html(STAGS_MIN_PHP_VERSION)));

        // Deactive self
        deactivate_plugins(plugin_basename(STAGS_DIR . '/simple-tags.php'));

        unset($_GET['activate']);

        add_action('admin_notices', array( __CLASS__, 'admin_notices' ));
    }

    /**
     * Notify the user about the incompatibility issue.
     */
    public static function admin_notices()
    {
        echo '<div class="notice error is-dismissible taxopress-notice">';
        $data = get_plugin_data(TAXOPRESS_FILE);

        echo '<p><strong>' . esc_html__('Warning:', 'simple-tags') . '</strong> '
            . sprintf(
                esc_html__('The active plugin %s is not compatible with your PHP version.', 'simple-tags') . '</p><p>',
                '&laquo;' . esc_html($data['Name']) . ' ' . esc_html($data['Version']) . '&raquo;'
            )
            . sprintf(esc_html__('%s is the minimum version required for this plugin.', 'simple-tags'), 'PHP 7.2.5 ')
            . '</p>';

        echo '</div>';
    }
}
