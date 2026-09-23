<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

// Include modules
require_once(TAXOPRESS_ABSPATH . '/modules/taxopress-ai/taxopress-ai.php');

class SimpleTags_Admin
{
    // CPT and Taxonomy support
    public static $post_type = 'post';
    public static $post_type_name = '';
    public static $taxonomy = '';
    public static $taxo_name = '';
    public static $admin_url = '';
    public static $enabled_menus = [];

    public const MENU_SLUG = 'st_options';

    /**
     * Initialize Admin
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {
        // DB Upgrade ?
        self::upgrade();

        // Which taxo ?
        self::register_taxonomy();

        // Plugin installer
        add_action('admin_init', array(__CLASS__, 'plugin_installer_upgrade_code'));

        // Redirect on plugin activation
        add_action('admin_init', array(__CLASS__, 'redirect_on_activate'));

        // Admin menu
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));

        //Admin footer credit
        add_action('in_admin_footer', array(__CLASS__, 'taxopress_admin_footer'));

        // Load JavaScript and CSS
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));

        add_action('admin_enqueue_scripts', [$this, 'maybe_enqueue_frontend_assets_in_admin']);

        add_action('admin_head', array($this, 'taxopress_hide_other_plugin_notices'));

        add_action('wp_ajax_taxopress_search_posts', [$this, 'taxopress_search_posts_ajax']);

        //ui class is used accross many pages. So, it should be here
        require STAGS_DIR . '/inc/class.admin.taxonomies.ui.php';

        $dashboard_screen = (isset($_GET['page']) && $_GET['page'] === 'st_dashboard') ? true : false;

        //dashboard
        require STAGS_DIR . '/inc/dashboard.php';
        SimpleTags_Dashboard::get_instance();
        self::$enabled_menus['st_dashboard'] = esc_html__('Dashboard', 'simple-tags');
        if (1 === (int) SimpleTags_Plugin::get_option_value('active_taxonomies')) {
            self::$enabled_menus['st_taxonomies'] = esc_html__('Taxonomies', 'simple-tags');
        }

        //hidden-terms
        if (1 === (int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
            require STAGS_DIR . '/inc/hidden-terms.php';
            SimpleTags_Hidden_Terms::get_instance();
        }

        //terms
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_st_terms')) {
            require STAGS_DIR . '/inc/terms-table.php';
            require STAGS_DIR . '/inc/terms.php';
            SimpleTags_Terms::get_instance();
            self::$enabled_menus['st_terms'] = esc_html__('Terms', 'simple-tags');
        }

        //posts
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_st_posts')) {
            require STAGS_DIR . '/inc/posts-table.php';
            require STAGS_DIR . '/inc/posts.php';
            SimpleTags_Posts::get_instance();
            self::$enabled_menus['st_posts'] = esc_html__('Posts', 'simple-tags');
        }

        //tag clouds/ terms display
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_terms_display')) {
            require_once STAGS_DIR . '/inc/tag-clouds-action.php';
            require STAGS_DIR . '/inc/tag-clouds-table.php';
            require STAGS_DIR . '/inc/tag-clouds.php';
            SimpleTags_Tag_Clouds::get_instance();
        }

        //post tags
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_post_tags')) {
            require_once STAGS_DIR . '/inc/post-tags-action.php';
            require STAGS_DIR . '/inc/post-tags-table.php';
            require STAGS_DIR . '/inc/post-tags.php';
            SimpleTags_Post_Tags::get_instance();
        }

        //Related Posts
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_related_posts_new')) {
            require_once STAGS_DIR . '/inc/related-posts-action.php';
            require STAGS_DIR . '/inc/related-posts-table.php';
            require STAGS_DIR . '/inc/related-posts.php';
            SimpleTags_Related_Post::get_instance();
        }

        //Auto Links
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_auto_links')) {
            require STAGS_DIR . '/inc/autolinks-table.php';
            require STAGS_DIR . '/inc/autolinks.php';
            SimpleTags_Autolink::get_instance();
        }

        //Auto Terms
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
            require STAGS_DIR . '/inc/autoterms-table.php';
            require STAGS_DIR . '/inc/autoterms-logs-table.php';
            require STAGS_DIR . '/inc/autoterms.php';
            require STAGS_DIR . '/inc/autoterms_content.php';
            require STAGS_DIR . '/inc/schedule.php';
            require STAGS_DIR . '/inc/schedule-logs-table.php';
            SimpleTags_Autoterms::get_instance();
            SimpleTags_Autoterms_Content::get_instance();
            SimpleTags_Autoterms_Schedule::get_instance();
            self::$enabled_menus['st_autoterms'] = esc_html__('Auto Terms', 'simple-tags');
        }

        TaxoPress_AI_Module::get_instance();

        //click terms option
        require STAGS_DIR . '/inc/class.admin.clickterms.php';
        new SimpleTags_Admin_ClickTags();

        require STAGS_DIR . '/inc/class.admin.autocomplete.php';
        new SimpleTags_Admin_Autocomplete();

        // Mass edit terms
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_mass_edit')) {
            require STAGS_DIR . '/inc/class.admin.mass.php';
            new SimpleTags_Admin_Mass();
        }

        // Manage terms
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_manage')) {
            require STAGS_DIR . '/inc/class-tag-table.php';
            require STAGS_DIR . '/inc/class.admin.manage.php';
            SimpleTags_Admin_Manage::get_instance();
        }

        require STAGS_DIR . '/inc/class.admin.post.php';
        new SimpleTags_Admin_Post_Settings();

        //taxonomies
        if ($dashboard_screen || 1 === (int) SimpleTags_Plugin::get_option_value('active_taxonomies')) {
            require_once STAGS_DIR . '/inc/taxonomies-action.php';
            require STAGS_DIR . '/inc/class-taxonomies-table.php';
            require STAGS_DIR . '/inc/taxonomies.php';
            SimpleTags_Admin_Taxonomies::get_instance();
        }

        do_action('taxopress_admin_class_after_includes');

        // Ajax action, JS Helper and admin action
        add_action('wp_ajax_simpletags', array(__CLASS__, 'ajax_check'));
        // Save dashboard feature
        add_action('wp_ajax_save_taxopress_dashboard_feature_by_ajax', [__CLASS__, 'saveDashboardFeature']);
        // Plugin action links
        add_filter('plugin_action_links_' . plugin_basename(TAXOPRESS_FILE), [__CLASS__, 'plugin_settings_link']);
    }

    public function taxopress_hide_other_plugin_notices()
    {
        global $pagenow;

        $taxopress_pages = taxopress_admin_pages();

        if (isset($_GET['page']) && in_array($_GET['page'], $taxopress_pages)) {
            echo '<style>
				.notice:not(.taxopress-notice) {
					display: none !important;
				}
			</style>';
        }
    }

    /**
     * Plugin action links
     */
    public static function plugin_settings_link($links)
    {

        foreach (self::$enabled_menus as $menu_slug => $menu_title) {
            $links[] = '<a href="' . admin_url('admin.php?page=' . $menu_slug) . '">' . $menu_title . '</a>';
        }

        return $links;
    }

    /**
     * Ajax for saving a feature from dashboard page
     *
     * Copied from PublishPress Blocks
     *
     * @return boolean,void     Return false if failure, echo json on success
     */
    public static function saveDashboardFeature()
    {
        if (!current_user_can('admin_simple_tags')) {
            wp_send_json(__('No permission!', 'simple-tags'), 403);
            return false;
        }

        if (
            !wp_verify_nonce(
                sanitize_key(wp_unslash($_POST['nonce'])),
                'st-admin-js'
            )
        ) {
            wp_send_json(__('Invalid nonce token!', 'simple-tags'), 400);
            return false;
        }

        if (empty($_POST['feature'])) {
            wp_send_json(__('Error: wrong data', 'simple-tags'), 400);
            return false;
        }

        $feature   = sanitize_text_field(wp_unslash($_POST['feature']));
        $new_state = sanitize_text_field(wp_unslash($_POST['new_state']));

        SimpleTags_Plugin::set_option_value($feature, $new_state);

        wp_send_json(true, 200);
    }

    /**
     * Ajax Dispatcher
     */
    public static function ajax_check()
    {
        if (isset($_GET['stags_action']) && 'maybe_create_tag' === $_GET['stags_action'] && isset($_GET['tag'])) {
            if (!check_ajax_referer('st-admin-js', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'simple-tags')], 403);
            }

            $taxonomy = get_taxonomy('post_tag');
            if (!$taxonomy || empty($taxonomy->cap->manage_terms) || !current_user_can($taxonomy->cap->manage_terms)) {
                wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')], 403);
            }

            self::maybe_create_tag(sanitize_text_field(wp_unslash($_GET['tag'])));
        }
    }

    /**
     * Maybe create a tag, and return the term_id
     *
     * @param string $tag_name
     */
    public static function maybe_create_tag($tag_name = '')
    {
        $term_id     = 0;
        //restore & in tag post
        $tag_name = sanitize_text_field(str_replace("simpletagand", "&", $tag_name));
        $result_term = term_exists($tag_name, 'post_tag', 0);
        if (empty($result_term)) {
            $result_term = wp_insert_term(
                $tag_name,
                'post_tag'
            );

            if (!is_wp_error($result_term)) {
                $term_id = (int) $result_term['term_id'];
            }
        } else {
            $term_id = (int) $result_term['term_id'];
        }

        wp_send_json_success(['term_id' => $term_id]);
    }

    /**
     * Ajax for search posts
     *
     * @return void
     */
    public static function taxopress_search_posts_ajax()
    {
        if (!check_ajax_referer('st-admin-js', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'simple-tags')]);
        }

        if (!current_user_can('simple_tags')) {
            wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')]);
        }

        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $paged = max(1, intval($_GET['page'] ?? 1));
        $post_type = 'post';


        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids'
        ]);

        $results = [];
        foreach ($query->posts as $post_id) {
            $results[] = [
                'id' => $post_id,
                'text' => get_the_title($post_id)
            ];
        }

        wp_send_json([
            'results' => $results,
            'more' => $paged < $query->max_num_pages
        ]);
    }

    /**
     * Test if current URL is not a DEV environnement
     *
     * Copy from monsterinsights_is_dev_url(), thanks !
     *
     * @param string $url
     *
     * @return bool
     */
    private static function is_dev_url($url = '')
    {
        $is_local_url = false;

        // Trim it up
        $url = strtolower(trim($url));
        // Need to get the host...so let's add the scheme so we can use parse_url
        if (false === strpos($url, 'http://') && false === strpos($url, 'https://')) {
            $url = 'http://' . $url;
        }

        $url_parts = wp_parse_url($url);
        $host      = !empty($url_parts['host']) ? $url_parts['host'] : false;
        if (!empty($url) && !empty($host)) {
            if (false !== ip2long($host)) {
                if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $is_local_url = true;
                }
            } elseif ('localhost' === $host) {
                $is_local_url = true;
            }

            $tlds_to_check = array('.local', ':8888', ':8080', ':8081', '.invalid', '.example', '.test');
            foreach ($tlds_to_check as $tld) {
                if (false !== strpos($host, $tld)) {
                    $is_local_url = true;
                    break;
                }
            }

            if (substr_count($host, '.') > 1) {
                $subdomains_to_check = array('dev.', '*.staging.', 'beta.', 'test.');
                foreach ($subdomains_to_check as $subdomain) {
                    $subdomain = str_replace('.', '(.)', $subdomain);
                    $subdomain = str_replace(array('*', '(.)'), '(.*)', $subdomain);
                    if (preg_match('/^(' . $subdomain . ')/', $host)) {
                        $is_local_url = true;
                        break;
                    }
                }
            }
        }

        return $is_local_url;
    }

    /**
     * Init taxonomy class variable, load this action after all actions on init !
     * Make a public static function for call it from children class...
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function register_taxonomy()
    {
        add_action('init', array(__CLASS__, 'init'), 99999999);
    }

    /**
     * Put in var class the current taxonomy choose by the user
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function init()
    {
        self::$taxo_name      = esc_html__('Post tags', 'simple-tags');
        self::$post_type_name = esc_html__('Posts', 'simple-tags');

        // Custom CPT ?
        if (isset($_GET['cpt']) && !empty($_GET['cpt']) && post_type_exists(sanitize_text_field(wp_unslash($_GET['cpt'])))) {
            $cpt                  = get_post_type_object(sanitize_text_field(wp_unslash($_GET['cpt'])));
            self::$post_type      = $cpt->name;
            self::$post_type_name = $cpt->labels->name;
        }

        // Get compatible taxo for current post type
        $compatible_taxonomies = get_object_taxonomies(self::$post_type);

        // Custom taxo ?
        if (isset($_GET['taxo']) && !empty($_GET['taxo']) && taxonomy_exists(sanitize_text_field(wp_unslash($_GET['taxo'])))) {
            $taxo = get_taxonomy(sanitize_text_field(wp_unslash($_GET['taxo'])));

            // Taxo is compatible ?
            if (in_array($taxo->name, $compatible_taxonomies)) {
                self::$taxonomy  = $taxo->name;
                self::$taxo_name = $taxo->labels->name;
            } else {
                unset($taxo);
            }
        }

        // Default taxo from CPT...
        if (!isset($taxo) && is_array($compatible_taxonomies) && !empty($compatible_taxonomies)) {
            // Take post_tag before category
            if (in_array('post_tag', $compatible_taxonomies, true)) {
                $taxo = get_taxonomy('post_tag');
            } else {
                $taxo = get_taxonomy(current($compatible_taxonomies));
            }

            self::$taxonomy  = $taxo->name;
            self::$taxo_name = $taxo->labels->name;

            // TODO: Redirect for help user that see the URL...
        } elseif (!isset($taxo)) {
            // TODO: We can't wp_die on init as it affect all pages
            //wp_die(esc_html__('This custom post type not have taxonomies.', 'simple-tags'));
        }

        // Free memory
        unset($cpt, $taxo);
    }

    /**
     * Build HTML form for allow user to change taxonomy for the current page.
     *
     * @param string $page_value
     *
     * @return void
     * @author Olatechpro
     */
    public static function boxSelectorTaxonomy($page_value = '')
    {
        echo '<div class="box-selector-taxonomy">' . PHP_EOL;

        echo '<div class="change-taxo">' . PHP_EOL;
        echo '<form action="" method="get">' . PHP_EOL;
        if (!empty($page_value)) {
            echo '<input type="hidden" name="page" value="' . esc_attr($page_value) . '" />' . PHP_EOL;
        }
        $taxonomies = [];
        echo '<select name="cpt" id="cpt-select" class="st-cpt-select">' . PHP_EOL;
        foreach (get_post_types(array('show_ui' => true), 'objects') as $post_type) {
            $taxonomies_children = get_object_taxonomies($post_type->name);
            if (empty($taxonomies_children)) {
                continue;
            }
            $taxonomies[$post_type->name] = $taxonomies_children;
            echo '<option ' . selected($post_type->name, self::$post_type, false) . ' value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->labels->name) . '</option>' . PHP_EOL;
        }
        echo '</select>' . PHP_EOL;

        echo '<select name="taxo" id="taxonomy-select" class="st-taxonomy-select">' . PHP_EOL;
        foreach ($taxonomies as $parent_post => $taxonomy) {
            if (count($taxonomy) > 0) {
                foreach ($taxonomy as $tax_name) {
                    $taxonomy = get_taxonomy($tax_name);
                    if (false === (bool) $taxonomy->show_ui) {
                        continue;
                    }

                    if (self::$post_type == $parent_post) {
                        $class = "";
                    } else {
                        $class = "st-hide-content";
                    }

                    echo '<option ' . selected($tax_name, self::$taxonomy, false) . ' value="' . esc_attr($tax_name) . '" data-post="' . esc_attr($parent_post) . '" class="' . esc_attr($class) . '">' . esc_html($taxonomy->labels->name) . '</option>' . PHP_EOL;
                }
            }
        }
        echo '</select>' . PHP_EOL;

        echo '<input type="submit" class="button" id="submit-change-taxo" value="' . esc_attr__('Change selection', 'simple-tags') . '" />' . PHP_EOL;
        echo '</form>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
    }

    public static function tabSelectorTaxonomy($tab_slug = '', $page_slug = '')
    {
        $current_taxo = isset($_GET["{$tab_slug}_taxo"]) ? sanitize_text_field(wp_unslash($_GET["{$tab_slug}_taxo"])) : get_option("{$tab_slug}_taxo", '');
        $current_cpt  = isset($_GET["{$tab_slug}_cpt"]) ? sanitize_text_field(wp_unslash($_GET["{$tab_slug}_cpt"])) : get_option("{$tab_slug}_cpt", '');

        // Fallbacks if not yet set
        if (empty($current_cpt)) {
            foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
                if (!empty(get_object_taxonomies($pt->name))) {
                    $current_cpt = $pt->name;
                    break;
                }
            }
        }
        if (empty($current_taxo) && $current_cpt) {
            $possible_taxos = get_object_taxonomies($current_cpt);
            if (!empty($possible_taxos)) {
                $current_taxo = $possible_taxos[0];
            }
        }

        // Save for use elsewhere
        self::$post_type = $current_cpt;
        self::$taxonomy  = $current_taxo;

        // Update $post_type_name dynamically
        if ($current_cpt) {
            $post_type_object = get_post_type_object($current_cpt);
            if ($post_type_object) {
                self::$post_type_name = $post_type_object->labels->name;
            }
        }

        // Save to DB for persistence
        update_option("{$tab_slug}_taxo", $current_taxo);
        update_option("{$tab_slug}_cpt", $current_cpt);

        // Build list of post types and taxonomies (only CPTs with taxonomies)
        $taxonomies = [];
        echo '<div class="box-selector-taxonomy tab-taxo-filter tab-taxo-filter-' . esc_attr($tab_slug) . '">' . PHP_EOL;
        echo '<div class="change-taxo">' . PHP_EOL;

        echo '<form action="' . esc_url(admin_url('admin.php')) . '" method="get">' . PHP_EOL;
        $page = !empty($page_slug) ? $page_slug : (isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'st_manage');
        echo '<input type="hidden" name="page" value="' . esc_attr($page) . '" />' . PHP_EOL;
        echo '<input type="hidden" name="page" value="st_manage" />' . PHP_EOL;

        if (!empty($tab_slug)) {
            echo '<input type="hidden" name="tab" value="' . esc_attr($tab_slug) . '" />' . PHP_EOL;
        }

        // CPT dropdown
        echo '<select name="' . esc_attr($tab_slug) . '_cpt" class="st-cpt-select st-cpt-select-' . esc_attr($tab_slug) . '">' . PHP_EOL;
        foreach (get_post_types(['show_ui' => true], 'objects') as $post_type) {
            $taxonomies_children = get_object_taxonomies($post_type->name);
            if (empty($taxonomies_children)) {
                continue;
            }
            $taxonomies[$post_type->name] = $taxonomies_children;
            echo '<option ' . selected($post_type->name, $current_cpt, false) . ' value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->labels->name) . '</option>' . PHP_EOL;
        }
        echo '</select>' . PHP_EOL;

        // Taxonomy dropdown
        echo '<select name="' . esc_attr($tab_slug) . '_taxo" class="st-taxonomy-select st-taxonomy-select-' . esc_attr($tab_slug) . '">' . PHP_EOL;
        foreach ($taxonomies as $parent_post => $taxonomy_list) {
            foreach ($taxonomy_list as $tax_name) {
                $taxonomy = get_taxonomy($tax_name);
                if (false === (bool) $taxonomy->show_ui) {
                    continue;
                }
                $class = ($parent_post === $current_cpt) ? '' : 'st-hide-content';

                echo '<option ' . selected($tax_name, $current_taxo, false) .
                     ' value="' . esc_attr($tax_name) . '"' .
                     ' data-post="' . esc_attr($parent_post) . '"' .
                     ' class="' . esc_attr($class) . '">' .
                     esc_html($taxonomy->labels->name) .
                     '</option>' . PHP_EOL;
            }
        }
        echo '</select>' . PHP_EOL;

        echo '<input type="submit" class="button" value="' . esc_attr__('Change selection', 'simple-tags') . '" />' . PHP_EOL;
        echo '</form>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
    }

    public function maybe_enqueue_frontend_assets_in_admin()
    {

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        if (strpos($screen->id, 'taxopress_page_') === false) {
            return;
        }

        $is_edit_mode = isset($_GET['action']) && in_array($_GET['action'], ['edit'], true);
        if (!$is_edit_mode) {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        // List of pages that need preview functionality
        $preview_pages = [
            'st_terms_display',
            'st_related_posts',
            'st_post_tags',
        ];

        if (!in_array($current_page, $preview_pages, true)) {
            return;
        }

        if ((int) SimpleTags_Plugin::get_option_value('disable_admin_frontend_scripts') === 1) {
            return;
        }

        if (! apply_filters('taxopress_load_admin_frontend_scripts', true)) {
            return;
        }

        // Register and enqueue assets
        wp_register_script('taxopress-frontend-js', STAGS_URL . '/assets/frontend/js/frontend.js', array('jquery'), STAGS_VERSION);
        wp_register_style('taxopress-frontend-css', STAGS_URL . '/assets/frontend/css/frontend.css', array(), STAGS_VERSION, 'all');

        // Enqueue the assets
        wp_enqueue_script('taxopress-frontend-js');
        wp_enqueue_style('taxopress-frontend-css');
    }
    /**
     * Init somes JS and CSS need for TaxoPress.
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function admin_enqueue_scripts()
    {
        global $pagenow;

        $select_2_page = false;
        if ((isset($_GET['page']) && in_array($_GET['page'], ['st_posts', 'st_terms', 'st_autolinks', 'st_autoterms', 'st_autoterms_schedule', 'st_terms_display', 'st_related_posts', 'st_post_tags', 'st_mass_terms'])) || in_array($pagenow, ['post.php', 'edit.php', 'post-new.php'])) {
            $select_2_page = true;
        }

        do_action('taxopress_admin_class_before_assets_register');

        //color picker style
        wp_enqueue_style('wp-color-picker');

        // Helper TaxoPress
        wp_register_script('st-helper-add-tags', STAGS_URL . '/assets/js/helper-add-tags.js', array('jquery'), STAGS_VERSION);
        wp_localize_script('st-helper-add-tags', 'stHelperAddTagsL10n', [
            'nonce' => wp_create_nonce('st-admin-js'),
        ]);
        wp_register_script('st-helper-options', STAGS_URL . '/assets/js/helper-options.js', array('jquery', 'wp-color-picker'), STAGS_VERSION);

        // Register CSS
        wp_register_style('st-admin', STAGS_URL . '/assets/css/admin.css', array(), STAGS_VERSION, 'all');
        wp_register_style('st-admin-global', STAGS_URL . '/assets/css/admin-global.css', array(), STAGS_VERSION, 'all');

        // Register tooltip
        wp_register_script(
            'taxopress-admin-tooltip',
            STAGS_URL . '/assets/lib/tooltip/js/tooltip.min.js',
            ['jquery'],
            STAGS_VERSION
        );

        wp_register_style(
            'taxopress-admin-tooltip',
            STAGS_URL . '/assets/lib/tooltip/css/tooltip.min.css',
            [],
            STAGS_VERSION
        );

        //enqueue tooltip
        wp_enqueue_script('taxopress-admin-tooltip');
        wp_enqueue_style('taxopress-admin-tooltip');

        // Register Select 2
        wp_register_script(
            'taxopress-admin-select2',
            STAGS_URL . '/assets/lib/select2/js/select2.full.min.js',
            ['jquery'],
            STAGS_VERSION
        );

        wp_register_style(
            'taxopress-admin-select2',
            STAGS_URL . '/assets/lib/select2/css/select2.min.css',
            [],
            STAGS_VERSION
        );

        if ($select_2_page) {
            wp_enqueue_script('taxopress-admin-select2');
            wp_enqueue_style('taxopress-admin-select2');
        }

        //Register and enqueue admin js
        $script_dependencies = ['jquery'];
        if ($select_2_page) {
            $script_dependencies[] = 'taxopress-admin-select2';
            $script_dependencies[] = 'wp-util';
        }

        $taxopress_pages = taxopress_admin_pages();

        if (isset($_GET['page']) && in_array($_GET['page'], $taxopress_pages)) {
            wp_enqueue_media();
        }

        wp_register_script('st-admin-js', STAGS_URL . '/assets/js/admin.js', $script_dependencies, STAGS_VERSION);
        wp_enqueue_script('st-admin-js');
        //localize script
        wp_localize_script('st-admin-js', 'st_admin_localize', [
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'select_valid' => esc_html__('Please select a valid', 'simple-tags'),
            'check_nonce' => wp_create_nonce('st-admin-js'),
            'select_default_label' => esc_html__('Select Default Post Thumb', 'simple-tags'),
            'use_media_label' => esc_html__('Use this media', 'simple-tags'),
            'existing_content_admin_label' => esc_html__('Edit the current setting.', 'simple-tags'),
            'request_error' => esc_html__('The request could not be completed. Please try again.', 'simple-tags'),
            'autoterm_admin_url' => admin_url('admin.php?page=st_autoterms'),
            'no_terms_message' => esc_html__('No terms will be deleted', 'simple-tags'),
            'terms_count_message' => esc_html__(' terms will be deleted.', 'simple-tags'),
            'checking_terms_message' => esc_html__('Checking terms...', 'simple-tags'),
            'terms_error'            => esc_html__('An error occurred while checking terms.', 'simple-tags'),
            'post_required' => esc_html__('Select a post to see a preview.', 'simple-tags'),
            'save_settings' => esc_html__('Auto Term ID missing. Kindly save the auto term before using this feature.', 'simple-tags'),
            'delete_label' => esc_html__('Delete', 'simple-tags'),
            'ai_nonce' => wp_create_nonce('taxopress-ai-ajax-nonce'),
            'post_title'              => '%post_title%',
            'post_permalink'          => '%post_permalink%',
            'post_date'               => '%post_date%',
            'post_thumb_url'          => '%post_thumb_url%',
            'post_category'           => '%post_category%',
            'post_size'               => '%post_size%',
            'post_color'              => '%post_color%',
            'merge_cancelled'         => esc_html__('Merge has been cancelled.', 'simple-tags'),
            'cancel_label'            => esc_html__('Cancel', 'simple-tags'),
            'paused_label'            => esc_html__('Pause.', 'simple-tags'),
            'continue_label'          => esc_html__('Continue', 'simple-tags'),
            'merge_in_progress'       => esc_html__('Merging terms. Please wait...', 'simple-tags'),
            'merge_attached_data'     => esc_html__('Merging %1$s terms attached to %2$s posts. Please wait...', 'simple-tags'),
            'merge_large_data'        => esc_html__('Large dataset detected, terms will be merged in batches of 20!', 'simple-tags'),
            'merge_none_merged'       => esc_html__('No terms were merged.', 'simple-tags'),
            'batch_merge_progress'     => esc_html__('Batch %1$s of %2$s merged.', 'simple-tags'),
            'terms_merged_text'       => esc_html__('terms merged', 'simple-tags'),
            'posts_updated_text'      => esc_html__('posts updated', 'simple-tags'),
            'merge_success_update' => esc_html__('All terms merged into %s', 'simple-tags'),
            'ajax_merge_terms_error'  => esc_html__('AJAX error on batch', 'simple-tags'),
            'batch_error_text'        => esc_html__('Error on batch %1$s:', 'simple-tags'),
            'enable_merge_terms_slug' => SimpleTags_Plugin::get_option_value('enable_merge_terms_slug'),
            'enable_add_terms_slug' => SimpleTags_Plugin::get_option_value('enable_add_terms_slug'),
            'enable_remove_terms_slug' => SimpleTags_Plugin::get_option_value('enable_remove_terms_slug'),
            'enable_rename_terms_slug' => SimpleTags_Plugin::get_option_value('enable_rename_terms_slug'),
            'enable_mass_edit_terms_slug' => SimpleTags_Plugin::get_option_value('enable_mass-edit_terms_slug'),
            'select_post_label'        => esc_html__('Search Posts...', 'simple-tags'),
            'loading'                  => esc_html__('Loading...', 'simple-tags'),
            'preview_error'            => esc_html__('Error loading preview', 'simple-tags'),
            'enable_ibm_watson_ai_source' => SimpleTags_Plugin::get_option_value('enable_ibm_watson_ai_source'),
            'enable_dandelion_ai_source' => SimpleTags_Plugin::get_option_value('enable_dandelion_ai_source'),
            'enable_lseg_ai_source'    => SimpleTags_Plugin::get_option_value('enable_lseg_ai_source'),
            'plugin_url' => STAGS_URL,
            'using_default_text' => __('Using default TaxoPress image', 'simple-tags'),
            'select_image_label' => esc_html__('Select Media', 'simple-tags'),
            'change_image_label' => esc_html__('Change Media', 'simple-tags'),
            'use_default_label' => esc_html__('Use Default', 'simple-tags'),
            'see_more_suggestions' => esc_html__('See More', 'simple-tags'),
            'see_less_suggestions' => esc_html__('See Less', 'simple-tags'),
            'apply_selected' => esc_html__('Apply Selected', 'simple-tags'),
            'close_suggestions' => esc_html__('Close', 'simple-tags'),
            'select_suggestion_alert' => esc_html__('Please select at least one suggestion to apply.', 'simple-tags'),
            'multiple_merge_warning' => esc_html__('You have selected multiple merge suggestions. All selected terms will be merged into a single new term. Do you want to continue?', 'simple-tags'),
            'no_merge_suggestions' => esc_html__('No merge suggestions found for this taxonomy.', 'simple-tags'),
            'suggested_terms_title' => esc_html__('Suggested Terms to Merge:', 'simple-tags'),
            'duplicates_text' => esc_html__('duplicates', 'simple-tags'),
            'reason_text' => esc_html__('Reason:', 'simple-tags'),
            'select_all_label' => esc_html__('Select All', 'simple-tags'),
            'deselect_all_label' => esc_html__('Deselect All', 'simple-tags'),
            'terms_to_merge_text' => esc_html__('Terms to Merge', 'simple-tags'),
            'new_term_text' => esc_html__('New Term', 'simple-tags'),
            'reasons_text' => esc_html__('Reasons', 'simple-tags'),
        ]);


        //Register remodal assets
        wp_register_script('st-remodal-js', STAGS_URL . '/assets/js/remodal.min.js', array('jquery'), STAGS_VERSION);
        wp_register_style('st-remodal-css', STAGS_URL . '/assets/css/remodal.css', array(), STAGS_VERSION, 'all');
        wp_register_style('st-remodal-default-theme-css', STAGS_URL . '/assets/css/remodal-default-theme.css', array(), STAGS_VERSION, 'all');

        // Register location
        $wp_post_pages = array('post.php', 'post-new.php');
        $wp_page_pages = array('page.php', 'page-new.php');

        wp_enqueue_style('st-admin-global');

        // Common Helper for Post, Page and Plugin Page
        if (
            in_array($pagenow, $wp_post_pages) ||
            (in_array($pagenow, $wp_page_pages) && is_page_have_tags()) ||
            (isset($_GET['page']) && in_array($_GET['page'], $taxopress_pages))
        ) {
            wp_enqueue_script('st-remodal-js');
            wp_enqueue_style('st-remodal-css');
            wp_enqueue_style('st-remodal-default-theme-css');
            wp_enqueue_style('st-admin');

            do_action('taxopress_admin_class_after_styles_enqueue');
        }

        // add jQuery tabs for options page. Use jQuery UI Tabs from WP
        if (isset($_GET['page']) && in_array($_GET['page'], array('st_options', 'st_terms_display', 'st_post_tags', 'st_related_posts',))) {
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_script('st-helper-options');
        }

        do_action('taxopress_admin_class_after_assets_enqueue');
    }

    /**
     * Add settings page on WordPress admin menu
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function admin_menu()
    {
        self::$admin_url = admin_url('admin.php?page=' . self::MENU_SLUG);

        add_menu_page(
            __('TaxoPress: Options', 'simple-tags'),
            __('TaxoPress', 'simple-tags'),
            'admin_simple_tags',
            self::MENU_SLUG,
            array(
                __CLASS__,
                'page_options',
            ),
            'dashicons-tag',
            69
        );
        add_submenu_page(
            self::MENU_SLUG,
            __('TaxoPress: Options', 'simple-tags'),
            __('Settings', 'simple-tags'),
            'admin_simple_tags',
            self::MENU_SLUG,
            array(
                __CLASS__,
                'page_options',
            )
        );
    }

    /**
     * Build HTML for page options, manage also save/reset settings
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function page_options()
    {
        // Get options
        $options = (array) SimpleTags_Plugin::get_option();

        if (current_user_can('admin_simple_tags')) {
            // Update or reset options
            if (isset($_POST['updateoptions'])) {
                $dashboard_options      = taxopress_dashboard_options();
                $dashboard_option_keys  = array_column($dashboard_options, 'option_key');

                check_admin_referer('updateresetoptions-simpletags');

                $sanitized_options = [];

                $is_fast_update_submission = isset($_GET['page']) && $_GET['page'] === 'st_taxopress_ai';

                // This settings has been migrated to fast update screen so only update from there
                if ($is_fast_update_submission) {
                    // add taxopress ai post type and taxonomies options so we can have all post types. TODO: This need to be a filter
                    foreach (get_post_types(['public' => true], 'names') as $post_type => $post_type_object) {
                        if ($post_type == 'post') {
                            $opt_default_value = 'post_tag';
                        } else {
                            $opt_default_value = 0;
                        }
                        $options['taxopress_ai_' . $post_type . '_metabox_default_taxonomy'] = $opt_default_value;
                        $options['taxopress_ai_' . $post_type . '_metabox_display_option'] = 'default';
                        $options['taxopress_ai_' . $post_type . '_support_private_taxonomy'] = 0;

                        $options['taxopress_ai_' . $post_type . '_metabox_orderby'] = 'count';
                        $options['taxopress_ai_' . $post_type . '_metabox_order'] = 'desc';
                        $options['taxopress_ai_' . $post_type . '_metabox_maximum_terms'] = 45;
                        $options['taxopress_ai_' . $post_type . '_metabox_show_post_count'] = 0;
                        $options['taxopress_ai_' . $post_type . '_metabox_show_term_slug'] = 0;

                        $options['taxopress_ai_' . $post_type . '_minimum_term_length'] = 2;
                        $options['taxopress_ai_' . $post_type . '_maximum_term_length'] = 40;

                        $options['taxopress_ai_' . $post_type . '_exclusions'] = '';
                        $options['enable_taxopress_ai_' . $post_type . '_metabox'] = $opt_default_value;
                        $options['taxopress_ai_' . $post_type . '_metabox_filters'] = 1;
                        foreach (['post_terms', 'existing_terms', 'suggest_local_terms', 'create_terms'] as $taxopress_ai_tab) {
                            $options['enable_taxopress_ai_' . $post_type . '_' . $taxopress_ai_tab . '_tab'] = $opt_default_value;
                        }
                    }

                    // add metabox post type and taxonomies options so we can have all post types. TODO: This need to be a filter
                    foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
                        if (in_array($role_name, ['administrator', 'editor', 'author', 'contributor'])) {
                            $enable_acess_default_value = 1;
                        } else {
                            $enable_acess_default_value = 0;
                        }
                        $options['enable_' . $role_name . '_metabox'] = $enable_acess_default_value;
                        $options['enable_restrict' . $role_name . '_metabox'] = $enable_acess_default_value;

                        // ONLY admin + editor default to can edit
                        if (!isset($options['enable_edit_' . $role_name . '_metabox'])) {
                            $options['enable_edit_' . $role_name . '_metabox'] = in_array($role_name, ['administrator', 'editor']) ? 1 : 0;
                        }

                        $options['enable_metabox_' . $role_name . ''] = [];
                        $options['remove_taxonomy_metabox_' . $role_name . ''] = [];
                    }
                }

                foreach ($options as $key => $value) {
                    // If this is NOT a Fast Update submission, preserve metabox/metabox-access keys
                    if (!$is_fast_update_submission && preg_match('/^(enable_taxopress_ai_|taxopress_ai_|enable_.*_metabox|enable_restrict.*_metabox|enable_edit_.*_metabox|enable_metabox_|remove_taxonomy_metabox_)/', $key)) {
                        // Keep existing value as-is
                        if (!is_array($value)) {
                            $sanitized_options[$key] = taxopress_sanitize_text_field($value);
                        } else {
                            $sanitized_options[$key] = map_deep($value, 'sanitize_text_field');
                        }
                        continue;
                    }

                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $post_value = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

                    if (empty($post_value) && in_array($key, $dashboard_option_keys)) {
                        $post_value = SimpleTags_Plugin::get_option_value($key);
                    }

                    if (!is_array($post_value)) {
                        $sanitized_options[$key] = taxopress_sanitize_text_field($post_value);
                    } else {
                        $sanitized_options[$key] = map_deep($post_value, 'sanitize_text_field');
                    }
                }
                $options = $sanitized_options;

                SimpleTags_Plugin::set_option($options);

                do_action('simpletags_settings_save_general_end');
                do_action('taxopress_settings_saved');

                add_settings_error(__CLASS__, __CLASS__, esc_html__('Options saved', 'simple-tags'), 'updated taxopress-notice');
            } elseif (isset($_POST['reset_options'])) {
                check_admin_referer('updateresetoptions-simpletags');

                SimpleTags_Plugin::set_default_option();

                add_settings_error(__CLASS__, __CLASS__, esc_html__('TaxoPress options resetted to default options!', 'simple-tags'), 'updated taxopress-notice');
            } else {
                //add_settings_error(__CLASS__, __CLASS__, esc_html__('Settings updated', 'simple-tags'), 'updated');
            }
        }

        settings_errors(__CLASS__);
        include STAGS_DIR . '/views/admin/page-settings.php';
    }

    /**
     * Get terms for a post, format terms for input and autocomplete usage
     *
     * @param string $taxonomy
     * @param integer $post_id
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function getTermsToEdit($taxonomy = 'post_tag', $post_id = 0)
    {
        $post_id = (int) $post_id;
        if (!$post_id) {
            return '';
        }

        $is_mass_edit_page = isset($_GET['page']) && $_GET['page'] === 'st_mass_terms';
        $show_slug = SimpleTags_Plugin::get_option_value('enable_mass-edit_terms_slug');

        if ($is_mass_edit_page) {
            $term_objects = wp_get_post_terms($post_id, $taxonomy);
            if (empty($term_objects) || is_wp_error($term_objects)) {
                return '';
            }

            $terms = array();
            foreach ($term_objects as $term) {
                if ($show_slug) {
                    if (!empty($term->slug)) {
                        $terms[] = $term->name . ' (' . $term->slug . ')';
                    } else {
                        $terms[] = $term->name;
                    }
                } else {
                    $terms[] = $term->name;
                }
            }

            $terms = join(', ', $terms);
        } else {
            $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
            if (empty($terms) || is_wp_error($terms)) {
                return '';
            }

            $terms = array_unique($terms);
            $terms = join(', ', $terms);
        }

        $terms = esc_attr($terms);
        $terms = apply_filters('tags_to_edit', $terms);

        return $terms;
    }

    /**
     * Default content for meta box of TaxoPress
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function getDefaultContentBox()
    {
        if ((int) wp_count_terms(array('taxonomy' => 'post_tag', 'hide_empty' => false)) == 0) { // TODO: Custom taxonomy
            return esc_html__('This feature requires at least 1 tag to work. Begin by adding tags!', 'simple-tags');
        } else {
            return esc_html__('This feature works only with activated JavaScript. Activate it in your Web browser so you can!', 'simple-tags');
        }
    }

    /**
     * A short public static function for display the same copyright on all admin pages
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function printAdminFooter()
    {
        /* ?>
        <p class="footer_st"><?php printf( __( 'Thanks for using TaxoPress | <a href="https://taxopress.com/">TaxoPress.com</a> | Version %s', 'simple-tags' ), STAGS_VERSION ); ?></p>
        <?php */
    }

    /**
     * A short public static function for display the same copyright on all taxopress admin pages
     *
     * @return void
     * @author Olatechpro
     */
    public static function taxopress_admin_footer()
    {

        $taxopress_pages = taxopress_admin_pages();

        if (isset($_GET['page']) && in_array($_GET['page'], $taxopress_pages)) {
            ?>
            <p class="footer_st">
                <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            printf(__('Thanks for using TaxoPress | %1sTaxoPress.com%2s | Version %3s', 'simple-tags'), '<a href="https://taxopress.com/">', '</a>', esc_html(STAGS_VERSION)); ?>
            </p>
            <?php
        }
    }

    /**
     * Ouput formatted options
     *
     * @param array $option_data
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function print_options($option_data)
    {
        // Get options
        $option_actual = SimpleTags_Plugin::get_option();

        // Generate output
        $output = '';
        foreach ($option_data as $section => $options) {
            $colspan       = count($options) > 1 ? 'colspan="2"' : '';

            if ($section === 'legacy') {
                $table_sub_tab = '<div class="st-legacy-subtab">
                <span class="active" data-content=".legacy-tag-cloud-content">' . esc_html__("Tag Cloud", "simple-tags") . '</span> |
                <span data-content=".legacy-post-tags-content">' . esc_html__("Tags for Current Post", "simple-tags") . '</span> |
                <span data-content=".legacy-related-posts-content">' . esc_html__("Related Posts", "simple-tags") . '</span> |
                <span data-content=".legacy-auto-link-content">' . esc_html__("Auto Links", "simple-tags") . '</span>
                </div>' . PHP_EOL;
            } elseif ($section === 'taxopress-ai') {
                $table_sub_tab_lists = [];
                $pt_index = 0;
                foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
                    if (!in_array($post_type, ['attachment'])) {
                        $active_pt = ($pt_index === 0) ? 'active' : '';
                        $table_sub_tab_lists[] = '<span class="' . $active_pt . '" data-content=".taxopress-ai-' . $post_type . '-content">' . esc_html($post_type_object->labels->name) . '</span>';
                        $pt_index++;
                    }
                }
                $table_sub_tab = '<div class="st-taxopress-ai-subtab">' . join(' | ', $table_sub_tab_lists) . '</div>' . PHP_EOL;
            } elseif ($section === 'metabox') {
                $table_sub_tab_lists = [];
                $pt_index = 0;
                foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
                    $active_pt = ($pt_index === 0) ? 'active' : '';
                    $table_sub_tab_lists[] = '<span class="' . $active_pt . '" data-content=".metabox-' . $role_name . '-content">' . esc_html(translate_user_role($role_info['name'])) . '</span>';
                    $pt_index++;
                }
                $table_sub_tab = '<div class="st-metabox-subtab">' . join(' | ', $table_sub_tab_lists) . '</div>' . PHP_EOL;
            } else {
                $table_sub_tab = '';
            }

            $output .= '<div class="group" id="' . sanitize_title($section) . '">' . PHP_EOL;
            $output .= $table_sub_tab;
            $output .= '<fieldset class="options">' . PHP_EOL;
            $output .= '<legend>' . self::getNiceTitleOptions($section) . '</legend>' . PHP_EOL;
            $output .= '<table class="form-table">' . PHP_EOL;
            foreach ((array) $options as $option) {
                $class = '';
                if (in_array($section, ['legacy', 'taxopress-ai', 'metabox'])) {
                    $class = $option[5];
                }

                // Helper
                if ($option[2] == 'helper') {
                    $output .= '<tr style="vertical-align: middle;" class="' . $class . '"><td class="helper stpexplan" ' . $colspan . '>' . stripslashes($option[4]) . '</td></tr>' . PHP_EOL;
                    continue;
                }

                // Fix notices
                if (!isset($option_actual[$option[0]])) {
                    $option_actual[$option[0]] = '';
                }

                // Add our custom core_terms_promo type
                if ($option[2] == 'core_terms_promo') {
                    if (isset($option[4]) && !empty($option[4])) {
                        $output .= '<tr style="vertical-align: middle;" class="' . $class . '"><td class="helper" ' . $colspan . '>' . apply_filters('taxopress_admin_manage_' . $option[0], $option[4]) . '</td></tr>' . PHP_EOL;
                    }
                    continue;
                }

                $input_type = '';
                $desc_html_tag = 'div';
                switch ($option[2]) {
                    case 'radio':
                        $input_type = array();
                        foreach ($option[3] as $value => $text) {
                            $input_type[] = '<label><input type="radio" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($value) . '" ' . checked($value, $option_actual[$option[0]], false) . ' /> ' . $text . '</label>' . PHP_EOL;
                        }
                        $input_type = implode('<br />', $input_type);
                        break;

                    case 'checkbox':
                        $desc_html_tag = 'span';
                        $input_type    = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option[3]) . '" ' . (($option_actual[$option[0]]) ? 'checked="checked"' : '') . ' />' . PHP_EOL;
                        break;

                    case 'multiselect':
                        $desc_html_tag = 'div';
                        $input_type = array();
                        foreach ($option[3] as $field_name => $text) {
                            $selected_option = (is_array($option_actual[$option[0]]) && in_array($field_name, $option_actual[$option[0]])) ? true : false;
                            $input_type[] = '<label><input type="checkbox" id="' . $option[0] . '-' . $field_name . '" name="' . $option[0] . '[]" value="' . $field_name . '" ' . checked($selected_option, true, false) . ' /> ' . $text . '</label> <br />' . PHP_EOL;
                        }
                        $input_type = implode('<br />', $input_type);
                        break;

                    case 'sub_multiple_checkbox':
                        $desc_html_tag = 'div';
                        $input_type = array();
                        foreach ($option[3] as $field_name => $field_option) {
                            $checked_option = !empty($option_actual[$field_name]) ? (int) $option_actual[$field_name] : 0;
                            $selected_option = ($checked_option > 0) ? true : false;
                            $field_description = !empty($field_option['description']) ? '<br /><span class="description stpexplan">' . $field_option['description'] . '</span>' : '';
                            $input_type[] = '<label><input type="checkbox" id="' . $option[0] . '" name="' . $field_name . '" value="1" ' . checked($selected_option, true, false) . ' /> ' . $field_option['label'] . '</label> ' . $field_description . '<br />' . PHP_EOL;
                        }
                        $input_type = implode('<br />', $input_type);
                        break;

                    case 'dropdown':// legacy
                        $selopts = explode('/', $option[3]);
                        $seldata = '';
                        foreach ((array) $selopts as $sel) {
                            $seldata .= '<option value="' . esc_attr($sel) . '" ' . ((isset($option_actual[$option[0]]) && $option_actual[$option[0]] == $sel) ? 'selected="selected"' : '') . ' >' . ucfirst($sel) . '</option>' . PHP_EOL;
                        }
                        $input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . PHP_EOL;
                        break;


                    case 'select':
                        $selopts = $option[3];
                        $seldata = '';
                        foreach ((array) $selopts as $sel_key => $sel_label) {
                            $seldata .= '<option value="' . esc_attr($sel_key) . '" ' . ((isset($option_actual[$option[0]]) && $option_actual[$option[0]] == $sel_key) ? 'selected="selected"' : '') . ' >' . ucfirst($sel_label) . '</option>' . PHP_EOL;
                        }
                        $input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . PHP_EOL;
                        break;

                    case 'select_with_icon':
                        $selopts = $option[3];
                        $seldata = '';
                        foreach ((array) $selopts as $sel_key => $sel_label) {
                            $seldata .= '<option value="' . esc_attr($sel_key) . '" ' . ((isset($option_actual[$option[0]]) && $option_actual[$option[0]] == $sel_key) ? 'selected="selected"' : '') . ' >' . ucfirst($sel_label) . '</option>' . PHP_EOL;
                        }

                        $icon_class = isset($option[6]['icon']) ? $option[6]['icon'] : 'dashicons-lock';
                        $modal_content = isset($option[6]['modal']) ? $option[6]['modal'] : '';
                        $disabled = !empty($option[8]) && isset($option[8]['disabled']) ? 'disabled="disabled"' : '';
                        $class_attr = isset($option[5]) ? esc_attr($option[5]) : '';
                        $icon_wrapper_class = isset($option[6]['icon_wrapper_class']) ? esc_attr($option[6]['icon_wrapper_class']) : 'taxopress-select-icon';
                        $modal_wrapper_class = isset($option[6]['modal_wrapper_class']) ? esc_attr($option[6]['modal_wrapper_class']) : 'taxopress-select-icon-modal';

                        $input_type = '<div class="' . $class_attr . '">
								<select id="' . $option[0] . '" name="' . $option[0] . '" ' . $disabled . '>' . $seldata . '</select>
								<span class="' . $icon_wrapper_class . ' dashicons ' . esc_attr($icon_class) . '">
									<div class="' . $modal_wrapper_class . '">' . $modal_content . '</div>
								</span>
							</div>' . PHP_EOL;
                        break;

                    case 'multiselect_with_desc_top':
                        $desc_html_tag = 'div';
                        $input_type_array = array();
                        $prefix = !empty($field_description) ? '<' . $desc_html_tag . ' class="stpexplan">' . $field_description . '</' . $desc_html_tag . '>' : '';
                        foreach ($field_options as $option_key => $option_label) {
                            $field_value = isset($option_actual[$field_id]) ? $option_actual[$field_id] : array();
                            $selected_option = (is_array($field_value) && in_array($option_key, $field_value)) ? true : false;
                            $input_type_array[] = '<label><input type="checkbox" id="' . esc_attr($field_id . '-' . $option_key) . '" name="' . esc_attr($field_id) . '[]" value="' . esc_attr($option_key) . '" ' . checked($selected_option, true, false) . ' /> ' . esc_html($option_label) . '</label><br />';
                        }
                        $input_type = $prefix . implode('', $input_type_array);
                        $field_description = '';
                        break;

                    case 'text-color':
                        $input_type = '<input type="text" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[$option[0]]) . '" class="text-color ' . $option[3] . '" />' . PHP_EOL;
                        break;

                    case 'text':
                        $input_type = '<input type="text" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[$option[0]]) . '" class="' . $option[3] . '" />' . PHP_EOL;
                        break;

                    case 'licence_field':
                        $input_type = '<input type="text" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option[3]) . '" class="' . $option[5] . '" />' . PHP_EOL;
                        break;

                    case 'number':
                        $min_attr = isset($option[6]) ? ' min="' . esc_attr($option[6]) . '"' : '';
                        $input_type = '<input type="number" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[$option[0]]) . '" class="' . $option[3] . '"' . $min_attr . ' />' . PHP_EOL;
                        break;

                    case 'textarea':
                        $rows_attr = isset($option[7]['rows']) ? ' rows="' . esc_attr($option[7]['rows']) . '"' : ' rows="4"';
                        $placeholder_attr = isset($option[7]['placeholder']) ? ' placeholder="' . esc_attr($option[7]['placeholder']) . '"' : '';
                        $width_attr = (!empty($option[7]['width'])) ? ' style="width:' . esc_attr($option[7]['width']) . ';"' : ' style="width:100%; max-width:600px;"';
                        $input_type = '<textarea id="' . $option[0] . '" name="' . $option[0] . '"' . $rows_attr . $placeholder_attr . $width_attr . ' class="' . $option[3] . '">' . esc_textarea($option_actual[$option[0]]) . '</textarea>' . PHP_EOL;
                        break;
                }

                if (is_array($option[2])) {
                    $input_type = '<input type="' . $option[2]["type"] . '" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr($option_actual[$option[0]]) . '" class="' . $option[3] . '" ' . $option[2]["attr"] . ' />' . PHP_EOL;
                }

                // Additional Information
                $extra_prefix = '';
                $extra_suffix = '';
                if (!empty($option[4])) {
                    if ($option[2] == 'sub_multiple_checkbox') {
                        $extra_prefix = '<' . $desc_html_tag . ' class="stpexplan">' . $option[4] . '</' . $desc_html_tag . '>' . PHP_EOL;
                    } else {
                        $extra_suffix = '<' . $desc_html_tag . ' class="stpexplan">' . $option[4] . '</' . $desc_html_tag . '>' . PHP_EOL;
                    }
                }

                // Output
                $output .= '<tr style="vertical-align: top;" class="' . $class . '"><th scope="row"><label for="' . $option[0] . '">' . $option[1] . '</label></th><td>' . $extra_prefix . ' ' . $input_type . ' ' . $extra_suffix . '</td></tr>' . PHP_EOL;
            }
            $output .= '</table>' . PHP_EOL;
            $output .= '</fieldset>' . PHP_EOL;
            $output .= '</div>' . PHP_EOL;
        }

        return $output;
    }

    /**
     * Get nice title for tabs title option
     *
     * @param string $id
     *
     * @return string
     */
    public static function getNiceTitleOptions($id = '')
    {
        switch ($id) {
            case 'administration':
                return esc_html__('Administration', 'simple-tags');
            case 'auto-links':
                return esc_html__('Auto link', 'simple-tags');
            case 'features':
                return esc_html__('Features', 'simple-tags');
            case 'embeddedtags':
                return esc_html__('Embedded Tags', 'simple-tags');
            case 'tagspost':
                return esc_html__('Tags for Current Post', 'simple-tags');
            case 'relatedposts':
                return esc_html__('Related Posts', 'simple-tags');
            case 'legacy':
                return esc_html__('Legacy', 'simple-tags');
            case 'posts':
                return esc_html__('Posts', 'simple-tags');
            case 'taxopress-ai':
                return esc_html__('Metaboxes', 'simple-tags');
            case 'metabox':
                return esc_html__('Metabox Access', 'simple-tags');
            case 'linked_terms':
                return esc_html__('Linked Terms', 'simple-tags');
            case 'synonyms':
                return esc_html__('Term Synonyms', 'simple-tags');
            case 'licence':
                return esc_html__('License', 'simple-tags');
            case 'hidden_terms':
                return esc_html__('Hidden Terms', 'simple-tags');
            case 'frontend_scripts':
                return esc_html__('Frontend Scripts', 'simple-tags');
            case 'manage_terms':
                return esc_html__('Manage Terms', 'simple-tags');
            case 'mass_edit_terms':
                return esc_html__('Mass Edit Terms', 'simple-tags');
            case 'core_linked_terms':
                return esc_html__('Linked Terms', 'simple-tags');
            case 'core_synonyms_terms':
                return esc_html__('Term Synonyms', 'simple-tags');
            case 'legacy_ai_sources':
                return esc_html__('Auto Terms', 'simple-tags');
        }

        return '';
    }

    /**
     * This method allow to check if the DB is up to date, and if a upgrade is need for options
     * TODO, useful or delete ?
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function upgrade()
    {
        // Get current version number
        $current_version = get_option(STAGS_OPTIONS_NAME . '-version');

        // Upgrade needed ?
        if ($current_version == false || version_compare($current_version, STAGS_VERSION, '<')) {
            $current_options = get_option(STAGS_OPTIONS_NAME);
            $default_options = (array) include(STAGS_DIR . '/inc/helper.options.default.php');

            // Add new options
            foreach ($default_options as $key => $default_value) {
                if (!isset($current_options[$key])) {
                    $current_options[$key] = $default_value;
                }
            }

            // Remove old options
            /*foreach ($current_options as $key => $current_value) {
                if (!isset($default_options[$key])) {
                    unset($current_options[$key]);
                }
            }*/

            update_option(STAGS_OPTIONS_NAME . '-version', STAGS_VERSION);
            update_option(STAGS_OPTIONS_NAME, $current_options);
        }
    }

    /**
     * Make a simple SQL query with some args for get terms for ajax display
     *
     * @param string $taxonomy
     * @param string $search
     * @param string $order_by
     * @param string $order
     *
     * @return array
     * @author WebFactory Ltd
     */
    public static function getTermsForAjax($taxonomy = 'post_tag', $search = '', $order_by = 'name', $order = 'ASC', $limit = 0)
    {
        global $wpdb;

        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $allowed_orderby = [
            'name'   => 't.name',
            'count'  => 'tt.count',
            'random' => 'RAND()',
        ];

        if (isset($allowed_orderby[$order_by])) {
            $order_by_sql = $allowed_orderby[$order_by];
        } else {
            $order_by_sql = $allowed_orderby['name'];
        }

        $limit_sql = '';
        $limit = (int) $limit;
        if ($limit > 0) {
            $limit_sql = $wpdb->prepare('LIMIT 0, %d', $limit);
        }

        if ($taxonomy == 'linked_term_taxonomies') {
            $taxonomies = SimpleTags_Plugin::get_option_value('linked_terms_taxonomies');
            if (empty($taxonomies) || !is_array($taxonomies)) {
                $taxonomies = ['category', 'post_tag'];
            }
            $taxonomies_list = "'" . implode("', '", $taxonomies) . "'";
        }

        if (!empty($search)) {
            if ($taxonomy == 'linked_term_taxonomies') {
                $query = $wpdb->prepare(
                    "
					SELECT DISTINCT t.name, t.term_id, tt.taxonomy
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy IN ($taxonomies_list)
					AND t.name LIKE %s
					ORDER BY $order_by_sql $order $limit_sql
					",
                    '%' . $wpdb->esc_like($search) . '%'
                );
            } else {
                $query = $wpdb->prepare(
                    "
					SELECT DISTINCT t.name, t.slug, t.term_id, tt.taxonomy
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					AND t.name LIKE %s
					ORDER BY $order_by_sql $order $limit_sql
				",
                    $taxonomy,
                    '%' . $wpdb->esc_like($search) . '%'
                );
            }
            return $wpdb->get_results($query);
        } else {
            if ($taxonomy == 'linked_term_taxonomies') {
                $query = "
					SELECT DISTINCT t.name, t.term_id, tt.taxonomy
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy IN ($taxonomies_list)
					ORDER BY $order_by_sql $order $limit_sql
				";
            } else {
                $query = $wpdb->prepare("
					SELECT DISTINCT t.name, t.slug, t.term_id, tt.taxonomy
					FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					ORDER BY $order_by_sql $order $limit_sql
				", $taxonomy);
            }

            return $wpdb->get_results($query);
        }
    }

    /**
     * Plugin installer/uograde code
     *
     * @return void
     */
    public static function plugin_installer_upgrade_code()
    {
        if (!get_option('taxopress_3_23_0_upgrade_completed')) {
            $options = SimpleTags_Plugin::get_option();

            // add metabox default values
            $tax_names = array_keys(get_taxonomies([], 'names'));
            foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
                if (in_array($role_name, ['administrator', 'editor', 'author', 'contributor'])) {
                    $enable_acess_default_value = 1;
                } else {
                    $enable_acess_default_value = 0;
                }
                $options['enable_' . $role_name . '_metabox'] = $enable_acess_default_value;
                $options['enable_metabox_' . $role_name . ''] = $tax_names;
                $options['remove_taxonomy_metabox_' . $role_name . ''] = [];
            }

            SimpleTags_Plugin::set_option($options);

            update_option('taxopress_3_23_0_upgrade_completed', true);
        } elseif (!get_option('taxopress_3_28_0_upgrade_completed')) {
            if (function_exists('taxopress_get_autoterm_data')) {
                $autoterms      = taxopress_get_autoterm_data();
                foreach ($autoterms as $autoterm_index => $autoterm) {
                    //enable when to fields
                    $autoterms[$autoterm_index]['autoterm_for_post'] = 1;
                    $autoterms[$autoterm_index]['autoterm_for_schedule'] = 1;
                    $autoterms[$autoterm_index]['autoterm_for_existing_content'] = 1;
                    $autoterms[$autoterm_index]['autoterm_for_metaboxes'] = 1;
                    // update new cloned fields for other groups
                    $autoterms[$autoterm_index]['schedule_terms_limit'] = !empty($autoterm['terms_limit']) ? $autoterm['terms_limit'] : 5;
                    $autoterms[$autoterm_index]['schedule_autoterm_target'] = !empty($autoterm['autoterm_target']) ? $autoterm['autoterm_target'] : 0;
                    $autoterms[$autoterm_index]['schedule_autoterm_word'] = !empty($autoterm['autoterm_word']) ? $autoterm['autoterm_word'] : 0;
                    $autoterms[$autoterm_index]['schedule_autoterm_hash'] = !empty($autoterm['autoterm_hash']) ? $autoterm['autoterm_hash'] : 0;

                    $autoterms[$autoterm_index]['existing_content_terms_limit'] = !empty($autoterm['terms_limit']) ? $autoterm['terms_limit'] : 5;
                }
                update_option('taxopress_autoterms', $autoterms);
            }
            update_option('taxopress_3_28_0_upgrade_completed', true);
        }
    }

    /**
     * Redirect user on plugin activation
     *
     * @return void
     */
    public static function redirect_on_activate()
    {
        if (get_option('taxopress_activate')) {
            delete_option('taxopress_activate');
            wp_redirect(admin_url("admin.php?page=st_dashboard&welcome"));
            exit;
        }
    }
}
