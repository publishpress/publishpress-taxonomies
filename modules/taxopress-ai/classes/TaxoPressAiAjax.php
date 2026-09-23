<?php

if (!class_exists('TaxoPressAiAjax')) {
    class TaxoPressAiAjax
    {
        /**
         * Handle an ajax request to search post
         */
        public static function handle_ai_post_search()
        {
            header('Content-Type: application/javascript');

            if (
                empty($_GET['nonce'])
                || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'taxopress-ai-post-search')
            ) {
                wp_send_json_error(null, 403);
            }

            if (!current_user_can('simple_tags')) {
                wp_send_json_error(null, 403);
            }

            $search = !empty($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
            $post_type = !empty($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : 'any';

            $post_args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => apply_filters('taxopress_filter_posts_search_result_limit', 20),
            ];

            if (!empty($search)) {
                $post_args['s'] = $search;
            }

            $posts = get_posts($post_args);
            $results = [];

            foreach ($posts as $post) {
                $results[] = [
                    'id' => $post->ID,
                    'text' => $post->post_title,
                ];
            }

            $response = [
                'results' => $results,
            ];
            echo wp_json_encode($response);
            exit;
        }

        /**
         * Enforce per-user and site-wide limits for credential-backed AI previews.
         *
         * @return bool True when the request may proceed.
         */
        private static function allow_remote_ai_preview()
        {
            $window = (int) floor(time() / MINUTE_IN_SECONDS);
            $limits = [
                'user_' . get_current_user_id() => max(1, (int) apply_filters('taxopress_ai_preview_user_rate_limit', 10)),
                'site' => max(1, (int) apply_filters('taxopress_ai_preview_site_rate_limit', 60)),
            ];

            foreach ($limits as $scope => $limit) {
                $transient_key = 'taxopress_ai_rate_' . md5($scope . '|' . $window);
                $request_count = (int) get_transient($transient_key);

                if ($request_count >= $limit) {
                    return false;
                }

                set_transient($transient_key, $request_count + 1, 2 * MINUTE_IN_SECONDS);
            }

            return true;
        }

        /**
         * Handle AI preview ajax request.
         */
        public static function handle_taxopress_ai_preview_feature()
        {

            $response['status'] = 'success';
            $response['content'] = esc_html__('Request completed.', 'simple-tags');

            //do not process request if nonce validation failed
            if (
                empty($_POST['nonce'])
                || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'taxopress-ai-ajax-nonce')
            ) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Security error. Kindly reload this page and try again',
                    'simple-tags'
                );
            } elseif (!can_manage_taxopress_metabox()) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Permission error. You do not have permission to manage PublishPress Taxonomies',
                    'simple-tags'
                );
            } else {
                $preview_ai = !empty($_POST['preview_ai']) ? sanitize_text_field(wp_unslash($_POST['preview_ai'])) : '';
                $current_tags = !empty($_POST['current_tags']) ? array_map('sanitize_text_field', wp_unslash($_POST['current_tags'])) : [];
                $preview_taxonomy = !empty($_POST['preview_taxonomy']) ? sanitize_key(wp_unslash($_POST['preview_taxonomy'])) : '';
                $search_text = !empty($_POST['search_text']) ? sanitize_text_field(wp_unslash($_POST['search_text'])) : '';
                $selected_autoterms = !empty($_POST['selected_autoterms']) ? sanitize_text_field(wp_unslash($_POST['selected_autoterms'])) : '';
                $screen_source = !empty($_POST['screen_source']) ? sanitize_text_field(wp_unslash($_POST['screen_source'])) : 'st_autoterms';
                $preview_post = !empty($_POST['preview_post']) ? (int) $_POST['preview_post'] : 0;
                $preview_feature = 'data';
                $post_data = get_post($preview_post);
                if (!is_object($post_data)) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('Posts content and title is empty.', 'simple-tags');
                    wp_send_json($response);
                    exit;
                }

                if (!current_user_can('read_post', $post_data->ID)) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('Permission error. You do not have permission to preview this post.', 'simple-tags');
                    wp_send_json($response);
                    exit;
                }

                $settings_data = TaxoPressAiUtilities::taxopress_get_ai_settings_data($post_data->post_type);
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Using custom taxopress_sanitize_text_field() for sanitization
                $post_content = isset($_POST['post_content']) ? taxopress_sanitize_text_field(wp_unslash($_POST['post_content'])) : $post_data->post_content;
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Using custom taxopress_sanitize_text_field() for sanitization
                $post_title = isset($_POST['post_title']) ? taxopress_sanitize_text_field(wp_unslash($_POST['post_title'])) : $post_data->post_title;
                $term_results = [];

                if ($screen_source == 'st_taxopress_ai') {
                    $post_content = $post_data->post_content;
                    $post_title = $post_data->post_title;
                }

                if ($preview_ai == 'suggest_local_terms') {
                    $preview_ai = 'autoterms';
                }

                $preview_role = isset($_POST['preview_role']) ? sanitize_key($_POST['preview_role']) : '';


                if (!can_manage_taxopress_metabox_taxonomy($preview_taxonomy, false, $preview_role)) {
                    $response['status'] = 'error';
                    $response['content'] = sprintf(esc_html__('You do not have permission to manage this taxonomy. Enable Metabox Access Taxonomies for this role in %1sPublishPress Taxonomies Settings%2s.', 'simple-tags'), '<a target="_blank" href="' . admin_url('admin.php?page=st_options#metabox') . '">', '</a>');
                    wp_send_json($response);
                    exit;
                }


                if ($preview_ai == 'autoterms' && !empty($selected_autoterms)) {
                    $autoterm_data      = taxopress_get_autoterm_data();
                    $settings_data      = array_key_exists($selected_autoterms, $autoterm_data) ? $autoterm_data[$selected_autoterms] : [];
                    $autoterm_use_taxonomy      = !empty($settings_data['autoterm_use_taxonomy']);
                    $autoterm_use_open_ai       = !empty($settings_data['autoterm_use_open_ai']);
                    $autoterm_use_ibm_watson    = !empty($settings_data['autoterm_use_ibm_watson']);
                    $autoterm_use_dandelion     = !empty($settings_data['autoterm_use_dandelion']);
                    $autoterm_use_opencalais    = !empty($settings_data['autoterm_use_opencalais']);

                    $uses_remote_provider = $autoterm_use_open_ai
                        || $autoterm_use_ibm_watson
                        || $autoterm_use_dandelion
                        || $autoterm_use_opencalais;

                    if ($uses_remote_provider && !self::allow_remote_ai_preview()) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('Too many AI preview requests. Please wait a minute and try again.', 'simple-tags');
                        wp_send_json($response, 429);
                        exit;
                    }
                }

                $autoterm_from = isset($settings_data['autoterm_from']) ? $settings_data['autoterm_from'] : 'posts';

                if ($autoterm_from === '') {
                    $post_content = '';
                    $post_title   = '';
                } elseif ($autoterm_from === 'post_title') {
                    $post_content = '';
                } elseif ($autoterm_from === 'post_content') {
                    $post_title = '';
                }

                /**
                 * Filter auto term content
                 *
                 * @param string $content Original content to be analyzed. It could include post title,
                 *  content and/excerpt based on autoterms settings
                 * @param integer $post_id This is the post id
                 * @param array $settings_data Autoterm settings
                 */
                if (!empty($post_content)) {
                    $post_content = apply_filters('taxopress_filter_autoterm_content', $post_content, $post_data->ID, $settings_data);
                } elseif (!empty($post_title)) {
                    $post_title = apply_filters('taxopress_filter_autoterm_content', $post_title, $post_data->ID, $settings_data);
                }

                // TODO: Save last selected settings

                $content = $post_content . ' ' . $post_title;
                $clean_content = TaxoPressAiUtilities::taxopress_clean_up_content($post_content, $post_title);
                $post_id = $post_data->ID;
                $preview_taxonomy_details = get_taxonomy($preview_taxonomy);
                $post_type_details = get_post_type_object($post_data->post_type);
                $args = [
                    'post_id' => $post_id,
                    'settings_data' => $settings_data,
                    'screen_source' => $screen_source,
                    'content' => $content,
                    'taxonomy' => $preview_taxonomy,
                    'clean_content' => $clean_content,
                    'preview_taxonomy' => $preview_taxonomy,
                    'preview_taxonomy_details' => $preview_taxonomy_details,
                    'post_type_details' => $post_type_details,
                    'preview_feature' => $preview_feature,
                    'current_tags' => $current_tags,
                    'selected_autoterms' => $selected_autoterms,
                    'content_source' => $preview_feature . '_post_content_title'

                ];
                if ($preview_ai == 'suggest_local_terms') {
                    $args['suggest_terms'] = true;
                    $args['show_counts'] = isset($settings_data['suggest_local_terms_show_post_count']) ? $settings_data['suggest_local_terms_show_post_count'] : 0;
                    $suggest_local_terms_results = self::get_existing_terms_results($args);
                    if (!empty($suggest_local_terms_results['results'])) {
                        $term_results = $suggest_local_terms_results['results'];
                    }
                    $response['status'] = $suggest_local_terms_results['status'];
                    $response['content'] = $suggest_local_terms_results['message'];
                } elseif ($preview_ai == 'existing_terms') {
                    $args['show_counts'] = isset($settings_data['existing_terms_show_post_count']) ? $settings_data['existing_terms_show_post_count'] : 0;
                    $args['search_text'] = $search_text;

                    if (isset($_POST['existing_terms_order'])) {
                            $args['existing_terms_order'] = sanitize_text_field(wp_unslash($_POST['existing_terms_order']));
                    }
                    if (isset($_POST['existing_terms_orderby'])) {
                        $args['existing_terms_orderby'] = sanitize_text_field(wp_unslash($_POST['existing_terms_orderby']));
                    }
                    if (isset($_POST['existing_terms_maximum_terms'])) {
                        $args['existing_terms_maximum_terms'] = (int)$_POST['existing_terms_maximum_terms'];
                    }

                            $existing_terms_results = self::get_existing_terms_results($args);
                    if (!empty($existing_terms_results['results'])) {
                        $term_results = $existing_terms_results['results'];
                    }
                            $response['status'] = $existing_terms_results['status'];
                            $response['content'] = $existing_terms_results['message'];
                } elseif ($preview_ai == 'post_terms') {
                    $args['show_counts'] = isset($settings_data['post_terms_show_post_count']) ? $settings_data['post_terms_show_post_count'] : 0;
                    $post_terms_results = wp_get_post_terms($post_id, $preview_taxonomy, ['fields' => 'names']);

                    if (!empty($current_tags)) {
                        $current_post_tags = get_terms(
                            array(
                            'taxonomy' => $preview_taxonomy,
                            'include' => $current_tags,
                            'fields' => 'names',
                            'hide_empty' => false
                            )
                        );

                        $post_terms_results = array_unique(array_filter(array_merge($post_terms_results, $current_post_tags)));
                        $post_terms_results = array_values($post_terms_results);
                    }
                    if (!empty($post_terms_results)) {
                        $term_results = $post_terms_results;
                        $response['status'] = 'success';
                        $response['content'] = esc_html__('Term Results', 'simple-tags');
                    } else {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('No results found for this post with this taxonomy.', 'simple-tags');
                    }
                } elseif ($preview_ai == 'autoterms') {
                    if (empty($selected_autoterms) || empty($settings_data)) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('Invalid Auto Term ID. Please save the settings before using preview.', 'simple-tags');
                    } elseif (
                        !$autoterm_use_taxonomy && !$autoterm_use_open_ai && !$autoterm_use_ibm_watson && !$autoterm_use_dandelion && !$autoterm_use_opencalais
                    ) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('You must enable at least one AI integration source to use auto term preview.', 'simple-tags');
                    } elseif (empty($settings_data['autoterm_for_metaboxes'])) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('This Auto Terms option is not enabled for metaboxes. Please enable it in the Auto Terms settings.', 'simple-tags');
                    } else {
                        $request_args  = $args;
                        $request_args['settings_data'] = $settings_data;
                        $request_args['return_tags'] = true;
                        $terms_found = false;

                        $metabox_display_option = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_data->post_type . '_metabox_display_option');

                        if (empty($metabox_display_option) || $screen_source == 'st_autoterms') {
                            $metabox_display_option = 'default';
                        }

                        $term_results = '<div class="preview-action-title taxopress-autoterm-element ' . esc_attr($metabox_display_option) . '"><p class="description">';
                        $term_results .= sprintf(esc_html__('Click %1s to select or deselect them from this %2s.', 'simple-tags'), esc_html($preview_taxonomy_details->labels->name), esc_html($post_type_details->labels->singular_name));
                        $term_results .= '</p></div>';
                        // autoterm_use_taxonomy
                        if ($autoterm_use_taxonomy) {
                            $request_args['suggest_terms'] = true;
                            $request_args['show_counts'] = isset($settings_data['suggest_local_terms_show_post_count']) ? $settings_data['suggest_local_terms_show_post_count'] : 0;
                            $request_args['settings_data'] = $settings_data;

                            $suggest_local_terms_results = TaxoPressAiAjax::get_existing_terms_results($request_args);

                            if (!empty($suggest_local_terms_results['results'])) {
                                $terms_found = true;
                                $request_args['results']        = $suggest_local_terms_results['results'];
                                $request_args['legend_title']   = esc_html__('Suggest Existing Terms', 'simple-tags');
                                $term_results               .= TaxoPressAiUtilities::get_term_fieldset_html($request_args);
                            } else {
                                $term_results .= '<fieldset class="previewed-tag-fieldset"><legend> ' . esc_html__('Suggest Existing Terms', 'simple-tags') . ' </legend><div class="previewed-tag-content"><div class="taxopress-response-css red"><p>' . $suggest_local_terms_results['message'] . '</p></div></div></fieldset>';
                            }
                        }

                        if (taxopress_is_pro_version()) {
                            // autoterm_use_open_ai
                            if ($autoterm_use_open_ai) {
                                    ##https://platform.openai.com/docs/guides/gpt
                                    ##https://platform.openai.com/docs/api-reference/chat/create
                                    ##https://platform.openai.com/docs/models/model-endpoint-compatibility

                                    $request_args['show_counts'] = isset($settings_data['open_ai_show_post_count']) ? $settings_data['open_ai_show_post_count'] : 0;
                                    $open_ai_results = TaxoPressAiApi::get_open_ai_results($request_args);
                                if (!empty($open_ai_results['results'])) {
                                    $terms_found = true;
                                    $request_args['results']        = $open_ai_results['results'];
                                    $request_args['legend_title']   = esc_html__('OpenAI', 'simple-tags');
                                    $term_results               .= TaxoPressAiUtilities::get_term_fieldset_html($request_args);
                                } else {
                                    $term_results .= '<fieldset class="previewed-tag-fieldset"><legend> ' . esc_html__('OpenAI', 'simple-tags') . ' </legend><div class="previewed-tag-content"><div class="taxopress-response-css red"><p>' . $open_ai_results['message'] . '</p></div></div></fieldset>';
                                }
                            }

                                // autoterm_use_ibm_watson
                            if ($autoterm_use_ibm_watson) {
                                ##https://cloud.ibm.com/apidocs/natural-language-understanding
                                $request_args['show_counts'] = isset($settings_data['ibm_watson_show_post_count']) ? $settings_data['ibm_watson_show_post_count'] : 0;
                                $ibm_watson_results = TaxoPressAiApi::get_ibm_watson_results($request_args);
                                if (!empty($ibm_watson_results['results'])) {
                                    $terms_found = true;
                                    $request_args['results']        = $ibm_watson_results['results'];
                                    $request_args['legend_title']   = esc_html__('IBM Watson', 'simple-tags');
                                    $term_results               .= TaxoPressAiUtilities::get_term_fieldset_html($request_args);
                                } else {
                                    $term_results .= '<fieldset class="previewed-tag-fieldset"><legend> ' . esc_html__('IBM Watson', 'simple-tags') . ' </legend><div class="previewed-tag-content"><div class="taxopress-response-css red"><p>' . $ibm_watson_results['message'] . '</p></div></div></fieldset>';
                                }
                            }

                                // autoterm_use_dandelion
                            if ($autoterm_use_dandelion) {
                                ##https://dandelion.eu/docs/api/datatxt/nex/v1/#response
                                $request_args['show_counts'] = isset($settings_data['dandelion_show_post_count']) ? $settings_data['dandelion_show_post_count'] : 0;
                                $dandelion_results = TaxoPressAiApi::get_dandelion_results($request_args);
                                if (!empty($dandelion_results['results'])) {
                                    $terms_found = true;
                                    $request_args['results']        = $dandelion_results['results'];
                                    $request_args['legend_title']   = esc_html__('Dandelion', 'simple-tags');
                                    $term_results               .= TaxoPressAiUtilities::get_term_fieldset_html($request_args);
                                } else {
                                    $term_results .= '<fieldset class="previewed-tag-fieldset"><legend> ' . esc_html__('Dandelion', 'simple-tags') . ' </legend><div class="previewed-tag-content"><div class="taxopress-response-css red"><p>' . $dandelion_results['message'] . '</p></div></div></fieldset>';
                                }
                            }

                                // autoterm_use_opencalais
                            if ($autoterm_use_opencalais) {
                                ## https://developers.lseg.com/en/api-catalog/open-perm-id/intelligent-tagging-restful-api/documentation
                                $request_args['show_counts'] = isset($settings_data['open_calais_show_post_count']) ? $settings_data['open_calais_show_post_count'] : 0;
                                $open_calais_results = TaxoPressAiApi::get_open_calais_results($request_args);
                                if (!empty($open_calais_results['results'])) {
                                    $terms_found = true;
                                    $request_args['results']        = $open_calais_results['results'];
                                    $request_args['legend_title']   = esc_html__('LSEG / Refinitiv', 'simple-tags');
                                    $term_results               .= TaxoPressAiUtilities::get_term_fieldset_html($request_args);
                                } else {
                                    $term_results .= '<fieldset class="previewed-tag-fieldset"><legend> ' . esc_html__('LSEG / Refinitiv', 'simple-tags') . ' </legend><div class="previewed-tag-content"><div class="taxopress-response-css red"><p>' . $open_calais_results['message'] . '</p></div></div></fieldset>';
                                }
                            }
                        }

                        if ($terms_found) {
                            $term_results .= '<div class="preview-action-btn-wrap">';
                            $term_results .= '<button class="button button-primary taxopress-ai-addtag-button">
                            <div class="spinner"></div>
                            ' . sprintf(esc_html__('Update %1s on this %2s', 'simple-tags'), esc_html($preview_taxonomy_details->labels->name), esc_html($post_type_details->labels->singular_name)) . ' 
                            </button>';
                            $term_results .= '</div>';
                        }
                    }
                }

                if (!empty($term_results)) {
                    if (is_array($term_results)) {
                        $term_results = array_unique(array_filter($term_results));
                        $addded_term_results = [];
                        foreach ($term_results as $term_result) {
                            if (!in_array($preview_ai, ['post_terms', 'existing_terms'])) {
                                $term_details = get_term_by('name', $term_result, $preview_taxonomy);
                                if ($term_details) {
                                    $primary_term = $term_result;
                                    $term_id = $term_details->term_id;
                                    $add_terms = [];
                                    $add_terms[$primary_term] = $term_id;

                                    // add term synonyms
                                    $term_synonyms = taxopress_get_term_synonyms($term_id);
                                    if (!empty($term_synonyms)) {
                                        foreach ($term_synonyms as $term_synonym) {
                                            $add_terms[$term_synonym] = $term_id;
                                        }
                                    }

                                    // add linked term
                                    $add_terms = taxopress_add_linked_term_options($add_terms, $term_id, $preview_taxonomy);

                                    // add all of the linked and synonmy terms to the list
                                    foreach ($add_terms as $add_name => $add_term_id) {
                                        if (is_string($add_name) && ! empty($add_name) && !in_array($add_name, $addded_term_results)) {
                                            $addded_term_results[] = $add_name;
                                        }
                                    }
                                } else {
                                    $addded_term_results[] = $term_result;
                                }
                            } else {
                                $addded_term_results[] = $term_result;
                            }
                        }
                        $addded_term_results = array_unique($addded_term_results);
                        $legend_title = '<a href="' . get_edit_post_link($post_id) . '" target="blank">' . $post_data->post_title . ' (' . esc_html__('Edit', 'simple-tags') . ')</a>';
                        $show_term_slug = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_data->post_type . '_metabox_show_term_slug');
                        $args['show_term_slug'] = $show_term_slug;
                        $response_content = TaxoPressAiUtilities::format_taxonomy_term_results($addded_term_results, $preview_taxonomy, $post_id, $legend_title, $args['show_counts'], $current_tags, $args);
                    } else {
                        $response_content = $term_results;
                    }
                    $response['content'] = $response_content;
                }
            }

            wp_send_json($response);
            exit;
        }

        /**
         * Get existing terms
         *
         * @param  array $args
         * @return array
         */
        public static function get_existing_terms_results($args)
        {
            $return['status'] = 'error';
            $return['message'] = esc_html__('Existing Terms not found for the selected post type and taxonomies', 'simple-tags');
            $return['results'] = '';

            $settings_data = $args['settings_data'];
            $content = $args['content'];
            $suggest_terms = !empty($args['suggest_terms']);
            $return_tags = !empty($args['return_tags']);
            $current_tags = !empty($args['current_tags']) ? (array) $args['current_tags'] : [];
            $search_text = !empty($args['search_text']) ? $args['search_text'] : '';
            $post_id = !empty($args['post_id']) ? (int) $args['post_id'] : 0;
            $post_type = get_post_type($post_id);
            $existing_terms_taxonomy = isset($args['preview_taxonomy']) ? $args['preview_taxonomy'] : ['post_tag'];

            $allowed_orderby_keys = array_keys(TaxoPressAiUtilities::get_existing_terms_orderby());
            $allowed_order_keys   = array_keys(TaxoPressAiUtilities::get_existing_terms_order());

            if ($suggest_terms) {
                $existing_terms_maximum_terms = 0;

                $existing_terms_orderby = isset($settings_data['suggest_local_terms_orderby']) ? $settings_data['suggest_local_terms_orderby'] : 'count';
                if (!in_array($existing_terms_orderby, $allowed_orderby_keys, true)) {
                    $existing_terms_orderby = 'count';
                }

                $existing_terms_order = isset($settings_data['suggest_local_terms_order']) ? strtolower($settings_data['suggest_local_terms_order']) : 'desc';
                if (!in_array($existing_terms_order, $allowed_order_keys, true)) {
                    $existing_terms_order = 'desc';
                }

                $existing_terms_show_post_count = isset($settings_data['suggest_local_terms_show_post_count']) ? $settings_data['suggest_local_terms_show_post_count'] : 0;
            } else {
                if (isset($args['existing_terms_order']) && in_array(strtolower($args['existing_terms_order']), $allowed_order_keys, true)) {
                    $existing_terms_order = strtolower($args['existing_terms_order']);
                } else {
                    $default_order = isset($settings_data['existing_terms_order']) ? strtolower($settings_data['existing_terms_order']) : 'desc';
                    $existing_terms_order = in_array($default_order, $allowed_order_keys, true) ? $default_order : 'desc';
                }

                if (isset($args['existing_terms_orderby']) && in_array($args['existing_terms_orderby'], $allowed_orderby_keys, true)) {
                    $existing_terms_orderby = $args['existing_terms_orderby'];
                } else {
                    $default_orderby = isset($settings_data['existing_terms_orderby']) ? $settings_data['existing_terms_orderby'] : 'count';
                    $existing_terms_orderby = in_array($default_orderby, $allowed_orderby_keys, true) ? $default_orderby : 'count';
                }

                if (isset($args['existing_terms_maximum_terms'])) {
                    $existing_terms_maximum_terms = (int)$args['existing_terms_maximum_terms'];
                } else {
                    $existing_terms_maximum_terms = isset($settings_data['existing_terms_maximum_terms']) ? (int) $settings_data['existing_terms_maximum_terms'] : 45;
                }

                $existing_terms_show_post_count = isset($settings_data['existing_terms_show_post_count']) ? $settings_data['existing_terms_show_post_count'] : 0;
            }

            if (!empty($args['show_counts'])) {
                $existing_terms_show_post_count = 1;
            }

            if ($existing_terms_maximum_terms > 0) {
                $limit = (int) $existing_terms_maximum_terms;
            } else {
                $limit = 0;
            }

            if (empty($existing_terms_taxonomy)) {
                $return['status'] = 'error';
                $return['message'] = esc_html__(
                    'Existing Terms taxonomy is required. Kindly select taxonomies and save the settings before running preview.',
                    'simple-tags'
                );
            } else {
                $post_type_taxonomies = get_object_taxonomies($post_type);

                $supported_tax = false;
                $response_content = '';
                foreach ([$existing_terms_taxonomy] as $existing_tax) {
                    if (in_array($existing_tax, $post_type_taxonomies)) {
                        $supported_tax = true;
                        $taxonomy_details = get_taxonomy($existing_tax);

                        $terms = SimpleTags_Admin::getTermsForAjax($existing_tax, $search_text, $existing_terms_orderby, $existing_terms_order, $limit);
                        $show_term_slug = SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_metabox_show_term_slug');
                        // make sure post terms are always included
                        if (!$suggest_terms) {
                            $post_terms = wp_get_post_terms($post_id, $existing_tax);
                            $structured_post_terms = [];
                            if (!empty($post_terms)) {
                                // Transform post_terms to match terms structure
                                $structured_post_terms = array_map(function ($term) {
                                    return (object) [
                                        'name' => $term->name,
                                        'slug' => $term->slug,
                                        'term_id' => $term->term_id,
                                        'taxonomy' => $term->taxonomy
                                    ];
                                }, $post_terms);
                                // add structured post terms
                                $terms = array_merge($structured_post_terms, $terms);
                            }

                            $unique_terms = [];
                            foreach ((array) $terms as $term) {
                                $term_id = isset($term->term_id) ? (int) $term->term_id : 0;
                                if ($term_id && !isset($unique_terms[$term_id])) {
                                    $unique_terms[$term_id] = $term;
                                }
                            }

                            if ($limit > 0) {
                                $display_limit = max($limit, count($structured_post_terms));
                                $unique_terms = array_slice($unique_terms, 0, $display_limit, true);
                            }

                            $terms = array_values($unique_terms);
                        }

                        // Apply Auto Terms settings consistently for "Suggest Existing Terms"
                        if ($suggest_terms && !empty($settings_data)) {
                            if (array_key_exists('autoterm_from', $settings_data)) {
                                // Respect explicit empty string '' as "Don't scan Post Content or Title"
                                $autoterm_from = $settings_data['autoterm_from'];
                            } else {
                                // Only default to posts when the setting truly does not exist
                                $autoterm_from = 'posts';
                            }

                            $post_title_raw   = get_the_title($post_id);
                            $post_content_raw = get_post_field('post_content', $post_id);

                            if ($autoterm_from === '') {
                                $base_content = '';
                            } elseif ($autoterm_from === 'post_title') {
                                $base_content = (string) $post_title_raw;
                            } elseif ($autoterm_from === 'post_content') {
                                $base_content = (string) $post_content_raw;
                            } elseif ($autoterm_from === 'posts') {
                                $base_content = (string) $post_title_raw . ' ' . (string) $post_content_raw;
                            } else {
                                $base_content = (string) $content;
                            }

                            $scan_content = apply_filters(
                                'taxopress_filter_autoterm_content',
                                (string) $base_content,
                                $post_id,
                                $settings_data
                            );
                            $scan_content = trim($scan_content);

                            if ($scan_content === '') {
                                $return['status']  = 'error';
                                $return['message'] = esc_html__(
                                    'Posts content and title is empty.',
                                    'simple-tags'
                                );
                                return $return;
                            }

                            $autoterm_word       = !empty($settings_data['autoterm_word']) ? (int) $settings_data['autoterm_word'] : 0;
                            $autoterm_hash       = !empty($settings_data['autoterm_hash']) ? (int) $settings_data['autoterm_hash'] : 0;
                            $autoterm_regex_code = !empty($settings_data['autoterm_use_regex']) && !empty($settings_data['terms_regex_code']) ? stripslashes($settings_data['terms_regex_code']) : '';

                            $autoterm_exclude = !empty($settings_data['autoterm_exclude']) ? (array) $settings_data['autoterm_exclude'] : [];
                            $autoterm_useonly = !empty($settings_data['autoterm_useonly']) && !empty($settings_data['specific_terms']);
                            $specific_terms   = $autoterm_useonly ? (array) $settings_data['specific_terms'] : [];

                            $terms = array_filter((array) $terms, function ($term_obj) use ($autoterm_exclude, $autoterm_useonly, $specific_terms) {
                                $name = stripslashes($term_obj->name);
                                if (!empty($autoterm_exclude) && in_array($name, $autoterm_exclude, true)) {
                                    return false;
                                }
                                if ($autoterm_useonly && !in_array($name, $specific_terms, true)) {
                                    return false;
                                }
                                return true;
                            });

                            $filtered_terms = [];
                            foreach ($terms as $term_obj) {
                                $primary_name = stripslashes($term_obj->name);
                                $term_id      = (int) $term_obj->term_id;

                                $check_terms = [$primary_name => $term_id];

                                if (function_exists('taxopress_get_term_synonyms')) {
                                    $syns = taxopress_get_term_synonyms($term_id);
                                    if (!empty($syns)) {
                                        foreach ($syns as $syn) {
                                            $check_terms[$syn] = $term_id;
                                        }
                                    }
                                }

                                if (function_exists('taxopress_add_linked_term_options')) {
                                    $check_terms = taxopress_add_linked_term_options($check_terms, $term_id, $existing_tax, false, true);
                                }

                                $term_matches = false;
                                foreach ($check_terms as $check_term => $original_id) {
                                    $check = (string) $check_term;

                                    if (!empty($autoterm_regex_code)) {
                                        $regex = str_replace('{term}', preg_quote($check, '/'), $autoterm_regex_code);
                                        if (@preg_match($regex, '') !== false && preg_match($regex, $scan_content)) {
                                            $term_matches = true;
                                            break;
                                        }
                                    } elseif ($autoterm_word === 1) {
                                        if (preg_match('/\b' . preg_quote($check, '/') . '\b/i', $scan_content)) {
                                            $term_matches = true;
                                            break;
                                        }
                                        if ($autoterm_hash === 1) {
                                            if (stripos($scan_content, '#' . $check) !== false) {
                                                $term_matches = true;
                                                break;
                                            }
                                            if (substr($check, 0, 1) === '#') {
                                                $trim = ltrim($check, '#');
                                                if (preg_match('/\B#+' . preg_quote($trim, '/') . '\b/i', $scan_content)) {
                                                    $term_matches = true;
                                                    break;
                                                }
                                            }
                                        }
                                    } else {
                                        if (stripos($scan_content, $check) !== false) {
                                            $term_matches = true;
                                            break;
                                        }
                                        if ($autoterm_hash === 1 && stripos($scan_content, '#' . $check) !== false) {
                                            $term_matches = true;
                                            break;
                                        }
                                    }
                                }

                                if ($term_matches) {
                                    $filtered_terms[] = $term_obj;
                                }
                            }

                            $terms   = $filtered_terms;
                            $content = $scan_content;
                        }

                        if (!empty($terms)) {
                            if ($suggest_terms) {
                                $term_results = [];
                                foreach ($terms as $term) {
                                    $term_id = $term->term_id;
                                    $term = stripslashes($term->name);
                                    $add_terms = [];
                                    $add_terms[$term] = $term_id;
                                    $primary_term = $term;
                                    $term_check_names = [];
                                    // add term synonyms
                                    $term_synonyms = taxopress_get_term_synonyms($term_id);
                                    if (!empty($term_synonyms)) {
                                        foreach ($term_synonyms as $term_synonym) {
                                            $term_check_names[$term_synonym] = $primary_term;
                                            $add_terms[$term_synonym] = $term_id;
                                        }
                                    }

                                    // add linked term
                                    $add_terms = taxopress_add_linked_term_options($add_terms, $term_id, $existing_tax);
                                    foreach ($add_terms as $add_name => $add_term_id) {
                                        $new_term = (isset($term_check_names[$add_name])) ? $term_check_names[$add_name] : $add_name;

                                        if (is_string($new_term) && ! empty($new_term) && stristr($content, $add_name) && !in_array($new_term, $term_results)) {
                                            $term_results[] = $new_term;
                                        }
                                    }
                                }
                            } else {
                                $term_results = array_unique(array_column((array) $terms, 'name'));
                            }


                            $taxonomy_list_page = admin_url('edit-tags.php');
                            $taxonomy_list_page = add_query_arg(
                                array(
                                    'taxonomy' => $existing_tax,
                                    'post_type' => $post_type,
                                ),
                                $taxonomy_list_page
                            );

                            $legend_title = '<a href="' . esc_url($taxonomy_list_page) . '" target="blank">' . $taxonomy_details->labels->name . '</a>';
                            if ($return_tags) {
                                $response_content = $term_results;
                            } else {
                                $args['show_term_slug'] = $show_term_slug;
                                $response_content = TaxoPressAiUtilities::format_taxonomy_term_results($term_results, $existing_tax, $post_id, $legend_title, $existing_terms_show_post_count, $current_tags, $args);
                            }
                        }
                    }
                }

                if (!empty($response_content)) {
                    $return['status'] = 'success';
                    $return['results'] = $response_content;
                    $return['message'] = esc_html__(
                        'Result from ajax request.',
                        'simple-tags'
                    );
                } elseif (!$supported_tax) {
                    $return['status'] = 'error';
                    $return['message'] = esc_html__(
                        'The selected taxonomy is not associated with the selected post type.',
                        'simple-tags'
                    );
                } else {
                    $return['status'] = 'error';
                    $return['message'] = esc_html__(
                        'No results found for this taxonomy.',
                        'simple-tags'
                    );
                }
            }

            return $return;
        }

        /**
         * Handle AI post terms update.
         */
        public static function handle_taxopress_ai_post_term()
        {

            $response['status'] = 'success';
            $response['content'] = esc_html__('Request completed.', 'simple-tags');

            //do not process request if nonce validation failed
            if (
                empty($_POST['nonce'])
                || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'taxopress-ai-ajax-nonce')
            ) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Security error. Kindly reload this page and try again',
                    'simple-tags'
                );
            } elseif (!can_manage_taxopress_metabox()) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Permission error. You do not have permission to manage PublishPress Taxonomies',
                    'simple-tags'
                );
            } else {
                $taxonomy = !empty($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
                $post_type_label = !empty($_POST['post_type_label']) ? sanitize_text_field(wp_unslash($_POST['post_type_label'])) : esc_html__('Post', 'simple-tags');
                $post_id = !empty($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
                $added_tags = !empty($_POST['added_tags']) ? map_deep(wp_unslash($_POST['added_tags']), 'sanitize_text_field') : [];
                $removed_tags = !empty($_POST['removed_tags']) ? map_deep(wp_unslash($_POST['removed_tags']), 'sanitize_text_field') : [];

                if (!can_manage_taxopress_metabox_taxonomy($taxonomy)) {
                    $response['status'] = 'error';
                    $response['content'] = sprintf(esc_html__('You do not have permission to manage this taxonomy. Enable Metabox Access Taxonomies for this role in %1sPublishPress Taxonomies Settings%2s.', 'simple-tags'), '<a target="_blank" href="' . admin_url('admin.php?page=st_options#metabox') . '">', '</a>');
                    wp_send_json($response);
                    exit;
                }
                if (!$post_id && !empty($_POST['tab_post_id'])) {
                    $post_id = (int) $_POST['tab_post_id'];
                }

                if (!empty($post_id)) {
                    $post_type = get_post_type($post_id);
                    $post_type_details  = get_post_type_object($post_type);
                    $post_type_label = $post_type_details->labels->singular_name;
                }

                if (!current_user_can('edit_post', $post_id)) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('You do not have permission to edit this post.', 'simple-tags');
                    wp_send_json($response);
                    exit;
                }

                $taxonomy_object = get_taxonomy($taxonomy);
                if (
                    !$taxonomy_object
                    || empty($taxonomy_object->cap->assign_terms)
                    || !current_user_can($taxonomy_object->cap->assign_terms)
                    || !is_object_in_taxonomy(get_post_type($post_id), $taxonomy)
                ) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('You do not have permission to assign terms from this taxonomy.', 'simple-tags');
                    wp_send_json($response, 403);
                    exit;
                }

                if (empty($added_tags) && empty($removed_tags)) {
                    $response['status'] = 'error';
                    $response['content'] = sprintf(esc_html__('Click Term to select or deselect from this %1s', 'simple-tags'), esc_html($post_type_label));
                } elseif (empty($taxonomy) || empty($post_id)) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('Both Taxonomy and Post are required.', 'simple-tags');
                } else {
                    $post_terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);

                    $removed_terms_name = [];
                    $added_terms_name = [];
                    $removed_terms_id = [];
                    $added_terms_id = [];
                    // Remove de-selected terms
                    if (!empty($removed_tags)) {
                        foreach ($removed_tags as $removed_tag) {
                            $term_id = (int) $removed_tag['term_id'];
                            if (in_array($term_id, $post_terms, true)) {
                                $remove = wp_remove_object_terms($post_id, $term_id, $taxonomy);
                                if ($remove) {
                                    $removed_terms_name[] = $removed_tag['name'];
                                    $removed_terms_id[] = $term_id;
                                    clean_term_cache($term_id, $taxonomy);
                                }
                            }
                        }
                        clean_object_term_cache($post_id, $taxonomy);
                    }
                    // Add selected terms
                    if (!empty($added_tags)) {
                        foreach ($added_tags as $added_tag) {
                            $term_id = (int) $added_tag['term_id'];
                            if ($term_id === 0) {
                                if (
                                    empty($taxonomy_object->cap->edit_terms)
                                    || !current_user_can($taxonomy_object->cap->edit_terms)
                                ) {
                                    continue;
                                }

                                $inserted_term = wp_insert_term($added_tag['name'], $taxonomy);
                                if (is_wp_error($inserted_term)) {
                                    continue;
                                }
                                $term_id = (int) $inserted_term['term_id'];
                            } elseif (!term_exists($term_id, $taxonomy)) {
                                continue;
                            }
                            if (!in_array($term_id, $post_terms, true)) {
                                $add = wp_set_object_terms($post_id, $term_id, $taxonomy, true);
                                if ($add) {
                                    $added_terms_name[] = $added_tag['name'];
                                    $added_terms_id[] = $term_id;
                                    clean_term_cache($term_id, $taxonomy);
                                }
                            }
                        }
                        clean_object_term_cache($post_id, $taxonomy);
                    }

                    $additional_message = '';
                    $response['status'] = 'success';
                    $response['removed_terms_id'] = $removed_terms_id;
                    $response['added_terms_id'] = $added_terms_id;
                    if (empty($added_terms_name) && empty($removed_terms_name)) {
                        $response['status'] = 'error';
                        $additional_message = esc_html__('No new terms were selected or deselected.', 'simple-tags');
                    }

                    if (!empty($added_terms_name)) {
                        $escaped_added_terms = array_map('esc_html', $added_terms_name);
                        $additional_message .= ' ' . sprintf(esc_html__('%1s terms added to this %2s.', 'simple-tags'), '<strong>' . implode(', ', $escaped_added_terms) . '</strong>', esc_html($post_type_label));
                    }

                    if (!empty($removed_terms_name)) {
                        $escaped_removed_terms = array_map('esc_html', $removed_terms_name);
                        $additional_message .= ' ' . sprintf(esc_html__('%1s terms removed from this %2s.', 'simple-tags'), '<strong>' . implode(', ', $escaped_removed_terms) . '</strong>', esc_html($post_type_label));
                    }

                    $response['content'] = wp_kses($additional_message, ['strong' => []]);
                }
            }

            wp_send_json($response);
            exit;
        }

        /**
         * Handle AI new term
         */
        public static function handle_taxopress_ai_new_term()
        {

            $response['status'] = 'error';
            $response['content'] = esc_html__('An error occured.', 'simple-tags');

            //do not process request if nonce validation failed
            if (
                empty($_POST['nonce'])
                || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'taxopress-ai-ajax-nonce')
            ) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Security error. Kindly reload this page and try again',
                    'simple-tags'
                );
            } elseif (!can_manage_taxopress_metabox()) {
                $response['status'] = 'error';
                $response['content'] = esc_html__(
                    'Permission error. You do not have permission to manage PublishPress Taxonomies',
                    'simple-tags'
                );
            } else {
                $taxonomy = !empty($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
                $term_name = !empty($_POST['term_name']) ? sanitize_text_field(wp_unslash($_POST['term_name'])) : '';
                $screen_source = !empty($_POST['screen_source']) ? sanitize_text_field(wp_unslash($_POST['screen_source'])) : '';
                $existing_terms = !empty($_POST['existing_terms']) ? map_deep(wp_unslash($_POST['existing_terms']), 'sanitize_text_field') : [];
                $selected_terms = !empty($_POST['selected_terms']) ? map_deep($_POST['selected_terms'], 'intval') : [];
                $post_id = !empty($_POST['post_id']) ? intval($_POST['post_id']) : 0;

                if (!can_manage_taxopress_metabox_taxonomy($taxonomy)) {
                    $response['status'] = 'error';
                    $response['content'] = sprintf(esc_html__('You do not have permission to manage this taxonomy. Enable Metabox Access Taxonomies for this role in %1sPublishPress Taxonomies Settings%2s.', 'simple-tags'), '<a target="_blank" href="' . admin_url('admin.php?page=st_options#metabox') . '">', '</a>');
                    wp_send_json($response);
                    exit;
                }

                $taxonomy_data = get_taxonomy($taxonomy);
                $can_manage_term = $taxonomy_data
                    && !empty($taxonomy_data->cap->edit_terms)
                    && current_user_can($taxonomy_data->cap->edit_terms);

                if (!$can_manage_term) {
                    $response['status'] = 'error';
                    $response['content'] = esc_html__('You do not have capability to manage this taxonomy.', 'simple-tags');
                    wp_send_json($response);
                    exit;
                }

                if (!empty($post_id)) {
                    if (
                        !current_user_can('edit_post', $post_id)
                        || !is_object_in_taxonomy(get_post_type($post_id), $taxonomy)
                    ) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('You do not have permission to edit this post with this taxonomy.', 'simple-tags');
                        wp_send_json($response, 403);
                        exit;
                    }

                    if (
                        $screen_source === 'st_taxopress_ai'
                        && (
                            empty($taxonomy_data->cap->assign_terms)
                            || !current_user_can($taxonomy_data->cap->assign_terms)
                        )
                    ) {
                        $response['status'] = 'error';
                        $response['content'] = esc_html__('You do not have permission to assign terms from this taxonomy.', 'simple-tags');
                        wp_send_json($response, 403);
                        exit;
                    }
                }

                $validated_term = apply_filters('taxopress_validate_term_before_insert', $term_name, $taxonomy);
                if (is_wp_error($validated_term)) {
                    $response['status'] = 'error';
                    $response['content'] = $validated_term->get_error_message();
                    wp_send_json($response);
                    exit;
                }


                $term_id   = 0;
                $term_data = false;
                $term_exits = 0;

                $result_term = term_exists($term_name, $taxonomy);
                if (empty($result_term)) {
                    $result_term = wp_insert_term(
                        $term_name,
                        $taxonomy
                    );

                    if (!is_wp_error($result_term)) {
                        $term_id = (int) $result_term['term_id'];
                    }
                } else {
                    $term_id = (int) $result_term['term_id'];
                    $term_exits = 1;
                }

                $term_html = '';
                if ($term_id > 0) {
                    $term = get_term($term_id, $taxonomy);
                    $term_data = [
                        'term_id' => $term_id,
                        'name' => $term->name,
                        'term_exits' => $term_exits
                    ];
                    $existing_terms[] = $term->name;
                    $existing_terms = array_filter($existing_terms);
                    if (!empty($post_id)) {
                        $term_html = TaxoPressAiUtilities::format_taxonomy_term_results($existing_terms, $taxonomy, $post_id, '', false, $selected_terms, ['screen_source' => 'post.php']);

                        if ($screen_source == 'st_taxopress_ai') {
                            wp_set_object_terms($post_id, $term->slug, $taxonomy, true);
                            clean_term_cache($term_id, $taxonomy);
                        }
                    }
                }
                $current_term = $term_id;

                $response['status'] = 'success';
                $response['content'] = esc_html__('Request completed.', 'simple-tags');
                $response['term'] = $term_data;
                $response['term_html'] = $term_html;
                $response['current_term'] = $current_term;
            }
            wp_send_json($response);
            exit;
        }

        public static function taxopress_validate_term_before_insert($term, $taxonomy)
        {
            $post_id   = 0;
            $post_type = '';

            // Check both possible keys for post ID
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in calling function handle_taxopress_ai_new_term()
            if (!empty($_POST['post_ID'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $post_id = intval($_POST['post_ID']);
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } elseif (!empty($_POST['post_id'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $post_id = intval($_POST['post_id']);
            }

            if ($post_id) {
                $post_type = get_post_type($post_id);
            }

            $post_type = $post_type ?: 'post';

            $option_name    = 'taxopress_ai_' . $post_type . '_exclusions';
            $excluded_chars = SimpleTags_Plugin::get_option_value($option_name);

            if (!empty($excluded_chars)) {
                $excluded_chars = array_filter(str_split(preg_replace('/\s+/', '', $excluded_chars)));

                if (!empty($excluded_chars)) {
                    $exact_chars = array_filter($excluded_chars, function ($char) use ($term) {
                        return strpos($term, $char) !== false;
                    });

                    if (!empty($exact_chars)) {
                        return new WP_Error(
                            'invalid_character',
                            sprintf(__('Terms cannot contain the following characters: %s', 'simple-tags'), implode(' ', $exact_chars))
                        );
                    }
                }
            }

            // Retrieve minimum and maximum term length settings
            $min_length = (int) SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_minimum_term_length');
            $max_length = (int) SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post_type . '_maximum_term_length');

            // Validate term length
            $term_length = mb_strlen($term);
            if ($min_length > 0 && $term_length < $min_length) {
                return new WP_Error(
                    'term_too_short',
                    sprintf(__('Terms must be at least %d characters long.', 'simple-tags'), $min_length)
                );
            }
            if ($max_length > 0 && $term_length > $max_length) {
                return new WP_Error(
                    'term_too_long',
                    sprintf(__('Terms cannot exceed %d characters.', 'simple-tags'), $max_length)
                );
            }

            return $term;
        }

        public static function handle_role_preview()
        {
            if (!current_user_can('simple_tags')) {
                wp_send_json_error(null, 403);
            }

            check_ajax_referer('taxopress-ai-ajax-nonce', 'nonce');

            $preview_role = isset($_POST['preview_role']) ? sanitize_key($_POST['preview_role']) : '';
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';

            $role_taxonomies = (array) SimpleTags_Plugin::get_option_value('enable_metabox_' . $preview_role . '');
            $has_metabox_access = !empty(SimpleTags_Plugin::get_option_value('enable_' . $preview_role . '_metabox'));
            if (!$has_metabox_access) {
                $role_taxonomies = [];
            }

            $can_create_terms = !SimpleTags_Plugin::get_option_value('enable_restrict' . $preview_role . '_metabox');

            // Check if user is administrator - only they can edit labels
            $can_edit_labels = $preview_role === 'administrator';

            $show_taxonomy = in_array($taxonomy, $role_taxonomies, true);

            $available_taxonomies = [];
            foreach (TaxoPressAiUtilities::get_taxonomies(true) as $tax_name => $tax_data) {
                if (!in_array($tax_name, ['post_format'], true) && in_array($tax_name, $role_taxonomies, true)) {
                    $available_taxonomies[] = $tax_name;
                }
            }

            wp_send_json_success([
                'show_taxonomy' => $show_taxonomy,
                'can_create_terms' => $can_create_terms,
                'can_edit_labels' => $can_edit_labels,
                'allowed_taxonomies' => $available_taxonomies
            ]);
        }

        public static function handle_preview_update()
        {
            if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'taxopress-ai-ajax-nonce')) {
                wp_send_json_error(['message' => 'Invalid nonce'], 403);
                exit;
            }

            if (!current_user_can('simple_tags')) {
                wp_send_json_error(['message' => 'Permission denied'], 403);
                exit;
            }

            $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

            if (!$post_id) {
                wp_send_json_error(['message' => 'Invalid post ID'], 400);
                exit;
            }

            // Get the post
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(['message' => 'Post not found'], 404);
                exit;
            }

            if (!current_user_can('read_post', $post_id)) {
                wp_send_json_error(['message' => 'Permission denied'], 403);
                exit;
            }

            ob_start();
            TaxoPress_AI_Module::get_instance()->editor_metabox($post, 'fast_update');
            $metabox_content = ob_get_clean();

            wp_send_json_success([
                'metabox_content' => $metabox_content,
                'metabox_filters_enabled' => !empty(SimpleTags_Plugin::get_option_value('taxopress_ai_' . $post->post_type . '_metabox_filters')),
            ]);
            exit;
        }
    }
}
