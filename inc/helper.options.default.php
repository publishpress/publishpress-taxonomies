<?php

return array(
    // Features
    'active_taxonomies'      => 1,
    'active_terms_display'   => 1,
    'active_post_tags'       => 1,
    'active_related_posts_new' => 1,
    'active_auto_links'      => 1,
    'active_auto_terms'      => 1,
    'active_suggest_terms'   => 1,
    'active_mass_edit'       => 1,
    'active_manage'          => 1,
    'active_related_posts'   => 1,
    'active_autotags'        => 1,
    'active_st_terms'        => 1,
    'active_features_synonyms'      => 1,
    'active_features_linked_terms'  => 1,
    'active_features_core_linked_terms' => 1,
    'active_features_core_synonyms_terms' => 1,
    'active_st_posts'        => 1,

    // post terms filter format
    'post_terms_filter_format'  => 'term_name',
    'post_terms_taxonomy_type' => 'public',

    // linked terms
    'linked_terms_taxonomies' => ['category', 'post_tag'],
    'linked_terms_type'       => 'main',

    // term synonyms
    'synonyms_taxonomies' => ['category', 'post_tag'],

    //hidden terms
    'enable_hidden_terms' => 0,
    'hide-rarely' => 5,

    //frontend scripts
    'disable_frontend_scripts' => 0,
    'disable_admin_frontend_scripts' => 0,

    //manage terms
    'enable_add_terms_slug' => 0,
    'enable_remove_terms_slug' => 0,
    'enable_rename_terms_slug' => 0,
    'enable_merge_terms_slug' => 1,
    'enable_mass-edit_terms_slug' => 0,

    // taxopress ai
    'enable_taxopress_ai_post_metabox' => 1,
    'enable_taxopress_ai_post_post_terms_tab' => 1,
    'taxopress_ai_post_metabox_filters' => 1,
    'enable_taxopress_ai_post_suggest_local_terms_tab' => 1,
    'enable_taxopress_ai_post_existing_terms_tab' => 1,
    'taxopress_ai_post_metabox_default_taxonomy' => 'post_tag',

    // taxopress ai tab labels
    'taxopress_ai_post_terms_tab_label' => __('Manage Post Terms', 'simple-tags'),
    'taxopress_ai_existing_terms_tab_label' => __('Show All Existing Terms', 'simple-tags'),
    'taxopress_ai_suggest_local_terms_tab_label' => __('Auto Terms', 'simple-tags'),
    'taxopress_ai_create_terms_tab_label' => __('Create Terms', 'simple-tags'),

    'taxopress_ai_post_metabox_orderby' => 'count',
    'taxopress_ai_post_metabox_order' => 'desc',
    'taxopress_ai_post_metabox_maximum_terms' => 45,
    'taxopress_ai_post_metabox_show_post_count' => 0,
    'taxopress_ai_post_metabox_show_term_slug' => 0,

    'taxopress_ai_post_minimum_term_length' => 2,
    'taxopress_ai_post_maximum_term_length' => 40,

    'taxopress_ai_post_metabox_display_option' => 'default',
    'enable_taxopress_ai_post_create_terms_tab' => 1,
    'taxopress_ai_post_exclusions' => '',

    // legacy ai settings
    'enable_ibm_watson_ai_source' => 0,
    'enable_dandelion_ai_source' => 0,
    'enable_lseg_ai_source' => 0,

    // metabox
    'enable_administrator_metabox' => 1,
    'enable_editor_metabox' => 1,
    'enable_author_metabox' => 1,
    'enable_contributor_metabox' => 1,

    'enable_metabox_administrator' => ['category', 'post_tag'],
    'enable_metabox_editor' => ['category', 'post_tag'],
    'enable_metabox_author' => ['category', 'post_tag'],
    'enable_metabox_contributor' => ['category', 'post_tag'],

    'enable_restrict_administrator_metabox' => 1,
    'enable_restrict_editor_metabox' => 1,
    'enable_restrict_author_metabox' => 1,
    'enable_restrict_contributor_metabox' => 1,

    'enable_edit_administrator_metabox' => 1,
    'enable_edit_editor_metabox' => 1,
    'enable_edit_author_metabox' => 0,
    'enable_edit_contributor_metabox' => 0,

    'allow_embed_tcloud'     => 1,
    // Auto link
    'auto_link_tags'         => 0,
    'auto_link_min'          => 1,
    'auto_link_all'          => 1,
    'auto_link_case'         => 1,
    'auto_link_exclude'      => '',
    'auto_link_max_by_post'  => 10,
    'auto_link_max_by_tag'   => 1,
    'auto_link_priority'     => 12,
    'auto_link_views'        => 'singular',
    'auto_link_dom'          => 0,
    'auto_link_title'        => __('Posts tagged with %s', 'simple-tags'),
    'auto_link_title_custom_url' => __('Visit this URL for more on %s', 'simple-tags'),
    'auto_link_title_excl'   => 0,
    // The tags
    'tt_feed'                => 0,
    'tt_embedded'            => 'no',
    'tt_separator'           => ', ',
    'tt_before'              => __('Tags: ', 'simple-tags'),
    'tt_after'               => '<br />',
    'tt_notagstext'          => __('No tags for this post.', 'simple-tags'),
    'tt_number'              => 0,
    'tt_inc_cats'            => 0,
    'tt_xformat'             => __('<a href="%tag_link%" title="%tag_name%" class="st-post-tags t%tag_scale%" style="%tag_size% %tag_color%" %tag_rel%>%tag_name%</a>', 'simple-tags'),
    'tt_format'              => 'flat',
    'tt_adv_usage'           => '',
    'tt_min_size'            => 12,
    'tt_max_size'             => 12,
    'tt_min_color'        => '#353535',
    'tt_max_color'        => '#000000',
    'tt_unit'                => 'pt',
    'tt_selectionby'         => 'count',
    'tt_selection'           => 'desc',
    'tt_orderby'             => 'name',
    'tt_order'               => 'asc',
    'tt_limit_days'          => 0,
    // Related Posts
    'rp_taxonomy'            => 'post_tag',
    'rp_feed'                => 0,
    'rp_embedded'            => 'no',
    'rp_order'               => 'count-desc',
    'rp_limit_qty'           => 3,
    'rp_notagstext'          => __('No related posts.', 'simple-tags'),
    'rp_title'               => __('<h4>Related posts</h4>', 'simple-tags'),
    'rp_xformat'             =>  __('<a href="%post_permalink%" title="%post_title% (%post_date%)" style="font-size:%post_size%;color:%post_color%"> 
			                       %post_title% <br> 
			                       <img src="%post_thumb_url%" height="200" width="200" class="custom-image-class" />
			                       </a> 
			                       (%post_comment%)', 'simple-tags'),
    'rp_default_featured_media' => 'default',
    'rp_format'              => 'box',
    'rp_adv_usage'           => '',
    'rp_max_size'            => 12,
    'rp_min_size'             => 12,
    'rp_min_color'        => '#353535',
    'rp_max_color'        => '#000000',
    'rp_unit'                => 'pt',
    'rp_image_width' => 200,
    'rp_image_height' => 200,
    // Tag cloud
    'cloud_taxonomy'         => 'post_tag',
    'cloud_selectionby'      => 'count',
    'cloud_selection'        => 'desc',
    'cloud_orderby'          => 'random',
    'cloud_order'            => 'asc',
    'cloud_limit_qty'        => 20,
    'cloud_notagstext'       => __('No tags.', 'simple-tags'),
    'cloud_title'            => __('<h4>Tag Cloud</h4>', 'simple-tags'),
    'cloud_format'           => 'border',
    'cloud_xformat'          => __('<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>', 'simple-tags'),
    'cloud_max_color'        => '#000000',
    'cloud_min_color'        => '#353535',
    'cloud_max_size'         => 12,
    'cloud_min_size'         => 12,
    'cloud_unit'             => 'pt',
    'cloud_adv_usage'        => '',
    'cloud_parent_term'     => '',
    'cloud_display_mode'   => 'parents_and_sub'
);
