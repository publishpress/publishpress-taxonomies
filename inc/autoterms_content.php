<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Autoterms_Content
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
        if (isset($_GET['page']) && $_GET['page'] == 'st_autoterms_content') {
            wp_enqueue_style('st-taxonomies-css');
        }
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
            esc_html__('Auto Terms Existing', 'simple-tags'),
            esc_html__('Auto Terms Existing', 'simple-tags'),
            'simple_tags',
            'st_autoterms_content',
            [
                $this,
                'page_manage_autoterms_content',
            ]
        );
        add_action("load-$hook", [$this, 'save_autoterms_content_settings']);
    }

    public function save_autoterms_content_settings()
    {

        if (
            !empty($_POST['taxopress_autoterm_content_submit'])
            && !empty($_POST['_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce'])), 'taxopress_autoterm_content_nonce')
            && current_user_can('simple_tags')
        ) {
            $auto_term_id = !empty($_POST['taxopress_autoterm_content']['autoterm_id']) ? (int)$_POST['taxopress_autoterm_content']['autoterm_id'] : 0;

            $existing_terms_batches = !empty($_POST['taxopress_autoterm_content']['existing_terms_batches']) ? (int)$_POST['taxopress_autoterm_content']['existing_terms_batches'] : 0;
            $existing_terms_sleep = !empty($_POST['taxopress_autoterm_content']['existing_terms_sleep']) ? (int)$_POST['taxopress_autoterm_content']['existing_terms_sleep'] : 0;
            $limit_days = !empty($_POST['taxopress_autoterm_content']['limit_days']) ? taxopress_sanitize_text_field(wp_unslash($_POST['taxopress_autoterm_content']['limit_days'])) : 0;
            $autoterm_existing_content_exclude = !empty($_POST['taxopress_autoterm_content']['autoterm_existing_content_exclude']) ? (int)$_POST['taxopress_autoterm_content']['autoterm_existing_content_exclude'] : 0;

            $response_message = esc_html__('An error occured.', 'simple-tags');
            $response_sucess  = false;
            if (empty($existing_terms_batches)) {
                $response_message = esc_html__('Limit per batches is required.', 'simple-tags');
            } elseif (empty($existing_terms_sleep)) {
                $response_message = esc_html__('Batches wait time is required.', 'simple-tags');
            } else {
                $auto_term_settings = [
                    'autoterm_id' => $auto_term_id,
                    'existing_terms_batches' => $existing_terms_batches,
                    'existing_terms_sleep' => $existing_terms_sleep,
                    'limit_days' => $limit_days,
                    'autoterm_existing_content_exclude' => $autoterm_existing_content_exclude,
                ];
                update_option('taxopress_autoterms_content', $auto_term_settings);
                $response_message = esc_html__('Settings updated successfully.', 'simple-tags');
                $response_sucess  = true;
            }

            add_action('admin_notices', function () use ($response_message, $response_sucess) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo taxopress_admin_notices_helper($response_message, $response_sucess);
            });
        }
    }

    /**
     * Create our settings page output.
     *
     * @internal
     */
    public function page_manage_autoterms_content()
    {
        settings_errors(__CLASS__);
        ?>
        <?php

        $ui = new taxopress_admin_ui();

        $autoterms_content = taxopress_get_autoterms_content_data();

        ?>
        <div class="wrap taxopress-split-wrap taxopress-autoterm-content">
            <h1><?php echo esc_html__('Auto Terms Existing Content', 'simple-tags'); ?> </h1>
            <div class="taxopress-description">
                <?php esc_html_e('This feature can scan your existing content and automatically assign new and existing terms.', 'simple-tags'); ?>
            </div>
            <div class="wp-clearfix"></div>
            <form method="post" id="auto_term_content_form" action="">
                <div id="poststuff">
                    <div id="post-body" class="taxopress-section metabox-holder columns-2">
                        <div class="tp-flex-item">
                            <div id="post-body-content" class="right-body-content" style="position: relative;">
                                <div class="postbox-header">
                                    <h2 class="hndle ui-sortable-handle">
                                        <?php echo esc_html__('Settings', 'simple-tags'); ?>
                                    </h2>
                                </div>
                                <div class="main">
                                    <table class="form-table taxopress-table autoterm_oldcontent">
                                        <?php
                                        $autoterm_data = taxopress_get_autoterm_data();
                                        $selected_autoterm = !empty($autoterms_content['autoterm_id']) ? (int)$autoterms_content['autoterm_id'] : '';
                                        if (empty($autoterm_data)) :
                                            $auto_term_opionts = [
                                                [
                                                    'attr' => '',
                                                    'text' => __('Select an option...', 'simple-tags')
                                                ]
                                            ];
                                        else :
                                            $auto_term_opionts = [];
                                            foreach ($autoterm_data as $autoterm_settings) {
                                                $current_option = [];
                                                $current_option['attr'] = $autoterm_settings['ID'];
                                                $current_option['text'] = $autoterm_settings['title'];
                                                if ($selected_autoterm == $autoterm_settings['ID']) {
                                                    $current_option['default'] = 'true';
                                                }
                                                $auto_term_opionts[] = $current_option;
                                            }
                                        endif;
                                        $select = [];
                                        $select['options']  = $auto_term_opionts;
                                        $select['selected'] = '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_checkbox_input_main([
                                        'namearray'  => 'taxopress_autoterm_content',
                                        'name'       => 'autoterm_id',
                                        'labeltext'  => esc_html__(
                                            'Auto Terms setting',
                                            'simple-tags'
                                        ),
                                        'aftertext'  => esc_html__('Select an Auto Terms configuration to use when scanning content.', 'simple-tags'),
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
                                        $selected           = (isset($autoterms_content['autoterm_existing_content_exclude'])) ? taxopress_disp_boolean($autoterms_content['autoterm_existing_content_exclude']) : '';
                                        $select['selected'] = !empty($selected) ? $autoterms_content['autoterm_existing_content_exclude'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_checkbox_input([
                                        'namearray'  => 'taxopress_autoterm_content',
                                        'name'       => 'autoterm_existing_content_exclude',
                                        'class'      => '',
                                        'labeltext'  => esc_html__('Exclude previously analyzed content', 'simple-tags'),
                                        'aftertext'  => esc_html__('This enables you to skip posts that have already been analyzed by the Existing Content feature.', 'simple-tags'),
                                        'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_number_input([
                                        'namearray' => 'taxopress_autoterm_content',
                                        'name'      => 'existing_terms_batches',
                                        'textvalue' => isset($autoterms_content['existing_terms_batches']) ? esc_attr($autoterms_content['existing_terms_batches']) : '2',
                                        'labeltext' => esc_html__(
                                            'Limit per batches',
                                            'simple-tags'
                                        ),
                                        'helptext'  => esc_html__('This enables you to add Terms to existing content in batches. If you have a lot of existing content, set this to a lower number to avoid timeouts.', 'simple-tags'),
                                        'min'       => '1',
                                        'required'  => true,
                                        ]);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_number_input([
                                        'namearray' => 'taxopress_autoterm_content',
                                        'name'      => 'existing_terms_sleep',
                                        'textvalue' => isset($autoterms_content['existing_terms_sleep']) ? esc_attr($autoterms_content['existing_terms_sleep']) : '10',
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

                                        if (is_array($autoterms_content)) {
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

                                        $selected           = (isset($autoterms_content['limit_days'])) ? taxopress_disp_boolean($autoterms_content['limit_days']) : '';
                                        $select['selected'] = !empty($selected) ? $autoterms_content['limit_days'] : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo $ui->get_select_number_select([
                                        'namearray'  => 'taxopress_autoterm_content',
                                        'name'       => 'limit_days',
                                        'labeltext'  => esc_html__(
                                            'Limit Auto Terms, based on published date',
                                            'simple-tags'
                                        ),
                                        'aftertext'  => esc_html__('This setting allows you to add Terms only to recent content.', 'simple-tags'),
                                        'selections' => $select,// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        ]);
                                        ?>
                                    </table>
                                </div>
                            </div>
                            <div class="tp-submit-div">
                                <?php wp_nonce_field('taxopress_autoterm_content_nonce', '_nonce'); ?>
                                <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-autoterm-content-submit" name="taxopress_autoterm_content_submit" value="<?php echo esc_attr__('Save Settings', 'simple-tags'); ?>">
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container tp-flex-item">
                            <div id="side-sortables" class="meta-box-sortables ui-sortable" style="">
                                <div id="submitdiv" class="postbox"
                                    data-ai-source="post_terms" data-preview="Preview">
                                    <div class="postbox-header">
                                        <h2 class="hndle ui-sortable-handle post_terms_icon preview-title">
                                            <?php esc_html_e('Results', 'simple-tags'); ?>
                                        </h2>
                                    </div>
                                    <div class="inside">
                                        <div id="minor-publishing">
                                            <div class="sidebar-body-wrap">
                                                <p class="description"><?php echo sprintf(esc_html__('You can see full log details on the %1s screen', 'simple-tags'), '<a target="_blank" href="' . admin_url('admin.php?page=st_autoterms&tab=logs') . '">' . esc_html__('Auto Terms Logs', 'simple-tags') . '</a>'); ?></p>
                                                <div class="submit-action">
                                                    <span class="spinner taxopress-spinner"></span>
                                                    <input type="submit" class="button taxopress-autoterm-all-content"
                                                        value="<?php echo esc_attr(__('Add Auto Terms to existing content', 'simple-tags')); ?>">
                                                </div>
                                                <div class="auto-term-content-result-title"></div>
                                                <ul class="auto-term-content-result"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php //do_action('taxopress_admin_after_sidebar');?>
                            </div>

                        </div>
                        <br class="clear">
                    </div>
                </div>
            </form>
        </div>
        <?php SimpleTags_Admin::printAdminFooter(); ?>
        <?php
    }
}
