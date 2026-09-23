<?php

// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore,Squiz.PHP.CommentedOutCode.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedUnsetVariable,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_TagCloud
{
    /**
     * Init Embedded tag cloud
     *
     * SimpleTags_Client_TagCloud constructor.
     */
    public function __construct()
    {
    }

    /**
     * Sort an array without accent for naturel order :)
     *
     * @param string $a
     * @param string $b
     *
     * @return boolean
     */
    public static function uksort_by_name($a = '', $b = '')
    {
        return strnatcasecmp(remove_accents($a), remove_accents($b));
    }

    /**
     * Generate extended tag cloud
     *
     * @param string $args
     *
     * @return string|array
     */
    public static function extendedTagCloud($args = '', $copyright = true)
    {
        $defaults = array(
            // Simple Tag global options defaults
            'selectionby' => 'count',
            'selection'   => 'desc',
            'orderby'     => 'random',
            'order'       => 'asc',
            'format'      => 'border',
            'xformat'     => __('<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>', 'simple-tags'),
            'number'      => 20,
            'notagstext'  => __('No tags.', 'simple-tags'),
            'title'       => __('<h4>Tag Cloud</h4>', 'simple-tags'),
            'maxcolor'    => '#000000',
            'mincolor'    => '#353535',
            'largest'     => 12,
            'smallest'    => 12,
            'unit'        => 'pt',
            'taxonomy'    => 'post_tag', // Note: saved as an option but no UI to set it
            'parent_term' => '',
            'display_mode' => 'parents_and_sub',
            // Simple Tag other defaults
            'size'        => 'true',
            'color'       => 'true',
            'exclude'     => '',
            'exclude_terms' => '',
            'include'     => '',
            'term_selection_mode' => 'automatic',
            'include_terms' => '',
            'limit_days'  => 0,
            'min_usage'   => 0,
            'category'    => 0,
            'ID'          => 0,
            'hide_title'  => 0,
            'hide_output' => 0,
            'post_type'   => '',
            'wrap_class'  => '',
            'link_class'  => '',
            'before'      => '',
            'after'       => '',
            'hide_terms' => 0,
        );

        // Get options
        $options = SimpleTags_Plugin::get_option();
        $enable_hidden_terms = SimpleTags_Plugin::get_option_value('enable_hidden_terms');

        // Get values in DB
        $defaults['selectionby'] = $options['cloud_selectionby'];
        $defaults['selection']   = $options['cloud_selection'];
        $defaults['orderby']     = $options['cloud_orderby'];
        $defaults['order']       = $options['cloud_order'];
        $defaults['format']      = $options['cloud_format'];
        $defaults['xformat']     = $options['cloud_xformat'];
        $defaults['number']      = $options['cloud_limit_qty'];
        $defaults['notagstext']  = $options['cloud_notagstext'];
        $defaults['title']       = $options['cloud_title'];
        $defaults['maxcolor']    = $options['cloud_max_color'];
        $defaults['mincolor']    = $options['cloud_min_color'];
        $defaults['largest']     = $options['cloud_max_size'];
        $defaults['smallest']    = $options['cloud_min_size'];
        $defaults['unit']        = $options['cloud_unit'];
        $defaults['taxonomy']    = $options['cloud_taxonomy'];
        $defaults['parent_term'] = $options['cloud_parent_term'];
        $defaults['display_mode'] = $options['cloud_display_mode'];

        $adv_usage = $options['cloud_adv_usage'];
        if (empty($args)) {
            $args = $adv_usage;
        } else {
            $args = $adv_usage . "&" . $args;
        }
        $args = wp_parse_args($args, $defaults);

        // Add compatibility tips with old field syntax
        if (isset($args['cloud_sort'])) {
            $args['cloud_order'] = $args['cloud_sort'];
            unset($args['cloud_sort']);
        }

        // Translate selection order
        if (isset($args['cloud_order'])) {
            $args['orderby'] = self::compatOldOrder($args['cloud_order'], 'orderby');
            $args['order']   = self::compatOldOrder($args['cloud_order'], 'order');
        }

        // Category names to ID codes
        if (isset($args['category'])) {
            $category = explode(",", $args['category']);
            foreach ($category as $key => $name) {
                $category[ $key ] = is_numeric($name) ? $name : get_cat_ID($name);
                if ($category[ $key ] == 0) {
                    unset($category[ $key ]);
                }
            }
            $args['category'] = implode(",", $category);
        }

        // Get correct taxonomy ?
        $taxonomy = self::_get_current_taxonomy($args['taxonomy']);

        // Get terms
        $term_selection_mode = isset($args['term_selection_mode']) ? $args['term_selection_mode'] : 'automatic';

        if ($term_selection_mode === 'custom') {
            $terms = [];
            if (!empty($args['include_terms'])) {
                $include_terms_array = array_map('trim', explode(',', $args['include_terms']));
                foreach ($include_terms_array as $term_name) {
                    if (!empty($term_name)) {
                        $term = get_term_by('name', $term_name, $taxonomy);
                        if ($term && !is_wp_error($term)) {
                            $terms[] = $term;
                        }
                    }
                }
            }
        } elseif ($term_selection_mode === 'combined') {
            $terms = self::getTags($args, $taxonomy);

            if (!empty($args['include_terms'])) {
                $include_terms_array = array_map('trim', explode(',', $args['include_terms']));
                $existing_term_ids = wp_list_pluck($terms, 'term_id');

                foreach ($include_terms_array as $term_name) {
                    if (!empty($term_name)) {
                        $term = get_term_by('name', $term_name, $taxonomy);
                        if ($term && !is_wp_error($term) && !in_array($term->term_id, $existing_term_ids)) {
                            $terms[] = $term;
                            $existing_term_ids[] = $term->term_id;
                        }
                    }
                }
            }
        } else {
            $terms = self::getTags($args, $taxonomy);
        }

        // Remove hidden terms if enabled
        if ($enable_hidden_terms && !empty($args['hide_terms'])) {
            $hidden_terms = get_transient('taxopress_hidden_terms_' . $args['taxonomy']);
            if (!empty($hidden_terms) && is_array($hidden_terms)) {
                $terms = array_filter($terms, function ($term) use ($hidden_terms) {
                    return !in_array($term->term_id, $hidden_terms);
                });
            }
        }

        extract($args); // Params to variables

        // Process exclude_terms
        if (! empty($exclude_terms)) {
            // Convert the exclude terms string into a set (associative array) for faster lookup
            $exclude_terms_set = array_flip(array_map('strtolower', array_map('trim', explode(',', $exclude_terms))));
        } else {
            $exclude_terms_set = array();
        }

        // Filter out excluded terms
        if (! empty($exclude_terms_set)) {
            // Use array_filter to remove terms that exist in the exclude set
            $terms = array_filter($terms, function ($term) use ($exclude_terms_set) {
                return ! isset($exclude_terms_set[ strtolower($term->name) ]);
            });
        }

        // If empty use default xformat !
        if (empty($xformat)) {
            $xformat = $defaults['xformat'];
        }

        $xformat = taxopress_sanitize_text_field($xformat);

        //remove title if in settings
        if ((int)$hide_title > 0) {
            $title = '';
        }

        if (empty($terms)) {
            // Check if custom mode is selected with empty include_terms
            if ($term_selection_mode === 'custom' && empty($args['include_terms'])) {
                $message_text = __('You have selected Custom mode but the "Custom terms to display" field is empty. Please add terms to display or switch to Automatic mode.', 'simple-tags');
                // Apply the same styling as terms would have
                $font_size = !empty($smallest) ? $smallest : 12;
                $font_color = !empty($mincolor) ? $mincolor : '#353535';
                $font_unit = !empty($unit) ? $unit : 'pt';
                $custom_mode_message = '<span style="font-size: ' . esc_attr($font_size) . esc_attr($font_unit) . '; color: ' . esc_attr($font_color) . ';">' . esc_html($message_text) . '</span>';
                if ((int)$hide_output === 0) {
                    return SimpleTags_Client::output_content('st-tag-cloud', $format, $title, $custom_mode_message, $copyright, '', $wrap_class, $link_class);
                } else {
                    return '';
                }
            }

            if ((int)$hide_output === 0) {
                return SimpleTags_Client::output_content('st-tag-cloud', $format, $title, $notagstext, $copyright, '', $wrap_class, $link_class);
            } else {
                return '';
            }
        }

        $counts = $terms_data = array();
        foreach ((array) $terms as $term) {
            $counts[ $term->name ]     = $term->count;
            $terms_data[ $term->name ] = $term;
        }

        // Remove temp data from memory
        $terms = array();
        unset($terms);

        // Use full RBG code
        if (strlen($maxcolor) == 4) {
            $maxcolor = $maxcolor . substr($maxcolor, 1, strlen($maxcolor));
        }
        if (strlen($mincolor) == 4) {
            $mincolor = $mincolor . substr($mincolor, 1, strlen($mincolor));
        }

        // Check as smallest inferior or egal to largest
        if ($smallest > $largest) {
            $smallest = $largest;
        }

        // Scaling - Hard value for the moment
        $scale_min = 0;
        $scale_max = 10;

        $minval = min($counts);
        $maxval = max($counts);

        $minout = max($scale_min, 0);
        $maxout = max($scale_max, $minout);

        $scale = ($maxval > $minval) ? (($maxout - $minout) / ($maxval - $minval)) : 0;

        // HTML Rel (tag/no-follow)
        $rel = SimpleTags_Client::get_rel_attribut();

        // Remove color marquer if color = false
        if ($color == 'false') {
            $xformat = str_replace('%tag_color%', '', $xformat);
        }

        // Remove size marquer if size = false
        if ($size == 'false') {
            $xformat = str_replace('%tag_size%', '', $xformat);
        }


        // Order terms before output
        // count, name, rand | asc, desc
        $orderby = strtolower($orderby);
        $order = strtolower($order);

        if (in_array($format, ['parent/child']) && $args['display_mode'] === 'parents_and_sub') {
            $terms = self::getTags($args, $taxonomy);

            // Check if terms is empty or invalid
            if (empty($terms)) {
                return SimpleTags_Client::output_content('st-tag-cloud', $format, $title, $notagstext, $copyright, '', $wrap_class, $link_class);
            }

            // First, group terms by parent term
            $grouped_terms = [];
            foreach ($terms as $term) {
                // Only group terms with a parent (excluding standalone terms)
                if ($term->parent != 0) {
                    $parent_id = $term->parent;
                    if (!isset($grouped_terms[$parent_id])) {
                        $grouped_terms[$parent_id] = [];
                    }
                    $grouped_terms[$parent_id][] = $term;
                }
            }

            // Then, sort each group by name
            foreach ($grouped_terms as &$parent_group) {
                usort($parent_group, function ($a, $b) {
                    return strcmp($a->name, $b->name);
                });
            }

            // Merge all grouped terms into a single array, ensuring correct parent-child order
            $terms = [];
            foreach ($grouped_terms as $parent_group) {
                $terms = array_merge($terms, $parent_group);
            }
        }

        // Custom drag-and-drop order: always check first!
        if ($orderby === 'taxopress_term_order') {
            $custom_order = get_option('taxopress_term_order_' . $taxonomy, []);
            if (!empty($custom_order)) {
                $terms_by_id = [];
                foreach ($terms_data as $term_name => $term_obj) {
                    if (is_object($term_obj) && isset($term_obj->term_id)) {
                        $terms_by_id[$term_obj->term_id] = $term_obj;
                    }
                }
                $ordered_terms = [];
                foreach ($custom_order as $term_id) {
                    if (isset($terms_by_id[$term_id])) {
                        $ordered_terms[$terms_by_id[$term_id]->name] = $terms_by_id[$term_id]->count;
                        unset($terms_by_id[$term_id]);
                    }
                }
                // Add any terms not in custom order at the end
                foreach ($terms_by_id as $term_obj) {
                    $ordered_terms[$term_obj->name] = $term_obj->count;
                }
                if ($order === 'desc') {
                    $ordered_terms = array_reverse($ordered_terms, true);
                }
                $counts = $ordered_terms;
            }
        } else {
            // Default sorting logic
            if ($orderby == 'count') {
                asort($counts);
            } elseif ($orderby == 'name') {
                uksort($counts, [__CLASS__, 'uksort_by_name']);
            } else { // rand
                SimpleTags_Client::random_array($counts);
            }
            $order = strtolower($order);
            if ($order == 'desc' && $orderby != 'random') {
                $counts = array_reverse($counts, true);
            }
        }

        $output = array();

        //update xformat with class link class
        if (!empty(trim($link_class))) {
            $link_class = taxopress_format_class($link_class);
            $xformat = taxopress_add_class_to_format($xformat, $link_class);
        }

        foreach ((array) $counts as $term_name => $count) {
            if (! is_object($terms_data[ $term_name ])) {
                continue;
            }

            $term         = $terms_data[ $term_name ];
            $scale_result = (int) ($scale <> 0 ? (($term->count - $minval) * $scale + $minout) : ($scale_max - $scale_min) / 2);
            //$output[]     = SimpleTags_Client::format_internal_tag( $xformat, $term, $rel, $scale_result, $scale_max, $scale_min, $largest, $smallest, $unit, $maxcolor, $mincolor );
            $formatted = SimpleTags_Client::format_internal_tag($xformat, $term, $rel, $scale_result, $scale_max, $scale_min, $largest, $smallest, $unit, $maxcolor, $mincolor);
            if ($format === 'table') {
                $output[] = [
                    'html' => $formatted,
                    'count' => $term->count,
                    'term_name' => $term->name,
                ];
            } else {
                $output[] = $formatted;
            }
        }

        return SimpleTags_Client::output_content('st-tag-cloud', $format, $title, $output, $copyright, '', $wrap_class, $link_class, $before, $after, $taxonomy);
    }


    /**
     * Generate extended tag result
     *
     * @param string $args
     *
     * @return array
     */
    public static function extendedTagResult($args = '', $copyright = true)
    {
        $defaults = array(
            // Simple Tag global options defaults
            'selectionby' => 'count',
            'selection'   => 'desc',
            'orderby'     => 'random',
            'order'       => 'asc',
            'format'      => 'border',
            'xformat'     => __('<a href="%tag_link%" id="tag-link-%tag_id%" class="st-tags t%tag_scale%" title="%tag_count% topics" %tag_rel% style="%tag_size% %tag_color%">%tag_name%</a>', 'simple-tags'),
            'number'      => 20,
            'notagstext'  => __('No tags.', 'simple-tags'),
            'title'       => __('<h4>Tag Cloud</h4>', 'simple-tags'),
            'maxcolor'    => '#000000',
            'mincolor'    => '#353535',
            'largest'     => 12,
            'smallest'    => 12,
            'unit'        => 'pt',
            'taxonomy'    => 'post_tag', // Note: saved as an option but no UI to set it
            'parent_term' => '',
            'display_mode' => 'parents_and_sub',
            // Simple Tag other defaults
            'size'        => 'true',
            'color'       => 'true',
            'exclude'     => '',
            'include'     => '',
            'limit_days'  => 0,
            'min_usage'   => 0,
            'category'    => 0,
            'hide_terms' => 0,
        );

        // Get options
        $options = SimpleTags_Plugin::get_option();
        $enable_hidden_terms = SimpleTags_Plugin::get_option_value('enable_hidden_terms');

        // Get values in DB
        $defaults['selectionby'] = $options['cloud_selectionby'];
        $defaults['selection']   = $options['cloud_selection'];
        $defaults['orderby']     = $options['cloud_orderby'];
        $defaults['order']       = $options['cloud_order'];
        $defaults['format']      = $options['cloud_format'];
        $defaults['xformat']     = $options['cloud_xformat'];
        $defaults['number']      = $options['cloud_limit_qty'];
        $defaults['notagstext']  = $options['cloud_notagstext'];
        $defaults['title']       = $options['cloud_title'];
        $defaults['maxcolor']    = $options['cloud_max_color'];
        $defaults['mincolor']    = $options['cloud_min_color'];
        $defaults['largest']     = $options['cloud_max_size'];
        $defaults['smallest']    = $options['cloud_min_size'];
        $defaults['unit']        = $options['cloud_unit'];
        $defaults['taxonomy']    = $options['cloud_taxonomy'];
        $defaults['parent_term'] = $options['cloud_parent_term'];
        $defaults['display_mode'] = $options['cloud_display_mode'];

        $adv_usage = $options['cloud_adv_usage'];
        if (empty($args)) {
            $args = $adv_usage;
        } else {
            $args = $adv_usage . "&" . $args;
        }
        $args = wp_parse_args($args, $defaults);

        // Add compatibility tips with old field syntax
        if (isset($args['cloud_sort'])) {
            $args['cloud_order'] = $args['cloud_sort'];
            unset($args['cloud_sort']);
        }

        // Translate selection order
        if (isset($args['cloud_order'])) {
            $args['orderby'] = self::compatOldOrder($args['cloud_order'], 'orderby');
            $args['order']   = self::compatOldOrder($args['cloud_order'], 'order');
        }

        // Category names to ID codes
        if (isset($args['category'])) {
            $category = explode(",", $args['category']);
            foreach ($category as $key => $name) {
                $category[ $key ] = is_numeric($name) ? $name : get_cat_ID($name);
                if ($category[ $key ] == 0) {
                    unset($category[ $key ]);
                }
            }
            $args['category'] = implode(",", $category);
        }

        // Get correct taxonomy ?
        $taxonomy = self::_get_current_taxonomy($args['taxonomy']);

        // Get terms
        $terms = self::getTags($args, $taxonomy);

        // Remove hidden terms if enabled
        if ($enable_hidden_terms && !empty($args['hide_terms'])) {
            $hidden_terms = get_transient('taxopress_hidden_terms_' . $args['taxonomy']);
            if (!empty($hidden_terms) && is_array($hidden_terms)) {
                $terms = array_filter($terms, function ($term) use ($hidden_terms) {
                    return !in_array($term->term_id, $hidden_terms);
                });
            }
        }
        extract($args); // Params to variables

        // If empty use default xformat !
        if (empty($xformat)) {
            $xformat = $defaults['xformat'];
        }

        if (empty($terms)) {
            return [];
        }

        $counts = $terms_data = array();
        foreach ((array) $terms as $term) {
            $counts[ $term->name ]     = $term->count;
            $terms_data[ $term->name ] = $term;
        }

        // Remove temp data from memory
        $terms = array();
        unset($terms);

        // Use full RBG code
        if (strlen($maxcolor) == 4) {
            $maxcolor = $maxcolor . substr($maxcolor, 1, strlen($maxcolor));
        }
        if (strlen($mincolor) == 4) {
            $mincolor = $mincolor . substr($mincolor, 1, strlen($mincolor));
        }

        // Check as smallest inferior or egal to largest
        if ($smallest > $largest) {
            $smallest = $largest;
        }

        // Scaling - Hard value for the moment
        $scale_min = 0;
        $scale_max = 10;

        $minval = min($counts);
        $maxval = max($counts);

        $minout = max($scale_min, 0);
        $maxout = max($scale_max, $minout);

        $scale = ($maxval > $minval) ? (($maxout - $minout) / ($maxval - $minval)) : 0;

        // HTML Rel (tag/no-follow)
        $rel = SimpleTags_Client::get_rel_attribut();

        // Remove color marquer if color = false
        if ($color == 'false') {
            $xformat = str_replace('%tag_color%', '', $xformat);
        }

        // Remove size marquer if size = false
        if ($size == 'false') {
            $xformat = str_replace('%tag_size%', '', $xformat);
        }

        // Order terms before output
        // count, name, rand | asc, desc

        $orderby = strtolower($orderby);
        if ($orderby == 'count') {
            asort($counts);
        } elseif ($orderby == 'name') {
            uksort($counts, array( __CLASS__, 'uksort_by_name' ));
        } else { // rand
            SimpleTags_Client::random_array($counts);
        }

        $order = strtolower($order);
        if ($order == 'desc' && $orderby != 'random') {
            $counts = array_reverse($counts);
        }

        $output = array();
        foreach ((array) $counts as $term_name => $count) {
            if (! is_object($terms_data[ $term_name ])) {
                continue;
            }
            $output[]   = $terms_data[ $term_name ];
        }


        return $output;
    }

    /**
     * Check if taxonomy exist and return it, otherwise return default post tags.
     *
     * @param string|array $taxonomies
     * @param boolean $force_single
     *
     * @return array|string
     * @author WebFactory Ltd
     */
    public static function _get_current_taxonomy($taxonomies, $force_single = false)
    {
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $key => $value) {
                if (! taxonomy_exists($value)) { // Remove from array is taxonomy not exist !
                    unset($taxonomies[ $key ]);
                } elseif (true === $force_single) {// Force single instead array ?
                    return $value;
                }
            }

            return $taxonomies;
        } elseif (taxonomy_exists($taxonomies)) {
            return $taxonomies;
        }

        return 'post_tag';
    }

    /**
     * Extended get_tags public static function that use getTerms function
     *
     * @param string|array $args
     * @param string|array $taxonomy
     *
     * @return array
     */
    public static function getTags($args = '', $taxonomy = 'post_tag', $post_type = '')
    {
        $key = md5(maybe_serialize($args) . $taxonomy . (isset($args['parent_term']) ? $args['parent_term'] : '') . (isset($args['display_mode']) ? $args['display_mode'] : ''));

        // Get cache if exist
        if ($cache = wp_cache_get('st_get_tags', 'simple-tags')) {
            if (isset($cache[ $key ])) {
                return apply_filters('get_tags', $cache[ $key ], $args);
            }
        }

        // Get tags
        if (isset($args['taxonomy'])) {
            $taxonomy = $args['taxonomy'];
        }

        if (isset($args['parent_term'])) {
            $parent_term = $args['parent_term'];
        }

        if (isset($args['display_mode'])) {
            $display_mode = $args['display_mode'];
        }

        if (isset($args['max'])) {
            $max_terms = intval($args['max']);
        } else {
            $max_terms = 20;
        }

        if (isset($args['hide_terms'])) {
            $hide_terms = $args['hide_terms'];
        }

        $term_args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number' => $max_terms,
        ];

        // exclude hidden terms from being queried for display at all
        if ($hide_terms && SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
            $hidden_terms = get_transient('taxopress_hidden_terms_' . $taxonomy);
            if (!empty($hidden_terms) && is_array($hidden_terms)) {
                $term_args['exclude'] = $hidden_terms;
            }
        }

        $is_hierarchical = is_taxonomy_hierarchical($taxonomy);

        if ($is_hierarchical && isset($args['parent_term'])) {
            $parent_term = $args['parent_term'];
            $display_mode = isset($args['display_mode']) ? $args['display_mode'] : '';

            if ($parent_term === 'all') {
                if ($display_mode === 'parents_only') {
                    $term_args['parent'] = 0;
                } elseif ($display_mode === 'sub_terms_only') {
                    // Get all parent terms
                    $parent_terms = get_terms([
                        'taxonomy'   => $taxonomy,
                        'parent'     => 0,
                        'hide_empty' => false
                    ]);

                    $parent_ids = wp_list_pluck($parent_terms, 'term_id');

                    if (!empty($parent_ids)) {
                        $sub_terms = [];
                        foreach ($parent_ids as $parent_id) {
                            $terms = get_terms([
                                'taxonomy'   => $taxonomy,
                                'parent'     => $parent_id,
                                'hide_empty' => false,
                                'number'     => $max_terms
                            ]);
                            $sub_terms = array_merge($sub_terms, $terms);
                            if (count($sub_terms) >= $max_terms) {
                                break;
                            }
                        }
                        $term_args['include'] = wp_list_pluck($sub_terms, 'term_id');
                    } else {
                        $term_args['parent'] = -1;
                    }
                }
            } else {
                // Specific parent term selected
                if ($display_mode === 'parents_only') {
                    $term_args['include'] = [$parent_term];
                } elseif ($display_mode === 'sub_terms_only') {
                    $term_args['parent'] = $parent_term;
                } else {
                    // Both parent and sub-terms
                    $parent_terms = get_terms([
                        'taxonomy'   => $taxonomy,
                        'include'    => [$parent_term],
                        'hide_empty' => false
                    ]);
                    $sub_terms = get_terms([
                        'taxonomy'   => $taxonomy,
                        'parent'     => $parent_term,
                        'hide_empty' => false,
                        'number'     => $max_terms
                    ]);

                    $all_terms = array_merge($parent_terms, $sub_terms);
                    $term_args['include'] = wp_list_pluck(array_slice($all_terms, 0, $max_terms), 'term_id');
                }
            }
        }

        // Ensure `max_terms` is always applied
        if (!empty($term_args['include'])) {
            $term_args['include'] = array_slice($term_args['include'], 0, $max_terms);
        }


        if (isset($args['orderby'])) {
            $term_args['orderby'] = $args['orderby'];
        }
        if (isset($args['order'])) {
            $term_args['order'] = $args['order'];
        }

        if (!empty($args['limit_days'])) {
            $recent_posts_args = [
                'post_type'      => $post_type ?: 'any',
                'post_status'    => 'publish',
                'date_query'     => [
                    [
                        'after'     => $args['limit_days'] . ' days ago',
                        'inclusive' => false,
                    ],
                ],
                'fields'         => 'ids',
                'posts_per_page' => 100,
            ];
            $recent_posts      = [];
            $page              = 1;

            do {
                $recent_posts_args['paged'] = $page;
                $recent_posts_batch         = get_posts($recent_posts_args);
                $recent_posts               = array_merge($recent_posts, $recent_posts_batch);
                $page++;
            } while (count($recent_posts_batch) === $recent_posts_args['posts_per_page']);
            if (!empty($recent_posts)) {
                $terms = get_terms([
                    'taxonomy'   => $taxonomy,
                    'object_ids' => $recent_posts,
                    'hide_empty' => false,
                    'number'     => $max_terms,
                ]);
            } else {
                $terms = [];
            }
        } else {
            $terms = get_terms($term_args);
        }

        if (isset($args['orderby']) && strtolower($args['orderby']) === 'random' && is_array($terms)) {
            shuffle($terms);
            if (isset($args['order']) && strtolower($args['order']) === 'desc') {
                $terms = array_reverse($terms);
            }
        }
        if (empty($terms)) {
            return [];
        }

        if ($hide_terms && SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
            $hidden_terms = get_transient('taxopress_hidden_terms_' . $taxonomy);
            if (!empty($hidden_terms) && is_array($hidden_terms)) {
                $hidden_terms = array_flip($hidden_terms);
                $terms = array_filter($terms, function ($term) use ($hidden_terms) {
                    return !isset($hidden_terms[$term->term_id]);
                });
            }
        }

        // Cache the result
        $cache = [];
        $cache[$key] = $terms;
        wp_cache_set('st_get_tags', $cache, 'simple-tags');

        $terms = apply_filters('st_get_tags', $terms, $args);

        return $terms;
    }

    /**
     * Helper public static function for keep compatibility with old options TaxoPress widgets
     *
     * @param string $old_value
     * @param string $key
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function compatOldOrder($old_value = '', $key = '')
    {
        $return = array();

        switch (strtolower($old_value)) { // count-asc/count-desc/name-asc/name-desc/random
            case 'count-asc':
                $return = array( 'orderby' => 'count', 'order' => 'ASC' );
                break;
            case 'rand':
            case 'random':
                $return = array( 'orderby' => 'random', 'order' => '' );
                break;
            case 'name-asc':
                $return = array( 'orderby' => 'name', 'order' => 'ASC' );
                break;
            case 'name-desc':
                $return = array( 'orderby' => 'name', 'order' => 'DESC' );
                break;
            case 'count-desc':
                $return = array( 'orderby' => 'count', 'order' => 'DESC' );
                break;
        }

        if (empty($return) || ! isset($return[ $key ])) {
            return $old_value;
        }

        return $return[ $key ];
    }

    /**
     * Extended get_terms public static function support
     * - Limit category
     * - Limit days
     * - Selection restrict
     * - Min usage
     *
     * @param string|array $taxonomies
     * @param string $args
     *
     * @return array|WP_Error
     */
    public static function getTerms($taxonomies, $args = '')
    {
        global $wpdb;
        $empty_array   = array();
        $join_relation = false;

        $single_taxonomy = false;
        if (! is_array($taxonomies)) {
            $single_taxonomy = true;
            $taxonomies      = array( $taxonomies );
        }

        foreach ((array) $taxonomies as $taxonomy) {
            if (! taxonomy_exists($taxonomy)) {
                $error = new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));

                return $error;
            }
        }
        $in_taxonomies = "'" . implode("', '", $taxonomies) . "'";

        $defaults = array(
            'orderby'       => 'name',
            'order'         => 'ASC',
            'hide_empty'    => true,
            'exclude'       => array(),
            'exclude_tree'  => array(),
            'include'       => array(),
            'number'        => '',
            'fields'        => 'all',
            'slug'          => '',
            'parent'        => '',
            'hierarchical'  => true,
            'child_of'      => 0,
            'get'           => '',
            'name__like'    => '',
            'pad_counts'    => false,
            'offset'        => '',
            'search'        => '',
            // TaxoPress added
            'limit_days'    => 0,
            'category'      => 0,
            'min_usage'     => 0,
            'st_name__like' => '',
            'post_type'     => false
        );

        $args = wp_parse_args($args, $defaults);

        // Translate selection order
        $args['orderby'] = self::compatOldOrder($args['selectionby'], 'orderby');
        $args['order']   = self::compatOldOrder($args['selection'], 'order');

        $args['number']     = absint($args['number']);
        $args['offset']     = absint($args['offset']);
        $args['limit_days'] = absint($args['limit_days']);
        $args['min_usage']  = absint($args['min_usage']);

        if (
            ! $single_taxonomy || ! is_taxonomy_hierarchical($taxonomies[0]) ||
             '' !== $args['parent']
        ) {
            $args['child_of']     = 0;
            $args['hierarchical'] = false;
            $args['pad_counts']   = false;
        }

        if ('all' == $args['get']) {
            $args['child_of']     = 0;
            $args['hide_empty']   = 0;
            $args['hierarchical'] = false;
            $args['pad_counts']   = false;
        }
        extract($args, EXTR_SKIP);

        if ($child_of) {
            $hierarchy = _get_term_hierarchy($taxonomies[0]);
            if (! isset($hierarchy[ $child_of ])) {
                return $empty_array;
            }
        }

        if ($parent) {
            $hierarchy = _get_term_hierarchy($taxonomies[0]);
            if (! isset($hierarchy[ $parent ])) {
                return $empty_array;
            }
        }

        // $args can be whatever, only use the args defined in defaults to compute the key
        $filter_key   = (has_filter('list_terms_exclusions')) ? serialize($GLOBALS['wp_filter']['list_terms_exclusions']) : '';
        $key          = md5(serialize(compact(array_keys($defaults))) . serialize($taxonomies) . $filter_key);
        $last_changed = wp_cache_get('last_changed', 's-terms');
        if (! $last_changed) {
            $last_changed = time();
            wp_cache_set('last_changed', $last_changed, 's-terms');
        }
        $cache_key = "get_terms:$key:$last_changed";
        $cache     = wp_cache_get($cache_key, 's-terms');
        if (false !== $cache) {
            $cache = apply_filters('get_terms', $cache, $taxonomies, $args);

            return $cache;
        }

        $_orderby = strtolower($orderby);
        if ('count' == $_orderby) {
            $orderby = 'tt.count';
        }
        if ('random' == $_orderby) {
            $orderby = 'RAND()';
        } elseif ('name' == $_orderby) {
            $orderby = 't.name';
        } elseif ('slug' == $_orderby) {
            $orderby = 't.slug';
        } elseif ('term_group' == $_orderby) {
            $orderby = 't.term_group';
        } elseif (empty($_orderby) || 'id' == $_orderby) {
            $orderby = 't.term_id';
        }
        $orderby = apply_filters('get_terms_orderby', $orderby, $args);

        if (! empty($orderby)) {
            $orderby = "ORDER BY $orderby";
        } else {
            $order = '';
        }

        $where      = '';
        $inclusions = '';
        if (! empty($include)) {
            $exclude      = '';
            $exclude_tree = '';
            $interms      = wp_parse_id_list($include);
            foreach ($interms as $interm) {
                if (empty($inclusions)) {
                    $inclusions = ' AND ( t.term_id = ' . intval($interm) . ' ';
                } else {
                    $inclusions .= ' OR t.term_id = ' . intval($interm) . ' ';
                }
            }
        }

        if (! empty($inclusions)) {
            $inclusions .= ')';
        }
        $where .= $inclusions;

        $exclusions = '';

        $excluded_terms = array(); // Collect all terms to exclude

        if (! empty($exclude_tree)) {
            $excluded_trunks = wp_parse_id_list($exclude_tree);
            foreach ($excluded_trunks as $extrunk) {
                $excluded_children   = (array) get_terms(array(
                    'taxonomy' => $taxonomies[0],
                    'child_of'  => intval($extrunk),
                    'fields'    => 'ids',
                ));
                $excluded_children[] = $extrunk;
                foreach ($excluded_children as $exterm) {
                    if (empty($exclusions)) {
                        $exclusions = ' AND ( t.term_id <> ' . intval($exterm) . ' ';
                    } else {
                        $exclusions .= ' AND t.term_id <> ' . intval($exterm) . ' ';
                    }
                }

                // Also collect terms for batch exclusion
                $excluded_terms = array_merge($excluded_terms, $excluded_children);
            }
        }

        if (! empty($exclude)) {
            $exterms = wp_parse_id_list($exclude);
            foreach ($exterms as $exterm) {
                if (empty($exclusions)) {
                    $exclusions = ' AND ( t.term_id <> ' . intval($exterm) . ' ';
                } else {
                    $exclusions .= ' AND t.term_id <> ' . intval($exterm) . ' ';
                }
            }

            // Also collect terms for batch exclusion
            $excluded_terms = array_merge($excluded_terms, $exterms);
        }

        // Batch exclusion logic with 'NOT IN'
        $excluded_terms = array_unique($excluded_terms);
        if (! empty($excluded_terms)) {
            $excluded_terms_sql = implode(',', array_map('intval', $excluded_terms));
            // Batch exclusion will be combined with original exclusion logic
            $exclusions .= " AND t.term_id NOT IN ($excluded_terms_sql)";
        }

        if (! empty($exclusions)) {
            $exclusions .= ')';
        }
        $exclusions = apply_filters('list_terms_exclusions', $exclusions, $args);
        $where      .= $exclusions;

        // ST Features : Restrict category
        if (!empty($category)) {
            if (! is_array($taxonomies)) {
                $taxonomies = array( $taxonomies );
            }

            $incategories = wp_parse_id_list($category);

            $taxonomies   = "'" . implode("', '", $taxonomies) . "'";
            $incategories = "'" . implode("', '", $incategories) . "'";

            $where .= " AND tr.object_id IN ( ";
            $where .= "SELECT tr.object_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN $wpdb->posts as p ON tr.object_id=p.ID WHERE tt.term_id IN ($incategories) AND p.post_status='publish'";
            $where .= " ) ";
            $join_relation = true;
            unset($incategories, $category);
        }

        // ST Features : Limit posts date
        if ((int)$limit_days > 0) {
            if ($post_type) {
                $post_type = "AND post_type = '$post_type'";
            } else {
                $post_type = '';
            }
            $where .= " AND tr.object_id IN ( ";
            $where .= "SELECT DISTINCT ID FROM $wpdb->posts AS p WHERE p.post_date_gmt > '" . gmdate('Y-m-d H:i:s', time() - $limit_days * 86400) . "' $post_type";
            $where .= " ) ";
            $join_relation = true;
            unset($limit_days);
        } else {
            if ($post_type) {
                $where .= " AND tr.object_id IN ( ";
                $where .= "SELECT DISTINCT ID FROM $wpdb->posts AS p WHERE post_type = '$post_type'";
                $where .= " ) ";
                $join_relation = true;
            }
        }

        if (! empty($slug)) {
            $slug  = sanitize_title($slug);
            $where .= " AND t.slug = '$slug'";
        }

        if (! empty($name__like)) {
            $where .= " AND t.name LIKE '{$name__like}%'";
        }

        if ('' !== $parent) {
            $parent = (int) $parent;
            $where  .= " AND tt.parent = '$parent'";
        }

        // ST Features : Another way to search
        if (strpos($st_name__like, ' ') !== false) {
            $st_terms_formatted = array();
            $st_terms           = preg_split('/[\s,]+/', $st_name_like);
            foreach ((array) $st_terms as $st_term) {
                if (empty($st_term)) {
                    continue;
                }
                $st_terms_formatted[] = "t.name LIKE '%" . $wpdb->esc_like($st_term) . "%'";
            }

            $where .= " AND ( " . explode(' OR ', $st_terms_formatted) . " ) ";
            unset($st_term, $st_terms_formatted, $st_terms);
        } elseif (! empty($st_name__like)) {
            $where .= " AND t.name LIKE '%{$st_name__like}%'";
        }

        if (in_array($taxonomies[0], ['post_tag', 'category'])) {
            //TODO - count not working for attachment and/or CPT ?
            // ST Features : Add min usage
            if ($hide_empty && ! $hierarchical) {
                if ($min_usage == 0) {
                    $where .= ' AND tt.count > 0';
                } else {
                    $where .= $wpdb->prepare(' AND tt.count >= %d', $min_usage);
                }
            }
        }


        if (! empty($search)) {
            $search = $wpdb->esc_like($search);
            $where  .= " AND (t.name LIKE '%$search%')";
        }

        $selects = array();
        switch ($fields) {
            case 'all':
                $selects = array( 't.*', 'tt.*' );
                break;
            case 'ids':
            case 'id=>parent':
                $selects = array( 't.term_id', 'tt.parent', 'tt.count' );
                break;
            case 'names':
                $selects = array( 't.term_id', 'tt.parent', 'tt.count', 't.name' );
                break;
            case 'count':
                $orderby = '';
                $order   = '';
                $selects = array( 'COUNT(*)' );
        }
        $select_this = implode(', ', apply_filters('get_terms_fields', $selects, $args));

        // Add inner to relation table ?
        $join_relation = $join_relation == false ? '' : "INNER JOIN $wpdb->term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id";
        // Query parts are individually escaped above, $taxonomies are individually checked if they exists


        // don't limit the query results when we have to descend the family tree
        if (! empty($number) && ! $hierarchical && empty($child_of) && '' == $parent) {
            if ($offset) {
                $query = $wpdb->prepare(
                    "SELECT DISTINCT $select_this
			FROM $wpdb->terms AS t
			INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
			$join_relation
			WHERE 1 = %d AND tt.taxonomy IN ($in_taxonomies)
			$where
			$orderby $order
            LIMIT %d, %d",
                    1,
                    $offset,
                    $number
                );
            } else {
                $query = $wpdb->prepare(
                    "SELECT DISTINCT $select_this
			FROM $wpdb->terms AS t
			INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
			$join_relation
			WHERE 1 = %d AND tt.taxonomy IN ($in_taxonomies)
			$where
			$orderby $order
            LIMIT %d",
                    1,
                    $number
                );
            }
        } else {
            $query = $wpdb->prepare(
                "SELECT DISTINCT $select_this
			FROM $wpdb->terms AS t
			INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
			$join_relation
			WHERE 1 = %d AND tt.taxonomy IN ($in_taxonomies)
			$where
			$orderby $order",
                1
            );
        }

        // GROUP BY t.term_id

        if ('count' == $fields) {
            $term_count = $wpdb->get_var($query);

            return $term_count;
        }

        $terms = $wpdb->get_results($query);
        if ('all' == $fields) {
            update_term_cache($terms);
        }

        if (empty($terms)) {
            wp_cache_add($cache_key, array(), 's-terms');
            $terms = apply_filters('get_terms', array(), $taxonomies, $args);

            return $terms;
        }

        if ($child_of) {
            $children = _get_term_hierarchy($taxonomies[0]);
            if (! empty($children)) {
                $terms = &_get_term_children($child_of, $terms, $taxonomies[0]);
            }
        }

        // Update term counts to include children.
        if ($pad_counts && 'all' == $fields) {
            _pad_term_counts($terms, $taxonomies[0]);
        }

        // Make sure we show empty categories that have children.
        if ($hierarchical && $hide_empty && is_array($terms)) {
            foreach ($terms as $k => $term) {
                if (! $term->count) {
                    $children = _get_term_children($term->term_id, $terms, $taxonomies[0]);
                    if (is_array($children)) {
                        foreach ($children as $child) {
                            if ($child->count) {
                                continue 2;
                            }
                        }
                    }

                    // It really is empty
                    unset($terms[ $k ]);
                }
            }
        }
        reset($terms);

        $_terms = array();
        if ('id=>parent' == $fields) {
            while ($term = array_shift($terms)) {
                $_terms[ $term->term_id ] = $term->parent;
            }
            $terms = $_terms;
        } elseif ('ids' == $fields) {
            while ($term = array_shift($terms)) {
                $_terms[] = $term->term_id;
            }
            $terms = $_terms;
        } elseif ('names' == $fields) {
            while ($term = array_shift($terms)) {
                $_terms[] = $term->name;
            }
            $terms = $_terms;
        }

        if (0 < $number && intval(@count($terms)) > $number) {
            $terms = array_slice($terms, $offset, $number);
        }

        wp_cache_add($cache_key, $terms, 's-terms');

        $terms = apply_filters('get_terms', $terms, $taxonomies, $args);

        return $terms;
    }
}
