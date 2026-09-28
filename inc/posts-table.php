<?php

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Taxopress_Posts_List extends WP_List_Table
{
    /**
     * Current level for output.
     *
     * @since 4.3.0
     * @var int
     */
    protected $current_level = 0;

    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => esc_html__('Post', 'simple-tags'), //singular name of the listed records
            'plural'   => esc_html__('Posts', 'simple-tags'), //plural name of the listed records
            'ajax'     => true //does this table support ajax?
        ]);
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'title'          => esc_html__('Title', 'simple-tags'),
            'taxonomy_terms' => esc_html__('Terms', 'simple-tags'),
            'post_types'     => esc_html__('Post Type', 'simple-tags'),
            'post_status'    => esc_html__('Post Status', 'simple-tags'),
            'author'         => esc_html__('Author', 'simple-tags'),
            'date'           => esc_html__('Date', 'simple-tags')
        ];

        return $columns;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    protected function get_sortable_columns()
    {
        $sortable_columns = [
            'title'       => 'title',
            'date'        => ['date', true],
        ];

        return $sortable_columns;
    }

    public function get_all_posts()
    {

        $search            = (!empty($_REQUEST['s'])) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $term_filter        = (!empty($_REQUEST['posts_term_filter'])) ? (int) $_REQUEST['posts_term_filter'] : '';
        $post_types        = (!empty($_REQUEST['posts_post_type_filter'])) ? sanitize_text_field(wp_unslash($_REQUEST['posts_post_type_filter'])) : '';

        $orderby           = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'date';
        $order             = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'DESC';


        $posts_per_page    = $this->get_items_per_page('st_posts_per_page', 20);

        $page              = $this->get_pagenum();

        if (empty($post_types)) {
            $post_types = get_post_types(array('public' => true), 'names');
            if (isset($post_types['attachment'])) {
                unset($post_types['attachment']);
            }
            $post_types = array_keys($post_types);
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'any',
            'posts_per_page' => $posts_per_page,
            'order' => $order,
            'orderby' => $orderby,
            'paged'          => $page
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        if (!empty($term_filter)) {
            $term_filter_data = get_term($term_filter);
            if ($term_filter_data instanceof WP_Term) {
                $args['tax_query'] = array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => $term_filter_data->taxonomy,
                        'field'     => 'term_id',
                        'terms'    => $term_filter_data->term_id,
                    )
                );
            }
        }

        $query = new WP_Query($args);

        $results = [
            'posts'         => [],
            'max_num_posts' => 0,
            'max_num_pages' => 1
        ];

        if ($query->have_posts()) {
            $results['posts']         = $query->posts;
            $results['max_num_posts'] = $query->found_posts;
            $results['max_num_pages'] = $query->max_num_pages;
            wp_reset_postdata();
        }


        return $results;
    }

    /**
     * Show single row item
     *
     * @param object $post
     */
    public function single_row($post)
    {
        $class = ['st-posts-tr'];
        $id    = 'post-' . $post->post_id . '';
        echo sprintf('<tr id="%s" class="%s">', esc_attr($id), esc_attr(implode(' ', $class)));
        $this->single_row_columns($post);
        echo '</tr>';
    }

    /**
     * Add custom filter to tablenav
     *
     * @param string $which
     */
    protected function extra_tablenav($which)
    {

        if ('top' === $which) {
            $post_types = get_post_types(['public' => true], 'objects');

            $posts_term_filter = (!empty($_REQUEST['posts_term_filter'])) ? (int) $_REQUEST['posts_term_filter'] : '';
            $posts_post_type_filter = (!empty($_REQUEST['posts_post_type_filter'])) ? sanitize_text_field(wp_unslash($_REQUEST['posts_post_type_filter'])) : '';
            ?>


            <div class="alignleft actions autoposts-posts-table-filter">
                <select data-nonce="<?php echo esc_attr(wp_create_nonce('taxopress-term-search')); ?>"
                    class="posts-term-filter-select taxopress-select2 taxopress-term-search"
                    data-placeholder="<?php esc_attr_e('Terms Filter', 'simple-tags'); ?>"
                    name="posts_term_filter"
                    id="posts_term_filter"
                    >
                    <option value=""><?php esc_html_e('', 'simple-tags'); ?></option>
                    <?php
                    if (!empty($posts_term_filter)) {
                        $posts_term_filter_data = get_term($posts_term_filter);
                        if (is_object($posts_term_filter_data) && !is_wp_error($posts_term_filter_data) && isset($posts_term_filter_data->term_id)) {
                            echo '<option value="' . esc_attr($posts_term_filter_data->term_id) . '" selected>' . esc_html($posts_term_filter_data->name) . '</option>';
                        }
                    }
                    ?>
                </select>

                <select class="posts-post-type-filter-select taxopress-select2 taxopress-simple-select2"
                    name="posts_post_type_filter"              
                    id="posts_post_type_filter"
                    data-placeholder="<?php esc_attr_e('Post Types Filter', 'simple-tags'); ?>">
                    <option value=""><?php esc_html_e('All Post Types', 'simple-tags'); ?></option>
                    <?php
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($posts_post_type_filter, $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
                    }
                    ?>
                </select>

                <a href="javascript:void(0)" class="taxopress-posts-tablenav-filter button"><?php esc_html_e('Filter', 'simple-tags'); ?></a>

            </div>
            <?php
        }
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param object $post
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($post, $column_name)
    {
        return !empty($post->$column_name) ? $post->$column_name : '&mdash;';
    }

    /** Text displayed when no stpost data is available */
    public function no_items()
    {
        esc_html_e('No posts found.', 'simple-tags');
    }

    /**
     * Displays the search box.
     *
     * @param string $text The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     *
     *
     */
    public function search_box($text, $input_id)
    {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            //return;
        }

        $input_id = $input_id . '-search-input';

        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['orderby']))) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['order']))) . '" />';
        }
        if (!empty($_REQUEST['page'])) {
            echo '<input type="hidden" name="page" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))) . '" />';
        }

        $custom_filters = ['posts_term_filter', 'posts_post_type_filter'];

        foreach ($custom_filters as $custom_filter) {
            $filter_value = !empty($_REQUEST[$custom_filter]) ? sanitize_text_field(wp_unslash($_REQUEST[$custom_filter])) : '';
            echo '<input type="hidden" name="' . esc_attr($custom_filter) . '" value="' . esc_attr($filter_value) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button($text, '', '', false, ['id' => 'taxopress-posts-search-submit']); ?>
        </p>
        <?php
    }

    /**
     * Sets up the items (roles) to list.
     */
    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();
        $this->process_bulk_action();

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = $this->get_items_per_page('st_posts_per_page', 20);

        /**
         * Fetch the data
         */
        $posts_data = $this->get_all_posts();
        $data = $posts_data['posts'];

        /**
         * Pagination.
         */
        $current_page = $this->get_pagenum();
        $total_items  = $posts_data['max_num_posts'];

        /**
         * Now we can add the data to the items property, where it can be used by the rest of the class.
         */
        $this->items = $data;

        /**
         * We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args([
            'total_items' => $total_items,                      //calculate the total number of items
            'per_page'    => $per_page,                         //depostine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //calculate the total number of pages
        ]);
    }

    /**
     * Method for name column
     *
     * @param object $post
     *
     * @return string
     */
    protected function column_title($post)
    {

        if (current_user_can('edit_post', $post->ID)) {
            $title = sprintf(
                '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
                add_query_arg(
                    [
                        'post' => $post->ID,
                        'action' => 'edit'
                    ],
                    admin_url('post.php')
                ),
                esc_html(get_the_title($post))
            );
        } else {
            $title = '<strong><span class="row-title">' . get_the_title($post) . '</span></strong>';
        }

        return $title;
    }

    /**
     * The action column
     *
     * @param $post
     *
     * @return string
     */
    protected function column_author($post)
    {

        if (function_exists(('get_post_authors'))) {
            $authors = get_post_authors($post->ID, true);
            $author_name = [];
            foreach ($authors as $author) {
                if (is_object($author) && isset($author->display_name)) {
                    $author_name[] = $author->display_name;
                }
            }
            $out = join(', ', $author_name);
        } else {
            $out = get_the_author_meta('display_name', $post->post_author);
        }

        return $out;
    }

    /**
     * The action column
     *
     * @param $post
     *
     * @return string
     */
    protected function column_post_types($post)
    {
        $post_type = $post->post_type;
        $post_type_object = get_post_type_object($post->post_type);
        if (is_object($post_type_object)) {
            $post_type = $post_type_object->label;
        }

        $out = '<a href="' . $this->update_or_add_url_parameter('posts_post_type_filter', $post->post_type) . '">' . $post_type . '</a>';

        return $out;
    }

    /**
     * The action column
     *
     * @param $post
     *
     * @return string
     */
    protected function column_taxonomy_terms($post)
    {
        $out = '';

        $taxonomy_type = SimpleTags_Plugin::get_option_value('post_terms_taxonomy_type');

        // Get all the taxonomies for the post
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');

        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy_slug => $taxonomy) {
                // Check if the taxonomy should be displayed based on $taxonomy_type
                if ($taxonomy_type === 'public' && $taxonomy->public === false) {
                    continue;
                } elseif ($taxonomy_type === 'private' && $taxonomy->public === true) {
                    continue;
                }

                // Get the assigned terms for each taxonomy
                $terms = get_the_terms($post->ID, $taxonomy_slug);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $out .= '<div class="taxopress-post-taxonomy">';
                    $out .= "<strong>{$taxonomy->labels->name}:</strong> ";
                    $out .= '<span class="taxopress-post-taxonomy-terms">';

                    $term_links = [];
                    foreach ($terms as $term) {
                        $link_label = esc_html(sanitize_term_field('name', $term->name, $term->term_id, $taxonomy_slug, 'display'));
                        $link_url   = $this->update_or_add_url_parameter('posts_term_filter', $term->term_id);
                        $term_links[] = sprintf(
                            '<a href="%s">%s</a>',
                            esc_url($link_url),
                            esc_html($link_label)
                        );
                    }
                    $out .= implode(', ', $term_links);
                    $out .= "</span>";
                    $out .= '</div>';
                }
            }
        }

        return $out;
    }

    protected function update_or_add_url_parameter($param_name, $param_value)
    {

        $url = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));

        // Check if the parameter already exists in the URL
        $existing_param = get_query_var($param_name);

        if ($existing_param) {
            // Update the parameter value
            $url = add_query_arg($param_name, $param_value, $url);
        } else {
            // Add the parameter with its value
            $url = add_query_arg(array($param_name => $param_value), $url);
        }

        return $url;
    }

    /**
     * The action column
     *
     * @param $post
     *
     * @return string
     */
    protected function column_post_status($post)
    {
        $post_status = $post->post_status;
        $post_status_object = get_post_status_object($post->post_status);
        if (is_object($post_status_object)) {
            $post_status = $post_status_object->label;
        }

        $out = $post_status;

        return $out;
    }

    /**
     * Handles the post date column output.
     *
     * @since 4.3.0
     *
     * @global string $mode List table view mode.
     *
     * @param WP_Post $post The current WP_Post object.
     */
    public function column_date($post)
    {

        $mode = get_user_setting('posts_list_mode', 'list');
        if ('0000-00-00 00:00:00' === $post->post_date) {
            $t_time    = __('Unpublished');
            $time_diff = 0;
        } else {
            $t_time = sprintf(
                /* translators: 1: Post date, 2: Post time. */
                __('%1$s at %2$s'),
                /* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
                get_the_time(__('Y/m/d'), $post),
                /* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
                get_the_time(__('g:i a'), $post)
            );

            $time      = get_post_timestamp($post);
            $time_diff = time() - $time;
        }

        if ('publish' === $post->post_status) {
            $status = __('Published');
        } elseif ('future' === $post->post_status) {
            if ($time_diff > 0) {
                $status = '<strong class="error-message">' . __('Missed schedule') . '</strong>';
            } else {
                $status = __('Scheduled');
            }
        } else {
            $status = __('Last Modified');
        }

        /**
         * Filters the status text of the post.
         *
         * @since 4.8.0
         *
         * @param string  $status      The status text.
         * @param WP_Post $post        Post object.
         * @param string  $column_name The column name.
         * @param string  $mode        The list display mode ('excerpt' or 'list').
         */
        $status = apply_filters('post_date_column_status', $status, $post, 'date', $mode);

        if ($status) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $status . '<br />';
        }

        /**
         * Filters the published time of the post.
         *
         * @since 2.5.1
         * @since 5.5.0 Removed the difference between 'excerpt' and 'list' modes.
         *              The published time and date are both displayed now,
         *              which is equivalent to the previous 'excerpt' mode.
         *
         * @param string  $t_time      The published time.
         * @param WP_Post $post        Post object.
         * @param string  $column_name The column name.
         * @param string  $mode        The list display mode ('excerpt' or 'list').
         */
        echo apply_filters('post_date_column_time', $t_time, $post, 'date', $mode); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
