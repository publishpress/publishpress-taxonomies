<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

require_once STAGS_DIR . '/modules/taxopress-ai/classes/TaxoPressAiUtilities.php';

$current_screen = get_current_screen();
$is_fast_update = isset($_GET['page']) && $_GET['page'] === 'st_taxopress_ai';

$taxopress_ai_tabs = [];

$taxopress_posts_terms_label = get_option(
    'taxopress_ai_post_terms_tab_label',
    SimpleTags_Plugin::get_option_value('taxopress_ai_post_terms_tab_label')
);
$taxopress_existing_terms_label = get_option(
    'taxopress_ai_existing_terms_tab_label',
    SimpleTags_Plugin::get_option_value('taxopress_ai_existing_terms_tab_label')
);
$taxopress_suggest_local_terms_label = get_option(
    'taxopress_ai_suggest_local_terms_tab_label',
    SimpleTags_Plugin::get_option_value('taxopress_ai_suggest_local_terms_tab_label')
);
$taxopress_create_terms_label = get_option(
    'taxopress_ai_create_terms_tab_label',
    SimpleTags_Plugin::get_option_value('taxopress_ai_create_terms_tab_label')
);

if (empty($taxopress_existing_terms_label)) {
    $taxopress_existing_terms_label = esc_html__('Show All Existing Terms', 'simple-tags');
}
if (empty($taxopress_posts_terms_label)) {
    $taxopress_posts_terms_label = esc_html__('Manage Post Terms', 'simple-tags');
}
if (empty($taxopress_suggest_local_terms_label)) {
    $taxopress_suggest_local_terms_label = esc_html__('Auto Terms', 'simple-tags');
}
if (empty($taxopress_create_terms_label)) {
    $taxopress_create_terms_label = esc_html__('Create Terms', 'simple-tags');
}

$taxopress_ai_tabs['existing_terms'] = $taxopress_existing_terms_label;
$taxopress_ai_tabs['post_terms'] = $taxopress_posts_terms_label;
$taxopress_ai_tabs['suggest_local_terms'] = $taxopress_suggest_local_terms_label;
$taxopress_ai_tabs['create_terms'] = $taxopress_create_terms_label;

$taxopress_ai_fields = [];
$pt_index = 0;
foreach (TaxoPressAiUtilities::get_post_types_options() as $post_type => $post_type_object) {
    $hidden_field = ($pt_index === 0) ? '' : 'st-hide-content';

    $default_taxonomy_options = [];
    foreach (get_object_taxonomies($post_type, 'objects') as $tax_key => $tax_object) {
        if (!in_array($tax_key, ['post_format']) && (!empty($tax_object->show_ui) || !empty(SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_support_private_taxonomy')))) {
            $default_taxonomy_options[$tax_key] = $tax_object->labels->name . ' (' . $tax_object->name . ')';
        }
    }

    if (empty($default_taxonomy_options)) {
        // This feature only matter if a post has taxonomy
        $taxopress_ai_fields[] = array(
            'enable_taxopress_ai_' . $post_type . '_text',
            '',
            'helper',
            '1',
            esc_html__('This post type has no taxonomies.', 'simple-tags'),
            'taxopress-ai-tab-content taxopress-ai-' . $post_type . '-content ' . $hidden_field . ''
        );
    } else {
        $taxopress_ai_fields[] = array(
            'enable_taxopress_ai_' . $post_type . '_metabox',
            sprintf(esc_html__('%1s Metabox', 'simple-tags'), esc_html($post_type_object->labels->name)),
            'checkbox',
            '1',
            sprintf(esc_html__('Enable the metabox on the %1s screen.', 'simple-tags'), esc_html($post_type_object->labels->name)),
            'taxopress-ai-tab-content taxopress-ai-' . $post_type . '-content ' . $hidden_field . ''
        );

        // add feature tab
        $tab_field_options = [];
        foreach ($taxopress_ai_tabs as $taxopress_ai_tab => $taxopress_ai_tab_label) {
            $tab_field_options['enable_taxopress_ai_' . $post_type . '_' . $taxopress_ai_tab . '_tab'] = [
                'label' => $taxopress_ai_tab_label
            ];
        }
        $taxopress_ai_fields[] = array(
            'enable_taxopress_ai_' . $post_type . '_tab',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_field st-subhide-content">' . esc_html__('Metabox Features', 'simple-tags') . '</div>',
            'sub_multiple_checkbox',
            $tab_field_options,
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_field st-subhide-content'
        );

        $taxopress_ai_fields[] = array(
             'taxopress_ai_' . $post_type . '_support_private_taxonomy',
            sprintf(esc_html__('Show Private Taxonomies', 'simple-tags')),
            'checkbox',
            '1',
            sprintf(esc_html__('Add support for private taxonomies.', 'simple-tags')),
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content'
        );

        // add taxonomy
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_default_taxonomy',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content">' . esc_html__('Default Taxonomy', 'simple-tags') . '</div>',
            'select',
            $default_taxonomy_options,
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_post_terms_tab_field st-subhide-content'
        );

        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_filters',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content">' . sprintf(esc_html__('%1s Metabox Filters', 'simple-tags'), esc_html($post_type_object->labels->name)) . '</div>',
            'checkbox',
            '1',
            sprintf(esc_html__('Enable filters in the %1s existing terms tab.', 'simple-tags'), esc_html($post_type_object->labels->name)),
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content'
        );

        // add _metabox_orderby
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_orderby',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content">' . esc_html__('Default Method for choosing terms', 'simple-tags') . '</div>',
            'select',
            TaxoPressAiUtilities::get_existing_terms_orderby(),
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content'
        );

        // add _metabox_order
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_order',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content">' . esc_html__('Default Ordering for choosing terms', 'simple-tags') . '</div>',
            'select',
            TaxoPressAiUtilities::get_existing_terms_order(),
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content'
        );

        // add _metabox_maximum_terms
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_maximum_terms',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content">' . esc_html__('Default maximum terms', 'simple-tags') . '</div>',
            'number',
            '',
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_metabox_filters_field st-subhide-content',
            0
        );

        // add _metabox_show_post_count
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_show_post_count',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content">' . esc_html__('Metabox Show Term Post Count', 'simple-tags') . '</div>',
            'checkbox',
            '1',
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content'
        );

        // add _metabox_show_term_slug
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_metabox_show_term_slug',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content">' . esc_html__('Show Term Slug', 'simple-tags') . '</div>',
            'checkbox',
            '1',
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_existing_terms_tab_field st-subhide-content'
        );

        //add minimum term length
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_minimum_term_length',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content">' . esc_html__('Minimum term length', 'simple-tags') . '</div>',
            'number',
            '',
            '<p>' . esc_html__('Specify the minimum length for new terms when creating terms.', 'simple-tags') . '</p>',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content',
            1
        );

        //add maximum term length
        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_maximum_term_length',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content">' . esc_html__('Maximum term length', 'simple-tags') . '</div>',
            'number',
            '',
            '<p>' . esc_html__('Specify the maximum length for new terms when creating terms.', 'simple-tags') . '</p>',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content',
            1
        );

        $taxopress_ai_fields[] = array(
            'taxopress_ai_' . $post_type . '_exclusions',
            '<div class="taxopress-ai-tab-content-sub taxopress-settings-subtab-title taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content">' . esc_html__('Prohibited Characters', 'simple-tags') . '</div>',
            'textarea',
            '',
            '',
            'taxopress-ai-tab-content-sub taxopress-ai-' . $post_type . '-content-sub enable_taxopress_ai_' . $post_type . '_create_terms_tab_field st-subhide-content',
            '',
            array(
                'rows' => 6,
                'placeholder' => esc_attr__('Enter characters that will be blocked when users add new terms inside the "Create Terms" box. For example: !@#$%&*()', 'simple-tags'),
                'width' => '80%'
            )
        );
        // allow to taxopress ai field for each post type
        $taxopress_ai_fields = apply_filters('taxopress_settings_post_type_ai_fields', $taxopress_ai_fields, $post_type);
    }
    $pt_index++;
}

$all_taxonomies = get_taxonomies([], 'objects');
$all_taxonomy_options = [];
foreach ($all_taxonomies as $tax) {
    if (in_array($tax->name, ['author', 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category'])) {
        continue;
    }
    $all_taxonomy_options[$tax->name] = $tax->label;
}

$metabox_taxonomy_options = [];
foreach ($all_taxonomies as $tax) {
    if (!empty($tax->public) && !empty($tax->show_ui)) {
        $metabox_taxonomy_options[$tax->name] = $tax->label;
    }
}


//metabox
$metabox_fields = [];
$pt_index = 0;
foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
    $hidden_field = ($pt_index === 0) ? '' : 'st-hide-content';
    // add option to enable/disable access
    $metabox_fields[] = array(
        'enable_' . $role_name . '_metabox',
        esc_html__('Metabox Access', 'simple-tags'),
        'checkbox',
        '1',
        sprintf(esc_html__('Allow users in the %1s role to use the TaxoPress metabox.', 'simple-tags'), esc_html(translate_user_role($role_info['name']))),
        'metabox-tab-content metabox-' . $role_name . '-content ' . $hidden_field . ''
    );
    // add option to manage terms per user role
    $metabox_fields[] = array(
       'enable_restrict' . $role_name . '_metabox',
       esc_html__('Block Users from Creating New Terms', 'simple-tags'),
       'checkbox',
       '1',
       sprintf(esc_html__('Prevent users in the %1$s role from creating new terms in the TaxoPress metabox.', 'simple-tags'), esc_html(translate_user_role($role_info['name']))),
       'metabox-tab-content metabox-' . $role_name . '-content ' . $hidden_field . ''
    );
    // add metabox allowed taxonomies
    $metabox_fields[] = array(
        'enable_metabox_' . $role_name . '',
        '<div class="metabox-tab-content taxopress-settings-subtab-title metabox-' . $role_name . '-content enable_' . $role_name . '_metabox_field ' . $hidden_field . '">' . esc_html__('Taxonomies in Metabox', 'simple-tags') . '</div>',
        'multiselect_with_desc_top',
        $metabox_taxonomy_options,
        '<p class="metabox-tab-content taxopress-settings-description metabox-' . $role_name . '-content enable_' . $role_name . '_metabox_field description ' . $hidden_field . '">' . sprintf(esc_html__('Select the taxonomies that users in %1s role can manage in the TaxoPress metabox.', 'simple-tags'), esc_html(translate_user_role($role_info['name']))) . '</p>',
        'metabox-tab-content metabox-' . $role_name . '-content enable_' . $role_name . '_metabox_field ' . $hidden_field . ''
    );
    // add core removed taxonomies
    $metabox_fields[] = array(
        'remove_taxonomy_metabox_' . $role_name . '',
        '<div class="metabox-tab-content taxopress-settings-subtab-title metabox-' . $role_name . '-content ' . $hidden_field . '">' . esc_html__('Remove Default Metaboxes', 'simple-tags') . '</div>',
        'multiselect_with_desc_top',
        $all_taxonomy_options,
        '<p class="metabox-tab-content taxopress-settings-description metabox-' . $role_name . '-content description ' . $hidden_field . '">' . sprintf(esc_html__('Remove default taxonomy metaboxes for users in the %1s role.', 'simple-tags'), esc_html(translate_user_role($role_info['name']))) . '</p>',
        'metabox-tab-content metabox-' . $role_name . '-content ' . $hidden_field . ''
    );

    $pt_index++;
}

$options = array(
    // post tab
    'posts'       => array(
        array(
            'post_tab_description',
            '',
            'helper',
            '',
            __('These settings control features on the Posts screen.', 'simple-tags'),
            ''
        ),
        array(
            'post_terms_filter_format',
            __('Terms Filter display:', 'simple-tags'),
            'radio',
            array(
                'term_name'  => __('Term Name', 'simple-tags'),
                'term_name_taxonomy_name'   => __('Term Name + Taxonomy Name', 'simple-tags'),
                'term_name_taxonomy_slug' => __('Term Name + Taxonomy Slug', 'simple-tags'),
            ),
            __('This controls the details that appear in the "Terms Filter" display and can help if you have terms with similar names.', 'simple-tags'),
            ''
        ),
        array(
            'post_terms_taxonomy_type',
            __('Terms Filter taxonomy:', 'simple-tags'),
            'radio',
            array(
                'public'  => __('Public Taxonomies', 'simple-tags'),
                'private'   => __('Private Taxonomies', 'simple-tags'),
                'term_and_private' => __('Public Taxonomies and Private Taxonomies', 'simple-tags'),
            ),
            __('This controls the taxonomy terms that appear on the "Posts" screen.', 'simple-tags'),
            ''
        ),
    ),


    // linked terms tab
    'linked_terms'       => array(
        array(
            'linked_terms_description',
            '',
            'helper',
            '',
            __('This feature allows you to connect terms. When the primary or secondary term is added to a post, the other term can be added also.', 'simple-tags'),
            ''
        ),
        array(
            'linked_terms_type',
            __('Linked Terms Type:', 'simple-tags'),
            'radio',
            array(
                'main'       => __('2-way relationship. When the main term or secondary term are added to the post, other term will be added also.', 'simple-tags'),
                'primary'     => __('Add the primary term, get the secondary term', 'simple-tags'),
                'secondary'     => __('Add the secondary term, get the primary term.', 'simple-tags'),
            ),
            '',
            ''
        ),
        array(
            'linked_terms_taxonomies',
            __('Enable Taxonomies:', 'simple-tags'),
            'multiselect',
            $all_taxonomy_options,
            __('This controls which taxonomies are available for the Linked Terms feature.', 'simple-tags'),
            ''
        )
    ),

    // term synonyms tab
    'synonyms'       => array(
        array(
            'synonyms_description',
            '',
            'helper',
            '',
            __('This feature allows you to have multiple words associated with a single term. If TaxoPress scans your content and finds a synonym, it will act as if it has found the main term.', 'simple-tags'),
            ''
        ),
        array(
            'synonyms_taxonomies',
            __('Enable Taxonomies:', 'simple-tags'),
            'multiselect',
            $all_taxonomy_options,
            __('This controls which taxonomies are available for the Term Synonyms feature.', 'simple-tags'),
            ''
        )
    ),

    // hidden terms tab
    'hidden_terms' => array(
        array(
            'enable_hidden_terms',
            __('Enable Hidden Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('This feature will hide terms that are infrequently used. These terms will be visible inside the WordPress admin area, but not on the front of this site.', 'simple-tags'),
            ''
        ),
        array(
            'hide-rarely',
            __('Minimum Usage for Hidden Terms:', 'simple-tags'),
            'number',
            '1',
            __('Set the minimum number of posts a term must be attached to. If you enter 5, any term used in fewer than 5 posts will be hidden across the site, and its archive page will redirect to the homepage.', 'simple-tags'),
            '',
            1
        )
    ),

    // frontend scripts tab
    'frontend_scripts' => array(
        array(
            'frontend_scripts_description',
            '',
            'helper',
            '',
            __('TaxoPress loads CSS and JavaScript files on the frontend for features like Terms Display, Related Posts, and Terms for Current Post. If you disable these scripts, those features may not display correctly.', 'simple-tags'),
            ''
        ),
        array(
            'disable_frontend_scripts',
            __('Disable Frontend Scripts:', 'simple-tags'),
            'checkbox',
            '1',
            __('Check this to prevent TaxoPress from loading CSS and JavaScript files on the frontend of your site.', 'simple-tags'),
            ''
        ),
        array(
            'disable_admin_frontend_scripts',
            __('Disable Admin Preview Scripts:', 'simple-tags'),
            'checkbox',
            '1',
            __('Check this to prevent TaxoPress from loading frontend CSS and JavaScript files in the WordPress admin area. This will break the preview functionality when editing Terms Display, Related Posts, and Terms for Current Post.', 'simple-tags'),
            ''
        )
    ),

    'core_linked_terms' => array(
        array(
            'linked_terms_pro_notice',
            '',
            'core_terms_promo',
            '',
            apply_filters('taxopress_settings_linked_terms_pro_notice', ''),
            ''
        )
    ),

    'core_synonyms_terms' => array(
        array(
            'synonyms_terms_pro_notice',
            '',
            'core_terms_promo',
            '',
            apply_filters('taxopress_settings_synonyms_terms_pro_notice', ''),
            ''
        )
    ),

    // Manage terms tab
    'manage_terms' => array(
        array(
            'manage_terms_description',
            '',
            'helper',
            '',
            __('These settings control features on the Manage Terms screen.', 'simple-tags'),
            ''
        ),
        array(
            'enable_add_terms_slug',
            __('Add Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('Enabling this will allow users to see the slug while adding terms', 'simple-tags'),
            ''
        ),
        array(
            'enable_remove_terms_slug',
            __('Remove Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('Enabling this will allow users to see the slug while removing terms', 'simple-tags'),
            ''
        ),
        array(
            'enable_rename_terms_slug',
            __('Rename Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('Enabling this will allow users to see the slug while Renaming terms', 'simple-tags'),
            ''
        ),
        array(
            'enable_merge_terms_slug',
            __('Merge Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('Enabling this will allow users to see the slug while merging terms', 'simple-tags'),
            ''
        ),
    ),

    'mass_edit_terms' => array(
        array(
            'enable_mass-edit_terms_slug',
            __('Mass Edit Terms:', 'simple-tags'),
            'checkbox',
            '1',
            __('Enabling this will allow users to see the slug in the Mass Edit Terms screen', 'simple-tags'),
            ''
        ),
    )

    );

if ($is_fast_update) {
    $options['taxopress-ai'] = $taxopress_ai_fields;
    $options['metabox'] = $metabox_fields;
}

return apply_filters('taxopress_admin_options', $options);
