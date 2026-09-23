<?php

/**
 * TODO : Autoload classes
 */

require_once plugin_dir_path(__FILE__) . 'classes/TaxoPressAiAjax.php';
require_once plugin_dir_path(__FILE__) . 'classes/TaxoPressAiApi.php';
require_once plugin_dir_path(__FILE__) . 'classes/TaxoPressAiFields.php';
require_once plugin_dir_path(__FILE__) . 'classes/TaxoPressAiUtilities.php';

if (!class_exists('TaxoPress_AI_Module')) {
    /**
     * class TaxoPress_AI_Module
     */
    class TaxoPress_AI_Module
    {
        public const MENU_SLUG = 'st_options';

        public const PAGE_MENU_SLUG = 'st_taxopress_ai';

        public const TAXOPRESS_AI_OPTION_KEY = 'st_taxopress_ai_settings';

        // class instance
        public static $instance;

        /**
         * Construct the TaxoPress_AI_Module class
         */
        public function __construct()
        {
            // Admin menu
            add_action('admin_menu', [$this, 'admin_menu']);
            // Script and Styles
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 11);
            // Migrate Auto Terms and Suggest Terms Dandelion and Open Calais API
            add_action('admin_init', [$this, 'migrate_legacy_settings']);
            // Save settings data
            add_action('admin_init', [$this, 'save_settings']);
            // Handle ai integration preview post search
            add_action('wp_ajax_taxopress_ai_post_search', ['TaxoPressAiAjax', 'handle_ai_post_search']);
            // Handle AI preview ajax request
            add_action('wp_ajax_taxopress_ai_preview_feature', ['TaxoPressAiAjax', 'handle_taxopress_ai_preview_feature']);
            // Handle AI post terms update.
            add_action('wp_ajax_taxopress_ai_add_post_term', ['TaxoPressAiAjax', 'handle_taxopress_ai_post_term']);
            // Handle AI new term.
            add_action('wp_ajax_taxopress_ai_add_new_term', ['TaxoPressAiAjax', 'handle_taxopress_ai_new_term']);
            // Ajax action, JS Helper and admin action
            add_action('wp_ajax_simpletags', [$this, 'ajax_check']);
            // PublishPress Taxonomies AI metabox default result on page load
            add_action('load_taxopress_ai_term_results', [$this, 'load_result']);
            // Register metabox for suggest tags, for post, and optionnaly cpt.
            add_action('admin_head', [$this, 'admin_head'], 1);

            add_filter('taxopress_validate_term_before_insert', ['TaxoPressAiAjax', 'taxopress_validate_term_before_insert'], 10, 2);

            // AJAX handlers for all tab label renaming
            add_action('wp_ajax_taxopress_ai_save_existing_terms_label', [$this, 'taxopress_save_existing_terms_label']);
            add_action('wp_ajax_taxopress_ai_save_post_terms_label', [$this, 'taxopress_save_post_terms_label']);
            add_action('wp_ajax_taxopress_ai_save_suggest_local_terms_label', [$this, 'taxopress_save_suggest_local_terms_label']);
            add_action('wp_ajax_taxopress_ai_save_create_terms_label', [$this, 'taxopress_save_create_terms_label']);

            // Ensure tab labels are persisted in dedicated options
            add_action('admin_init', [$this, 'ensure_tab_label_options']);

            add_action('wp_ajax_taxopress_role_preview', ['TaxoPressAiAjax', 'handle_role_preview']);
            add_action('wp_ajax_taxopress_preview_update', ['TaxoPressAiAjax', 'handle_preview_update']);
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
         * Migrate Auto Terms and Suggest Terms Dandelion and Open Calais API
         *
         * @return void
         */
        public function migrate_legacy_settings()
        {

            if (!get_option('migrate_taxopress_ai_legacy_api') && current_user_can('simple_tags')) {
                $taxopress_ai_settings = get_option(self::TAXOPRESS_AI_OPTION_KEY);
                if (empty($taxopress_ai_settings)) {
                    $suggestterm_datas = function_exists('taxopress_get_suggestterm_data') ? taxopress_get_suggestterm_data() : [];
                    $autoterm_datas = function_exists('taxopress_get_autoterm_data') ? taxopress_get_autoterm_data() : [];
                    $taxopress_ai_settings = [];

                    $dandelion_api_token = false;
                    $open_calais_api     = false;

                    if (!empty($suggestterm_datas) && is_array($suggestterm_datas)) {
                        foreach ($suggestterm_datas as $suggestterm_data) {
                            if (!$dandelion_api_token && !empty($suggestterm_data['terms_datatxt_access_token'])) {
                                $dandelion_api_token = $suggestterm_data['terms_datatxt_access_token'];
                            }
                            if (!$open_calais_api && !empty($suggestterm_data['terms_opencalais_key'])) {
                                $open_calais_api = $suggestterm_data['terms_opencalais_key'];
                            }
                        }
                    }

                    if (!empty($autoterm_datas) && is_array($autoterm_datas)) {
                        foreach ($autoterm_datas as $autoterm_data) {
                            if (!$dandelion_api_token && !empty($autoterm_data['terms_datatxt_access_token'])) {
                                $dandelion_api_token = $autoterm_data['terms_datatxt_access_token'];
                            }
                            if (!$open_calais_api && !empty($autoterm_data['terms_opencalais_key'])) {
                                $open_calais_api = $autoterm_data['terms_opencalais_key'];
                            }
                        }
                    }

                    if ($dandelion_api_token) {
                        $taxopress_ai_settings['dandelion_api_token'] = $dandelion_api_token;
                    }

                    if ($open_calais_api) {
                        $taxopress_ai_settings['open_calais_api_key'] = $open_calais_api;
                    }

                    if (!empty($taxopress_ai_settings)) {
                        update_option(self::TAXOPRESS_AI_OPTION_KEY, $taxopress_ai_settings);
                    }
                }
                update_option('migrate_taxopress_ai_legacy_api', true);
            }
        }

        public function save_settings()
        {

            if (wp_doing_ajax()) {
                return;
            }

            if (!is_admin()) {
                return;
            }

            if (!current_user_can('simple_tags')) {
                return;
            }

            if (empty($_GET)) {
                return;
            }

            if (!isset($_GET['page'])) {
                return;
            }

            if ($_GET['page'] !== self::PAGE_MENU_SLUG) {
                return;
            }

            if (!empty($_POST['taxopress_ai_integration']) && !empty($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'taxopress_ai_settings_nonce_action')) {
                $sanitized_data = map_deep(wp_unslash($_POST['taxopress_ai_integration']), 'sanitize_text_field');

                foreach (['open_ai', 'ibm_watson', 'dandelion', 'open_calais'] as $field) {
                    if (!isset($sanitized_data[$field . '_cache_result'])) {
                        $sanitized_data[$field . '_cache_result'] = 0;
                    }
                }
                foreach (['open_ai', 'ibm_watson', 'dandelion', 'open_calais', 'suggest_local_terms', 'existing_terms', 'post_terms'] as $field) {
                    if (!isset($sanitized_data[$field . '_show_post_count'])) {
                        $sanitized_data[$field . '_show_post_count'] = 0;
                    }
                }

                update_option(self::TAXOPRESS_AI_OPTION_KEY, $sanitized_data);
                add_action('admin_notices', function () {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo taxopress_admin_notices_helper(esc_html__('Settings updated successfully.', 'simple-tags'));
                });
                return;
            }

            if (!isset($_POST['updateoptions'])) {
                return;
            }

            if (!empty($_POST['_wpnonce']) && wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'updateresetoptions-simpletags')) {
                if (isset($_POST['post_ID']) || isset($_POST['post_type'])) {
                    return;
                }

                check_admin_referer('updateresetoptions-simpletags');

                $current_options = SimpleTags_Plugin::get_option();

                // Handle taxopress_ai_integration array fields (API settings) - if present in metabox forms
                if (!empty($_POST['taxopress_ai_integration'])) {
                    $sanitized_data = map_deep(wp_unslash($_POST['taxopress_ai_integration']), 'sanitize_text_field');

                    foreach (['open_ai', 'ibm_watson', 'dandelion', 'open_calais'] as $field) {
                        if (!isset($sanitized_data[$field . '_cache_result'])) {
                            $sanitized_data[$field . '_cache_result'] = 0;
                        }
                    }
                    foreach (['open_ai', 'ibm_watson', 'dandelion', 'open_calais', 'suggest_local_terms', 'existing_terms', 'post_terms'] as $field) {
                        if (!isset($sanitized_data[$field . '_show_post_count'])) {
                            $sanitized_data[$field . '_show_post_count'] = 0;
                        }
                    }

                    update_option(self::TAXOPRESS_AI_OPTION_KEY, $sanitized_data);
                }

                $active_tab = '';
                if (!empty($_POST['taxopress_ai_integration']['active_tab'])) {
                    $active_tab = sanitize_key($_POST['taxopress_ai_integration']['active_tab']);
                } elseif (!empty($_GET['tab'])) {
                    $active_tab = sanitize_key($_GET['tab']);
                }
                $active_post_type = !empty($_POST['taxopress_ai_integration']['active_post_type']) ? sanitize_key($_POST['taxopress_ai_integration']['active_post_type']) : '';
                $active_role      = !empty($_POST['taxopress_ai_integration']['active_role']) ? sanitize_key($_POST['taxopress_ai_integration']['active_role']) : '';

                $option_data = (array) include(STAGS_DIR . '/inc/helper.options.admin.php');

                $sections_to_check = [];
                if ($active_tab === 'metabox') {
                    $sections_to_check = ['taxopress-ai'];
                } elseif ($active_tab === 'metabox_access') {
                    $sections_to_check = ['metabox'];
                } else {
                    $sections_to_check = ['taxopress-ai', 'metabox'];
                }

                $expected_checkbox_keys = [];
                $expected_multiselect_keys = [];
                foreach ($sections_to_check as $section) {
                    if (empty($option_data[$section]) || !is_array($option_data[$section])) {
                        continue;
                    }
                    foreach ($option_data[$section] as $opt) {
                        if (empty($opt) || !is_array($opt)) {
                            continue;
                        }
                        $field_id   = isset($opt[0]) ? $opt[0] : '';
                        $field_type = isset($opt[2]) ? $opt[2] : '';
                        $field_class = isset($opt[5]) ? $opt[5] : '';

                        if ($section === 'taxopress-ai' && $active_post_type) {
                            if (strpos($field_class, 'taxopress-ai-' . $active_post_type . '-content') === false) {
                                continue;
                            }
                        }

                        if ($section === 'metabox' && $active_role) {
                            if (strpos($field_class, 'metabox-' . $active_role . '-content') === false) {
                                continue;
                            }
                        }

                        if ($field_type === 'checkbox' && $field_id) {
                            $expected_checkbox_keys[] = $field_id;
                            continue;
                        }

                        if (($field_type === 'multiselect' || $field_type === 'multiselect_with_desc_top') && $field_id) {
                            $expected_multiselect_keys[] = $field_id;
                            continue;
                        }

                        if ($field_type === 'sub_multiple_checkbox' && isset($opt[3]) && is_array($opt[3])) {
                            foreach ($opt[3] as $sub_key => $sub_val) {
                                $expected_checkbox_keys[] = $sub_key;
                            }
                        }

                        // multiselect / per-role checkboxes might use arrays; skip those (they are handled by posted arrays)
                    }
                }

                foreach (array_unique($expected_checkbox_keys) as $chk_key) {
                    if (!array_key_exists($chk_key, $_POST)) {
                        $current_options[$chk_key] = '0';
                    }
                }

                foreach (array_unique($expected_multiselect_keys) as $ms_key) {
                    if (!array_key_exists($ms_key, $_POST)) {
                        $current_options[$ms_key] = [];
                    }
                }

                foreach ($_POST as $key => $value) {
                    if (in_array($key, ['_wpnonce', '_wp_http_referer', 'updateoptions', 'taxopress_ai_integration'])) {
                        continue;
                    }

                    if (is_array($value)) {
                        $current_options[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $current_options[$key] = sanitize_text_field($value);
                    }
                }

                SimpleTags_Plugin::set_option($current_options);

                add_action('admin_notices', function () {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo taxopress_admin_notices_helper(esc_html__('Settings updated successfully.', 'simple-tags'));
                });
            }
        }

        /**
         * Init somes JS and CSS need for this feature
         *
         * @return void
         * @author Olatechpro
         */
        public function admin_enqueue_scripts()
        {
            global $pagenow;

            if (
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                isset($_GET['page']) && $_GET['page'] == self::PAGE_MENU_SLUG ||
                    (
                        in_array($pagenow, ['post-new.php', 'post.php', 'page.php', 'page-new.php', 'edit.php']) &&
                        !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . get_post_type() . '_metabox'))
                    )
            ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Script enqueue context, no state modification
                if ($pagenow && !empty($_GET['post'])) {
                    $main_post_screen = false;
                } else {
                    $main_post_screen = true;
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Script enqueue context, no state modification
                $fast_update_screen = isset($_GET['page']) && $_GET['page'] == self::PAGE_MENU_SLUG;

                if ($fast_update_screen) {
                    $tp_current_screen = self::PAGE_MENU_SLUG;
                } else {
                    $tp_current_screen = 'post.php';
                }

                $manage_link = add_query_arg(
                    [
                        'page' => 'st_taxopress_ai'
                    ],
                    admin_url('admin.php')
                );

                $removed_taxonomies = taxopress_user_role_removed_taxonomy();
                $removed_taxonomies_tax = $removed_taxonomies['taxonomies'];
                $removed_taxonomies_css = $removed_taxonomies['custom_css'];

                if ($fast_update_screen) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Script enqueue context, no state modification
                    $preview_post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
                    if (empty($preview_post_type)) {
                        foreach (TaxoPressAiUtilities::get_post_types_options() as $pt => $pt_obj) {
                            if (!in_array($pt, ['attachment'])) {
                                $preview_post_type = $pt;
                                break;
                            }
                        }
                    }
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Script enqueue context, no state modification
                    $metabox_filters_enabled = !empty($preview_post_type) ? SimpleTags_Plugin::get_option_value('taxopress_ai_' . $preview_post_type . '_metabox_filters') : false;
                } elseif (function_exists('get_post_type')) {
                    $current_post_type = get_post_type();
                    if ($current_post_type) {
                        $metabox_filters_enabled = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $current_post_type . '_metabox_filters');
                    }
                }



                wp_enqueue_style('taxopress-admin-select2');
                wp_enqueue_script('taxopress-admin-select2');

                wp_enqueue_script('taxopress-ai-editor-js', plugins_url('', __FILE__) . '/assets/js/taxopress-ai-editor.js', array(
                    'jquery', 'taxopress-admin-select2'
                ), STAGS_VERSION);

                wp_enqueue_style('taxopress-ai-editor-css', plugins_url('', __FILE__) . '/assets/css/taxopress-ai-editor.css', [], STAGS_VERSION, 'all');

                if (!empty($removed_taxonomies_css) && !$main_post_screen && !$fast_update_screen) {
                    wp_add_inline_style('taxopress-ai-editor-css', '' . implode(',', $removed_taxonomies_css) . ' {display:none !important;}');
                }

                $metabox_display_option = 'default';
                if (!$fast_update_screen && function_exists('get_post_type')) {
                    $current_post_type = get_post_type();
                    if ($current_post_type) {
                        $metabox_display_option = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $current_post_type . '_metabox_display_option');
                        if (empty($metabox_display_option)) {
                            $metabox_display_option = 'default';
                        }
                    }
                }

                wp_localize_script(
                    'taxopress-ai-editor-js',
                    'taxoPressAIRequestAction',
                    [
                        'requiredSuffix' => esc_html__('Please choose a post to use with PublishPress Taxonomies AI.', 'simple-tags'),
                        'nonce' => wp_create_nonce('taxopress-ai-ajax-nonce'),
                        // phpcs:ignore Squiz.PHP.CommentedOutCode -- Configuration URL, intentionally commented for future use
                        'apiEditLink' => '',//'<span class="edit-suggest-term-metabox"> <a target="blank" href="' . $manage_link . '"> '. esc_html__('Manage API Configuration', 'simple-tags') .' </a></span>'
                        'fieldTabs' => TaxoPressAiFields::get_fields_tabs(),
                        'removed_tax' => $removed_taxonomies_tax,
                        'current_screen' => $tp_current_screen,
                        'metabox_display_option' => $metabox_display_option,
                        'metabox_filters_enabled' => $metabox_filters_enabled,
                        'label_empty_error' => esc_html__('Label can\'t be empty.', 'simple-tags'),
                        'label_too_long_error' => esc_html__('Label can\'t exceed 30 characters.', 'simple-tags'),
                        'unknown_tab_error' => esc_html__('Unknown tab type.', 'simple-tags'),
                        'save_error' => esc_html__('Error saving label.', 'simple-tags'),
                        'requiredSuffix' => esc_html__('Please choose a post to preview.', 'simple-tags'),
                    ]
                );
            }
        }

        /**
         * Add WP admin menu for PublishPress Taxonomies ai
         *
         * @return void
         * @author ojopau;
         */
        public function admin_menu()
        {
            $hook = add_submenu_page(
                self::MENU_SLUG,
                esc_html__('Metaboxes', 'simple-tags'),
                esc_html__('Metaboxes', 'simple-tags'),
                'simple_tags',
                self::PAGE_MENU_SLUG,
                [
                    $this,
                    'page_manage_taxopress_ai',
                ]
            );
        }


        /**
         * @return void
         */
        public function page_manage_taxopress_ai()
        {

            settings_errors(__CLASS__);
            $post = 0;

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page display context, no state modification
            $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'preview';

            $tabs = [
                'preview' => __('Preview', 'simple-tags'),
                'metabox' => __('Metabox Settings', 'simple-tags'),
                'metabox_access' => __('Metabox Access', 'simple-tags')
            ];

            ?>
            <div class="wrap st_wrap st_taxopress_ai-wrap st-manage-taxonomies-page <?php echo esc_attr(self::PAGE_MENU_SLUG . '-wrap'); ?>">
                <h1>
                    <?php echo esc_html__('Metaboxes', 'simple-tags'); ?>
                </h1>
                <div class="taxopress-description">
                <?php esc_html_e('This feature allows you to customize the metabox interface you see when adding terms to posts.', 'simple-tags'); ?> 
                </div>

                <h2 class="nav-tab-wrapper">
                    <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                        <a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_MENU_SLUG, 'tab' => $tab_key], admin_url('admin.php'))); ?>" 
                        class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    <?php endforeach; ?>
                </h2>
                
                <div class="wp-clearfix"></div>
                
                <form method="post" action="">
                   <?php wp_nonce_field('updateresetoptions-simpletags'); ?>
                   <input type="hidden" name="taxopress_ai_integration[active_tab]" class="taxopress-active-subtab" value="<?php echo esc_attr($current_tab); ?>" />
                    
                    <div id="poststuff">
                        <div id="post-body" class="taxopress-section metabox-holder columns-2">
                            <div>
                                <div id="post-body-content" class="right-body-content" style="position: relative;">
                                    <?php
                                    switch ($current_tab) {
                                        case 'metabox':
                                            $this->render_metabox_settings_tab();
                                            break;
                                        case 'metabox_access':
                                            $this->render_metabox_access_tab();
                                            break;
                                        case 'preview':
                                        default:
                                            $this->render_preview_tab($post);
                                            break;
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        }

        /**
         * Render PublishPress Taxonomies AI fields
         */
        private function render_taxopress_ai_fields($fields)
        {
            $option_actual = SimpleTags_Plugin::get_option();

            $output = '';
            $pt_index = 0;

            foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
                if (in_array($post_type, ['attachment'])) {
                    continue;
                }

                $active_class = ($pt_index === 0) ? 'active' : '';
                $display_style = ($pt_index === 0) ? '' : 'style="display:none;"';

                $output .= '<div id="taxopress-ai-' . esc_attr($post_type) . '-content" class="post-type-content ' . esc_attr($active_class) . '" ' . $display_style . '>';

                foreach ($fields as $option) {
                    if (empty($option) || !is_array($option)) {
                        continue;
                    }

                    $field_id = $option[0];
                    $field_label = $option[1];
                    $field_type = $option[2];
                    $field_options = isset($option[3]) ? $option[3] : '';
                    $field_description = isset($option[4]) ? $option[4] : '';
                    $field_class = isset($option[5]) ? $option[5] : '';

                    if (strpos($field_class, 'taxopress-ai-' . $post_type . '-content') === false) {
                        continue;
                    }

                    if ($field_type === 'header') {
                        $clean_header = wp_strip_all_tags($field_label);
                        if (!empty($clean_header) && strpos($clean_header, 'Metabox') !== false) {
                            $output .= '<h3 class="' . esc_attr($field_class) . '">' . esc_html($clean_header) . '</h3>';
                        }
                        continue;
                    }

                    if ($field_type === 'helper') {
                        if (!empty($field_description)) {
                            $output .= '<p class="description ' . esc_attr($field_class) . '">' . esc_html($field_description) . '</p>';
                        }
                        continue;
                    }

                    $input_type = '';
                    $desc_html_tag = 'span';

                    // Generate input based on field type
                    switch ($field_type) {
                        case 'checkbox':
                            $checked = !empty($option_actual[$field_id]) ? 'checked="checked"' : '';
                            $input_type = '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="1" ' . $checked . ' />';
                            break;

                        case 'sub_multiple_checkbox':
                            $desc_html_tag = 'div';
                            $input_type_array = array();
                            foreach ($field_options as $sub_field_name => $sub_field_option) {
                                $checked_option = !empty($option_actual[$sub_field_name]) ? (int) $option_actual[$sub_field_name] : 0;
                                $selected_option = ($checked_option > 0) ? true : false;
                                $sub_field_description = !empty($sub_field_option['description']) ? '<br /><span class="description stpexplan">' . $sub_field_option['description'] . '</span>' : '';
                                $input_type_array[] = '<label><input type="checkbox" id="' . esc_attr($sub_field_name) . '" name="' . esc_attr($sub_field_name) . '" value="1" ' . checked($selected_option, true, false) . ' /> ' . $sub_field_option['label'] . '</label> ' . $sub_field_description . '<br />';
                            }
                            $input_type = implode('', $input_type_array);
                            break;

                        case 'select':
                            $select_options = '';
                            foreach ($field_options as $option_key => $option_label) {
                                $selected = (isset($option_actual[$field_id]) && $option_actual[$field_id] == $option_key) ? 'selected="selected"' : '';
                                $select_options .= '<option value="' . esc_attr($option_key) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                            }
                            $input_type = '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '">' . $select_options . '</select>';
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

                        case 'multiselect':
                            $desc_html_tag = 'div';
                            $input_type_array = array();
                            foreach ($field_options as $option_key => $option_label) {
                                $selected_option = (is_array($option_actual[$field_id]) && in_array($option_key, $option_actual[$field_id])) ? true : false;
                                $input_type_array[] = '<label><input type="checkbox" id="' . esc_attr($field_id . '-' . $option_key) . '" name="' . esc_attr($field_id) . '[]" value="' . esc_attr($option_key) . '" ' . checked($selected_option, true, false) . ' /> ' . esc_html($option_label) . '</label><br />';
                            }
                            $input_type = implode('', $input_type_array);
                            break;

                        case 'number':
                            $min_value = isset($option[6]) ? $option[6] : 0;
                            $min_attr = $min_value > 0 ? ' min="' . esc_attr($min_value) . '"' : '';

                            $field_value = isset($option_actual[$field_id]) ? $option_actual[$field_id] : '';

                            $input_type = '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($field_value) . '"' . $min_attr . ' />';
                            break;

                        case 'textarea':
                            $rows_attr = isset($option[7]['rows']) ? ' rows="' . esc_attr($option[7]['rows']) . '"' : ' rows="4"';
                            $placeholder_attr = isset($option[7]['placeholder']) ? ' placeholder="' . esc_attr($option[7]['placeholder']) . '"' : '';
                            $width_attr = (!empty($option[7]['width'])) ? ' style="width:' . esc_attr($option[7]['width']) . ';"' : ' style="width:100%; max-width:600px;"';

                            $field_value = isset($option_actual[$field_id]) ? $option_actual[$field_id] : '';

                            $input_type = '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '"' . $rows_attr . $placeholder_attr . $width_attr . '>' . esc_textarea($field_value) . '</textarea>';
                            break;

                        default:
                            $input_type = '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($option_actual[$field_id]) . '" />';
                            break;
                    }

                    $clean_label = wp_strip_all_tags($field_label);
                    if (empty($clean_label)) {
                        $clean_label = $field_label;
                    }

                    $extra_suffix = '';
                    if (!empty($field_description)) {
                        if ($field_type == 'sub_multiple_checkbox') {
                            $extra_prefix = '<' . $desc_html_tag . ' class="stpexplan">' . $field_description . '</' . $desc_html_tag . '>';
                        } else {
                            $extra_suffix = '<' . $desc_html_tag . ' class="stpexplan">' . $field_description . '</' . $desc_html_tag . '>';
                        }
                    }

                    $output .= '<table class="form-table">';
                    $output .= '<tr style="vertical-align: top;" class="' . esc_attr($field_class) . '"><th scope="row"><label for="' . esc_attr($field_id) . '">' . $clean_label . '</label></th><td>' . (isset($extra_prefix) ? $extra_prefix : '') . $input_type . $extra_suffix . '</td></tr>';
                    $output .= '</table>';
                }

                $output .= '</div>';
                $pt_index++;
            }

            return $output;
        }

        /**
         * Render metabox fields
         */
        private function render_metabox_fields($fields)
        {
            // Get current options
            $option_actual = SimpleTags_Plugin::get_option();

            $output = '';
            $role_index = 0;

            foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
                $active_class = ($role_index === 0) ? 'active' : '';
                $display_style = ($role_index === 0) ? '' : 'style="display:none;"';

                $output .= '<div id="metabox-' . esc_attr($role_name) . '-content" class="role-content ' . esc_attr($active_class) . '" ' . $display_style . '>';

                foreach ($fields as $option) {
                    if (empty($option) || !is_array($option)) {
                        continue;
                    }

                    $field_id = $option[0];
                    $field_label = $option[1];
                    $field_type = $option[2];
                    $field_options = isset($option[3]) ? $option[3] : '';
                    $field_description = isset($option[4]) ? $option[4] : '';
                    $field_class = isset($option[5]) ? $option[5] : '';

                    if (strpos($field_class, 'metabox-' . $role_name . '-content') === false) {
                        continue;
                    }

                    if (in_array($field_type, ['header', 'helper'])) {
                        continue;
                    }

                    $input_type = '';
                    $desc_html_tag = 'span';

                    // Generate input based on field type
                    switch ($field_type) {
                        case 'checkbox':
                            $checked = !empty($option_actual[$field_id]) ? 'checked="checked"' : '';
                            $input_type = '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="1" ' . $checked . ' />';
                            break;

                        case 'multiselect':
                            $desc_html_tag = 'div';
                            $input_type_array = array();
                            foreach ($field_options as $option_key => $option_label) {
                                $field_value = isset($option_actual[$field_id]) ? $option_actual[$field_id] : array();
                                $selected_option = (is_array($field_value) && in_array($option_key, $field_value)) ? true : false;
                                $input_type_array[] = '<label><input type="checkbox" id="' . esc_attr($field_id . '-' . $option_key) . '" name="' . esc_attr($field_id) . '[]" value="' . esc_attr($option_key) . '" ' . checked($selected_option, true, false) . ' /> ' . esc_html($option_label) . '</label><br />';
                            }
                            $input_type = implode('', $input_type_array);
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

                        default:
                            $input_type = '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" value="' . esc_attr($option_actual[$field_id]) . '" />';
                            break;
                    }

                    $clean_label = wp_strip_all_tags($field_label);
                    if (empty($clean_label)) {
                        $clean_label = $field_label;
                    }

                    $extra_suffix = '';
                    if (!empty($field_description)) {
                        $extra_suffix = '<' . $desc_html_tag . ' class="stpexplan">' . $field_description . '</' . $desc_html_tag . '>';
                    }

                    $output .= '<table class="form-table">';
                    $output .= '<tr style="vertical-align: top;"><th scope="row"><label for="' . esc_attr($field_id) . '">' . $clean_label . '</label></th><td>' . $input_type . $extra_suffix . '</td></tr>';
                    $output .= '</table>';
                }

                $output .= '</div>';
                $role_index++;
            }

            return $output;
        }

        /**
         * Render the Metabox Settings tab
         */
        private function render_metabox_settings_tab()
        {
            $option_data = (array) include(STAGS_DIR . '/inc/helper.options.admin.php');
            $taxopress_ai_fields = $option_data['taxopress-ai'];

            ?>
            <div class="taxopress-tab-content">
                <div class="taxopress-ai-post-type-tabs">
                    <ul class="taxopress-ai-post-type-tab-nav">
                        <?php
                        $pt_index = 0;
                        $post_type_links = [];
                        $first_post_type = '';
                        foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
                            if (in_array($post_type, ['attachment'])) {
                                continue;
                            }
                            if ($pt_index === 0) {
                                $first_post_type = $post_type;
                            }
                            $active_class = ($pt_index === 0) ? 'active' : '';
                            $post_type_links[] = '<a href="#taxopress-ai-' . esc_attr($post_type) . '-content" data-content="taxopress-ai-' . esc_attr($post_type) . '-content" class="' . esc_attr($active_class) . '">' . esc_html($post_type_object->labels->name) . '</a>';
                            $pt_index++;
                        }
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $post_type_links array contains already escaped HTML
                        echo join(' | ', $post_type_links);
                        echo '<input type="hidden" name="taxopress_ai_integration[active_post_type]" class="taxopress-active-posttype" value="' . esc_attr($first_post_type) . '" />';
                        ?>
                    </ul>
                    
                    <div class="taxopress-ai-post-type-tab-content">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content already escaped by render_taxopress_ai_fields()
                        echo $this->render_taxopress_ai_fields($taxopress_ai_fields);
                        ?>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="updateoptions" id="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'simple-tags'); ?>">
                </p>
            </div>
            <?php
        }

        /**
         * Render the Metabox Access tab
         */
        private function render_metabox_access_tab()
        {
            $option_data = (array) include(STAGS_DIR . '/inc/helper.options.admin.php');
            $metabox_fields = $option_data['metabox'];

            ?>
            <div class="taxopress-tab-content">
                <div class="metabox-role-tabs">
                    <ul class="metabox-role-tab-nav">
                        <?php
                        $role_index = 0;
                        $role_links = [];
                        $first_role = '';
                        foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
                            if ($role_index === 0) {
                                $first_role = $role_name;
                            }
                            $active_class = ($role_index === 0) ? 'active' : '';
                            $role_links[] = '<a href="#metabox-' . esc_attr($role_name) . '-content" data-content="metabox-' . esc_attr($role_name) . '-content" class="' . esc_attr($active_class) . '">' . esc_html(translate_user_role($role_info['name'])) . '</a>';
                            $role_index++;
                        }
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $role_links array contains already escaped HTML
                        echo join(' | ', $role_links);
                        echo '<input type="hidden" name="taxopress_ai_integration[active_role]" class="taxopress-active-role" value="' . esc_attr($first_role) . '" />';
                        ?>
                    </ul>
                    
                    <div class="metabox-role-tab-content">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content already escaped by render_metabox_fields()
                        echo $this->render_metabox_fields($metabox_fields);
                        ?>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="updateoptions" id="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'simple-tags'); ?>">
                </p>
            </div>
            <?php
        }

        /**
         * Render the Preview tab content
        */
        private function render_preview_tab($post)
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview tab display, no state modification
            $url_post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview tab display, no state modification
            $url_post_id = isset($_GET['post']) ? (int)$_GET['post'] : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview tab display, no state modificationET['post_type']) : '';
            $url_post_id = isset($_GET['post']) ? (int)$_GET['post'] : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $url_role = isset($_GET['role']) ? sanitize_key($_GET['role']) : '';

            // Get default post type and post
            $default_post_type = '';
            foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
                if (!in_array($post_type, ['attachment'])) {
                    // Use URL post type if valid, otherwise use first available
                    if ($url_post_type && $url_post_type === $post_type) {
                        $default_post_type = $url_post_type;
                        if ($url_post_id) {
                            $post = get_post($url_post_id);
                        }
                        break;
                    } elseif (!$default_post_type) {
                        $default_post_type = $post_type;
                        if (!$url_post_id) {
                            $posts = get_posts(['post_type' => $post_type, 'numberposts' => 1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
                            if (!empty($posts)) {
                                $post = $posts[0];
                            }
                        }
                    }
                }
            }

            ?>
            <div class="taxopress-tab-content">
                <div class="taxopress-preview-filters" data-ai-source="preview">
                    <select class="preview-post-types-select taxopress-ai-select2" style="max-width: 100px;">
                        <?php foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) :
                            if (!in_array($post_type, ['attachment'])) {
                                ?>
                                <option value='<?php echo esc_attr($post_type); ?>'
                                    data-singular_label="<?php echo esc_attr($post_type_object->labels->singular_name); ?>"
                                    <?php selected($post_type, $default_post_type); ?>>
                                    <?php echo esc_attr($post_type_object->labels->name); ?>
                                </option>
                                <?php
                            }
                        endforeach; ?>
                    </select>

                    <select class="preview-user-role-select taxopress-ai-select2" style="max-width: 150px;">
                        <?php foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) : ?>
                            <option value="<?php echo esc_attr($role_name); ?>"
                                <?php selected($role_name, $url_role); ?>>
                                <?php echo esc_html(translate_user_role($role_info['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="preview-post-select taxopress-ai-post-search"
                        style="max-width: 250px; width: 250px;"
                        data-placeholder="<?php echo esc_attr__('Select...', 'simple-tags'); ?>"
                        data-allow-clear="true"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('taxopress-ai-post-search')); ?>">
                        <?php if (is_object($post)) : ?>
                            <option value='<?php echo esc_attr($post->ID); ?>'>
                                <?php echo esc_html($post->post_title); ?>
                            </option>
                        <?php endif; ?>
                    </select>

                    <button type="button" class="button button-secondary preview-metabox-content">
                        <?php esc_html_e('Save Changes', 'simple-tags'); ?>
                    </button>
                </div>

                <div id="poststuff">
                    <div id="post-body" class="taxopress-section metabox-holder columns-2">
                        <div>
                            <div id="taxopress-preview-metabox-content" class="right-body-content taxopress-preview-metabox-content" style="position: relative;">
                                <?php $this->editor_metabox($post, $context = 'fast_update'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Helper method to render settings fields
         */
        private function render_settings_field($args)
        {
            $defaults = [
                'type' => 'text',
                'value' => '',
                'description' => '',
                'key' => '',
                'label' => ''
            ];
            $args = array_merge($defaults, $args);

            ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($args['key']); ?>">
                        <?php echo esc_html($args['label']); ?>
                    </label>
                </th>
                <td>
                    <?php if ($args['type'] === 'checkbox') : ?>
                        <input name="taxopress_ai_integration[<?php echo esc_attr($args['key']); ?>]" 
                            id="<?php echo esc_attr($args['key']); ?>"
                            type="checkbox" 
                            value="1" 
                            <?php checked(1, (int) $args['value']); ?> />
                    <?php else : ?>
                        <input name="taxopress_ai_integration[<?php echo esc_attr($args['key']); ?>]" 
                            id="<?php echo esc_attr($args['key']); ?>"
                            type="<?php echo esc_attr($args['type']); ?>" 
                            value="<?php echo esc_attr($args['value']); ?>" />
                    <?php endif; ?>
                    
                    <?php if (!empty($args['description'])) : ?>
                        <p class="description">
                            <?php echo esc_html($args['description']); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        /**
         * Get a rendered field partial
         *
         * @param array $args Arguments to render in the partial.
         */
        private static function get_rendered_field_partial($args)
        {
            $defaults = [
                'description' => '',
                'type' => 'text',
                'tab' => 'general',
                'value' => '',
                'key' => '',
                'other_attr' => '',
                'label' => '',
                'required' => false,
                'multiple' => false,
                'classes' => '',
                'options' => [],
            ];
            $args = array_merge($defaults, $args);

            $key = $args['key'];
            ?>
                    <tr valign="top" class="<?php echo esc_attr('form-field settings-' . $key . '-wrap'); ?>">
                        <th scope="row">
                            <?php if (!empty($args['label'])) : ?>
                                <label for="<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($args['label']); ?>
                                </label>
                                <?php if ($args['required']) { ?>
                                    <span class="required">*</span>
                                <?php } ?>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php
                            if ($args['type'] === 'checkbox') :
                                ?>
                                <input name="taxopress_ai_integration[<?php echo esc_attr($key); ?>]" id="<?php echo esc_attr($key); ?>"
                                    type="<?php echo esc_attr($args['type']); ?>" value="1" <?php checked(1, (int) $args['value']); ?>
                                    <?php echo($args['required'] ? 'required="true"' : ''); ?> />
                                <?php if (!empty($args['description'])) : ?>
                                                    <span class="description">
                                        <?php echo esc_html($args['description']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php elseif ($args['type'] === 'select') : ?>
                                <select 
                                    name="taxopress_ai_integration[<?php echo esc_attr($key); ?>]<?php echo $args['multiple'] ? '[]' : '';?>"
                                    id="<?php echo esc_attr($key); ?>"
                                    class="<?php echo esc_attr($args['classes']); ?>"
                                    style="width: 95%;"
                                    data-placeholder="<?php printf(esc_html__('Select %s', 'simple-tags'), esc_html(strtolower($args['label']))); ?>"
                                    <?php echo $args['other_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                                    <?php echo($args['multiple'] ? 'multiple' : '');?>
                                    <?php echo($args['required'] ? 'required="true"' : '');?>>
                                    <?php
                                    foreach ($args['options'] as $select_key => $select_label) {
                                        if ($args['multiple']) {
                                            $selected_option = (isset($args['value']) && is_array($args['value']) && in_array($select_key, $args['value'])) ? true : false;
                                        } else {
                                            $selected_option = (isset($args['value']) && $select_key == $args['value']) ? true : false;
                                        }
                                        ?>
                                        <option value="<?php esc_attr_e($select_key); ?>"
                                                <?php selected(true, $selected_option); ?>>
                                                <?php echo esc_html($select_label); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <?php if (isset($args['description'])) : ?>
                                    <p class="description">
                                        <?php echo esc_html($args['description']); ?>
                                    </p>
                                <?php endif; ?>
                            <?php elseif ($args['type'] === 'textarea') : ?>
                                <?php
                                $required_attr = ($args['required'] ? 'required="true"' : '');
                                ?>
                                <textarea style="min-height: 150px;" name="taxopress_ai_integration[<?php echo esc_attr($key); ?>]" id="<?php echo esc_attr($key); ?>" <?php echo $required_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>                 <?php echo $args['other_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>><?php echo sanitize_textarea_field(stripslashes_deep($args['value'])); ?></textarea>
                                <?php if (isset($args['description'])) : ?>
                                    <p class="description">
                                        <?php echo $args['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                                    </p>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php
                                $required_attr = ($args['required'] ? 'required="true"' : '');
                                ?>
                                <input name="taxopress_ai_integration[<?php echo esc_attr($key); ?>]" id="<?php echo esc_attr($key); ?>"
                                    type="<?php echo esc_attr($args['type']); ?>" value="<?php echo esc_attr($args['value']); ?>" <?php echo $required_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>                 <?php echo $args['other_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?> />
                                <?php if (isset($args['description'])) : ?>
                                    <p class="description">
                                        <?php echo $args['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
        }

        /**
         * Ajax action, JS Helper and admin action
         *
         */
        public function ajax_check()
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- AJAX endpoint validation, no state modification
            if (isset($_GET['stags_action']) && $_GET['stags_action'] == 'tags_from_local_db') {
                self::ajax_suggest_local();
            } else {
                status_header(200);
                header("Content-Type: text/html; charset=" . get_bloginfo('charset'));
                echo '<p>' . esc_html__('Invalid request.', 'simple-tags') . '</p>';
                exit();
            }
        }

        /**
         * Suggest tags from local database
         *
         */
        public static function ajax_suggest_local()
        {
            if (!check_ajax_referer('st-admin-js', 'nonce', false)) {
                wp_die(esc_html__('Security check failed.', 'simple-tags'), '', ['response' => 403]);
            }

            $taxonomy = 'post_tag';
            if (!empty($_GET['taxonomy'])) {
                $taxonomy = sanitize_key(wp_unslash($_GET['taxonomy']));
            } elseif (isset($_GET['suggestterms'])) {
                $suggestterms = taxopress_get_suggestterm_data();
                $selected_suggestterm = absint($_GET['suggestterms']);

                if (array_key_exists($selected_suggestterm, $suggestterms)) {
                    $taxonomy = sanitize_key($suggestterms[$selected_suggestterm]['taxonomy']);
                }
            }

            $taxonomy_object = get_taxonomy($taxonomy);
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            if (
                !$taxonomy_object
                || empty($taxonomy_object->cap->assign_terms)
                || !current_user_can($taxonomy_object->cap->assign_terms)
                || ($post_id > 0 && !current_user_can('edit_post', $post_id))
            ) {
                wp_die(esc_html__('Permission denied.', 'simple-tags'), '', ['response' => 403]);
            }

            status_header(200);
            header("Content-Type: text/html; charset=" . get_bloginfo('charset'));

            if (((int) wp_count_terms(array( 'taxonomy' => $taxonomy, 'hide_empty' => false ))) == 0) { // No tags to suggest
                echo '<p>' . esc_html__('No terms in your WordPress database.', 'simple-tags') . '</p>';
                exit();
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Array indices checked, values sanitized for text comparison only
            $content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? '')) . ' ' . sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
            $content = trim($content);

            if (empty($content)) {
                echo '<p>' . esc_html__('There\'s no content to scan.', 'simple-tags') . '</p>';
                exit();
            }

            // Get all terms
            $terms = SimpleTags_Admin::getTermsForAjax($taxonomy, '');
            if (empty($terms) || $terms == false) {
                echo '<p>' . esc_html__('No results from your WordPress database.', 'simple-tags') . '</p>';
                exit();
            }

            // Get terms for current post
            $post_terms = array();
            if ($post_id > 0) {
                $post_terms = wp_get_post_terms($post_id, $taxonomy, array( 'fields' => 'ids' ));
            }

            $flag = false;
            $click_terms = [];
            foreach ((array) $terms as $term) {
                $class_current = in_array($term->term_id, $post_terms) ? 'used_term' : '';
                $term_id = $term->term_id;
                $term = stripslashes($term->name);

                $add_terms = [];
                $add_terms[$term] = $term_id;
                $primary_term = $term;

                // add term synonyms
                $term_synonyms = taxopress_get_term_synonyms($term_id);
                if (!empty($term_synonyms)) {
                    foreach ($term_synonyms as $term_synonym) {
                        $add_terms[$term_synonym] = $term_id;
                    }
                }

                // add linked term
                $add_terms = taxopress_add_linked_term_options($add_terms, $term_id, $taxonomy);

                foreach ($add_terms as $add_name => $add_term_id) {
                    if (is_string($add_name) && ! empty($add_name) && stristr($content, $add_name) && !in_array($primary_term, $click_terms)) {
                        $click_terms[] = $primary_term;
                        $flag = true;
                        echo '<span data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" class="local ' . esc_attr($class_current) . '" tabindex="0" role="button" aria-pressed="false">' . esc_html($primary_term) . '</span>' . "\n";
                    }
                }
            }

            if ($flag == false) {
                echo '<p>' . esc_html__('There are no terms that are relevant to your content.', 'simple-tags') . '</p>';
            } else {
                echo '<div class="clear"></div>';
            }

            exit();
        }

        public function load_result($args)
        {

            if (empty($args)) {
                return;
            }

            $action       = $args['action'];
            $taxonomy     = $args['taxonomy'];
            $ai_group     = $args['ai_group'];
            $post_title   = $args['post_title'];
            $post_content = $args['post_content'];
            $post_id      = $args['post_id'];
            $post         = get_post($post_id);

            if (in_array($ai_group, ['existing_terms', '__suggest_local_terms', 'post_terms'])) {
                $content = $post_content . ' ' . $post_title;
                $settings_data = TaxoPressAiUtilities::taxopress_get_ai_settings_data($post->post_type);
                $result_args = [
                    'settings_data' => $settings_data,
                    'screen_source' => 'st_taxopress_ai',
                    'content' => $content,
                    'post_id' => $post_id,
                    'preview_taxonomy' => $taxonomy,
                ];

                if ($ai_group == 'suggest_local_terms') {
                    $result_args['suggest_terms'] = true;
                    $result_args['show_counts'] = isset($settings_data['suggest_local_terms_show_post_count']) ? $settings_data['suggest_local_terms_show_post_count'] : 0;
                    $term_results = TaxoPressAiAjax::get_existing_terms_results($result_args);
                } elseif ($ai_group == 'existing_terms') {
                    $result_args['show_counts'] = isset($settings_data['existing_terms_show_post_count']) ? $settings_data['existing_terms_show_post_count'] : 0;
                    $term_results = TaxoPressAiAjax::get_existing_terms_results($result_args);
                } elseif ($ai_group == 'post_terms') {
                    $result_args['show_counts'] = isset($settings_data['post_terms_show_post_count']) ? $settings_data['post_terms_show_post_count'] : 0;
                    $post_terms_results = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
                    if (!empty($post_terms_results)) {
                        $taxonomy_list_page = admin_url('edit-tags.php');
                        $taxonomy_list_page = add_query_arg(array(
                            'taxonomy' => $taxonomy
                        ), $taxonomy_list_page);

                        $legend_title  = '<a href="' . esc_url($taxonomy_list_page) . '" target="blank">' . esc_html__('Tags', 'simple-tags') . '</a>';
                        $formatted_result = TaxoPressAiUtilities::format_taxonomy_term_results($post_terms_results, $taxonomy, $post_id, $legend_title, $result_args['show_counts'], [], $result_args);

                        $term_results['results'] = $formatted_result;
                        $term_results['status'] = 'success';
                        $term_results['message'] = '';
                    } else {
                        $term_results['status'] = 'error';
                        $term_results['message'] =  '';
                    }
                }
                    
                if (!empty($term_results['results'])) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Results already escaped by format_taxonomy_term_results()
                    echo $term_results['results'];
                }
            }
        }

        /**
         * Register metabox for suggest tags, for post, and optionnaly cpt.
         *
         * @return void
         * @author WebFactory Ltd
         */
        public function admin_head()
        {
            global $pagenow;

            if (in_array($pagenow, ['post-new.php', 'post.php', 'page.php', 'page-new.php', 'edit.php']) && can_manage_taxopress_metabox() && !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . get_post_type() . '_metabox'))) {
                add_meta_box(
                    'taxopress-ai-suggestedtags',
                    esc_html__('PublishPress Taxonomies', 'simple-tags'),
                    [$this, 'editor_metabox'],
                    get_post_type(),
                    'normal',
                    'core'
                );
            }
        }

        /**
         * Print HTML for suggest tags box
         *
         **/
        public function editor_metabox($post, $context = 'post.php')
        {
            $fast_update_screen = $context == 'fast_update';

            $access_metabox = can_manage_taxopress_metabox();
            if (!$access_metabox) {
                return;
            }

            if (!$fast_update_screen && (!is_object($post) || !isset($post->post_type))) {
                return;
            }

            $default_post_type = is_object($post) && isset($post->post_type) ? $post->post_type : '';

            if ($fast_update_screen && empty($default_post_type)) {
                foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
                    if (!in_array($post_type, ['attachment'])) {
                        if (empty($default_post_type)) {
                            $default_post_type = $post_type;
                            $posts = $posts = get_posts(['post_type' => $post_type, 'numberposts' => 1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
                            if (!empty($posts)) {
                                $post = $posts[0];
                            }
                        }
                    }
                }
            }

            if (!is_object($post) || !isset($post->post_type)) {
                return;
            }

            $preview_role = '';
            if ($fast_update_screen) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preview mode only; the AJAX handler verifies its nonce.
                $preview_role = isset($_POST['preview_role']) ? sanitize_key($_POST['preview_role']) : '';
            }

            $settings_data = TaxoPressAiUtilities::taxopress_get_ai_settings_data($post->post_type);
            $fields_tabs   = TaxoPressAiFields::get_fields_tabs();

            $metabox_filters_enabled = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post->post_type . '_metabox_filters');

            $existing_terms_label = get_option('taxopress_ai_existing_terms_tab_label');
            if ($existing_terms_label === '' || $existing_terms_label === false) {
                $existing_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_existing_terms_tab_label');
            }
            if (empty($existing_terms_label)) {
                $existing_terms_label = esc_html__('Show All Existing Terms', 'simple-tags');
            }

            $post_terms_label = get_option('taxopress_ai_post_terms_tab_label');
            if ($post_terms_label === '' || $post_terms_label === false) {
                $post_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_post_terms_tab_label');
            }
            if (empty($post_terms_label)) {
                $post_terms_label = esc_html__('Manage Post Terms', 'simple-tags');
            }

            $suggest_local_terms_label = get_option('taxopress_ai_suggest_local_terms_tab_label');
            if ($suggest_local_terms_label === '' || $suggest_local_terms_label === false) {
                $suggest_local_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_suggest_local_terms_tab_label');
            }

            $create_terms_label = get_option('taxopress_ai_create_terms_tab_label');
            if ($create_terms_label === '' || $create_terms_label === false) {
                $create_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_create_terms_tab_label');
            }
            if (empty($create_terms_label)) {
                $create_terms_label = esc_html__('Create Terms', 'simple-tags');
            }

            $existing_terms_maximum_terms = !empty($settings_data['existing_terms_maximum_terms']) ? $settings_data['existing_terms_maximum_terms'] : '';
            $existing_terms_orderby = !empty($settings_data['existing_terms_orderby']) ? $settings_data['existing_terms_orderby'] : '';
            $existing_terms_order = !empty($settings_data['existing_terms_order']) ? $settings_data['existing_terms_order'] : '';

            $wrapper_class = $fast_update_screen ? 'fast_update_screen' : 'editor-screen';
            $can_edit_labels = $fast_update_screen && $preview_role
                ? $preview_role === 'administrator'
                : can_edit_taxopress_metabox_labels();

            ?>
            <div class="taxopress-post-suggestterm <?php echo esc_attr($wrapper_class); ?>">
                <div class="taxopress-suggest-terms-contents">
                <?php
                    $all_content_tabs = [
                        'existing_terms' => [
                            'label'   => $existing_terms_label,
                            'enabled' => !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . $post->post_type . '_existing_terms_tab')),
                        ],
                        'post_terms' => [
                            'label'   => $post_terms_label,
                            'enabled' => !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . $post->post_type . '_post_terms_tab')),
                        ],
                        'suggest_local_terms' => [
                            'label'   => $suggest_local_terms_label,
                            'enabled' => !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . $post->post_type . '_suggest_local_terms_tab')),
                        ],
                        'create_terms' => [
                            'label'   => $create_terms_label,
                            'enabled' => !empty(SimpleTags_Plugin::get_option_value('enable_taxopress_ai_' . $post->post_type . '_create_terms_tab')),
                        ],
                    ];

                    $all_content_tabs = can_manage_taxopress_metabox_tabs($all_content_tabs);
                    if (
                        $fast_update_screen
                        && $preview_role
                        && SimpleTags_Plugin::get_option_value('enable_restrict' . $preview_role . '_metabox')
                    ) {
                        $all_content_tabs['create_terms']['enabled'] = false;
                    }

                    foreach ($all_content_tabs as $all_content_tab_name => $all_content_tab_options) {
                        if ($all_content_tab_options['enabled']) {
                            $content_tabs[$all_content_tab_name] = $all_content_tab_options['label'];
                        }
                    }


                    $support_private_taxonomy = $fast_update_screen ? false : SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post->post_type . '_support_private_taxonomy');

                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview tab, data used only for display
                    $post_type_taxonomies = [];
                    $permitted_post_type_taxonomies = [];
                    if ($fast_update_screen) {
                        $preview_has_metabox_access = !$preview_role
                            || !empty(SimpleTags_Plugin::get_option_value('enable_' . $preview_role . '_metabox'));

                        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy_name => $taxonomy_data) {
                            if (!in_array($taxonomy_name, ['post_format'], true)) {
                                if ($preview_role) {
                                    if (
                                        $preview_has_metabox_access
                                        && can_manage_taxopress_metabox_taxonomy($taxonomy_name, false, $preview_role)
                                    ) {
                                        $post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                        $permitted_post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                    }
                                } elseif (can_manage_taxopress_metabox_taxonomy($taxonomy_name)) {
                                    $post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                    if (in_array($taxonomy_name, ['category', 'post_tag'], true) && current_user_can('manage_categories')) {
                                        $permitted_post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                    } elseif (!empty($taxonomy_data->cap->edit_terms) && current_user_can($taxonomy_data->cap->edit_terms)) {
                                        $permitted_post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                    }
                                }
                            }
                        }
                    } else {
                        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy_name => $taxonomy_data) {
                            if (can_manage_taxopress_metabox_taxonomy($taxonomy_name)) {
                                $post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                if (in_array($taxonomy_name, ['category', 'post_tag']) && current_user_can('manage_categories')) {
                                    $permitted_post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                } elseif (!empty($taxonomy_data->cap->edit_terms) && current_user_can($taxonomy_data->cap->edit_terms)) {
                                    $permitted_post_type_taxonomies[$taxonomy_name] = $taxonomy_data;
                                }
                            }
                        }
                    }

                    $post_type_taxonomy_names = array_keys($post_type_taxonomies);
                    $post_type_default_taxonomy = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post->post_type . '_metabox_default_taxonomy');
                    if (empty($post_type_default_taxonomy)) {
                        $post_type_default_taxonomy = 'post_tag';
                    }

                    if (empty($post_type_taxonomy_names)) {
                        echo '<div style="padding: 15px;">';
                        $metabox_access_link = esc_url(admin_url('admin.php?page=' . self::PAGE_MENU_SLUG . '&tab=metabox_access'));
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- printf with esc_html__ and URLs already escaped
                        printf(esc_html__('This user does not have access to manage any of this post attached taxonomies. Enable Metabox Access Taxonomies for this role in %1sPublishPress Taxonomies Settings%2s.', 'simple-tags'), '<a target="_blank" href="' . $metabox_access_link . '">', '</a>');
                        echo '</div>';
                    } elseif (empty($content_tabs)) {
                        echo '<div style="padding: 15px;">';
                        esc_html_e('No PublishPress Taxonomies Metabox features are enabled for this post type.', 'simple-tags');
                        echo '</div>';
                    } else {
                        $default_taxonomy = (in_array($post_type_default_taxonomy, $post_type_taxonomy_names) ? $post_type_default_taxonomy : $post_type_taxonomy_names[0]);
                        ?>
                    <div class="taxopress-suggest-terms-content">
                        <ul class="taxopress-tab ai-integration-tab">
                            <?php
                                $tab_index = 0;
                            foreach ($content_tabs as $key => $label) {
                                $selected_class = ($tab_index === 0) ? ' active' : '';
                                ?>
                                <li class="<?php echo esc_attr($key); ?>_tab<?php echo esc_attr($selected_class); ?>"
                                    data-content="<?php echo esc_attr($key); ?>"
                                    aria-current="<?php echo $tab_index === 0 ? 'true' : 'false'; ?>">
                                    <a href="#<?php echo esc_attr($key); ?>" class="<?php echo esc_attr($key); ?>_icon">
                                        <span class="tp-tab-label">
                                            <?php echo esc_html($label); ?>
                                        </span>
                                <?php if ($can_edit_labels && $fast_update_screen) { ?>
                                    <div class="pp-tooltips-library" data-toggle="tooltip">
                                        <span class="dashicons dashicons-edit tp-rename-tab" data-tab="<?php echo esc_attr($key); ?>"></span>
                                        <div class="taxopress tooltip-text taxopress-ai"><?php echo esc_html__('Edit', 'simple-tags'); ?></div>
                                    </div>
                                    <span class="tp-rename-inline-controls" style="display:none;">
                                        <input type="text" class="tp-rename-tab-input" value="<?php echo esc_attr($label); ?>">
                                        <div class="pp-tooltips-library" data-toggle="tooltip">
                                            <span class="dashicons dashicons-yes tp-rename-tab-save"></span>
                                            <div class="taxopress tooltip-text"><?php echo esc_html__('Save, You can also press Enter to save and escape to cancel', 'simple-tags'); ?></div>
                                        </div>
                                    </span>
                                    <span class="tp-rename-tab-error" role="alert" aria-live="polite" style="display:none;"></span>
                                <?php } ?>
                                </a>
                                </li>
                                <?php
                                $tab_index++;
                            }
                            ?>
                        </ul>
                        <div class="st-taxonomy-content taxopress-tab-content multiple">
                            <?php
                            $auto_term_options = TaxoPressAiUtilities::get_auto_term_options();
                            $auto_term_class = count($auto_term_options) > 1 ? 'multiple-option' : 'single-option';
                            $content_index = 0;
                            foreach ($content_tabs as $key => $label) {
                                $result_request_args = [
                                'action'    => 'pageload',
                                'taxonomy'  => $default_taxonomy,
                                'ai_group'  => $key,
                                'post_title'    => $post->post_title,
                                'post_content'  => $post->post_content,
                                'post_id'       => $post->ID,
                                ];
                                $button_label = !empty($fields_tabs[$key]['button_label']) ? $fields_tabs[$key]['button_label'] : esc_html__('View Terms', 'simple-tags');

                                $hide_filters = ($key === 'existing_terms' && !$metabox_filters_enabled) ? 'display: none;' : '';
                                ?>
                                <table class="taxopress-tab-content-item form-table taxopress-table taxopress-ai-tab-content <?php echo esc_attr($key); ?>"
                                    data-ai-source="<?php echo esc_attr($key); ?>"
                                    data-post_id="<?php echo esc_attr($post->ID); ?>"
                                    style="<?php echo ($content_index === 0) ? '' : 'display:none;'; ?>">
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="taxopress-ai-fetch-wrap">
                                                    <input 
                                                        class="taxopress-taxonomy-search existing-term-item" 
                                                        type="search" 
                                                        value="" 
                                                        placeholder="<?php echo esc_attr__('Search Terms...', 'simple-tags'); ?>"
                                                        style="<?php echo esc_attr($hide_filters); ?>"
                                                        onkeydown="return event.key != 'Enter';" />


                                                        <select class="taxopress-autoterms-options <?php echo esc_attr($auto_term_class); ?>"
                                                        style="display: none;">
                                                                <?php foreach ($auto_term_options as $option_name => $option_label) : ?>
                                                                    <option value='<?php echo esc_attr($option_name); ?>'>
                                                                            <?php echo esc_html($option_label); ?>
                                                                        </option> 
                                                                    <?php
                                                                endforeach; ?>
                                                        </select>
                                                        
                                                    <input 
                                                        class="taxopress-taxonomy-term-input create-term-item" 
                                                        type="text" 
                                                        value="" 
                                                        placeholder="<?php echo esc_attr__('Create Term', 'simple-tags'); ?>"
                                                        style="display: none; "
                                                        onkeydown="return event.key != 'Enter';" />
                                                    <select class="taxopress-ai-fetch-create-taxonomy create-term-item" style="display: none;">
                                                                    <?php foreach ($permitted_post_type_taxonomies as $tax_key => $tax_object) :
                                                                        if (!in_array($tax_key, ['post_format']) && (!empty($tax_object->show_ui) || !empty($support_private_taxonomy))) {
                                                                            $rest_api_base = !empty($tax_object->rest_base) ? $tax_object->rest_base : $tax_key;
                                                                            $hierarchical = !empty($tax_object->hierarchical) ? (int) $tax_object->hierarchical : 0;
                                                                            ?>
                                                                    <option value='<?php echo esc_attr($tax_key); ?>'
                                                                    data-rest_base='<?php echo esc_attr($rest_api_base); ?>'
                                                                    data-hierarchical='<?php echo esc_attr($hierarchical); ?>'
                                                                            <?php selected($tax_key, $default_taxonomy); ?>>
                                                                            <?php echo esc_html($tax_object->labels->name . ' (' . $tax_object->name . ')'); ?>
                                                                    </option>
                                                                        <?php }
                                                                    endforeach; ?>
                                                    </select>
                                                    <button class="button button-secondary taxopress-ai-create-button create-term-item" style="display: none;">
                                                        <div class="spinner"></div>
                                                        <span class="btn-text"><?php echo esc_html__('Create Term', 'simple-tags'); ?></span>
                                                    </button>

                                                    <select class="taxopress-ai-fetch-taxonomy-select" style="<?php echo esc_attr($hide_filters); ?>">
                                                                    <?php foreach ($post_type_taxonomies as $tax_key => $tax_object) :
                                                                        if (!in_array($tax_key, ['post_format']) && (!empty($tax_object->show_ui) || !empty($support_private_taxonomy))) {
                                                                            $rest_api_base = !empty($tax_object->rest_base) ? $tax_object->rest_base : $tax_key;
                                                                            $hierarchical = !empty($tax_object->hierarchical) ? (int) $tax_object->hierarchical : 0;
                                                                            ?>
                                                                    <option value='<?php echo esc_attr($tax_key); ?>'
                                                                    data-rest_base='<?php echo esc_attr($rest_api_base); ?>'
                                                                    data-hierarchical='<?php echo esc_attr($hierarchical); ?>'
                                                                            <?php selected($tax_key, $default_taxonomy); ?>>
                                                                            <?php echo esc_html($tax_object->labels->name . ' (' . $tax_object->name . ')'); ?>
                                                                    </option>
                                                                        <?php }
                                                                    endforeach; ?>
                                                    </select>
                                                    <select id="existing_terms_orderby"
                                                        class="existing-term-item" style="<?php echo esc_attr($hide_filters); ?>">
                                                                <?php foreach (TaxoPressAiUtilities::get_existing_terms_orderby() as $key => $label) : ?>
                                                            <option value='<?php echo esc_attr($key); ?>'
                                                                    <?php selected($key, $existing_terms_orderby); ?>>
                                                                    <?php echo esc_html($label); ?>
                                                            </option>
                                                                <?php endforeach; ?>
                                                    </select>
                                                    <select id="existing_terms_order"
                                                        class="existing-term-item" style="<?php echo esc_attr($hide_filters); ?>">
                                                                <?php foreach (TaxoPressAiUtilities::get_existing_terms_order() as $key => $label) : ?>
                                                            <option value='<?php echo esc_attr($key); ?>'
                                                                    <?php selected($key, $existing_terms_order); ?>>
                                                                    <?php echo esc_html($label); ?>
                                                            </option>
                                                                <?php endforeach; ?>
                                                    </select>
                                                    <input 
                                                        id="existing_terms_maximum_terms"
                                                        step="1" min="0"
                                                        class="existing-term-item" 
                                                        type="number" 
                                                        value="<?php echo esc_attr($existing_terms_maximum_terms); ?>" 
                                                        placeholder="<?php echo esc_attr__('Count', 'simple-tags'); ?>"
                                                        style="<?php echo esc_attr($hide_filters); ?>"
                                                        min-width: unset;width: 80px;"
                                                        onkeydown="return event.key != 'Enter';" />
                                                    <button class="button button-secondary taxopress-ai-fetch-button" style="<?php echo esc_attr($hide_filters); ?>">
                                                        <div class="spinner"></div>
                                                        <span class="btn-text"><?php echo esc_html($button_label); ?></span>
                                                    </button>
                                                </div>
                                                <div class="taxopress-ai-fetch-result <?php echo esc_attr($key); ?>">
                                                        <?php
                                                        if (!$fast_update_screen) {
                                                            do_action('load_taxopress_ai_term_results', $result_request_args);
                                                        }
                                                        ?>
                                                </div>
                                                        <?php if (empty($permitted_post_type_taxonomies)) {
                                                            echo '<div class="auto-terms-error-red create-term-item" style="display: none;" style="padding: 15px;"><p>';
                                                            echo esc_html__('You do not have the required capabilities to manage any of this post attached taxonomies.', 'simple-tags');
                                                            echo '</p></div>';
                                                        }
                                                        ?>
                                                <div class="taxopress-ai-fetch-result-msg <?php echo esc_attr($key); ?>"></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <?php
                                $content_index++;
                            }
                            ?>
                        </div>
                        <div class="clear"></div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php
        }

        public function ensure_tab_label_options()
        {
            $keys = [
                'taxopress_ai_existing_terms_tab_label'       => esc_html__('Show All Existing Terms', 'simple-tags'),
                'taxopress_ai_post_terms_tab_label'           => esc_html__('Manage Post Terms', 'simple-tags'),
                'taxopress_ai_suggest_local_terms_tab_label'  => esc_html__('Auto Terms', 'simple-tags'),
                'taxopress_ai_create_terms_tab_label'         => esc_html__('Create Terms', 'simple-tags'),
            ];

            foreach ($keys as $opt => $default) {
                $current = get_option($opt, '');
                if ($current === '' || $current === false) {
                    // Pull from plugin option container if present, else default
                    $from_plugin = SimpleTags_Plugin::get_option_value($opt);
                    $val = $from_plugin !== '' ? $from_plugin : $default;
                    update_option($opt, $val);
                }
            }
        }

        public function taxopress_save_existing_terms_label()
        {
            $this->taxopress_save_tab_label('taxopress_ai_existing_terms_tab_label');
        }

        public function taxopress_save_post_terms_label()
        {
            $this->taxopress_save_tab_label('taxopress_ai_post_terms_tab_label');
        }

        public function taxopress_save_suggest_local_terms_label()
        {
            $this->taxopress_save_tab_label('taxopress_ai_suggest_local_terms_tab_label');
        }

        public function taxopress_save_create_terms_label()
        {
            $this->taxopress_save_tab_label('taxopress_ai_create_terms_tab_label');
        }

        private function taxopress_save_tab_label($option_key)
        {
            if (!current_user_can('simple_tags')) {
                wp_send_json_error(['message' => esc_html__('Permission denied.', 'simple-tags')], 403);
            }
            if (!can_edit_taxopress_metabox_labels()) {
                wp_send_json_error(['message' => esc_html__('You can not edit this label.', 'simple-tags')], 403);
            }
            if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'taxopress-ai-ajax-nonce')) {
                wp_send_json_error(['message' => esc_html__('Invalid nonce token.', 'simple-tags')], 400);
            }
            $new_label = isset($_POST['new_label']) ? sanitize_text_field(wp_unslash($_POST['new_label'])) : '';
            $new_label = trim($new_label);

            if ($new_label === '') {
                wp_send_json_error(['message' => esc_html__("Label can't be empty.", 'simple-tags')], 400);
            }
            if (mb_strlen($new_label) > 80) {
                wp_send_json_error(['message' => esc_html__('Label is too long.', 'simple-tags')], 400);
            }

            // Save both in plugin container and as dedicated option
            SimpleTags_Plugin::set_option_value($option_key, $new_label);
            update_option($option_key, $new_label);

            wp_send_json_success(['label' => $new_label]);
        }
    }
}
