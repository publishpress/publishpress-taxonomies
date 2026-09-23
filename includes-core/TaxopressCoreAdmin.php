<?php

namespace PublishPress\Taxopress;

class TaxopressCoreAdmin
{
    public function __construct()
    {

        if (current_user_can('simple_tags')) {
            if (is_admin()) {
                if (class_exists('PublishPress\WordpressVersionNotices\Module\TopNotice\Module')) {
                    add_filter(
                        \PublishPress\WordpressVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER,
                        function ($settings) {
                            $settings['publishpress-taxopress'] = [
                                'message' => esc_html__("You're using TaxoPress Free. The Pro version has more features and support. %sUpgrade to Pro%s", 'simple-tags'),
                                'link'    => 'https://taxopress.com/taxopress/',
                                'screens' => [
                                    ['base' => 'taxopress_page_st_dashboard', 'id'   => 'taxopress_page_st_dashboard'],
                                    ['base' => 'taxopress_page_st_taxonomies', 'id'   => 'taxopress_page_st_taxonomies'],
                                    ['base' => 'taxopress_page_st_mass_terms', 'id'   => 'taxopress_page_st_mass_terms'],
                                    ['base' => 'taxopress_page_st_manage',     'id'   => 'taxopress_page_st_manage'],
                                    ['base' => 'taxopress_page_st_auto',       'id'   => 'taxopress_page_st_auto'],
                                    ['base' => 'toplevel_page_st_options',     'id'   => 'toplevel_page_st_options'],
                                    ['base' => 'taxopress_page_st_terms_display', 'id' => 'taxopress_page_st_terms_display'],
                                    ['base' => 'toplevel_page_st_posts',     'id'   => 'toplevel_page_st_posts'],
                                    ['base' => 'taxopress_page_st_posts',       'id'  => 'taxopress_page_st_posts'],
                                    ['base' => 'taxopress_page_st_post_tags',   'id'  => 'taxopress_page_st_post_tags'],
                                    ['base' => 'taxopress_page_st_related_posts', 'id' => 'taxopress_page_st_related_posts'],
                                    ['base' => 'taxopress_page_st_autolinks',   'id'  => 'taxopress_page_st_autolinks'],
                                    ['base' => 'taxopress_page_st_autoterms',   'id'  => 'taxopress_page_st_autoterms'],
                                    ['base' => 'taxopress_page_st_autoterms_content',   'id'  => 'taxopress_page_st_autoterms_content'],
                                    ['base' => 'taxopress_page_st_suggestterms', 'id'  => 'taxopress_page_st_suggestterms'],
                                    ['base' => 'taxopress_page_st_terms',       'id'  => 'taxopress_page_st_terms'],
                                    ['base' => 'taxopress_page_st_taxopress_ai', 'id'  => 'taxopress_page_st_taxopress_ai'],
                                    ['base' => 'taxopress_page_st_autoterms_schedule', 'id'  => 'taxopress_page_st_autoterms_schedule']
                                ]
                            ];

                            return $settings;
                        }
                    );

                    add_filter(
                        \PublishPress\WordpressVersionNotices\Module\MenuLink\Module::SETTINGS_FILTER,
                        function ($settings) {
                            $settings['publishpress-taxopress'] = [
                                'parent' => 'st_options',
                                'label'  => __('Upgrade to Pro', 'simple-tags'),
                                'link'   => 'https://taxopress.com/taxopress/',
                            ];

                            return $settings;
                        }
                    );
                }
            }
        }


        add_action('taxopress_admin_class_before_assets_register', [$this, 'taxopress_load_admin_core_assets']);
        add_action('taxopress_admin_class_after_styles_enqueue', [$this, 'taxopress_load_admin_core_styles']);
        add_action('taxopress_admin_after_sidebar', [$this, 'taxopress_admin_advertising_sidebar_banner']);
        add_action('taxopress_autoterms_after_autoterm_terms_to_use', [$this, 'taxopress_core_autoterm_terms_to_use_field']);
        add_action('taxopress_suggestterm_after_api_fields', [$this, 'taxopress_core_suggestterm_after_api_fields']);
        add_action('taxopress_autoterms_after_autoterm_advanced', [$this, 'taxopress_core_autoterm_advanced_field']);
        add_action('taxopress_autolinks_after_html_exclusions_tr', [$this, 'taxopress_core_autolinks_after_html_exclusions_promo']);
        add_action('taxopress_settings_linked_terms_pro_notice', [$this, 'taxopress_core_linked_terms_content']);
        add_action('taxopress_settings_synonyms_terms_pro_notice', [$this, 'taxopress_core_synonyms_terms_content']);
        add_action('taxopress_ai_after_open_ai_fields', [$this, 'taxopress_core_ai_after_open_ai_fields']);
        add_action('taxopress_ai_after_ibm_watson_fields', [$this, 'taxopress_core_ai_after_ibm_watson_fields']);
        add_action('taxopress_ai_after_dandelion_fields', [$this, 'taxopress_core_ai_after_dandelion_fields']);
        add_action('taxopress_ai_after_open_calais_fields', [$this, 'taxopress_core_ai_after_open_calais_fields']);
        add_action('load_taxopress_ai_term_results', [$this, 'taxopress_core_ai_term_results_banner']);
        add_filter('taxopress_dashboard_features', [$this, 'taxopress_core_linked_terms_feature']);
        add_filter('taxopress_dashboard_features', [$this, 'taxopress_core_synonyms_terms_feature']);
        add_filter('taxopress_autolink_row_actions', [$this, 'taxopress_core_copy_action'], 10, 2);
        add_filter('taxopress_posttags_row_actions', [$this, 'taxopress_core_copy_action'], 10, 2);
        add_filter('taxopress_autoterm_row_actions', [$this, 'taxopress_core_copy_action'], 10, 2);
        add_filter('taxopress_relatedpost_row_actions', [$this, 'taxopress_core_copy_action'], 10, 2);
        add_filter('taxopress_tagclouds_row_actions', [$this, 'taxopress_core_copy_action'], 10, 2);
        add_filter('taxopress_terms_row_actions', [$this, 'taxopress_core_copy_with_metadata_action'], 10, 2);
        add_filter('taxopress_settings_post_type_ai_fields', [$this, 'filter_settings_post_type_ai_fields'], 10, 2);
        add_filter('taxopress_display_formats', [$this, 'taxopress_render_display_formats_field']);
        add_action('taxopress_terms_copy_with_metadata_promo', [$this, 'taxopress_terms_copy_with_metadata_promo']);
        add_action('taxopress_terms_order', [$this, 'taxopress_terms_order_free']);
        add_filter('taxopress_order_column', [$this, 'taxopress_order_column_free']);
        add_action('taxopress_tagcloud_ordering_method', [$this, 'taxopress_free_tagcloud_ordering_method']);
        add_action('taxopress_posttags_ordering_method', [$this, 'taxopress_free_posttags_ordering_method']);
        add_action('taxopress_schedule_frequency_fields', [$this, 'taxopress_free_schedule_frequency_fields']);
    }

    public function taxopress_load_admin_core_assets()
    {
        wp_register_style('st-admin-core', STAGS_URL . '/includes-core/assets/css/core.css', array(), STAGS_VERSION, 'all');
    }

    public function taxopress_load_admin_core_styles()
    {
        wp_enqueue_style('st-admin-core');
    }

    public function taxopress_admin_advertising_sidebar_banner()
    {
        ?>

        <div class="taxopress-advertisement-right-sidebar">
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="advertisement-box-content postbox">
                        <div class="postbox-header">
                            <h3 class="advertisement-box-header hndle is-non-sortable">
                                <span><?php echo esc_html__('Upgrade to TaxoPress Pro', 'simple-tags'); ?></span>
                            </h3>
                        </div>

                        <div class="inside">
                            <p><?php echo esc_html__('Enhance the power of TaxoPress with the Pro version:', 'simple-tags'); ?>
                            </p>
                            <ul>
                                <li><?php echo esc_html__('Unlimited “Term Display”', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Unlimited “Terms for Current Posts”', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Unlimited “Related Posts”', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Unlimited “Auto Links”', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Unlimited “Auto Terms”', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Create new terms using OpenAI and other sources', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Use Linked Terms', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Use Synonyms', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Schedule Auto Terms to run automatically', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Control the display of term metaboxes', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('Fast, professional support', 'simple-tags'); ?></li>
                                <li><?php echo esc_html__('No ads inside the plugin', 'simple-tags'); ?></li>
                            </ul>
                            <div class="upgrade-btn">
                                <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="advertisement-box-content postbox">
                        <div class="postbox-header">
                            <h3 class="advertisement-box-header hndle is-non-sortable">
                                <span><?php echo esc_html__('Need TaxoPress Support?', 'simple-tags'); ?></span>
                            </h3>
                        </div>

                        <div class="inside">
                            <p><?php echo esc_html__('If you need help or have a new feature request, let us know.', 'simple-tags'); ?>
                                <a class="advert-link" href="https://wordpress.org/support/plugin/simple-tags/" target="_blank">
                                    <?php echo esc_html__('Request Support', 'simple-tags'); ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                        <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
                                    </svg>
                                </a>
                            </p>
                            <p>
                                <?php echo esc_html__('Detailed documentation is also available on the plugin website.', 'simple-tags'); ?>
                                <a class="advert-link" href="https://taxopress.com/docs/taxopress/" target="_blank">
                                    <?php echo esc_html__('View Knowledge Base', 'simple-tags'); ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                        <path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path>
                                    </svg>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }

    public function taxopress_core_ai_after_open_ai_fields($current)
    {
        ?>
        <tr>
            <td>
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('OpenAI Integration', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to integrate OpenAI to analyze your content and suggest terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function taxopress_core_ai_after_ibm_watson_fields($current)
    {
        ?>
    <tr>
        <td>
            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('IBM Watson Integration', 'simple-tags'); ?></span>
                    </h3>
                </div>

                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to integrate Watson to analyze your content and suggest terms.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        </td>
    </tr>
        <?php
    }

    public function taxopress_core_ai_after_dandelion_fields($current)
    {
        ?>
    <tr>
        <td>
            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Dandelion Integration', 'simple-tags'); ?></span>
                    </h3>
                </div>

                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to integrate Dandelion to analyze your content and suggest terms.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        </td>
    </tr>
        <?php
    }

    public function taxopress_core_ai_after_open_calais_fields($current)
    {
        ?>
    <tr>
        <td>
            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('LSEG / Refinitiv Integration', 'simple-tags'); ?></span>
                    </h3>
                </div>

                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to integrate LSEG / Refinitiv to analyze your content and suggest terms.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        </td>
    </tr>
        <?php
    }



    public function taxopress_core_ai_term_results_banner($args)
    {
        $ai_group     = $args['ai_group'];

        if ($ai_group == 'open_ai') : ?>
            <br />
            <br />
            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Suggest terms using AI', 'simple-tags'); ?></span>
                    </h3>
                </div>
                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to suggest new terms for your content using the OpenAI. This service can analyze your content and suggest new terms.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        <?php elseif ($ai_group == 'ibm_watson') :
            ?>
            <br />
            <br />
            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Suggest terms using AI', 'simple-tags'); ?></span>
                    </h3>
                </div>

                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to suggest new terms for your content using the IBM Watson. This service can analyze your content and suggest new terms.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
            <?php
        endif;
    }

    public function taxopress_core_copy_action($actions, $item)
    {
        $allowed_pages = ['st_autolinks', 'st_terms_display', 'st_post_tags', 'st_related_posts', 'st_autoterms'];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (in_array($current_page, $allowed_pages, true)) {
            $copy_action = [
                'copy' => sprintf(
                    '<a href="%s" class="copy-action-pages">%s</a>',
                    add_query_arg([
                        'page' => $current_page,
                        'add'  => 'new_item',
                    ], admin_url('admin.php')),
                    __('Copy', 'simple-tags')
                )
            ];

            // Ensure "Copy" appears before "Delete"
            if (isset($actions['delete'])) {
                $new_actions = [];
                foreach ($actions as $key => $action) {
                    if ($key === 'delete') {
                        $new_actions['copy'] = $copy_action['copy'];
                    }
                    $new_actions[$key] = $action;
                }
                $actions = $new_actions;
            } else {
                $actions = $copy_action + $actions;
            }
        }

        return $actions;
    }

    public function taxopress_core_copy_with_metadata_action($actions, $item)
    {
        $allowed_pages = ['st_terms'];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if (in_array($current_page, $allowed_pages, true)) {
            $copy_action = [
                'copy_term_with_meta' => sprintf(
                    '<a href="%s" class="copy-action-pages">%s</a>',
                    add_query_arg([
                        'page' => $current_page,
                        'action' => 'new_item',
                        'term_id' => $item->term_id,
                        '_wpnonce' => wp_create_nonce('term-copy-metadata-nonce')
                    ], admin_url('admin.php')),
                    __('Copy with Metadata', 'simple-tags')
                )
            ];

            // Ensure "Copy with Metadata" appears after "Copy"
            if (isset($actions['copy_term'])) {
                $new_actions = [];
                foreach ($actions as $key => $action) {
                    $new_actions[$key] = $action;
                    if ($key === 'copy_term') {
                        $new_actions['copy_term_with_meta'] = $copy_action['copy_term_with_meta'];
                    }
                }
                $actions = $new_actions;
            } else {
                $actions = $copy_action + $actions;
            }
        }

        return $actions;
    }

    public function taxopress_core_autoterm_terms_to_use_field($current)
    {
        ?>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro autoterm-terms-use-openai-notice">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Auto terms using AI', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to generate new terms for your content using the OpenAI, IBM Watson, Dandelion and Open Calais services. These services can analyze your content and add new terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro autoterm-terms-use-ibm-watson-notice">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Auto terms using AI', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to generate new terms for your content using the OpenAI, IBM Watson, Dandelion and Open Calais services. These services can analyze your content and add new terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro autoterm-terms-use-dandelion-notice">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Auto terms using AI', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to generate new terms for your content using the OpenAI, IBM Watson, Dandelion and Open Calais services. These services can analyze your content and add new terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro autoterm-terms-use-lseg-refinitiv-notice">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Auto terms using AI', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to generate new terms for your content using the OpenAI, IBM Watson, Dandelion and Open Calais services. These services can analyze your content and add new terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function taxopress_core_suggestterm_after_api_fields($current)
    {
        ?>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Suggest terms using AI', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to suggest new terms for your content using the OpenAI, IBM Watson, Dandelion and Open Calais services. These services can analyze your content and suggest new terms.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function taxopress_core_autolinks_after_html_exclusions_promo($current)
    {
        ?>
        <tr>
            <td colspan="2">
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Add Custom Elements', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you complete control over where Auto Links are added. You can choose to skip any HTML elements that appear in your content.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function taxopress_core_autoterm_advanced_field($current)
    {
        ?>
        <tr>
            <td>
                <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                    <div class="postbox-header">
                        <h3 class="advertisement-box-header hndle is-non-sortable">
                            <span><?php echo esc_html__('Use Regular Expressions to modify Auto Terms', 'simple-tags'); ?></span>
                        </h3>
                    </div>

                    <div class="inside-content">
                        <p><?php echo esc_html__('TaxoPress Pro allows you to change how Auto Terms analyzes your posts. You will need to know how to write Regular Expressions to use this feature.', 'simple-tags'); ?></p>
                        <div class="upgrade-btn">
                            <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }
    /**
     * Add Linked Terms feature to dashboard
     */
    public function taxopress_core_linked_terms_feature($features)
    {
        $st_terms_position = array_search('st_terms', array_keys($features));

        if ($st_terms_position !== false) {
            $new_features = array_slice($features, 0, $st_terms_position + 1, true);

            $new_features['st_linked_terms'] = [
                'label'        => esc_html__('Linked Terms', 'simple-tags'),
                'description'  => esc_html__('This feature allows you to connect terms. When the main term or any of these terms are added to the post, all the other terms will be added also.', 'simple-tags'),
                'option_key'   => 'active_features_core_linked_terms',
                'class'       => 'feature-pro-locked',
            ];

            $new_features += array_slice($features, $st_terms_position + 1, null, true);
            return $new_features;
        }

        return $features;
    }

    public function taxopress_core_linked_terms_content($content)
    {
        ob_start();
        ?>

            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable taxopress-core-terms-promobox">
                        <span><?php echo esc_html__('Linked Terms Feature', 'simple-tags'); ?></span>
                    </h3>
                </div>
                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to create powerful connections between your terms. When one term is added to a post, its linked terms can be automatically added too. This helps maintain consistent organization across your content.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    /**
    * Add Synonyms Terms feature to dashboard
    */
    public function taxopress_core_synonyms_terms_feature($features)
    {

        $features['st_features_synonyms'] = [
            'label'        => esc_html__('Synonyms', 'simple-tags'),
            'description'  => esc_html__('This feature allows you to associate additional words with each term. For example, "website" can have synonyms such as "websites", "web site", and "web pages".', 'simple-tags'),
            'option_key'   => 'active_features_core_synonyms_terms',
            'class'       => 'feature-pro-locked',
        ];

        return $features;
    }

    public function taxopress_core_synonyms_terms_content($content)
    {
        ob_start();
        ?>

            <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
                <div class="postbox-header">
                    <h3 class="advertisement-box-header hndle is-non-sortable taxopress-core-terms-promobox">
                        <span><?php echo esc_html__('Synonyms Terms Feature', 'simple-tags'); ?></span>
                    </h3>
                </div>
                <div class="inside-content">
                    <p><?php echo esc_html__('TaxoPress Pro allows you to have multiple words associated with a single term. If TaxoPress scans your content and finds a synonym, it will act as if it has found the main term.', 'simple-tags'); ?></p>
                    <div class="upgrade-btn">
                        <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                    </div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    public function filter_settings_post_type_ai_fields($taxopress_ai_fields, $post_type)
    {

        $default_taxonomy_display_options = [
            'default' => esc_html__('Default', 'simple-tags'),
        ];

        $new_entry = array(
            'taxopress_ai_' . $post_type . '_metabox_display_option',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content">' .
                esc_html__('Taxonomy Display', 'simple-tags') .
            '</div>',
            'select_with_icon',
            $default_taxonomy_display_options,
            '<div class="taxopress-select-icon-wrapper">
                    <span class="pp-tooltips-library" data-toggle="tooltip">
                        <span class="dashicons dashicons-lock taxopress-select-icon"></span>
                        <span class="taxopress tooltip-text">' .
                        esc_html__('This feature is available in TaxoPress Pro', 'simple-tags') .
                    '</span>
                    </span>
                </div>
                <div class="taxopress-stpexplan">' .
                esc_html__('Customize the display of terms in the TaxoPress metabox.', 'simple-tags') . '<br />' .
                esc_html__('Options include checkboxes and a dropdown list.', 'simple-tags') .
            '</div>',
            'taxopress-select-with-icon taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content',
            array(
                'icon' => '',
                'modal' => '',
                'icon_wrapper_class' => '',
                'modal_wrapper_class' => '',
            ),
        );
        // Get the index of 'taxopress_ai_post_metabox_default_taxonomy' if it exists
        $field_to_find = 'taxopress_ai_' . $post_type . '_metabox_default_taxonomy';
        $keys = array_column($taxopress_ai_fields, 0);
        $insert_after_key = array_search($field_to_find, $keys);

        // Determine the insertion position adding fallback incase the setting doesn't exist
        $position = ($insert_after_key !== false) ? $insert_after_key + 1 : count($taxopress_ai_fields);

        // Insert new entry at the determined position
        $taxopress_ai_fields = array_merge(
            array_slice($taxopress_ai_fields, 0, $position, true),
            [$new_entry],
            array_slice($taxopress_ai_fields, $position, null, true)
        );

        return $taxopress_ai_fields;
    }

    public function taxopress_terms_copy_with_metadata_promo()
    {
        ?>
        <div class="taxopress-content-promo-box advertisement-box-content postbox postbox upgrade-pro">
            <div class="postbox-header">
                <h3 class="advertisement-box-header hndle is-non-sortable taxopress-core-terms-promobox">
                    <span><?php echo esc_html__('Copy Term with Metadata', 'simple-tags'); ?></span>
                </h3>
            </div>

            <div class="inside-content">
                <h2><?php echo esc_html__('To Copy terms with their metadata, please upgrade to pro.', 'simple-tags') ?></h2>
                <p><?php echo esc_html__('With TaxoPress Pro, you can duplicate taxonomy terms along with all their metadata. This includes term descriptions, images, and custom fields. You can copy terms between taxonomies to maintain consistent organization across your site.', 'simple-tags'); ?></p>
                <div class="upgrade-btn">
                    <a href="https://taxopress.com/taxopress/" target="__blank"><?php echo esc_html__('Upgrade to Pro', 'simple-tags'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Define the default display formats
     *
     * @return array
     */
    public function taxopress_render_display_formats_field()
    {

        $select = [
            'flat' => esc_html__('Cloud', 'simple-tags')
        ];
        ?>
        <tr>
            <th>
                <div class="taxopress_post_tags[format]"> 
                   <?php echo esc_html__('Display Format', 'simple-tags'); ?>
                </div>
            </th>

            <td>
                <div class="taxopress-select-with-icon-wrapper">
                    <select name="taxopress_post_tags[display_format]" class="taxopress-select-with-icon" disabled="disabled">
                        <?php
                        foreach ($select as $key => $label) {
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($key),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                    <span class="pp-tooltips-library" data-toggle="tooltip">
                        <span class="dashicons dashicons-lock taxopress-select-icon taxopress-formats"></span>
                        <span class="taxopress tooltip-text"><?php echo esc_html__('This feature is available in TaxoPress Pro', 'simple-tags'); ?></span>
                    </span>
                </div>
                <div class="taxopress-stpexplan">
                    <?php echo esc_html__('Choose the display formats of your terms, options include table list, ordered list, border-cloud and lots more.', 'simple-tags'); ?>
                </div>
            </td>
        </tr>
        <?php
        return '';
    }

    public function taxopress_free_schedule_frequency_fields($cron_schedule)
    {
        ?>
        <label>
            <input
                type="radio"
                class="autoterm_cron_radio"
                id="autoterm_cron_hourly"
                name="taxopress_autoterm_schedule[cron_schedule_choice]"
                value="hourly"
                disabled
            />
            <?php esc_html_e('Hourly', 'simple-tags'); ?>
        </label>
        <br /><br />
        
        <label>
            <input
                type="radio"
                class="autoterm_cron_radio"
                id="autoterm_cron_daily"
                name="taxopress_autoterm_schedule[cron_schedule_choice]"
                value="daily"
                disabled
            />
            <?php esc_html_e('Daily', 'simple-tags'); ?>
        </label>
        <br />

        <div class="taxopress-pro-feature-btn-wrapper">
            <span class="pp-tooltips-library" data-toggle="tooltip">
                <button type="button" class="taxopress-pro-feature-btn" tabindex="-1">
                    <span class="dashicons dashicons-lock taxopress-pro-feature-lock"></span>
                    <?php echo esc_html__('Pro Feature', 'simple-tags'); ?>
                </button>
                <span class="taxopress tooltip-text"><?php echo esc_html__('Some of these features are available in TaxoPress Pro', 'simple-tags'); ?></span>
            </span>
        </div>
        <?php
    }

    public function taxopress_terms_order_free($current)
    {

        $orderby_options = [
            [ 'attr' => 'term_id', 'text' => esc_attr__('ID', 'simple-tags'), 'default' => true ],
            [ 'attr' => 'name_free', 'text' => esc_attr__('Name (Pro)', 'simple-tags') ],
            [ 'attr' => 'count_free', 'text' => esc_attr__('Counter (Pro)', 'simple-tags') ],
            [ 'attr' => 'random_free', 'text' => esc_attr__('Random (Pro)', 'simple-tags') ],
            [ 'attr' => 'taxopress_term_order_free', 'text' => esc_attr__('Term Order (Pro)', 'simple-tags') ],
        ];
        $selected_orderby = isset($current['orderby']) ? $current['orderby'] : '';

        $name = isset($current['name']) ? $current['name'] : '';
        $terms_table_url = esc_url(
            admin_url(
                'admin.php?page=st_terms&taxopress_terms_taxonomy=' . urlencode($name) . '&taxopress_pro_notice=term_order'
            )
        );
        ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Method for choosing terms for display', 'simple-tags'); ?></th>
                <td>
                    <?php foreach ($orderby_options as $option) :
                        $is_id = ($option['attr'] === 'term_id');
                        $disabled = $is_id ? '' : 'disabled';
                        $blur_class = $is_id ? '' : 'taxopress-blur-option';
                        $checked = $is_id ? 'checked="checked"' : '';
                        ?>
                        <label style="display:block;margin-bottom:4px;" class="<?php echo esc_attr($blur_class); ?>">
                            <input type="radio"
                                name="cpt_custom_tax[orderby]"
                                value="<?php echo esc_attr($option['attr']); ?>"
                                <?php echo esc_attr($disabled); ?>
                                <?php echo esc_attr($checked); ?>
                            />
                            <?php echo esc_html($option['text']); ?>
                        </label>
                    <?php endforeach; ?>

                    <!-- Pro Feature Button -->
                    <div class="taxopress-pro-feature-btn-wrapper">
                        <span class="pp-tooltips-library" data-toggle="tooltip">
                            <button type="button" class="taxopress-pro-feature-btn" tabindex="-1">
                                <span class="dashicons dashicons-lock taxopress-pro-feature-lock"></span>
                                <?php echo esc_html__('Pro Feature', 'simple-tags'); ?>
                            </button>
                            <span class="taxopress tooltip-text"><?php echo esc_html__('Some of these features are available in TaxoPress Pro', 'simple-tags'); ?></span>
                        </span>
                    </div>
                </td>
            </tr>
            <?php

            $order_options = [
                [ 'attr' => 'asc', 'text' => esc_attr__('Ascending', 'simple-tags'), 'default' => true ],
                [ 'attr' => 'desc', 'text' => esc_attr__('Descending', 'simple-tags') ],
            ];
            $selected_order = isset($current['order']) ? $current['order'] : '';
            ?>
            <tr>
                <th scope="row"><?php echo esc_html__('Ordering for choosing terms for display', 'simple-tags'); ?></th>
                <td>
                <?php foreach ($order_options as $option) :
                    $is_asc = ($option['attr'] === 'asc');
                    $checked = ($selected_order === $option['attr'] || (empty($selected_order) && $is_asc)) ? 'checked="checked"' : '';
                    ?>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="radio"
                            name="cpt_custom_tax[order]"
                            value="<?php echo esc_attr($option['attr']); ?>"
                            <?php echo esc_attr($checked); ?>
                        />
                        <?php echo esc_html($option['text']); ?>
                    </label>
                <?php endforeach; ?>
                </td>
            </tr>
            <?php
    }

    public function taxopress_order_column_free($item)
    {
        // Get the orderby value and ordering enabled flag from the taxonomy object
        $taxonomies = taxopress_get_all_edited_taxonomy_data();
        $order_value = '';
        $ordering_enabled = false;

        if (isset($taxonomies[$item->name]['orderby'])) {
            $order_value = $taxonomies[$item->name]['orderby'];
        }
        if (!empty($taxonomies[$item->name]['enable_taxopress_ordering'])) {
            $ordering_enabled = true;
        }

        $orderby_options = [
            'name' => esc_html__('Name', 'simple-tags'),
            'term_id' => esc_html__('ID', 'simple-tags'),
            'count' => esc_html__('Counter', 'simple-tags'),
            'random' => esc_html__('Random', 'simple-tags'),
            'taxopress_term_order' => esc_html__('Term Order', 'simple-tags'),
        ];

        // Build the terms table URL
        $terms_table_url = esc_url(
            admin_url(
                'admin.php?page=st_terms&taxopress_terms_taxonomy=' . urlencode($item->name) . '&taxopress_pro_notice=term_order'
            )
        );

        if (!$ordering_enabled) {
            // Show Disabled with tooltip
            return sprintf(
                '<div class="pp-tooltips-library" data-toggle="tooltip">
                    <span class="taxopress-order-disabled">%s</span>
                    <div class="taxopress tooltip-text">%s</div>
                </div>',
                esc_html__('Disabled', 'simple-tags'),
                esc_html__('You have disabled TaxoPress ordering', 'simple-tags')
            );
        }

        $order_label = isset($orderby_options[$order_value]) ? $orderby_options[$order_value] : esc_html__('ID', 'simple-tags');
        $tooltip = sprintf(
            esc_html__('Terms in this taxonomy are ordered by %s', 'simple-tags'),
            $order_label
        );

        if ($order_value === 'taxopress_term_order') {
            // Make "Term Order" a link
            return sprintf(
                '<div class="pp-tooltips-library" data-toggle="tooltip">
                    <a href="%s" target="_blank">%s</a>
                    <div class="taxopress tooltip-text">%s</div>
                </div>',
                $terms_table_url,
                esc_html($orderby_options[$order_value]),
                esc_html($tooltip)
            );
        }
        return sprintf(
            '<div class="pp-tooltips-library" data-toggle="tooltip">
                %s
                <div class="taxopress tooltip-text">%s</div>
            </div>',
            esc_html($order_label),
            esc_html($tooltip)
        );
    }
    /**
     * Display the ordering method for the tag cloud in the settings
     *
     * @param array $current Current settings for the tag cloud.
    */
    public function taxopress_free_tagcloud_ordering_method($current)
    {
        $options = [
            'name' => esc_attr__('Name', 'simple-tags'),
            'count' => esc_attr__('Counter', 'simple-tags'),
            'random' => esc_attr__('Random', 'simple-tags'),
            'taxopress_term_order_free' => esc_attr__('Term Order(Pro)', 'simple-tags'),
        ];

        $selected = isset($current['orderby']) ? $current['orderby'] : 'random';
        ?>
        <tr>
            <th>
                <div class="taxopress_tag_cloud[format]">
                    <?php echo esc_html__('Method for choosing terms for display', 'simple-tags'); ?>
                </div>
            </th>
            <td>
                <div class="taxopress-select-with-icon-wrapper taxopress-order">
                    <select name="taxopress_tag_cloud[orderby]" class="taxopress-select-with-icon">
                        <?php foreach ($options as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"
                                <?php
                                    selected($selected, $key);
                                if ($key === 'taxopress_term_order_free') {
                                    echo ' disabled';
                                }
                                ?>
                            ><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div>
                        <span class="pp-tooltips-library" data-toggle="tooltip">
                            <span class="dashicons dashicons-lock taxopress-select-icon order"></span>
                            <span class="taxopress tooltip-text"><?php echo esc_html__('Upgrade to TaxoPress Pro to use Term Order to manually order Terms', 'simple-tags'); ?></span>
                        </span>
                    </div>
                </div>
            </td>
        </tr>

        <?php
    }

    public function taxopress_free_posttags_ordering_method($current)
    {
        $options = [
            'name' => esc_attr__('Name', 'simple-tags'),
            'count' => esc_attr__('Counter', 'simple-tags'),
            'random' => esc_attr__('Random', 'simple-tags'),
            'taxopress_term_order_free' => esc_attr__('Term Order(Pro)', 'simple-tags'),
        ];

        $selected = isset($current['orderby']) ? $current['orderby'] : 'random';
        ?>
        <tr>
            <th>
                <div class="taxopress_post_tags[format]">
                    <?php echo esc_html__('Method for choosing terms for display', 'simple-tags'); ?>
                </div>
            </th>
            <td>
                <div class="taxopress-select-with-icon-wrapper taxopress-order">
                    <select name="taxopress_tag_cloud[orderby]" class="taxopress-select-with-icon">
                        <?php foreach ($options as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"
                                <?php
                                    selected($selected, $key);
                                if ($key === 'taxopress_term_order_free') {
                                    echo ' disabled';
                                }
                                ?>
                            ><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div>
                        <span class="pp-tooltips-library" data-toggle="tooltip">
                            <span class="dashicons dashicons-lock taxopress-select-icon order"></span>
                            <span class="taxopress tooltip-text"><?php echo esc_html__('Upgrade to TaxoPress Pro to use Term Order to manually order Terms', 'simple-tags'); ?></span>
                        </span>
                    </div>
                </div>
            </td>
        </tr>

        <?php
    }
}
