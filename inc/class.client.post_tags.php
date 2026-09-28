<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_PostTags
{
    /**
     * SimpleTags_Client_PostTags constructor.
     */
    public function __construct()
    {
    }


    /**
     * Generate current post tags
     *
     * @param string $args
     * @param bool $copyright
     *
     * @return string|array|boolean
     */
    public static function extendedPostTags($args = '', $copyright = true)
    {
        // Get options
        $options = SimpleTags_Plugin::get_option();
        $enable_hidden_terms = SimpleTags_Plugin::get_option_value('enable_hidden_terms');

        // Default values
        $defaults = array(
            'before'    => __('Tags: ', 'simple-tags'),
            'separator' => ', ',
            'after'     => '<br />',
            'post_id'   => 0,
            'inc_cats'  => 0,
            'xformat'   => __('<a href="%tag_link%" title="%tag_name%" class="st-post-tags t%tag_scale%" style="%tag_size% %tag_color%" %tag_rel%>%tag_name%</a>', 'simple-tags'),
            'notagtext' => __('No tag for this post.', 'simple-tags'),
            'number'    => 0,
            'format'    => 'flat',
            'ID'        => false,
            'taxonomy'  => false,
            'embedded'  => false,
            'feed'      => false,
            'hide_output' => 0,
            'wrap_class'  => '',
            'link_class'  => '',
            'hide_terms' => 0,
            'smallest'   => 12,
            'largest'    => 12,
            'unit'       => 'pt',
            'color'      => true,
            'mincolor'   => '#353535',
            'maxcolor'   => '#000000',
            'selectionby' => 'count',
            'selection'   => 'desc',
            'orderby'     => 'random',
            'order'       => 'asc',
            'limit_days'  => 0,
        );

        // Get values in DB
        $defaults['before']    = $options['tt_before'];
        $defaults['separator'] = $options['tt_separator'];
        $defaults['after']     = $options['tt_after'];
        $defaults['inc_cats']  = $options['tt_inc_cats'];
        $defaults['xformat']   = $options['tt_xformat'];
        $defaults['format']    = $options['tt_format'];
        $defaults['notagtext'] = $options['tt_notagstext'];
        $defaults['number']    = (int) $options['tt_number'];
        $defaults['smallest']  = isset($options['tt_min_size']) ? (int)$options['tt_min_size'] : 12;
        $defaults['largest']   = isset($options['tt_max_size']) ? (int)$options['tt_max_size'] : 12;
        $defaults['unit']      = isset($options['tt_unit']) ? $options['tt_unit'] : 'pt';
        $defaults['color']     = isset($options['tt_color']) ? $options['tt_color'] : 1;
        $defaults['mincolor']  = isset($options['tt_min_color']) ? $options['tt_min_color'] : '#353535';
        $defaults['maxcolor']  = isset($options['tt_max_color']) ? $options['tt_max_color'] : '#000000';
        $defaults['selectionby'] = isset($options['tt_selectionby']) ? $options['tt_selectionby'] : 'count';
        $defaults['selection']   = isset($options['tt_selection']) ? $options['tt_selection'] : 'desc';
        $defaults['orderby']     = isset($options['tt_orderby']) ? $options['tt_orderby'] : 'random';
        $defaults['order']       = isset($options['tt_order']) ? $options['tt_order'] : 'asc';
        $defaults['limit_days']  = isset($options['tt_limit_days']) ? (int)$options['tt_limit_days'] : 0;
        if (empty($args)) {
            $args = $options['tt_adv_usage'];
        }

        // Extract data in variables
        $args = wp_parse_args($args, $defaults);
        extract($args);

        // If empty use default xformat !
        if (empty($xformat)) {
            $xformat = $defaults['xformat'];
        }

        $xformat = taxopress_sanitize_text_field($xformat);

        // Choose post ID
        $object_id = (int) $post_id;
        if (0 === $object_id) {
            global $post;
            if (! isset($post->ID) || 0 === (int) $post->ID) {
                return false;
            }

            $object_id = (int) $post->ID;
        }

        // Get categories ?
        if ($ID) {
            $taxonomies = $taxonomy;
        } else {
            $taxonomies = (0 === (int) $inc_cats) ? 'post_tag' : array( 'post_tag', 'category' );
        }

        $hidden_terms = [];
        if (!empty($enable_hidden_terms) && !empty($args['hide_terms']) && !empty($args['taxonomy'])) {
            $hidden_terms = get_transient('taxopress_hidden_terms_' . $args['taxonomy']);
            if (!empty($hidden_terms) && is_array($hidden_terms)) {
                $args['exclude'] = implode(',', $hidden_terms);
            }
        }

        // Get terms
        // According to codex https://developer.wordpress.org/reference/functions/get_object_term_cache/, $taxonomy must be a string
        $terms = array();
        foreach ((array) $taxonomies as $taxonomy) {
            $taxterms = get_object_term_cache($object_id, $taxonomy);

            if (false === $taxterms) {
                $taxterms = wp_get_object_terms($object_id, $taxonomy);
                $to_cache = array();
                foreach ($taxterms as $key => $term) {
                    $to_cache[ $key ] = $term->data;
                }
                wp_cache_add($object_id, $to_cache, $taxonomy . '_relationships');
            }
            if ($taxterms && !is_wp_error($taxterms)) {
                $terms = array_map('get_term', $taxterms);
            }
        }

        // Hook
        if (!empty($enable_hidden_terms) && !empty($hidden_terms)) {
            $terms = array_filter($terms, function ($term) use ($hidden_terms) {
                return !in_array($term->term_id, $hidden_terms);
            });
        } else {
            $terms = apply_filters('get_the_tags', $terms);
        }

        // Limit to max quantity if set
        $number = (int) $number;
        if (0 !== $number) {
            shuffle($terms); // Randomize terms
            $terms = array_slice($terms, 0, $number);
        }

        // Return for object format
        if ('object' === $format) {
            return $terms;
        }

        // If no terms, return text nothing.
        if (empty($terms)) {
            if ((int)$hide_output === 0) {
                $notagtext_html = '<div class="taxopress-no-tags-message">' . esc_html($notagtext) . '</div>';
                return SimpleTags_Client::output_content('st-post-tags', $format, '', $notagtext_html, $copyright, $separator, '', '', $before, $after);
            } else {
                return '';
            }
        }

        // HTML Rel
        $rel = SimpleTags_Client::get_rel_attribut();

        //update xformat with class link class
        if (!empty(trim($link_class))) {
            $link_class = taxopress_format_class($link_class);
            $xformat = taxopress_add_class_to_format($xformat, $link_class);
        }

        // Filter by timeframe if set
        if (!empty($limit_days) && $limit_days > 0) {
            $min_time = strtotime("-{$limit_days} days");
            $terms = array_filter($terms, function ($term) use ($min_time) {
                return strtotime($term->term_group) >= $min_time; // You may need to adjust this if you store term assignment time elsewhere
            });
        }

        // Sort terms from the database before display
        if (!empty($selectionby)) {
            if ($selectionby === 'name') {
                usort($terms, function ($a, $b) {
                    return strcmp($a->name, $b->name);
                });
            } elseif ($selectionby === 'slug') {
                usort($terms, function ($a, $b) {
                    return strcmp($a->slug, $b->slug);
                });
            } elseif ($selectionby === 'count') {
                usort($terms, function ($a, $b) {
                    return $a->count <=> $b->count;
                });
            } elseif ($selectionby === 'random') {
                shuffle($terms);
            }
        }

        // Apply ordering for choosing term from the database
        if (!empty($selection)) {
            if ($selection === 'desc') {
                $terms = array_reverse($terms);
            }
            // 'asc' is default, so do nothing
        }

        // Now, for display order (after slicing/limiting)
        if (!empty($orderby) && $orderby === 'taxopress_term_order') {
            $custom_order = get_option('taxopress_term_order_' . $taxonomy, []);
            if (!empty($custom_order)) {
                $terms_by_id = [];
                foreach ($terms as $term) {
                    if (is_object($term) && isset($term->term_id)) {
                        $terms_by_id[$term->term_id] = $term;
                    }
                }
                $ordered_terms = [];
                foreach ($custom_order as $term_id) {
                    if (isset($terms_by_id[$term_id])) {
                        $ordered_terms[] = $terms_by_id[$term_id];
                        unset($terms_by_id[$term_id]);
                    }
                }
                // Add any terms not in custom order at the end
                foreach ($terms_by_id as $term) {
                    $ordered_terms[] = $term;
                }
                if ($order === 'desc') {
                    $ordered_terms = array_reverse($ordered_terms, true);
                }
                $terms = $ordered_terms;
            }
        } elseif (!empty($orderby)) {
            if ($orderby === 'name') {
                usort($terms, function ($a, $b) {
                    return strcmp($a->name, $b->name);
                });
            } elseif ($orderby === 'count') {
                usort($terms, function ($a, $b) {
                    return $a->count <=> $b->count;
                });
            } elseif ($orderby === 'random') {
                shuffle($terms);
            }
            if (!empty($order) && $order === 'desc' && $orderby !== 'random' && $orderby !== 'taxopress_term_order') {
                $terms = array_reverse($terms);
            }
        }

        // Prepare output
        $output = array();

        // Calculate scaling for font size and color if enabled
        $counts = array();
        foreach ((array) $terms as $term) {
            if (is_object($term)) {
                $counts[ $term->term_id ] = $term->count;
            }
        }

        // Use full RBG code
        if (strlen($maxcolor) == 4) {
            $maxcolor = $maxcolor . substr($maxcolor, 1, strlen($maxcolor));
        }
        if (strlen($mincolor) == 4) {
            $mincolor = $mincolor . substr($mincolor, 1, strlen($mincolor));
        }

        // Check as smallest inferior or equal to largest
        if ($smallest > $largest) {
            $smallest = $largest;
        }

        // Scaling - Hard value for the moment
        $scale_min = 0;
        $scale_max = 10;

        if (!empty($counts)) {
            $minval = min($counts);
            $maxval = max($counts);
        } else {
            $minval = $maxval = 0;
        }

        $minout = max($scale_min, 0);
        $maxout = max($scale_max, $minout);

        $scale = ($maxval > $minval) ? (($maxout - $minout) / ($maxval - $minval)) : 0;

        foreach ((array) $terms as $term) {
            if (! is_object($term)) {
                continue;
            }

            // Calculate scale_result for this term
            $scale_result = null;
            if ($scale !== 0 && isset($counts[$term->term_id])) {
                $scale_result = (int) (($term->count - $minval) * $scale + $minout);
            } else {
                $scale_result = ($scale_max - $scale_min) / 2;
            }

            // Remove color/size markers if disabled
            if ($color == '0' || $color === false || $color === 'false') {
                $xformat = str_replace('%tag_color%', '', $xformat);
            }
            if ($smallest == $largest) {
                $xformat = str_replace('%tag_size%', '', $xformat);
            }

            $output[] = SimpleTags_Client::format_internal_tag(
                $xformat,
                $term,
                $rel,
                $scale_result,
                $scale_max,
                $scale_min,
                $largest,
                $smallest,
                $unit,
                $maxcolor,
                $mincolor
            );
        }

        return SimpleTags_Client::output_content('st-post-tags ' . taxopress_format_class($wrap_class) . '', $format, '', $output, $copyright, $separator, '', '', $before, $after);
    }
}
