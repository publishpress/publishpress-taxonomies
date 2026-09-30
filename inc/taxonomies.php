<?php

class SimpleTags_Admin_Taxonomies
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

        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        // Admin menu
        add_action('admin_menu', [$this, 'admin_menu']);

        // Javascript
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts'], 11);

        add_action('wp_ajax_taxopress_select2_term_filter', [$this, 'handle_taxopress_select2_filter']);
    }

    /**
     * Init somes JS and CSS need for this feature
     *
     * @return void
     * @author Olatechpro
     */
    public static function admin_enqueue_scripts()
    {
        wp_register_script(
            'st-taxonomies',
            STAGS_URL . '/assets/js/taxonomies.js',
            ['jquery', 'jquery-ui-dialog', 'postbox'],
            STAGS_VERSION
        );
        wp_register_style(
            'st-taxonomies-css',
            STAGS_URL . '/assets/css/taxonomies.css',
            ['wp-jquery-ui-dialog'],
            STAGS_VERSION,
            'all'
        );

        // add JS for manage click tags
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['page']) && $_GET['page'] == 'st_taxonomies') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_enqueue_script('st-taxonomies');
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            wp_enqueue_style('st-taxonomies-css');


            $core                  = get_taxonomies(['_builtin' => true]);
            $public                = get_taxonomies([
                '_builtin' => false,
                'public'   => true,
            ]);
            $private               = get_taxonomies([
                '_builtin' => false,
                'public'   => false,
            ]);
            $registered_taxonomies = array_merge($core, $public, $private);
            wp_localize_script(
                'st-taxonomies',
                'taxopress_tax_data',
                [
                    'confirm'             => esc_html__(
                        'Are you sure you want to delete this? Deleting will NOT remove created content.',
                        'simple-tags'
                    ),
                    'no_associated_type'  => esc_html__('Please select at least one post type.', 'simple-tags'),
                    'existing_taxonomies' => $registered_taxonomies,
                    'integer_error'  => esc_html__('Taxonomy slug cannot be numbers only.', 'simple-tags'),
                ]
            );
        }
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
            esc_html__('Taxonomies', 'simple-tags'),
            esc_html__('Taxonomies', 'simple-tags'),
            'simple_tags',
            'st_taxonomies',
            [
                $this,
                'page_manage_taxonomies',
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
            'option'  => 'st_taxonomies_per_page'
        ];

        add_screen_option($option, $args);

        $this->terms_table = new Taxonomy_List();
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_manage_taxonomies()
    {
        // Default order
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for default value
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for page display
        if (!isset($_GET['add'])) {
            //all tax
            ?>
            <div class="wrap st_wrap st-manage-taxonomies-page">

            <div id="">
                <h1 class="wp-heading-inline"><?php _e('Taxonomies', 'simple-tags'); ?></h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=st_taxonomies&add=taxonomy')); ?>"
                   class="page-title-action"><?php esc_html_e('Add New', 'simple-tags'); ?></a>

                   <div class="taxopress-description"><?php esc_html_e('This feature allows you to create new taxonomies and edit all the settings for each taxonomy.', 'simple-tags'); ?></div>


                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if (isset($_REQUEST['s']) && $search = sanitize_text_field(wp_unslash($_REQUEST['s']))) {
                    /* translators: %s: search keywords */
                    printf(' <span class="subtitle-text">' . esc_html__(
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
                    <?php $this->terms_table->search_box(__('Search Taxonomies', 'simple-tags'), 'term'); ?>
                </form>
                <div class="clear"></div>

                <div id="col-container" class="wp-clearfix">
                    <div class="col-wrap">
                        <?php
                            $selected_option = 'public';
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for UI display
                        if (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'all') {
                            $selected_option = 'all';
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for UI display
                        } elseif (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'private') {
                            $selected_option = 'private';
                        }
                        ?>
                        <div class="taxopress-taxonomy-type-wrap">
                            <select name="taxopress-taxonomy-type" class="taxopress-taxonomy-type">
                                <option value="all" <?php echo($selected_option === 'all' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('All Taxonomies', 'simple-tags'); ?></option>
                                <option value="public" <?php echo($selected_option === 'public' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('Public Taxonomies', 'simple-tags'); ?></option>
                                <option value="private" <?php echo($selected_option === 'private' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('Private Taxonomies', 'simple-tags'); ?></option>
                            </select>
                        </div>
                        <?php
                        $post_types = get_post_types(
                            array(
                                'show_ui' => true,
                            ),
                            'objects'
                        );

                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for filter display
                        $selected_post_type = isset($_GET['taxopress_taxonomy_post_type'])
                           // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            ? sanitize_key(wp_unslash($_GET['taxopress_taxonomy_post_type']))
                            : 'all';
                        ?>
                        <div class="taxopress-taxonomy-post-type-wrap">
                            <select name="taxopress_taxonomy_post_type" class="taxopress-taxonomy-post-type">
                                <option value="all" <?php echo('all' === $selected_post_type ? 'selected="selected"' : ''); ?>>
                                    <?php echo esc_html__('All Post Types', 'simple-tags'); ?>
                                </option>
                                <?php
                                if (! empty($post_types) && is_array($post_types)) {
                                    foreach ($post_types as $post_type) {
                                        ?>
                                        <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($selected_post_type, $post_type->name); ?>>
                                            <?php echo esc_html($post_type->labels->singular_name); ?>
                                        </option>
                                        <?php
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                            <?php $this->terms_table->display(); //Display the table?>
                        </form>
                        <div class="form-wrap edit-term-notes">
                            <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                        </div>
                    </div>

                </div>


            </div>
        <?php } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for page routing
            if (isset($_GET['add']) && $_GET['add'] === 'taxonomy') {
                //add/edit taxonomy
                $this->taxopress_manage_taxonomies();
                echo '<div>';
            }
        } ?>


        <?php SimpleTags_Admin::printAdminFooter(); ?>
        </div>
        <?php
        do_action('simpletags-taxonomies', SimpleTags_Admin::$taxonomy);
    }

    /**
    * Select2 Search taxonomy terms query
    */
    public function handle_taxopress_select2_filter()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in wp_verify_nonce call below
        $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in wp_verify_nonce call below
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : 'category';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in wp_verify_nonce call below
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in wp_verify_nonce call below
        $page        = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $per_page    = 20;

        if (empty($nonce) || !wp_verify_nonce($nonce, 'st-admin-js')) {
            wp_send_json_error(array('message' => esc_html__('Invalid nonce. Request is not authorized.', 'simple-tags')), 403);
        }

        $taxonomy_object = get_taxonomy($taxonomy);
        if (
            !current_user_can('simple_tags')
            || !$taxonomy_object
            || empty($taxonomy_object->cap->assign_terms)
            || !current_user_can($taxonomy_object->cap->assign_terms)
        ) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to view terms in this taxonomy.', 'simple-tags')), 403);
        }

        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => ($page - 1) * $per_page,
            'search'     => $search_term,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            wp_send_json_error(array('message' => esc_html__('Unable to retrieve terms for this taxonomy.', 'simple-tags')), 400);
        }

        $count_args = $args;
        unset($count_args['number'], $count_args['offset']);
        $total_terms = wp_count_terms($count_args);
        if (is_wp_error($total_terms)) {
            $total_terms = 0;
        }

        $context    = isset($_GET['context']) ? sanitize_text_field(wp_unslash($_GET['context'])) : '';
        $show_slug  = (
            'mass_edit' === $context
            && (int) SimpleTags_Plugin::get_option_value('enable_mass-edit_terms_slug') === 1
        );

        $results = array();
        foreach ($terms as $term) {
            $text = $term->name;
            if ($show_slug) {
                $text = sprintf('%s (%s)', $term->name, $term->slug);
            }

            $results[] = array(
                'id'   => $term->slug,
                'text' => $text,
            );
        }

        wp_send_json(array(
            'items' => $results,
            'more'  => ($page * $per_page < $total_terms),
        ));
    }



    /**
     * Create our settings page output.
     *
     * @internal
     */
    public function taxopress_manage_taxonomies()
    {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab       = (!empty($_GET) && !empty($_GET['action']) && 'edit' == $_GET['action']) ? 'edit' : 'new';
        $tab_class = 'taxopress-' . $tab;
        $current   = null;

        ?>

    <div class="wrap <?php echo esc_attr($tab_class); ?>">

        <?php
        /**
         * Fires right inside the wrap div for the taxonomy editor screen.
         */
        do_action('taxopress_inside_taxonomy_wrap');

        /**
         * Filters whether or not a taxonomy was deleted.
         *
         * @param bool $value Whether or not taxonomy deleted. Default false.
         */
        $taxonomy_deleted = apply_filters('taxopress_taxonomy_deleted', false);

        /**
         * Fires below the output for the tab menu on the taxonomy add/edit screen.
         */
        do_action('taxopress_below_taxonomy_tab_menu');

        $external_edit = false;
        $taxonomy_edit = false;
        $core_edit = false;

        if ('edit' === $tab) {
            $taxonomies = taxopress_get_taxonomy_data();

            $selected_taxonomy = taxopress_get_current_taxonomy($taxonomy_deleted);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for taxonomy selection
            $request_tax       = isset($_GET['taxopress_taxonomy']) ? sanitize_text_field(wp_unslash($_GET['taxopress_taxonomy'])) : '';

            if ($selected_taxonomy && array_key_exists($selected_taxonomy, $taxonomies)) {
                $current       = $taxonomies[$selected_taxonomy];
                $taxonomy_edit = true;
            } elseif (taxonomy_exists($request_tax)) {
                //not out taxonomy
                $external_taxonomy = get_taxonomies(['name' => $request_tax], 'objects');
                if (isset($external_taxonomy) > 0) {
                    $current       = taxopress_convert_external_taxonomy(
                        $external_taxonomy[$request_tax],
                        $request_tax
                    );
                    $external_edit = true;
                    $taxonomy_edit = true;
                }
            }

            if ($request_tax === 'media_tag') {
                $external_edit = false;
            }
        }

        if ($taxonomy_edit) {
            $wordpress_core_tax = array_keys(get_taxonomies(['_builtin' => true]));
            $wordpress_core_tax[] = 'post_tag';
            $wordpress_core_tax[] = 'category';
            if (in_array($current['name'], $wordpress_core_tax)) {
                $core_edit = true;
            }
        }

        $ui = new taxopress_admin_ui();
        ?>


        <div class="wrap <?php echo esc_attr($tab_class); ?>">
            <h1><?php echo esc_html__('Manage Taxonomy', 'simple-tags'); ?></h1>
            <div class="wp-clearfix"></div>

            <form method="post" action="<?php echo esc_url(taxopress_get_post_form_action($ui)); ?>">

                <?php
                if ($external_edit) {
                    echo '<input type="hidden" name="taxonomy_external_edit" aria-current="false" class="taxonomy_external_edit" value="1" />';
                }
                ?>


                <div class="taxonomiesui">


                    <div class="postbox-container">
                        <div id="poststuff">
                            <div class="taxopress-section postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <?php
                                        if ($taxonomy_edit) {
                                            echo esc_html__('Edit Taxonomy', 'simple-tags');
                                        } else {
                                            echo esc_html__('Add new Taxonomy', 'simple-tags');
                                        }
                                        ?>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div class="main">

                                        <ul class="st-taxonomy-tab">
                                            <li aria-current="true" class="taxonomy_general_tab active" data-content="taxonomy_general">
                                                <a href="#taxonomy_general">
                                                    <span><?php esc_html_e('General', 'simple-tags'); ?></span>
                                                </a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_posttypes_tab" data-content="taxonomy_posttypes">
                                                <a href="#taxonomy_posttypes">
                                                    <span><?php esc_html_e('Post Types', 'simple-tags'); ?></span>
                                                </a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_permalinks_tab" data-content="taxonomy_permalinks">
                                                <a href="#taxonomy_permalinks">
                                                    <span><?php esc_html_e('Permalinks', 'simple-tags'); ?></span>
                                                </a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_menus_tab" data-content="taxonomy_menus">
                                                <a href="#taxonomy_menus"><span><?php esc_html_e('Admin Area', 'simple-tags'); ?></span></a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_labels_tab" data-content="taxonomy_labels">
                                                <a href="#taxonomy_labels"><span><?php esc_html_e('Other Labels', 'simple-tags'); ?></span></a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_restapi_tab" data-content="taxonomy_restapi">
                                                <a href="#taxonomy_restapi"><span><?php esc_html_e('REST API', 'simple-tags'); ?></span></a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_advanced_tab" data-content="taxonomy_advanced">
                                                <a href="#taxonomy_advanced"><span><?php esc_html_e('Advanced', 'simple-tags'); ?></span></a>
                                            </li>

                                            <li aria-current="false" class="taxonomy_order_tab" data-content="taxonomy_order">
                                                <a href="#taxonomy_order"><span><?php esc_html_e('Order', 'simple-tags'); ?></span></a>
                                            </li>

                                            <?php if ($taxonomy_edit && !$external_edit) { ?>
                                            <li aria-current="false" class="taxonomy_slug_tab" data-content="taxonomy_slug">
                                                <a href="#taxonomy_slug"><span><?php esc_html_e('Slug', 'simple-tags'); ?></span></a>
                                            </li>
                                            <?php } ?>

                                            <?php
                                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for conditional UI display
                                            if (!empty($_GET) && !empty($_GET['action']) && 'edit' === $_GET['action']) {
                                                ?>
                                            <li aria-current="false" class="taxonomy_delete_tab" data-content="taxonomy_delete">
                                                <a href="#taxonomy_delete"><span><?php esc_html_e('Deactivate or Delete', 'simple-tags'); ?></span></a>
                                            </li>
                                            <?php } ?>
                                        </ul>


                                        <div class="st-taxonomy-content">


                                            <table class="form-table taxopress-table taxonomy_general">
                                                <?php
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_tr_start();

                                                if (!$taxonomy_edit) {
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_start();
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_label('name', esc_html__('Taxonomy Slug', 'simple-tags')) . $ui->get_required_span();

                                                    if ('edit' === $tab) {
                                                        echo '<p id="slugchanged" class="hidemessage">' . esc_html__(
                                                            'Slug has changed',
                                                            'simple-tags'
                                                        ) . '<span class="dashicons dashicons-warning"></span></p>';
                                                    }
                                                    echo '<p id="slugexists" class="hidemessage">' . esc_html__(
                                                        'Slug already exists',
                                                        'simple-tags'
                                                    ) . '<span class="dashicons dashicons-warning"></span></p>';
                                                    echo '<p id="st-tags-slug-error-input" class="hidemessage">' . esc_html__('Special character not allowed in slug.', 'simple-tags') . '<span class="dashicons dashicons-warning"></span></p>';

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_end() . $ui->get_td_start();

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_text_input([
                                                        'namearray'   => 'cpt_custom_tax',
                                                        'name'        => 'name',
                                                        'textvalue'   => isset($current['name']) ? esc_attr($current['name']) : '',
                                                        'maxlength'   => '32',
                                                        'helptext'    => esc_html__(
                                                            'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and underscores.',
                                                            'simple-tags'
                                                        ),
                                                        'class'     => 'tax-slug-input',
                                                        'required'    => true,
                                                        'placeholder' => false,
                                                        'wrap'        => false,
                                                    ]);

                                                    if ('edit' === $tab) {
                                                        echo '<p>';
                                                        esc_html_e(
                                                            'DO NOT EDIT the taxonomy slug unless also planning to migrate terms. Changing the slug registers a new taxonomy entry.',
                                                            'simple-tags'
                                                        );
                                                        echo '</p>';

                                                        echo '<div class="taxopress-spacer">';
                                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_check_input([
                                                            'checkvalue' => 'update_taxonomy',
                                                            'checked'    => 'false',
                                                            'name'       => 'update_taxonomy',
                                                            'namearray'  => 'update_taxonomy',
                                                            'labeltext'  => esc_html__(
                                                                'Migrate terms to newly renamed taxonomy?',
                                                                'simple-tags'
                                                            ),
                                                            'helptext'   => '',
                                                            'default'    => false,
                                                            'wrap'       => false,
                                                        ]);
                                                        echo '</div>';
                                                    }
                                                }



        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'label',
                                                'textvalue' => isset($current['label']) ? esc_attr($current['label']) : '',
                                                'aftertext' => esc_html__('(e.g. Jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('Plural Label', 'simple-tags'),
                                                'maxlength' => '100',
                                                'helptext'  => '',
                                                'required'  => true,
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'singular_label',
                                                'textvalue' => isset($current['singular_label']) ? esc_attr($current['singular_label']) : '',
                                                'aftertext' => esc_html__('(e.g. Job)', 'simple-tags'),
                                                'labeltext' => esc_html__('Singular Label', 'simple-tags'),
                                                'maxlength' => '100',
                                                'helptext'  => '',
                                                'required'  => true,
                                                ]);



                                                $select             = [
                                                'options' => [
                                                [
                                                'attr'    => '0',
                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                [
                                                'attr' => '1',
                                                'text' => esc_attr__('True', 'simple-tags'),
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['hierarchical']) : '';
                                                $select['selected'] = !empty($selected) ? $current['hierarchical'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'hierarchical',
                                                'labeltext'  => esc_html__('Parent-Child Relationships', 'simple-tags'),
                                                'aftertext'  => esc_html__(
                                                    'Can terms in this taxonomy be organized into hierarchical relationships?',
                                                    'simple-tags'
                                                ),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);



                                                if (isset($current['description'])) {
                                                    $current['description'] = stripslashes_deep($current['description']);
                                                }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_textarea_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'description',
                                                'rows'      => '4',
                                                'cols'      => '40',
                                                'textvalue' => isset($current['description']) ? esc_textarea($current['description']) : '',
                                                'labeltext' => esc_html__('Description', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'Describe what your taxonomy is used for.',
                                                    'simple-tags'
                                                ),
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'default_term',
                                                'textvalue' => isset($current['default_term']) ? esc_attr($current['default_term']) : '',
                                                'labeltext' => esc_html__('Default Terms', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Set the default terms for this taxonomy. These will be automatically added to all new posts. Enter the term names or slugs. Separate multiple terms with by comma.',
                                                    'simple-tags'
                                                ),
                                                ]);


        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_td_end() . $ui->get_tr_end();
                                                ?>
                                            </table>


                                            <table class="form-table taxopress-table taxonomy_posttypes"
                                                   style="display:none;">
                                                <?php

        /**
         * Filters the arguments for post types to list for taxonomy association.
         *
         *
         * @param array $value Array of default arguments.
         */
                                                $args = apply_filters(
                                                    'taxopress_attach_post_types_to_taxonomy',
                                                    ['public' => true]
                                                );

        // If they don't return an array, fall back to the original default. Don't need to check for empty, because empty array is default for $args param in get_post_types anyway.
                                                if (!is_array($args)) {
                                                    $args = ['public' => true];
                                                }
                                                $output = 'objects'; // Or objects.

        /**
         * Filters the results returned to display for available post types for taxonomy.
         *
         * @param array $value Array of post type objects.
         * @param array $args Array of arguments for the post type query.
         * @param string $output The output type we want for the results.
         */
                                                $post_types = apply_filters(
                                                    'taxopress_get_post_types_for_taxonomies',
                                                    get_post_types($args, $output),
                                                    $args,
                                                    $output
                                                );

                                                foreach ($post_types as $post_type) {
                                                    $core_label = in_array($post_type->name, [
                                                        'post',
                                                        'page',
                                                        'attachment',
                                                    ], true) ? '' : '';


                                                    echo '<tr valign="top"><th scope="row"><label for="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</label></th><td>';

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_check_input([
                                                        'checkvalue' => esc_attr($post_type->name),
                                                        'checked'    => (!empty($current['object_types']) && is_array($current['object_types']) && in_array(
                                                            $post_type->name,
                                                            $current['object_types'],
                                                            true
                                                        )) ? 'true' : 'false',
                                                        'name'       => esc_attr($post_type->name),
                                                        'namearray'  => 'cpt_post_types',
                                                        'textvalue'  => esc_attr($post_type->name),
                                                        'labeltext'  => "",
                                                        'wrap'       => false,
                                                    ]);

                                                    echo '</td></tr>';
                                                }


                                                if ($taxonomy_edit) {
                                                    $select             = [
                                                        'options' => [
                                                            [
                                                                'attr'    => '0',
                                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                                'default' => 'true',
                                                            ],
                                                            [
                                                                'attr' => '1',
                                                                'text' => esc_attr__('True', 'simple-tags'),
                                                            ],
                                                        ],
                                                    ];
                                                } else {
                                                    $select             = [
                                                        'options' => [
                                                            [
                                                                'attr'    => '0',
                                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                            ],
                                                            [
                                                                'attr' => '1',
                                                                'text' => esc_attr__('True', 'simple-tags'),
                                                                'default' => 'true',
                                                            ],
                                                        ],
                                                    ];
                                                }
                                                $selected           = isset($current) && isset($current['include_in_result']) ? taxopress_disp_boolean($current['include_in_result']) : '';
                                                $select['selected'] = !empty($selected) ? $current['include_in_result'] : '';

                                                echo '<td><hr /></td>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'include_in_result',
                                                'labeltext'  => esc_html__('Archive page result', 'simple-tags'),
                                                'aftertext'  => esc_html__(
                                                    'Normally, WordPress will only show one post type on taxonomy archive pages. Enable this feature to show content from all selected posts types.',
                                                    'simple-tags'
                                                ),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);


                                                ?>

                                            </table>


                                            <table class="form-table taxopress-table taxonomy_slug"
                                                   style="display:none;">
                                                <?php


                                                if ($taxonomy_edit) {
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_start();
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_label('name', esc_html__('Taxonomy Slug', 'simple-tags')) . $ui->get_required_span();

                                                    if ('edit' === $tab) {
                                                        echo '<p id="slugchanged" class="hidemessage">' . esc_html__(
                                                            'Slug has changed',
                                                            'simple-tags'
                                                        ) . '<span class="dashicons dashicons-warning"></span></p>';
                                                    }
                                                    echo '<p id="slugexists" class="hidemessage">' . esc_html__(
                                                        'Slug already exists',
                                                        'simple-tags'
                                                    ) . '<span class="dashicons dashicons-warning"></span></p>';

                                                    echo '<p id="st-tags-slug-error-input" class="hidemessage">' . esc_html__('Special character not allowed in slug.', 'simple-tags') . '<span class="dashicons dashicons-warning"></span></p>';

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_end() . $ui->get_td_start();

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_text_input([
                                                        'namearray'   => 'cpt_custom_tax',
                                                        'name'        => 'name',
                                                        'textvalue'   => isset($current['name']) ? esc_attr($current['name']) : '',
                                                        'maxlength'   => '32',
                                                        'helptext'    => 'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and underscores.',
                                                        'class'     => 'tax-slug-input',
                                                        'required'    => true,
                                                        'placeholder' => false,
                                                        'wrap'        => false,
                                                    ]);

                                                    if ('edit' === $tab) {
                                                        echo '<p>';
                                                        esc_html_e(
                                                            'DO NOT EDIT the taxonomy slug unless also planning to migrate terms. Changing the slug registers a new taxonomy entry.',
                                                            'simple-tags'
                                                        );
                                                        echo '</p>';

                                                        echo '<div class="taxopress-spacer">';
                                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_check_input([
                                                            'checkvalue' => 'update_taxonomy',
                                                            'checked'    => 'false',
                                                            'name'       => 'update_taxonomy',
                                                            'namearray'  => 'update_taxonomy',
                                                            'labeltext'  => esc_html__(
                                                                'Migrate terms to newly renamed taxonomy?',
                                                                'simple-tags'
                                                            ),
                                                            'helptext'   => '',
                                                            'default'    => false,
                                                            'wrap'       => false,
                                                        ]);
                                                        echo '</div>';
                                                    }
                                                }

                                                ?>

                                            </table>


                                            <table class="form-table taxopress-table taxonomy_permalinks"
                                                   style="display:none;">
                                                <?php


                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['rewrite']) : '';
                                                $select['selected'] = !empty($selected) ? $current['rewrite'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'rewrite',
                                                'labeltext'  => esc_html__('Rewrite', 'simple-tags'),
                                                'aftertext'  => esc_html__(
                                                    'WordPress can use a custom permalink for this taxonomy. It does not have to match the slug.',
                                                    'simple-tags'
                                                ),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'rewrite_slug',
                                                'textvalue' => isset($current['rewrite_slug']) ? esc_attr($current['rewrite_slug']) : '',
                                                'aftertext' => esc_attr__('(default: taxonomy name)', 'simple-tags'),
                                                'labeltext' => esc_html__('Custom Rewrite Slug', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy rewrite slug.',
                                                    'simple-tags'
                                                ),
                                                ]);

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['rewrite_withfront']) : '';
                                                $select['selected'] = !empty($selected) ? $current['rewrite_withfront'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'rewrite_withfront',
                                                'labeltext'  => esc_html__('Rewrite With Front', 'simple-tags'),
                                                'aftertext'  => esc_html__(
                                                    'Should the permastruct be prepended with the front base.',
                                                    'simple-tags'
                                                ),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr'    => '0',
                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                'default' => 'false',
                                                ],
                                                [
                                                'attr' => '1',
                                                'text' => esc_attr__('True', 'simple-tags'),
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['rewrite_hierarchical']) : '';
                                                $select['selected'] = !empty($selected) ? $current['rewrite_hierarchical'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'rewrite_hierarchical',
                                                'labeltext'  => esc_html__('Rewrite Hierarchical', 'simple-tags'),
                                                'aftertext'  => esc_html__(
                                                    'Should the permastruct allow hierarchical urls.',
                                                    'simple-tags'
                                                ),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                ?>

                                            </table>


                                            <table class="form-table taxopress-table taxonomy_menus"
                                                   style="display:none;">
                                                <?php


                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['show_ui']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_ui'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_ui',
                                                'labeltext'  => esc_html__('Show user interface', 'simple-tags'),
                                                'aftertext'  => esc_html__('Should there be a visible interface in the WordPress admin area to manage these terms?', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['show_in_menu']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_in_menu'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_in_menu',
                                                'labeltext'  => esc_html__('Show in admin menus', 'simple-tags'),
                                                'aftertext'  => esc_html__('Should there be links to this taxonomy in the WordPress admin menus?', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = (isset($current) && !empty($current['show_in_nav_menus'])) ? taxopress_disp_boolean($current['show_in_nav_menus']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_in_nav_menus'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_in_nav_menus',
                                                'labeltext'  => esc_html__('Show in frontend menus', 'simple-tags'),
                                                'aftertext'  => esc_html__('Should this taxonomy be available for the frontend “Menus” and “Navigation” options?', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);


                                                $select             = [
                                                'options' => [
                                                [
                                                'attr'    => '0',
                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                [
                                                'attr' => '1',
                                                'text' => esc_attr__('True', 'simple-tags'),
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['show_admin_column']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_admin_column'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_admin_column',
                                                'labeltext'  => esc_html__('Show admin column', 'simple-tags'),
                                                'aftertext'  => esc_html__('Should a column for this taxonomy appear on screens such as “Posts” and “Pages”?', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr'    => '0',
                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                'default' => 'false',
                                                ],
                                                [
                                                'attr' => '1',
                                                'text' => esc_attr__('True', 'simple-tags'),
                                                ],
                                                ],
                                                ];
                                                $selected           = (isset($current) && !empty($current['show_in_quick_edit'])) ? taxopress_disp_boolean($current['show_in_quick_edit']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_in_quick_edit'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_in_quick_edit',
                                                'labeltext'  => esc_html__(
                                                    'Show in "Quick Edit" and "Bulk Edit"',
                                                    'simple-tags'
                                                ),
                                                'aftertext'  => esc_html__('Should this taxonomy be available in editing tools on screens such as “Posts” and “Pages”?', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $select             = array(
                                                'options' => array(
                                                array(
                                                'attr'    => '0',
                                                'text'    => esc_attr__('False', 'simple-tags'),
                                                'default' => 'false',
                                                ),
                                                array(
                                                'attr' => '1',
                                                'text' => esc_attr__('True', 'simple-tags'),
                                                ),
                                                ),
                                                );
                                                $selected           = (isset($current) && ! empty($current['show_in_filter'])) ? taxopress_disp_boolean($current['show_in_filter']) : '';
                                                $select['selected'] = ! empty($selected) ? $current['show_in_filter'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input(
                                                    array(
                                                    'namearray' => 'cpt_custom_tax',
                                                    'name' => 'show_in_filter',
                                                    'labeltext' => esc_html__(
                                                        'Show in Filter',
                                                        'simple-tags'
                                                    ),
                                                    'aftertext' => esc_html__('Should this taxonomy be available in filters on screens such as “Posts” and “Pages”?', 'simple-tags'),
                                                    'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    )
                                                );

                                                ?>

                                            </table>


                                            <table class="form-table taxopress-table taxonomy_labels"
                                                   style="display:none;">

                                                <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'menu_name',
                                                'textvalue' => isset($current['labels']['menu_name']) ? esc_attr($current['labels']['menu_name']) : '',
                                                'aftertext' => esc_attr__('(e.g. Jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('Menu Name', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom admin menu name for your taxonomy.',
                                                    'simple-tags'
                                                ),
                                                'maxlength' => '100',
                                                'data'      => [
                                                'label'     => 'item', // Not localizing because it's isolated.
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'all_items',
                                                'textvalue' => isset($current['labels']['all_items']) ? esc_attr($current['labels']['all_items']) : '',
                                                'aftertext' => esc_attr__('(e.g. All Jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('All Items', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Used as tab text when showing all terms for hierarchical taxonomy while editing post.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('All %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'edit_item',
                                                'textvalue' => isset($current['labels']['edit_item']) ? esc_attr($current['labels']['edit_item']) : '',
                                                'aftertext' => esc_attr__('(e.g. Edit Job)', 'simple-tags'),
                                                'labeltext' => esc_html__('Edit Item', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Used at the top of the term editor screen for an existing taxonomy term.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Edit %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'view_item',
                                                'textvalue' => isset($current['labels']['view_item']) ? esc_attr($current['labels']['view_item']) : '',
                                                'aftertext' => esc_attr__('(e.g. View Job)', 'simple-tags'),
                                                'labeltext' => esc_html__('View Item', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Used in the admin bar when viewing editor screen for an existing taxonomy term.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('View %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'update_item',
                                                'textvalue' => isset($current['labels']['update_item']) ? esc_attr($current['labels']['update_item']) : '',
                                                'aftertext' => esc_attr__('(e.g. Update Job Name)', 'simple-tags'),
                                                'labeltext' => esc_html__('Update Item Name', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__(
                                                        'Update %s name',
                                                        'simple-tags'
                                                    ),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'add_new_item',
                                                'textvalue' => isset($current['labels']['add_new_item']) ? esc_attr($current['labels']['add_new_item']) : '',
                                                'aftertext' => esc_attr__('(e.g. Add New Job)', 'simple-tags'),
                                                'labeltext' => esc_html__('Add New Item', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Used at the top of the term editor screen and button text for a new taxonomy term.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Add new %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'new_item_name',
                                                'textvalue' => isset($current['labels']['new_item_name']) ? esc_attr($current['labels']['new_item_name']) : '',
                                                'aftertext' => esc_attr__('(e.g. New Job Name)', 'simple-tags'),
                                                'labeltext' => esc_html__('New Item Name', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('New %s name', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'parent_item',
                                                'textvalue' => isset($current['labels']['parent_item']) ? esc_attr($current['labels']['parent_item']) : '',
                                                'aftertext' => esc_attr__('(e.g. Parent Job)', 'simple-tags'),
                                                'labeltext' => esc_html__('Parent Item', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Parent %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'parent_item_colon',
                                                'textvalue' => isset($current['labels']['parent_item_colon']) ? esc_attr($current['labels']['parent_item_colon']) : '',
                                                'aftertext' => esc_attr__('(e.g. Parent Job:)', 'simple-tags'),
                                                'labeltext' => esc_html__('Parent Item Colon', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Parent %s:', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'singular',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'search_items',
                                                'textvalue' => isset($current['labels']['search_items']) ? esc_attr($current['labels']['search_items']) : '',
                                                'aftertext' => esc_attr__('(e.g. Search Jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('Search Items', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Search %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'popular_items',
                                                'textvalue' => isset($current['labels']['popular_items']) ? esc_attr($current['labels']['popular_items']) : null,
                                                'aftertext' => esc_attr__('(e.g. Popular Jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('Popular Items', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Popular %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'separate_items_with_commas',
                                                'textvalue' => isset($current['labels']['separate_items_with_commas']) ? esc_attr($current['labels']['separate_items_with_commas']) : null,
                                                'aftertext' => esc_attr__(
                                                    '(e.g. Separate Jobs with commas)',
                                                    'simple-tags'
                                                ),
                                                'labeltext' => esc_html__(
                                                    'Separate Items with Commas',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(esc_attr__(
                                                    'Separate %s with commas',
                                                    'simple-tags'
                                                ), 'item'),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'add_or_remove_items',
                                                'textvalue' => isset($current['labels']['add_or_remove_items']) ? esc_attr($current['labels']['add_or_remove_items']) : null,
                                                'aftertext' => esc_attr__(
                                                    '(e.g. Add or remove Jobs)',
                                                    'simple-tags'
                                                ),
                                                'labeltext' => esc_html__('Add or Remove Items', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__(
                                                        'Add or remove %s',
                                                        'simple-tags'
                                                    ),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'choose_from_most_used',
                                                'textvalue' => isset($current['labels']['choose_from_most_used']) ? esc_attr($current['labels']['choose_from_most_used']) : null,
                                                'aftertext' => esc_attr__(
                                                    '(e.g. Choose from the most used Jobs)',
                                                    'simple-tags'
                                                ),
                                                'labeltext' => esc_html__('Choose From Most Used', 'simple-tags'),
                                                'helptext'  => esc_html__(
                                                    'Custom taxonomy label. Used in the admin menu for displaying taxonomies.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(esc_attr__(
                                                    'Choose from the most used %s',
                                                    'simple-tags'
                                                ), 'item'),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'no_terms',
                                                'textvalue' => isset($current['labels']['no_terms']) ? esc_attr($current['labels']['no_terms']) : null,
                                                'aftertext' => esc_html__('(e.g. No jobs)', 'simple-tags'),
                                                'labeltext' => esc_html__('No terms', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'Used when indicating that there are no terms in the given taxonomy associated with an object.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('No %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'items_list_navigation',
                                                'textvalue' => isset($current['labels']['items_list_navigation']) ? esc_attr($current['labels']['items_list_navigation']) : null,
                                                'aftertext' => esc_html__(
                                                    '(e.g. Jobs list navigation)',
                                                    'simple-tags'
                                                ),
                                                'labeltext' => esc_html__('Items List Navigation', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'Screen reader text for the pagination heading on the term listing screen.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__(
                                                        '%s list navigation',
                                                        'simple-tags'
                                                    ),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'items_list',
                                                'textvalue' => isset($current['labels']['items_list']) ? esc_attr($current['labels']['items_list']) : null,
                                                'aftertext' => esc_html__('(e.g. Jobs list)', 'simple-tags'),
                                                'labeltext' => esc_html__('Items List', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'Screen reader text for the items list heading on the term listing screen.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('%s list', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'not_found',
                                                'textvalue' => isset($current['labels']['not_found']) ? esc_attr($current['labels']['not_found']) : null,
                                                'aftertext' => esc_html__('(e.g. No jobs found)', 'simple-tags'),
                                                'labeltext' => esc_html__('Not Found', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'The text displayed via clicking ‘Choose from the most used items’ in the taxonomy meta box when no items are available.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('No %s found', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_tax_labels',
                                                'name'      => 'back_to_items',
                                                'textvalue' => isset($current['labels']['back_to_items']) ? esc_attr($current['labels']['back_to_items']) : null,
                                                'aftertext' => esc_html__(
                                                    '(e.g. &larr; Back to jobs',
                                                    'simple-tags'
                                                ),
                                                'labeltext' => esc_html__('Back to Items', 'simple-tags'),
                                                'helptext'  => esc_attr__(
                                                    'The text displayed after a term has been updated for a link back to main index.',
                                                    'simple-tags'
                                                ),
                                                'data'      => [
                /* translators: Used for autofill */
                                                'label'     => sprintf(
                                                    esc_attr__('Back to %s', 'simple-tags'),
                                                    'item'
                                                ),
                                                'plurality' => 'plural',
                                                ],
                                                ]);
                                                ?>
                                            </table>

                                            <table class="form-table taxopress-table taxonomy_restapi"
                                                   style="display:none;">
                                                <?php

                                                $select             = [
                                                'options' => [
                                                [
                                                'attr' => '0',
                                                'text' => esc_attr__('False', 'simple-tags'),
                                                ],
                                                [
                                                'attr'    => '1',
                                                'text'    => esc_attr__('True', 'simple-tags'),
                                                'default' => 'true',
                                                ],
                                                ],
                                                ];
                                                $selected           = isset($current) ? taxopress_disp_boolean($current['show_in_rest']) : '';
                                                $select['selected'] = !empty($selected) ? $current['show_in_rest'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'cpt_custom_tax',
                                                'name'       => 'show_in_rest',
                                                'labeltext'  => esc_html__('Show in REST API', 'simple-tags'),
                                                'aftertext'  => esc_attr__('Add the taxonomy to the WordPress wp-json API.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);

                                                $rest_base_slug = '';

                                                if (!empty($current['rest_base'])) {
                                                    $rest_base_slug .= $current['rest_base'];
                                                } elseif (!empty($current['name'])) {
                                                    $rest_base_slug .= $current['name'];
                                                } else {
                                                    $rest_base_slug .= '{taxonomy}';
                                                }

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Reference comment for clarity
        // $current['name']
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped in get_text_input method
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'rest_base',
                                                'labeltext' => esc_html__('REST API base slug', 'simple-tags'),
                                                'helptext'  => esc_attr__('The base slug that this taxonomy will use in the REST API.', 'simple-tags') . ' <a target="blank" href="' . esc_url(home_url('/wp-json/wp/v2/' . $rest_base_slug)) . '">' . esc_url(home_url('/wp-json/wp/v2/' . $rest_base_slug)) . '</a>',
                                                'textvalue' => isset($current['rest_base']) ? esc_attr($current['rest_base']) : '',
                                                ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_text_input([
                                                'namearray' => 'cpt_custom_tax',
                                                'name'      => 'rest_controller_class',
                                                'labeltext' => esc_html__(
                                                    'REST API controller class',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_attr__('The name of a custom Rest Controller class instead of WP_REST_Terms_Controller.', 'simple-tags'),
                                                'aftertext' => esc_attr__(
                                                    'Custom controller to use instead of WP_REST_Terms_Controller.',
                                                    'simple-tags'
                                                ),
                                                'textvalue' => isset($current['rest_controller_class']) ? esc_attr($current['rest_controller_class']) : '',
                                                ]);


                                                ?>

                                        </div>


                                        <table class="form-table taxopress-table taxonomy_advanced"
                                               style="display:none;">
                                            <?php

                                            $select             = [
                                            'options' => [
                                            [
                                            'attr' => '0',
                                            'text' => esc_attr__('False', 'simple-tags'),
                                            ],
                                            [
                                            'attr'    => '1',
                                            'text'    => esc_attr__('True', 'simple-tags'),
                                            'default' => 'true',
                                            ],
                                            ],
                                            ];
                                            $selected           = isset($current) ? taxopress_disp_boolean($current['public']) : '';
                                            $select['selected'] = !empty($selected) ? $current['public'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                            'namearray'  => 'cpt_custom_tax',
                                            'name'       => 'public',
                                            'labeltext'  => esc_html__('Public', 'simple-tags'),
                                            'aftertext'  => esc_html__(
                                                'The taxonomy is for public use. It can be seen by frontend users.',
                                                'simple-tags'
                                            ),
                                            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $select             = [
                                            'options' => [
                                            [
                                            'attr' => '0',
                                            'text' => esc_attr__('False', 'simple-tags'),
                                            ],
                                            [
                                            'attr'    => '1',
                                            'text'    => esc_attr__('True', 'simple-tags'),
                                            'default' => 'true',
                                            ],
                                            ],
                                            ];
                                            $selected           = isset($current) ? taxopress_disp_boolean($current['publicly_queryable']) : '';
                                            $select['selected'] = !empty($selected) ? $current['publicly_queryable'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                            'namearray'  => 'cpt_custom_tax',
                                            'name'       => 'publicly_queryable',
                                            'labeltext'  => esc_html__('Public Queryable', 'simple-tags'),
                                            'aftertext'  => esc_html__(
                                                'The taxonomy is publicly queryable.',
                                                'simple-tags'
                                            ),
                                            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $select             = [
                                            'options' => [
                                            [
                                            'attr' => '0',
                                            'text' => esc_attr__('False', 'simple-tags'),
                                            ],
                                            [
                                            'attr'    => '1',
                                            'text'    => esc_attr__('True', 'simple-tags'),
                                            'default' => 'true',
                                            ],
                                            ],
                                            ];
                                            $selected           = isset($current) ? taxopress_disp_boolean($current['query_var']) : '';
                                            $select['selected'] = !empty($selected) ? $current['query_var'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                            'namearray'  => 'cpt_custom_tax',
                                            'name'       => 'query_var',
                                            'labeltext'  => esc_html__('Query Var', 'simple-tags'),
                                            'aftertext'  => esc_html__(
                                                'Enable a custom query_var key for this taxonomy.',
                                                'simple-tags'
                                            ),
                                            'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_text_input([
                                            'namearray' => 'cpt_custom_tax',
                                            'name'      => 'query_var_slug',
                                            'textvalue' => isset($current['query_var_slug']) ? esc_attr($current['query_var_slug']) : '',
                                            'aftertext' => '',
                                            'labeltext' => esc_html__('Custom Query Var String', 'simple-tags'),
                                            'helptext'  => esc_html__(
                                                'Sets a custom query_var slug for this taxonomy.',
                                                'simple-tags'
                                            ),
                                            ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_text_input([
                                            'namearray' => 'cpt_custom_tax',
                                            'name'      => 'meta_box_cb',
                                            'textvalue' => isset($current['meta_box_cb']) ? esc_attr($current['meta_box_cb']) : '',
                                            'labeltext' => esc_html__('Metabox callback', 'simple-tags'),
                                            'helptext'  => esc_html__(
                                                'Sets a callback function name for the meta box display. Hierarchical default: post_categories_meta_box, non-hierarchical default: post_tags_meta_box. To remove the metabox completely, use "false".',
                                                'simple-tags'
                                            ),
                                            ]);
                                            ?>
                                        </table>

                                        <table class="form-table taxopress-table taxonomy_order"
                                               style="display:none;">
                                            <?php
                                            $taxonomy_label = !empty($current['label']) ? $current['label'] : esc_html__('Categories', 'simple-tags');
                                            ?>
                                            <tr>
                                                <td colspan="2">
                                                    <div class="taxopress-description">
                                                        <?php
                                                        echo esc_html(
                                                            sprintf(
                                                                __('This feature controls the order of %s when you\'re editing posts, and in frontend displays.', 'simple-tags'),
                                                                $taxonomy_label
                                                            )
                                                        );
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    <label for="taxopress_enable_ordering"><?php esc_html_e('Enable PublishPress Taxonomies ordering', 'simple-tags'); ?></label>
                                                </th>
                                                <td>
                                                    <input type="checkbox" name="cpt_custom_tax[enable_taxopress_ordering]" id="taxopress_enable_ordering" value="1"
                                                        <?php checked(!empty($current['enable_taxopress_ordering']), 1); ?> />
                                                    <span class="description"><?php esc_html_e('Enable PublishPress Taxonomies ordering for this taxonomy in the frontend and admin area.', 'simple-tags'); ?></span>
                                                </td>
                                            </tr>
                                            <?php
                                            do_action('taxopress_terms_order', $current);
                                            ?>
                                        </table>
                                        


                                            <table class="form-table taxopress-table taxonomy_delete"
                                                   style="display:none;">
                                                <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_tr_start() . $ui->get_th_start();

                                                ?>
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for conditional UI display
        if (!empty($_GET) && !empty($_GET['action']) && 'edit' === $_GET['action']) { ?>
                                <?php

                                $activate_action_link   = add_query_arg(
                                    [
                                    'page'               => 'st_taxonomies',
                                    'add'                => 'taxonomy',
                                    'action'             => 'edit',
                                    'action2'            => 'taxopress-reactivate-taxonomy',
                                    'taxonomy'           => esc_attr($request_tax),
                                    '_wpnonce'           => wp_create_nonce('taxonomy-action-request-nonce'),
                                    'taxopress_taxonomy' => esc_attr($request_tax),
                                    ],
                                    taxopress_admin_url('admin.php')
                                );
                                $deactivate_action_link = add_query_arg(
                                    [
                                    'page'               => 'st_taxonomies',
                                    'add'                => 'taxonomy',
                                    'action'             => 'edit',
                                    'action2'            => 'taxopress-deactivate-taxonomy',
                                    'taxonomy'           => esc_attr($request_tax),
                                    '_wpnonce'           => wp_create_nonce('taxonomy-action-request-nonce'),
                                    'taxopress_taxonomy' => esc_attr($request_tax),
                                    ],
                                    taxopress_admin_url('admin.php')
                                );

                                if (in_array($request_tax, taxopress_get_deactivated_taxonomy())) {
                                    ?>
                                    <span class="action-button reactivate"><a class="button-primary"
                                            href="<?php echo esc_url($activate_action_link); ?>"><?php echo esc_html__('Re-activate Taxonomy', 'simple-tags'); ?></a></span>
                                <?php } else { ?>
                                    <span class="action-button deactivate"><a class="button-primary"
                                            href="<?php echo esc_url($deactivate_action_link); ?>"><?php echo esc_html__('Deactivate Taxonomy', 'simple-tags'); ?></a></span>
                                <?php }
                                /**
                                 * Filters the text value to use on the button when deleting.
                                 *
                                 * @param string $value Text to use for the button.
                                 */
                                if (!$external_edit) {
                                    ?>
                                    <input type="submit" class="button-secondary taxopress-delete-bottom"
                                           name="cpt_delete"
                                           id="cpt_submit_delete"
                                           value="<?php echo esc_attr(apply_filters(
                                               'taxopress_taxonomy_submit_delete',
                                               esc_html__('Delete Taxonomy', 'simple-tags')
                                           )); ?>"/>
                                <?php } elseif ($external_edit && array_key_exists($current['name'], taxopress_get_extername_taxonomy_data())) { ?>
                                    <input type="submit" class="button-secondary taxopress-delete-bottom"
                                           name="cpt_delete"
                                           id="cpt_submit_delete"
                                           value="<?php echo esc_attr(apply_filters(
                                               'taxopress_taxonomy_submit_delete',
                                               esc_html__('Delete PublishPress Taxonomies Edit Data', 'simple-tags')
                                           )); ?>"/>
                                                        <?php
                                                        echo '<div class="taxopress-warning"">' . esc_html__(
                                                            'You can only delete taxonomies created with PublishPress Taxonomies. However, you can delete any changes or edit made from PublishPress Taxonomies which will remove PublishPress Taxonomies version edit restoring original taxonomies data or removing it if already deleted',
                                                            'simple-tags'
                                                        ) . '</div>';
                                }
        }
        ?>

                                                <?php

                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_th_end() . $ui->get_tr_end();


                                                ?>

                                            </table>


                                    </div>
                                    <div class="clear"></div>


                                </div>
                            </div>
                        </div>


                        <?php
                        /**
                         * Fires after the default fieldsets on the taxonomy screen.
                         *
                         * @param taxopress_admin_ui $ui Admin UI instance.
                         */
                        do_action('taxopress_taxonomy_after_fieldsets', $ui);
                        ?>

                    </div>
                    </div>
                    </div>



                <div class="taxopress-right-sidebar">
                    <div class="taxopress-right-sidebar-wrapper">


                    <p class="submit">

        <?php
        wp_nonce_field(
            'taxopress_addedit_taxonomy_nonce_action',
            'taxopress_addedit_taxonomy_nonce_field'
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for conditional UI display
        if (!empty($_GET) && !empty($_GET['action']) && 'edit' === $_GET['action']) { ?>
            <?php

            /**
             * Filters the text value to use on the button when editing.
             *
             * @param string $value Text to use for the button.
             */
            ?>
    <input type="submit" class="button-primary taxopress-taxonomy-submit" name="cpt_submit"
           value="<?php echo esc_attr(apply_filters(
               'taxopress_taxonomy_submit_edit',
               esc_attr__('Save Taxonomy', 'simple-tags')
           )); ?>"/>
            <?php
        } else { ?>
            <?php

            /**
             * Filters the text value to use on the button when adding.
             *
             * @param string $value Text to use for the button.
             */
            ?>
<input type="submit" class="button-primary taxopress-taxonomy-submit" name="cpt_submit"
       value="<?php echo esc_attr(apply_filters(
           'taxopress_taxonomy_submit_add',
           esc_attr__('Add Taxonomy', 'simple-tags')
       )); ?>"/>
        <?php } ?>
<div class="taxonomy-required-field"></div>

        <?php if (!empty($current)) { ?>
<input type="hidden" name="tax_original" id="tax_original"
       value="<?php echo esc_attr($current['name']); ?>"/>
            <?php
        }

        // Used to check and see if we should prevent duplicate slugs.
        ?>
<input type="hidden" name="cpt_tax_status" id="cpt_tax_status"
   value="<?php echo esc_attr($tab); ?>"/>
</p>
                    </div>

                                    <?php do_action('taxopress_admin_after_sidebar'); ?>
                </div>


            </form>
        </div><!-- End .wrap -->

        <div class="clear"></div>

        <?php # Modal Windows;?>
<div class="remodal" data-remodal-id="taxopress-modal-alert"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false">
     <div class="" style="color:red;"><?php echo esc_html__('Please complete the following required fields to save your changes:', 'simple-tags'); ?></div>
    <div id="taxopress-modal-alert-content"></div>
    <br>
    <button data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html__('Okay', 'simple-tags'); ?></button>
</div>

<div class="remodal" data-remodal-id="taxopress-modal-confirm"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false">
    <div id="taxopress-modal-confirm-content"></div>
    <br>
    <button data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html__('No', 'simple-tags'); ?></button>
    <button data-remodal-action="confirm"
            class="remodal-confirm"><?php echo esc_html__('Yes', 'simple-tags'); ?></button>
</div>

        <?php
    }
}
