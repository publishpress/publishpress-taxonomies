<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class SimpleTags_Schedule_Logs extends WP_List_Table
{
    public function __construct()
    {

        parent::__construct([
            'singular' => __('autotermsschedulelog', 'simple-tags'),
            'plural'   => __('autotermsschedulelogs', 'simple-tags'),
            'ajax'     => false
        ]);
    }

    public function get_st_autoterms()
    {
        $per_page = $this->get_items_per_page('st_autoterms_schedule_logs_per_page', 20);
        $current_page = $this->get_pagenum();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for sorting display, no state change
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'ID';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for sort order display, no state change
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field(wp_unslash($_REQUEST['order'])) : 'desc';


        return taxopress_autoterms_logs_data($per_page, $current_page, $orderby, $order, true);
    }

    public static function record_count()
    {
        return get_st_autoterms()['counts'];
    }

    public function single_row($item)
    {
        $class = ['st-autoterm-log-tr'];
        $id    = 'st-autoterm-log-' . md5($item->ID);
        echo sprintf('<tr id="%s" class="%s">', esc_attr($id), esc_attr(implode(' ', $class)));
        $this->single_row_columns($item);
        echo '</tr>';
    }

    public function get_columns()
    {
        $columns = [
            'cb' => '<input type="checkbox" aria-label="' . esc_attr__('Select all scheduled auto terms logs', 'simple-tags') . '" />',
            'title'     => esc_html__('Post', 'simple-tags'),
            'post_type'     => esc_html__('Post type', 'simple-tags'),
            'taxonomy'     => esc_html__('Taxonomy', 'simple-tags'),
            'terms'     => esc_html__('Terms added', 'simple-tags'),
            'date'     => esc_html__('Date', 'simple-tags')
        ];

        return $columns;
    }

    public function column_default($item, $column_name)
    {
        return isset($item->$column_name) ? $item->$column_name : '&mdash;';
    }

    public function no_items()
    {
        esc_html_e('Schedule log is empty.', 'simple-tags');
    }

    protected function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" aria-label="%3$s" />',
            'taxopress_autoterms_schedule_logs',
            $item->ID,
            sprintf(
                /* translators: %s: log ID. */
                esc_attr__('Select scheduled auto terms log %s', 'simple-tags'),
                (string) $item->ID
            )
        );
    }

    protected function get_bulk_actions()
    {
        $actions = [
            'taxopress-autoterms-delete-logs' => esc_html__('Delete', 'simple-tags')
        ];

        return $actions;
    }

    public function process_bulk_action()
    {

        $query_arg = '_wpnonce';
        $action = 'bulk-' . $this->_args['plural'];
        $checked = $result = isset($_REQUEST[$query_arg]) ? wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST[$query_arg])), $action) : false;

        if (!$checked || !current_user_can('simple_tags')) {
            return;
        }

        if ($this->current_action() === 'taxopress-autoterms-delete-logs') {
            $taxopress_autoterms_schedule_logs = isset($_REQUEST['taxopress_autoterms_schedule_logs']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['taxopress_autoterms_schedule_logs'])) : [];
            if (!empty($taxopress_autoterms_schedule_logs)) {
                foreach ($taxopress_autoterms_schedule_logs as $taxopress_autoterms_log) {
                    wp_delete_post($taxopress_autoterms_log, true);
                }
            }
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = [
            'title'    => ['title', true],
            'date'    => ['date', true]
        ];

        return $sortable_columns;
    }

    protected function handle_row_actions($item, $column_name, $primary)
    {
        $auto_term_log_post_id = get_post_meta($item->ID, '_taxopress_log_post_id', true);

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
                    'page'                   => 'st_autoterms_schedule',
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

    protected function column_post_type($item)
    {
        $auto_term_log_post_id = get_post_meta($item->ID, '_taxopress_log_post_id', true);
        $auto_term_log_posttype = get_post_type_object(get_post_type($auto_term_log_post_id));

        return ($auto_term_log_posttype && !is_wp_error($auto_term_log_posttype)) ? $auto_term_log_posttype->labels->singular_name : '&mdash;';
    }

    protected function column_taxonomy($item)
    {
        $taxopress_log_taxonomy = get_post_meta($item->ID, '_taxopress_log_taxonomy', true);
        $taxopress_log_taxonomy_data = get_taxonomy($taxopress_log_taxonomy);

        return ($taxopress_log_taxonomy_data && !is_wp_error($taxopress_log_taxonomy_data)) ? $taxopress_log_taxonomy_data->labels->singular_name : '&mdash;';
    }

    protected function column_terms($item)
    {
        $taxopress_log_terms = get_post_meta($item->ID, '_taxopress_log_terms', true);

        if ($taxopress_log_terms && !empty(trim($taxopress_log_terms))) {
            return '<font color="green"> ' . esc_html(ucwords($taxopress_log_terms)) . ' </font>';
        } else {
            return '<font color="red"> ' . esc_html__('None', 'simple-tags') . ' </font>';
        }
    }

    protected function column_date($item)
    {

        return get_the_date('l F j, Y h:i A', $item->ID);
    }

    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('st_autoterms_schedule_logs_per_page', 20);

        $results = $this->get_st_autoterms();
        $data = $results['posts'];
        $total_items  = $results['counts'];
        $current_page = $this->get_pagenum();


        $this->items = $data;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
