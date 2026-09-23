<?php

class SimpleTags_Tag_Clouds
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

        add_action('wp_ajax_taxopress_terms_display_preview', [$this, 'handle_termsdisplay_preview']);
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
        if (isset($_GET['page']) && $_GET['page'] == 'st_terms_display') {
            wp_enqueue_style('st-taxonomies-css');
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
            esc_html__('Terms Display', 'simple-tags'),
            esc_html__('Terms Display', 'simple-tags'),
            'simple_tags',
            'st_terms_display',
            [
                $this,
                'page_manage_tagclouds',
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
            'option'  => 'st_terms_display_per_page'
        ];

        add_screen_option($option, $args);

        $this->terms_table = new TagClouds_List();
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_manage_tagclouds()
    {
        // Default order
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['add'])) {
            //all tax
            ?>
            <div class="wrap st_wrap st-manage-taxonomies-page">

            <div id="">
                <h1 class="wp-heading-inline"><?php _e('Terms Display', 'simple-tags'); ?></h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=st_terms_display&add=new_item')); ?>"
                   class="page-title-action taxopress">
                    <?php if (! taxopress_is_pro_version()) : ?>
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <?php endif; ?>
                   <?php esc_html_e('Add New Terms Display', 'simple-tags'); ?>
                </a>

                   <div class="taxopress-description"><?php esc_html_e('This feature allows you to create a customizable display of all the terms in one taxonomy.', 'simple-tags'); ?></div>


                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for search display only, no state change
                if (isset($_REQUEST['s']) && $search = sanitize_text_field(wp_unslash($_REQUEST['s']))) {
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
                    <?php $this->terms_table->search_box(esc_html__('Search Terms Display', 'simple-tags'), 'term'); ?>
                </form>
                <div class="clear"></div>

                <div id="col-container" class="wp-clearfix">

                    <div class="col-wrap">
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
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter to check if adding new item, no state change
            if ($_GET['add'] == 'new_item') {
                //add/edit taxonomy
                $this->taxopress_manage_tagclouds();
                echo '<div>';
            }
        } ?>


        <?php SimpleTags_Admin::printAdminFooter(); ?>
        </div>
        <?php
    }


    /**
     * Create our settings page output.
     *
     * @internal
     */
    public function taxopress_manage_tagclouds()
    {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters to determine edit vs new tab display, no state change
        $tab       = (!empty($_GET) && !empty($_GET['action']) && 'edit' == $_GET['action']) ? 'edit' : 'new';
        $tab_class = 'taxopress-' . $tab;
        $current   = null;

        ?>

    <div class="wrap <?php echo esc_attr($tab_class); ?>">

        <?php

        $tagclouds = taxopress_get_tagcloud_data();
        $tag_cloud_edit = false;
        $tag_cloud_limit = false;

        if ('edit' === $tab) {
            $selected_tagcloud = taxopress_get_current_tagcloud();

            if ($selected_tagcloud && array_key_exists($selected_tagcloud, $tagclouds)) {
                $current       = $tagclouds[$selected_tagcloud];
                $tag_cloud_edit = true;
            }
        }


        if (!isset($current['title']) && count($tagclouds) > 0 && apply_filters('taxopress_tag_clouds_create_limit', true)) {
            $tag_cloud_limit = true;
        }


        $ui = new taxopress_admin_ui();
        ?>




<div class="wrap <?php echo esc_attr($tab_class); ?>">
            <h1><?php echo esc_html__('Manage Terms Display', 'simple-tags'); ?></h1>
            <div class="wp-clearfix"></div>

            <form method="post" action="">


                <div class="tagcloudui st-tabbed">

                <?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter to check edit mode for conditional display, no state change
                if (isset($_GET['action']) && $_GET['action'] === 'edit') : ?>
                <div class="tagclouds-postbox-container terms-display-preview">
                    <div id="poststuff" class="taxopress-preview-box">
                        <div class="taxopress-section postbox">
                            <div class="postbox-header">
                                <h2 class="hndle ui-sortable-handle">
                                    <?php echo esc_html__('Preview Terms Display', 'simple-tags'); ?>
                                </h2>
                                
                                <div class="handle-actions hide-if-no-js">
                                    <span class="term-panel-move up dashicons dashicons-arrow-up-alt2" 
                                        title="<?php esc_attr_e('Move up', 'simple-tags'); ?>">
                                    </span>
                                    <span class="term-panel-move down dashicons dashicons-arrow-down-alt2"
                                        title="<?php esc_attr_e('Move down', 'simple-tags'); ?>">
                                    </span>
                                    <button type="button" class="handlediv" aria-expanded="true">
                                        <span class="screen-reader-text">
                                            <?php esc_html_e('Toggle panel', 'simple-tags'); ?>
                                        </span>
                                        <span class="toggle-indicator dashicons dashicons-arrow-down" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="inside">
                                <div class="taxopress-preview-wrapper">
                                    <div class="taxopress-preview-control">
                                        <?php
                                        $preview_display_id = isset($current['ID']) ? (int) $current['ID'] : 0;
                                        ?>
                                        <span class="spinner" style="visibility:hidden;"></span>
                                    </div>
                                    <div id="term-display-preview" class="taxopress-preview-content">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>


                    <div class="tagclouds-postbox-container">
                        <div id="poststuff">
                            <div class="taxopress-section postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <?php
                                        if ($tag_cloud_edit) {
                                            $active_tab = (isset($current['active_tab']) && !empty(trim($current['active_tab']))) ? $current['active_tab'] : 'tagcloud_general';
                                            echo esc_html__('Edit Terms Display', 'simple-tags');
                                            echo '<input type="hidden" name="edited_tagcloud" value="' . esc_attr($current['ID']) . '" />';
                                            echo '<input type="hidden" name="taxopress_tag_cloud[ID]" value="' . esc_attr($current['ID']) . '" />';
                                            echo '<input type="hidden" name="taxopress_tag_cloud[active_tab]" class="taxopress-active-subtab" value="' . esc_attr($active_tab) . '" />';
                                        } else {
                                            $active_tab = 'tagcloud_general';
                                            echo '<input type="hidden" name="taxopress_tag_cloud[active_tab]" class="taxopress-active-subtab" value="" />';
                                            echo esc_html__('Add new Terms Display', 'simple-tags');
                                        }
                                        ?>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div class="main">


                                        <?php if ($tag_cloud_limit) {
                                            echo '<div class="st-taxonomy-content promo-box-area"><div class="taxopress-warning upgrade-pro">
                                            <h2 style="margin-bottom: 5px;">' . esc_html__('To create more Terms Display, please upgrade to PublishPress Taxonomies Pro.', 'simple-tags') . '</h2>
                                            <p>

                                            ' . esc_html__('With PublishPress Taxonomies Pro, you can create unlimited Terms Display. You can create Terms Display for any taxonomy and then display those Terms Display anywhere on your site.', 'simple-tags') . '

                                            </p>
                                            </div></div>';
                                        } else {
                                            ?>


                                            <ul class="taxopress-tab">
                                                <li aria-current="<?php echo $active_tab === 'tagcloud_general' ? 'true' : 'false'; ?>" class="tagcloud_general_tab <?php echo $active_tab === 'tagcloud_general' ? 'active' : ''; ?>" data-content="tagcloud_general">
                                                    <a href="#tagcloud_general"><span><?php esc_html_e('General', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_terms' ? 'true' : 'false'; ?>" class="tagcloud_terms_tab <?php echo $active_tab === 'tagcloud_terms' ? 'active' : ''; ?>" data-content="tagcloud_terms">
                                                    <a href="#tagcloud_terms"><span><?php esc_html_e('Choose Terms', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_options' ? 'true' : 'false'; ?>" class="tagcloud_options_tab <?php echo $active_tab === 'tagcloud_options' ? 'active' : ''; ?>" data-content="tagcloud_options">
                                                    <a href="#tagcloud_options"><span><?php esc_html_e('Options', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_layout' ? 'true' : 'false'; ?>" class="tagcloud_layout_tab <?php echo $active_tab === 'tagcloud_layout' ? 'active' : ''; ?>" data-content="tagcloud_layout">
                                                    <a href="#tagcloud_layout"><span><?php esc_html_e('Layout', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_exceptions' ? 'true' : 'false'; ?>" class="tagcloud_exceptions_tab <?php echo $active_tab === 'tagcloud_exceptions' ? 'active' : ''; ?>" data-content="tagcloud_exceptions">
                                                    <a href="#tagcloud_exceptions"><span><?php esc_html_e('Exceptions', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_design' ? 'true' : 'false'; ?>" class="tagcloud_design_tab <?php echo $active_tab === 'tagcloud_design' ? 'active' : ''; ?>" data-content="tagcloud_design">
                                                    <a href="#tagcloud_design"><span><?php esc_html_e('Design', 'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'tagcloud_advanced' ? 'true' : 'false'; ?>" class="tagcloud_advanced_tab <?php echo $active_tab === 'tagcloud_advanced' ? 'active' : ''; ?>" data-content="tagcloud_advanced">
                                                    <a href="#tagcloud_advanced"><span><?php esc_html_e('Display Format', 'simple-tags'); ?></span></a>
                                                </li>

                                            </ul>

                                            <div class="st-taxonomy-content taxopress-tab-content">


                                                <table class="form-table taxopress-table tagcloud_general"
                                                       style="<?php echo $active_tab === 'tagcloud_general' ? '' : 'display:none;'; ?>">
                                                    <?php
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_tr_start();


                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_start();

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_label('name', esc_html__('Title', 'simple-tags')) . $ui->get_required_span();
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_th_end() . $ui->get_td_start();

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_text_input([
                                                    'namearray'   => 'taxopress_tag_cloud',
                                                    'name'        => 'title',
                                                    'textvalue'   => isset($current['title']) ? esc_attr($current['title']) : '',
                                                    'maxlength'   => '32',
                                                    'helptext'  => '',
                                                    'required'    => true,
                                                    'placeholder' => false,
                                                    'wrap'        => false,
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
                                                    $selected           = (isset($current) && isset($current['hide_title'])) ? taxopress_disp_boolean($current['hide_title']) : '';
                                                    $select['selected'] = !empty($selected) ? $current['hide_title'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'hide_title',
                                                    'labeltext'  => esc_html__('Hide title in output?', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);



                                                    $options[] = [ 'attr' => '', 'text' => esc_html__('All post types', 'simple-tags'), 'default' => 'true' ];
                                                    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
                                                        if (!in_array($post_type->name, ['attachment'])) {
                                                            $options[] = [ 'attr' => $post_type->name, 'text' => $post_type->label ];
                                                        }
                                                    }

                                                    $select = [
                                                    'options' => $options,
                                                    ];
                                                    $selected = (isset($current) && isset($current['post_type'])) ? taxopress_disp_boolean($current['post_type']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['post_type'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'post_type',
                                                    'class'      => 'st-post-type-select',
                                                    'labeltext'  => esc_html__('Post Type', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                                    $options = [];
                                                    foreach (get_all_taxopress_taxonomies() as $_taxonomy) {
                                                        $_taxonomy = $_taxonomy->name;
                                                        $tax = get_taxonomy($_taxonomy);
                                                        if (! $tax->show_tagcloud || empty($tax->labels->name) || $_taxonomy === 'link_category') {
                                                            continue;
                                                        }
                                                        if ($tax->name === 'post_tag') {
                                                            $options[] = [ 'post_type' => join(',', $tax->object_type), 'attr' => $tax->name, 'text' => $tax->labels->name . ' (' . $tax->name . ')', 'default' => 'true' ];
                                                        } else {
                                                            $options[] = [ 'post_type' => join(',', $tax->object_type), 'attr' => $tax->name, 'text' => $tax->labels->name . ' (' . $tax->name . ')' ];
                                                        }
                                                    }

                                                    $select = [
                                                    'options' => $options,
                                                    ];
                                                    $selected = isset($current) ? taxopress_disp_boolean($current['taxonomy']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['taxonomy'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'taxonomy',
                                                    'class'      => 'st-post-taxonomy-select',
                                                    'labeltext'  => esc_html__('Taxonomy', 'simple-tags'),
                                                    'required'   => true,
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                                    $options = [
                                                    ['attr' => 'all', 'text' => esc_html__('All Parent Terms', 'simple-tags'), 'default' => 'true']
                                                    ];
                                                    if (!empty($current['taxonomy'])) {
                                                        $taxonomy = $current['taxonomy'];

                                                        $all_terms = get_terms([
                                                            'taxonomy'   => $taxonomy,
                                                            'hide_empty' => false,
                                                        ]);

                                                        if (!empty($all_terms) && !is_wp_error($all_terms)) {
                                                            $child_term_ids = [];
                                                            $parent_term_ids = [];

                                                            // Build child-parent mapping
                                                            foreach ($all_terms as $term) {
                                                                if ($term->parent) {
                                                                    $child_term_ids[] = $term->term_id;
                                                                    $parent_term_ids[] = $term->parent;
                                                                }
                                                            }

                                                            $parent_term_ids = array_unique($parent_term_ids);

                                                            foreach ($all_terms as $term) {
                                                                $term_id = $term->term_id;

                                                                if (in_array($term_id, $parent_term_ids, true) || $term->parent == 0) {
                                                                    $options[] = [
                                                                        'attr' => strval($term_id),
                                                                        'text' => esc_html($term->name)
                                                                    ];
                                                                }
                                                            }
                                                        }
                                                    }

                                                    $selected = isset($current['parent_term']) ? (array) $current['parent_term'] : ['all'];
                                                    $select['selected'] = isset($current['parent_term']) ? $current['parent_term'] : 'all';

                                                    $select = [
                                                    'options'  => $options,
                                                    'selected' => $selected
                                                    ];

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'parent_term',
                                                    'class'      => 'taxopress-single-select2',
                                                    'labeltext'  => esc_html__('Parent Term', 'simple-tags'),
                                                    'selections' => $select // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                                    $select = [
                                                    'options' => [
                                                    [ 'attr' => 'parents_only', 'text' => esc_attr__('Display only Parent Terms', 'simple-tags') ],
                                                    [ 'attr' => 'sub_terms_only', 'text' => esc_attr__('Display only Sub-Terms', 'simple-tags') ],
                                                    [ 'attr' => 'parents_and_sub', 'text' => esc_attr__('Display Parents and Sub-Terms', 'simple-tags'), 'default' => 'true' ]
                                                    ]
                                                    ];

                                                    $selected = isset($current['display_mode']) ? taxopress_disp_boolean($current['display_mode']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['display_mode'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'display_mode',
                                                    'labeltext'  => esc_html__('Display Mode', 'simple-tags'),
                                                    'selections' => $select // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_td_end() . $ui->get_tr_end();
                                                    ?>
                                                </table>




                                                <table class="form-table taxopress-table tagcloud_terms"
                                                       style="<?php echo $active_tab === 'tagcloud_terms' ? '' : 'display:none;'; ?>">
                                                    <?php

                                                    ?>
                                                    <tr valign="top">
                                                        <th scope="row"><label><?php esc_html_e('Term selection mode', 'simple-tags'); ?></label></th>
                                                        <td>
                                                            <?php
                                                            $current_mode = isset($current['term_selection_mode']) ? $current['term_selection_mode'] : 'automatic';
                                                            ?>
                                                            <div style="margin-bottom: 10px;">
                                                                <label>
                                                                    <input type="radio" name="taxopress_tag_cloud[term_selection_mode]" value="automatic" <?php checked($current_mode, 'automatic'); ?> />
                                                                    <?php esc_html_e('Automatic', 'simple-tags'); ?>
                                                                </label>
                                                                <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                                                    <?php esc_html_e('Display terms based on settings in the "General" tab.', 'simple-tags'); ?>
                                                                </p>
                                                            </div>
                                                            <div style="margin-bottom: 10px;">
                                                                <label>
                                                                    <input type="radio" name="taxopress_tag_cloud[term_selection_mode]" value="custom" <?php checked($current_mode, 'custom'); ?> />
                                                                    <?php esc_html_e('Custom', 'simple-tags'); ?>
                                                                </label>
                                                                <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                                                    <?php esc_html_e('Display only the specific terms entered in the "Custom terms to display" field below.', 'simple-tags'); ?>
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <label>
                                                                    <input type="radio" name="taxopress_tag_cloud[term_selection_mode]" value="combined" <?php checked($current_mode, 'combined'); ?> />
                                                                    <?php esc_html_e('Combined', 'simple-tags'); ?>
                                                                </label>
                                                                <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                                                    <?php esc_html_e('Display automatic terms plus any additional custom terms specified below.', 'simple-tags'); ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_textarea_input([
                                                        'namearray' => 'taxopress_tag_cloud',
                                                        'name'      => 'include_terms',
                                                        'rows'      => '4',
                                                        'cols'      => '40',
                                                        'class'     => 'autocomplete-input tagclouds-include',
                                                        'textvalue' => isset($current['include_terms']) ? esc_attr($current['include_terms']) : '',
                                                        'labeltext' => esc_html__('Custom terms to display', 'simple-tags'),
                                                        'helptext'  => esc_html__('Enter terms (comma-separated) to display. Used in Custom and Combined modes.', 'simple-tags'),
                                                        'required'  => false,
                                                    ]);

                                                    $select = [
                                                    'options' => [
                                                    [ 'attr' => '1', 'text' => esc_attr__('24 hours', 'simple-tags') ],
                                                    [ 'attr' => '7', 'text' => esc_attr__('7 days', 'simple-tags') ],
                                                    [ 'attr' => '14', 'text' => esc_attr__('2 weeks', 'simple-tags') ],
                                                    [ 'attr' => '30', 'text' => esc_attr__('1 month', 'simple-tags') ],
                                                    [ 'attr' => '180', 'text' => esc_attr__('6 months', 'simple-tags') ],
                                                    [ 'attr' => '365', 'text' => esc_attr__('1 year', 'simple-tags') ],
                                                    [ 'attr' => '0', 'text' => esc_attr__('No limit', 'simple-tags'), 'default' => 'true' ],
                                                    ],
                                                    ];
                                                    $selected = isset($current) ? taxopress_disp_boolean($current['limit_days']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['limit_days'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_number_select([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'limit_days',
                                                    'labeltext'  => esc_html__('Limit terms based on timeframe', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                                    do_action('taxopress_tagcloud_ordering_method', $current);


                                                    $select = [
                                                    'options' => [
                                                    [ 'attr' => 'asc', 'text' => esc_attr__('Ascending', 'simple-tags') ],
                                                    [ 'attr' => 'desc', 'text' => esc_attr__('Descending', 'simple-tags'), 'default' => 'true' ],
                                                    ],
                                                    ];
                                                    $selected = isset($current) ? taxopress_disp_boolean($current['order']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['order'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'order',
                                                    'labeltext'  => esc_html__('Ordering for choosing terms for display', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);



                                                    ?>
                                                </table>


                                                <table class="form-table taxopress-table tagcloud_layout"
                                                       style="<?php echo $active_tab === 'tagcloud_layout' ? '' : 'display:none;'; ?>">
                                                       <?php

                                                        $select = [
                                                        'options' => [
                                                        [ 'attr' => 'flat', 'text' => esc_attr__('Cloud', 'simple-tags')],
                                                        [ 'attr' => 'list', 'text' => esc_attr__('Unordered List (UL/LI)', 'simple-tags') ],
                                                        [ 'attr' => 'ol', 'text' => esc_attr__('Ordered List (OL/LI)', 'simple-tags') ],
                                                        [ 'attr' => 'comma', 'text' => esc_attr__('WordPress Default', 'simple-tags'), 'default' => 'true'],
                                                        ['attr' => 'table', 'text' => esc_attr__('Table List', 'simple-tags')],
                                                        ['attr' => 'border', 'text' => esc_attr__('Border Cloud', 'simple-tags'), 'default' => 'true' ],
                                                        ['attr' => 'parent/child', 'text' => esc_attr__('Parent / Child', 'simple-tags')],
                                                        ],
                                                        ];
                                                        $selected = isset($current) ? taxopress_disp_boolean($current['format']) : '';
                                                        $select['selected'] = ! empty($selected) ? $current['format'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_select_checkbox_input_main([
                                                        'namearray'  => 'taxopress_tag_cloud',
                                                        'name'       => 'format',
                                                        'labeltext'  => esc_html__('Display format', 'simple-tags'),
                                                        'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        ]);
                                                        ?>

                                                </table>  
                                                     


                                                <table class="form-table taxopress-table tagcloud_options"
                                                       style="<?php echo $active_tab === 'tagcloud_options' ? '' : 'display:none;'; ?>">
                                                       <?php

                                                 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_text_input([
                                                        'namearray' => 'taxopress_tag_cloud',
                                                        'name'      => 'before',
                                                        'textvalue' => isset($current['before']) ? esc_attr($current['before']) : '',
                                                        'labeltext' => esc_html__(
                                                            'Text to display before terms list',
                                                            'simple-tags'
                                                        ),
                                                        'helptext'  => esc_html__(
                                                            'Enter the text that should be display before terms list. This field accepts basic HTML.',
                                                            'simple-tags'
                                                        ),
                                                        'required'  => false,
                                                        ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                       echo $ui->get_text_input([
                                                       'namearray' => 'taxopress_tag_cloud',
                                                       'name'      => 'after',
                                                       'textvalue' => isset($current['after']) ? esc_attr($current['after']) : '',
                                                       'labeltext' => esc_html__(
                                                           'Text to display after terms list',
                                                           'simple-tags'
                                                       ),
                                                       'helptext'  => esc_html__(
                                                           'Enter the text that should be display after terms list. This field accepts basic HTML.',
                                                           'simple-tags'
                                                       ),
                                                       'required'  => false,
                                                       ]);
                                                        ?>
                                                </table>

                                                <table class="form-table taxopress-table tagcloud_exceptions"
                                                       style="<?php echo $active_tab === 'tagcloud_exceptions' ? '' : 'display:none;'; ?>">
                                                       <?php

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_textarea_input([
                                                        'namearray' => 'taxopress_tag_cloud',
                                                        'name'      => 'exclude_terms',
                                                        'rows'      => '4',
                                                        'cols'      => '40',
                                                        'class'     => 'autocomplete-input tagclouds-exclude',
                                                        'textvalue' => isset($current['exclude_terms']) ? esc_attr($current['exclude_terms']) : '',
                                                        'labeltext' => esc_html__('Exclude terms from terms display', 'simple-tags'),
                                                        'helptext'  => esc_html__('Enter terms (comma-separated) to exclude from the terms display.', 'simple-tags'),
                                                        'required'  => false,
                                                        ]);

                                                       $enable_hidden_terms = SimpleTags_Plugin::get_option_value('enable_hidden_terms');

                                                       if ($enable_hidden_terms) {
                                                           $select = [
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

                                                           $selected = isset($current['hide_terms']) ? taxopress_disp_boolean($current['hide_terms']) : '0';
                                                           $select['selected'] = !empty($selected) ? $selected : '0';

                                                           // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                           echo $ui->get_select_checkbox_input([
                                                               'namearray'  => 'taxopress_tag_cloud',
                                                               'name'       => 'hide_terms',
                                                               'labeltext'  => esc_html__('Exclude hidden terms from Terms Display?', 'simple-tags'),
                                                               'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                           ]);
                                                       } else {
                                                           if (isset($current['hide_terms']) && $current['hide_terms'] !== '0') {
                                                               $current['hide_terms'] = '0';
                                                           }
                                                       }

                                                        ?>
                                                </table>


                                                <table class="form-table taxopress-table tagcloud_design"
                                                       style="<?php echo $active_tab === 'tagcloud_design' ? '' : 'display:none;'; ?>">
                                                    <?php

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
                                                    $selected           = (isset($current) && isset($current['hide_output'])) ? taxopress_disp_boolean($current['hide_output']) : '';
                                                    $select['selected'] = !empty($selected) ? $current['hide_output'] : '';
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'hide_output',
                                                    'labeltext'  => esc_html__('Hide display output if no terms?', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_number_input([
                                                    'namearray' => 'taxopress_tag_cloud',
                                                    'name'      => 'max',
                                                    'textvalue' => isset($current['max']) ? esc_attr($current['max']) : '20 ',
                                                    'labeltext' => esc_html__('Maximum terms to display', 'simple-tags'),
                                                    'helptext'    => '',
                                                    'required'  => true,
                                                    ]);

                                                   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_number_input([
                                                    'namearray' => 'taxopress_tag_cloud',
                                                    'name'      => 'smallest',
                                                    'textvalue' => isset($current['smallest']) ? esc_attr($current['smallest']) : '12',
                                                    'labeltext' => esc_html__('Font size minimum', 'simple-tags'),
                                                    'helptext'    => '',
                                                    'required'  => true,
                                                    ]);

                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_number_input([
                                                    'namearray' => 'taxopress_tag_cloud',
                                                    'name'      => 'largest',
                                                    'textvalue' => isset($current['largest']) ? esc_attr($current['largest']) : '12',
                                                    'labeltext' => esc_html__('Font size maximum', 'simple-tags'),
                                                    'helptext'    => '',
                                                    'required'  => true,
                                                    ]);

                                                    $select = [
                                                    'options' => [
                                                    [ 'attr' => 'pt', 'text' => esc_attr__('Point', 'simple-tags'), 'default' => 'true' ],
                                                    [ 'attr' => 'px', 'text' => esc_attr__('Pixel', 'simple-tags') ],
                                                    [ 'attr' => 'em', 'text' => esc_attr__('Em', 'simple-tags') ],
                                                    [ 'attr' => '%', 'text' => esc_attr__('Percent', 'simple-tags') ],
                                                    ],
                                                    ];
                                                    $selected = isset($current) ? taxopress_disp_boolean($current['unit']) : '';
                                                    $select['selected'] = ! empty($selected) ? $current['unit'] : '';
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input_main([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'unit',
                                                    'labeltext'  => esc_html__('Unit font size', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);



                                                    $select             = [
                                                    'options' => [
                                                    [
                                                        'attr'    => '0',
                                                        'text'    => esc_attr__('False', 'simple-tags'),
                                                    ],
                                                    [
                                                        'attr' => '1',
                                                        'text' => esc_attr__('True', 'simple-tags'),
                                                        //'default' => 'true', removed when default value is checked as this mean box is always checked even when user uncheck it since it's defau;t
                                                    ],
                                                    ],
                                                    ];
                                                    $selected           = (isset($current) && isset($current['color'])) ? taxopress_disp_boolean($current['color']) : '';

                                                    if ($tag_cloud_edit) {
                                                        $select['selected'] = !empty($selected) ? $current['color'] : '';
                                                    } else {
                                                        $select['selected'] = 1; //makeup for default when creating new term display
                                                    }
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_select_checkbox_input([
                                                    'namearray'  => 'taxopress_tag_cloud',
                                                    'name'       => 'color',
                                                    'class'     => 'tag-cloud-color-option',
                                                    'labeltext'  => esc_html__('Enable colors for terms', 'simple-tags'),
                                                    'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_text_input([
                                                    'namearray' => 'taxopress_tag_cloud',
                                                    'name'      => 'mincolor',
                                                    'class'     => 'text-color tag-cloud-min',
                                                    'textvalue' => isset($current['mincolor']) ? esc_attr($current['mincolor']) : '#353535',
                                                    'labeltext' => esc_html__('Font color minimum', 'simple-tags'),
                                                    'helptext' => esc_html__('This is the color of the least popular term', 'simple-tags'),
                                                    'required'  => true,
                                                    ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_text_input([
                                                    'namearray' => 'taxopress_tag_cloud',
                                                    'name'      => 'maxcolor',
                                                    'class'     => 'text-color tag-cloud-max',
                                                    'textvalue' => isset($current['maxcolor']) ? esc_attr($current['maxcolor']) : '#000000',
                                                    'labeltext' => esc_html__('Font color maximum', 'simple-tags'),
                                                    'helptext' => esc_html__('This is the color of the most popular term', 'simple-tags'),
                                                    'required'  => true,
                                                    ]);


                                                    ?>

                                                </table>


                                                <table class="form-table taxopress-table tagcloud_advanced"
                                                       style="<?php echo $active_tab === 'tagcloud_advanced' ? '' : 'display:none;'; ?>">
                                                       <?php

                                               // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        echo $ui->get_text_input([
                                                            'namearray' => 'taxopress_tag_cloud',
                                                            'name'      => 'wrap_class',
                                                            'class'     => '',
                                                            'textvalue' => isset($current['wrap_class']) ? esc_attr($current['wrap_class']) : '',
                                                            'labeltext' => esc_html__('Term display div class', 'simple-tags'),
                                                            'helptext'  => '',
                                                            'required'  => false,
                                                        ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                       echo $ui->get_text_input([
                                                       'namearray' => 'taxopress_tag_cloud',
                                                       'name'      => 'link_class',
                                                       'class'     => '',
                                                       'textvalue' => isset($current['link_class']) ? esc_attr($current['link_class']) : '',
                                                       'labeltext' => esc_html__('Term link class', 'simple-tags'),
                                                       'helptext'  => '',
                                                       'required'  => false,
                                                       ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                       echo $ui->get_textarea_input([
                                                       'namearray' => 'taxopress_tag_cloud',
                                                       'name'      => 'xformat',
                                                       'class'     => 'st-full-width',
                                                       'rows'      => '4',
                                                       'cols'      => '40',
                                                       'textvalue' => isset($current['xformat']) ? esc_attr($current['xformat']) : esc_attr('<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>'),
                                                       'labeltext' => esc_html__('Term link format', 'simple-tags'),
                                                       'helptext'  => sprintf(esc_html__('This option allows you to change the format of your terms. You can use any of the tokens in the "Terms Display format" sidebar". Find more details %1sin the documentation%2s.', 'simple-tags'), '<a target="blank" href="https://taxopress.com/docs/format-tag-clouds/">', '</a>'),
                                                       'required'  => false,
                                                       ]);


                                                        ?>
                                                </table>


                                            </div>


                                        <?php }//end new fields
                                        ?>


                                        <div class="clear"></div>


                                    </div>
                                </div>
                            </div>


                            <?php if ($tag_cloud_limit) { ?>
                                <div class="pp-version-notice-bold-purple" style="margin-left:0px;">
                                    <div class="pp-version-notice-bold-purple-message"><?php echo esc_html__('You\'re using PublishPress Taxonomies Free.
                                        The Pro version has more features and support.', 'simple-tags'); ?>
                                    </div>
                                    <div class="pp-version-notice-bold-purple-button">
                                        <a href="https://taxopress.com/taxopress/" target="_blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?> </a>
                                    </div>
                                </div>

                            <?php } ?>
                            <input type="hidden" class="pp-terms-display-fontsize-warning" value="<?php echo esc_attr__('Font size minimum must not be greater than Font size maximum value.', 'simple-tags'); ?>" />
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
                    <div class="taxopress-right-sidebar-wrapper" style="min-height: 205px;<?php echo ($tag_cloud_limit) ? 'display: none;' : ''; ?>">


                    <?php
                    if (!$tag_cloud_limit) { ?>
                        <p class="submit">

                            <?php
                            wp_nonce_field(
                                'taxopress_addedit_tagcloud_nonce_action',
                                'taxopress_addedit_tagcloud_nonce_field'
                            );
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above via wp_nonce_field; reading GET to determine submit button text
                            if (!empty($_GET) && !empty($_GET['action']) && 'edit' === $_GET['action']) { ?>
                                <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-tag-cloud-submit" name="tagcloud_submit"
                                       value="<?php echo esc_attr(esc_attr__('Save Terms Display', 'simple-tags')); ?>"/>
                                    <?php
                            } else { ?>
                            <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-tag-cloud-submit" name="tagcloud_submit"
                                   value="<?php echo esc_attr(esc_attr__('Add Terms Display', 'simple-tags')); ?>"/>
                            <?php } ?>

                        <input type="hidden" name="cpt_tax_status" id="cpt_tax_status"
                               value="<?php echo esc_attr($tab); ?>"/>
                        </p>

                        <?php if (!empty($current)) {
                            ?>
                        <p>
                            <?php echo '<div class="taxopress-warning" style="">' . esc_html__('Shortcode: ', 'simple-tags'); ?> &nbsp;
                            <textarea style="resize: none;padding: 5px;" readonly>[taxopress_termsdisplay id="<?php echo (int)$current['ID']; ?>"]</textarea>
                            </div>
                        </p>
                        <?php } ?>

                        <?php
                    }
                    ?>

                    </div>

                    <div class="taxopress-token-right-sidebar">
            <div id="postbox-container-1" class="postbox-container">
            <div class="meta-box-sortables">
                <div class="postbox">
                    <div class="postbox-header">
                        <h3 class="hndle is-non-sortable">
                            <span><?php echo esc_html__('Terms Display format', 'simple-tags'); ?></span>
                        </h3>
                    </div>
        
                    <div class="inside">
                        <p><?php echo esc_html__('Here are the tokens you can use for Term link format', 'simple-tags'); ?>:</p>
                        <ul>
                            <li><code>%tag_link%</code> – <?php echo esc_html__('The URL of the term', 'simple-tags'); ?></li>
                            <li><code>%tag_id%</code> – <?php echo esc_html__('The ID of the term', 'simple-tags'); ?></li>
                            <li><code>t%tag_scale%</code> – <?php echo esc_html__('The weighted size of the term in the display', 'simple-tags'); ?></li>
                            <li><code>%tag_count%</code> – <?php echo esc_html__('The number of times the term is used', 'simple-tags'); ?></li>
                            <li><code>%tag_rel%</code> – <?php echo esc_html__('This provides rel tag markup', 'simple-tags'); ?> (<?php echo esc_html__('it creates', 'simple-tags'); ?> <code>rel="tag"</code>)</li>
                            <li><code>%tag_size%</code> – <?php echo esc_html__('The font size for the term', 'simple-tags'); ?></li>
                            <li><code>%tag_color%</code> – <?php echo esc_html__('The font color for the term', 'simple-tags'); ?></li>
                            <li><code>%tag_name%</code> – <?php echo esc_html__('The name of the term', 'simple-tags'); ?></li>
                            <li><code>%tag_name_attribute%</code> – <?php echo esc_html__('The name of the term with any HTML stripped out', 'simple-tags'); ?></li>
                            <li><code>%tag_description%</code> – <?php echo esc_html__('The description of the term', 'simple-tags'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>    
    </div>


                                                    <?php do_action('taxopress_admin_after_sidebar'); ?>
                </div>

                <div class="clear"></div>



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
            class="remodal-confirm"><?php echo esc_html__('Yes', 'simple-tags'); ?>
    </button>
</div>

        <?php
        do_action('simpletags-terms_display');
    }

    /**
     * Handle the AJAX request for previewing terms display.
     */
    public function handle_termsdisplay_preview()
    {
        // Validate nonce
        if (!isset($_POST['nonce']) || !check_ajax_referer('st-admin-js', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce', 'simple-tags')]);
        }

        if (!current_user_can('simple_tags')) {
            wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')]);
        }

        if (!isset($_POST['taxopress_termsdisplay'])) {
            wp_send_json_error(['message' => __('Missing display ID.', 'simple-tags')]);
        }

        $display_id = sanitize_text_field(wp_unslash($_POST['taxopress_termsdisplay']));

        // Get the display configuration
        $tagclouds = taxopress_get_tagcloud_data();
        if (!array_key_exists($display_id, $tagclouds)) {
            wp_send_json_error(['message' => __('Invalid terms display configuration.', 'simple-tags')]);
        }

        $config = $tagclouds[$display_id];

        // Create tag cloud client instance
        $client = new SimpleTags_Client_TagCloud();

        // Convert config to query args format
        $args = http_build_query([
            'taxonomy'      => $config['taxonomy'] ?? 'post_tag',
            'selectionby'   => $config['selectionby'] ?? 'count',
            'selection'     => $config['selection'] ?? 'desc',
            'orderby'      => $config['orderby'] ?? 'random',
            'order'        => $config['order'] ?? 'desc',
            'format'       => $config['format'] ?? 'border',
            'number'       => $config['max'] ?? 20,
            'largest'      => $config['largest'] ?? 12,
            'smallest'     => $config['smallest'] ?? 12,
            'unit'         => $config['unit'] ?? 'pt',
            'color'        => !empty($config['color']),
            'mincolor'     => $config['mincolor'] ?? '#353535',
            'maxcolor'     => $config['maxcolor'] ?? '#000000',
            'hide_empty'   => !empty($config['hide_empty']),
            'hide_title'   => !empty($config['hide_title']),
            'hide_output'  => !empty($config['hide_output']),
            'notagstext'   => !empty($config['notagstext']),
            'title'        => $config['title'] ?? '',
            'exclude_terms' => $config['exclude_terms'] ?? '',
            'term_selection_mode' => $config['term_selection_mode'] ?? 'automatic',
            'include_terms' => $config['include_terms'] ?? '',
            'hide_terms'   => !empty($config['hide_terms']),
            'before'      => $config['before'] ?? '',
            'after'       => $config['after'] ?? '',
            'xformat'     => $config['xformat'] ?? '',
            'wrap_class'  => $config['wrap_class'] ?? '',
            'link_class'  => $config['link_class'] ?? '',
            'parent_term' => $config['parent_term'] ?? 'all',
            'display_mode' => $config['display_mode'] ?? 'parents_and_sub',
            'max'         => $config['max'] ?? 20,
            'limit_days' => $config['limit_days'] ?? '',
        ]);

        // Get the tag cloud output
        $output = $client->extendedTagCloud($args);

        if (empty($output)) {
            if (!empty($config['hide_output'])) {
                wp_send_json_success(['html' => '']);
            }
            $notagstext = esc_html__('There are no terms in this taxonomy.', 'simple-tags');
            wp_send_json_success(['html' => '<p>' . $notagstext . '</p>']);
        }

        wp_send_json_success(['html' => $output]);
    }
}
