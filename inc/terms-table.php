<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Taxopress_Terms_List extends WP_List_Table
{
    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => 'Term', //singular name of the listed records
            'plural'   => 'Terms', //plural name of the listed records
            'ajax'     => true //does this table support ajax?
        ]);
    }

    /**
     * Return the current Terms screen filters in a normalized shape.
     *
     * @return array
     */
    private function get_current_term_filters()
    {
        $taxonomies = array_keys(get_all_taxopress_taxonomies_request());

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for search filtering
        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering and display
        $selected_post_type = (!empty($_REQUEST['terms_filter_post_type'])) ? sanitize_key(wp_unslash($_REQUEST['terms_filter_post_type'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering and display
        $selected_taxonomy = (!empty($_REQUEST['terms_filter_taxonomy'])) ? sanitize_key(wp_unslash($_REQUEST['terms_filter_taxonomy'])) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
        if (!empty($_REQUEST['taxopress_terms_taxonomy'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
            $selected_taxonomy = sanitize_key(wp_unslash($_REQUEST['taxopress_terms_taxonomy']));
            $taxonomies = [$selected_taxonomy];
        } elseif (!empty($selected_taxonomy)) {
            $taxonomies = [$selected_taxonomy];
        }

        return [
            'taxonomies'         => array_values(array_filter(array_map('sanitize_key', $taxonomies))),
            'search'             => $search,
            'selected_post_type' => $selected_post_type,
            'selected_taxonomy'  => $selected_taxonomy,
        ];
    }

    /**
     * Hydrate a page of term references while preserving the requested order.
     *
     * @param array $term_refs List of arrays containing term_id and taxonomy.
     * @return array
     */
    private function get_terms_from_refs($term_refs)
    {
        if (empty($term_refs)) {
            return [];
        }

        $by_taxonomy = [];
        foreach ($term_refs as $term_ref) {
            $taxonomy = sanitize_key($term_ref['taxonomy']);
            $term_id  = (int) $term_ref['term_id'];

            if ($term_id > 0 && !empty($taxonomy)) {
                $by_taxonomy[$taxonomy][] = $term_id;
            }
        }

        $terms_by_key = [];
        foreach ($by_taxonomy as $taxonomy => $term_ids) {
            $taxonomy_terms = get_terms([
                'taxonomy'               => [$taxonomy],
                'include'                => array_values(array_unique($term_ids)),
                'hide_empty'             => false,
                'orderby'                => 'include',
                'number'                 => count($term_ids),
                'pad_counts'             => false,
                'hierarchical'           => false,
                'update_term_meta_cache' => true,
            ]);

            if (empty($taxonomy_terms) || is_wp_error($taxonomy_terms)) {
                continue;
            }

            foreach ($taxonomy_terms as $term) {
                $terms_by_key[$term->taxonomy . ':' . $term->term_id] = $term;
            }
        }

        $ordered_terms = [];
        foreach ($term_refs as $term_ref) {
            $key = sanitize_key($term_ref['taxonomy']) . ':' . (int) $term_ref['term_id'];
            if (isset($terms_by_key[$key])) {
                $ordered_terms[] = $terms_by_key[$key];
            }
        }

        return $ordered_terms;
    }

    /**
     * Add term references to an ordered list.
     *
     * @param array  $term_refs Ordered term references.
     * @param string $taxonomy Taxonomy slug.
     * @param array  $term_ids Term IDs.
     */
    private function append_term_refs(&$term_refs, $taxonomy, $term_ids)
    {
        foreach ((array) $term_ids as $term_id) {
            $term_id = (int) $term_id;

            if ($term_id <= 0) {
                continue;
            }

            $term_refs[] = [
                'term_id'  => $term_id,
                'taxonomy' => $taxonomy,
            ];
        }
    }

    /**
     * Count terms for one taxonomy using the same filters as the list table.
     *
     * @param string       $taxonomy Taxonomy slug.
     * @param array|string $selected_post_type Selected post type filter.
     * @param string       $search Search text.
     * @return int
     */
    private function count_taxonomy_terms($taxonomy, $selected_post_type, $search)
    {
        $terms_attr = [
            'taxonomy'               => [$taxonomy],
            'post_types'             => $selected_post_type,
            'hide_empty'             => false,
            'pad_counts'             => false,
            'hierarchical'           => false,
            'update_term_meta_cache' => false,
            'fields'                 => 'count',
            'search'                 => $search,
        ];

        $term_count = get_terms($terms_attr);

        return is_wp_error($term_count) ? 0 : (int) $term_count;
    }

    /**
     * Remove deleted term IDs from the saved manual ordering option.
     *
     * @param array $term_ids_by_taxonomy Deleted term IDs grouped by taxonomy.
     */
    private function remove_terms_from_manual_order($term_ids_by_taxonomy)
    {
        foreach ($term_ids_by_taxonomy as $taxonomy => $term_ids) {
            $term_ids = array_map('intval', (array) $term_ids);
            if (empty($term_ids)) {
                continue;
            }

            $option_name = 'taxopress_term_order_' . sanitize_key($taxonomy);
            $custom_order = get_option($option_name, []);

            if (empty($custom_order) || !is_array($custom_order)) {
                continue;
            }

            $updated_order = array_values(array_diff(array_map('intval', $custom_order), $term_ids));

            if ($updated_order !== array_values(array_map('intval', $custom_order))) {
                update_option($option_name, $updated_order);
            }
        }
    }

    /**
     * Show a just-copied term directly above its original during the next render.
     *
     * @param array $terms Current visible terms.
     * @return array
     */
    private function prioritize_copied_term($terms)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporary display context after a copy action.
        $copied_term_id = !empty($_REQUEST['taxopress_copied_term_id']) ? (int) $_REQUEST['taxopress_copied_term_id'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporary display context after a copy action.
        $original_term_id = !empty($_REQUEST['taxopress_original_term_id']) ? (int) $_REQUEST['taxopress_original_term_id'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Temporary display context after a copy action.
        $taxonomy = !empty($_REQUEST['taxopress_copied_taxonomy']) ? sanitize_key(wp_unslash($_REQUEST['taxopress_copied_taxonomy'])) : '';

        if ($copied_term_id <= 0 || $original_term_id <= 0 || empty($taxonomy)) {
            return $terms;
        }

        $copied_term = get_term($copied_term_id, $taxonomy);
        $original_term = get_term($original_term_id, $taxonomy);

        if (!$copied_term || !$original_term || is_wp_error($copied_term) || is_wp_error($original_term)) {
            return $terms;
        }

        if (isset($original_term->taxopress_depth)) {
            $copied_term->taxopress_depth = $original_term->taxopress_depth;
        }

        $ordered_terms = [];
        $inserted_copy = false;

        foreach ($terms as $term) {
            if ((int) $term->term_id === $copied_term_id) {
                continue;
            }

            if ((int) $term->term_id === $original_term_id) {
                if (isset($term->taxopress_depth)) {
                    $copied_term->taxopress_depth = $term->taxopress_depth;
                }

                $ordered_terms[] = $copied_term;
                $inserted_copy = true;
            }

            $ordered_terms[] = $term;
        }

        if (!$inserted_copy) {
            $ordered_terms[] = $copied_term;
        }

        return $ordered_terms;
    }

    /**
     * Flatten a hierarchical terms array into a flat array.
     *
     * @param array $terms Array of term objects.
     * @param int $max_depth Maximum depth to traverse (default 10).
     * @return array Flattened array of term objects.
    */
    public function taxopress_flatten_terms_tree($terms, $max_depth = 7)
    {
        $flat = [];
        $map = [];
        $children_map = [];
        $visited = [];

        // Build a map of terms by ID and their children
        foreach ($terms as $term) {
            $map[$term->term_id] = $term;
            $children_map[$term->parent][] = $term;
        }

        // Initialize stack with root-level terms (no valid parent)
        $stack = [];
        foreach (array_reverse($terms) as $term) {
            if ($term->parent === 0 || !isset($map[$term->parent])) {
                $stack[] = ['term' => $term, 'depth' => 0];
            }
        }

        // Iterative depth-first traversal
        while (!empty($stack)) {
            $node = array_pop($stack);
            $term = $node['term'];
            $depth = $node['depth'];

            if ($depth > $max_depth) {
                continue;
            }
            if (isset($visited[$term->term_id])) {
                continue; // Prevent cycles
            }
            $visited[$term->term_id] = true;

            $flat[] = $term;

            // Use pre-indexed children map
            if (!empty($children_map[$term->term_id])) {
                foreach (array_reverse($children_map[$term->term_id]) as $child) {
                    $stack[] = ['term' => $child, 'depth' => $depth + 1];
                }
            }
        }

        return $flat;
    }

    /**
     * Arrange terms in hierarchical order with depth info for dash prefixing.
    */
    public function taxopress_arrange_terms_hierarchically($terms)
    {
        $terms_by_id = [];
        $children = [];
        foreach ($terms as $term) {
            $term_id = (int) $term->term_id;
            $parent_id = (int) $term->parent;

            if ($term_id <= 0 || isset($terms_by_id[$term_id])) {
                continue;
            }

            $terms_by_id[$term_id] = $term;
            $children[$parent_id][] = $term_id;
        }

        $ordered = [];
        $visited = [];

        $add_terms = function ($term_ids) use (&$terms_by_id, &$children, &$ordered, &$visited) {
            $stack = [];

            foreach (array_reverse($term_ids) as $term_id) {
                $stack[] = [
                    'term_id' => (int) $term_id,
                    'depth'   => 0,
                ];
            }

            while (!empty($stack)) {
                $node = array_pop($stack);
                $term_id = (int) $node['term_id'];

                if (isset($visited[$term_id]) || !isset($terms_by_id[$term_id])) {
                    continue;
                }

                $visited[$term_id] = true;
                $term = $terms_by_id[$term_id];
                $term->taxopress_depth = (int) $node['depth'];
                $ordered[] = $term;

                if (!empty($children[$term_id])) {
                    foreach (array_reverse($children[$term_id]) as $child_id) {
                        $child_id = (int) $child_id;

                        if ($child_id === $term_id || isset($visited[$child_id])) {
                            continue;
                        }

                        $stack[] = [
                            'term_id' => $child_id,
                            'depth'   => (int) $node['depth'] + 1,
                        ];
                    }
                }
            }
        };

        // Start with root terms
        $root_ids = [];
        foreach ($terms as $term) {
            $term_id = (int) $term->term_id;
            $parent_id = (int) $term->parent;

            if ($parent_id === 0 || !isset($terms_by_id[$parent_id]) || $parent_id === $term_id) {
                $root_ids[] = $term_id;
            }
        }

        $add_terms($root_ids);

        // Include any disconnected or cyclic branches once, without recursion.
        foreach ($terms as $term) {
            $term_id = (int) $term->term_id;
            if (!isset($visited[$term_id])) {
                $add_terms([$term_id]);
            }
        }

        return $ordered;
    }

    public function get_all_terms($count = false)
    {

        $filters = $this->get_current_term_filters();
        $taxonomies = $filters['taxonomies'];
        $taxonomy_settings = taxopress_get_all_edited_taxonomy_data();

        $search = $filters['search'];

        $items_per_page = $this->get_items_per_page('st_terms_per_page', 20);
        $page           = $this->get_pagenum();
        $offset         = ($page - 1) * $items_per_page;

        $selected_post_type = !empty($filters['selected_post_type']) ? [$filters['selected_post_type']] : '';
        $selected_taxonomy  = $filters['selected_taxonomy'];

        $allowed_orderby = ['name', 'slug', 'taxonomy', 'count', 'id'];
        $allowed_order   = ['asc', 'desc'];

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for ordering
        $requested_orderby = !empty($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for ordering
        $requested_order   = !empty($_REQUEST['order']) ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['order']))) : '';

        if (!in_array($requested_orderby, $allowed_orderby, true)) {
            $requested_orderby = '';
        }
        if (!in_array($requested_order, $allowed_order, true)) {
            $requested_order = '';
        }

        $order_setting   = isset($taxonomy_settings[$selected_taxonomy]['order']) ? strtolower($taxonomy_settings[$selected_taxonomy]['order']) : 'desc';
        $orderby_setting = isset($taxonomy_settings[$selected_taxonomy]['orderby']) ? $taxonomy_settings[$selected_taxonomy]['orderby'] : 'ID';

        $orderby_setting = strtolower($orderby_setting);
        if ($orderby_setting === 'id' || $orderby_setting === 'term_id') {
            $orderby_setting = 'id';
        }

        if ($requested_orderby) {
            $orderby_setting = $requested_orderby;
        }
        if ($requested_order) {
            $order_setting = $requested_order;
        }

        $show_all_terms_in_taxonomy = false;

        // Check if any taxonomy uses manual ordering
        $manual_order = false;
        foreach ($taxonomies as $taxonomy) {
            $order_setting = isset($taxonomy_settings[$taxonomy]['order']) ? $taxonomy_settings[$taxonomy]['order'] : 'desc';
            $orderby_setting = isset($taxonomy_settings[$taxonomy]['orderby']) ? $taxonomy_settings[$taxonomy]['orderby'] : 'ID';
            if ($requested_orderby) {
                $orderby_setting = $requested_orderby;
            }
            if ($requested_order) {
                $order_setting = $requested_order;
            }
            if ($orderby_setting === 'taxopress_term_order') {
                $manual_order = true;
                break;
            }
        }

        // If any taxonomy uses manual order, use the original per-taxonomy logic
        if ($manual_order) {
            $term_refs = [];
            $remaining_offset = $count ? 0 : $offset;
            $remaining_number = $count ? PHP_INT_MAX : $items_per_page;
            foreach ($taxonomies as $taxonomy) {
                if ($remaining_number <= 0) {
                    break;
                }

                $custom_order = get_option('taxopress_term_order_' . $taxonomy, []);
                $custom_order = array_values(array_filter(array_map('intval', (array) $custom_order)));
                $custom_order = array_values(array_unique($custom_order));
                $custom_order_lookup = array_flip($custom_order);
                $order_setting = isset($taxonomy_settings[$taxonomy]['order']) ? $taxonomy_settings[$taxonomy]['order'] : 'desc';
                $orderby_setting = isset($taxonomy_settings[$taxonomy]['orderby']) ? $taxonomy_settings[$taxonomy]['orderby'] : 'ID';
                $use_custom_order = ($orderby_setting === 'taxopress_term_order');
                $display_custom_order = ($order_setting === 'desc') ? array_reverse($custom_order) : $custom_order;

                if ($use_custom_order && empty($search) && empty($selected_post_type)) {
                    $taxonomy_count = $this->count_taxonomy_terms($taxonomy, '', '');
                    $ordered_count = min(count($display_custom_order), $taxonomy_count);
                    $new_count = max(0, $taxonomy_count - $ordered_count);

                    if ($remaining_offset < $ordered_count) {
                        $ordered_term_ids = array_slice($display_custom_order, $remaining_offset, $remaining_number);
                        $this->append_term_refs($term_refs, $taxonomy, $ordered_term_ids);
                        $remaining_number -= count($ordered_term_ids);
                        $remaining_offset = 0;
                    } elseif ($remaining_offset >= $ordered_count) {
                        $remaining_offset -= $ordered_count;
                    }

                    if ($remaining_number > 0 && $remaining_offset < $new_count) {
                        $new_number = min($remaining_number, $new_count - $remaining_offset);
                        $all_term_ids = get_terms([
                            'taxonomy'               => [$taxonomy],
                            'hide_empty'             => false,
                            'pad_counts'             => false,
                            'hierarchical'           => false,
                            'update_term_meta_cache' => false,
                            'fields'                 => 'ids',
                        ]);
                        $new_term_ids = is_wp_error($all_term_ids)
                            ? []
                            : array_values(array_diff(array_map('intval', (array) $all_term_ids), $custom_order));
                        $new_term_ids = array_slice($new_term_ids, $remaining_offset, $new_number);

                        if (!empty($new_term_ids)) {
                            if ($order_setting === 'desc') {
                                $new_term_ids = array_reverse($new_term_ids);
                            }
                            $this->append_term_refs($term_refs, $taxonomy, $new_term_ids);
                            $remaining_number -= count($new_term_ids);
                        }

                        $remaining_offset = 0;
                    } elseif ($remaining_offset >= $new_count) {
                        $remaining_offset -= $new_count;
                    }

                    continue;
                }

                $terms_attr = [
                    'taxonomy' => [$taxonomy],
                    'post_types' => $selected_post_type,
                    'hide_empty' => false,
                    'pad_counts' => false,
                    'hierarchical' => false,
                    'update_term_meta_cache' => false,
                    'fields' => 'ids',
                    'search' => $search,
                ];

                if (!$use_custom_order) {
                    $terms_attr['orderby'] = $orderby_setting;
                    $terms_attr['order'] = $order_setting;
                }

                $taxonomy_count = $this->count_taxonomy_terms($taxonomy, $selected_post_type, $search);
                if ($remaining_offset >= $taxonomy_count) {
                    $remaining_offset -= $taxonomy_count;
                    continue;
                }

                $terms_attr['offset'] = $remaining_offset;
                $terms_attr['number'] = $remaining_number;

                // Manual ordering with active filters still needs ID-level filtering, but never hydrates full term objects.
                $taxonomy_term_ids = get_terms($terms_attr);

                if (empty($taxonomy_term_ids) || is_wp_error($taxonomy_term_ids)) {
                    continue;
                }

                $taxonomy_term_ids = array_map('intval', (array) $taxonomy_term_ids);

                if ($use_custom_order) {
                    $terms_by_id = array_flip($taxonomy_term_ids);
                    $ordered_term_ids = [];
                    $new_term_ids = [];

                    // Ordered terms
                    foreach ($display_custom_order as $term_id) {
                        if (isset($terms_by_id[$term_id])) {
                            $ordered_term_ids[] = $term_id;
                        }
                    }

                    // Terms not in custom order
                    foreach ($taxonomy_term_ids as $term_id) {
                        if (!isset($custom_order_lookup[$term_id])) {
                            $new_term_ids[] = $term_id;
                        }
                    }

                    // Merge: custom-ordered terms first, then new/unordered terms.
                    $taxonomy_term_ids = array_merge($ordered_term_ids, $new_term_ids);
                } else {
                    if ($orderby_setting === 'random') {
                        shuffle($taxonomy_term_ids);
                        if ($order_setting === 'desc') {
                            $taxonomy_term_ids = array_reverse($taxonomy_term_ids);
                        }
                    }
                }

                $this->append_term_refs($term_refs, $taxonomy, $taxonomy_term_ids);
                $remaining_number -= count($taxonomy_term_ids);
                $remaining_offset = 0;
            }

            $terms = $this->get_terms_from_refs($term_refs);

            // HIERARCHY SUPPORT
            if (!empty($selected_taxonomy)) {
                $terms = $this->taxopress_arrange_terms_hierarchically($terms);
            } else {
                $terms = $this->taxopress_flatten_terms_tree($terms);
            }

            return $this->prioritize_copied_term($terms);
        }

        // If no manual ordering, use the efficient all-in-one get_terms
        $terms_attr = [
            'taxonomy' => $taxonomies,
            'post_types' => $selected_post_type,
            'orderby' => $orderby_setting,
            'order' => $order_setting,
            'search' => $search,
            'hide_empty' => false,
            'pad_counts' => false,
            'hierarchical' => false,
            'update_term_meta_cache' => true,
        ];
        if ($count || $show_all_terms_in_taxonomy) {
            $terms_attr['number'] = 0;
        } else {
            $terms_attr['offset'] = $offset;
            $terms_attr['number'] = $items_per_page;
        }

        $terms = get_terms($terms_attr);

        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }

        if (!empty($requested_orderby) && $requested_orderby === 'taxonomy') {
            usort($terms, function ($a, $b) use ($order_setting) {
                $cmp = strcmp($a->taxonomy, $b->taxonomy);
                return ($order_setting === 'desc') ? -$cmp : $cmp;
            });
        }

        if ($orderby_setting === 'random') {
            shuffle($terms);
            if ($order_setting === 'desc') {
                $terms = array_reverse($terms);
            }
        }

        // HIERARCHY SUPPORT
        if (!empty($selected_taxonomy)) {
            $terms = $this->taxopress_arrange_terms_hierarchically($terms);
        } else {
            $terms = $this->taxopress_flatten_terms_tree($terms);
        }

        return $this->prioritize_copied_term($terms);
    }

    /**
     * Retrieve st_Terms data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public function get_st_Terms()
    {
        return $this->get_all_terms();
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public function record_count()
    {
        global $wpdb;

        $filters = $this->get_current_term_filters();

        if (empty($filters['taxonomies'])) {
            return 0;
        }

        $join = "INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id";
        $where = [];
        $query_args = [];

        $taxonomy_placeholders = implode(', ', array_fill(0, count($filters['taxonomies']), '%s'));
        $where[] = "tt.taxonomy IN ({$taxonomy_placeholders})";
        $query_args = array_merge($query_args, $filters['taxonomies']);

        if (!empty($filters['search'])) {
            $where[] = 't.name LIKE %s';
            $query_args[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        if (!empty($filters['selected_post_type'])) {
            $join .= " INNER JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $join .= " INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id";
            $where[] = 'p.post_type = %s';
            $query_args[] = $filters['selected_post_type'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(DISTINCT tt.term_taxonomy_id) FROM {$wpdb->terms} AS t {$join} WHERE {$where_sql}";
        $cache_key = 'taxopress_terms_count_' . md5($sql . wp_json_encode($query_args));
        $cached_count = wp_cache_get($cache_key, 'taxopress_terms');

        if ($cached_count !== false) {
            return (int) $cached_count;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Cached count query avoids loading term objects on large sites.
        $total_items = (int) $wpdb->get_var($wpdb->prepare($sql, $query_args));
        wp_cache_set($cache_key, $total_items, 'taxopress_terms', 60);

        return $total_items;
    }

    /**
     * Show single row item
     *
     * @param array $item
     */
    public function single_row($item)
    {
        $class = ['st-terms-tr'];
        $id    = 'term-' . $item->term_id . '';
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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
        if (!empty($_REQUEST['taxopress_terms_taxonomy'])) {
            return [
                'name'        => esc_html__('Title', 'simple-tags'),
                'description' => esc_html__('Description', 'simple-tags'),
                'count'       => esc_html__('Count', 'simple-tags'),
            ];
        }
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'name'     => esc_html__('Title', 'simple-tags'),
            'slug'     => esc_html__('Slug', 'simple-tags'),
            'description'     => esc_html__('Description', 'simple-tags'),
            'taxonomy'  => esc_html__('Taxonomy', 'simple-tags'),
            'posttypes'  => esc_html__('Post Types', 'simple-tags'),
            'taxopress_custom_url'  => esc_html__('Custom URL', 'simple-tags'),
            'synonyms'  => esc_html__('Synonyms', 'simple-tags'),
            'linked_terms'  => esc_html__('Linked Terms', 'simple-tags'),
            'hidden_status' => esc_html__('Status', 'simple-tags'),
            'count'  => esc_html__('Count', 'simple-tags')
        ];

        if (!taxopress_is_pro_version()) {
            unset($columns['synonyms']);
            unset($columns['linked_terms']);
        }

        if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
            unset($columns['hidden_status']);
        }

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
            'name'      => ['name', true],
            'slug'      => ['slug', true],
            'taxonomy'  => ['taxonomy', true],
            'count'     => ['count', true],
        ];

        return $sortable_columns;
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
        return sprintf('<input type="checkbox" name="%1$s[]" value="%2$s" />', 'taxopress_terms', $item->term_id);
    }

    /**
     * Get the bulk actions to show in the top page dropdown
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        $actions = [
            'taxopress-terms-delete-terms' => esc_html__('Delete', 'simple-tags'),
            'taxopress-terms-copy-terms'   => esc_html__('Copy', 'simple-tags')
        ];

        return $actions;
    }

    /**
     * Add custom filter to tablenav
     *
     * @param string $which
     */
    protected function extra_tablenav($which)
    {
        // Hide filters if taxopress_show_all=1
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for display filtering
        if ('top' === $which && empty($_REQUEST['taxopress_show_all'])) {
            $post_types = get_post_types(['public' => true], 'objects');

            $taxonomies = get_all_taxopress_taxonomies_request();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering
            $selected_post_type = (!empty($_REQUEST['terms_filter_post_type'])) ? sanitize_text_field(wp_unslash($_REQUEST['terms_filter_post_type'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering
            $selected_taxonomy = (!empty($_REQUEST['terms_filter_taxonomy'])) ? sanitize_text_field(wp_unslash($_REQUEST['terms_filter_taxonomy'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering
            $selected_post = (!empty($_REQUEST['taxopress_destination_post_type'])) ? sanitize_text_field(wp_unslash($_REQUEST['taxopress_destination_post_type'])) : '';

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying GET parameter for taxonomy type
            $selected_option = 'public';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'all') {
                $selected_option = 'all';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            } elseif (isset($_GET['taxonomy_type']) && $_GET['taxonomy_type'] === 'private') {
                $selected_option = 'private';
            }
            ?>

            <div class="alignleft actions autoterms-terms-table-copy" id="taxopress-copy-selection-boxes" style="display: none;">
                <select class="auto-terms-terms-copy-select" name="taxopress_destination_taxonomy" id="terms_copy_select_destination_taxonomy">
                    <option value=""><?php esc_html_e('Select Destination Taxonomy', 'simple-tags'); ?></option> <?php
                    foreach ($taxonomies as $taxonomy) {
                        echo '<option value="' . esc_attr($taxonomy->name) . '">' . esc_html($taxonomy->labels->name) . '</option>';
                    } ?>
                </select>

                <select class="auto-terms-terms-copy-select taxopress-post-search" name="taxopress_destination_post_type" id="terms_copy_select_destination_post" data-placeholder="<?php esc_attr_e('Search posts', 'simple-tags'); ?>" data-allow-clear="true" data-nonce="<?php echo esc_attr(wp_create_nonce('taxopress-post-search')); ?>" data-post-types="<?php echo esc_attr($selected_post_type); ?>">
                    <?php
                        $post_type_label = !empty($selected_post_type) ? esc_html($selected_post_type) : esc_html__('post type', 'simple-tags');
                    ?>
                        <option value=""><?php printf(esc_html__('Select Destination %s', 'simple-tags'), esc_html($post_type_label)); ?></option>
                        <option value="all" <?php selected($selected_post, 'all'); ?>><?php printf(esc_html__('All %s', 'simple-tags'), esc_html($post_type_label)); ?></option>
                </select>
            </div>
            <div class="alignleft actions autoterms-terms-table-filter">

                <select class="auto-terms-terms-filter-select" name="terms_filter_select_post_type" id="terms_filter_select_post_type">
                    <option value=""><?php esc_html_e('Post type', 'simple-tags'); ?></option>
                    <?php
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($selected_post_type, $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
                    }
                    ?>
                </select>

                <select class="auto-terms-terms-filter-select" name="terms_filter_select_taxonomy" id="terms_filter_select_taxonomy">
                    <option value=""><?php esc_html_e('Taxonomy', 'simple-tags'); ?></option>
                    <?php
                    foreach ($taxonomies as $taxonomy) {
                        echo '<option value="' . esc_attr($taxonomy->name) . '" ' . selected($selected_taxonomy, $taxonomy->name, false) . '>' . esc_html($taxonomy->labels->name) . '</option>';
                    }
                    ?>
                </select>

                <select class="auto-terms-terms-filter-select" name="terms_filter_select_taxonomy_type" id="terms_filter_select_taxonomy_type">
                    <option value="all" <?php echo($selected_option === 'all' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('All Taxonomies', 'simple-tags'); ?></option>
                    <option value="public" <?php echo($selected_option === 'public' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('Public Taxonomies', 'simple-tags'); ?></option>
                    <option value="private" <?php echo($selected_option === 'private' ? 'selected="selected"' : ''); ?>><?php echo esc_html__('Private Taxonomies', 'simple-tags'); ?></option>
                </select>
                
                <a href="javascript:void(0)" class="taxopress-terms-tablenav-filter button"><?php esc_html_e('Filter', 'simple-tags'); ?></a>

            </div>
            <?php
        }
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action()
    {

        $query_arg = '_wpnonce';
        $action = 'bulk-' . $this->_args['plural'];
        $checked = isset($_REQUEST[$query_arg]) ? wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST[$query_arg])), $action) : false;

        if (!$checked || !current_user_can('simple_tags')) {
            return;
        }

        if ($this->current_action() === 'taxopress-terms-delete-terms') {
            $taxopress_terms = !empty($_REQUEST['taxopress_terms']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['taxopress_terms'])) : [];
            if (!empty($taxopress_terms)) {
                $deleted_terms_by_taxonomy = [];
                foreach ($taxopress_terms as $taxopress_term) {
                    $term = get_term($taxopress_term);
                    if (!$term || is_wp_error($term)) {
                        continue;
                    }

                    if (!taxopress_current_user_can_for_taxonomy($term->taxonomy, 'delete_terms')) {
                        continue;
                    }

                    $deleted_terms_by_taxonomy[$term->taxonomy][] = (int) $term->term_id;
                    wp_delete_term($term->term_id, $term->taxonomy);
                }
                $this->remove_terms_from_manual_order($deleted_terms_by_taxonomy);

                if (count($taxopress_terms) > 1) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo taxopress_admin_notices_helper(esc_html__('Terms deleted successfully.', 'simple-tags'), false);
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo taxopress_admin_notices_helper(esc_html__('Term deleted successfully.', 'simple-tags'), false);
                }
            }
        }
        if ($this->current_action() === 'taxopress-terms-copy-terms') {
            $taxopress_terms = !empty($_REQUEST['taxopress_terms']) ? array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['taxopress_terms'])) : [];
            $destination_taxonomy = !empty($_REQUEST['taxopress_destination_taxonomy']) ? sanitize_key(wp_unslash($_REQUEST['taxopress_destination_taxonomy'])) : '';
            $destination_post = !empty($_REQUEST['taxopress_destination_post_type']) ? sanitize_text_field(wp_unslash($_REQUEST['taxopress_destination_post_type'])) : '';

            if (
                !empty($taxopress_terms)
                && !empty($destination_taxonomy)
                && taxopress_current_user_can_for_taxonomy(
                    $destination_taxonomy,
                    ['edit_terms', 'assign_terms']
                )
            ) {
                foreach ($taxopress_terms as $taxopress_term) {
                    $term = get_term($taxopress_term);
                    if (
                        !$term
                        || is_wp_error($term)
                        || !taxopress_current_user_can_for_taxonomy($term->taxonomy, 'edit_terms')
                    ) {
                        continue;
                    }

                    $inserted_term = wp_insert_term($term->name, $destination_taxonomy, [
                        'slug' => $term->slug,
                        'description' => $term->description,
                    ]);
                    if (is_wp_error($inserted_term)) {
                        $existing_term = term_exists($term->slug, $destination_taxonomy);
                        $destination_term_id = is_array($existing_term) ? (int) $existing_term['term_id'] : (int) $existing_term;
                    } else {
                        $destination_term_id = (int) $inserted_term['term_id'];
                    }

                    if (
                        $destination_term_id
                        && !empty($destination_post)
                        && $destination_post !== 'all'
                        && current_user_can('edit_post', (int) $destination_post)
                        && is_object_in_taxonomy(get_post_type((int) $destination_post), $destination_taxonomy)
                    ) {
                        wp_set_object_terms((int) $destination_post, [$destination_term_id], $destination_taxonomy, true);
                    }

                    if ($destination_term_id && $destination_post === 'all') {
                        foreach (taxopress_get_post_ids_for_terms_action() as $post_id) {
                            if (
                                !current_user_can('edit_post', $post_id)
                                || !is_object_in_taxonomy(get_post_type($post_id), $destination_taxonomy)
                            ) {
                                continue;
                            }
                            wp_set_object_terms($post_id, [$destination_term_id], $destination_taxonomy, true);
                        }
                    }
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo taxopress_admin_notices_helper(esc_html__('Term(s) copied successfully.', 'simple-tags'), true);
            }
        }
    }

    protected function column_taxopress_custom_url($item)
    {
        $taxopress_custom_url = get_term_meta($item->term_id, 'taxopress_custom_url', true);
        return (!empty($taxopress_custom_url) && filter_var($taxopress_custom_url, FILTER_VALIDATE_URL))
            ? sprintf('<a href="%s" target="_blank">%s</a>', esc_url($taxopress_custom_url), esc_html($taxopress_custom_url))
            : '-';
    }

    protected function column_hidden_status($item)
    {
        $hidden_terms = get_transient('taxopress_hidden_terms_' . $item->taxonomy);

        if (!empty($hidden_terms) && in_array($item->term_id, $hidden_terms)) {
            return esc_html__('Hidden', 'simple-tags');
        }

        return esc_html__('Live', 'simple-tags');
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

    /** Text displayed when no stterm data is available */
    public function no_items()
    {
        esc_html_e('No terms found.', 'simple-tags');
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
        // Hide search box if taxopress_show_all=1
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for display filtering
        if ((!empty($_REQUEST['taxopress_show_all']) && $_REQUEST['taxopress_show_all'] == '1')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for display filtering
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            //return;
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

        $custom_filters = ['terms_filter_post_type', 'terms_filter_taxonomy', 'taxonomy_type'];

        foreach ($custom_filters as $custom_filter) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameters for filtering
            $filter_value = !empty($_REQUEST[$custom_filter]) ? sanitize_text_field(wp_unslash($_REQUEST[$custom_filter])) : '';
            echo '<input type="hidden" name="' . esc_attr($custom_filter) . '" value="' . esc_attr($filter_value) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button($text, '', '', false, ['id' => 'taxopress-terms-search-submit']); ?>
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
        $per_page = $this->get_items_per_page('st_terms_per_page', 20);

        /**
         * Fetch the data
         */
        $data = $this->get_st_Terms();
        if (!empty($data)) {
            update_termmeta_cache(wp_list_pluck($data, 'term_id'));
        }

        /**
         * Pagination.
         */
        $current_page = $this->get_pagenum();
        $total_items  = $this->record_count();

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
        $taxonomy = get_taxonomy($item->taxonomy);

        //Build row actions
        $actions = [];

        if (current_user_can('edit_term', $item->term_id)) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'taxonomy'      => $item->taxonomy,
                        'tag_ID'        => $item->term_id,
                        'post_type'     => isset($taxonomy->object_type[0]) ? $taxonomy->object_type[0] : 'post',
                    ],
                    admin_url('term.php')
                ),
                esc_html__('Edit', 'simple-tags')
            );
        }

        // Only add other actions if not viewing via taxopress_terms_taxonomy
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading non-state-modifying REQUEST parameter for taxonomy filtering
        if (empty($_REQUEST['taxopress_terms_taxonomy'])) {
            if (current_user_can('edit_term', $item->term_id)) {
                $actions['inline hide-if-no-js'] = sprintf(
                    '<button type="button" class="button-link editinline" aria-label="%s" aria-expanded="false" data-taxonomy="' . $taxonomy->name . '" data-term-id="' . $item->term_id . '">%s</button>',
                    /* translators: %s: Taxonomy term name. */
                    esc_attr(sprintf(esc_html__('Quick edit &#8220;%s&#8221; inline', 'simple-tags'), $item->name)),
                    esc_html__('Quick&nbsp;Edit', 'simple-tags')
                );
            }

            if (current_user_can('edit_term', $item->term_id)) {
                $actions['remove_posts'] = sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(add_query_arg(
                        taxopress_get_terms_screen_query_args([
                            'action'                 => 'taxopress-remove-from-posts',
                            'taxopress_terms'        => esc_attr($item->term_id),
                            '_wpnonce'               => wp_create_nonce('terms-action-request-nonce')
                        ]),
                        admin_url('admin.php')
                    )),
                    esc_html__('Remove From All Posts', 'simple-tags')
                );
            }

            if (current_user_can('delete_term', $item->term_id)) {
                $actions['delete'] = sprintf(
                    '<a href="%s" class="delete-terms">%s</a>',
                    esc_url(add_query_arg(
                        taxopress_get_terms_screen_query_args([
                            'action'                 => 'taxopress-delete-terms',
                            'taxopress_terms'        => esc_attr($item->term_id),
                            '_wpnonce'               => wp_create_nonce('terms-action-request-nonce')
                        ]),
                        admin_url('admin.php')
                    )),
                    esc_html__('Delete', 'simple-tags')
                );
            }

            if (is_taxonomy_viewable($item->taxonomy)) {
                $actions['view'] = sprintf(
                    '<a href="%s">%s</a>',
                    get_term_link($item->term_id),
                    esc_html__('View', 'simple-tags')
                );
            }

            $actions['copy_term'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg(
                    taxopress_get_terms_screen_query_args([
                        'action'                 => 'taxopress-copy-term',
                        'taxopress_terms'        => esc_attr($item->term_id),
                        '_wpnonce'               => wp_create_nonce('terms-action-request-nonce')
                    ]),
                    admin_url('admin.php')
                )),
                esc_html__('Copy', 'simple-tags')
            );
            $actions = apply_filters('taxopress_terms_row_actions', $actions, $item);
        }
        return $column_name === $primary ? $this->row_actions($actions, false) : '';
    }

    /**
     * Method for synonyms column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_synonyms($item)
    {
        $term_synonyms = taxopress_get_term_synonyms($item->term_id);
        if (!empty($term_synonyms)) {
            return join(', ', $term_synonyms);
        } else {
            return '-';
        }
    }

    /**
     * Method for linked_terms column
     *
     * @param array $item
     *
     * @return string
     */
    protected function column_linked_terms($item)
    {
        $term_linked_terms = taxopress_get_linked_terms($item->term_id);
        if (!empty($term_linked_terms)) {
            $term_linked_term_names = [];
            foreach ($term_linked_terms as $term_linked_term) {
                $linked_term_data = taxopress_get_linked_term_data($term_linked_term, $item->term_id);
                $term_linked_term_names[] = $linked_term_data->term_name . ' (' . $linked_term_data->term_taxonomy . ')';
            }
            return join(', ', $term_linked_term_names);
        } else {
            return '-';
        }
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
        $taxonomy = get_taxonomy($item->taxonomy);

        // Add dashes for hierarchy
        $depth = isset($item->taxopress_depth) ? (int)$item->taxopress_depth : 0;
        $dash = $depth > 0 ? str_repeat('&mdash; ', $depth) : '';

        $title = sprintf(
            '<a href="%1$s"><strong><span class="row-title">%2$s%3$s</span></strong></a>',
            add_query_arg(
                [
                    'taxonomy' => $item->taxonomy,
                    'tag_ID' => $item->term_id,
                    'post_type' => isset($taxonomy->object_type[0]) ? $taxonomy->object_type[0] : 'post',
                ],
                admin_url('term.php')
            ),
            $dash,
            esc_html($item->name)
        );

        $title .= ' <span class="taxopress-term-spinner" style="display:none;vertical-align:middle;"><span class="spinner is-active"></span></span>';

        // Use the already-loaded term object and apply edit filters without an extra uncached DB query.
        $qe_data = sanitize_term(clone $item, $item->taxonomy, 'edit');

        $title .= '<div class="hidden" id="inline_' . $qe_data->term_id . '">';
        $title .= '<div class="taxonomy">' . $item->taxonomy . '</div>';
        $title .= '<div class="name">' . $qe_data->name . '</div>';

        $title .= '<div class="slug">' . apply_filters('editable_slug', $qe_data->slug, $qe_data) . '</div>';
        $title .= '<div class="parent">' . $qe_data->parent . '</div>
        </div>';

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
    protected function column_posttypes($item)
    {
        $posttype = '';
        $sn = 0;
        $taxonomy = get_taxonomy($item->taxonomy);
        foreach ($taxonomy->object_type as $objecttype) {
            $sn++;
            $post_type_object = get_post_type_object($objecttype);
            if (is_object($post_type_object)) {
                $posttype .= $post_type_object->label;
                if ($sn < count($taxonomy->object_type)) {
                    $posttype .= ', ';
                }
            }
        }

        return $posttype;
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
        $term_counts = isset($item->count) ? (int) $item->count : 0;

        return sprintf(
            '<a href="%s" class="">%s</a>',
            add_query_arg(
                [
                    'page' => 'st_posts',
                    'posts_term_filter' => (int) $item->term_id,
                ],
                admin_url('admin.php')
            ),
            number_format_i18n($term_counts)
        );
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

        return term_description($item->term_id);
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
        $taxonomy = get_taxonomy($item->taxonomy);

        if ($taxonomy) {
            $return = sprintf(
                '<a href="%1$s">%2$s</a>',
                add_query_arg(
                    [
                        'page' => 'st_taxonomies',
                        'add' => 'taxonomy',
                        'action' => 'edit',
                        'taxopress_taxonomy' => $taxonomy->name,
                    ],
                    taxopress_admin_url('admin.php')
                ),
                esc_html($taxonomy->labels->name)
            );
        } else {
            $return = '&mdash;';
        }

        return $return;
    }

    /**
     * Outputs the hidden row displayed when inline editing
     *
     * @since 3.1.0
     */
    public function inline_edit()
    {
        ?>

        <form method="get">
            <table style="display: none">
                <tbody id="inlineedit">

                    <tr id="inline-edit" class="inline-edit-row" style="display: none">
                        <td colspan="<?php echo esc_attr($this->get_column_count()); ?>" class="colspanchange">

                            <fieldset>
                                <legend class="inline-edit-legend"><?php esc_html_e('Quick Edit', 'simple-tags'); ?></legend>
                                <div class="inline-edit-col">
                                    <label>
                                        <span class="title"><?php _ex('Name', 'term name', 'simple-tags'); ?></span>
                                        <span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
                                    </label>

                                    <label>
                                        <span class="title"><?php esc_html_e('Slug', 'simple-tags'); ?></span>
                                        <span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value="" /></span>
                                    </label>
                                    <label>
                                        <span class="taxonomy"><?php _ex('Taxonomy', 'term name', 'simple-tags'); ?></span>

                                        <?php $taxonomies = get_all_taxopress_taxonomies(); ?>
                                        <select class="input-text-wrap edit-tax edit_taxonomy" name="edit_taxonomy">
                                            <?php
                                            foreach ($taxonomies as $taxonomy) {
                                                echo '<option value="' . esc_attr($taxonomy->name) . '">' . esc_html($taxonomy->labels->name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </label>
                                </div>
                            </fieldset>

                            <div class="inline-edit-save submit">
                                <button type="button" class="cancel button alignleft"><?php esc_html_e('Cancel', 'simple-tags'); ?></button>
                                <button type="button" class="taxopress-save button button-primary alignright"><?php esc_html_e('Update', 'simple-tags'); ?></button>
                                <span class="spinner"></span>

                                <?php wp_nonce_field('taxinlineeditnonce', '_inline_edit', false); ?>
                                <br class="clear" />

                                <div class="notice notice-error notice-alt inline hidden taxopress-notice">
                                    <p class="error"></p>
                                </div>
                            </div>

                        </td>
                    </tr>

                </tbody>
            </table>
        </form>
        <?php
    }
}
