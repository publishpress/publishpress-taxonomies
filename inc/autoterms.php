<?php

// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect,Generic.WhiteSpace.ScopeIndent.IncorrectExact,PSR12.Operators.OperatorSpacing.NoSpaceAfter,PSR12.Operators.OperatorSpacing.NoSpaceBefore,PSR2.Classes.ClassDeclaration.CloseBraceAfterBody,PSR2.Methods.FunctionCallSignature.CloseBracketLine,PSR2.Methods.FunctionCallSignature.ContentAfterOpenBracket,PSR2.Methods.FunctionCallSignature.Indent,PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose,Squiz.ControlStructures.ControlSignature.SpaceAfterCloseParenthesis,Squiz.WhiteSpace.ControlStructureSpacing.SpacingAfterOpen,Squiz.WhiteSpace.ControlStructureSpacing.SpacingBeforeClose,Squiz.WhiteSpace.ScopeClosingBrace.Indent,WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Security.NonceVerification.Recommended,WordPressVIPMinimum.Security.ProperEscapingFunction.htmlAttrNotByEscHTML,WordPressVIPMinimum.Security.ProperEscapingFunction.notAttrEscAttr -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Autoterms
{
    public const MENU_SLUG = 'st_options';

    // class instance
    public static $instance;

    // WP_List_Table object
    public $terms_table;

    // WP_List_Table object
    public $logs_table;

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
        if (isset($_GET['page']) && $_GET['page'] == 'st_autoterms') {
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
            esc_html__('Auto Terms', 'simple-tags'),
            esc_html__('Auto Terms', 'simple-tags'),
            'simple_tags',
            'st_autoterms',
            [
                $this,
                'page_manage_autoterms',
            ]
        );

        if (taxopress_is_screen_main_page()) {
            add_action("load-$hook", [$this, 'screen_option']);
        }
    }

    public function autoterms_logs_count()
    {

        $count = taxopress_autoterms_logs_data(1)['counts'];
        return '('. number_format_i18n($count) .')';
    }

    /**
     * Screen options
     */
    public function screen_option()
    {

        $option = 'per_page';

        if (isset($_GET['tab']) && $_GET['tab'] === 'logs') {
            $args   = [
            'label'   => esc_html__('Number of items per page', 'simple-tags'),
            'default' => 20,
            'option'  => 'st_autoterms_logs_per_page'
            ];
            $this->logs_table = new Autoterms_Logs();
        } else {
            $args   = [
            'label'   => esc_html__('Number of items per page', 'simple-tags'),
            'default' => 20,
            'option'  => 'st_autoterms_per_page'
            ];
            $this->terms_table = new Autoterms_List();
        }

        add_screen_option($option, $args);
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author Olatechpro
     */
    public function page_manage_autoterms()
    {
        // Default order
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__);

        if (isset($_GET['tab']) && $_GET['tab'] === 'logs') {
            //autoterms logs

            $delete_all_link = add_query_arg(
                [
                'page'                   => 'st_autoterms',
                'tab'                    => 'logs',
                'action'                 => 'taxopress-delete-autoterm-logs',
                '_wpnonce'               => wp_create_nonce('autoterm-action-request-nonce')
                ],
                admin_url('admin.php')
            );

            $enable_log_link = add_query_arg(
                [
                'page'                   => 'st_autoterms',
                'tab'                    => 'logs',
                'action'                 => 'taxopress-enable-autoterm-logs',
                '_wpnonce'               => wp_create_nonce('autoterm-action-request-nonce')
                ],
                admin_url('admin.php')
            );

            $disable_log_link = add_query_arg(
                [
                'page'                   => 'st_autoterms',
                'tab'                    => 'logs',
                'action'                 => 'taxopress-disable-autoterm-logs',
                '_wpnonce'               => wp_create_nonce('autoterm-action-request-nonce')
                ],
                admin_url('admin.php')
            );
            ?>
            <div class="wrap st_wrap st-manage-taxonomies-page">

            <div id="">
                <h1 class="wp-heading-inline"><?php esc_html_e('Auto Terms Logs', 'simple-tags'); ?></h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms')); ?>"
                   class="page-title-action"><?php esc_html_e('Auto Terms', 'simple-tags'); ?></a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms&add=new_item')); ?>"
                   class="page-title-action"><?php esc_html_e('Add New Auto Terms', 'simple-tags'); ?></a>

                   <?php if (get_option('taxopress_autoterms_logs_disabled')) { ?>
                    <a href="<?php echo esc_url($enable_log_link); ?>" class="page-title-action taxopress-logs-tablenav-enable-logs"><?php esc_html_e('Enable Logs', 'simple-tags'); ?></a>
                <?php } else { ?>
                    <a href="<?php echo esc_url($disable_log_link); ?>" class="page-title-action taxopress-logs-tablenav-disable-logs" onclick="return confirm('<?php esc_attr_e('Are you sure you want to disable logs?', 'simple-tags'); ?>')"><?php esc_html_e('Disable Logs', 'simple-tags'); ?></a>
                <?php } ?>

                <a href="<?php echo esc_url($delete_all_link); ?>" class="page-title-action taxopress-logs-tablenav-purge-logs"  onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete all logs?', 'simple-tags'); ?>')"><?php esc_html_e('Delete All Logs', 'simple-tags'); ?></a>

                <div class="taxopress-description">
                    <?php esc_html_e('Auto Terms logs history.', 'simple-tags'); ?>
                </div>


                <?php
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
            $this->logs_table->prepare_items();
            ?>


                <hr class="wp-header-end">
                <div id="ajax-response"></div>
                <form class="search-form wp-clearfix st-taxonomies-search-form" method="get">
                    <?php $this->logs_table->search_box(esc_html__('Search Auto Terms Logs', 'simple-tags'), 'term'); ?>
                </form>
                <div class="clear"></div>

                <div id="col-container" class="wp-clearfix">

                    <div class="col-wrap">
                        <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                            <?php $this->logs_table->display(); //Display the table?>
                        </form>
                        <div class="form-wrap edit-term-notes">
                            <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                        </div>
                    </div>


                </div>


            </div>
        <?php } elseif (!isset($_GET['add'])) {
            //all tax
            ?>
            <div class="wrap st_wrap st-manage-taxonomies-page">

            <div id="">
                <h1 class="wp-heading-inline"><?php esc_html_e('Auto Terms', 'simple-tags'); ?></h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms&add=new_item')); ?>"
                   class="page-title-action taxopress">
                    <?php if (! taxopress_is_pro_version()) : ?>
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <?php endif; ?>
                   <?php esc_html_e('Add New Auto Terms', 'simple-tags'); ?>
                </a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms&tab=logs')); ?>"
                   class="page-title-action"><?php esc_html_e('Logs', 'simple-tags'); ?> <span class="update-plugins"><span class="plugin-count"><?php echo esc_html($this->autoterms_logs_count()); ?></span></span></a>

                <div class="taxopress-description">
                    <?php esc_html_e('Auto Terms can scan your content and automatically assign new and existing terms.', 'simple-tags'); ?>
                </div>


                <?php
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
                    <?php $this->terms_table->search_box(esc_html__('Search Auto Terms', 'simple-tags'), 'term'); ?>
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
            if ($_GET['add'] == 'new_item') {
                //add/edit taxonomy
                $this->taxopress_manage_autoterms();
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
    public function taxopress_manage_autoterms()
    {

        $tab       = (!empty($_GET) && !empty($_GET['action']) && 'edit' == $_GET['action']) ? 'edit' : 'new';
        $tab_class = 'taxopress-' . $tab;
        $current   = null;

        ?>

    <div class="wrap <?php echo esc_attr($tab_class); ?>">

        <?php

        $autoterms      = taxopress_get_autoterm_data();
        $autoterm_edit  = false;
        $autoterm_limit = false;

        if ('edit' === $tab) {


            $selected_autoterm = taxopress_get_current_autoterm();

            if ($selected_autoterm && array_key_exists($selected_autoterm, $autoterms)) {
                $current       = $autoterms[$selected_autoterm];
                $autoterm_edit = true;
            }

        }


        if (!isset($current['title']) && count($autoterms) > 0 && apply_filters('taxopress_autoterms_create_limit', true)) {
            $autoterm_limit = true;
        }


        $ui = new taxopress_admin_ui();

        $taxonomy_replace_options = [
            'options' => [
                [
                    'attr' => 'append',
                    'text' => esc_attr__('Add new terms and keep existing terms.', 'simple-tags'),
                    'default' => 'true'
                ],
                [
                    'attr' => 'append_and_replace',
                    'text' => esc_attr__('Add new terms and remove existing terms.', 'simple-tags')
                ],
                [
                    'attr' => 'replace',
                    'text' => esc_attr__('Remove existing terms even if no new terms are found.', 'simple-tags')
                ]
            ],
        ];
        ?>


        <div class="wrap <?php echo esc_attr($tab_class); ?>">
            <h1><?php echo esc_html__('Manage Auto Terms', 'simple-tags'); ?>

            <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms')); ?>"
                   class="page-title-action"><?php esc_html_e('Auto Terms', 'simple-tags'); ?></a>

                <a href="<?php echo esc_url(admin_url('admin.php?page=st_autoterms&tab=logs')); ?>"
                   class="page-title-action"><?php esc_html_e('Logs', 'simple-tags'); ?> <span class="update-plugins"><span class="plugin-count"><?php echo esc_html($this->autoterms_logs_count()); ?></span></span></a>

                   </h1>

            <div class="wp-clearfix"></div>

            <form method="post" action="">


                <div class="tagcloudui st-tabbed">


                    <div class="autoterms-postbox-container">
                        <div id="poststuff">
                            <div class="taxopress-section postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <?php
                                        if ($autoterm_edit) {
                                            $active_tab = (isset($current['active_tab']) && !empty(trim($current['active_tab']))) ? $current['active_tab'] : 'autoterm_general';
                                            echo esc_html__('Edit Auto Terms', 'simple-tags');
                                            echo '<input type="hidden" name="edited_autoterm" value="' . esc_attr($current['ID']) . '" />';
                                            echo '<input type="hidden" name="taxopress_autoterm[ID]" value="' . esc_attr($current['ID']) . '" />';
                                            echo '<input type="hidden" name="taxopress_autoterm[active_tab]" class="taxopress-active-subtab" value="'.esc_attr($active_tab).'" />';
                                        } else {
                                            $active_tab = 'autoterm_general';
                                            echo '<input type="hidden" name="taxopress_autoterm[active_tab]" class="taxopress-active-subtab" value="" />';
                                            echo esc_html__('Add new Auto Terms', 'simple-tags');
                                        }
        ?>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div class="main">


                                        <?php if ($autoterm_limit) {
                                            echo '<div class="st-taxonomy-content promo-box-area"><div class="taxopress-warning upgrade-pro">

                                            <h2 style="margin-bottom: 5px;">' . esc_html__(
                                                'To create more Auto Terms, please upgrade to TaxoPress Pro.',
                                                'simple-tags'
                                            ) . '</h2>
                                                    <p>
                                            ' . esc_html__(
                                                'With TaxoPress Pro, you can create unlimited Auto Terms. You can create Auto Terms for any taxonomy.',
                                                'simple-tags'
                                            ) . '

                                            </p>
                                            </div></div>';

                                        } else {
                                            ?>


                                            <ul class="taxopress-tab">
                                                <li aria-current="<?php echo $active_tab === 'autoterm_general' ? 'true' : 'false'; ?>" class="autoterm_general_tab <?php echo $active_tab === 'autoterm_general' ? 'active' : ''; ?>" data-content="autoterm_general">
                                                    <a href="#autoterm_general"><span><?php esc_html_e('General',
                                                        'simple-tags'); ?></span></a>
                                                </li>
                                                <li aria-current="<?php echo $active_tab === 'autoterm_display' ? 'true' : 'false'; ?>" class="autoterm_display_tab <?php echo $active_tab === 'autoterm_display' ? 'active' : ''; ?>" data-content="autoterm_display">
                                                    <a href="#autoterm_display"><span><?php esc_html_e('Post Types',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'autoterm_terms' ? 'true' : 'false'; ?>" class="autoterm_terms_tab <?php echo $active_tab === 'autoterm_terms' ? 'active' : ''; ?>" data-content="autoterm_terms">
                                                    <a href="#autoterm_terms"><span><?php esc_html_e('Sources',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'autoterm_when_to_use' ? 'true' : 'false'; ?>" class="autoterm_when_to_use_tab <?php echo $active_tab === 'autoterm_when_to_use' ? 'active' : ''; ?>" data-content="autoterm_when_to_use">
                                                    <a href="#autoterm_when_to_use"><span><?php esc_html_e('When to Use',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'autoterm_options' ? 'true' : 'false'; ?>" class="autoterm_options_tab <?php echo $active_tab === 'autoterm_options' ? 'active' : ''; ?>" data-content="autoterm_options">
                                                    <a href="#autoterm_options"><span><?php esc_html_e('Options',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'autoterm_exceptions' ? 'true' : 'false'; ?>" class="autoterm_exceptions_tab <?php echo $active_tab === 'autoterm_exceptions' ? 'active' : ''; ?>" data-content="autoterm_exceptions">
                                                    <a href="#autoterm_exceptions"><span><?php esc_html_e(
                                                        'Exceptions',
                                                        'simple-tags'
                                                    ); ?></span></a>
                                                </li>

                                                <!--<li aria-current="<?php echo $active_tab === 'autoterm_oldcontent' ? 'true' : 'false'; ?>" class="autoterm_oldcontent_tab <?php echo $active_tab === 'autoterm_oldcontent' ? 'active' : ''; ?>" data-content="autoterm_oldcontent">
                                                    <a href="#autoterm_oldcontent"><span><?php esc_html_e('Existing Content',
                                                        'simple-tags'); ?></span></a>
                                                </li>-->

                                                <li aria-current="<?php echo $active_tab === 'autoterm_advanced' ? 'true' : 'false'; ?>" class="autoterm_advanced_tab <?php echo $active_tab === 'autoterm_advanced' ? 'active' : ''; ?>" data-content="autoterm_advanced">
                                                    <a href="#autoterm_advanced"><span><?php esc_html_e('Advanced',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                                <li aria-current="<?php echo $active_tab === 'autoterm_preview' ? 'true' : 'false'; ?>" class="autoterm_preview_tab <?php echo $active_tab === 'autoterm_preview' ? 'active' : ''; ?>" data-content="autoterm_preview">
                                                    <a href="#autoterm_preview"><span><?php esc_html_e('Preview',
                                                        'simple-tags'); ?></span></a>
                                                </li>

                                            </ul>

                                            <div class="st-taxonomy-content taxopress-tab-content">


                                                <table class="form-table taxopress-table autoterm_general"
                                                       style="<?php echo $active_tab === 'autoterm_general' ? '' : 'display:none;'; ?>">
                                                    <?php
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_tr_start();

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_th_start();
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_label('title', esc_html__('Title', 'simple-tags')) . $ui->get_required_span();
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_th_end() . $ui->get_td_start();

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_text_input([
                                                'namearray'   => 'taxopress_autoterm',
                                                'name'        => 'title',
                                                'textvalue'   => isset($current['title']) ? esc_attr($current['title']) : '',
                                                'maxlength'   => '32',
                                                'helptext'    => '',
                                                'required'    => true,
                                                'placeholder' => false,
                                                'wrap'        => false,
                                            ]);


                                            $options = [];
                                            foreach (get_all_taxopress_taxonomies() as $_taxonomy) {
                                                $_taxonomy = $_taxonomy->name;
                                                $tax       = get_taxonomy($_taxonomy);
                                                if (empty($tax->labels->name)) {
                                                    continue;
                                                }
                                                if ($tax->name === 'post_tag') {
                                                    $options[] = [
                                                        'attr'    => $tax->name,
                                                        'text'    => $tax->labels->name. ' ('.$tax->name.')',
                                                        'default' => 'true',
                                                    ];
                                                } else {
                                                    $options[] = [
                                                        'attr' => $tax->name,
                                                        'text' => $tax->labels->name. ' ('.$tax->name.')',
                                                    ];
                                                }
                                            }

                                            $select             = [
                                                'options' => $options,
                                            ];
                                            $selected           = isset($current) ? taxopress_disp_boolean($current['taxonomy']) : '';
                                            $select['selected'] = !empty($selected) ? $current['taxonomy'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input_main([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'taxonomy',
                                                'class'      => 'st-post-taxonomy-select',
                                                'labeltext'  => esc_html__('Taxonomy', 'simple-tags'),
                                                'required'   => true,
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $selected_option    = (isset($current) && isset($current['autoterm_from'])) ? $current['autoterm_from'] : '';

                                            $term_from_options = [
                                                ''              =>  esc_attr__('Don\'t scan Post Content or Title', 'simple-tags'),
                                                'post_content'  =>  esc_attr__('Post Content', 'simple-tags'),
                                                'post_title'    =>  esc_attr__('Post Title', 'simple-tags'),
                                                'posts'         => esc_attr__('Post Content and Title', 'simple-tags')
                                            ];
                                            ?>
                                                    <tr valign="top">
                                                        <th scope="row">
                                                            <label for="autoterm_from">
                                                                <?php esc_html_e('Find term in:', 'simple-tags'); ?>
                                                            </label>
                                                        </th>
                                                        <td>
                                                            <select class="" id="autoterm_from" name="taxopress_autoterm[autoterm_from]">
                                                                <?php foreach ($term_from_options as $option => $label) : ?>
                                                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($selected_option, $option); ?>>
                                                                        <?php echo esc_html($label); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr valign="top">
                                                        <th></th>
                                                        <td style="margin-top: 0; padding-top: 0;">
                                                            <table class="visbile-table st-autoterm-area-table">
                                                                <tr>
                                                                    <th scope="row" colspan="3" style="margin-top: 0; padding-top: 0; padding-bottom: 0;">
                                                                        <label>
                                                                            <?php echo esc_html__('Find in more areas:', 'simple-tags'); ?>
                                                                        </label>
                                                                    </th>
                                                                </tr>
                                                                <tr class="autoterm-custom-findin-row form-tr option">
                                                                    <td style="padding-left: 0; padding-top: 0;" colspan="3">
                                                                        <select class="autoterm-area-custom-type" style="max-width: 100%;">
                                                                            <?php
                                                                    $area_types = [
                                                                        'custom_fields' => esc_html__('Custom Field', 'simple-tags'),
                                                                        'taxonomies'    => esc_html__('Taxonomy', 'simple-tags'),
                                                                    ];
                                            foreach ($area_types as $area_type_value => $area_type_label) : ?>
                                                                                <option value="<?php echo esc_attr($area_type_value); ?>">
                                                                                    <?php echo esc_html($area_type_label); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </td>
                                                                </tr>
                                                                <tr class="autoterm-custom-findin-row form-tr fields">
                                                                    <td style="padding-left: 0; padding-top: 0;" colspan="3">
                                                                        <select class="autoterm-area-custom-taxonomy st-hide-content find-in-content taxonomies" style="max-width: 100%"
                                                                        placeholder="<?php echo esc_attr__("Search Taxonomy", "simple-tags"); ?>">
                                                                        <option value=""><?php echo esc_html__("Search Taxonomy", "simple-tags"); ?></option>
                                                                            <?php
                                            foreach (get_all_taxopress_taxonomies() as $_taxonomy) :
                                                $_taxonomy = $_taxonomy->name;
                                                $tax       = get_taxonomy($_taxonomy);
                                                if (empty($tax->labels->name)) {
                                                    continue;
                                                }
                                                ?>
                                                                                <option value="<?php echo esc_attr($tax->name); ?>">
                                                                                    <?php echo esc_html($tax->labels->name. ' ('.$tax->name.')'); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <div class="autoterm-field-area">
                                                                            <select style="width: 100%;" class="taxopress-custom-fields-search find-in-content custom_fields" 
                                                                                data-placeholder="<?php echo esc_attr__("Search Custom Field...", "simple-tags"); ?>" 
                                                                                data-nonce="<?php echo esc_attr(wp_create_nonce('taxopress-custom-fields-search')); ?>"></select>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <?php
                                                                    $find_in_customs_entries = (!empty($current['find_in_customs_entries']) && is_array($current['find_in_customs_entries'])) ? $current['find_in_customs_entries'] : [];

                                            $find_in_custom_fields_custom_items = (!empty($current['find_in_custom_fields_custom_items']) && is_array($current['find_in_custom_fields_custom_items'])) ? $current['find_in_custom_fields_custom_items'] : [];

                                            $find_in_taxonomies_custom_items = (!empty($current['find_in_taxonomies_custom_items']) && is_array($current['find_in_taxonomies_custom_items'])) ? $current['find_in_taxonomies_custom_items'] : [];

                                            if (!empty($find_in_customs_entries)) :
                                                foreach ($find_in_customs_entries as $entry_type => $entry_options) :

                                                    if (!empty($entry_options)) :
                                                        foreach ($entry_options as $entry_option) :
                                                            $checked_option = false;
                                                            if ($entry_type == 'custom_fields' && in_array($entry_option, $find_in_custom_fields_custom_items)) {
                                                                $checked_option = true;
                                                            } elseif (in_array($entry_option, $find_in_taxonomies_custom_items)) {
                                                                $checked_option = true;
                                                            }
                                                            ?>
                                                                                <tr valign="top" class="find-in-customs-row <?php echo esc_attr($entry_type); ?> <?php echo esc_attr($entry_option); ?>">
                                                                                    <td colspan="2" class="item-header">
                                                                                        <div>
                                                                                            <span class="action-checkbox">
                                                                                                <input type="hidden" name="find_in_customs_entries[<?php echo esc_attr($entry_type); ?>][]" value="<?php echo esc_attr($entry_option); ?>" /><input type="checkbox" id="<?php echo esc_attr($entry_option); ?>" name="find_in_<?php echo esc_attr($entry_type); ?>_custom_items[]" value="<?php echo esc_attr($entry_option); ?>" <?php checked($checked_option, true); ?> />
                                                                                            </span>
                                                                                            <label for="<?php echo esc_attr($entry_option); ?>">
                                                                                                <?php echo esc_html($entry_option); ?>
                                                                                            </label>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td>
                                                                                        <span class="delete">
                                                                                            <?php echo esc_html__("Delete", "simple-tags"); ?> 
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                                <?php endforeach;
                                                    endif;

                                                endforeach;
                                            endif;

                                            ?>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_td_end() . $ui->get_tr_end();
                                            ?>
                                                </table>

                                                <table class="form-table taxopress-table autoterm_display"
                                                       style="<?php echo $active_tab === 'autoterm_display' ? '' : 'display:none;'; ?>">
                                                       
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

                                            $term_auto_locations = [];
                                            foreach ($post_types as $post_type) {
                                                if (!in_array($post_type->name, ['attachment'])) {
                                                    $term_auto_locations[$post_type->name] = $post_type->label;
                                                }
                                            }

                                            echo '<tr valign="top"><th scope="row"><label>' . esc_html__(
                                                'Post Types to scan:',
                                                'simple-tags'
                                            ) . '</label> </th><td>
                                                    <table class="visbile-table">';
                                            foreach ($term_auto_locations as $key => $value) {


                                                echo '<tr valign="top"><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($value) . '</label></th><td>';
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_check_input([
                                                    'checkvalue' => esc_attr($key),
                                                    'checked'    => (!empty($current['post_types']) && is_array($current['post_types']) && in_array($key, $current['post_types'], true)) ? 'true' : 'false',
                                                    'name'       => esc_attr($key),
                                                    'namearray'  => 'post_types',
                                                    'textvalue'  => esc_attr($key),
                                                    'labeltext'  => "",
                                                    'wrap'       => false,
                                                ]);

                                                echo '</td></tr>';


                                            }
                                            echo '</table></td></tr>';
                                            ?>

                                                </table>


                                                <table class="form-table taxopress-table autoterm_when_to_use"
                                                       style="<?php echo $active_tab === 'autoterm_when_to_use' ? '' : 'display:none;'; ?>">
                                                    <tr valign="top" class="tab-group-tr"> 
                                                        <td colspan="2">
                                                            <div class="taxopress-group-wrap autoterm-tab-group when">
                                                                <div class="taxopress-button-group">

                                                                    <label class="post current">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_when_to_use_tab][]" value="post"><?php esc_html_e('Posts', 'simple-tags'); ?></label>
                                                                    <label class="schedule">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_when_to_use_tab][]" value="schedule"><?php esc_html_e('Schedule', 'simple-tags'); ?></label>
                                                                    <label class="existing-content">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_when_to_use_tab][]" value="existing_content"><?php esc_html_e('Existing Content', 'simple-tags'); ?></label>
                                                                    <label class="metaboxes">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_when_to_use_tab][]" value="metaboxes"><?php esc_html_e('Metaboxes', 'simple-tags'); ?></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php

                                            if ($autoterm_edit) {
                                                $default_select             = [
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
                                                $default_select             = [
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

                                            /**
                                             * Post
                                             */
                                            $selected           = (isset($current) && isset($current['autoterm_for_post'])) ? taxopress_disp_boolean($current['autoterm_for_post']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_for_post'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_for_post',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post fields-control',
                                                'labeltext'  => esc_html__('Post', 'simple-tags'),
                                                'aftertext'  => esc_html__('Enable Auto Terms when a post is saved or updated.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                                'namearray' => 'taxopress_autoterm',
                                                'name'      => 'terms_limit',
                                                'textvalue' => isset($current['terms_limit']) ? esc_attr($current['terms_limit']) : '5',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post',
                                                'labeltext' => esc_html__(
                                                    'Auto Terms Limit',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_html__('Limit the number of generated Auto Terms. \'0\' for unlimited terms', 'simple-tags'),
                                                'min'       => '0',
                                                'required'  => false,
                                            ]);


                                            $selected           = (isset($current) && isset($current['autoterm_target'])) ? taxopress_disp_boolean($current['autoterm_target']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_target'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_target',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post',
                                                'labeltext'  => esc_html__('Target content', 'simple-tags'),
                                                'aftertext'  => esc_html__('Only use Auto Terms on posts with no added terms.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            $selected           = (isset($current) && isset($current['autoterm_word'])) ? taxopress_disp_boolean($current['autoterm_word']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_word'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_word',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post',
                                                'labeltext'  => esc_html__('Whole words', 'simple-tags'),
                                                'aftertext'  => esc_html__('Only add terms when the word is an exact match. Do not make matches for partial words.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            $selected           = (isset($current) && isset($current['autoterm_hash'])) ? taxopress_disp_boolean($current['autoterm_hash']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_hash'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_hash',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post',
                                                'labeltext'  => esc_html__('Hashtags', 'simple-tags'),
                                                'aftertext'  => esc_html__('Support hashtags symbols # in Auto Terms.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $selected   = (isset($current) && isset($current['post_replace_type'])) ? taxopress_disp_boolean($current['post_replace_type']) : '';
                                            $taxonomy_replace_options['selected'] = !empty($selected) ? $current['post_replace_type'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_radio_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'post_replace_type',
                                                'class'      => 'autoterm_for_post autoterm-terms-when-to-field autoterm-terms-when-post',
                                                'labeltext'  => esc_html__(
                                                    'Auto Terms replacement settings',
                                                    'simple-tags'
                                                ),
                                                    'aftertext'  => esc_html__('This option determines what happens when adding new terms to posts.', 'simple-tags'),
                                                'selections' => $taxonomy_replace_options,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            /**
                                             * Schedule
                                             */
                                            $default_select     = [
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

                                            $selected           = (isset($current) && isset($current['autoterm_for_schedule'])) ? taxopress_disp_boolean($current['autoterm_for_schedule']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_for_schedule'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_for_schedule',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule fields-control',
                                                'labeltext'  => esc_html__('Schedule', 'simple-tags'),
                                                'aftertext'  => esc_html__('Enable Auto Terms for the "Schedule" feature.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                                'namearray' => 'taxopress_autoterm',
                                                'name'      => 'schedule_terms_limit',
                                                'textvalue' => isset($current['schedule_terms_limit']) ? esc_attr($current['schedule_terms_limit']) : '5',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                                                'labeltext' => esc_html__(
                                                    'Auto Terms Limit',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_html__('Limit the number of generated Auto Terms. \'0\' for unlimited terms', 'simple-tags'),
                                                'min'       => '0',
                                                'required'  => false,
                                            ]);


                                            $selected           = (isset($current) && isset($current['schedule_autoterm_target'])) ? taxopress_disp_boolean($current['schedule_autoterm_target']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_target'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'schedule_autoterm_target',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                                                'labeltext'  => esc_html__('Target content', 'simple-tags'),
                                                'aftertext'  => esc_html__('Only use Auto Terms on schedules with no added terms.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            $selected           = (isset($current) && isset($current['schedule_autoterm_word'])) ? taxopress_disp_boolean($current['schedule_autoterm_word']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_word'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'schedule_autoterm_word',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                                                'labeltext'  => esc_html__('Whole words', 'simple-tags'),
                                                'aftertext'  => esc_html__('Only add terms when the word is an exact match. Do not make matches for partial words.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            $selected           = (isset($current) && isset($current['schedule_autoterm_hash'])) ? taxopress_disp_boolean($current['schedule_autoterm_hash']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['schedule_autoterm_hash'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'schedule_autoterm_hash',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                                                'labeltext'  => esc_html__('Hashtags', 'simple-tags'),
                                                'aftertext'  => esc_html__('Support hashtags symbols # in Auto Terms.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $taxonomy_replace_options     = [
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

                                            $selected   = (isset($current) && isset($current['schedule_replace_type'])) ? taxopress_disp_boolean($current['schedule_replace_type']) : '';
                                            $taxonomy_replace_options['selected'] = !empty($selected) ? $current['schedule_replace_type'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_radio_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'schedule_replace_type',
                                                'class'      => 'autoterm_for_schedule autoterm-terms-when-to-field autoterm-terms-when-schedule',
                                                'labeltext'  => esc_html__(
                                                    'Auto Terms replacement settings',
                                                    'simple-tags'
                                                ),
                                                    'aftertext'  => esc_html__('This option determines what happens when adding new terms to posts.', 'simple-tags'),
                                                'selections' => $taxonomy_replace_options,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            /**
                                             * Existing Content
                                             */
                                            $selected           = (isset($current) && isset($current['autoterm_for_existing_content'])) ? taxopress_disp_boolean($current['autoterm_for_existing_content']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_for_existing_content'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_for_existing_content',
                                                'class'      => 'autoterm_for_existing_content autoterm-terms-when-to-field autoterm-terms-when-existing-content fields-control',
                                                'labeltext'  => esc_html__('Existing Content', 'simple-tags'),
                                                'aftertext'  => esc_html__('Enable Auto Terms for the "Existing Content" feature.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                                'namearray' => 'taxopress_autoterm',
                                                'name'      => 'existing_content_terms_limit',
                                                'textvalue' => isset($current['existing_content_terms_limit']) ? esc_attr($current['existing_content_terms_limit']) : '5',
                                                'class'      => 'autoterm_for_existing_content autoterm-terms-when-to-field autoterm-terms-when-existing-content',
                                                'labeltext' => esc_html__(
                                                    'Auto Terms Limit',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_html__('Limit the number of generated Auto Terms. \'0\' for unlimited terms', 'simple-tags'),
                                                'min'       => '0',
                                                'required'  => false,
                                            ]);

                                            $selected   = (isset($current) && isset($current['existing_content_replace_type'])) ? taxopress_disp_boolean($current['existing_content_replace_type']) : '';
                                            $taxonomy_replace_options['selected'] = !empty($selected) ? $current['existing_content_replace_type'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_radio_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'existing_content_replace_type',
                                                'class'      => 'autoterm_for_existing_content autoterm-terms-when-to-field autoterm-terms-when-existing-content',
                                                'labeltext'  => esc_html__(
                                                    'Auto Terms replacement settings',
                                                    'simple-tags'
                                                ),
                                                    'aftertext'  => esc_html__('This option determines what happens when adding new terms to posts.', 'simple-tags'),
                                                'selections' => $taxonomy_replace_options,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);


                                            /**
                                             * Metaboxes
                                             */
                                            $selected           = (isset($current) && isset($current['autoterm_for_metaboxes'])) ? taxopress_disp_boolean($current['autoterm_for_metaboxes']) : '';
                                            $default_select['selected'] = !empty($selected) ? $current['autoterm_for_metaboxes'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_for_metaboxes',
                                                'class'      => 'autoterm_for_metaboxes autoterm-terms-when-to-field autoterm-terms-when-metaboxes fields-control',
                                                'labeltext'  => esc_html__('Metaboxes', 'simple-tags'),
                                                'aftertext'  => esc_html__('Enable Auto Terms for the "Metaboxes" and the "Fast Update" feature.', 'simple-tags'),
                                                'selections' => $default_select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
                                            ]);
                                            ?>
                                                </table>




                                                <table class="form-table taxopress-table autoterm_terms"
                                                       style="<?php echo $active_tab === 'autoterm_terms' ? '' : 'display:none;'; ?>">
                                                    <tr valign="top" class="tab-group-tr"> 
                                                        <td colspan="2">
                                                            <div class="taxopress-group-wrap autoterm-tab-group source">
                                                                <div class="taxopress-button-group">
                                                                    <label class="existing current">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_tab][]" value="existing_terms"><?php esc_html_e('Existing Terms', 'simple-tags'); ?></label>
                                                                    <label class="openai">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_tab][]" value="open_ai"><?php esc_html_e('OpenAI', 'simple-tags'); ?></label>
                                                                    <label class="ibm-watson">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_tab][]" value="ibm_watson"><?php esc_html_e('IBM Watson', 'simple-tags'); ?></label>
                                                                    <label class="dandelion">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_tab][]" value="dandelion"><?php esc_html_e('Dandelion', 'simple-tags'); ?></label>
                                                                    <label class="lseg-refinitiv">
                                                                        <input disabled type="checkbox" name="taxopress_autoterm[autoterm_source_tab][]" value="lseg_refinitiv"><?php esc_html_e('LSEG / Refinitiv', 'simple-tags'); ?></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php

                                                if ($autoterm_edit) {
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
                                            $selected           = (isset($current) && isset($current['autoterm_use_taxonomy'])) ? taxopress_disp_boolean($current['autoterm_use_taxonomy']) : '';
                                            $select['selected'] = !empty($selected) ? $current['autoterm_use_taxonomy'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_use_taxonomy',
                                                'class'      => 'autoterm_use_taxonomy autoterm-terms-to-use-field autoterm-terms-use-existing fields-control',
                                                'labeltext'  => esc_html__('Existing taxonomy terms', 'simple-tags'),
                                                'aftertext'  => esc_html__('This will add existing terms from the taxonomy selected in the "General" tab.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
                                            ]);



                                            if ($autoterm_edit) {
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

                                            $selected           = (isset($current) && isset($current['autoterm_useall'])) ? taxopress_disp_boolean($current['autoterm_useall']) : '';
                                            $select['selected'] = !empty($selected) ? $current['autoterm_useall'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_useall',
                                                'class'      => 'autoterm_useall autoterm-terms-to-use-field autoterm-terms-use-existing',
                                                'labeltext'  => '',
                                                'aftertext'  => esc_html__('Use all the terms in the selected taxonomy.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                'required'    => false,
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
                                            $selected           = (isset($current) && isset($current['autoterm_useonly'])) ? taxopress_disp_boolean($current['autoterm_useonly']) : '';
                                            $select['selected'] = !empty($selected) ? $current['autoterm_useonly'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_useonly',
                                                'class'      => 'autoterm_useonly autoterm-terms-to-use-field autoterm-terms-use-existing',
                                                'labeltext'  => ' ',
                                                'aftertext'  => esc_html__('Use only some terms in the selected taxonomy.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            $specific_terms = (isset($current) && isset($current['specific_terms'])) ? taxopress_change_to_array($current['specific_terms']) : '';
                                            echo '<tr class="autoterm_useonly_options '. ($selected === 'false' ? 'st-hide-content' : '') .'" valign="top"><th scope="row"><label for=""></label></th><td>';
                                            echo '<div class="auto-terms-to-use-error" style="display:none;"> '.esc_html__('Please choose an option for "Sources"', 'simple-tags').' </div>';

                                            echo '<div class="st-autoterms-single-specific-term autoterm-terms-use-existing">
                                                            <input autocomplete="off" type="text" class="st-full-width specific_terms_input" name="specific_terms_search" maxlength="32" placeholder="'. esc_attr(__('Choose the terms to use.', 'simple-tags')) .'" value="">
                                                        </div>';
                                            echo '<ul class="taxopress-term-list-style">';
                                            if (!empty($specific_terms)) {
                                                foreach ($specific_terms as $specific_term) {
                                                    if (!empty($specific_term)) {
                                                        ?>
                                                            <li class="taxopress-term-li">
                                                                <span class="display-text"><?php echo esc_html($specific_term); ?></span>
                                                                <span class="remove-term-row">
                                                                    <span class="dashicons dashicons-no-alt"></span>
                                                                </span>
                                                                <input type="hidden" class="taxopress-terms-names" name="specific_terms[]" value="<?php echo esc_attr($specific_term); ?>">
                                                            </li>
                                                            <?php
                                                    }
                                                }
                                            }
                                            echo '</div>';
                                            echo '</td></tr>';


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
                                            $selected           = (isset($current) && isset($current['suggest_local_terms_show_post_count'])) ? taxopress_disp_boolean($current['suggest_local_terms_show_post_count']) : '';
                                            $select['selected'] = !empty($selected) ? $current['suggest_local_terms_show_post_count'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'suggest_local_terms_show_post_count',
                                                'labeltext'  => esc_html__('Show Term Post Count', 'simple-tags'),
                                                'aftertext'  => esc_html__('This will show the number of posts attached to the terms.', 'simple-tags'),
                                                'class'      => 'autoterm-terms-to-use-field autoterm-terms-use-existing',
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            do_action('taxopress_autoterms_after_autoterm_terms_to_use', $current);

                                            ?>
                                                </table>


                                                <table class="form-table taxopress-table autoterm_options"
                                                       style="<?php echo $active_tab === 'autoterm_options' ? '' : 'display:none;'; ?>">
                                                    <?php

                                            if (taxopress_is_pro_version() && taxopress_is_synonyms_enabled()) {
                                                $select = [
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
                                                $selected           = (isset($current) && isset($current['synonyms_term'])) ? taxopress_disp_boolean($current['synonyms_term']) : '';
                                                $select['selected'] = !empty($selected) ? $current['synonyms_term'] : '';
                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_select_checkbox_input([
                                                    'namearray'  => 'taxopress_autoterm',
                                                    'name'       => 'synonyms_term',
                                                    'labeltext'  => esc_html__(
                                                        'Add terms if synonyms found',
                                                        'simple-tags'
                                                    ),
                                                    'aftertext'  => esc_html__(
                                                        'TaxoPress will add a term to the post if a synonym is found.',
                                                        'simple-tags'
                                                    ),
                                                    'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                ]);
                                            }


                                            $post_status_objects = taxopress_get_post_statuses();
                                            $post_status_options = [];
                                            foreach ($post_status_objects as $status_slug => $status_object) {
                                                if (in_array($status_slug, ['publish', 'future', 'draft', 'pending'])) {
                                                    $post_status_options[$status_slug] = $status_object->label;
                                                }
                                            }

                                            echo '<tr valign="top"><th scope="row"><label for="">' . esc_html__('Content statuses', 'simple-tags') . '</label>  <span class="required">*</span></th><td>';

                                            echo '<div class="auto-terms-post-status-error" style="display:none;"> '.esc_html__('Please choose an option for "Content statuses"', 'simple-tags').' </div>';

                                            $autoterms_post_status = (!empty($current['post_status'])) ? (array)$current['post_status'] : ['publish'];
                                            foreach ($post_status_options as $key => $value) {
                                                $checked_status = (in_array($key, $autoterms_post_status)) ? 'checked' : '';
                                                echo '<input class="autoterm_post_status_'.esc_attr($key).'" type="checkbox" name="post_status[]" value="'.esc_attr($key).'" '.esc_attr($checked_status).'> ' . esc_html($value) . ' <br /><br />';

                                            }
                                            echo '</td></tr>';

                                            ?>

                                                </table>


                                                        <table class="form-table taxopress-table autoterm_exceptions" style="<?php echo $active_tab === 'autoterm_exceptions' ? '' : 'display:none;'; ?>">
                                                            <?php
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                    echo $ui->get_textarea_input([
                                                        'namearray' => 'taxopress_autoterm',
                                                        'name'      => 'autoterm_exclude',
                                                        'rows'      => '4',
                                                        'cols'      => '40',
                                                        'class'     => 'autocomplete-input auto-terms-stopwords',
                                                        'textvalue' => isset($current['autoterm_exclude']) ? esc_attr($current['autoterm_exclude']) : '',
                                                        'labeltext' => esc_html__(
                                                            'Exclude terms from Auto Term',
                                                            'simple-tags'
                                                        ),
                                                        'helptext'  => esc_html__(
                                                            'Choose terms to be excluded from auto terms.',
                                                            'simple-tags'
                                                        ),
                                                        'required'  => false,
                                                    ]);

                                            $html_exclusions = [
                                                //headers
                                                'h1'     => esc_attr__('H1', 'simple-tags'),
                                                'h2'     => esc_attr__('H2', 'simple-tags'),
                                                'h3'     => esc_attr__('H3', 'simple-tags'),
                                                'h4'     => esc_attr__('H4', 'simple-tags'),
                                                'h5'     => esc_attr__('H5', 'simple-tags'),
                                                'h6'     => esc_attr__('H6', 'simple-tags'),
                                                //html elements
                                                'a'      => esc_attr__('a', 'simple-tags'),
                                                'script' => esc_attr__('script', 'simple-tags'),
                                                'style'  => esc_attr__('style', 'simple-tags'),
                                                'pre'    => esc_attr__('pre', 'simple-tags'),
                                                'code'   => esc_attr__('code', 'simple-tags'),
                                            ];

                                            echo '<tr valign="top"><th scope="row"><label>' . esc_html__(
                                                'Prevent Auto Term inside elements',
                                                'simple-tags'
                                            ) . '</label><br /><small style=" color: #646970;">' . esc_html__(
                                                'Terms inside these HTML tags will not be used to generate terms.',
                                                'simple-tags'
                                            ) . '</small></th><td>
                                                    <table class="visbile-table st-html-exclusion-table" style="width: 100%;">';
                                            foreach ($html_exclusions as $key => $value) {

                                                echo '<tr valign="top"><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($value) . '</label></th><td>';

                                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                echo $ui->get_check_input([
                                                    'checkvalue' => $key,
                                                    'checked'    => (!empty($current['html_exclusion']) && is_array($current['html_exclusion']) && in_array(
                                                        $key,
                                                        $current['html_exclusion'],
                                                        true
                                                    )) ? 'true' : 'false',
                                                    'name'       => esc_attr($key),
                                                    'namearray'  => 'html_exclusion',
                                                    'textvalue'  => esc_attr($key),
                                                    'labeltext'  => esc_html($key),
                                                    'labeldescription' => true,
                                                    'wrap'       => false,
                                                ]);

                                                echo '</td></tr>';

                                                if ($key === 'h6') {
                                                    echo '<tr valign="top"><th style="padding: 0;" scope="row"><hr /></th><td style="padding: 0;"><hr /></td></tr>';
                                                }
                                            }

                                            /**
                                             * Fires after the autoterms html_exclusions.
                                             * @param $current array
                                             * @param taxopress_admin_ui $ui Admin UI instance.
                                             */
                                            do_action('taxopress_autoterms_after_html_exclusions', $current, $ui);

                                            echo '</table></td></tr>';

                                            /**
                                             * Fires after the autoterms html_exclusions tr.
                                             * @param $current array
                                             * @param taxopress_admin_ui $ui Admin UI instance.
                                             */
                                            do_action('taxopress_autoterms_after_html_exclusions_tr', $current, $ui);

                                            ?>

                                                        </table>


                                                <table class="form-table taxopress-table autoterm_oldcontent"
                                                       style="<?php echo $active_tab === 'autoterm_oldcontent' ? '' : 'display:none;'; ?>">

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
                                            $selected           = (isset($current) && isset($current['autoterm_existing_content_exclude'])) ? taxopress_disp_boolean($current['autoterm_existing_content_exclude']) : '';
                                            $select['selected'] = !empty($selected) ? $current['autoterm_existing_content_exclude'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'autoterm_existing_content_exclude',
                                                'class'      => '',
                                                'labeltext'  => esc_html__('Exclude previously analyzed content', 'simple-tags'),
                                                'aftertext'  => esc_html__('This enables you to skip posts that have already been analyzed by the Existing Content feature.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                                'namearray' => 'taxopress_autoterm',
                                                'name'      => 'existing_terms_batches',
                                                'textvalue' => isset($current['existing_terms_batches']) ? esc_attr($current['existing_terms_batches']) : '2',
                                                'labeltext' => esc_html__(
                                                    'Limit per batches',
                                                    'simple-tags'
                                                ),
                                                'helptext'  => esc_html__('This enables you to add Auto Terms to existing content in batches. If you have a lot of existing content, set this to a lower number to avoid timeouts.', 'simple-tags'),
                                                'min'       => '1',
                                                'required'  => true,
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                                'namearray' => 'taxopress_autoterm',
                                                'name'      => 'existing_terms_sleep',
                                                'textvalue' => isset($current['existing_terms_sleep']) ? esc_attr($current['existing_terms_sleep']) : '10',
                                                'labeltext' => esc_html__('Batches wait time', 'simple-tags'),
                                                'helptext'  => esc_html__('This is the wait time (in seconds) between processing batches of Auto Terms. If you have a lot of existing content, set this to a higher number to avoid timeouts.', 'simple-tags'),
                                                'min'       => '0',
                                                'required'  => true,
                                            ]);

                                            $select             = [
                                                'options' => [
                                                    [
                                                        'attr' => '1',
                                                        'text' => esc_attr__('24 hours ago', 'simple-tags')
                                                    ],
                                                    [
                                                        'attr' => '7',
                                                        'text' => esc_attr__('7 days ago', 'simple-tags')
                                                    ],
                                                    [
                                                        'attr' => '14',
                                                        'text' => esc_attr__('2 weeks ago', 'simple-tags')
                                                    ],
                                                    [
                                                        'attr' => '30',
                                                        'text' => esc_attr__('1 month ago', 'simple-tags'),
                                                        'default' => 'true'
                                                    ],
                                                    [
                                                        'attr' => '180',
                                                        'text' => esc_attr__('6 months ago', 'simple-tags')
                                                    ],
                                                    [
                                                        'attr' => '365',
                                                        'text' => esc_attr__('1 year ago', 'simple-tags')
                                                    ],
                                                    [
                                                        'attr'    => '0',
                                                        'text'    => esc_attr__('No limit', 'simple-tags')
                                                    ],
                                                ],
                                            ];

                                            if (isset($current) && is_array($current)) {
                                                $select             = [
                                                    'options' => [
                                                        [
                                                            'attr' => '1',
                                                            'text' => esc_attr__('24 hours ago', 'simple-tags')
                                                        ],
                                                        [
                                                            'attr' => '7',
                                                            'text' => esc_attr__('7 days ago', 'simple-tags')
                                                        ],
                                                        [
                                                            'attr' => '14',
                                                            'text' => esc_attr__('2 weeks ago', 'simple-tags')
                                                        ],
                                                        [
                                                            'attr' => '30',
                                                            'text' => esc_attr__('1 month ago', 'simple-tags'),
                                                        ],
                                                        [
                                                            'attr' => '180',
                                                            'text' => esc_attr__('6 months ago', 'simple-tags')
                                                        ],
                                                        [
                                                            'attr' => '365',
                                                            'text' => esc_attr__('1 year ago', 'simple-tags')
                                                        ],
                                                        [
                                                            'attr'    => '0',
                                                            'text'    => esc_attr__('No limit', 'simple-tags'),
                                                            'default' => 'true'
                                                        ],
                                                    ],
                                                ];
                                            }

                                            $selected           = (isset($current) && isset($current['limit_days'])) ? taxopress_disp_boolean($current['limit_days']) : '';
                                            $select['selected'] = !empty($selected) ? $current['limit_days'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_number_select([
                                                'namearray'  => 'taxopress_autoterm',
                                                'name'       => 'limit_days',
                                                'labeltext'  => esc_html__(
                                                    'Limit Auto Terms, based on published date',
                                                    'simple-tags'
                                                ),
                                                    'aftertext'  => esc_html__('This setting allows you to add Auto Terms only to recent content.', 'simple-tags'),
                                                'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);
                                            ?>

                                                       <tr valign="top"><th scope="row"><label><?php echo esc_html__('Existing Content', 'simple-tags'); ?></label></th>

                                                       <td>
                                                           <input type="submit" class="button taxopress-autoterm-all-content" value="<?php echo esc_attr(__('Add Auto Terms to existing content', 'simple-tags')); ?>">
                                                           <span class="spinner taxopress-spinner"></span>

                                                           <p class="taxopress-field-description description">
                                                               <?php echo esc_html__('Click the button to add Auto Terms to existing content.', 'simple-tags'); ?>
                                                            </p>

                                                            <div class="auto-term-content-result-title"></div>

                                                            </div>

                                                            <ul class="auto-term-content-result"></ul>
                                                        </td></tr>

                                                </table>


                                                <table class="form-table taxopress-table autoterm_advanced"
                                                       style="<?php echo $active_tab === 'autoterm_advanced' ? '' : 'display:none;'; ?>">

                                                        <?php do_action('taxopress_autoterms_after_autoterm_advanced', $current); ?>

                                                </table>


                                                <table class="form-table taxopress-table autoterm_preview"
                                                       style="<?php echo $active_tab === 'autoterm_preview' ? '' : 'display:none;'; ?>">
                                                    <tr class="autoterm-description-tr">
                                                        <td colspan="2">
                                                        <p class="description" style="margin-bottom: 20px;"><?php echo esc_attr__('Save changes to the settings before using this feature.', 'simple-tags'); ?></p>
                                                            <div class="taxopress-autoterm-fetch-wrap">
                                                                <select class="preview-post-types-select"
                                                                style="width: auto;max-width: 33%;display: none;">
                                                                    <?php foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object):
                                                                        if (!in_array($post_type, ['attachment'])) {
                                                                            if (empty($default_post_type)) {
                                                                                $default_post_type = $post_type;
                                                                                $posts = $posts = get_posts(['post_type' => $post_type, 'numberposts' => 1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
                                                                                if (!empty($posts)) {
                                                                                    $post = $posts[0];
                                                                                }
                                                                            }
                                                                            ?>
                                                                            <option value='<?php echo esc_attr($post_type); ?>'
                                                                                data-singular_label="<?php echo esc_html($post_type_object->labels->singular_name); ?>">
                                                                                <?php echo esc_html($post_type_object->labels->name); ?>
                                                                            </option>
                                                                        <?php
                                                                        }
                                                                    endforeach; ?>
                                                                </select>

                                                                <select class="preview-post-select taxopress-post-search"
                                                                style="max-width: 250px; width: 250px;"
                                                                    data-placeholder="<?php echo esc_attr__('Select...', 'simple-tags'); ?>"
                                                                    data-allow-clear="true"
                                                                    data-nonce="<?php echo esc_attr(wp_create_nonce('taxopress-post-search')); ?>">
                                                                    <?php if (is_object($post)) : ?>
                                                                        <option value='<?php echo esc_attr($post->ID); ?>'>
                                                                                <?php echo esc_html($post->post_title); ?>
                                                                            </option>
                                                                    <?php endif; ?>
                                                                </select>
                                                                <button class="button button-secondary preview-button">
                                                                    <div class="spinner"></div>
                                                                    <span class="btn-text"><?php echo esc_attr__('Preview', 'simple-tags'); ?></span>
                                                                </button>
                                                            </div>

                                                            <div class="taxopress-autoterm-result">
                                                                <div class="output"></div>
                                                                <div class="response"></div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                </table>


                                            </div>


                                        <?php }//end new fields
        ?>


                                        <div class="clear"></div>


                                    </div>
                                </div>
                            </div>


                            <?php if ($autoterm_limit) { ?>

                                <div class="pp-version-notice-bold-purple" style="margin-left:0px;">
                                    <div class="pp-version-notice-bold-purple-message"><?php echo esc_html__('You\'re using TaxoPress Free.
                                        The Pro version has more features and support.', 'simple-tags'); ?>
                                    </div>
                                    <div class="pp-version-notice-bold-purple-button"><a
                                            href="https://taxopress.com/taxopress/" target="_blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                                    </div>
                                </div>

                            <?php } ?>
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
                    <div class="taxopress-right-sidebar-wrapper" style="min-height: 205px;<?php echo ($autoterm_limit) ? 'display: none;' : ''; ?>">


                        <?php
                        if (!$autoterm_limit) { ?>
                            <p class="submit">

                                <?php
            wp_nonce_field('taxopress_addedit_autoterm_nonce_action',
                'taxopress_addedit_autoterm_nonce_field');
                            if (!empty($_GET) && !empty($_GET['action']) && 'edit' === $_GET['action']) { ?>
                                    <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-autoterm-submit"
                                           name="autoterm_submit"
                                           value="<?php echo esc_attr(esc_attr__('Save Auto Terms',
                                               'simple-tags')); ?>"/>
                                    <?php
                            } else { ?>
                                    <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-autoterm-submit"
                                           name="autoterm_submit"
                                           value="<?php echo esc_attr(esc_attr__('Add Auto Terms',
                                               'simple-tags')); ?>"/>
                                <?php } ?>


                                <input type="hidden" name="cpt_tax_status" id="cpt_tax_status"
                                       value="<?php echo esc_attr($tab); ?>"/>
                            </p>

                            <?php
                        }
        ?>

                    </div>

                                                    <?php do_action('taxopress_admin_after_sidebar'); ?>
                <div class="taxopress-advertisement-right-sidebar">
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="advertisement-box-content postbox">
                                <div class="postbox-header">
                                    <h3 class="advertisement-box-header hndle is-non-sortable">
                                        <span><?php echo esc_html__('TaxoPress and Languages', 'simple-tags'); ?></span>
                                    </h3>
                                </div>
                                <div class="inside">
                                    <p><?php echo sprintf(esc_html__('Please note that this is an automatic tool. It does have limitations around languages, types of content, and other factors. %1s Please read this documentation. %2s', 'simple-tags'), '<a href="https://taxopress.com/docs/characters/">', '</a>'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


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
            class="remodal-confirm"><?php echo esc_html__('Yes', 'simple-tags'); ?></button>
</div>

        <?php
    }

}
