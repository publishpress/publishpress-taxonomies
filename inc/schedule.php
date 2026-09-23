<?php

if (!class_exists('SimpleTags_Autoterms_Schedule')) {
    class SimpleTags_Autoterms_Schedule
    {
        public const MENU_SLUG = 'st_options';

        public static $instance;

        public $logs_table;

        public function __construct()
        {

            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts'], 11);
        }

        public static function get_instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function admin_enqueue_scripts()
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter to check page, no state change
            if (isset($_GET['page']) && $_GET['page'] == 'st_autoterms_schedule') {
                wp_enqueue_style('st-taxonomies-css');
            }
        }

        public function admin_menu()
        {
            $hook = add_submenu_page(
                self::MENU_SLUG,
                esc_html__('Auto Terms Schedule', 'simple-tags'),
                esc_html__('Auto Terms Schedule', 'simple-tags'),
                'simple_tags',
                'st_autoterms_schedule',
                [
                    $this,
                    'page_manage_autoterms_schedule',
                ]
            );
            add_action("load-$hook", [$this, 'save_autoterms_schedule_settings']);
            add_action("load-$hook", [$this, 'screen_option']);
        }

        public function screen_option()
        {
            $option = 'per_page';

            $args   = [
                'label'   => esc_html__('Number of items per page', 'simple-tags'),
                'default' => 20,
                'option'  => 'st_autoterms_schedule_logs_per_page'
            ];
            $this->logs_table = new SimpleTags_Schedule_Logs();

            add_screen_option($option, $args);
        }

        public function save_autoterms_schedule_settings()
        {

            if (
                !empty($_POST['taxopress_autoterm_schedule_test_run'])
                && !empty($_POST['_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce'])), 'taxopress_autoterm_schedule_nonce')
                && current_user_can('simple_tags')
            ) {
                $autoterms_schedule = taxopress_get_autoterms_schedule_data();
                $cron_schedule = isset($autoterms_schedule['cron_schedule']) ? $autoterms_schedule['cron_schedule'] : 'disable';

                if ($cron_schedule === 'disable') {
                    add_action('admin_notices', function () {
                        echo wp_kses_post(
                            taxopress_admin_notices_helper(
                                esc_html__('Schedule is disabled. Please enable a schedule frequency to run a test.', 'simple-tags'),
                                false
                            )
                        );
                    });
                    return;
                }

                $hook_name = 'taxopress_cron_autoterms_' . $cron_schedule;
                wp_schedule_single_event(time(), $hook_name);
                spawn_cron();

                add_action('admin_notices', function () use ($hook_name) {
                    echo wp_kses_post(
                        taxopress_admin_notices_helper(
                            sprintf(
                                esc_html__('Scheduled the cron event %s to run now. The original event will not be affected.', 'simple-tags'),
                                '<code>' . esc_html($hook_name) . '</code>'
                            ),
                            true
                        )
                    );
                });
                return;
            }

            if (
                !empty($_POST['taxopress_autoterm_schedule_submit'])
                && !empty($_POST['_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce'])), 'taxopress_autoterm_schedule_nonce')
                && current_user_can('simple_tags')
            ) {
                $auto_term_ids = !empty($_POST['taxopress_autoterm_schedule']['autoterm_id']) ? array_map('intval', (array)$_POST['taxopress_autoterm_schedule']['autoterm_id']) : [];

                $cron_schedule = !empty($_POST['taxopress_autoterm_schedule']['cron_schedule']) ? sanitize_text_field(wp_unslash($_POST['taxopress_autoterm_schedule']['cron_schedule'])) : 'disable';
                $schedule_terms_batches = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_batches']) ? (int)$_POST['taxopress_autoterm_schedule']['schedule_terms_batches'] : '';
                $schedule_terms_sleep = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_sleep']) ? (int)$_POST['taxopress_autoterm_schedule']['schedule_terms_sleep'] : '';
                $schedule_terms_limit_days = !empty($_POST['taxopress_autoterm_schedule']['schedule_terms_limit_days']) ? sanitize_text_field(wp_unslash($_POST['taxopress_autoterm_schedule']['schedule_terms_limit_days'])) : '';
                $autoterm_schedule_exclude = !empty($_POST['taxopress_autoterm_schedule']['autoterm_schedule_exclude']) ? (int)$_POST['taxopress_autoterm_schedule']['autoterm_schedule_exclude'] : '';

                $response_message = esc_html__('An error occured.', 'simple-tags');
                $response_sucess  = false;
                if (empty($schedule_terms_batches)) {
                    $response_message = esc_html__('Limit per batches is required.', 'simple-tags');
                } elseif (empty($schedule_terms_sleep)) {
                    $response_message = esc_html__('Batches wait time is required.', 'simple-tags');
                } else {
                    $auto_term_schedule_settings = [
                        'autoterm_id' => $auto_term_ids,
                        'cron_schedule' => $cron_schedule,
                        'schedule_terms_batches' => $schedule_terms_batches,
                        'schedule_terms_sleep' => $schedule_terms_sleep,
                        'schedule_terms_limit_days' => $schedule_terms_limit_days,
                        'autoterm_schedule_exclude' => $autoterm_schedule_exclude,
                    ];
                    update_option('taxopress_autoterms_schedule', $auto_term_schedule_settings);

                    wp_clear_scheduled_hook('taxopress_cron_autoterms_weekly');
                    do_action('taxopress_clear_schedule_cron_hooks');

                    if ($cron_schedule == 'weekly') {
                        wp_schedule_event(time(), 'weekly', 'taxopress_cron_autoterms_weekly');
                    }

                    do_action('taxopress_schedule_cron_events', $cron_schedule);

                    $autoterm_data = taxopress_get_autoterm_data();
                    $autoterm_data_selected = [];

                    if (!empty($auto_term_ids)) {
                        foreach ($auto_term_ids as $term_id) {
                            if (isset($autoterm_data[$term_id])) {
                                $autoterm_data_selected[$term_id] = $autoterm_data[$term_id];
                            }
                        }
                    }
                    $autoterm_data = $autoterm_data_selected;

                    $schedule_enabled = false;
                    foreach ($autoterm_data_selected as $term_data) {
                        if (!empty($term_data['autoterm_for_schedule'])) {
                            $schedule_enabled = true;
                            break;
                        }
                    }

                    if (!$schedule_enabled) {
                        $response_message = esc_html__('Schedule is not enabled for selected Auto Terms.', 'simple-tags');
                        $response_sucess  = false;
                    } else {
                        $response_message = esc_html__('Settings updated successfully.', 'simple-tags');
                        $response_sucess  = true;
                    }
                }

                add_action('admin_notices', function () use ($response_message, $response_sucess) {
                    echo wp_kses_post(taxopress_admin_notices_helper($response_message, $response_sucess));
                });
            }
        }

        public function page_manage_autoterms_schedule()
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter to set default sort, no state change
            if (!isset($_GET['order'])) {
                $_GET['order'] = 'name-asc';
            }

            settings_errors(__CLASS__);
            ?>
            <?php

            $ui = new taxopress_admin_ui();

            $autoterms_schedule = taxopress_get_autoterms_schedule_data();

            ?>
            <div class="wrap taxopress-split-wrap taxopress-autoterm-schedule">
                <h1><?php echo esc_html__('Auto Terms Schedule', 'simple-tags'); ?> </h1>
                <div class="taxopress-description">
                    <?php esc_html_e('This feature allows you to run the Auto Terms feature on a schedule. This is helpful if you regularly import content into WordPress. PublishPress Taxonomies can run on a schedule and add terms to your imported content.', 'simple-tags'); ?>
                </div>
                <div class="wp-clearfix"></div>
                <form method="post" id="auto_term_schedule_form" action="">
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
                                        <table class="form-table taxopress-table autoterm_schedule">
                                            <?php
                                            $autoterm_data = taxopress_get_autoterm_data();
                                            $selected_autoterm = !empty($autoterms_schedule['autoterm_id']) ? array_map('intval', (array)$autoterms_schedule['autoterm_id']) : [];
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
                                                    if (in_array($autoterm_settings['ID'], $selected_autoterm)) {
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
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'autoterm_id',
                                            'class'      => 'taxopress-multi-select2',
                                            'labeltext'  => esc_html__(
                                                'Auto Terms setting',
                                                'simple-tags'
                                            ),
                                            'aftertext'  => esc_html__('Select Auto Terms settings to use when running the "Schedule" feature.', 'simple-tags') . ' ',
                                            'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            'multiple'   => true,
                                            ]);

                                            $cron_options = [
                                            'disable' => __('Do not run on a schedule', 'simple-tags'),
                                            'weekly'  => __('Weekly', 'simple-tags'),
                                            ];
                                            ?>
                                            <tr valign="top">
                                                <th scope="row"><label><?php echo esc_html__('Schedule Frequency', 'simple-tags'); ?></label></th>
                                
                                                <td>
                                                    <?php
                                                    $cron_schedule  = (!empty($autoterms_schedule['cron_schedule'])) ? $autoterms_schedule['cron_schedule'] : 'disable';

                                                    ?>
                                                    <input type="hidden"
                                                        id="cron_schedule_value"
                                                        name="taxopress_autoterm_schedule[cron_schedule]"
                                                        value="<?php echo esc_attr($cron_schedule); ?>" />

                                                    <?php
                                                    $disable_checked = $cron_schedule === 'disable' ? 'checked' : '';
                                                    ?>
                                                    <label class="autoterm_cron_disable_label">
                                                        <input
                                                            type="checkbox"
                                                            id="autoterm_cron_disable"
                                                            class="autoterm_cron_disable"
                                                            <?php echo esc_html($disable_checked); ?>
                                                        />
                                                        <?php echo esc_html($cron_options['disable']); ?>
                                                    </label>

                                                    <p class="description autoterm_cron_help <?php echo $cron_schedule === 'disable' ? '' : 'st-hide-content'; ?>">
                                                        <?php echo esc_html__("Disable 'Do not run on a schedule' to select schedule frequency.", 'simple-tags'); ?>
                                                    </p>

                                                    <div class="autoterm_cron_frequency <?php echo $cron_schedule === 'disable' ? 'st-hide-content' : ''; ?>">
                                                        <?php
                                                        unset($cron_options['disable']);
                                                        foreach ($cron_options as $option => $label) {
                                                            $checked_status = ($cron_schedule === $option) ? 'checked' : '';
                                                            ?>
                                                            <label>
                                                                <input
                                                                    type="radio"
                                                                    class="autoterm_cron_radio"
                                                                    id="autoterm_cron_<?php echo esc_attr($option); ?>"
                                                                    name="taxopress_autoterm_schedule[cron_schedule_choice]"
                                                                    value="<?php echo esc_attr($option); ?>"
                                                                    <?php echo esc_html($checked_status); ?>
                                                                />
                                                                <?php echo esc_html($label); ?>
                                                            </label>
                                                            <br /><br />
                                                        <?php } ?>
                                                        <?php do_action('taxopress_schedule_frequency_fields', $cron_schedule); ?>
                                                    </div>
                                                </td>
                                            </tr>
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
                                            $selected           = (isset($autoterms_schedule) && isset($autoterms_schedule['autoterm_schedule_exclude'])) ? taxopress_disp_boolean($autoterms_schedule['autoterm_schedule_exclude']) : '';
                                            $select['selected'] = !empty($selected) ? $autoterms_schedule['autoterm_schedule_exclude'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_checkbox_input([
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'autoterm_schedule_exclude',
                                            'class'      => '',
                                            'labeltext'  => esc_html__('Exclude previously analyzed content', 'simple-tags'),
                                            'aftertext'  => esc_html__('This enables you to skip posts that have already been analyzed by the Schedule feature.', 'simple-tags'),
                                            'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                            'namearray' => 'taxopress_autoterm_schedule',
                                            'name'      => 'schedule_terms_batches',
                                            'textvalue' => isset($autoterms_schedule['schedule_terms_batches']) ? esc_attr($autoterms_schedule['schedule_terms_batches']) : '20',
                                            'labeltext' => esc_html__(
                                                'Limit per batches',
                                                'simple-tags'
                                            ),
                                            'helptext'  => esc_html__('This enables your scheduled Auto Terms to run in batches. If you have a lot of content, set this to a lower number to avoid timeouts.', 'simple-tags'),
                                            'min'       => '1',
                                            'required'  => true,
                                            ]);

                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_number_input([
                                            'namearray' => 'taxopress_autoterm_schedule',
                                            'name'      => 'schedule_terms_sleep',
                                            'textvalue' => isset($autoterms_schedule['schedule_terms_sleep']) ? esc_attr($autoterms_schedule['schedule_terms_sleep']) : '10',
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

                                            if (isset($autoterms_schedule) && is_array($autoterms_schedule)) {
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

                                            $selected           = (isset($autoterms_schedule) && isset($autoterms_schedule['schedule_terms_limit_days'])) ? taxopress_disp_boolean($autoterms_schedule['schedule_terms_limit_days']) : '';
                                            $select['selected'] = !empty($selected) ? $autoterms_schedule['schedule_terms_limit_days'] : '';
                                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            echo $ui->get_select_number_select([
                                            'namearray'  => 'taxopress_autoterm_schedule',
                                            'name'       => 'schedule_terms_limit_days',
                                            'labeltext'  => esc_html__(
                                                'Limit Auto Terms, based on published date',
                                                'simple-tags'
                                            ),
                                            'aftertext'  => esc_html__('This setting can limit your scheduled Auto Terms query to only recent content. We recommend using this feature to avoid timeouts on large sites.', 'simple-tags'),
                                            'selections' => $select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ]);

                                            ?>
                                        </table>
                                    </div>
                                </div>
                                <div class="tp-submit-div">
                                    <?php wp_nonce_field('taxopress_autoterm_schedule_nonce', '_nonce'); ?>
                                    <input type="submit" class="button-primary taxopress-taxonomy-submit taxopress-autoterm-schedule-submit" name="taxopress_autoterm_schedule_submit" value="<?php echo esc_attr__('Save Settings', 'simple-tags'); ?>">
                                    <input type="submit" class="button-secondary taxopress-taxonomy-submit taxopress-autoterm-schedule-test-run" name="taxopress_autoterm_schedule_test_run" value="<?php echo esc_attr__('Test Run', 'simple-tags'); ?>">
                                </div>
                            </div>

                            <div id="postbox-container-1" class="postbox-container tp-flex-item">
                                <div id="side-sortables" class="meta-box-sortables ui-sortable" style="">
                                    <div id="submitdiv" class="postbox">
                                        <div class="postbox-header">
                                            <h2 class="hndle ui-sortable-handle post_terms_icon preview-title">
                                                <?php esc_html_e('Recent Schedule Runs', 'simple-tags'); ?>
                                            </h2>
                                        </div>
                                        <div class="inside">
                                            <div id="minor-publishing">
                                                <div class="sidebar-body-wrap">
                                                    <p class="description"><?php echo sprintf(esc_html__('You can see full log details on the %1s screen', 'simple-tags'), '<a target="_blank" href="' . esc_url(admin_url('admin.php?page=st_autoterms&tab=logs')) . '">' . esc_html__('Auto Terms Logs', 'simple-tags') . '</a>'); ?></p>
                                                        <?php
                                                        $this->logs_table->prepare_items();
                                                        ?>
                                        
                                                        <div id="col-container" class="wp-clearfix">
                                        
                                                            <div class="col-wrap">
                                                                <form action="<?php echo esc_url(add_query_arg('', '')); ?>" method="post">
                                                                    <?php $this->logs_table->display(); ?>
                                                                </form>
                                                                <div class="form-wrap edit-term-notes">
                                                                    <p><?php esc_html__('Description here.', 'simple-tags') ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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

        public function autoterms_logs_count()
        {

            $count = taxopress_autoterms_logs_data(1)['counts'];
            return '(' . number_format_i18n($count) . ')';
        }
    }
}
