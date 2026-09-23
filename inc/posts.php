<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Posts
{
    public const MENU_SLUG = 'st_options';

    // class instance
    public static $instance;

    // WP_List_Table object
    public $posts_table;

    /**
     * Constructor
     *
     * @return void
     * @author Olatechpro
     */
    public function __construct()
    {

        global $hook_suffix;    //avoid warning outputs
        if (!isset($hook_suffix)) {
            $hook_suffix = '';
        }

        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);
        // Javascript
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts'], 11);

        add_action('wp_ajax_taxopress_filter_term_search', [__CLASS__, 'handle_filter_term_search']);
    }

    /**
     * Init somes JS and CSS need for this feature
     *
     * @return void
     * @author Olatechpro
     */
    public static function admin_enqueue_scripts()
    {

        // add JS for manage click tags
        if (isset($_GET['page']) && $_GET['page'] == 'st_posts') {
            wp_enqueue_style('st-taxonomies-css');
            wp_enqueue_script('admin-tags');
            wp_enqueue_script('inline-edit-tax');
        }
    }

    /**
     * Handle an ajax request to search filter available terms
     */
    public static function handle_filter_term_search()
    {
        header('Content-Type: application/javascript');

        if (
            empty($_GET['nonce'])
            || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'taxopress-term-search')
        ) {
            wp_send_json_error(null, 403);
        }

        if (! current_user_can('simple_tags')) {
            wp_send_json_error(null, 403);
        }

        $search        = !empty($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $field          = !empty($_GET['field']) ? sanitize_text_field(wp_unslash($_GET['field'])) : 'slug';
        $filter_format  = SimpleTags_Plugin::get_option_value('post_terms_filter_format');
        $taxonomy_type = SimpleTags_Plugin::get_option_value('post_terms_taxonomy_type');

        $terms        = self::get_possible_terms_for_search($search, $taxonomy_type);
        $results = [];

        foreach ($terms as $term) {
            $text = $term->name;
            if ($filter_format === 'term_name_taxonomy_name') {
                $taxonomy = get_taxonomy($term->taxonomy);
                $text .= ' (' . $taxonomy->labels->singular_name . ')';
            } elseif ($filter_format === 'term_name_taxonomy_slug') {
                $text .= ' (' . $term->taxonomy . ')';
            }
            $results[] = [
                'id'   => $term->$field,
                'text' => $text,
            ];
        }

        $response = [
            'results' => $results,
        ];
        echo wp_json_encode($response);
        exit;
    }

    /**
     * Get the possible terms for a given search query.
     *
     * @param string $search Search query.
     *
     * @return object
     */
    public static function get_possible_terms_for_search($search, $taxonomy_type = 'public')
    {
        if ($taxonomy_type === 'public') {
            $taxonomies = array_keys(get_taxonomies(['public' => true]));
        } elseif ($taxonomy_type === 'private') {
            $taxonomies = array_keys(get_taxonomies(['public' => false]));
        } else {
            $taxonomies = array_keys(get_taxonomies());
        }

        $term_args = [
            'taxonomy'   => $taxonomies,
            'hide_empty' => true,
            'number'     => apply_filters('taxopress_terms_search_result_limit', 20),
            'order_by'   => 'name',
        ];

        if (!empty($search)) {
            $search = str_replace(['\"', "\'"], '', $search);
            $term_args['search'] = $search;
        }

        $terms = get_terms($term_args);

        return $terms;
    }



    public static function set_screen($status, $option, $value)
    {
        return $value;
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
            esc_html__('Posts', 'simple-tags'),
            esc_html__('Posts', 'simple-tags'),
            'simple_tags',
            'st_posts',
            [
                $this,
                'page_manage_posts',
            ]
        );

        if (taxopress_is_screen_main_page()) {
            add_action("load-$hook", [$this, 'screen_option']);
        }
    }

    /**
     * Screen options
     */
    public function screen_option()
    {

        $option = 'per_page';
        $args   = [
            'label'   => esc_html__('Number of items per page', 'simple-tags'),
            'default' => 20,
            'option'  => 'st_posts_per_page'
        ];

        add_screen_option($option, $args);

        $this->posts_table = new Taxopress_Posts_List();
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_manage_posts()
    {
        // Default order
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__);

        ?>
        <div class="wrap st_wrap st-manage-taxonomies-page manage-taxopress-posts">

            <div id="">
                <h1 class="wp-heading-inline"><?php esc_html_e('Posts', 'simple-tags'); ?></h1>
                <div class="taxopress-description">
                    <?php esc_html_e('This feature allows you to search for terms and see all the posts attached to that term.', 'simple-tags'); ?>
                </div>


                <?php
                if (isset($_REQUEST['s']) && $search = esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s'])))) {
                    echo '<span class="subtitle__">';
                    printf(
                        /* translators: %s: Search query. */
                        esc_html__('Search results for: %s'),
                        '<strong>' . esc_html($search) . '</strong>'
                    );
                    echo '</span>';
                }
                ?>
                <?php

        //the posts table instance
                $this->posts_table->prepare_items();
                ?>


                <hr class="wp-header-end">
                <div id="ajax-response"></div>
                <form class="search-form wp-clearfix st-taxonomies-search-form" method="get">
                    <?php $this->posts_table->search_box(esc_html__('Search Posts', 'simple-tags'), 'post'); ?>
                </form>
                <div class="clear"></div>

                <div id="col-container" class="wp-clearfix">

                    <div class="col-wrap">
                        <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                            <?php $this->posts_table->display(); //Display the table
                            ?>
                        </form>
                        <div class="form-wrap edit-post-notes">
                            <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                        </div>
                    </div>


                </div>


            </div>
            <?php $this->posts_table->inline_edit(); ?>
            <?php SimpleTags_Admin::printAdminFooter(); ?>
        </div>
        <?php
    }
}
