<?php

if (!class_exists('TaxoPressAiUtilities')) {
    class TaxoPressAiUtilities
    {
        /**
         * Fetch our PublishPress Taxonomies ai settings data.
         *
         * @return mixed
         */
        public static function taxopress_get_ai_settings_data($post_type = false)
        {
            // new settings now store it for each post type
            if ($post_type) {
                $settings_option = [
                    'existing_terms_orderby' => SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_metabox_orderby'),
                    'existing_terms_order' => SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_metabox_order'),
                    'existing_terms_maximum_terms' => SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_metabox_maximum_terms'),
                    'existing_terms_show_post_count' => SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_metabox_show_post_count'),
                ];
            } else {
                // keeping this for legacy purpose
                $settings_option = (array) apply_filters(
                    'taxopress_get_ai_settings_data',
                    get_option(TaxoPress_AI_Module::TAXOPRESS_AI_OPTION_KEY, []),
                    get_current_blog_id()
                );
            }

            return $settings_option;
        }

        /**
         * Get PublishPress Taxonomies ai groups
         *
         * @return array
         */
        public static function get_taxopress_ai_groups()
        {
            $field_tabs = TaxoPressAiFields::get_fields_tabs();

            return array_keys($field_tabs);
        }

        /**
         * Get previewed features
         *
         * @return array
         */
        public static function get_preview_features()
        {
            $preview_features = [
                'suggest_terms' => esc_html__('Suggest Terms', 'simple-tags'),
                'auto_terms' => esc_html__('Auto Terms', 'simple-tags')
            ];

            return $preview_features;
        }

        /**
         * Get taxonomies
         *
         * @return array
         */
        public static function get_taxonomies($include_data = false)
        {

            // phpcs:ignore Squiz.PHP.CommentedOutCode
            $all_taxonomies = get_taxonomies([], 'objects');//'public' => true, 'show_ui' => true

            $taxonomies = [];
            foreach ($all_taxonomies as $tax) {
                if (empty($tax->labels->name)) {
                    continue;
                }

                if (in_array($tax->name, ['post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category'])) {
                    continue;
                }

                $object_types = !(empty($tax->object_type)) ? $tax->object_type : [];

                if (is_array($object_types) && !empty($object_types)) {
                    $show_category = false;
                    foreach ($object_types as $object_type) {
                        if (!empty($tax->show_ui)) {
                            $show_category = true;
                        } elseif (!empty(SimpleTags_Plugin::get_option_value('taxopress_ai_' . $object_type . '_support_private_taxonomy'))) {
                            $show_category = true;
                        }
                    }

                    if (!$show_category) {
                        continue;
                    }
                }

                $taxonomies[$tax->name] = $include_data ? $tax : $tax->labels->name . ' (' . $tax->name . ')';
            }

            return $taxonomies;
        }

        /**
         * Get existing terms order by
         *
         * @return array
         */
        public static function get_existing_terms_orderby()
        {
            $orderby = [
                'name' => esc_html__('Name', 'simple-tags'),
                'count' => esc_html__('Counter', 'simple-tags'),
                'random' => esc_html__('Random', 'simple-tags')
            ];

            return $orderby;
        }

        /**
         * Get auto term options
         *
         * @return array
         */
        public static function get_auto_term_options()
        {

            $autoterm_data = taxopress_get_autoterm_data();

            $auto_term_opions = [];
            foreach ($autoterm_data as $autoterm_settings) {
                $auto_term_opions[$autoterm_settings['ID']] = $autoterm_settings['title'];
            }

            return $auto_term_opions;
        }

        /**
         * Get existing terms order
         *
         * @return array
         */
        public static function get_existing_terms_order()
        {
            $order = [
                'asc' => esc_html__('Ascending', 'simple-tags'),
                'desc' => esc_html__('Descending', 'simple-tags')
            ];

            return $order;
        }

        /**
         * Get post types options
         *
         * @return array
         */
        public static function get_post_types_options()
        {
            global $taxopress_ai_post_types_options;

            if (!is_array($taxopress_ai_post_types_options)) {
                $taxopress_ai_post_types_options = get_post_types(['public' => true], 'objects');
            }

            return $taxopress_ai_post_types_options;
        }

        /**
         * Clean up post content for API request.
         *
         * @param string $post_content The content of the post.
         * @param string $post_title The title of the post.
         *
         * @return string The cleaned-up content.
         */
        public static function taxopress_clean_up_content($post_content = '', $post_title = '')
        {

            // Return empty string if both post content and title are empty
            if (empty($post_content) && empty($post_title)) {
                return '';
            }

            // Apply content and title filters if provided
            if (!empty($post_content)) {
                $post_content = apply_filters('the_content', $post_content);

                /* Remove HTML entities */
                $post_content = preg_replace('/&#?[a-z0-9]{2,8};/i', '', $post_content);

                /*  Remove abbreviations */
                $post_content = preg_replace('/[A-Z][A-Z]+/', '', $post_content);

                /* Replace HTML line breaks with newlines */
                $post_content = preg_replace('#<br\s?/?>#', "\n\n", $post_content);

                // Strip all remaining HTML tags
                $post_content = wp_strip_all_tags($post_content);
            }

            // Initialize the cleaned-up content variable
            $cleaned_up_content = '';

            // Combine post title and content if both are available
            if (!empty($post_content) && !empty($post_title)) {
                $cleaned_up_content = $post_title . ".\n\n" . $post_content;
            } elseif (!empty($post_content)) {
                $cleaned_up_content = $post_content;
            } elseif (!empty($post_title)) {
                $cleaned_up_content = $post_title;
            }
            return $cleaned_up_content;
        }

        /**
         * Get term post counts
         *
         * @param integer $term_id
         * @param string $taxonomy
         *
         * @return integer
         */
        public static function count_term_posts($term_id, $taxonomy)
        {
            global $wpdb;

            // No caching used intentionally - this method is called during metabox rendering
            // where users actively manage term assignments. Cached counts could become stale
            // and display incorrect numbers to the user. The COUNT query is optimized on
            // indexed columns and has minimal performance impact.
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships AS tr
                INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.term_id = %d AND tt.taxonomy = %s",
                $term_id,
                $taxonomy
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $term_count = $wpdb->get_var($count_query);

            return $term_count;
        }

        /**
         * Format taxonomy term results
         *
         * @param array $term_results
         * @param string $taxonomy
         * @param integer $post_id
         * @param string $legend_title
         * @param bool $show_counts
         * @param array $current_tags
         * @param array $args
         *
         * @return string
         */
        public static function format_taxonomy_term_results($term_results, $taxonomy, $post_id, $legend_title, $show_counts, $current_tags = [], $args = [])
        {
            if (empty($term_results)) {
                return '';
            }

            $post_data          = get_post($post_id);
            $taxonomy_details   = get_taxonomy($taxonomy);
            $post_type_details  = get_post_type_object($post_data->post_type);
            $post_terms         = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
            $post_terms         = array_merge($post_terms, $current_tags);
            $screen_source = !empty($args['screen_source']) ? $args['screen_source'] : 'st_autoterms';
            $show_term_slug = !empty($args['show_term_slug']) ? $args['show_term_slug'] : false;
            $metabox_display_option = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_data->post_type . '_metabox_display_option');

            if (empty($metabox_display_option) || $screen_source == 'st_autoterms') {
                $metabox_display_option = 'default';
            }

            if ($metabox_display_option == 'default') {
                if (count($post_terms) === count($term_results)) {
                    $modified_legend_title = '<span class="ai-select-all all-selected" data-select-all="' . sprintf(esc_attr__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" data-deselect-all="' . sprintf(esc_attr__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '">' . sprintf(esc_html__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '</span>';
                } else {
                    $modified_legend_title = '<span class="ai-select-all" data-select-all="' . sprintf(esc_attr__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" data-deselect-all="' . sprintf(esc_attr__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '">' . sprintf(esc_html__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '</span>';
                }
            } else {
                $modified_legend_title = '<span>' . esc_html($taxonomy_details->labels->name) . '</span>';
            }

            // phpcs:ignore Squiz.PHP.CommentedOutCode -- Optional legend display, intentionally commented for future use
            // $modified_legend_title .= ' <span class="ai-original-legend">'. $legend_title .'</span>';
            $response_content = '<div class="preview-action-title taxopress-autoterm-element ' . esc_attr($metabox_display_option) . '"><p class="description">';
            $response_content .= sprintf(esc_html__('Click %1s to select or deselect them from this %2s.', 'simple-tags'), esc_html($taxonomy_details->labels->name), esc_html($post_type_details->labels->singular_name));
            $response_content .= '</p></div>';
            $response_content .= '<fieldset class="previewed-tag-fieldset">';
            $response_content .= '<legend> ' . $modified_legend_title . ' </legend>';
            $response_content .= '<div class="previewed-tag-content taxopress-autoterm-element ' . esc_attr($metabox_display_option) . '">';

            $additional_html = '';
            if ($metabox_display_option == 'dropdown') {
                $additional_html .= '<div class="auto-terms-options-wrap select"><select name="auto_term_terms_options[]" class="auto_term_terms_options select" data-placeholder="' . sprintf(esc_html__('Select %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" style="width: 99%;" multiple>';
            } elseif ($metabox_display_option == 'radio') {
                $additional_html .= '<div class="auto-terms-options-wrap radio">';
            } elseif ($metabox_display_option == 'checkbox') {
                $additional_html .= '<div class="auto-terms-options-wrap checkbox">';
            }

            $term_link_id = 1;
            foreach ($term_results as $term_result) {
                if (!is_string($term_result) || empty(trim($term_result)) || !wp_strip_all_tags($term_result)) {
                    continue;
                }
                $term_result = stripslashes(rtrim($term_result, ','));

                $linked_term_results = [$term_result => 0];
                // Check if the term exists for the given taxonomy
                $term = get_term_by('name', $term_result, $taxonomy);
                if ($term) {
                    //add linked terms if term exist
                    $linked_term_results = $metabox_display_option == 'default' ? taxopress_add_linked_term_options([$term_result => $term->term_id], $term->term_id, $taxonomy) : [$term_result => $term->term_id];
                }

                foreach ($linked_term_results as $linked_term_name => $linked_term_id) {
                    $additional_class = '';
                    $term_post_counts = 0;
                    $term_id = false;
                    $selected_terms = false;

                    if (!empty($linked_term_id)) {
                        $term_id = $linked_term_id;
                        $additional_class = in_array($term_id, $post_terms) ? 'used_term' : '';
                        $selected_terms = in_array($term_id, $post_terms) ? true : false;

                        if (!empty($show_counts)) {
                            $additional_class .= ' countable';
                        }
                        $term_post_counts = self::count_term_posts($term_id, $taxonomy);
                    }

                    $response_content .= '<span class="result-terms ' . esc_attr($additional_class) . '" data-term_link_id="' . esc_attr($term_link_id) . '">';
                    $response_content .= '<span data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" class="term-name ' . esc_attr($taxonomy) . '" tabindex="0" role="button" aria-pressed="false">';
                    $display_name = stripslashes($linked_term_name);
                    if ($show_term_slug && $term_id) {
                        $term_obj = get_term($term_id, $taxonomy);
                        if ($term_obj && !is_wp_error($term_obj) && !empty($term_obj->slug)) {
                            $display_name .= ' (' . $term_obj->slug . ')';
                        }
                    }
                    $response_content .= esc_html($display_name);
                    $response_content .= '</span>';
                    $count_output = '';
                    if (!empty($show_counts) && $term_id) {
                        $response_content .= '<span class="term-counts">';
                        $response_content .= esc_html(number_format_i18n($term_post_counts));
                        $response_content .= '</span>';
                        $count_output = ' (' . number_format_i18n($term_post_counts) . ')';
                    }
                    $response_content .= '</span>';

                    if ($metabox_display_option == 'dropdown') {
                        $option_display_name = stripslashes($linked_term_name);
                        if ($show_term_slug && $term_id) {
                            $term_obj = get_term($term_id, $taxonomy);
                            if ($term_obj && !is_wp_error($term_obj) && !empty($term_obj->slug)) {
                                $option_display_name .= ' (' . $term_obj->slug . ')';
                            }
                        }
                        $additional_html .= '<option value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . selected($selected_terms, true, false) . '>' . esc_html($option_display_name . $count_output) . '</option>';
                    } elseif ($metabox_display_option == 'radio') {
                        $radio_display_name = stripslashes($linked_term_name);
                        if ($show_term_slug && $term_id) {
                            $term_obj = get_term($term_id, $taxonomy);
                            if ($term_obj && !is_wp_error($term_obj) && !empty($term_obj->slug)) {
                                $radio_display_name .= ' (' . $term_obj->slug . ')';
                            }
                        }
                        $additional_html .= '<label><input value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" type="radio" name="auto_term_terms_options[]" class="auto_term_terms_options radio" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . checked($selected_terms, true, false) . '> ' . esc_html($radio_display_name . $count_output) . '</label>';
                    } elseif ($metabox_display_option == 'checkbox') {
                        $checkbox_display_name = stripslashes($linked_term_name);
                        if ($show_term_slug && $term_id) {
                            $term_obj = get_term($term_id, $taxonomy);
                            if ($term_obj && !is_wp_error($term_obj) && !empty($term_obj->slug)) {
                                $checkbox_display_name .= ' (' . $term_obj->slug . ')';
                            }
                        }
                        $additional_html .= '<label><input value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" type="checkbox" name="auto_term_terms_options[]" class="auto_term_terms_options checkbox" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . checked($selected_terms, true, false) . '> ' . esc_html($checkbox_display_name . $count_output) . '</label>';
                    }
                    $term_link_id++;
                }
            }

            if ($metabox_display_option == 'dropdown') {
                $additional_html .= '</select></div>';
            } elseif ($metabox_display_option == 'radio') {
                $additional_html .= '</div>';
            } elseif ($metabox_display_option == 'checkbox') {
                $additional_html .= '</div>';
            }

            $response_content .= '</div>';
            $response_content .= $additional_html;
            $response_content .= '</fieldset>';
            $response_content .= '<div class="preview-action-btn-wrap">';
            $response_content .= '<button class="button button-primary taxopress-ai-addtag-button">
            <div class="spinner"></div>
            ' . sprintf(esc_html__('Update %1s on this %2s', 'simple-tags'), esc_html($taxonomy_details->labels->name), esc_html($post_type_details->labels->singular_name)) . ' 
            </button>';
            $response_content .= '</div>';

            return $response_content;
        }



        /**
         * Format taxonomy term results
         */
        public static function get_term_fieldset_html($args)
        {
            $term_results   = $args['results'];
            $post_id        = $args['post_id'];
            $taxonomy       = $args['preview_taxonomy'];
            $show_counts    = !empty($args['show_counts']);
            $screen_source = !empty($args['screen_source']) ? $args['screen_source'] : 'st_autoterms';

            $post_data          = get_post($post_id);
            $taxonomy_details   = get_taxonomy($taxonomy);
            $post_terms         = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);

            $metabox_display_option = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_data->post_type . '_metabox_display_option');

            if (empty($metabox_display_option) || $screen_source == 'st_autoterms') {
                $metabox_display_option = 'default';
            }


            if ($metabox_display_option == 'default') {
                if (count($post_terms) === count($term_results)) {
                    $modified_legend_title = '<span class="ai-select-all all-selected" data-select-all="' . sprintf(esc_attr__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" data-deselect-all="' . sprintf(esc_attr__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '">' . sprintf(esc_html__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '</span>';
                } else {
                    $modified_legend_title = '<span class="ai-select-all" data-select-all="' . sprintf(esc_attr__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" data-deselect-all="' . sprintf(esc_attr__('Deselect all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '">' . sprintf(esc_html__('Select all %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '</span>';
                }
            } else {
                $modified_legend_title = '<span>' . esc_html($taxonomy_details->labels->name) . '</span>';
            }

            $response_content = '';
            $response_content .= '<fieldset class="previewed-tag-fieldset">';
            $response_content .= '<legend> ' . wp_kses_post($args['legend_title']) . ' (' . $modified_legend_title . ')</legend>';
            $response_content .= '<div class="previewed-tag-content taxopress-autoterm-element ' . esc_attr($metabox_display_option) . '">';

            $additional_html = '';
            if ($metabox_display_option == 'dropdown') {
                $additional_html .= '<div class="auto-terms-options-wrap select"><select name="auto_term_terms_options[]" class="auto_term_terms_options select" data-placeholder="' . sprintf(esc_html__('Select %1s', 'simple-tags'), esc_html($taxonomy_details->labels->name)) . '" style="width: 99%;" multiple>';
            } elseif ($metabox_display_option == 'radio') {
                $additional_html .= '<div class="auto-terms-options-wrap radio">';
            } elseif ($metabox_display_option == 'checkbox') {
                $additional_html .= '<div class="auto-terms-options-wrap checkbox">';
            }

            $term_link_id = 1;
            foreach ($term_results as $term_result) {
                if (!is_string($term_result) || empty(trim($term_result)) || !wp_strip_all_tags($term_result)) {
                    continue;
                }
                $term_result = stripslashes(rtrim($term_result, ','));

                $linked_term_results = [$term_result => 0];
                // Check if the term exists for the given taxonomy
                $term = get_term_by('name', $term_result, $taxonomy);
                if ($term) {
                    //add linked terms if term exist
                    $linked_term_results = $metabox_display_option == 'default' ? taxopress_add_linked_term_options([$term_result => $term->term_id], $term->term_id, $taxonomy) : [$term_result => $term->term_id];
                }
                foreach ($linked_term_results as $linked_term_name => $linked_term_id) {
                    $additional_class = '';
                    $term_post_counts = 0;
                    $term_id = false;
                    $selected_terms = false;
                    if (!empty($linked_term_id)) {
                        $term_id = $linked_term_id;
                        $additional_class = in_array($term_id, $post_terms) ? 'used_term' : '';
                        $selected_terms = in_array($term_id, $post_terms) ? true : false;
                        if (!empty($show_counts)) {
                            $additional_class .= ' countable';
                        }
                        $term_post_counts = self::count_term_posts($term_id, $taxonomy);
                    }

                    $response_content .= '<span class="result-terms ' . esc_attr($additional_class) . '" data-term_link_id="' . esc_attr($term_link_id) . '">';
                    $response_content .= '<span data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" class="term-name ' . esc_attr($taxonomy) . '" tabindex="0" role="button" aria-pressed="false">';
                    $response_content .= esc_html(stripslashes($linked_term_name));
                    $response_content .= '</span>';
                    $count_output = '';
                    if (!empty($show_counts) && $term_id) {
                        $response_content .= '<span class="term-counts">';
                        $response_content .= esc_html(number_format_i18n($term_post_counts));
                        $response_content .= '</span>';
                        $count_output = ' (' . number_format_i18n($term_post_counts) . ')';
                    }
                    $response_content .= '</span>';

                    if ($metabox_display_option == 'dropdown') {
                        $additional_html .= '<option value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . selected($selected_terms, true, false) . '>' . esc_html(stripslashes($linked_term_name) . $count_output) . '</option>';
                    } elseif ($metabox_display_option == 'radio') {
                        $additional_html .= '<label><input value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" type="radio" name="auto_term_terms_options[]" class="auto_term_terms_options radio" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . checked($selected_terms, true, false) . '> ' . esc_html(stripslashes($linked_term_name) . $count_output) . '</label>';
                    } elseif ($metabox_display_option == 'checkbox') {
                        $additional_html .= '<label><input value="' . esc_attr(stripslashes($linked_term_name)) . '" data-term_link_id="' . esc_attr($term_link_id) . '" type="checkbox" name="auto_term_terms_options[]" class="auto_term_terms_options checkbox" data-term_id="' . esc_attr($term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" ' . checked($selected_terms, true, false) . '> ' . esc_html(stripslashes($linked_term_name) . $count_output) . '</label>';
                    }
                    $term_link_id++;
                }
            }
            if ($metabox_display_option == 'dropdown') {
                $additional_html .= '</select></div>';
            } elseif ($metabox_display_option == 'radio') {
                $additional_html .= '</div>';
            } elseif ($metabox_display_option == 'checkbox') {
                $additional_html .= '</div>';
            }

            $response_content .= '</div>';
            $response_content .= $additional_html;
            $response_content .= '</fieldset>';

            return $response_content;
        }
    }
}
