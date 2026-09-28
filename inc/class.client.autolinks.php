<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.Security.NonceVerification.Missing -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_Autolinks
{
    public static $posts = array();
    public static $link_tags = array();
    public static $tagged_link_count = 0;

    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {

        // Register frontend filters regardless of the global setting so a post-level
        // "enabled" status can force Auto Links on.
        add_filter('the_posts', array(__CLASS__, 'the_posts'), 10);
        add_filter('the_content', array(__CLASS__, 'taxopress_autolinks_the_content'), 5);
        add_filter('the_title', array(__CLASS__, 'taxopress_autolinks_the_title'), 5);

        // Elementor compatibility: elementor outputs content through its own filters,
        // so also run our autolinks on those outputs.
        if (defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin')) {
            add_filter('elementor/frontend/the_content', array(__CLASS__, 'taxopress_autolinks_the_content'), 5);
            add_filter('elementor/frontend/builder_content', array(__CLASS__, 'taxopress_autolinks_the_content'), 5);
        }

        if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_links')) {
            add_action('admin_init', [$this, 'taxopress_customurl_taxonomies_fields']);
        }
    }

    public function taxopress_customurl_taxonomies_fields()
    {

        if (!$this->is_taxonomy_page()) {
            return;
        }

        // Cache the autolink settings to avoid repeated database calls
        static $enabled_taxonomies = null;

        if ($enabled_taxonomies === null) {
            $autolink_settings = taxopress_get_autolink_data();
            $enabled_taxonomies = $this->get_enabled_taxonomies($autolink_settings);
        }

        // Only get taxonomies that are actually enabled
        $taxonomies = get_taxonomies([], 'objects');

        foreach ($enabled_taxonomies as $taxonomy_name) {
            if (isset($taxonomies[$taxonomy_name])) {
                add_action("{$taxonomy_name}_edit_form_fields", [$this, 'taxopress_add_custom_url_field']);
                add_action("{$taxonomy_name}_add_form_fields", [$this, 'taxopress_add_custom_url_field_new']);
                add_action("edited_{$taxonomy_name}", [$this, 'taxopress_save_custom_url_field']);
                add_action("created_{$taxonomy_name}", [$this, 'taxopress_save_custom_url_field']);
            }
        }
    }

    private function is_taxonomy_page()
    {
        global $pagenow;
        return in_array($pagenow, ['edit-tags.php', 'term.php']);
    }

    private function get_enabled_taxonomies($autolink_settings)
    {
        $default_enabled_taxonomies = ['post_tag', 'category'];
        $enabled_taxonomies = [];
        $has_setting_saved = false;

        foreach ($autolink_settings as $setting) {
            if (array_key_exists('enable_customurl_field', $setting)) {
                $has_setting_saved = true;
                $enabled_taxonomies = is_array($setting['enable_customurl_field']) ? $setting['enable_customurl_field'] : [];
                break;
            }
        }

        return $has_setting_saved ? array_unique($enabled_taxonomies) : $default_enabled_taxonomies;
    }

    /**
     * Stock posts ID as soon as possible
     * TODO: test if post_type allow post_tag before keep post ID
     *
     * @param array $posts
     *
     * @return array
     */
    public static function the_posts($posts)
    {
        if (!empty($posts) && is_array($posts)) {
            foreach ((array) $posts as $post) {
                self::$posts[] = (int) $post->ID;
            }

            self::$posts = array_unique(self::$posts);
        }

        return $posts;
    }

    /**
     * Get tags from current post views
     *
     * @return array
     */
    public static function get_tags_from_current_posts($options = false)
    {

        if (is_array(self::$posts) && count(self::$posts) > 0) {
            // Generate SQL from post id
            $postlist = implode("', '", self::$posts);

            // Generate key cache
            $key = md5(maybe_serialize($postlist));

            $results = array();

            if ($options) {
                $term_taxonomy = $options['taxonomy'];
            } else {
                $term_taxonomy = 'post_tag';
            }

            // Get cache if exist
            $cache = wp_cache_get('generate_keywords', 'simple-tags');
            if ($options || false === $cache) {
                if ($cache === false) {
                    $cache = [];
                }
                foreach (self::$posts as $object_id) {
                    // Get terms
                    $terms = get_object_term_cache($object_id, $term_taxonomy);
                    if (false === $terms || is_wp_error($terms)) {
                        $terms = wp_get_object_terms($object_id, $term_taxonomy);
                    }

                    if (false !== $terms && !is_wp_error($terms)) {
                        $results = array_merge($results, $terms);
                    }
                }

                $cache[$key] = $results;
                wp_cache_set('generate_keywords', $cache, 'simple-tags');
            } else {
                if (isset($cache[$key])) {
                    return $cache[$key];
                }
            }

            return $results;
        }

        return array();
    }

    /**
     * Get all available local tags
     *
     * @return array
     */
    public static function get_all_post_tags($options = false)
    {
        if (is_array(self::$posts) && count(self::$posts) > 0) {
            // Generate SQL from post id
            $postlist = implode("', '", self::$posts);

            // Generate key cache
            $key = md5(maybe_serialize($postlist));

            if ($options) {
                $term_taxonomy = $options['taxonomy'];
            } else {
                $term_taxonomy = 'post_tag';
            }
            $results = get_tags(['taxonomy' => $term_taxonomy, 'hide_empty' => false]);
            // Get cache if exist
            $cache = wp_cache_get('generate_keywords', 'simple-tags');
            if ($options || false === $cache) {
                foreach (self::$posts as $object_id) {
                    // Get terms
                    $terms = get_object_term_cache($object_id, $term_taxonomy);
                    if (false === $terms || is_wp_error($terms)) {
                        $terms = wp_get_object_terms($object_id, $term_taxonomy);
                    }

                    if (false !== $terms && !is_wp_error($terms)) {
                        $results = array_merge($results, $terms);
                    }
                }
                $cache = [];
                $cache[$key] = $results;
                wp_cache_set('generate_keywords', $cache, 'simple-tags');
            } else {
                if (isset($cache[$key])) {
                    return $cache[$key];
                }
            }

            return $results;
        }

        return array();
    }

    public function taxopress_add_custom_url_field($term)
    {

        if (!is_object($term)) {
            return;
        }

        $taxopress_custom_url = get_term_meta($term->term_id, 'taxopress_custom_url', true);

        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="taxopress_custom_url"><?php esc_html_e('Custom URL', 'simple-tags'); ?></label>
            </th>
            <td>
                <input type="text" name="taxopress_custom_url" id="taxopress_custom_url" value="<?php echo esc_attr($taxopress_custom_url); ?>" size="40">
                <p class="description">
                    <?php esc_html_e('Enter a custom URL for this term. This URL will only be used for auto-linked terms. If left empty, the term will link to its archive page.', 'simple-tags'); ?>
                </p>

            </td>
        </tr>
        <?php
    }

    public function taxopress_add_custom_url_field_new()
    {
        ?>
        <div class="form-field">
            <label for="taxopress_custom_url"><?php esc_html_e('Custom URL', 'simple-tags'); ?></label>
            <input type="text" name="taxopress_custom_url" id="taxopress_custom_url" value="" size="40">
            <p class="description">
                <?php esc_html_e('Enter a custom URL for this term. This URL will only be used if the term is auto-linked. If left empty, the term will link to its archive page.', 'simple-tags'); ?>
            </p>
        </div>
        <?php
    }

    public function taxopress_save_custom_url_field($term_id)
    {
        if (!empty($_POST['taxopress_custom_url'])) {
            $taxopress_custom_url = sanitize_url(wp_unslash($_POST['taxopress_custom_url']));
            update_term_meta($term_id, 'taxopress_custom_url', $taxopress_custom_url);
        } else {
            delete_term_meta($term_id, 'taxopress_custom_url');
        }
    }

    /**
     * Get links for each tag for auto link feature
     *
     */
    public static function prepare_auto_link_tags($options = false)
    {
        global $post;
        if ($options) {
            $auto_link_min = (int) $options['autolink_usage_min'];
            $unattached_terms = (int) $options['unattached_terms'];
            $autolink_min_char = (int) $options['autolink_min_char'];
            $autolink_max_char = (int) $options['autolink_max_char'];
            $term_taxonomy = $options['taxonomy'];
            $custom_urls_enabled = !empty($options['enable_custom_urls']);
        } else {
            $auto_link_min = (int) SimpleTags_Plugin::get_option_value('auto_link_min');
            $unattached_terms  = (int) SimpleTags_Plugin::get_option_value('auto_link_all');
            $autolink_min_char = 0;
            $autolink_max_char = 0;
            $term_taxonomy = 'post_tag';
            $custom_urls_enabled = false;
            $autolink_settings = taxopress_get_autolink_data();
            foreach ($autolink_settings as $setting) {
                if (!empty($setting['enable_custom_urls'])) {
                    $custom_urls_enabled = true;
                    break;
                }
            }
        }

        if (1 === $unattached_terms) {
            $terms = self::get_all_post_tags($options);
        } else {
            $terms = self::get_tags_from_current_posts($options);
        }

        $custom_urls_enabled = !empty($options['enable_custom_urls']);
        $archivepage = !empty($options['archivepage']);

        if (!$archivepage && !$custom_urls_enabled) {
            self::$link_tags = [];
            return true;
        }

        $custom_urls = [];
        if ($custom_urls_enabled && !empty($terms)) {
            $term_ids = wp_list_pluck($terms, 'term_id');
            $custom_urls = self::get_term_meta_batch($term_ids, 'taxopress_custom_url');
        }

        foreach ((array) $terms as $term) {
            //hidden terms should not be auto linked
            if ((int) SimpleTags_Plugin::get_option_value('enable_hidden_terms') === 1) {
                $min_usage = (int) SimpleTags_Plugin::get_option_value('hide-rarely');
                if ($term->count < $min_usage) {
                    continue;
                }
            }

            if ($options && isset($options['autolink_usage_min'])) {
                $autolink_min_usage = (int) $options['autolink_usage_min'];
                if ($term->count < $autolink_min_usage) {
                    continue;
                }
            }

            if (!$archivepage && $custom_urls_enabled) {
                $taxopress_custom_url = isset($custom_urls[$term->term_id])
                    ? $custom_urls[$term->term_id]
                    : '';
                if (!empty($taxopress_custom_url)) {
                    self::$link_tags[$term->name] = esc_url($taxopress_custom_url);
                }
            } else {
                //add primary term
                if ($custom_urls_enabled) {
                    $taxopress_custom_url = isset($custom_urls[$term->term_id])
                        ? $custom_urls[$term->term_id]
                        : '';
                    $primary_term_link = !empty($taxopress_custom_url) ? esc_url($taxopress_custom_url) : get_term_link($term, $term->taxonomy);
                } else {
                    $primary_term_link = get_term_link($term, $term->taxonomy);
                }
                $add_terms = [];
                // store the URL
                self::$link_tags[$term->name] = $primary_term_link;
                $add_terms[$term->name] = $primary_term_link;

                // add term synonyms
                if (is_array($options) && isset($options['synonyms_link']) && (int)$options['synonyms_link'] > 0) {
                    $term_synonyms = taxopress_get_term_synonyms($term->term_id);
                    if (!empty($term_synonyms)) {
                        foreach ($term_synonyms as $term_synonym) {
                            $add_terms[$term_synonym] = $primary_term_link;
                        }
                    }
                }

                // add linked term
                $add_terms = taxopress_add_linked_term_options($add_terms, $term->name, $term->taxonomy, true);

                foreach ($add_terms as $add_name => $add_term_link) {
                    //min character check
                    $min_char_pass = true;
                    if ($autolink_min_char > 0) {
                        $min_char_pass = strlen($add_name) >= $autolink_min_char ? true : false;
                    }
                    //max character check
                    $max_char_pass = true;
                    if ($autolink_max_char > 0) {
                        $max_char_pass = strlen($add_name) <= $autolink_max_char ? true : false;
                    }

                    if ($auto_link_min === 0 || $term->count >= $auto_link_min  && $min_char_pass && $max_char_pass) {
                        self::$link_tags[$add_name] = esc_url($add_term_link);
                    }
                }
            }
        }
        return true;
    }

    private static function prepare_excluded_terms($exclude_terms)
    {
        $prepared_terms = [];
        $exclude_terms  = explode(',', (string) $exclude_terms);

        foreach ($exclude_terms as $exclude_term) {
            $exclude_term = trim($exclude_term);

            if ('' === $exclude_term) {
                continue;
            }

            $prepared_terms[] = $exclude_term;
        }

        return array_unique($prepared_terms);
    }

    /**
     * Helper function to batch load term meta to reduce database queries
     */
    private static function get_term_meta_batch($term_ids, $meta_key)
    {
        global $wpdb;

        if (empty($term_ids)) {
            return [];
        }

        // Sanitize term IDs
        $term_ids = array_map('intval', $term_ids);
        $term_ids_string = implode(',', $term_ids);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT term_id, meta_value FROM {$wpdb->termmeta} 
            WHERE term_id IN ($term_ids_string) AND meta_key = %s",
            $meta_key
        ));

        $batch_data = [];
        foreach ($results as $result) {
            $batch_data[$result->term_id] = $result->meta_value;
        }

        return $batch_data;
    }

    private static function taxopress_get_title_attribute($search, $url, $options)
    {

        $title_attribute = isset($options['autolink_title_attribute'])
        ? $options['autolink_title_attribute']
        : SimpleTags_Plugin::get_option_value('auto_link_title');

        $term = get_term_by('name', $search, $options['taxonomy']);
        if ($term) {
            $custom_url = get_term_meta($term->term_id, 'taxopress_custom_url', true);
            if (!empty($custom_url) && esc_url($custom_url) === $url) {
                $title_attribute = isset($options['autolink_title_attribute_when_using_custom_url'])
                ? $options['autolink_title_attribute_when_using_custom_url']
                : SimpleTags_Plugin::get_option_value('auto_link_title_custom_url');
            }
        }

        return esc_attr(sprintf($title_attribute, $search));
    }

    /**
     * Replace text by link, except HTML tag, and already text into link, use DOMdocument.
     * https://stackoverflow.com/questions/4044812/regex-domdocument-match-and-replace-text-not-in-a-link
     *
     * @param string $content
     * @param string $search
     * @param string $replace
     * @param string $case
     * @param string $rel
     *
     * @return void
     */
    private static function replace_by_links_dom(&$content, $search = '', $replace = '', $case = '', $rel = '', $options = false, $content_type = 'content')
    {
        global $post, $autolinked_contents;

        if (!is_object($post) || !isset($post->ID) || empty($content)) {
            return $content;
        }

        $dom = new DOMDocument();

        if (!is_array($search)) {
            $search_lists = [];
            $search_lists[] = [
                'term_name'  => $search,
                'term_link'  => $replace,
                'case'       => $case,
                'rel'        => $rel,
                'options'    => $options,
                'option_id'  => 0,
                'option_idxx'  => 0,
                'post_limit' => SimpleTags_Plugin::get_option_value('auto_link_max_by_post'),
                'term_limit' => SimpleTags_Plugin::get_option_value('auto_link_max_by_tag'),
                'type'       => $content_type,
            ];
        } else {
            $search_lists = $search;
        }

        if (empty($search_lists)) {
            return;
        }

        $content_type = $search_lists[0]['type'];

        $content_key = $content_type . '_' . $post->ID;

        if (!is_array($autolinked_contents)) {
            $autolinked_contents = [];
        }


        if (isset($autolinked_contents[$content_key])) {
            $content = $autolinked_contents[$content_key];
            return $content;
        }

        // process blocks exclusion
        if (is_array($search_lists[0]['options']) && !empty($search_lists[0]['options']['blocks_exclusion'])) {
            $blocks_exclusion = $search_lists[0]['options']['blocks_exclusion'];
            $process = function ($item) {
                // Replace "core/" prefixes
                $item = str_replace("core/", "", $item);
                // Add "wp:" prefixes
                return "wp:" . $item;
            };
            // Apply the processing function to each element of the array
            $blocks_to_exclude = array_map($process, $blocks_exclusion);

            // Escape special characters for regex pattern
            $escaped_blocks = array_map(function ($block) {
                return preg_quote($block, '/');
            }, $blocks_to_exclude);

            // Create a regex pattern from the block names
            $pattern = '/<!-- (' . implode('|', $escaped_blocks) . ')(.*?)-->(.*?)<!-- \/wp\:(.*?) -->/s';

            $content = preg_replace_callback(
                $pattern,
                function ($matches) {
                    // Return the matched block content wrapped in taxopressnotag
                    return '<taxopressnotag>' . $matches[0] . '</taxopressnotag>';
                },
                $content
            );
        }

        // process shortcodes exclusion
        if (is_array($search_lists[0]['options']) && !empty($search_lists[0]['options']['shortcodes_exclusion'])) {
            $shortcodes_exclusion = $search_lists[0]['options']['shortcodes_exclusion'];

            // Create a regex pattern to match any of the shortcodes with or without attributes
            $pattern = '/\[(?:' . implode('|', $shortcodes_exclusion) . ')[^\]]*\]/';

            // Define the replacement function to wrap matched shortcodes
            $callback = function ($matches) {
                return '<taxopressnotag>' . $matches[0] . '</taxopressnotag>';
            };

            // Apply the regex pattern to the content using the callback
            $content = preg_replace_callback($pattern, $callback, $content);
        }

        //replace html entity with their entity code
        foreach (taxopress_html_character_and_entity() as $enity => $code) {
            $content = str_replace($enity, $code, $content);
        }

        // Replace HTML entities with placeholders
        $content = preg_replace_callback('/&#(\d+);/', function ($matches) {
            return 'STARTTAXOPRESSENTITY' . $matches[1] . 'TAXOPRESSENTITYEND';
        }, $content);

        //$content = str_replace('&#','|--|',$content);//https://github.com/publishpress/publishpress-taxonomies/issues/824
        //$content = str_replace('&','&#38;',$content); //https://github.com/publishpress/publishpress-taxonomies/issues/770*/
        $content = 'starttaxopressrandom' . $content . 'endtaxopressrandom'; //we're having issue when content start with styles https://wordpress.org/support/topic/3-7-2-auto-link-case-not-working/#post-16665257
        //$content = utf8_decode($content);

        libxml_use_internal_errors(true);
        // loadXml needs properly formatted documents, so it's better to use loadHtml, but it needs a hack to properly handle UTF-8 encoding
        //$result = $dom->loadHtml(mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8"));
        //$result = $dom->loadHtml(htmlspecialchars_decode($content, ENT_QUOTES | ENT_HTML5));

        // Load the content as HTML without adding DOCTYPE and html/body tags
        $content = '<div>' . $content . '</div>';

        $content = mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0xFFFFF], 'UTF-8');

        $result = $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if (false === $result) {
            return;
        }

        $xpath = new DOMXPath($dom);
        $j        = 0;
        $replaced_count = 0;

        $replaced_tags_counts = [];
        $option_limits        = [];
        $term_limits          = [];
        $option_remaining     = [];
        $option_tagged_counts = [];
        $node_text            = [];

        foreach ($search_lists as $search_details) {
            $search  = $search_details['term_name'];
            $replace = $search_details['term_link'];
            $case    = $search_details['case'];
            $rel     = $search_details['rel'];
            $options = $search_details['options'];

            $search = str_replace('&amp;', 'taxopressamp', $search); // https://github.com/publishpress/publishpress-taxonomies/issues/1638

            if (is_array($options)) {
                $autolink_case   = $options['autolink_case'];
                $html_exclusion  = $options['html_exclusion'];
                $html_exclusion_customs  = isset($options['html_exclusion_customs']) ? $options['html_exclusion_customs'] : [];
                $exclude_class   = $options['autolink_exclude_class'];
                $title_attribute = $options['autolink_title_attribute'];
                $title_attribute_custom_url = $options['autolink_title_attribute_when_using_custom_url'];
                $link_class      = isset($options['link_class']) ? taxopress_format_class($options['link_class']) : '';
            } else {
                $autolink_case = 'lowercase';
                $html_exclusion = [];
                $html_exclusion_customs = [];
                $exclude_class = '';
                $title_attribute = SimpleTags_Plugin::get_option_value('auto_link_title');
                $title_attribute_custom_url = SimpleTags_Plugin::get_option_value('auto_link_title_custom_url');
                $link_class = '';
            }

            $detail_id = $search_details['type'] . '_' . $search_details['option_id'];

            if (!isset($option_limits[$detail_id])) {
                $option_limits[$detail_id] = $search_details['post_limit'];
            }

            if (!isset($option_remaining[$detail_id])) {
                $option_remaining[$detail_id] = $option_limits[$detail_id];
            }

            if (!isset($term_limits[$detail_id])) {
                $term_limits[$detail_id] = min($search_details['term_limit'], $option_remaining[$detail_id]);
            }

            if (!isset($option_tagged_counts[$detail_id])) {
                $option_tagged_counts[$detail_id] = 0;
            }

            $html_exclusion[] = 'taxopressnotag';
            $html_exclusion[] = 'meta';
            $html_exclusion[] = 'link';
            $html_exclusion[] = 'head';

            if (!empty($html_exclusion_customs)) {
                $html_exclusion = array_merge($html_exclusion, $html_exclusion_customs);
            }

            //auto link exclusion
            $exclusion = '[not(ancestor::a)][not(ancestor-or-self::a/@*)]';
            if (count($html_exclusion) > 0) {
                foreach ($html_exclusion as $exclude_ancestor) {
                    $exclusion .= '[not(ancestor::' . strtolower($exclude_ancestor) . ')][not(ancestor-or-self::' . strtolower($exclude_ancestor) . '/@*)]';
                }
            }

            // Prepare exclude terms array
            $excludes_class = explode(',', $exclude_class);
            if (!empty($excludes_class)) {
                $excludes_class = array_filter($excludes_class);
                $excludes_class = array_unique($excludes_class);
                if (count($excludes_class) > 0) {
                    foreach ($excludes_class as $idclass) {
                        if (substr(trim($idclass), 0, 1) === "#") {
                            $element_id = ltrim(trim($idclass), "#");
                            $exclusion .= "[not(ancestor::*[@id='$element_id'])]";
                        } else {
                            $element_class = ltrim(trim($idclass), ".");
                            $exclusion .= "[not(ancestor::*[@class='$element_class'])]";
                        }
                    }
                }
            }

            foreach ($xpath->query('//text()' . $exclusion . '') as $node) {
                // Exclude URLs from being replaced
                if (preg_match('/(http|https):\/\/[^\s]+/i', $node->wholeText)) {
                    continue;
                }
                $url = array_key_exists($search, self::$link_tags) ? self::$link_tags[$search] : $replace;
                $used_title = self::taxopress_get_title_attribute($search, $url, $options);
                $replace = $url;
                $substitute = '<a href="' . $replace . '" class="st_tag internal_tag ' . $link_class . '" ' . $rel . ' title="' . $used_title . "\">$search</a>";
                $link_openeing = '<a href="' . $replace . '" class="st_tag internal_tag ' . $link_class . '" ' . $rel . ' title="' . $used_title . "\">";
                $link_closing = '</a>';
                $upperterm = strtoupper($search);
                $lowerterm = strtolower($search);



                if ($option_limits[$detail_id] > 0 && 0 >= $option_remaining[$detail_id]) {
                    break;
                }

                if ($term_limits[$detail_id] > 0 && array_key_exists($replace, $replaced_tags_counts) && $replaced_tags_counts[$replace] >= $term_limits[$detail_id]) {
                    continue;
                }

                if ($term_limits[$detail_id] > 0 && array_key_exists($replace, $replaced_tags_counts)) {
                    $same_usage_max = min($term_limits[$detail_id] - $replaced_tags_counts[$replace], $option_remaining[$detail_id]);
                } else {
                    $same_usage_max = min($term_limits[$detail_id], $option_remaining[$detail_id]);
                }

                // Replace HTML entities with placeholders in term name too to match them in content
                $search = preg_replace_callback('/&#(\d+);/', function ($matches) {
                    return 'STARTTAXOPRESSENTITY' . $matches[1] . 'TAXOPRESSENTITYEND';
                }, $search);

                $whole_words = isset($options['whole_words']) ? (int)$options['whole_words'] : 1;
                if ($whole_words) {
                    $pattern = '/\b' . preg_quote($search, "/") . '\b/ui';
                } else {
                    // Allow partial matches - no word boundary checks
                    $pattern = '/' . preg_quote($search, "/") . '/i';
                }

                //if ('i' === $case) {
                if ($autolink_case === 'none') { // retain case
                    $replaced = preg_replace_callback($pattern, function ($matches) use ($link_openeing, $link_closing) {
                        return $link_openeing . htmlspecialchars($matches[0]) . $link_closing;
                    }, $node->wholeText, $same_usage_max, $rep_count);
                } elseif ($autolink_case === 'uppercase') { // uppercase
                    $replaced = preg_replace_callback($pattern, function ($matches) use ($link_openeing, $upperterm, $link_closing) {
                        return $link_openeing . strtoupper($matches[0]) . $link_closing;
                    }, $node->wholeText, $same_usage_max, $rep_count);
                } elseif ($autolink_case === 'termcase') { // termcase
                    $replaced = preg_replace_callback($pattern, function ($matches) use ($link_openeing, $search, $link_closing) {
                        return $link_openeing . $search . $link_closing;
                    }, $node->wholeText, $same_usage_max, $rep_count);
                } else { // lowercase
                    $replaced = preg_replace_callback($pattern, function ($matches) use ($link_openeing, $lowerterm, $link_closing) {
                        return $link_openeing . strtolower($matches[0]) . $link_closing;
                    }, $node->wholeText, $same_usage_max, $rep_count);
                }

                if ($replaced && !empty(trim($replaced))) {
                    $j++;
                    if ($rep_count > 0) {
                        // TODO : Think about synonyms
                        if (array_key_exists($replace, $replaced_tags_counts)) {
                            $replaced_tags_counts[$replace] = $replaced_tags_counts[$replace] + $rep_count;
                        } else {
                            $replaced_tags_counts[$replace] = $rep_count;
                        }
                        $option_tagged_counts[$detail_id] = $option_tagged_counts[$detail_id] + $rep_count;
                        $option_remaining[$detail_id] = $option_limits[$detail_id] - $option_tagged_counts[$detail_id];
                    }
                }
                $newNode = $dom->createDocumentFragment();
                $newNode->appendXML($replaced);

                $node->parentNode->replaceChild($newNode, $node);
                if ($option_remaining[$detail_id] === 0) {
                    break;
                }
            }
        }


        // Get the innerHTML of the root div, excluding the div itself
        $content = '';
        foreach ($dom->documentElement->childNodes as $node) {
            $content .= $dom->saveHTML($node);
        }

        // Add back the starting "&#"
        $content = str_replace('STARTTAXOPRESSENTITY', '&#', $content);
        // Add back the ending ";"
        $content = str_replace('TAXOPRESSENTITYEND', ';', $content);

        // get only the body tag with its contents, then trim the body tag itself to get only the original content
        //$content = mb_substr($dom->saveHTML($xpath->query('//body')->item(0)), 6, -7, "UTF-8");
        $content = str_replace('|--|', '&#', $content); //https://github.com/publishpress/publishpress-taxonomies/issues/824
        /**
         * I commented the line below because of https://github.com/publishpress/publishpress-taxonomies/issues/2118
         * In summary, when content contain < and > special character which are intentiona;, they're been
         * changed to < > which is not needed
         */
        //$content = str_replace('&#60;', '<', $content);
        //$content = str_replace('&#62;', '>', $content);

        foreach (taxopress_html_character_and_entity(true) as $enity => $code) {
            $content = str_replace($enity, $code, $content);
        }

        $content = str_replace('&amp ;rsquo;', '&rsquo;', $content);
        $content = str_replace(['’', ' ’', '&rsquor;', ' &rsquor;', '&rsquo;', ' &rsquo;'], '\'', $content);

        $content = str_replace('&#38;', '&', $content); //https://github.com/publishpress/publishpress-taxonomies/issues/770
        $content = str_replace(';amp;', ';', $content); //https://github.com/publishpress/publishpress-taxonomies/issues/810
        $content = str_replace('%7C--%7C038;', '&', $content); //https://github.com/publishpress/publishpress-taxonomies/issues/1377

        $content = str_replace('starttaxopressrandom', '', $content);
        $content = str_replace('endtaxopressrandom', '', $content);
        // replace <taxopressnotag> added to skip certain elements
        $content = str_replace('<taxopressnotag>', '', $content);
        $content = str_replace('</taxopressnotag>', '', $content);
    }

    /**
     * Replace text by link, except HTML tag, and already text into link, use PregEXP.
     *
     * @param string $content
     * @param string $search
     * @param string $replace
     * @param string $case
     * @param string $rel
     */
    private static function replace_by_links_regexp(&$content, $search = '', $replace = '', $case = '', $rel = '', $options = false)
    {

        if ($options) {
            $autolink_case = $options['autolink_case'];
            $html_exclusion = $options['html_exclusion'];
            $html_exclusion_customs  = isset($options['html_exclusion_customs']) ? $options['html_exclusion_customs'] : [];
            $exclude_class = $options['autolink_exclude_class'];
            $title_attribute = $options['autolink_title_attribute'];
            $title_attribute_custom_url = $options['autolink_title_attribute_when_using_custom_url'];
            $same_usage_max = $options['autolink_same_usage_max'];
            $max_by_post = $options['autolink_usage_max'];
            $link_class = isset($options['link_class']) ? taxopress_format_class($options['link_class']) : '';
        } else {
            $autolink_case = 'lowercase';
            $html_exclusion = [];
            $html_exclusion_customs = [];
            $exclude_class = '';
            $title_attribute = SimpleTags_Plugin::get_option_value('auto_link_title');
            $title_attribute_custom_url = SimpleTags_Plugin::get_option_value('auto_link_title_custom_url');
            $same_usage_max = SimpleTags_Plugin::get_option_value('auto_link_max_by_tag');
            $max_by_post = SimpleTags_Plugin::get_option_value('auto_link_max_by_post');
            $link_class = '';
        }


        if (!empty($html_exclusion_customs)) {
            $html_exclusion = array_merge($html_exclusion, $html_exclusion_customs);
        }

        $must_tokenize = true; // will perform basic tokenization
        $tokens        = null; // two kinds of tokens: markup and text

        $j        = 0;
        $filtered = ''; // will filter text token by token

        // Get URL and title first
        $url = array_key_exists($search, self::$link_tags) ? self::$link_tags[$search] : $replace;
        $used_title = self::taxopress_get_title_attribute($search, $url, $options);
        $replace = $url;

        // Determine word boundary pattern based on whole_words setting
        $whole_words = isset($options['whole_words']) ? (int)$options['whole_words'] : 1;
        if ($whole_words) {
            // Use \b for strict whole word matching
            $match = '/\b' . preg_quote($search, '/') . '\b/u' . $case;
            $substitute = '<a href="' . $replace . '" class="st_tag internal_tag ' . $link_class . '" ' . $rel . ' title="' . $used_title . "\">$0</a>";
        } else {
            // Allow partial matches - no word boundary checks
            $match = '/(' . preg_quote($search, '/') . ')/u' . $case;
            $substitute = '<a href="' . $replace . '" class="st_tag internal_tag ' . $link_class . '" ' . $rel . ' title="' . $used_title . "\">$1</a>";
        }

        //$match = "/\b" . preg_quote($search, "/") . "\b/".$case;
        //$substitute = '<a href="'.$replace.'" class="st_tag internal_tag '.$link_class.'" '.$rel.' title="'. esc_attr( sprintf( __('Posts tagged with %s', 'simple-tags'), $search ) )."\">$0</a>";
        // for efficiency only tokenize if forced to do so
        if ($must_tokenize) {
            // this regexp is taken from PHP Markdown by Michel Fortin: http://www.michelf.com/projects/php-markdown/
            $comment                = '(?s:<!(?:--.*?--\s*)+>)|';
            $processing_instruction = '(?s:<\?.*?\?>)|';
            $tag                    = '(?:<[/!$]?[-a-zA-Z0-9:]+\b(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*>)';

            $markup        = $comment . $processing_instruction . $tag;
            $flags         = PREG_SPLIT_DELIM_CAPTURE;
            $tokens        = preg_split("{($markup)}", $content, -1, $flags);
            $must_tokenize = false;
        }

        // there should always be at least one token, but check just in case
        $anchor_level = 0;

        if (isset($tokens) && is_array($tokens) && count($tokens) > 0) {
            $i = 0;
            $ancestor = '';
            foreach ($tokens as $token) {
                if (++$i % 2 && $token !== '') { // this token is (non-markup) text
                    $pass_check = true;

                    if (!empty(trim($ancestor))) {
                        //auto link exclusion
                        if (count($html_exclusion) > 0) {
                            foreach ($html_exclusion as $exclude_ancestor) {
                                if (taxopress_starts_with($ancestor, '<' . strtolower($exclude_ancestor) . '')) {
                                    $pass_check = false;
                                    break;
                                }
                            }
                        }


                        // Prepare exclude terms array
                        $excludes_class = explode(',', $exclude_class);
                        if (!empty($excludes_class)) {
                            $excludes_class = array_filter($excludes_class);
                            $excludes_class = array_unique($excludes_class);
                            if (count($excludes_class) > 0) {
                                foreach ($excludes_class as $idclass) {
                                    if (substr(trim($idclass), 0, 1) === "#") {
                                        $div_id = ltrim(trim($idclass), "#");
                                        if (preg_match_all('/<[a-z \'"]*id="' . $div_id . '"/i', $ancestor, $matches) || preg_match_all('/<[a-z \'"]*id=\'' . $div_id . '\'/i', $ancestor, $matches)) {
                                            $pass_check = false;
                                            break;
                                        }
                                    } else {
                                        $div_class = ltrim(trim($idclass), ".");
                                        if (preg_match_all('/<[a-z ]*class="' . $div_class . '"/i', $ancestor, $matches) || preg_match_all('/<[a-z ]*class=\'' . $div_class . '\'/i', $ancestor, $matches)) {
                                            $pass_check = false;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($anchor_level === 0 && $pass_check) { // linkify if not inside anchor tags
                        if (preg_match($match, $token)) { // use preg_match for compatibility with PHP 4
                            $j++;


                            $remaining_usage = $max_by_post - self::$tagged_link_count;
                            if ($same_usage_max > $remaining_usage) {
                                $same_usage_max = $remaining_usage;
                            }


                            if ($same_usage_max > 0) { // Limit replacement at 1 by default, or options value !
                                $token = preg_replace($match, $substitute, $token, $same_usage_max, $rep_count); // only PHP 5 supports calling preg_replace with 5 arguments
                                self::$tagged_link_count = self::$tagged_link_count + $rep_count;
                            }
                            $must_tokenize = true; // re-tokenize next time around
                        }
                    }
                } else { // this token is markup
                    if (preg_match("#<\s*a\s+[^>]*>#i", $token)) { // found <a ...>
                        $ancestor = $token;
                        $anchor_level++;
                    } elseif (preg_match("#<\s*/\s*a\s*>#i", $token)) { // found </a>
                        $anchor_level--;
                    } elseif (taxopress_starts_with($token, "</")) {
                        $ancestor = '';
                    } else {
                        $ancestor = $token;
                    }
                }
                $filtered .= $token; // this token has now been filtered
            }
            $content = $filtered; // filtering completed for this link
        }
    }


    /**
     * Replace text by link to tag
     *
     * @param string $content
     *
     * @return string
     */
    public static function taxopress_autolinks_the_content($content = '')
    {
        global $post;


        // Some Elementor render flows may call filters with no global $post set.
        // Try to recover a post object before bailing out.
        if (!is_object($post)) {
            $post = get_post();
        }


        if (!is_object($post) || is_admin()) {
            return $content;
        }

        $post_tags = taxopress_get_autolink_data();

        // User preference for this post?
        $feature_status = self::get_post_feature_status($post->ID);
        if (
            'disabled' === $feature_status
            || ('default' === $feature_status && 1 !== (int) SimpleTags_Plugin::get_option_value('active_auto_links'))
        ) {
            return $content;
        }

        $force_enabled = 'enabled' === $feature_status;

        if (count($post_tags) > 0) {
            $auto_link_replace = [];
            foreach ($post_tags as $post_tag) {
                // Get option
                $embedded = (isset($post_tag['embedded']) && is_array($post_tag['embedded']) && count($post_tag['embedded']) > 0) ? $post_tag['embedded'] : false;

                if (!$force_enabled && (!$embedded || !in_array($post->post_type, $embedded, true))) {
                    continue;
                }

                if ($post_tag['autolink_display'] === 'post_title') {
                    continue;
                }

                //reset tags just in case
                self::$link_tags = [];
                // Get currents tags if no exists
                self::prepare_auto_link_tags($post_tag);

                // Shuffle array
                SimpleTags_Client::random_array(self::$link_tags);

                // HTML Rel (tag/no-follow)
                $rel = SimpleTags_Client::get_rel_attribut();

                // only continue if the database actually returned any links
                if (!isset(self::$link_tags) || !is_array(self::$link_tags) || empty(self::$link_tags)) {
                    $can_continue = false;
                } else {
                    $can_continue = true;
                }

                if ($can_continue) {
                    // Case option ?
                    $case       = (1 === (int) $post_tag['ignore_case']) ? 'i' : '';
                    $strpos_fnc = ('i' === $case) ? 'stripos' : 'strpos';

                    $excludes_terms = self::prepare_excluded_terms($post_tag['auto_link_exclude']);

                    $z = 0;

                    foreach ((array) self::$link_tags as $term_name => $term_link) {
                        $z++;
                        // Force string for tags "number"
                        $term_name = (string) $term_name;

                        // Exclude terms ? next...
                        if (taxopress_in_array_i($term_name, (array) $excludes_terms, true)) {
                            continue;
                        }

                        // Make a first test with PHP function, economize CPU with regexp
                        if (false === $strpos_fnc($post->post_content, $term_name)) {
                            continue;
                        }

                        $auto_link_replace[] = [
                            'term_name'  => $term_name,
                            'term_link'  => $term_link,
                            'case'       => $case,
                            'rel'        => $rel,
                            'options'    => $post_tag,
                            'option_id'  => $post_tag['ID'],
                            'post_limit' => $post_tag['autolink_usage_max'],
                            'term_limit' => $post_tag['autolink_same_usage_max'],
                            'type'       => 'content',
                        ];

                        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
                            self::replace_by_links_regexp($content, $term_name, $term_link, $case, $rel, $post_tag);
                        }
                    }
                }
            }
            if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
                self::replace_by_links_dom($content, $auto_link_replace);
            }
        }
        return $content;
    }




    /**
     * Replace text by link to tag
     *
     * @param string $title
     *
     * @return string
     */
    public static function taxopress_autolinks_the_title($title = '')
    {
        global $post;

        if (!is_object($post) || is_admin()) {
            return $title;
        }

        $post_tags = taxopress_get_autolink_data();


        // User preference for this post?
        $feature_status = self::get_post_feature_status($post->ID);
        if (
            'disabled' === $feature_status
            || ('default' === $feature_status && 1 !== (int) SimpleTags_Plugin::get_option_value('active_auto_links'))
        ) {
            return $title;
        }

        $force_enabled = 'enabled' === $feature_status;

        if (count($post_tags) > 0) {
            foreach ($post_tags as $post_tag) {
                // Get option
                $embedded = (isset($post_tag['embedded']) && is_array($post_tag['embedded']) && count($post_tag['embedded']) > 0) ? $post_tag['embedded'] : false;

                if (!$force_enabled && (!$embedded || !in_array($post->post_type, $embedded, true))) {
                    continue;
                }

                if ($post_tag['autolink_display'] === 'post_content') {
                    continue;
                }
                //reset tags just in case
                self::$link_tags = [];
                // Get currents tags if no exists
                self::prepare_auto_link_tags($post_tag);

                // Shuffle array
                SimpleTags_Client::random_array(self::$link_tags);

                // HTML Rel (tag/no-follow)
                $rel = SimpleTags_Client::get_rel_attribut();

                // only continue if the database actually returned any links
                if (!isset(self::$link_tags) || !is_array(self::$link_tags) || empty(self::$link_tags)) {
                    $can_continue = false;
                } else {
                    $can_continue = true;
                }

                if ($can_continue) {
                    // Case option ?
                    $case       = (1 === (int) $post_tag['ignore_case']) ? 'i' : '';
                    $strpos_fnc = ('i' === $case) ? 'stripos' : 'strpos';

                    $excludes_terms = self::prepare_excluded_terms($post_tag['auto_link_exclude']);

                    $z = 0;
                    $auto_link_replace = [];
                    foreach ((array) self::$link_tags as $term_name => $term_link) {
                        $z++;
                        // Force string for tags "number"
                        $term_name = (string) $term_name;

                        // Exclude terms ? next...
                        if (taxopress_in_array_i($term_name, (array) $excludes_terms, true)) {
                            continue;
                        }

                        // Make a first test with PHP function, economize CPU with regexp
                        if (false === $strpos_fnc($title, $term_name)) {
                            continue;
                        }

                        $auto_link_replace[] = [
                            'term_name'  => $term_name,
                            'term_link'  => $term_link,
                            'case'       => $case,
                            'rel'        => $rel,
                            'options'    => $post_tag,
                            'option_id'  => $post_tag['ID'],
                            'post_limit' => $post_tag['autolink_usage_max'],
                            'term_limit' => $post_tag['autolink_same_usage_max'],
                            'type'       => 'content',
                        ];

                        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
                            self::replace_by_links_regexp($title, $term_name, $term_link, $case, $rel, $post_tag);
                        }
                    }
                    if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
                        self::replace_by_links_dom($title, $auto_link_replace);
                    }
                }
            }
        }


        return $title;
    }

    private static function get_post_feature_status($post_id)
    {
        $status = get_post_meta($post_id, '_taxopress_autolinks_status', true);

        if (in_array($status, ['default', 'enabled', 'disabled'], true)) {
            return $status;
        }

        if (get_post_meta($post_id, '_exclude_autolinks', true)) {
            return 'disabled';
        }

        return 'default';
    }
}
