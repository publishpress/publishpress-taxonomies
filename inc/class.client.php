<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize,WordPressVIPMinimum.Functions.CheckReturnValue.NonCheckedVariable,WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client
{
    /**
     * Initialize TaxoPress client
     *
     * @return boolean
     */
    public function __construct()
    {

        // Load translation
        add_action('init', array( __CLASS__, 'init_translation' ));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array( $this, 'enqueue_displayformat_scripts' ), 999);

        require(STAGS_DIR . '/inc/class.client.autolinks.php');
        new SimpleTags_Client_Autolinks();

        // Call tag clouds ?
        if (1 === (int) SimpleTags_Plugin::get_option_value('active_terms_display')) {
            require_once STAGS_DIR . '/inc/tag-clouds-action.php';
        }

        // Call post tags ?
        if (1 === (int) SimpleTags_Plugin::get_option_value('active_post_tags')) {
            require_once STAGS_DIR . '/inc/post-tags-action.php';
        }

        // Call related posts ?
        if ((int) SimpleTags_Plugin::get_option_value('active_related_posts_new') === 1) {
            require_once STAGS_DIR . '/inc/related-posts-action.php';
        }
        if ((int) SimpleTags_Plugin::get_option_value('active_related_posts_new') === 1) {
            require(STAGS_DIR . '/inc/class.client.related_posts.php');
            new SimpleTags_Client_RelatedPosts();
        }

        // Call auto terms ?
        require(STAGS_DIR . '/inc/class.client.autoterms.php');
        new SimpleTags_Client_Autoterms();

        // Call post tags ?
        require(STAGS_DIR . '/inc/class.client.post_tags.php');
        new SimpleTags_Client_PostTags();


        if ((int)SimpleTags_Plugin::get_option_value('active_taxonomies') === 1) {
            require_once STAGS_DIR . '/inc/taxonomies-action.php';
            add_action('parse_query', array( __CLASS__, 'cpt_taxonomy_parse_query' ));
            if (defined('STAGS_OPTIONS_NAME')) {
                $saved_option = (array)get_option(STAGS_OPTIONS_NAME);
                if ((array_key_exists('use_tag_pages', $saved_option))) {
                    if ((int)$saved_option['use_tag_pages'] === 1) {
                        if (!array_key_exists('post_tag', taxopress_get_extername_taxonomy_data())) {
                            add_action('init', array( __CLASS__, 'init' ), 11);
                            add_action('parse_query', array( __CLASS__, 'parse_query' ));
                        }
                    }
                }
            }
        }

        return true;
    }

    public function enqueue_displayformat_scripts()
    {

        if ((int) SimpleTags_Plugin::get_option_value('disable_frontend_scripts') === 1) {
            return;
        }

        if (! apply_filters('taxopress_load_frontend_scripts', true)) {
            return;
        }

        wp_register_script('taxopress-frontend-js', STAGS_URL . '/assets/frontend/js/frontend.js', array('jquery'), STAGS_VERSION);
        wp_register_style('taxopress-frontend-css', STAGS_URL . '/assets/frontend/css/frontend.css', array(), STAGS_VERSION, 'all');

        wp_enqueue_script('taxopress-frontend-js');
        wp_enqueue_style('taxopress-frontend-css');
    }


    /**
     * Retrieves the currently queried object.
     *
     * Wrapper for WP_Query::get_queried_object().
     *
     * @since 3.1.0
     *
     * @global WP_Query $wp_query WordPress Query object.
     *
     * @return WP_Term|WP_Post_Type|WP_Post|WP_User|null The queried object.
     */
    public static function taxopress_get_queried_object()
    {
        global $wp_query;

        if (!is_object($wp_query)) {
            return null;
        }

        return $wp_query->get_queried_object();
    }

    /**
     * Add cpt to taxonomy during the query
     *
     * @param WP_Query $query
     *
     * @return void
     * @author Olatechpro
     */
    public static function cpt_taxonomy_parse_query($query)
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
        }

        if (is_admin()) {
            return $query;
        }
        if (isset($screen->id) && $screen->id == 'edit-post') {
            return $query;
        }
        if ($query->is_category == true || $query->is_tag == true || $query->is_tax == true) {
            $get_queried_object = self::taxopress_get_queried_object();
            if (is_object($get_queried_object)) {
                if (!isset($get_queried_object->taxonomy)) {
                    return $query;
                }
                if (!taxopress_show_all_cpt_in_archive_result($get_queried_object->taxonomy)) {
                    return $query;
                }
                $get_taxonomy = get_taxonomy($get_queried_object->taxonomy);
                if (is_object($get_taxonomy)) {
                    $post_types = $get_taxonomy->object_type;
                    if (isset($query->query_vars['post_type'])) {
                        if (is_array($query->query_vars['post_type'])) {
                            $post_types = array_filter(array_merge($query->query_vars['post_type'], $post_types));
                        } elseif (is_string($query->query_vars['post_type'])) {
                            $original_post_type = $query->query_vars['post_type'];
                            $get_post_type_object = get_post_type_object($original_post_type);
                            if (is_object($get_post_type_object)) {
                                if ((int)$get_post_type_object->public === 0) {
                                    return $query;
                                }
                            }
                            $post_types[] = $query->query_vars['post_type'];
                        }
                        $new_post_object = $post_types;
                    } else {
                        $new_post_object = $post_types;
                    }
                    $query->query_vars['post_type'] = $new_post_object;
                }
            }
        }
        return $query;
    }

    /**
     * Load translations
     */
    public static function init_translation()
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Keep support for bundled translations installed outside WordPress.org.
        load_plugin_textdomain('simple-tags', false, basename(STAGS_DIR) . '/languages');
    }

    /**
     * Register taxonomy post_tags for page post type
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function init()
    {
        register_taxonomy_for_object_type('post_tag', 'page');
    }

    /**
     * Add page post type during the query
     *
     * @param WP_Query $query
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function parse_query($query)
    {
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
        }

        if (isset($screen->id) && $screen->id == 'edit-post') {
            return $query;
        }

        if ($query->is_tag == true) {
            if (isset($query->query_vars['post_type']) && is_array($query->query_vars['post_type'])) {
                $query->query_vars['post_type'][] = 'page';
            } else {
                $query->query_vars['post_type'] = array( 'post', 'page' );
            }
        }
    }

    /**
     * Randomize an array and keep association
     *
     * @param array $array
     *
     * @return boolean
     */
    public static function random_array(&$array)
    {
        if (! is_array($array) || empty($array)) {
            return false;
        }

        $keys = array_keys($array);
        shuffle($keys);

        $new = array();
        foreach ((array) $keys as $key) {
            $new[ $key ] = $array[ $key ];
        }

        $array = $new;

        return true;
    }

    /**
     * Build rel for tag link
     *
     * @return string
     */
    public static function get_rel_attribut()
    {
        global $wp_rewrite;
        $rel = (is_object($wp_rewrite) && $wp_rewrite->using_permalinks()) ? 'tag' : ''; // Tag ?
        if (! empty($rel)) {
            $rel = 'rel="' . $rel . '"'; // Add HTML Tag
        }

        return $rel;
    }

    /**
     * Retrieve the post count for a term across all taxonomies.
     *
     * This function queries terms based on the given term name and returns the number of posts associated with the first matching term.
     * It does not require specifying a taxonomy and will search across all public taxonomies.
     *
     * @param string $item
     * @return int The number of posts associated with the first matching term. Returns 0 if no term is found or if it has no posts.
     */
    public static function get_term_post_counts($item)
    {

        $terms = get_terms(array(
            'name' => wp_strip_all_tags($item),
            'hide_empty' => false,
            'fields' => 'all',
            'number' => 1,
            ));

        // If terms exist, return the first matching term's count
        if (! empty($terms) && ! is_wp_error($terms)) {
            return $terms[0]->count;
        }

        return 0;
    }

    /**
     * Format data for output
     *
     * @param string $html_class
     * @param string $format
     * @param string $title
     * @param string|array $content
     * @param boolean $copyright
     * @param string $separator
     *
     * @return string|array
     */
    public static function output_content($html_class = '', $format = 'list', $title = '', $content = '', $copyright = true, $separator = '', $div_class = '', $a_class = '', $before = '', $after = '', $taxonomy = '')
    {
        if (empty($content)) {
            return ''; // return nothing
        }

        if ($format == 'array' && is_array($content)) {
            return $content; // Return PHP array if format is array
        }

        $title = taxopress_sanitize_text_field($title);

        if (is_array($content)) {
            switch ($format) {
                case 'list':
                    $output = '' . $before . ' <ul class="' . $html_class . '">' . "\n\t" . '<li>' . implode("</li>\n\t<li>", $content) . "</li>\n</ul> {$after}\n";
                    break;
                case 'ol':
                    $output = '' . $before . ' <ol class="' . $html_class . '">' . "\n\t" . '<li>' . implode("</li>\n\t<li>", $content) . "</li>\n</ol> {$after}\n";
                    break;
                case 'comma':
                    $output = '' . '' . '' . '' . $before .  implode($separator, $content) . " {$after}\n";
                    break;
                case 'table':
                    $output = $before . '<table class="' . $html_class . ' taxopress-table-container">' . "\n\t";
                    $count = 0;
                    foreach ($content as $item) {
                        // If $item is an array, use its fields
                        if (is_array($item) && isset($item['html'], $item['count'])) {
                            $term_html = $item['html'];
                            $post_count = $item['count'];
                        } else {
                            // fallback for legacy
                            $term_html = $item;
                            $post_count = self::get_term_post_counts(wp_strip_all_tags($item));
                        }

                        // if ( $post_count === 0 ) {
                        //  continue;
                        // }

                        $display_class = $count >= 6 ? 'hidden' : '';
                        $output .= '<tr class="taxopress-table-row ' . $display_class . '"><td>' . $term_html . '</td><td class="taxopress-post-count">' . $post_count . '</td></tr>' . "\n\t";
                        $count++;
                    }
                    if ($count > 6) {
                        $output .= '<tr><td class="taxopress-see-more-container" colspan="2">';
                        $output .= '<span class="taxopress-see-more-link">see more <span class="taxopress-arrow right"></span></span>';
                        $output .= '<span class="taxopress-close-table-link">close table <span class="taxopress-arrow down"></span></span>';
                        $output .= '</td></tr>' . "\n";
                    }
                    $output .= "</table>" . $after . "\n";
                    break;
                case 'border':
                    $output = '<div class="taxopress-border-cloud ' . $html_class . '">' . $before . ' ' . "\n\t" . implode("{$separator}\n", $content) . " {$after}</div>\n";
                    break;
                case 'box':
                    $output = '<div class="taxopress-box-list ' . $html_class . '">' . $before . ' ' . "\n\t" . implode("{$separator}\n", $content) . " {$after}</div>\n";
                    break;
                case 'parent/child':
                    $output = $before . '<ul class="' . $html_class . ' taxopress-parent-child-list">' . "\n";

                    // Cache term hierarchy to avoid repeated queries
                    static $term_hierarchy = [];
                    $cache_key = md5($taxonomy . serialize($content));

                    if (!isset($term_hierarchy[$cache_key])) {
                        $terms_by_parent = [];
                        $all_terms = [];

                        $term_names = array_map(function ($term_html) {
                            return wp_strip_all_tags($term_html);
                        }, array_filter($content, 'trim'));

                        if (!empty($term_names)) {
                            $terms = get_terms([
                                'taxonomy' => $taxonomy,
                                'name' => $term_names,
                                'hide_empty' => false,
                                'update_term_meta_cache' => false
                            ]);

                            if (!is_wp_error($terms)) {
                                foreach ($terms as $term) {
                                    $all_terms[$term->term_id] = [
                                        'term' => $term,
                                        'html' => $content[array_search($term->name, $term_names)],
                                        'is_parent' => false
                                    ];

                                    $parent_id = $term->parent ?: '0';
                                    $terms_by_parent[$parent_id][] = $term->term_id;
                                }

                                foreach ($terms_by_parent as $child_ids) {
                                    foreach ($child_ids as $child_id) {
                                        if (isset($terms_by_parent[$child_id])) {
                                            $all_terms[$child_id]['is_parent'] = true;
                                        }
                                    }
                                }
                            }
                        }

                        $term_hierarchy[$cache_key] = [
                            'terms' => $all_terms,
                            'hierarchy' => $terms_by_parent
                        ];
                    }

                    // Use cached hierarchy
                    $data = $term_hierarchy[$cache_key];
                    $all_terms = $data['terms'];
                    $terms_by_parent = $data['hierarchy'];

                    // Group terms by parent
                    $terms_by_parent = [];
                    $all_terms = [];

                    // First pass - collect all terms
                    foreach ($content as $term_html) {
                        if (empty(trim($term_html))) {
                            continue;
                        }

                        $term_name = wp_strip_all_tags($term_html);
                        $term = get_term_by('name', $term_name, $taxonomy);

                        if (!$term) {
                            continue;
                        }

                        // Store term for reference
                        $all_terms[$term->term_id] = [
                            'term' => $term,
                            'html' => $term_html,
                            'is_parent' => false
                        ];

                        $parent_id = $term->parent ?: '0';
                        if (!isset($terms_by_parent[$parent_id])) {
                            $terms_by_parent[$parent_id] = [];
                        }
                        $terms_by_parent[$parent_id][] = $term->term_id;
                    }

                    foreach ($terms_by_parent as $parent_id => $children) {
                        foreach ($children as $child_id) {
                            if (isset($terms_by_parent[$child_id])) {
                                $all_terms[$child_id]['is_parent'] = true;
                            }
                        }
                    }

                    $output_term = function ($term_id, $level = 0) use (&$output_term, &$all_terms, &$terms_by_parent) {
                        if (!isset($all_terms[$term_id])) {
                            return '';
                        }

                        $term_data = $all_terms[$term_id];
                        $html = str_repeat("\t", $level);
                        $html .= '<li class="taxopress-parent-term">' . $term_data['html'];

                        if (isset($terms_by_parent[$term_id])) {
                            $html .= "\n" . str_repeat("\t", $level + 1);
                            $html .= '<ul class="taxopress-child-list">' . "\n";
                            foreach ($terms_by_parent[$term_id] as $child_id) {
                                $html .= $output_term($child_id, $level + 2);
                            }
                            $html .= str_repeat("\t", $level + 1) . "</ul>\n";
                        }

                        $html .= str_repeat("\t", $level) . "</li>\n";
                        return $html;
                    };
                    $display_mode = isset($current['display_mode']) ? $current['display_mode'] : 'parents_and_sub';

                    if ($display_mode === 'parents_only') {
                        foreach ($all_terms as $term_id => $term_data) {
                            if ($term_data['is_parent']) {
                                $output .= '<li class="taxopress-parent-term">' . $term_data['html'] . "</li>\n";
                            }
                        }
                    } elseif ($display_mode === 'sub_terms_only') {
                        foreach ($all_terms as $term_id => $term_data) {
                            if ($term_data['term']->parent > 0) {
                                $output .= '<li class="taxopress-child-term">' . $term_data['html'] . "</li>\n";
                            }
                        }
                    } else {
                        // Display full hierarchy
                        // Start with root terms or terms whose parents aren't in our set
                        foreach ($all_terms as $term_id => $term_data) {
                            $term = $term_data['term'];
                            if ($term->parent === 0 || !isset($all_terms[$term->parent])) {
                                $output .= $output_term($term_id);
                            }
                        }
                    }

                    $output .= "</ul>" . $after . "\n";
                    return $output;
                    break;
                default:
                    $output = '<div class="' . $html_class . '">' . $before . ' ' . "\n\t" . implode("{$separator}\n", $content) . " {$after}</div>\n";
                    break;
            }
        } else {
            $content = trim($content);
            switch ($format) {
                case 'string':
                    $output = $content;
                    break;
                case 'list':
                    $output = '' . $before . ' <ul class="' . $html_class . '">' . "\n\t" . '<li>' . $content . "</li>\n\t" . "</ul> {$after}\n";
                    break;
                case 'comma':
                    $output = '' . '' . '' . '' . $before . $content . " {$after}\n";
                    break;
                case 'table':
                    $output = $before . '<table class="' . $html_class . '">' . "\n\t"
                        . '<tr><td>' . $content . '</td></tr>' . "\n\t"
                        . "</table>" . $after . "\n";
                    break;
                case 'border':
                    $output = '<div class="taxopress-border-cloud ' . $html_class . '">' . $before . ' ' . "\n\t" . $content . " {$after} </div>\n";
                    break;
                case 'box':
                    $output = '<div class="taxopress-box-list ' . $html_class . '">' . $before . ' ' . "\n\t" . $content . " {$after} </div>\n";
                    break;
                case 'parent/child':
                    $output = $before . '<ul class="' . esc_attr($html_class) . ' taxopress-parent-child-list">' . "\n";
                    $lines = explode("\n", $content);
                    $inside_sublist = false;

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }

                        if (strpos($line, 'taxopress-parent-term') !== false) {
                            if ($inside_sublist) {
                                $output .= "</ul></li>\n";
                                $inside_sublist = false;
                            }

                            $output .= '<li class="parent-item" style="list-style-type: disc; color: black;">' . $line;

                            $output .= "\n<ul class=\"child-items\" style=\"list-style-type: circle; color: black;\">\n";
                            $inside_sublist = true;
                        } else {
                            $output .= '<li class="child-item">' . $line . "</li>\n";
                        }
                    }

                    // Close any open tags
                    if ($inside_sublist) {
                        $output .= "</ul></li>\n";
                    }

                    $output .= "</ul>" . $after . "\n";
                    break;
                default:
                    $output = '<div class="' . $html_class . '">' . $before . ' ' . "\n\t" . $content . " {$after} </div>\n";
                    break;
            }
        }

        //wrap class
        if (!empty(trim($div_class))) {
            $wrap_div_class_open = '<div class="' . taxopress_format_class($div_class) . '">';
            $wrap_div_class_close = '</div>';
        } else {
            $wrap_div_class_open = '<div class="taxopress-output-wrapper"> ';
            $wrap_div_class_close = '</div>';
        }
        // Replace false by empty
        $title = trim($title);
        if (strtolower($title) == 'false') {
            $title = '';
        }

        // Put title if exist
        if (! empty($title)) {
            $title .= "\n\t";
        }

        if ($copyright === true) {
            return "\n" . '<!-- Generated by TaxoPress ' . STAGS_VERSION . ' - https://wordpress.org/plugins/simple-tags/ -->' . "\n\t" . $wrap_div_class_open . $title . $output . $wrap_div_class_close . "\n";
        } else {
            return "\n\t" . $wrap_div_class_open . $title . $output . $wrap_div_class_close . "\n";
        }
    }

    /**
     * Remplace marker by dynamic values (use for related tags, current tags and tag cloud)
     *
     * @param string $element_loop
     * @param object $term
     * @param string $rel
     * @param integer $scale_result
     * @param integer $scale_max
     * @param integer $scale_min
     * @param integer $largest
     * @param integer $smallest
     * @param string $unit
     * @param string $maxcolor
     * @param string $mincolor
     *
     * @return string
     */
    public static function format_internal_tag($element_loop = '', $term = null, $rel = '', $scale_result = 0, $scale_max = null, $scale_min = 0, $largest = 0, $smallest = 0, $unit = '', $maxcolor = '', $mincolor = '')
    {
        // Need term object
        $tag_link = get_term_link($term, $term->taxonomy);
        $element_loop = str_replace('%tag_link%', esc_url($tag_link), $element_loop);
        $element_loop = str_replace('%tag_feed%', esc_url(get_term_feed_link($term->term_id, $term->taxonomy, '')), $element_loop);

        $element_loop = str_replace('%tag_name%', esc_html($term->name), $element_loop);
        $element_loop = str_replace('%tag_name_attribute%', esc_html(wp_strip_all_tags($term->name)), $element_loop);
        $element_loop = str_replace('%tag_id%', $term->term_id, $element_loop);
        $element_loop = str_replace('%tag_count%', (int) $term->count, $element_loop);
        $element_loop = str_replace('%tag_description%', esc_html($term->description), $element_loop);

        // Need rel
        $element_loop = str_replace('%tag_rel%', $rel, $element_loop);

        // Need max/min/scale and other :)
        if ($scale_result !== null) {
            $scale_result = (int) $scale_result;
            $scale_min = (int) $scale_min;
            $scale_max = (int) $scale_max;
            $largest = (int) $largest;
            $smallest = (int) $smallest;

            $element_loop = str_replace('%tag_size%', 'font-size:' . self::round(($scale_result - $scale_min) * ($largest - $smallest) / ($scale_max - $scale_min) + $smallest, 2) . $unit . ';', $element_loop);
            $element_loop = str_replace('%tag_color%', 'color:' . self::get_color_by_scale(self::round(($scale_result - $scale_min) * (100) / ($scale_max - $scale_min), 2), $mincolor, $maxcolor) . ';', $element_loop);
            $element_loop = str_replace('%tag_scale%', $scale_result, $element_loop);
        }

        // External link
        $element_loop = str_replace('%tag_technorati%', self::format_external_tag('technorati', $term->name), $element_loop);
        $element_loop = str_replace('%tag_flickr%', self::format_external_tag('flickr', $term->name), $element_loop);
        $element_loop = str_replace('%tag_delicious%', self::format_external_tag('delicious', $term->name), $element_loop);

        return $element_loop;
    }

    /**
     * This is pretty filthy. Doing math in hex is much too weird. It's more likely to work, this way!
     * Provided from UTW. Thanks.
     *
     * @param integer $scale_color
     * @param string $min_color
     * @param string $max_color
     *
     * @return string
     */
    public static function get_color_by_scale($scale_color, $min_color, $max_color)
    {
        $scale_color = $scale_color / 100;

        $minr = hexdec(substr($min_color, 1, 2));
        $ming = hexdec(substr($min_color, 3, 2));
        $minb = hexdec(substr($min_color, 5, 2));

        $maxr = hexdec(substr($max_color, 1, 2));
        $maxg = hexdec(substr($max_color, 3, 2));
        $maxb = hexdec(substr($max_color, 5, 2));

        $r = dechex(intval((($maxr - $minr) * $scale_color) + $minr));
        $g = dechex(intval((($maxg - $ming) * $scale_color) + $ming));
        $b = dechex(intval((($maxb - $minb) * $scale_color) + $minb));

        if (strlen($r) == 1) {
            $r = '0' . $r;
        }
        if (strlen($g) == 1) {
            $g = '0' . $g;
        }
        if (strlen($b) == 1) {
            $b = '0' . $b;
        }

        return '#' . $r . $g . $b;
    }

    /**
     * Extend the round PHP public static function for force a dot for all locales instead the comma.
     *
     * @param string $value
     * @param string $approximation
     *
     * @return float
     * @author WebFactory Ltd
     */
    public static function round($value, $approximation)
    {
        $value = round($value, $approximation);
        $value = str_replace(',', '.', $value); // Fixes locale comma
        $value = str_replace(' ', '', $value); // No space

        return $value;
    }

    /**
     * Format nice URL depending service
     *
     * @param string $type
     * @param string $term_name
     *
     * @return string
     */
    public static function format_external_tag($type = '', $term_name = '')
    {
        if (empty($term_name)) {
            return '';
        }

        $term_name = esc_html($term_name);
        switch ($type) {
            case 'technorati':
                return '<a class="tag_technorati" href="' . esc_url('http://technorati.com/tag/' . str_replace(' ', '+', $term_name)) . '" rel="tag">' . $term_name . '</a>';
                break;
            case 'flickr':
                return '<a class="tag_flickr" href="' . esc_url('http://www.flickr.com/photos/tags/' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($term_name)) . '/') . '" rel="tag">' . $term_name . '</a>';
                break;
            case 'delicious':
                return '<a class="tag_delicious" href="' . esc_url('http://del.icio.us/popular/' . strtolower(str_replace(' ', '', $term_name))) . '" rel="tag">' . $term_name . '</a>';
                break;
            default:
                return '';
                break;
        }
    }
}
