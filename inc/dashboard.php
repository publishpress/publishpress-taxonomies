<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Dashboard
{
    public const MENU_SLUG = 'st_options';

    // class instance
    public static $instance;

    /**
     * Constructor
     *
     * @return void
     * @author Olatechpro
     */
    public function __construct()
    {
        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /** Singleton instance */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add WP admin menu for Tags
     *
     * @return void
     * @author Olatechpro
     */
    public function admin_menu()
    {
        $hook = add_submenu_page(
            self::MENU_SLUG,
            esc_html__('Dashboard', 'simple-tags'),
            esc_html__('Dashboard', 'simple-tags'),
            'simple_tags',
            'st_dashboard',
            [
                $this,
                'page_manage_dashboard',
            ]
        );
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Ojopaul
     */
    public function page_manage_dashboard()
    {
        // Default order
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        // Display message
        settings_errors(__CLASS__);

        $show_welcome = isset($_GET['welcome']) ? true : false;
        ?>
        <?php if ($show_welcome) : ?>
        <div class="taxopress-welcome-banner">
            <div class="banner-wrap">
                <div class="banner-inner">
                    <a>
                        <img src="<?php echo esc_url(STAGS_URL . '/assets/images/tp-email-logo.png'); ?>"  alt="TaxoPress Logo"/>
                    </a>
                </div>
            </div>
        </div>

        <div class="taxopress-welcome-content">
            <div class="content-wrap">
                <div class="taxopress-welcome">
                    <div class="welcome-panel-content">
                        <h2><?php esc_html_e('Welcome to TaxoPress, the WordPress taxonomy plugin', 'simple-tags'); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <div class="taxopress-block-wrap">
        <div class="wrap st_wrap tagcloudui st_dashboard-page admin-settings">
            <h1 class="wp-heading-inline"><?php esc_html_e('Dashboard', 'simple-tags'); ?></h1>
            <div class="taxopress-description">
                <?php esc_html_e('This screen allows you to enable or disable TaxoPress features.', 'simple-tags'); ?>
            </div>

            <form id="taxopress-capabilities-dashboard-form">
                <div class="taxopress-dashboard-settings-boxes">
                    <?php foreach (taxopress_dashboard_options() as $feature => $option) :
                        $feature_option_key = $option['option_key'];
                        ?>
                        <div class="taxopress-dashboard-settings-box">
                            <h3><?php echo esc_html($option['label']); ?></h3>
                            <div class="taxopress-dashboard-settings-description"><?php echo esc_html($option['description']); ?></div>
                            <div class="taxopress-dashboard-settings-control">
                                <div class="taxopress-switch-button">
                                    <label class="switch">
                                        <input type="checkbox" value="1" data-option_key="<?php echo esc_attr($feature_option_key); ?>" data-feature="<?php echo esc_attr($feature); ?>" <?php checked((int) SimpleTags_Plugin::get_option_value($feature_option_key), 1); ?> />
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <div class="taxopress-right-sidebar admin-settings-sidebar">
            <?php do_action('taxopress_admin_after_sidebar'); ?>
        </div>

        </div>

        <?php
    }
}
