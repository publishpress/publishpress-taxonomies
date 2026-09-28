<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPressVIPMinimum.Functions.CheckReturnValue.NonCheckedVariable -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Termcloud_List extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => __('Tag Cloud', 'simple-tags'), //singular name of the listed records
            'plural'   => __('Tags Cloud', 'simple-tags'), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ]);
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-tax-tr'];
        $id = 'st-tax-' . md5($item->slug);
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
            'cb'      => '<input type="checkbox" />',
            'name'    => __('Name', 'simple-tags'),
            'slug'    => __('Slug', 'simple-tags'),
            'count'    => __('Count', 'simple-tags')
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
        $sortable_columns = array(
            'name' => array( 'name', true ),
            'slug' => array( 'slug', true ),
            'count' => array( 'count', false )
        );

        return $sortable_columns;
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
     * Retrieve termcloud data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_termcloud()
    {

        $order = '&selectionby=name&selection=asc&orderby=name&order=asc';
        $args = 'hide_empty=false&number=&color=false&get=all&title=' . $order . '&taxonomy=' . SimpleTags_Admin::$taxonomy ;

        $result = SimpleTags_Client_TagCloud::extendedTagResult($args);

        return $result;
    }


    /**
     * Delete a stterm record.
     *
     * @param int $id stterm ID
     */
    public static function delete_stterm($id)
    {
        $term = get_term($id);
        wp_delete_term($term->term_id, $term->taxonomy);
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        $order = '&selectionby=name&selection=asc&orderby=name&order=asc';
        $args = 'hide_empty=false&number=&color=false&get=all&title=' . $order . '&taxonomy=' . SimpleTags_Admin::$taxonomy ;
        $result = SimpleTags_Client_TagCloud::extendedTagResult($args);

        return count($result);
    }


    /** Text displayed when no stterm data is available */
    public function no_items()
    {
        _e('No term avaliable.', 'simple-tags');
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="st-bulk-delete-term[]" value="%s" />',
            $item->term_id
        );
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
        $tag_link = get_term_link($item, $item->taxonomy);
        $title = sprintf(
            '<a href="%1$s" class="tag-cloud-link"><strong><span class="row-title">%2$s</span></strong></a>',
            esc_url($tag_link),
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
    protected function column_slug($item)
    {
        return !empty($item->slug) ? $item->slug : '&mdash;';
    }

    /**
     * The action column
     *
     * @param $item
     *
     * @return string
     */
    protected function column_count($item)
    {
        return sprintf(
            '<a href="%s" class="">%s</a>',
            add_query_arg(
                get_taxonomy($item->taxonomy)->query_var,
                esc_attr($item->slug),
                admin_url('edit.php')
            ),
            number_format_i18n($item->count)
        );
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = [
            'st-bulk-delete-term' => __('Delete', 'simple-tags')
        ];

        return $actions;
    }

    public function process_bulk_action()
    {

        //Detect when a bulk action is being triggered...
        if ('delete' === $this->current_action()) {
            // In our file that handles the request, verify the nonce.
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));

            if (! wp_verify_nonce($nonce, 'sp_delete_stterm')) {
                die('Go get a life script kiddies');
            } else {
                self::delete_stterm(absint(sanitize_text_field(wp_unslash($_GET['stterm']))));
            }
        }

        // If the delete bulk action is triggered
        if (
            (isset($_POST['action']) && $_POST['action'] == 'st-bulk-delete-term')
             || (isset($_POST['action2']) && $_POST['action2'] == 'st-bulk-delete-term')
        ) {
            $delete_ids = array_map('sanitize_text_field', wp_unslash($_POST['st-bulk-delete-term']));

            // loop over the array of record IDs and delete them
            foreach ($delete_ids as $id) {
                self::delete_stterm($id);
            }
        }
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
     * Displays the search box.
     *
     * @param string $text The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     *
     *
     */
    public function search_box($text, $input_id)
    {
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
        if (!empty($_REQUEST['cpt'])) {
            echo '<input type="hidden" name="cpt" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['cpt']))) . '" />';
        }
        if (!empty($_REQUEST['taxo'])) {
            echo '<input type="hidden" name="taxo" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['taxo']))) . '" />';
        }
        $searchbox_search =  (empty($_REQUEST['s']) && !$this->has_items()) ? 'visibility:hidden;' : '';
        ?>
        <p class="search-box" style="<?php echo esc_attr($searchbox_search); ?>">
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
        $per_page = $this->get_items_per_page('termcloud_per_page', 10);

        /**
         * handle bulk actions.
         */
        $this->process_bulk_action();

        /**
         * Fetch the data
         */
        $data = self::get_termcloud();

        /**
         * Handle search
         */
        if ((!empty($_REQUEST['s'])) && $search = sanitize_text_field(wp_unslash($_REQUEST['s']))) {
            $data_filtered = [];
            foreach ($data as $item) {
                if ($this->str_contains($item->slug, $search, false) || $this->str_contains($item->name, $search, false)) {
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
            $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'name'; //If no sort, default to role
            $order = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'asc'; //If no order, default to asc
            $result = strnatcasecmp($a->$orderby, $b->$orderby); //Determine sort order, case insensitive, natural order

            return ($order === 'asc') ? $result : -$result; //Send final sort direction to usort
        }

        usort($data, 'usort_reorder');

        /**
         * Pagination.
         */
        $current_page = $this->get_pagenum();
        $total_items = count($data);


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
            'per_page' => $per_page,                         //determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page)   //calculate the total number of pages
        ]);
    }
}
