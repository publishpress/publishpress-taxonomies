<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.Security.NonceVerification.Missing,WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_Autoterms
{
    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {
        if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
            add_action('save_post', array(__CLASS__, 'save_post'), 12, 2);
            add_action('post_syndicated_item', array(__CLASS__, 'save_post'), 12, 2);
        }
        add_filter('taxopress_filter_autoterm_content', array(__CLASS__, 'filter_taxopress_autoterm_content'), 10, 3);
    }

    /**
     * Check post/page content for auto terms
     *
     * @param integer $post_id
     * @param object $object
     *
     * @return boolean
     */
    public static function save_post($post_id = null, $object = null)
    {
        // Get options
        $options = get_option(STAGS_OPTIONS_NAME_AUTO);

        // user preference for this post ?
        $post_autoterms_status = self::get_post_feature_status($post_id, '_taxopress_autoterms_status', '_exclude_autotags');
        if ('disabled' === $post_autoterms_status) {
            return false;
        }

        if (!is_object($object)) {
            return;
        }

        if (!isset($object->post_type)) {
            return;
        }

        // Loop option for find if autoterms is actived on any taxonomy and post type
        $current_post_type = $object->post_type;
        $current_post_status = $object->post_status;
        $autoterms = taxopress_get_autoterm_data();
        $flag = false;

        foreach ($autoterms as $autoterm_data) {
            $eligible_post_status = isset($autoterm_data['post_status']) && is_array($autoterm_data['post_status']) ? $autoterm_data['post_status'] : ['publish'];
            $eligible_post_types = isset($autoterm_data['post_types']) && is_array($autoterm_data['post_types']) ? $autoterm_data['post_types'] : [];
            $eligible_post_types = array_filter($eligible_post_types);

            if (count($eligible_post_types) === 0) {
                continue;
            }
            if (!in_array($current_post_type, $eligible_post_types)) {
                continue;
            }
            if (!in_array($current_post_status, $eligible_post_status)) {
                continue;
            }
            // check if auto term is enabled for post
            if (empty($autoterm_data['autoterm_for_post']) && 'enabled' !== $post_autoterms_status) {
                continue;
            }

            $autoterm_data['replace_type'] = isset($autoterm_data['post_replace_type']) ? $autoterm_data['post_replace_type'] : '';

            self::auto_terms_post($object, $autoterm_data['taxonomy'], $autoterm_data, false, 'save_posts', 'st_autoterms');
            $flag = true;
        }

        if ($flag == true) { // Clean cache ?
            clean_post_cache($post_id);
        }

        return true;
    }

    /**
     * Filter auto term content
     *
     * @param string $content Original content to be analyzed. It could include post title,
     * content and/excerpt based on autoterms settings
     * @param integer $post_id This is the post id
     * @param array $options Autoterm settings
     *
     * @return string
     */
    public static function filter_taxopress_autoterm_content($content, $post_id, $settings_data)
    {

        // add taxonomies data to content
        if (!empty($settings_data['find_in_taxonomies_custom_items']) && is_array($settings_data['find_in_taxonomies_custom_items'])) {
            foreach ($settings_data['find_in_taxonomies_custom_items'] as $taxonomy) {
                // Fetch all terms in the specified taxonomy
                $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));

                // Check if there are any terms and then process them
                if (!is_wp_error($terms) && !empty($terms)) {
                    // Join the term names with a space
                    $joined_terms = implode(' ', $terms);
                    // add data to content
                    $content .= ' ' . $joined_terms;
                }
            }
        }

        // add custom field data to content
        if (!empty($settings_data['find_in_custom_fields_custom_items']) && is_array($settings_data['find_in_custom_fields_custom_items'])) {
            foreach ($settings_data['find_in_custom_fields_custom_items'] as $metakey) {
                // get meta key value
                $meta_value = get_post_meta($post_id, $metakey, true);
                if (!empty($meta_value)) {
                    // add data to content
                    $resolved_values = [];

                    $values = is_array($meta_value) ? $meta_value : array($meta_value);

                    foreach ($values as $value) {
                        if (is_numeric($value)) {
                            $term = get_term((int)$value);
                            if ($term && !is_wp_error($term) && !empty($term->name)) {
                                $resolved_values[] = $term->name;
                                continue;
                            }
                        }
                        if (is_object($value) || is_array($value)) {
                            if (is_array($value) && isset($value['name'])) {
                                $resolved_values[] = $value['name'];
                            } elseif (is_object($value) && isset($value->name)) {
                                $resolved_values[] = $value->name;
                            } else {
                                $resolved_values[] = wp_json_encode($value);
                            }
                            continue;
                        }

                        if (is_scalar($value)) {
                            $resolved_values[] = (string)$value;
                        }
                    }

                    if (!empty($resolved_values)) {
                        $resolved_string = implode(' ', $resolved_values);
                        $content .= ' ' . $resolved_string;
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Automatically tag a post/page from the database terms for the taxonomy specified
     *
     * @param object $object
     * @param string $taxonomy
     * @param array $options
     * @param boolean $counter
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function auto_terms_post($object, $taxonomy = 'post_tag', $options = array(), $counter = false, $action = 'save_posts', $component = 'st_autoterms')
    {
        global $wpdb, $added_post_term, $empty_term_messages;

        if (!is_array($empty_term_messages)) {
            $empty_term_messages = [];
        }

        if (!isset($empty_term_messages[$object->ID]['message'])) {
            $empty_term_messages[$object->ID]['message'] = [];
        }

        $terms_to_add = array();

        if ('disabled' === self::get_post_feature_status($object->ID, '_taxopress_autoterms_status', '_exclude_autotags')) {
            $empty_term_messages[$object->ID]['message'][] = esc_html__('Post is excluded from Auto Term from post area.', 'simple-tags');
            return false;
        }

        // Option exists ?
        if ($options == false || empty($options)) {
            $empty_term_messages[$object->ID]['message'][] = esc_html__('Invalid Auto Term settings.', 'simple-tags');
            //update log
            self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'invalid_option');
            return false;
        }

        if ($action == 'save_posts' && (int) $options['autoterm_target'] === 1 && get_the_terms($object->ID, $taxonomy) != false) {
            $empty_term_messages[$object->ID]['message'][] = esc_html__('Only use Auto Terms on posts with no added terms is enabled and this post contain terms.', 'simple-tags');
            //update log
            self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'term_only_option');
            return false; // Skip post with terms, if term only empty post option is checked
        }

        $autoterm_exclude = isset($options['autoterm_exclude']) ? taxopress_change_to_array($options['autoterm_exclude']) : [];


        $content_source = !empty($options['autoterm_from']) ? $options['autoterm_from'] : 'post_content_title';
        if ($content_source === 'post_title') {
            $content = $object->post_title;
        } elseif ($content_source === 'post_content') {
            $content = $object->post_content;
        } elseif ($content_source === 'posts') {
            $content = $object->post_content . ' ' . $object->post_title;
        }

        $html_exclusion  = (!empty($options['html_exclusion']) && is_array($options['html_exclusion'])) ? $options['html_exclusion'] : [];
        $html_exclusion_customs  = !empty($options['html_exclusion_customs']) ? $options['html_exclusion_customs'] : [];
        if (!empty($html_exclusion_customs)) {
            $html_exclusion = array_filter(array_merge($html_exclusion, $html_exclusion_customs));
        }
        if (!empty($content) && count($html_exclusion) > 0) {
            foreach ($html_exclusion as $html_tag) {
                $pattern = "/<{$html_tag}[^>]*>.*?<\/{$html_tag}>/is";
                $content = preg_replace($pattern, '', $content);
            }
        }

        /**
         * Filter auto term content
         *
         * @param string $content Original content to be analyzed. It could include post title,
         * content and/excerpt based on autoterms settings
         * @param integer $post_id This is the post id
         * @param array $options Autoterm settings
         */
        $content = apply_filters('taxopress_filter_autoterm_content', $content, $object->ID, $options);

        $content = trim(wp_strip_all_tags($content));

        if (empty(trim($content))) {
            $empty_term_messages[$object->ID]['message'][] = esc_html__('Auto Term content is empty. Could not suggest terms without content.', 'simple-tags');
            //update log
            self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'empty_post_content');
            return false;
        }

        $autoterm_use_open_ai = isset($options['autoterm_use_open_ai']) ? (int) $options['autoterm_use_open_ai'] : 0;
        $autoterm_use_ibm_watson = isset($options['autoterm_use_ibm_watson']) ? (int) $options['autoterm_use_ibm_watson'] : 0;
        $autoterm_use_dandelion = isset($options['autoterm_use_dandelion']) ? (int) $options['autoterm_use_dandelion'] : 0;
        $autoterm_use_opencalais = isset($options['autoterm_use_opencalais']) ? (int) $options['autoterm_use_opencalais'] : 0;
        $autoterm_regex_code = !empty($options['autoterm_use_regex']) && !empty($options['terms_regex_code']) ? stripslashes($options['terms_regex_code']) : '';
        $autoterm_use_taxonomy = !empty($options['autoterm_use_taxonomy']) && (int) $options['autoterm_use_taxonomy'] === 1;
        $autoterm_useall = !empty($options['autoterm_useall']) && (int) $options['autoterm_useall'] === 1;
        $autoterm_useonly = !empty($options['autoterm_useonly']) && (int) $options['autoterm_useonly'] === 1;

        $autoterm_replace_type = isset($options['replace_type']) ? $options['replace_type'] : '';

        $args = [
            'post_id' => $object->ID,
            'settings_data' => $options,
            'content' => $content,
            'taxonomy' => $taxonomy,
            'clean_content' => TaxoPressAiUtilities::taxopress_clean_up_content($content),
            'content_source' => 'autoterm_' . $content_source

        ];

        //Autoterm with OpenAI
        if ($autoterm_use_open_ai > 0 && taxopress_is_pro_version()) {
            $open_ai_results = TaxoPressAiApi::get_open_ai_results($args);
            if (!empty($open_ai_results['results'])) {
                $term_results = $open_ai_results['results'];
                $data = $term_results;
                foreach ((array) $data as $term) {
                    $term = stripslashes($term);

                    if (!is_string($term)) {
                        continue;
                    }

                    $term = trim($term);
                    if (empty($term)) {
                        continue;
                    }

                    //exclude if name found in exclude terms
                    if (in_array($term, $autoterm_exclude)) {
                        $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by OpenAI but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                        continue;
                    }

                    //add primary term
                    $add_terms = [];
                    $add_terms[$term] = $term;
                    // add term synonyms
                    if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                        $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                        if (!empty($term_synonyms)) {
                            foreach ($term_synonyms as $term_synonym) {
                                $add_terms[$term_synonym] = $term;
                            }
                        }
                    }

                    // add linked term
                    $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                    foreach ($add_terms as $find_term => $original_term) {
                        $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';

                        if (!in_array($action, ['save_posts', 'hourly_cron_schedule', 'daily_cron_schedule'])) {
                            // The settings only apply to saving post and schedule auto terms. So, just add the terms for other component since it's suggested by the service.
                            $terms_to_add[] = $term;
                        } else {
                            if (!empty($terms_regex_code)) {
                                if (preg_match("{$terms_regex_code}", $content)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] == 1) {
                                // Whole word ?
                                //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                                if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                                    $terms_to_add[] = $term;
                                }

                                //make exception for hashtag special character
                                if (substr($find_term, 0, strlen('#')) === '#') {
                                    $trim_term = ltrim($find_term, '#');
                                    if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                        $terms_to_add[] = $term;
                                    }
                                }

                                if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] == 1 && stristr($content, '#' . $find_term)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (stristr($content, $find_term)) {
                                $terms_to_add[] = $term;
                            }
                        }
                    }
                }
            } else {
                $empty_term_messages[$object->ID]['message'][] = esc_html__('OpenAI', 'simple-tags') . ': ' . $open_ai_results['message'];
            }
        }

        if ($autoterm_use_ibm_watson > 0 && taxopress_is_pro_version()) {
            //Autoterm with IBM Watson
            $ibm_watson_results = TaxoPressAiApi::get_ibm_watson_results($args);
            if (!empty($ibm_watson_results['results'])) {
                $term_results = $ibm_watson_results['results'];
                $data = $term_results;
                foreach ((array) $data as $term) {
                    $term = stripslashes($term);

                    if (!is_string($term)) {
                        continue;
                    }

                    $term = trim($term);
                    if (empty($term)) {
                        continue;
                    }

                    //exclude if name found in exclude terms
                    if (in_array($term, $autoterm_exclude)) {
                        $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by IBM Watson but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                        continue;
                    }

                    //add primary term
                    $add_terms = [];
                    $add_terms[$term] = $term;
                    // add term synonyms
                    if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                        $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                        if (!empty($term_synonyms)) {
                            foreach ($term_synonyms as $term_synonym) {
                                $add_terms[$term_synonym] = $term;
                            }
                        }
                    }

                    // add linked term
                    $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                    foreach ($add_terms as $find_term => $original_term) {
                        $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';

                        if (!in_array($action, ['save_posts', 'hourly_cron_schedule', 'daily_cron_schedule'])) {
                            // The settings only apply to saving post and schedule auto terms. So, just add the terms for other component since it's suggested by the service.
                            $terms_to_add[] = $term;
                        } else {
                            if (!empty($terms_regex_code)) {
                                if (preg_match("{$terms_regex_code}", $content)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] == 1) {
                                // Whole word ?
                                //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                                if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                                    $terms_to_add[] = $term;
                                }

                                //make exception for hashtag special character
                                if (substr($find_term, 0, strlen('#')) === '#') {
                                    $trim_term = ltrim($find_term, '#');
                                    if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                        $terms_to_add[] = $term;
                                    }
                                }

                                if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] == 1 && stristr($content, '#' . $find_term)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (stristr($content, $find_term)) {
                                $terms_to_add[] = $term;
                            }
                        }
                    }
                }
            } else {
                $empty_term_messages[$object->ID]['message'][] = esc_html__('IBM Watson', 'simple-tags') . ': ' . $ibm_watson_results['message'];
            }
        }

        if ($autoterm_use_dandelion > 0 && taxopress_is_pro_version()) {
            //Autoterm with Dandelion
            $dandelion_results = TaxoPressAiApi::get_dandelion_results($args);
            if (!empty($dandelion_results['results'])) {
                $term_results = $dandelion_results['results'];
                $data = $term_results;
                if (!empty($data)) {
                    foreach ((array) $data as $term) {
                        $term = stripslashes($term);

                        if (!is_string($term)) {
                            continue;
                        }

                        $term = trim($term);
                        if (empty($term)) {
                            continue;
                        }

                        //exclude if name found in exclude terms
                        if (in_array($term, $autoterm_exclude)) {
                            $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by Dandelion but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                            continue;
                        }

                        //add primary term
                        $add_terms = [];
                        $add_terms[$term] = $term;
                        // add term synonyms
                        if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                            $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                            if (!empty($term_synonyms)) {
                                foreach ($term_synonyms as $term_synonym) {
                                    $add_terms[$term_synonym] = $term;
                                }
                            }
                        }

                        // add linked term
                        $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                        foreach ($add_terms as $find_term => $original_term) {
                            $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';

                            if (!in_array($action, ['save_posts', 'hourly_cron_schedule', 'daily_cron_schedule'])) {
                                // The settings only apply to saving post and schedule auto terms. So, just add the terms for other component since it's suggested by the service.
                                $terms_to_add[] = $term;
                            } else {
                                if (!empty($terms_regex_code)) {
                                    if (preg_match("{$terms_regex_code}", $content)) {
                                        $terms_to_add[] = $term;
                                    }
                                } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] == 1) {
                                    // Whole word ?
                                    //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                                    if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                                        $terms_to_add[] = $term;
                                    }

                                    //make exception for hashtag special character
                                    if (substr($find_term, 0, strlen('#')) === '#') {
                                        $trim_term = ltrim($find_term, '#');
                                        if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                            $terms_to_add[] = $term;
                                        }
                                    }

                                    if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] == 1 && stristr($content, '#' . $find_term)) {
                                        $terms_to_add[] = $term;
                                    }
                                } elseif (stristr($content, $find_term)) {
                                    $terms_to_add[] = $term;
                                }
                            }
                        }
                    }
                }
            } else {
                $empty_term_messages[$object->ID]['message'][] = esc_html__('Dandelion', 'simple-tags') . ': ' . $dandelion_results['message'];
            }
        }

        if ($autoterm_use_opencalais > 0 && taxopress_is_pro_version()) {
            //Autoterm with OpenCalais
            $open_calais_results = TaxoPressAiApi::get_open_calais_results($args);
            if (!empty($open_calais_results['results'])) {
                $data = $open_calais_results['results'];
                foreach ((array) $data as $term) {
                    $term = stripslashes($term);

                    if (!is_string($term)) {
                        continue;
                    }

                    $term = trim($term);
                    if (empty($term)) {
                        continue;
                    }

                    //exclude if name found in exclude terms
                    if (in_array($term, $autoterm_exclude)) {
                        $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by OpenCalais but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                        continue;
                    }

                    //add primary term
                    $add_terms = [];
                    $add_terms[$term] = $term;

                    // add term synonyms
                    if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                        $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                        if (!empty($term_synonyms)) {
                            foreach ($term_synonyms as $term_synonym) {
                                $add_terms[$term_synonym] = $term;
                            }
                        }
                    }

                    // add linked term
                    $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                    foreach ($add_terms as $find_term => $original_term) {
                        $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';

                        if (!in_array($action, ['save_posts', 'hourly_cron_schedule', 'daily_cron_schedule'])) {
                            // The settings only apply to saving post and schedule auto terms. So, just add the terms for other component since it's suggested by the service.
                            $terms_to_add[] = $term;
                        } else {
                            if (!empty($terms_regex_code)) {
                                if (preg_match("{$terms_regex_code}", $content)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] == 1) {
                                // Whole word ?
                                //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                                if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                                    $terms_to_add[] = $term;
                                }

                                //make exception for hashtag special character
                                if (substr($find_term, 0, strlen('#')) === '#') {
                                    $trim_term = ltrim($find_term, '#');
                                    if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                        $terms_to_add[] = $term;
                                    }
                                }

                                if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] == 1 && stristr($content, '#' . $find_term)) {
                                    $terms_to_add[] = $term;
                                }
                            } elseif (stristr($content, $find_term)) {
                                $terms_to_add[] = $term;
                            }
                        }
                    }
                }
            } else {
                $empty_term_messages[$object->ID]['message'][] = esc_html__('OpenCalais', 'simple-tags') . ': ' . $open_calais_results['message'];
            }
        }

        if ($autoterm_use_taxonomy && $autoterm_useonly && !empty($options['specific_terms'])) {
            // Auto term with specific auto terms list
            $terms = taxopress_sanitize_specific_terms($options['specific_terms']);
            foreach ($terms as $term) {
                if (!is_string($term)) {
                    continue;
                }

                $term = trim($term);
                if (empty($term)) {
                    continue;
                }

                //exclude if name found in exclude terms
                if (in_array($term, $autoterm_exclude)) {
                    $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by Suggested terms from specific terms but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                    continue;
                }

                //add primary term
                $add_terms = [];
                $add_terms[$term] = $term;

                // add term synonyms
                if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                    $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                    if (!empty($term_synonyms)) {
                        foreach ($term_synonyms as $term_synonym) {
                            $add_terms[$term_synonym] = $term;
                        }
                    }
                }

                // add linked term
                $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                foreach ($add_terms as $find_term => $original_term) {
                    $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';
                    // This settings must be applied to inbuilt suggested terms as we need to suggest terms that are found in content
                    if (!empty($terms_regex_code)) {
                        if (preg_match("{$terms_regex_code}", $content)) {
                            $terms_to_add[] = $term;
                        }
                    } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] === 1) {
                        // Whole word ?
                        //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                        if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                            $terms_to_add[] = $term;
                        }

                        //make exception for hashtag special character
                        if (substr($find_term, 0, strlen('#')) === '#') {
                            $trim_term = ltrim($find_term, '#');
                            if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                $terms_to_add[] = $term;
                            }
                        }

                        if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] === 1 && stristr($content, '#' . $find_term)) {
                            $terms_to_add[] = $term;
                        }
                    } elseif (stristr($content, $find_term)) {
                        $terms_to_add[] = $term;
                    }
                }
            }
            unset($terms, $term);
        } elseif ($autoterm_use_taxonomy && $autoterm_useall) {
            // Auto terms with all terms
            // Get all terms
            $terms = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT name
				FROM {$wpdb->terms} AS t
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s", $taxonomy));

            $terms = array_unique($terms);

            foreach ($terms as $term) {
                $term = stripslashes($term);

                if (!is_string($term)) {
                    continue;
                }

                $term = trim($term);
                if (empty($term)) {
                    continue;
                }

                //exclude if name found in exclude terms
                if (in_array($term, $autoterm_exclude)) {
                    $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested by Suggested terms from taxonomy terms but excluded by Auto Term Exception settings.', 'simple-tags'), '<strong>' . $term . '</strong>');
                    continue;
                }

                //add primary term
                $add_terms = [];
                $add_terms[$term] = $term;

                // add term synonyms
                if (is_array($options) && isset($options['synonyms_term']) && (int) $options['synonyms_term'] > 0) {
                    $term_synonyms = taxopress_get_term_synonyms($term, $taxonomy);
                    if (!empty($term_synonyms)) {
                        foreach ($term_synonyms as $term_synonym) {
                            $add_terms[$term_synonym] = $term;
                        }
                    }
                }

                // add linked term
                $add_terms = taxopress_add_linked_term_options($add_terms, $term, $taxonomy, false, true);

                foreach ($add_terms as $find_term => $original_term) {
                    $terms_regex_code = !empty($autoterm_regex_code) ? str_replace('{term}', preg_quote($find_term), $autoterm_regex_code) : '';

                    // This settings must be applied to inbuilt suggested terms as we need to suggest terms that are found in content
                    if (!empty($terms_regex_code)) {
                        if (preg_match("{$terms_regex_code}", $content)) {
                            $terms_to_add[] = $term;
                        }
                    } elseif (isset($options['autoterm_word']) && (int) $options['autoterm_word'] == 1) {
                        // Whole word ?
                        //if (preg_match("/\b" . preg_quote($find_term) . "\b/i", $content)) {
                        if (preg_match("#\b" . preg_quote($find_term) . "\b#i", $content)) {
                            $terms_to_add[] = $term;
                        }

                        //make exception for hashtag special character
                        if (substr($find_term, 0, strlen('#')) === '#') {
                            $trim_term = ltrim($find_term, '#');
                            if (preg_match("/\B(\#+$trim_term\b)(?!;)/i", $content)) {
                                $terms_to_add[] = $term;
                            }
                        }

                        if (isset($options['autoterm_hash']) && (int) $options['autoterm_hash'] == 1 && stristr($content, '#' . $find_term)) {
                            $terms_to_add[] = $term;
                        }
                    } elseif (stristr($content, $find_term)) {
                        $terms_to_add[] = $term;
                    }
                }
            }
        }

        // Append terms if terms to add
        if (!empty($terms_to_add)) {
            // Remove empty and duplicate elements
            $terms_to_add = array_filter($terms_to_add, '_delete_empty_element');
            $terms_to_add = array_unique($terms_to_add);

            //auto terms limit
            $terms_limit = isset($options['terms_limit']) ? (int) $options['terms_limit'] : 5;
            if ($terms_limit > 0 && count($terms_to_add) > $terms_limit) {
                $terms_to_add = array_slice($terms_to_add, 0, $terms_limit);
            }

            // remove term that already belongs to the post
            foreach ($terms_to_add as $index => $term_name) {
                if (has_term($term_name, $taxonomy, $object)) {
                    unset($terms_to_add[$index]);
                    $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('%1s was suggested but not added because it already belongs to the post.', 'simple-tags'), '<strong>' . $term_name . '</strong>');
                }
            }

            $terms_to_add = array_filter(array_values($terms_to_add));

            if (empty($terms_to_add)) {
                // remove term if replace type is replace and term is empty
                if ($autoterm_replace_type == 'replace') {
                    $empty_term_messages[$object->ID]['message'][] = esc_html__('No term was suggested but all post terms were removed based on Auto Terms replace settings.', 'simple-tags');
                    wp_set_object_terms($object->ID, [], $taxonomy, true);
                    //update log
                    self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'empty_terms_remove_all');
                } else {
                    //update log
                    self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'empty_terms');
                }
                return false;
            }

            if ($counter == true) {
                // Increment counter
                $counter = ((int) get_option('tmp_auto_terms_st')) + count($terms_to_add);
                update_option('tmp_auto_terms_st', $counter);
            }

            if (!is_array($added_post_term)) {
                $added_post_term = [];
            }

            $added_post_term[$object->ID] = $terms_to_add;

            // Validate taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('Taxonomy "%s" does not exist.', 'simple-tags'), $taxonomy);
                return false;
            }

            // Check if terms exist in taxonomy and create if needed
            $terms_to_add_ids = [];
            foreach ($terms_to_add as $term_name) {
                $term_exists = term_exists($term_name, $taxonomy);
                if ($term_exists) {
                    $term_id = is_array($term_exists) ? $term_exists['term_id'] : $term_exists;
                    $terms_to_add_ids[] = (int) $term_id;
                } else {
                    $new_term = wp_insert_term($term_name, $taxonomy);
                    if (is_wp_error($new_term)) {
                        $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('Error creating term "%s": %s', 'simple-tags'), $term_name, $new_term->get_error_message());
                    } else {
                        $terms_to_add_ids[] = (int) $new_term['term_id'];
                    }
                }
            }

            clean_object_term_cache($object->ID, $taxonomy);

            // Get current terms to compare later
            $current_terms_before = wp_get_object_terms($object->ID, $taxonomy, array('fields' => 'names'));
            if (is_wp_error($current_terms_before)) {
                $current_terms_before = array();
            }

            // Remove save_post hooks temporarily to prevent infinite loops during term assignment
            remove_action('save_post', array(__CLASS__, 'save_post'), 12);
            remove_action('post_syndicated_item', array(__CLASS__, 'save_post'), 12);

            $result = false;
            $append_mode = false;
            if (empty($terms_to_add_ids)) {
                // Nothing to assign
            } else {
                $post_exists = get_post($object->ID);
                if (!$post_exists) {
                    $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('Error: Post ID %d does not exist.', 'simple-tags'), $object->ID);
                } else {
                }

                // Check if taxonomy is registered for this post type
                $post_type_taxonomies = get_object_taxonomies($post_exists->post_type);
                if (in_array($taxonomy, $post_type_taxonomies)) {
                } else {
                    $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('Warning: Taxonomy "%s" is not registered for post type "%s".', 'simple-tags'), $taxonomy, $post_exists->post_type);
                }

                // Remove all filters on set_object_terms that might be blocking
                global $wp_filter;
                $backup_set_object_terms_filters = isset($wp_filter['set_object_terms']) ? $wp_filter['set_object_terms'] : null;

                if (isset($wp_filter['set_object_terms'])) {
                    unset($wp_filter['set_object_terms']);
                }

                if ($autoterm_replace_type == '' || $autoterm_replace_type == 'append') {
                    $append_mode = true;
                    $result = wp_set_object_terms($object->ID, $terms_to_add_ids, $taxonomy, true);
                } elseif ($autoterm_replace_type == 'append_and_replace' || $autoterm_replace_type == 'replace') {
                    $append_mode = false;
                    $result = wp_set_object_terms($object->ID, $terms_to_add_ids, $taxonomy, false);
                }

                // Restore filters
                if ($backup_set_object_terms_filters !== null) {
                    $wp_filter['set_object_terms'] = $backup_set_object_terms_filters;
                }
            }

            // If core API failed without WP_Error, apply DB-level fallback
            if ($result === false && !empty($terms_to_add_ids)) {
                global $wpdb;
                $fallback_tt_ids = array();
                foreach ($terms_to_add_ids as $term_id) {
                    $term_id = (int) $term_id;
                    $term_info = term_exists($term_id, $taxonomy);
                    if (is_array($term_info) && !empty($term_info['term_taxonomy_id'])) {
                        $fallback_tt_ids[] = (int) $term_info['term_taxonomy_id'];
                    }
                }
                $fallback_tt_ids = array_values(array_unique(array_filter($fallback_tt_ids)));

                if (!empty($fallback_tt_ids)) {
                    $existing_tt_ids = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT tr.term_taxonomy_id
						 FROM {$wpdb->term_relationships} AS tr
						 INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						 WHERE tr.object_id = %d AND tt.taxonomy = %s",
                            $object->ID,
                            $taxonomy
                        )
                    );
                    if (!is_array($existing_tt_ids)) {
                        $existing_tt_ids = array();
                    }
                    $existing_tt_ids = array_map('intval', $existing_tt_ids);

                    // Compute final tt_ids based on replace/append behavior
                    if ($autoterm_replace_type == 'append_and_replace' || $autoterm_replace_type == 'replace') {
                        $final_tt_ids = $fallback_tt_ids;
                        if (!empty($existing_tt_ids)) {
                            foreach ($existing_tt_ids as $existing_tt_id) {
                                $wpdb->delete(
                                    $wpdb->term_relationships,
                                    array(
                                        'object_id' => (int) $object->ID,
                                        'term_taxonomy_id' => (int) $existing_tt_id
                                    ),
                                    array('%d', '%d')
                                );
                            }
                        }
                    } else {
                        $final_tt_ids = array_values(array_unique(array_merge($existing_tt_ids, $fallback_tt_ids)));
                    }

                    foreach ($final_tt_ids as $tt_id) {
                        $tt_id = (int) $tt_id;
                        $wpdb->query(
                            $wpdb->prepare(
                                "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
								 VALUES (%d, %d, 0)",
                                $object->ID,
                                $tt_id
                            )
                        );
                    }

                    if (!empty($final_tt_ids)) {
                        wp_update_term_count($final_tt_ids, $taxonomy);
                        clean_object_term_cache($object->ID, $taxonomy);
                        wp_cache_delete($object->ID, 'posts');
                        wp_cache_delete($object->ID, 'post_meta');
                        $result = $final_tt_ids;
                    }
                } else {
                    // No valid term_taxonomy_ids resolved; nothing to do.
                }
            }

            $current_terms_after = wp_get_object_terms($object->ID, $taxonomy, array('fields' => 'names'));
            if (is_wp_error($current_terms_after)) {
                $current_terms_after = array();
            }

            $newly_added_terms = array_diff($current_terms_after, $current_terms_before);
            if (!empty($newly_added_terms)) {
                $empty_term_messages[$object->ID]['message'][] = sprintf('Terms added: %s', esc_html(implode(', ', $newly_added_terms)));
            }

            // Re-add save_post hooks
            if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
                add_action('save_post', array(__CLASS__, 'save_post'), 12, 2);
                add_action('post_syndicated_item', array(__CLASS__, 'save_post'), 12, 2);
            }

            // Check if wp_set_object_terms failed
            if (is_wp_error($result)) {
                $empty_term_messages[$object->ID]['message'][] = sprintf(esc_html__('Error adding terms: %s', 'simple-tags'), $result->get_error_message());
                return false;
            }

            // Clean cache
            clean_post_cache($object->ID);

            //update log
            self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'success', 'terms_added');

            return true;
        } else {
            // remove term if replace type is replace and term is empty
            if ($autoterm_replace_type == 'replace') {
                wp_set_object_terms($object->ID, [], $taxonomy, true);
            }

            //update log
            self::update_taxopress_logs($object, $taxonomy, $options, $counter, $action, $component, $terms_to_add, 'failed', 'empty_terms_remove_all');
        }

        return false;
    }

    /**
     * Update PublishPress Taxonomies logs
     *
     * Known possible values
     *
     * COMPONENT: (st_autoterms)
     * ACTION: (existing_content, save_posts, daily_cron_schedule, hourly_cron_schedule, weekly_cron_schedule)
     * STATUS: (failed, success)
     * STATUS MESSAGE: (invalid_option, term_only_option, empty_post_content, terms_added, empty_terms, empty_terms_remove_all)
     *
     * @param object $object
     * @param string $taxonomy
     * @param array $options
     * @param boolean $counter
     * @param string $action
     * @param string $component
     * @param array $terms_to_add
     * @param string $status
     * @param string $status_message
     *
     * @return boolean
     * @author olatechpro
     */
    public static function update_taxopress_logs($object, $taxonomy = 'post_tag', $options = array(), $counter = false, $action = 'save_posts', $component = 'st_autoterms', $terms_to_add = [], $status = 'failed', $status_message = 'not_provided')
    {
        if (get_option('taxopress_autoterms_logs_disabled') || !post_type_exists('taxopress_logs')) {
            return;
        }

        $insert_post_args = array(
            'post_author' => get_current_user_id(),
            'post_title' => $object->post_title,
            'post_content' => $object->post_content,
            'post_status' => 'publish',
            'post_type' => 'taxopress_logs',
        );
        $post_id = wp_insert_post($insert_post_args);
        update_post_meta($post_id, '_taxopress_log_post_id', $object->ID);
        update_post_meta($post_id, '_taxopress_log_taxonomy', $taxonomy);
        update_post_meta($post_id, '_taxopress_log_post_type', get_post_type($object->ID));
        update_post_meta($post_id, '_taxopress_log_action', $action);
        update_post_meta($post_id, '_taxopress_log_component', $component);
        update_post_meta($post_id, '_taxopress_log_terms', implode(", ", $terms_to_add));
        update_post_meta($post_id, '_taxopress_log_status', $status);
        update_post_meta($post_id, '_taxopress_log_status_message', $status_message);
        update_post_meta($post_id, '_taxopress_log_options', $options);
        update_post_meta($post_id, '_taxopress_log_option_id', isset($options['ID']) ? $options['ID'] : 0);

        //for performance reason, delete only 1 posts if more than limit instead of querying all posts
        $auto_terms_logs_limit = (int) get_option('taxopress_auto_terms_logs_limit', 1000);

        $current_logs_counts = wp_count_posts('taxopress_logs');
        $current_logs_count = isset($current_logs_counts->publish) ? $current_logs_counts->publish : 0;

        if (isset($current_logs_counts->publish) && (int) $current_logs_count > $auto_terms_logs_limit) {
            $posts = get_posts(
                array(
                    'post_type' => 'taxopress_logs',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'fields' => 'ids'
                )
            );
            if (count($posts) > 0) {
                foreach ($posts as $post) {
                    wp_delete_post($post, true);
                }
            }
        }
    }

    private static function get_post_feature_status($post_id, $status_meta_key, $legacy_disable_meta_key = '')
    {
        $status = get_post_meta($post_id, $status_meta_key, true);

        if (in_array($status, ['default', 'enabled', 'disabled'], true)) {
            return $status;
        }

        if ($legacy_disable_meta_key && get_post_meta($post_id, $legacy_disable_meta_key, true)) {
            return 'disabled';
        }

        return 'default';
    }
}
