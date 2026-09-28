<?php

/**
 * Register our block.
 */
function st_tag_clouds_block_init()
{
    global $post, $pagenow;

    if (!function_exists('register_block_type')) {
        return;
    }

    if ($pagenow === 'widgets.php') {
        return;
    }

    // Register our block editor script.
    wp_register_script(
        'st-block-tag-clouds',
        STAGS_URL . '/blocks/src/tag-clouds.js',
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-editor']
    );

    // Register our block, and explicitly define the attributes we accept.
    $tagcloud_data = taxopress_get_tagcloud_data();

    $options = [];
    $default = '';
    if (count($tagcloud_data) > 0) {
        $sn = 0;
        foreach ($tagcloud_data as $key => $value) {
            $sn++;
            if ($sn === 1) {
                $default = $key;
            }
            $options[] = ['label' => $value['title'], 'value' => $key];
        }
    }

    register_block_type('taxopress/tag-clouds', [
        'attributes'      => [
            'tagcloud_id' => [
                'type'    => 'string',
                'default' => $default,
            ],
            'post_id'        => [
                'type' => 'string',
            ],
        ],
        'editor_script'   => 'st-block-tag-clouds',
        'render_callback' => 'st_tag_clouds_render',
    ]);

    $shortcode_page = sprintf(
        '<a href="%s">%s</a>',
        add_query_arg(
            [
                'page'               => 'st_terms_display',
            ],
            admin_url('admin.php')
        ),
        esc_html__('this page.', 'simple-tags')
    );

    $select_label = __('Select Terms Display', 'simple-tags');
    $panel_title  = __('Terms Display (TaxoPress)', 'simple-tags');

    wp_localize_script('st-block-tag-clouds', 'ST_TAG_CLOUDS', [
        'options'      => $options,
        'panel_title'  => $panel_title,
        'select_label' => $select_label,
    ]);
}

add_action('init', 'st_tag_clouds_block_init');

/**
 * Our combined block and shortcode renderer.
 *
 * @param array $attributes The attributes that were set on the block or shortcode.
 */
function st_tag_clouds_render($attributes)
{
    global $post;
    ob_start();
    echo do_shortcode('[taxopress_termsdisplay id="' . $attributes['tagcloud_id'] . '"]');

    return ob_get_clean();
}
