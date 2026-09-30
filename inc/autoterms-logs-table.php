<?php

// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Autoterms_Logs extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => __('autotermslog', 'simple-tags'), //singular name of the listed records
            'plural'   => __('autotermslogs', 'simple-tags'), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ]);
    }

    /**
     * Retrieve st_autoterms data from the database
     *
     * @param bool $count_only
     *
     * @return mixed
     */
    public function get_st_autoterms()
    {
        $per_page = $this->get_items_per_page('st_autoterms_logs_per_page', 20);
        $current_page = $this->get_pagenum();

        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'ID'; //If no sort, default to role
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'desc'; //If no order, default to asc


        return taxopress_autoterms_logs_data($per_page, $current_page, $orderby, $order);
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        return get_st_autoterms()['counts'];
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-autoterm-log-tr'];
        $id    = 'st-autoterm-log-' . md5($item->ID);
        echo sprintf('<tr id="%s" class="%s">', esc_attr($id), esc_attr(implode(' ', $class)));
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * Add custom pagination side item
     *
     * @param string $which
     */
    protected function pagination($which)
    {

        parent::pagination($which);

        if ('top' === $which) {
            ?>

           <br class="clear">

            <div class="alignright actions autoterms-log-table-buttons">
                
            </div>
            <?php
        }
    }

    /**
     * Add custom filter to tablenav
     *
     * @param string $which
     */
    protected function extra_tablenav($which)
    {

        if ('top' === $which) {
            $log_actions = [
                'save_posts' => esc_html__('Manual post update', 'simple-tags'),
                'existing_content' => esc_html__('Existing content', 'simple-tags'),
                'daily_cron_schedule' => esc_html__('Scheduled daily cron', 'simple-tags'),
                'hourly_cron_schedule' => esc_html__('Scheduled hourly cron', 'simple-tags'),
                'weekly_cron_schedule' => esc_html__('Scheduled weekly cron', 'simple-tags')
            ];

            $post_types = get_post_types(['public' => true], 'objects');

            $taxonomies = get_all_taxopress_public_taxonomies();

            $status_messages = [
                'terms_added' => esc_html__('Terms added successfully', 'simple-tags'),
                'invalid_option' => esc_html__('Auto Terms settings do not exist.', 'simple-tags'),
                'term_only_option' => esc_html__('Auto Terms settings are configured to skip posts with terms.', 'simple-tags'),
                'empty_post_content' => esc_html__('Post content is empty.', 'simple-tags'),
                'empty_terms' => esc_html__('No new matching terms for Auto Terms settings and the post content.', 'simple-tags'),
                'empty_terms_remove_all' => esc_html__('No new matching terms for Auto Terms settings and the post content. However, the existing terms were removed due to the Auto Terms replacement settings.', 'simple-tags')
            ];

            $autoterm_settings = taxopress_get_autoterm_data();

            $selected_source = (!empty($_REQUEST['log_source_filter'])) ? sanitize_text_field(wp_unslash($_REQUEST['log_source_filter'])) : '';
            $selected_post_type = (!empty($_REQUEST['log_filter_post_type'])) ? sanitize_text_field(wp_unslash($_REQUEST['log_filter_post_type'])) : '';
            $selected_taxonomy = (!empty($_REQUEST['log_filter_taxonomy'])) ? sanitize_text_field(wp_unslash($_REQUEST['log_filter_taxonomy'])) : '';
            $selected_status_message = (!empty($_REQUEST['log_filter_status_message'])) ? sanitize_text_field(wp_unslash($_REQUEST['log_filter_status_message'])) : '';
            $selected_settings = (!empty($_REQUEST['log_filter_settings'])) ? (int)$_REQUEST['log_filter_settings'] : 0;
            ?>


            <div class="alignleft actions autoterms-log-table-filter">

                <select class="auto-terms-log-filter-select"  name="log_filter_select_post_type" id="log_filter_select_post_type">
                    <option value=""><?php esc_html_e('Post type', 'simple-tags'); ?></option>
                    <?php
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($selected_post_type, $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
                    }
                    ?>
                </select>

                <select class="auto-terms-log-filter-select"  name="log_filter_select_taxonomy" id="log_filter_select_taxonomy">
                    <option value=""><?php esc_html_e('Taxonomy', 'simple-tags'); ?></option>
                    <?php
                    foreach ($taxonomies as $taxonomy) {
                        echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($selected_taxonomy, $taxonomy->name, false) . '>' . esc_html($taxonomy->labels->name) . '</option>';
                    }
                    ?>
                </select>
                
                <select class="auto-terms-log-filter-select" name="log_source_filter_select" id="log_source_filter_select">
                    <option value=""><?php esc_html_e('Source', 'simple-tags'); ?></option>
                    <?php
                    foreach ($log_actions as $key => $label) {
                        echo '<option value="' . esc_attr($key) . '" ' . selected($selected_source, $key, false) . '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>

                <select class="auto-terms-log-filter-select"  name="log_filter_select_status_message" id="log_filter_select_status_message">
                    <option value=""><?php esc_html_e('Status message', 'simple-tags'); ?></option>
                    <?php
                    foreach ($status_messages as $key => $label) {
                        echo '<option value="' . esc_attr($key) . '" ' . selected($selected_status_message, $key, false) . '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>

                <select class="auto-terms-log-filter-select"  name="log_filter_select_settings" id="log_filter_select_settings">
                    <option value=""><?php esc_html_e('Settings', 'simple-tags'); ?></option>
                    <?php
                    foreach ($autoterm_settings as $autoterm_setting) {
                        echo '<option value="' . esc_attr($autoterm_setting['ID']) . '" ' . selected($selected_settings, $autoterm_setting['ID'], false) . '>' . esc_html($autoterm_setting['title']) . '</option>';
                    }
                    ?>
                </select>
                
                <a href="javascript:void(0)" class="taxopress-logs-tablenav-filter button"><?php esc_html_e('Filter', 'simple-tags'); ?></a>
                
            </div>
            <?php
        }
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'cb' => '<input type="checkbox"/>', //Render a checkbox instead of text
            'title'     => esc_html__('Post', 'simple-tags'),
            'post_type'     => esc_html__('Post type', 'simple-tags'),
            'taxonomy'     => esc_html__('Taxonomy', 'simple-tags'),
            'source'     => esc_html__('Source', 'simple-tags'),
            'terms'     => esc_html__('Terms added', 'simple-tags'),
            'status_message'     => esc_html__('Status message', 'simple-tags'),
            'date'     => esc_html__('Date', 'simple-tags'),
            'settings'     => esc_html__('Settings', 'simple-tags')
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
        return isset($item->$column_name) ? $item->$column_name : '&mdash;';
    }

    /** Text displayed when no stterm data is available */
    public function no_items()
    {
        esc_html_e('No item avaliable.', 'simple-tags');
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
        return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', 'taxopress_autoterms_logs', $item->ID);
    }

    /**
     * Get the bulk actions to show in the top page dropdown
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        $actions = [
            'taxopress-autoterms-delete-logs' => esc_html__('Delete', 'simple-tags')
        ];

        return $actions;
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action()
    {

        $query_arg = '_wpnonce';
        $action = 'bulk-' . $this->_args['plural'];
        $checked = $result = isset($_REQUEST[$query_arg]) ? wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST[$query_arg])), $action) : false;

        if (!$checked || !current_user_can('simple_tags')) {
            return;
        }

        if ($this->current_action() === 'taxopress-autoterms-delete-logs') {
            $taxopress_autoterms_logs = array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['taxopress_autoterms_logs']));
            if (!empty($taxopress_autoterms_logs)) {
                foreach ($taxopress_autoterms_logs as $taxopress_autoterms_log) {
                    wp_delete_post($taxopress_autoterms_log, true);
                }
            }
        }
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
            'date'    => ['date', true]
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
        $auto_term_log_post_id = get_post_meta($item->ID, '_taxopress_log_post_id', true);

        //Build row actions
        $actions = [
            'edit'   => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'action' => 'edit',
                        'post' => $auto_term_log_post_id,
                    ],
                    admin_url('post.php')
                ),
                __('Edit Main Post', 'simple-tags')
            ),
            'delete' => sprintf(
                '<a href="%s" class="delete-autoterm">%s</a>',
                add_query_arg(
                    [
                    'page'                   => 'st_autoterms',
                    'tab'                    => 'logs',
                    'action'                 => 'taxopress-delete-autoterm-log',
                    'taxopress_autoterms_log' => esc_attr($item->ID),
                    '_wpnonce'               => wp_create_nonce('autoterm-action-request-nonce')
                    ],
                    admin_url('admin.php')
                ),
                __('Delete Log', 'simple-tags')
            ),
        ];

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
        $auto_term_log_post_id = get_post_meta($item->ID, '_taxopress_log_post_id', true);

        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s</span></strong></a>',
            add_query_arg(
                [
                    'action' => 'edit',
                    'post' => $auto_term_log_post_id,
                ],
                admin_url('post.php')
            ),
            esc_html(get_the_title($auto_term_log_post_id))
        );

        return $title;
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
        $auto_term_log_post_id = get_post_meta($item->ID, '_taxopress_log_post_id', true);
        $auto_term_log_posttype = get_post_type_object(get_post_type($auto_term_log_post_id));

        return ($auto_term_log_posttype && !is_wp_error($auto_term_log_posttype)) ? $auto_term_log_posttype->labels->singular_name : '&mdash;';
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
        $taxopress_log_taxonomy = get_post_meta($item->ID, '_taxopress_log_taxonomy', true);
        $taxopress_log_taxonomy_data = get_taxonomy($taxopress_log_taxonomy);

        return ($taxopress_log_taxonomy_data && !is_wp_error($taxopress_log_taxonomy_data)) ? $taxopress_log_taxonomy_data->labels->singular_name : '&mdash;';
    }

    /**
     * Method for source column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_source($item)
    {

        $taxopress_log_action = get_post_meta($item->ID, '_taxopress_log_action', true);

        $log_action_texts = [
            'save_posts' => esc_html__('Manual post update', 'simple-tags'),
            'existing_content' => esc_html__('Existing content', 'simple-tags'),
            'daily_cron_schedule' => esc_html__('Scheduled daily cron', 'simple-tags'),
            'hourly_cron_schedule' => esc_html__('Scheduled hourly cron', 'simple-tags'),
            'weekly_cron_schedule' => esc_html__('Scheduled weekly cron', 'simple-tags')
        ];

        if (array_key_exists($taxopress_log_action, $log_action_texts)) {
            return esc_html($log_action_texts[$taxopress_log_action]);
        } else {
            return esc_html($taxopress_log_action);
        }
    }

    /**
     * Method for terms column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_terms($item)
    {
        $taxopress_log_terms = get_post_meta($item->ID, '_taxopress_log_terms', true);

        if ($taxopress_log_terms && !empty(trim($taxopress_log_terms))) {
            return '<font color="green"> ' . esc_html(ucwords($taxopress_log_terms)) . ' </font>';
        } else {
            return '<font color="red"> ' . esc_html__('None', 'simple-tags') . ' </font>';
        }
    }

    /**
     * Method for settings column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_settings($item)
    {
        $taxopress_log_settings = get_post_meta($item->ID, '_taxopress_log_options', true);
        if (!empty($taxopress_log_settings) && is_array($taxopress_log_settings)) {
            return sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page'                   => 'st_autoterms',
                        'add'                    => 'new_item',
                        'action'                 => 'edit',
                        'taxopress_autoterms' => $taxopress_log_settings['ID'],
                    ],
                    admin_url('admin.php')
                ),
                $taxopress_log_settings['title']
            );
        } else {
            return '&mdash;';
        }
    }

    /**
     * Method for status_message column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_status_message($item)
    {
        $taxopress_log_status_message = get_post_meta($item->ID, '_taxopress_log_status_message', true);

        $status_message_text = [
            'invalid_option' => esc_html__('Auto Terms settings do not exist.', 'simple-tags'),
            'term_only_option' => esc_html__('Auto Terms settings are configured to skip posts with terms.', 'simple-tags'),
            'empty_post_content' => esc_html__('Post content is empty.', 'simple-tags'),
            'terms_added' => esc_html__('Terms added successfully', 'simple-tags'),
            'empty_terms' => esc_html__('No new matching terms for Auto Terms settings and the post content.', 'simple-tags'),
            'empty_terms_remove_all' => esc_html__('No new matching terms for Auto Terms settings and the post content. However, the existing terms were removed due to the Auto Terms replacement settings.', 'simple-tags')
        ];

        if (array_key_exists($taxopress_log_status_message, $status_message_text)) {
            return esc_html($status_message_text[$taxopress_log_status_message]);
        } else {
            return esc_html($taxopress_log_status_message);
        }
    }

    /**
     * Method for date column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_date($item)
    {

        return get_the_date('l F j, Y h:i A', $item->ID);
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
        if (!isset($_REQUEST['s']) && !$this->has_items()) {
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
        if (!empty($_REQUEST['tab'])) {
            echo '<input type="hidden" name="tab" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['tab']))) . '" />';
        }

        $custom_filters = ['log_source_filter', 'log_filter_post_type', 'log_filter_taxonomy', 'log_filter_status_message', 'log_filter_settings'];

        foreach ($custom_filters as $custom_filter) {
            $filter_value = !empty($_REQUEST[$custom_filter]) ? sanitize_text_field(wp_unslash($_REQUEST[$custom_filter])) : '';
            echo '<input type="hidden" name="' . esc_attr($custom_filter) . '" value="' . esc_attr($filter_value) . '" />';
        }

        $log_limit_link = add_query_arg(
            [
            'page'                   => 'st_autoterms',
            'tab'                    => 'logs',
            'action'                 => 'taxopress-update-autoterm-limit',
            '_wpnonce'               => wp_create_nonce('autoterm-action-request-nonce')
            ],
            admin_url('admin.php')
        );
        ?>
        <p class="search-box">
            <span class="autoterms-log-table-limit-settings">
                <label for="taxopress_auto_terms_logs_limit"><?php esc_html_e('Limit the number of logs', 'simple-tags'); ?></label>
                <input data-link="<?php echo esc_attr($log_limit_link); ?>" type="number" step="1" min="1" name="taxopress_auto_terms_logs_limit" id="taxopress_auto_terms_logs_limit" value="<?php echo (int)get_option('taxopress_auto_terms_logs_limit', 1000); ?>" />
                <a href="javascript:void(0)" class="taxopress-logs-limit-update button"><?php esc_html_e('Update', 'simple-tags'); ?></a>
            </span>

            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s"
                   value="<?php _admin_search_query(); ?>"/>
            <?php submit_button($text, '', '', false, ['id' => 'taxopress-log-search-submit']); ?>
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
        $per_page = $this->get_items_per_page('st_autoterms_logs_per_page', 20);

        /**
         * Fetch the data
         */
        $results = $this->get_st_autoterms();
        $data = $results['posts'];
        $total_items  = $results['counts'];
        $current_page = $this->get_pagenum();


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
}
