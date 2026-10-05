<?php

class SimpleTags_Terms
{
    public const MENU_SLUG = 'st_options';

    // class instance
    public static $instance;

    // WP_List_Table object
    public $terms_table;

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
        //support post type for terms query
        add_filter('terms_clauses', [__CLASS__, 'taxopress_terms_clauses'], 10, 3);
        //inline term edit
        add_action('wp_ajax_taxopress_terms_inline_save_term', [$this, 'taxopress_terms_inline_save_term_callback']);
    }

    // Handle the post_type parameter given in get_terms function
    public static function taxopress_terms_clauses($clauses, $taxonomy, $args)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'st_terms' && !empty($args['post_types']) && is_array($args['post_types'])) {
            global $wpdb;

            $post_types = array();

            foreach ($args['post_types'] as $cpt) {
                $post_types[] = "'" . esc_sql(sanitize_key($cpt)) . "'";
            }

            if (!empty($post_types)) {
                $clauses['fields'] = 'DISTINCT ' . str_replace('tt.*', 'tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.description, tt.parent', $clauses['fields']) . ', COUNT(t.term_id) AS count';
                $clauses['join'] .= ' INNER JOIN ' . $wpdb->term_relationships . ' AS r ON r.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN ' . $wpdb->posts . ' AS p ON p.ID = r.object_id';
                $clauses['where'] .= ' AND p.post_type IN (' . implode(',', $post_types) . ')';
                $clauses['orderby'] = 'GROUP BY t.term_id ' . $clauses['orderby'];
            }
        }
        return $clauses;
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && $_GET['page'] == 'st_terms') {
            wp_enqueue_style('st-taxonomies-css');
            wp_enqueue_script('admin-tags');
            wp_enqueue_script('inline-edit-tax');
        }
    }


    public function taxopress_terms_inline_save_term_callback()
    {
        global $wpdb;

        check_ajax_referer('taxinlineeditnonce', '_inline_edit');

        if (!isset($_POST['edit_taxonomy'], $_POST['original_tax'])) {
            wp_die(-1);
        }

        $edit_taxonomy = sanitize_key(wp_unslash($_POST['edit_taxonomy']));
        $taxonomy = sanitize_key(wp_unslash($_POST['original_tax']));
        $tax      = get_taxonomy($taxonomy);
        $edit_tax = get_taxonomy($edit_taxonomy);

        if (!$tax || !$edit_tax) {
            wp_die(0);
        }

        if (!isset($_POST['tax_ID']) || !(int) $_POST['tax_ID']) {
            wp_die(-1);
        }

        $id = (int) $_POST['tax_ID'];

        if (!current_user_can('edit_term', $id)) {
            wp_die(-1);
        }

        $tag                  = get_term($id, $taxonomy);
        $_POST['description'] = $tag->description;

        $updated = wp_update_term($id, $taxonomy, $_POST);

        if ($updated && !is_wp_error($updated)) {
            $tag = get_term($updated['term_id'], $taxonomy);
            if (!$tag || is_wp_error($tag)) {
                if (is_wp_error($tag) && $tag->get_error_message()) {
                    wp_die(esc_html($tag->get_error_message()));
                }
                wp_die(esc_html__('Item not updated.'));
            } else {
                if ($tax !== $edit_tax) {
                    $update_term = $wpdb->update(
                        $wpdb->prefix . 'term_taxonomy',
                        ['taxonomy' => $edit_taxonomy],
                        ['term_taxonomy_id' => $tag->term_taxonomy_id],
                        ['%s'],
                        ['%d']
                    );
                    if ($update_term) {
                        clean_term_cache($tag->term_id);
                        $tag = get_term($tag->term_id, $edit_taxonomy);
                    }
                }
            }
        } else {
            wp_die(esc_html__('Error updating term.'));
        }

        $wp_list_table = new Taxopress_Terms_List();

        $wp_list_table->single_row($tag);
        wp_die();
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
            esc_html__('Terms', 'simple-tags'),
            esc_html__('Terms', 'simple-tags'),
            'simple_tags',
            'st_terms',
            [
                $this,
                'page_manage_terms',
            ]
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for action inspection
        if (taxopress_is_screen_main_page() && (!isset($_GET['action']) || $_GET['action'] !== 'new_item')) {
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
            'option'  => 'st_terms_per_page'
        ];

        add_screen_option($option, $args);


        $this->terms_table = new Taxopress_Terms_List();
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_manage_terms()
    {
        // Default order
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['orderby'])) {
            $_GET['orderby'] = 'name';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'asc';
        }

        settings_errors(__CLASS__);

        ?>
        <div class="wrap st_wrap st-manage-taxonomies-page manage-taxopress-terms">

            <div id="">

                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (isset($_GET['action']) && $_GET['action'] === 'new_item') {
                    ?>
                    <div class="taxopress-term-wrap">
                        <div class="taxopress-term-main">
                             <h1 class="wp-heading-inline"><?php esc_html_e('Terms', 'simple-tags'); ?></h1>
                            <div class="taxopress-description">
                        <?php esc_html_e('This screen allows you to search and edit all the terms on your site.', 'simple-tags'); ?>
                            </div>
                            <?php
                            do_action('taxopress_terms_copy_with_metadata_promo');
                            ?>
                        </div>
                        <div class="taxopress-right-sidebar">
                     <?php do_action('taxopress_admin_after_sidebar'); ?>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <?php

                    $taxonomy_heading = '';
                    $show_order_message = false;
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
                    if (!empty($_REQUEST['taxopress_terms_taxonomy'])) {
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
                        $taxonomy_obj = get_taxonomy(sanitize_text_field(wp_unslash($_REQUEST['taxopress_terms_taxonomy'])));
                        if ($taxonomy_obj) {
                            $taxonomy_heading = $taxonomy_obj->labels->name;
                            $show_order_message = true;
                        }
                    }
                    ?>
                <h1 class="wp-heading-inline">
                    <?php
                    if ($taxonomy_heading) {
                            echo esc_html($taxonomy_heading);
                    } else {
                                esc_html_e('Terms', 'simple-tags');
                    }
                    ?>
                </h1>
                <div class="taxopress-description">
                    <?php
                    if ($show_order_message) {
                        esc_html_e('This screen allows you to order terms via drag and drop.', 'simple-tags');
                    } else {
                        esc_html_e('This screen allows you to search and edit all the terms on your site.', 'simple-tags');
                    }
                    ?>
                </div>

                    <?php
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for search filtering
                    if (isset($_REQUEST['s']) && $search = esc_attr(sanitize_text_field(wp_unslash($_REQUEST['s'])))) {
                        /* translators: %s: search keywords */
                        printf(' <span class="subtitle">' . esc_html__(
                            'Search results for &#8220;%s&#8221;',
                            'simple-tags'
                        ) . '</span>', esc_html($search));
                    }
                    ?>
                    <?php

                    //the terms table instance
                    $this->terms_table->prepare_items();
                    ?>


                <hr class="wp-header-end">
                <div id="ajax-response"></div>
                <form class="search-form wp-clearfix st-taxonomies-search-form" method="get">
                    <?php
                        // Hide search box if taxopress_show_all=1
                            $this->terms_table->search_box(esc_html__('Search Terms', 'simple-tags'), 'term');

                    ?>
                </form>
                <div class="clear"></div>

                <div id="col-container" class="wp-clearfix">

                    <div class="col-wrap">
                        <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                            <?php
                            $this->terms_table->display();
                            ?>
                        </form>
                        <div class="form-wrap edit-term-notes">
                            <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                        </div>
                    </div>


                </div>


            </div>
                    <?php $this->terms_table->inline_edit(); ?>

            <script>
                (function($) {
                    'use strict';

                    $(document).ready(function() {
                        // -------------------------------------------------------------
                        //   PublishPress Taxonomies terms quick edit
                        // -------------------------------------------------------------
                        $(document).on('click', 'a.editinline, button.editinline', function(e) {
                            var term_id = $(this).attr('data-term-id');
                            var taxonomy = $(this).attr('data-taxonomy');
                            $('.inline-edit-row#edit-' + term_id).find('select[name="edit_taxonomy"]').val(taxonomy);
                        });
                    });

                })(jQuery);
            </script>
                    <?php SimpleTags_Admin::printAdminFooter(); ?>
        </div>
                    <?php
                }
                ?>
        <?php
    }
}
