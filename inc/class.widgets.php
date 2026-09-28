<?php

// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore,Squiz.PHP.CommentedOutCode.Found -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

/**
 * TaxoPress widget class
 *
 */
class SimpleTags_Widget extends WP_Widget
{
    /**
     * Constructor widget
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {
        parent::__construct(
            'simpletags',
            esc_html__('Tag Cloud (TaxoPress Legacy)', 'simple-tags'),
            array(
                'classname'   => 'widget-simpletags',
                'description' => esc_html__('[DEPRECATED] - Your most used tags in cloud format with dynamic color and many options', 'simple-tags')
            )
        );
    }

    /**
     * Check if taxonomy exist and return it, otherwise return default post tags.
     *
     * @param array $instance
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function _get_current_taxonomy($instance)
    {
        if (! empty($instance['taxonomy']) && taxonomy_exists($instance['taxonomy'])) {
            return $instance['taxonomy'];
        }

        return 'post_tag';
    }

    /**
     * Default settings for widget
     *
     * @return array
     * @author WebFactory Ltd
     */
    public static function get_fields()
    {
        return array(
            'taxonomy'    => 'post_tag',
            'title'       => esc_html__('Tag cloud', 'simple-tags'),
            'max'         => 45,
            'selectionby' => 'count',
            'selection'   => 'desc',
            'orderby'     => 'random',
            'order'       => 'asc',
            'smini'       => 8,
            'smax'        => 22,
            'unit'        => 'pt',
            'format'      => 'flat',
            'color'       => 1,
            'cmini'       => '#CCCCCC',
            'cmax'        => '#000000',
            'xformat'     => '',
            'adv_usage'   => ''
        );
    }

    /**
     * Method for theme render
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function widget($args, $instance)
    {
        extract($args);

        $current_taxonomy = self::_get_current_taxonomy($instance);

        // Build or not the name of the widget
        if (! empty($instance['title'])) {
            $title = $instance['title'];
        } else {
            if ('post_tag' == $current_taxonomy) {
                $title = esc_html__('Tags', 'simple-tags');
            } else {
                $tax = get_taxonomy($current_taxonomy);
                if (isset($tax->labels)) {
                    $title = $tax->labels->name;
                } else {
                    $title = $tax->name;
                }
            }
        }
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        $title = esc_html($title);

        // Set values and clean it
        foreach ((array) self::get_fields() as $field => $field_value) {
            ${$field} = isset($instance[$field]) ? esc_html(trim($instance[$field])) : '';
        }

        $param = '';

        // Selection
        $param .= (! empty($selectionby)) ? '&selectionby=' . $selectionby : '&selectionby=count';
        $param .= (! empty($selection)) ? '&selection=' . $selection : '&selection=desc';

        // Order
        $param .= (! empty($orderby)) ? '&orderby=' . $orderby : '&orderby=random';
        $param .= (! empty($order)) ? '&order=' . $order : '&order=asc';

        // Max tags
        if ((int) $max != 0) {
            $param .= '&number=' . $max;
        }

        // Size Mini
        if ((int) $smini != 0) {
            $param .= '&smallest=' . $smini;
        }

        // Size Maxi
        if ((int) $smax != 0) {
            $param .= '&largest=' . $smax;
        }

        // Unit
        if (! empty($unit)) {
            $param .= '&unit=' . $unit;
        }

        // Format
        if (! empty($format)) {
            $param .= '&format=' . $format;
        }

        // Use color ?
        if ((int) $color == 0) {
            $param .= '&color=false';
        }

        // Color mini
        if (! empty($cmini)) {
            $param .= '&mincolor=' . $cmini;
        }

        // Color Max
        if (! empty($cmax)) {
            $param .= '&maxcolor=' . $cmax;
        }

        // Xformat
        if (! empty($xformat)) {
            $param .= '&xformat=' . $xformat;
        }

        // Advanced usage
        if (! empty($adv_usage)) {
            if (substr($adv_usage, 0, 1) != '&') {
                $param .= '&';
            }

            $param .= $adv_usage;
        }


        // Taxonomy
        $param .= '&taxonomy=' . $current_taxonomy;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $before_widget;
        if (! empty($title)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $before_title . $title . $after_title;
        }
        st_tag_cloud(apply_filters('simple-tags-widget', 'title=' . $param, $instance)); // Use Widgets title and no ST title !!
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
     * @author WebFactory Ltd
     */
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;

        foreach ((array) self::get_fields() as $field => $field_value) {
            $instance[ $field ] = sanitize_text_field($new_instance[ $field ]);
        }

        return $instance;
    }

    /**
     * Admin form for widgets
     *
     * @param array $instance
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function form($instance)
    {
        //Defaults
        $instance = wp_parse_args((array) $instance, self::get_fields());
        ?>
        <p style="color:red;"><?php esc_html_e('This widget is no longer being updated. Please use the "Terms Display (TaxoPress Shortcode)" widget instead.', 'simple-tags'); ?></p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'simple-tags'); ?>
                <input class="widefat" type="text" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                       value="<?php echo esc_attr($instance['title']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('max')); ?>">
                <?php esc_html_e('Max tags to display: (default: 45)', 'simple-tags'); ?>
                <input class="widefat" size="20" type="text" id="<?php echo esc_attr($this->get_field_id('max')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('max')); ?>"
                       value="<?php echo esc_attr($instance['max']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('selectionby')); ?>">
                <?php esc_html_e('Order by for DB selection tags:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('selectionby')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('selectionby')); ?>">
                    <option <?php selected(esc_attr($instance['selectionby']), 'name'); ?>
                        value="name"><?php esc_html_e('Name', 'simple-tags'); ?></option>
                    <option <?php selected($instance['selectionby'], 'slug'); ?>
                        value="slug"><?php esc_html_e('Slug', 'simple-tags'); ?></option>
                    <option <?php selected($instance['selectionby'], 'term_group'); ?>
                        value="term_group"><?php esc_html_e('Term group', 'simple-tags'); ?></option>
                    <option <?php selected($instance['selectionby'], 'count'); ?>
                        value="count"><?php esc_html_e('Counter (default)', 'simple-tags'); ?></option>
                    <option <?php selected($instance['selectionby'], 'random'); ?>
                        value="random"><?php esc_html_e('Random', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('selection')); ?>">
                <?php esc_html_e('Order for DB selection tags:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('selection')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('selection')); ?>">
                    <option <?php selected($instance['selection'], 'asc'); ?>
                        value="asc"><?php esc_html_e('ASC', 'simple-tags'); ?></option>
                    <option <?php selected($instance['selection'], 'desc'); ?>
                        value="desc"><?php esc_html_e('DESC (default)', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('orderby')); ?>">
                <?php esc_html_e('Order by for display tags:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('orderby')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('orderby')); ?>">
                    <option <?php selected(esc_attr($instance['orderby']), 'name'); ?>
                        value="name"><?php esc_html_e('Name', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['orderby']), 'count'); ?>
                        value="count"><?php esc_html_e('Counter', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['orderby']), 'random'); ?>
                        value="random"><?php esc_html_e('Random (default)', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('order')); ?>">
                <?php esc_html_e('Order for display tags:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('order')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('order')); ?>">
                    <option <?php selected(esc_attr($instance['order']), 'asc'); ?>
                        value="asc"><?php esc_html_e('ASC', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['order']), 'desc'); ?>
                        value="desc"><?php esc_html_e('DESC (default)', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('smini')); ?>">
                <?php esc_html_e('Font size mini: (default: 8)', 'simple-tags'); ?>
                <input class="widefat" size="20" type="text" id="<?php echo esc_attr($this->get_field_id('smini')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('smini')); ?>"
                       value="<?php echo esc_attr($instance['smini']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('smax')); ?>">
                <?php esc_html_e('Font size max: (default: 22)', 'simple-tags'); ?>
                <input class="widefat" size="20" type="text" id="<?php echo esc_attr($this->get_field_id('smax')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('smax')); ?>"
                       value="<?php echo esc_attr($instance['smax']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('unit')); ?>">
                <?php esc_html_e('Unit font size:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('unit')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('unit')); ?>">
                    <option <?php selected(esc_attr($instance['unit']), 'pt'); ?>
                        value="pt"><?php esc_html_e('Point (default)', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['unit']), 'px'); ?>
                        value="px"><?php esc_html_e('Pixel', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['unit']), 'em'); ?>
                        value="em"><?php esc_html_e('Em', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['unit']), '%'); ?>
                        value="%"><?php esc_html_e('Pourcent', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('format')); ?>">
                <?php esc_html_e('Format:', 'simple-tags'); ?>
                <select id="<?php echo esc_attr($this->get_field_id('format')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('format')); ?>">
                    <option <?php selected(esc_attr($instance['format']), 'flat'); ?>
                        value="flat"><?php esc_html_e('Flat (default)', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['format']), 'list'); ?>
                        value="list"><?php esc_html_e('List (UL/LI)', 'simple-tags'); ?></option>
                    <option <?php selected(esc_attr($instance['format']), 'ol'); ?>
                        value="list"><?php esc_html_e('Ordinary List (OL/LI)', 'simple-tags'); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('color')); ?>">
                <input class="checkbox" type="checkbox" id="<?php echo esc_attr($this->get_field_id('color')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('color')); ?>" <?php checked((int) $instance['color'], 1); ?>
                       value="1"/>
                <?php esc_html_e('Use auto color cloud:', 'simple-tags'); ?>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('cmini')); ?>">
                <?php esc_html_e('Font color mini: (default: #CCCCCC)', 'simple-tags'); ?>
                <input class="widefat" type="text" id="<?php echo esc_attr($this->get_field_id('cmini')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('cmini')); ?>"
                       value="<?php echo esc_attr($instance['cmini']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('cmax')); ?>">
                <?php esc_html_e('Font color max: (default: #000000)', 'simple-tags'); ?>
                <input class="widefat" type="text" id="<?php echo esc_attr($this->get_field_id('cmax')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('cmax')); ?>"
                       value="<?php echo esc_attr($instance['cmax']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('xformat')); ?>">
                <?php esc_html_e('Tag link format:', 'simple-tags'); ?><br/>
                <input class="widefat" style="width: 100% !important;" type="text"
                       id="<?php echo esc_attr($this->get_field_id('xformat')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('xformat')); ?>"
                       value="<?php echo esc_attr($instance['xformat']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('adv_usage')); ?>">
                <?php esc_html_e('Advanced usage:', 'simple-tags'); ?><br/>
                <input class="adv_usage" style="width: 100% !important;" type="text"
                       id="<?php echo esc_attr($this->get_field_id('adv_usage')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('adv_usage')); ?>"
                       value="<?php echo esc_attr($instance['adv_usage']); ?>"/>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('taxonomy')); ?>">
                <?php esc_html_e("What to show", 'simple-tags'); ?><br/>
                <select id="<?php echo esc_attr($this->get_field_id('taxonomy')); ?>"
                        name="<?php echo esc_attr($this->get_field_name('taxonomy')); ?>" style="width:100%;">
                    <?php
                    foreach (get_object_taxonomies('post') as $_taxonomy) {
                        $tax = get_taxonomy($_taxonomy);
                        if (! $tax->show_tagcloud || empty($tax->labels->name)) {
                            continue;
                        }

                        echo '<option ' . selected(esc_attr($instance['taxonomy']), esc_attr($tax->name), false) . ' value="' . esc_attr($tax->name) . '">' . esc_html($tax->labels->name) . '</option>';
                    }
                    ?>
                </select>
            </label>
        </p>
        <?php
    }
}
