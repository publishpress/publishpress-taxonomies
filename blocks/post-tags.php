<?php

/**
 * Register our block.
 */
function st_post_tags_block_init()
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
        'st-block-post-tags',
        STAGS_URL . '/blocks/src/post-tags.js',
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-editor']
    );

    // Register our block, and explicitly define the attributes we accept.
    $posttags_data = taxopress_get_posttags_data();

    $options = [];
    $default = '';
    if (count($posttags_data) > 0) {
        $sn = 0;
        foreach ($posttags_data as $key => $value) {
            $sn++;
            if ($sn === 1) {
                $default = $key;
            }
            $options[] = ['label' => $value['title'], 'value' => $key];
        }
    }

    register_block_type('taxopress/post-tags', [
        'attributes'      => [
            'posttags_id' => [
                'type'    => 'string',
                'default' => $default,
            ],
            'post_id'        => [
                'type' => 'string',
            ],
        ],
        'editor_script'   => 'st-block-post-tags',
        'render_callback' => 'st_post_tags_render',
    ]);

    $shortcode_page = sprintf(
        '<a href="%s">%s</a>',
        add_query_arg(
            [
                'page'               => 'st_post_tags',
            ],
            admin_url('admin.php')
        ),
        esc_html__('Here', 'simple-tags')
    );

    $select_label = __(' Select current post', 'simple-tags');
    $panel_title  = __('Current Posts (TaxoPress)', 'simple-tags');

    wp_localize_script('st-block-post-tags', 'ST_POST_TAGS', [
        'options'      => $options,
        'panel_title'  => $panel_title,
        'select_label' => $select_label,
    ]);
}

add_action('init', 'st_post_tags_block_init');

/**
 * Our combined block and shortcode renderer.
 *
 * @param array $attributes The attributes that were set on the block or shortcode.
 */
function st_post_tags_render($attributes)
{
    global $post;
    ob_start();
    echo do_shortcode('[taxopress_postterms id="' . $attributes['posttags_id'] . '"]');

    return ob_get_clean();
}
