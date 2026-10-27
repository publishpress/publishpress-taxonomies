<?php

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class RelatedPosts_List extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' =>  esc_html__('Related Post', 'simple-tags'), //singular name of the listed records
            'plural'   =>  esc_html__('Related Posts', 'simple-tags'), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ]);
    }

    /**
     * Retrieve st_related_posts data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_st_related_posts()
    {
        return taxopress_get_relatedpost_data();
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        return count(taxopress_get_relatedpost_data());
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-relatedpost-tr'];
        $id    = 'st-relatedpost-' . md5($item['ID']);
        echo sprintf('<tr id="%s" class="%s">', esc_attr($id), esc_attr(implode(' ', $class)));
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'title'     =>  esc_html__('Title', 'simple-tags'),
            'taxonomy'  =>  esc_html__('Taxonomy', 'simple-tags'),
            'post_type' =>  esc_html__('Post Types', 'simple-tags'),
            'embedded'  =>  esc_html__('Automatic display', 'simple-tags'),
            'shortcode' =>  esc_html__('Shortcode', 'simple-tags')
        ];

        return $columns;
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        return !empty($item[$column_name]) ? $item[$column_name] : '&mdash;';
    }

    /** Text displayed when no stterm data is available */
    public function no_items()
    {
        _e('No item avaliable.', 'simple-tags');
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_REQUEST['orderby'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['orderby']))) . '" />';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_REQUEST['order'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<input type="hidden" name="order" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['order']))) . '" />';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_REQUEST['page'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<input type="hidden" name="page" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['page']))) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s"
                   value="<?php _admin_search_query(); ?>"/>
            <?php submit_button($text, '', '', false, ['id' => 'search-submit']); ?>
        </p>
        <?php
    }

    /**
     * Sets up the items (roles) to list.
     */
    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = $this->get_items_per_page('st_related_posts_per_page', 20);

        /**
         * Fetch the data
         */
        $data = self::get_st_related_posts();

        /**
         * Handle search
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ((!empty($_REQUEST['s'])) && $search = sanitize_text_field(wp_unslash($_REQUEST['s']))) {
            $data_filtered = [];
            foreach ($data as $item) {
                if ($this->str_contains($item['title'], $search, false)) {
                    $data_filtered[] = $item;
                }
            }
            $data = $data_filtered;
        }

        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         */
        function usort_reorder($a, $b)
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'ID'; //If no sort, default to role
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'desc'; //If no order, default to asc
            $result  = strnatcasecmp(
                $a[$orderby],
                $b[$orderby]
            ); //Determine sort order, case insensitive, natural order

            return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
        }

        usort($data, 'usort_reorder');

        /**
         * Pagination.
         */
        $current_page = $this->get_pagenum();
        $total_items  = count($data);


        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to
         */
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);

        /**
         * Now we can add the data to the items property, where it can be used by the rest of the class.
         */
        $this->items = $data;

        /**
         * We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args([
            'total_items' => $total_items,                      //calculate the total number of items
            'per_page'    => $per_page,                         //determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //calculate the total number of pages
        ]);
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string $haystack
     * @param string|array $needles
     * @param bool $sensitive Use case sensitive search
     *
     * @return bool
     */
    public function str_contains($haystack, $needles, $sensitive = true)
    {
        foreach ((array)$needles as $needle) {
            $function = $sensitive ? 'mb_strpos' : 'mb_stripos';
            if ($needle !== '' && $function($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    protected function get_sortable_columns()
    {
        $sortable_columns = [
            'title'    => ['title', true],
            'taxonomy' => ['taxonomy', true],
        ];

        return $sortable_columns;
    }

    /**
     * Generates and display row actions links for the list table.
     *
     * @param object $item The item being acted upon.
     * @param string $column_name Current column name.
     * @param string $primary Primary column name.
     *
     * @return string The row actions HTML, or an empty string if the current column is the primary column.
     */
    protected function handle_row_actions($item, $column_name, $primary)
    {
        //Build row actions
        $actions = [
            'edit'   => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page'                   => 'st_related_posts',
                        'add'                    => 'new_item',
                        'action'                 => 'edit',
                        'taxopress_relatedposts' => $item['ID'],
                    ],
                    admin_url('admin.php')
                ),
                esc_html__('Edit', 'simple-tags')
            ),
            'delete' => sprintf(
                '<a href="%s" class="delete-relatedpost">%s</a>',
                add_query_arg(
                    [
                    'page'                   => 'st_related_posts',
                    'action'                 => 'taxopress-delete-relatedpost',
                    'taxopress_relatedposts' => esc_attr($item['ID']),
                    '_wpnonce'               => wp_create_nonce('relatedpost-action-request-nonce')
                    ],
                    admin_url('admin.php')
                ),
                esc_html__('Delete', 'simple-tags')
            ),
        ];

        $actions = apply_filters('taxopress_relatedpost_row_actions', $actions, $item);
        return $column_name === $primary ? $this->row_actions($actions, false) : '';
    }

    /**
     * Method for title column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_title($item)
    {
        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
            add_query_arg(
                [
                    'page'                   => 'st_related_posts',
                    'add'                    => 'new_item',
                    'action'                 => 'edit',
                    'taxopress_relatedposts' => $item['ID'],
                ],
                admin_url('admin.php')
            ),
            esc_html($item['title'])
        );

        return $title;
    }

    /**
     * Method for taxonomy column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_taxonomy($item)
    {
        $taxonomy = get_taxonomy($item['taxonomy']);

        return $taxonomy->labels->name;
    }

    /**
     * Method for post_type column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_post_type($item)
    {
        $title = '';
        if (isset($item['post_type']) && !empty(trim($item['post_type']))) {
            if ($item['post_type'] === 'st_current_posttype') {
                $title =  esc_html__('Current post type', 'simple-tags');
            } elseif ($item['post_type'] === 'st_all_posttype') {
                $title =  esc_html__('All', 'simple-tags');
            } else {
                $post_type = get_post_type_object($item['post_type']);
                if (is_object($post_type)) {
                    $title = sprintf(
                        '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
                        add_query_arg(
                            [
                                'post_type' => $item['post_type'],
                            ],
                            admin_url('edit.php')
                        ),
                        esc_html($post_type->label)
                    );
                }
            }
        } elseif (isset($item['post_types']) && !empty($item['post_types'])) {
            $result_array     = [];
            foreach ($item['post_types'] as $post_type_name) {
                $post_type = get_post_type_object($post_type_name);
                if (is_object($post_type)) {
                    $result_array[] = sprintf(
                        '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
                        add_query_arg(
                            [
                                'post_type' => $post_type_name,
                            ],
                            admin_url('edit.php')
                        ),
                        esc_html($post_type->label)
                    );
                }
            }
            $title = join(', ', $result_array);
        } else {
            $title =  esc_html__('Default', 'simple-tags');
        }

        return $title;
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_embedded($item)
    {
        $embedded = (isset($item['embedded']) && is_array($item['embedded']) && count($item['embedded']) > 0) ? $item['embedded'] : false;
        if ($embedded) {
            $args = apply_filters('taxopress_attach_post_types_to_taxonomy', ['public' => true]);
            if (!is_array($args)) {
                $args = ['public' => true];
            }
            $output     = 'objects'; // Or objects.
            $post_types = apply_filters(
                'taxopress_get_post_types_for_taxonomies',
                get_post_types($args, $output),
                $args,
                $output
            );

            $result_array     = [];
            $embedded_options = [
                'homeonly'   => esc_html__('Homepage', 'simple-tags'),
                'blogonly'   => esc_html__('Blog display', 'simple-tags'),
                'singleonly' => esc_html__('Single post display', 'simple-tags'),
                'feed'       => esc_html__('RSS feed', 'simple-tags'),
            ];
            foreach ($post_types as $post_type) {
                $embedded_options[$post_type->name] = $post_type->label;
            }
            foreach ($embedded as $location) {
                $result_array[] = isset($embedded_options[$location]) ? $embedded_options[$location] : '';
            }
            $result = join(', ', $result_array);
        } else {
            $result = esc_html__('No', 'simple-tags');
        }

        return $result;
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_shortcode($item)
    {

        return '<input readonly type="text" value=\'[taxopress_relatedposts id="' . $item['ID'] . '"]\' />';
    }
}
