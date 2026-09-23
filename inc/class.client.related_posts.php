<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_RelatedPosts
{
    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {

        //Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array( $this, 'enqueue_boxdisplay_scripts' ));
    }

    public function enqueue_boxdisplay_scripts()
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
     * Generate related posts
     *
     * @param string $user_args
     * @param bool $copyright
     *
     * @return string|array|boolean
     */
    public static function get_related_posts($user_args = '', $copyright = true)
    {
        global $wpdb, $post;

        // Get options
        $options = SimpleTags_Plugin::get_option();
        $width = isset($options['rp_image_width']) ? (int)$options['rp_image_width'] : 200;
        $height = isset($options['rp_image_height']) ? (int)$options['rp_image_height'] : 200;

        $defaults = array(
            'taxonomy'      => 'post_tag',
            'post_type'     => get_post_type($post),// leaving this for legacy purpose
            'post_types'    => '',
            'number'        => 3,
            'max_related_posts' => 3,
            'max_post_chars' => 100,
            'taxopress_max_cats' => 3,
            'taxopress_max_tags' => 3,
            'order'         => 'count-desc',
            'format'        => 'box',
            'separator'     => '',
            'exclude_posts' => '',
            'exclude_terms' => '',
            'post_id'       => 0,
            'excerpt_wrap'  => 55,
            'limit_days'    => 0,
            'min_shared'    => 1,
            'title'         => __('<h4>Related posts</h4>', 'simple-tags'),
            'nopoststext'   => __('No related posts.', 'simple-tags'),
            'dateformat'    => get_option('date_format'),
            'xformat' => sprintf(
                '<a href="%%post_permalink%%" title="%%post_title%% (%%post_date%%)" style="font-size:%%post_size%%;color:%%post_color%%">' .
                '<img src="%%post_thumb_url%%" height="%d" width="%d" class="custom-image-class"/>' .
                '<br>%%post_title%%<br>%%post_category%%</a>' .
                '(%%post_comment%%)',
                $width,
                $height
            ),
             'ID'            => 0,
             'hide_title'    => 0,
             'hide_output'   => 0,
             'title_header'  => '',
             'wrap_class'  => '',
             'link_class'  => '',
             'before'      => '',
             'after'       => '',
             'default_featured_media' => 'default',
             'imageresolution' => '1536x1536',
             'smallest'   => 12,
             'largest'    => 12,
             'unit'       => 'pt',
             'mincolor'   => '#353535',
             'maxcolor'   => '#000000',
             'color'      => true,
        );

        // Get values in DB
        $defaults['number']      = $options['rp_limit_qty'];
        $defaults['order']       = $options['rp_order'];
        $defaults['nopoststext'] = $options['rp_notagstext'];
        $defaults['title']       = $options['rp_title'];
        $defaults['xformat']     = $options['rp_xformat'];
        $defaults['taxonomy']    = $options['rp_taxonomy'];
        $defaults['default_featured_image'] = $options['rp_default_featured_media'];
        $defaults['format']      = $options['rp_format'];
        $defaults['smallest']    = isset($options['rp_min_size']) ? (int)$options['rp_min_size'] : 12;
        $defaults['largest']     = isset($options['rp_max_size']) ? (int)$options['rp_max_size'] : 12;
        $defaults['unit']        = isset($options['rp_unit']) ? $options['rp_unit'] : 'pt';
        $defaults['mincolor']    = isset($options['rp_min_color']) ? $options['rp_min_color'] : '#353535';
        $defaults['maxcolor']    = isset($options['rp_max_color']) ? $options['rp_max_color'] : '#000000';

        if (empty($user_args)) {
            $user_args = $options['rp_adv_usage'];
        }

        // Replace old markers by new
        $markers = array(
            '%date%'         => '%post_date%',
            '%permalink%'    => '%post_permalink%',
            '%title%'        => '%post_title%',
            '%commentcount%' => '%post_comment%',
            '%tagcount%'     => '%post_tagcount%',
            '%postid%'       => '%post_id%',
            '%postcontent%'   => '%post_content%',
            '%postcategory%' => '%post_category%',
        );
        if (! is_array($user_args)) {
            $user_args = strtr($user_args, $markers);
        }
        $args = wp_parse_args($user_args, $defaults);

        $args['number'] = $args['max_related_posts'];

        extract($args);

        // Sanitize taxonomy
        $taxonomy = sanitize_key($taxonomy);

        // If empty use default xformat !
        if (empty($xformat)) {
            $xformat = $defaults['xformat'];
        }

        $xformat = taxopress_sanitize_text_field($xformat);

        // Choose post ID
        $object_id = (int) $post_id;
        if (0 === $object_id) {
            if (! isset($post->ID) || 0 === (int) $post->ID) {
                return false;
            }

            $object_id = (int) $post->ID;
        }

        // Get cache if exist
        $results = false;

        // Generate key cache
        $key = md5(maybe_serialize($user_args) . '-' . $object_id);

        if ($cache = wp_cache_get('related_posts' . $taxonomy, 'simple-tags')) {
            if (isset($cache[ $key ])) {
                $results = $cache[ $key ];
            }
        }


        //set title
        if ((int)$ID > 0) {
            $copyright = false;
            if ((int)$hide_title > 0) {
                $title = '';
            } else {
                $new_title = '';
                if (!empty($title_header)) {
                    $new_title .= '<' . $title_header . '>';
                }
                $new_title .= $title;
                if (!empty($title_header)) {
                    $new_title .= '</' . $title_header . '>';
                }
                $title = $new_title;
            }
        }

        $title = taxopress_sanitize_text_field($title);

        // If cache not exist, get datas and set cache
        if ($results === false || $results === null) {
            // Get get tags
            $current_terms = get_the_terms((int) $object_id, $taxonomy);

            if ($current_terms == false || is_wp_error($current_terms)) {
                if ((int)$hide_output === 0) {
                    return SimpleTags_Client::output_content('st-related-posts', $format, $title, $nopoststext, $copyright, '', $wrap_class, $link_class);
                } else {
                    return '';
                }
            }

            // Number - Limit
            $number = (int) $number;
            if ($number == 0) {
                $number = 3;
            } elseif ($number > 50) {
                $number = 50;
            }
            $limit_number = $number;
            unset($number);

            // Order tags before output (count-asc/count-desc/date-asc/date-desc/name-asc/name-desc/random)
            $order_by = '';
            $order    = strtolower($order);
            switch ($order) {
                case 'count-asc':
                    $order_by = 'counter ASC, p.post_title DESC';
                    break;
                case 'random':
                    $order_by = 'RAND()';
                    break;
                case 'date-asc':
                    $order_by = 'p.post_date ASC';
                    break;
                case 'date-desc':
                    $order_by = 'p.post_date DESC';
                    break;
                case 'name-asc':
                    $order_by = 'p.post_title ASC';
                    break;
                case 'name-desc':
                    $order_by = 'p.post_title DESC';
                    break;
                default: // count-desc
                    $order_by = 'counter DESC, p.post_title DESC';
                    break;
            }

            // Limit days - 86400 seconds = 1 day
            $limit_days     = (int) $limit_days;
            $limit_days_sql = '';
            if ($limit_days != 0) {
                $limit_days_sql = 'AND p.post_date > "' . gmdate('Y-m-d H:i:s', time() - $limit_days * 86400) . '"';
            }
            unset($limit_days);

            if (is_array($post_types) && !empty($post_types)) {
                $post_type = $post_types;
            } else {
                // legacy post type

                //get post type for current selection
                if ($post_type === 'st_current_posttype') {
                    $post_type = [get_post_type($post)];
                }

                // Make array post type
                if (is_string($post_type)) {
                    $post_type = explode(',', $post_type);
                }
            }

            // Sanitize post type
            $post_type = array_map('sanitize_key', $post_type);
            $post_type = array_filter($post_type);

            // Build post type SQL
            if (in_array('st_all_posttype', $post_type)) {//if all post type is selected
                $restrict_sql = '';
            } else {
                $restrict_sql = "AND p.post_type IN ('" . implode("', '", array_map('esc_sql', $post_type)) . "')";
            }

            // Restrict posts
            $exclude_posts_sql = '';
            if ($exclude_posts != '') {
                $exclude_posts     = (array) explode(',', $exclude_posts);
                $exclude_posts     = array_unique($exclude_posts);
                $exclude_posts_sql = "AND p.ID NOT IN (";
                foreach ($exclude_posts as $value) {
                    $value = (int) $value;
                    if ($value > 0 && $value != $object_id) {
                        $exclude_posts_sql .= '"' . $value . '", ';
                    }
                }
                $exclude_posts_sql .= '"' . $object_id . '")';
            } else {
                $exclude_posts_sql = "AND p.ID <> {$object_id}";
            }
            unset($exclude_posts);

            // Restricts tags
            $terms_to_exclude = array();
            if ($exclude_terms != '') {
                $exclude_terms = (array) explode(',', $exclude_terms);
                $exclude_terms = array_unique($exclude_terms);
                foreach ($exclude_terms as $value) {
                    $terms_to_exclude[] = trim($value);
                }
            }
            unset($exclude_terms);

            // SQL Terms list
            $term_list = array();
            foreach ((array) $current_terms as $term) {
                if (! in_array($term->name, $terms_to_exclude)) {
                    $term_list[] = '"' . (int) $term->term_id . '"';
                }
            }
            $term_list = implode(', ', $term_list);

            // Build SQL terms subqueries array
            $include_terms_sql = array();
            if (! empty($term_list)) {
                $include_terms_sql[ $taxonomy ] = $term_list;
            }

            // Group Concat check if post_relatedtags is used by xformat...
            $select_gp_concat = '';
            if (strpos($xformat, '%post_relatedtags%') || $min_shared > 1) {
                $select_gp_concat = ', GROUP_CONCAT(tt.term_id) as terms_id';
            }

            // Check if post_excerpt is used by xformat...
            $select_excerpt = '';
            //if ( strpos( $xformat, '%post_excerpt%' ) ) {
            //  $select_excerpt = ', p.post_content, p.post_excerpt, p.post_password';
            //}

            // If empty return no posts text
            if (empty($include_terms_sql)) {
                if ((int)$hide_output === 0) {
                    return SimpleTags_Client::output_content('st-related-posts', $format, $title, $nopoststext, $copyright, '', $wrap_class, $link_class);
                } else {
                    return '';
                }
            }

            //set default xformat contents when display format is box
            if ($format == 'box') {
                $defaults['number']    = 3;
                $defaults['xformat']   = __('<a href="%post_permalink%" title="%post_title% (%post_date%)"> 
			                       <img src="%post_thumb_url%" height="200" width="200" class="custom-image-class" />
								   <br>
								   %post_title%
								   <br>
								    <span>%post_date% &bull;</span>  <span>%post_category%</span>
			                       </a> 
			                       ', 'simple-tags');
            }

            // Posts: title, comments_count, date, permalink, post_id, counter
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "
			SELECT p.*, COUNT(tr.object_id) AS counter {$select_excerpt} {$select_gp_concat}
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->term_relationships} AS tr ON (p.ID = tr.object_id)
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
			WHERE 1 = 1
			AND (tt.taxonomy = '{$taxonomy}' AND tt.term_id IN ({$term_list}))
			{$exclude_posts_sql}
			AND p.post_status = 'publish'
			AND p.post_date_gmt < '" . current_time('mysql') . "'
			{$limit_days_sql}
			{$restrict_sql}
			GROUP BY tr.object_id
			ORDER BY {$order_by}
			LIMIT 0, %d",
                    $limit_number
                )
            );

            if (!$cache) {
                $cache = [];
            }
            $cache[ $key ] = $results;
            wp_cache_set('related_posts' . $taxonomy, $cache, 'simple-tags');
        }

        if ($format == 'object' || $format == 'array') {
            return $results;
        } elseif ($results === false || empty($results)) {
            if ((int)$hide_output === 0) {
                return SimpleTags_Client::output_content('st-related-posts', $format, $title, $nopoststext, $copyright, '', $wrap_class, $link_class);
            } else {
                return '';
            }
        }

        if (empty($dateformat)) {
            $dateformat = get_option('date_format');
        }

        $imageresolution = isset($args['imageresolution']) ? sanitize_text_field($args['imageresolution']) : $defaults['imageresolution'];
        if (empty($imageresolution)) {
            $imageresolution = $defaults['imageresolution'];
        }

        $allowed_image_resolutions = array('thumbnail', 'medium', 'large', '1536x1536', '2048x2048');
        if (! in_array($imageresolution, $allowed_image_resolutions, true)) {
            $imageresolution = $defaults['imageresolution'];
        }

        $output = array();

        //update xformat with class link class
        if (!empty(trim($link_class))) {
            $link_class = taxopress_format_class($link_class);
            $xformat = taxopress_add_class_to_format($xformat, $link_class);
        }

        $min_count = PHP_INT_MAX;
        $max_count = 0;

        foreach ((array) $results as $result) {
            if (!is_object($result)) {
                continue;
            }

            $term_count = isset($result->counter) ? (int)$result->counter : 0;

            if ($term_count < $min_count) {
                $min_count = $term_count;
            }

            if ($term_count > $max_count) {
                $max_count = $term_count;
            }
        }

        // Prevent divide by zero
        if ($max_count === $min_count) {
            $max_count++;
        }

        // Replace placeholders
        foreach ((array) $results as $result) {
            if (($min_shared > 1 && (count(explode(',', $result->terms_id)) < $min_shared)) || !is_object($result)) {
                continue;
            }

            $element_loop = $xformat;

            $term_count = isset($result->counter) ? (int)$result->counter : 0;

            // Font size calculation
            $smallest = isset($smallest) ? (float)$smallest : 12;
            $largest = isset($largest) ? (float)$largest : 12;
            $unit     = isset($unit) ? $unit : 'pt';

            $font_size = $smallest + (($term_count - $min_count) / ($max_count - $min_count)) * ($largest - $smallest);
            $font_size_output = round($font_size, 2) . $unit;

            // Color calculation
            $use_color = isset($color) && $color;
            $mincolor = isset($mincolor) ? $mincolor : '#353535';
            $maxcolor = isset($maxcolor) ? $maxcolor : '#000000';
            $post_color = $mincolor;

            if ($use_color) {
                $start_rgb = sscanf($mincolor, "#%02x%02x%02x");
                $end_rgb = sscanf($maxcolor, "#%02x%02x%02x");

                $ratio = ($term_count - $min_count) / ($max_count - $min_count);

                $r = (int)($start_rgb[0] + ($end_rgb[0] - $start_rgb[0]) * $ratio);
                $g = (int)($start_rgb[1] + ($end_rgb[1] - $start_rgb[1]) * $ratio);
                $b = (int)($start_rgb[2] + ($end_rgb[2] - $start_rgb[2]) * $ratio);

                $post_color = sprintf("#%02x%02x%02x", $r, $g, $b);
            }

            $post_title   = apply_filters('the_title', $result->post_title, $result->ID);

            // Get the category of the post
            $categories = get_the_category($result->ID);
            if (!empty($categories)) {
                //display all categories when the value is set to 0
                if ($taxopress_max_cats === '0') {
                    $post_category = $categories;
                } else {
                    //limit categories to set value
                    $post_category = array_slice($categories, 0, $taxopress_max_cats);
                }

                $category_names = array_map(function ($cat) {
                    return esc_html($cat->name);
                }, $post_category);
                $post_category = implode(', ', $category_names); // Join categories with a comma if there are multiple
            } else {
                $post_category = '';
            }

            //style the category
            $post_category = '<span class="taxopress-boxrelatedpost-cat">' . $post_category . '</span>';

            // Replace %post_category% in the element loop
            $element_loop = str_replace('%post_category%', $post_category, $element_loop);

            // Add featured Image
            $post_thumbnail_id = get_post_thumbnail_id($result->ID);
            if ($post_thumbnail_id) {
                // Extract height and width from xformat
                preg_match('/height="(\d+)"/', $xformat, $height_matches);
                preg_match('/width="(\d+)"/', $xformat, $width_matches);

                $desired_height = isset($height_matches[1]) ? (int)$height_matches[1] : 200;
                $desired_width = isset($width_matches[1]) ? (int)$width_matches[1] : 200;

                // Register dynamic image size
                add_image_size('taxopress-related-dynamic', $desired_width, $desired_height, true);


                $image = wp_get_attachment_image_src($post_thumbnail_id, $imageresolution);

                if (!$image) {
                    $image = wp_get_attachment_image_src($post_thumbnail_id, 'taxopress-related-dynamic');
                }

                if ($image) {
                    $post_thumbnail_url = $image[0];
                    // Update CSS variables for this specific image
                    $element_loop = str_replace(
                        'class="custom-image-class"',
                        sprintf(
                            'class="custom-image-class" style="width:%dpx;height:%dpx;object-fit:cover"',
                            $desired_width,
                            $desired_height
                        ),
                        $element_loop
                    );
                }
            } else {
                $post_thumbnail_url = '';
            }

            if (empty($post_thumbnail_url)) {
                if ($default_featured_media === 'default') {
                    $post_thumbnail_url = STAGS_URL . '/assets/images/taxopress-white-logo.png';
                } elseif (!empty($default_featured_media)) {
                    $post_thumbnail_url = $default_featured_media;
                }

                // Apply width and height to default images
                if (!empty($post_thumbnail_url)) {
                    // Extract height and width from xformat
                    preg_match('/height="(\d+)"/', $xformat, $height_matches);
                    preg_match('/width="(\d+)"/', $xformat, $width_matches);

                    $desired_height = isset($height_matches[1]) ? (int)$height_matches[1] : 200;
                    $desired_width = isset($width_matches[1]) ? (int)$width_matches[1] : 200;

                    // Update CSS variables for default image
                    $element_loop = str_replace(
                        'class="custom-image-class"',
                        sprintf(
                            'class="custom-image-class" style="width:%dpx;height:%dpx;object-fit:cover"',
                            $desired_width,
                            $desired_height
                        ),
                        $element_loop
                    );
                }
            }

            if (empty($post_thumbnail_url)) {
                $element_loop = preg_replace('/<img\b[^>]*\bsrc="%post_thumb_url%"[^>]*>/i', '', $element_loop);
            }

            $element_loop = str_replace('%post_thumb_url%', $post_thumbnail_url, $element_loop);

            $element_loop = str_replace('%post_date%', mysql2date($dateformat, $result->post_date), $element_loop);

            $element_loop = str_replace('%post_permalink%', get_permalink($result), $element_loop);
            $element_loop = str_replace('%post_title%', $post_title, $element_loop);
            $element_loop = str_replace('%post_title_attribute%', esc_html(wp_strip_all_tags($post_title)), $element_loop);
            $element_loop = str_replace('%post_comment%', (int) $result->comment_count, $element_loop);
            $element_loop = str_replace('%post_tagcount%', (int) $result->counter, $element_loop);
            $element_loop = str_replace('%post_id%', $result->ID, $element_loop);
            $element_loop = str_replace('%post_size%', esc_attr($font_size_output), $element_loop);
            $element_loop = str_replace('%post_color%', esc_attr($post_color), $element_loop);


            if (isset($result->terms_id)) {
                //format related tags differently for box format
                if ($format == 'box') {
                    $terms_ids = explode(',', $result->terms_id);

                    $tags = wp_get_object_terms($result->ID, $taxonomy, array(
                        'include' => $terms_ids,
                        'fields'  => 'names'
                    ));

                    if (!is_wp_error($tags) && !empty($tags)) {
                        if ($taxopress_max_tags === '0') {
                            $post_tag = $tags;
                        } else {
                            //tag display limit to set value
                            $post_tag = array_slice($tags, 0, $taxopress_max_tags);
                        }
                        $tags_list = implode(', ', $post_tag);

                        // Replace %post_relatedtags% with the comma-separated tag names
                        $element_loop = str_replace('%post_relatedtags%', $tags_list, $element_loop);
                    }
                } else {
                    // Handle other formats
                    $tags_list = self::get_tags_from_id($result->terms_id, $taxonomy);

                    if ($taxopress_max_tags === '0') {
                        //display all tags
                    } else {
                        $post_tag = explode(', ', $tags_list);
                        $tags_list = implode(', ', array_slice($post_tag, 0, $taxopress_max_tags));
                    }

                    $element_loop = str_replace('%post_relatedtags%', $tags_list, $element_loop);
                }
            }

            if (isset($result->post_excerpt) || isset($result->post_content)) {
                $element_loop = str_replace('%post_excerpt%', self::get_excerpt_post($result->post_excerpt, $result->post_content, $result->post_password, $excerpt_wrap), $element_loop);
            }


            $max_chars = isset($max_post_chars) ? (int) $max_post_chars : 100;

            if (isset($result->post_content)) {
                // Trim content based on the maximum characters
                $content_excerpt = mb_strimwidth(wp_strip_all_tags($result->post_content), 0, $max_chars, '...');
                $element_loop = str_replace('%post_content%', esc_html($content_excerpt), $element_loop);
            }



            $output[] = $element_loop;
        }

        return SimpleTags_Client::output_content('st-related-posts', $format, $title, $output, $copyright, $separator, $wrap_class, $link_class, $before, $after);
    }

    /**
     * Build excerpt from post data with specific lenght
     *
     * @param string $excerpt
     * @param string $content
     * @param string $password
     * @param integer $excerpt_length
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function get_excerpt_post($excerpt = '', $content = '', $password = '', $excerpt_length = 55)
    {
        if (! empty($password)) { // if there's a password
            // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
            if ($_COOKIE[ 'wp-postpass_' . COOKIEHASH ] != $password) { // and it doesn't match the cookie
                return __('There is no excerpt because this is a protected post.', 'simple-tags');
            }
        }

        if (! empty($excerpt)) {
            return apply_filters('get_the_excerpt', $excerpt);
        } else { // Fake excerpt
            $content = str_replace(']]>', ']]&gt;', $content);
            $content = wp_strip_all_tags($content);

            $excerpt_length = (int) $excerpt_length;
            if (0 === $excerpt_length) {
                $excerpt_length = 55;
            }

            $words = explode(' ', $content, $excerpt_length + 1);
            if (count($words) > $excerpt_length) {
                array_pop($words);
                array_push($words, '[...]');
                $content = implode(' ', $words);
            }

            return $content;
        }
    }

    /**
     * Get and format tags from list ID (SQL Group Concat)
     *
     * @param string $terms
     * @param string $taxonomy
     *
     * @return string
     * @author WebFactory Ltd
     */
    public static function get_tags_from_id($terms = '', $taxonomy = 'post_tag')
    {
        if (empty($terms)) {
            return '';
        }

        // Get tags since Term ID.
        $terms = (array) get_terms(array(
            'taxonomy' => $taxonomy,
            'include'  => $terms,
        ));
        if (empty($terms)) {
            return '';
        }

        // HTML Rel (tag)
        $rel = SimpleTags_Client::get_rel_attribut();

        $output = array();
        foreach ((array) $terms as $term) {
            $link = get_term_link($term->term_id, $term->taxonomy);
            if (empty($link) || is_wp_error($link)) {
                continue;
            }

            $output[] = '<a href="' . $link . '" title="' . esc_attr(sprintf(_n('%d topic', '%d topics', (int) $term->count, 'simple-tags'), $term->count)) . '" ' . $rel . '>' . esc_html($term->name) . '</a>';
        }

        return implode(', ', $output);
    }
}
