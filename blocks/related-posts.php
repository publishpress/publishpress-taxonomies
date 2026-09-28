<?php

/**
 * Register our block.
 */
function st_related_posts_block_init()
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
        'st-block-related-posts',
        STAGS_URL . '/blocks/src/related-posts.js',
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-editor']
    );

    // Register and enqueue frontend styles for block editor
    wp_register_style(
        'taxopress-frontend-css',
        STAGS_URL . '/assets/frontend/css/frontend.css',
        [],
        STAGS_VERSION,
        'all'
    );
    add_action('enqueue_block_editor_assets', function () {
        if (is_admin() && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->is_block_editor()) {
                wp_enqueue_style('taxopress-frontend-css');
            }
        }
    });

    // Register our block, and explicitly define the attributes we accept.
    $relatedposts_data = taxopress_get_relatedpost_data();

    $options = [];
    $default = '';
    if (count($relatedposts_data) > 0) {
        $sn = 0;
        foreach ($relatedposts_data as $key => $value) {
            $sn++;
            if ($sn === 1) {
                $default = $key;
            }
            $options[] = ['label' => $value['title'], 'value' => $key];
        }
    }

    register_block_type('taxopress/related-posts', [
        'attributes'      => [
            'relatedpost_id' => [
                'type'    => 'string',
                'default' => $default,
            ],
            'post_id'        => [
                'type' => 'string',
            ],
        ],
        'editor_script'   => 'st-block-related-posts',
        'render_callback' => 'st_related_posts_render',
    ]);

    $shortcode_page = sprintf(
        '<a href="%s">%s</a>',
        add_query_arg(
            [
                'page' => 'st_related_posts',
            ],
            admin_url('admin.php')
        ),
        __('this page.', 'simple-tags')
    );

    $select_label = __('Select related post', 'simple-tags');
    $panel_title  = __('Related Posts (TaxoPress)', 'simple-tags');

    wp_localize_script('st-block-related-posts', 'ST_RELATED_POST', [
        'options'      => $options,
        'panel_title'  => $panel_title,
        'select_label' => $select_label,
    ]);
}

add_action('init', 'st_related_posts_block_init');

/**
 * Our combined block and shortcode renderer.
 *
 * @param array $attributes The attributes that were set on the block or shortcode.
 */
function st_related_posts_render($attributes)
{
    global $post;
    ob_start();

    $relatedpost_id = '';
    if (is_array($attributes) && isset($attributes['relatedpost_id'])) {
        $relatedpost_id = sanitize_text_field($attributes['relatedpost_id']);
    }

    echo do_shortcode('[taxopress_relatedposts id="' . $relatedpost_id . '"]');

    return ob_get_clean();
}
