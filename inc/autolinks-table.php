<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Autolinks_List extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => esc_html__('Auto Link', 'simple-tags'), //singular name of the listed records
            'plural'   => esc_html__('Auto Links', 'simple-tags'), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ]);
    }

    /**
     * Retrieve st_autolinks data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_st_autolinks()
    {
        return taxopress_get_autolink_data();
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        return count(taxopress_get_autolink_data());
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-autolink-tr'];
        $id    = 'st-autolink-' . md5($item['ID']);
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
            'title'     => __('Title', 'simple-tags'),
            'taxonomy'  => __('Taxonomy', 'simple-tags'),
            'embedded'  => __('Auto Link Post type', 'simple-tags'),
            'autolink_display'  => __('Auto Link areas', 'simple-tags')
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
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
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
        $per_page = $this->get_items_per_page('st_autolinks_per_page', 20);

        /**
         * Fetch the data
         */
        $data = self::get_st_autolinks();

        /**
         * Handle search
         */
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
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'ID'; //If no sort, default to role
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
                        'page'                   => 'st_autolinks',
                        'add'                    => 'new_item',
                        'action'                 => 'edit',
                        'taxopress_autolinks' => $item['ID'],
                    ],
                    admin_url('admin.php')
                ),
                __('Edit', 'simple-tags')
            ),
            'delete' => sprintf(
                '<a href="%s" class="delete-autolink">%s</a>',
                add_query_arg(
                    [
                    'page'                   => 'st_autolinks',
                    'action'                 => 'taxopress-delete-autolink',
                    'taxopress_autolinks' => esc_attr($item['ID']),
                    '_wpnonce'               => wp_create_nonce('autolink-action-request-nonce')
                    ],
                    admin_url('admin.php')
                ),
                __('Delete', 'simple-tags')
            ),
        ];

        $actions = apply_filters('taxopress_autolink_row_actions', $actions, $item);
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
                    'page'                   => 'st_autolinks',
                    'add'                    => 'new_item',
                    'action'                 => 'edit',
                    'taxopress_autolinks' => $item['ID'],
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
                'homeonly'   => esc_attr__('Homepage', 'simple-tags'),
                'blogonly'   => esc_attr__('Blog display', 'simple-tags'),
                'singleonly' => esc_attr__('Single post display', 'simple-tags'),
                'feed'       => esc_attr__('RSS feed', 'simple-tags'),
            ];
            foreach ($post_types as $post_type) {
                $embedded_options[$post_type->name] = $post_type->label;
            }
            foreach ($embedded as $location) {
                $result_array[] = isset($embedded_options[$location]) ? $embedded_options[$location] : '';
            }
            $result = join(', ', $result_array);
        } else {
            $result = esc_attr__('None', 'simple-tags');
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
    protected function column_autolink_display($item)
    {
        $autolink_display_options = [
            'post_content'   => esc_attr__('Post Content', 'simple-tags'),
            'post_title'   => esc_attr__('Post Title', 'simple-tags'),
            'posts' => esc_attr__('Post Content and Title', 'simple-tags'),
        ];

        return $autolink_display_options[$item['autolink_display']];
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

        return '<input readonly type="text" value=\'[taxopress_autolinks id="' . $item['ID'] . '"]\' />';
    }
}
