<?php

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Security.NonceVerification.Recommended -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Taxonomy_List extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => __('Taxonomy', 'simple-tags'), //singular name of the listed records
            'plural'   => __('Taxonomies', 'simple-tags'), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ]);
    }

    /**
     * Retrieve st_taxonomies data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_st_taxonomies()
    {
        return get_all_taxopress_taxonomies_request();
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        return count(get_all_taxopress_taxonomies_request());
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-tax-tr'];
        $id    = 'st-tax-' . md5($item->name);
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
            'cb'          => '',
            'name'        => __('Name', 'simple-tags'),
            'registration_key' => __('Registration key', 'simple-tags'),
            'description' => __('Description', 'simple-tags'),
            'active'      => __('Active', 'simple-tags'),
            'posttypes'   => __('Post Types', 'simple-tags'),
            'count'   => __('Count', 'simple-tags'),
            'taxopress_order' => __('Order', 'simple-tags'),
            'edited_with_taxopress'  => __('Edited', 'simple-tags'),
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
        return !empty($item->$column_name) ? $item->$column_name : '&mdash;';
    }

    /**
     * The checkbox column
     *
     * @param object $item
     *
     * @return string|void
     */
    protected function column_cb($item)
    {

        return '';
    }

    /** Text displayed when no stterm data is available */
    public function no_items()
    {
        _e('No taxonomies found.', 'simple-tags');
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
        if (!empty($_REQUEST['taxonomy_type'])) {
            echo '<input type="hidden" name="taxonomy_type" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['taxonomy_type']))) . '" />';
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
        $per_page = $this->get_items_per_page('st_taxonomies_per_page', 20);

        /**
         * Fetch the data
         */
        $data = self::get_st_taxonomies();

        /**
         * Filter by post type if requested
         */
        $selected_post_type = isset($_GET['taxopress_taxonomy_post_type'])
            ? sanitize_key(wp_unslash($_GET['taxopress_taxonomy_post_type']))
            : '';

        if (!empty($selected_post_type) && 'all' !== $selected_post_type) {
            $data_filtered = [];
            foreach ($data as $item) {
                if (!empty($item->object_type) && is_array($item->object_type)) {
                    if (in_array($selected_post_type, $item->object_type, true)) {
                        $data_filtered[] = $item;
                    }
                }
            }
            $data = $data_filtered;
        }

        /**
         * Handle search
         */
        if ((!empty($_REQUEST['s'])) && $search = sanitize_text_field(wp_unslash($_REQUEST['s']))) {
            $data_filtered = [];
            foreach ($data as $item) {
                if (
                    $this->str_contains($item->label, $search, false) || $this->str_contains(
                        $item->name,
                        $search,
                        false
                    )
                ) {
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
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'label'; //If no sort, default to role
            $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'asc'; //If no order, default to asc
            $result  = strnatcasecmp(
                $a->$orderby,
                $b->$orderby
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
            'name'        => ['label', true],
            'description' => ['description', true],
            'registration_key' => ['name', true],
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
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page'               => 'st_taxonomies',
                        'add'                => 'taxonomy',
                        'action'             => 'edit',
                        'taxopress_taxonomy' => $item->name,
                    ],
                    taxopress_admin_url('admin.php')
                ),
                __('Edit', 'simple-tags')
            ),
            'view' => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'taxonomy' => $item->name,
                    ],
                    taxopress_admin_url('edit-tags.php')
                ),
                __('View', 'simple-tags')
            ),
        ];

        return $column_name === $primary ? $this->row_actions($actions, false) : '';
    }

    /**
     * Method for name column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_name($item)
    {
        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
            add_query_arg(
                [
                    'page'               => 'st_taxonomies',
                    'add'                => 'taxonomy',
                    'action'             => 'edit',
                    'taxopress_taxonomy' => $item->name,
                ],
                taxopress_admin_url('admin.php')
            ),
            esc_html($item->label)
        );

        return $title;
    }

    /**
     * Method for registration_key column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_registration_key($item)
    {
        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
            add_query_arg(
                [
                    'page'               => 'st_taxonomies',
                    'add'                => 'taxonomy',
                    'action'             => 'edit',
                    'taxopress_taxonomy' => $item->name,
                ],
                taxopress_admin_url('admin.php')
            ),
            esc_html($item->name)
        );

        return $title;
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_description($item)
    {
        if (in_array($item->name, ['category', 'post_tag'])) {
            return __('WordPress core', 'simple-tags');
        } else {
            if ($item->name === 'media_tag' || array_key_exists($item->name, taxopress_get_taxonomy_data())) {
                $alt_description = __('TaxoPress', 'simple-tags');
            } else {
                $alt_description = '&mdash;';
            }
            return !empty($item->description) ? esc_html($item->description) : $alt_description;
        }
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_active($item)
    {

        if (in_array($item->name, taxopress_get_deactivated_taxonomy())) {
            return __('No', 'simple-tags');
        } else {
            return __('Yes', 'simple-tags');
        }
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_posttypes($item)
    {
        $posttype = '';
        $sn       = 0;
        foreach ($item->object_type as $objecttype) {
            $sn++;
            $post_type_object = get_post_type_object($objecttype);
            if (is_object($post_type_object)) {
                $posttype .= $post_type_object->label;
                if ($sn < count($item->object_type)) {
                    $posttype .= ', ';
                }
            }
        }

        return $posttype;
    }

    /**
     * Method for count column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_count($item)
    {
        $terms = get_terms(array('taxonomy' => $item->name, 'hide_empty' => false, 'fields' => 'ids'));
        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
            add_query_arg(
                [
                    'page'     => 'st_terms',
                    'terms_filter_taxonomy' => $item->name
                ],
                taxopress_admin_url('admin.php')
            ),
            count($terms)
        );

        return $title;
    }

    /**
     * The edited_with_taxopress column
     *
     * @param object $item
     * @return string
     */
    protected function column_edited_with_taxopress($item)
    {
        $external_taxonomies = get_option('taxopress_external_taxonomies', array());
        $taxopress_taxonomies = taxopress_get_taxonomy_data();

        if ($item->name === 'media_tag' || array_key_exists($item->name, $taxopress_taxonomies) || array_key_exists($item->name, $external_taxonomies)) {
            return '<div class="pp-tooltips-library" data-toggle="tooltip">
                    <span class="dashicons dashicons-yes-alt taxopress-edited-indicator taxopress-edited-yes"></span>
                    <div class="taxopress tooltip-text">This taxonomy has been edited with TaxoPress</div>
                    </div>';
        } else {
            return '<div class="pp-tooltips-library" data-toggle="tooltip">
                    <span class="dashicons dashicons-no-alt taxopress-edited-indicator taxopress-edited-no"></span>
                    <div class="taxopress tooltip-text">This taxonomy has not been edited with TaxoPress</div>
                    </div>';
        }
    }

    protected function column_taxopress_order($item)
    {
        echo apply_filters('taxopress_order_column', $item);
    }
}
