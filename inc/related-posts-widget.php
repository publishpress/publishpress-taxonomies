<?php

/**
 * TaxoPress widget class
 *
 */
class SimpleTags_RelatedPosts_Widget extends WP_Widget
{
    /**
     * Constructor widget
     *
     * @return void
     * @author Olatechpro
     */
    public function __construct()
    {
        parent::__construct(
            'simpletags-relatedposts',
            esc_html__('Related Posts (TaxoPress)', 'simple-tags'),
            [
                'classname'   => 'widget-simpletags-relatedposts',
                'description' => esc_html__('Taxopress Related Posts Shortcode', 'simple-tags')
            ]
        );
    }

    /**
     * Default settings for widget
     *
     * @return array
     * @author Olatechpro
     */
    public static function get_fields()
    {
        return [
            'relatedposts_id' => 0,
        ];
    }

    /**
     * Method for theme render
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     * @author Olatechpro
     */
    public function widget($args, $instance)
    {
        extract($args);

        // Set values and clean it
        foreach ((array)self::get_fields() as $field => $field_value) {
            ${$field} = isset($instance[$field]) ? trim($instance[$field]) : '';
        }//$relatedposts_id;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $before_widget;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo do_shortcode('[taxopress_relatedposts id="' . (int)$relatedposts_id . '"]');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $after_widget;
    }

    /**
     * Update widget settings
     *
     * @param array $new_instance
     * @param array $old_instance
     *
     * @return array
     * @author Olatechpro
     */
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;

        foreach ((array)self::get_fields() as $field => $field_value) {
            $instance[$field] = $new_instance[$field];
        }

        return $instance;
    }

    /**
     * Admin form for widgets
     *
     * @param array $instance
     *
     * @return void
     * @author Olatechpro
     */
    public function form($instance)
    {
        //Defaults
        $instance = wp_parse_args((array)$instance, self::get_fields());

        $relatedposts_data = taxopress_get_relatedpost_data();
        if (count($relatedposts_data) > 0) {
            $shortcode_page = sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page' => 'st_related_posts',
                    ],
                    admin_url('admin.php')
                ),
                esc_html__('this page.', 'simple-tags')
            );

            echo '<p>' . esc_html__('Related Posts are added on ', 'simple-tags');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $shortcode_page;
            echo '</p>'

            ?>
            <p>
                <label for="<?php echo esc_attr($this->get_field_id('relatedposts_id')); ?>">
                    <?php esc_html_e('Select widget Related Posts.', 'simple-tags'); ?>
                    <select id="<?php echo esc_attr($this->get_field_id('relatedposts_id')); ?>"
                            name="<?php echo esc_attr($this->get_field_name('relatedposts_id')); ?>">
                        <?php foreach ($relatedposts_data as $key => $value) { ?>
                            <option <?php selected(esc_attr($instance['relatedposts_id']), esc_attr($key)); ?>
                                value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value['title']); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>

            <?php
        } else {
            $shortcode_page = sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page' => 'st_related_posts',
                    ],
                    admin_url('admin.php')
                ),
                esc_html__('Here', 'simple-tags')
            );

            echo '<br />' . esc_html__('No Related Posts shortcode available. Add new shortcode ', 'simple-tags');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $shortcode_page;
            echo '<br /><br />';
        }
    }
}
