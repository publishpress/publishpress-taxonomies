<?php

defined('ABSPATH') || exit;

if (!class_exists('TaxoPressAiTermDescription')) {
    class TaxoPressAiTermDescription
    {
        public static function get_sources()
        {
            $sources = [
                [
                    'id'    => 'local',
                    'label' => esc_html__('Local content (no external provider)', 'simple-tags'),
                ],
            ];

            foreach (TaxoPressAiApi::get_wordpress_ai_providers() as $provider_id => $provider_name) {
                $sources[] = [
                    'id'    => 'wordpress_ai:' . sanitize_key($provider_id),
                    'label' => sprintf(
                        /* translators: %s: configured WordPress AI Connector name. */
                        esc_html__('AI Connector: %s', 'simple-tags'),
                        sanitize_text_field($provider_name)
                    ),
                ];
            }

            $settings = TaxoPressAiUtilities::taxopress_get_ai_settings_data();
            if (
                !empty(SimpleTags_Plugin::get_option_value('enable_openai_ai_source'))
                && !empty($settings['open_ai_api_key'])
            ) {
                $sources[] = [
                    'id'    => 'openai',
                    'label' => esc_html__('OpenAI (configured legacy source)', 'simple-tags'),
                ];
            }

            return apply_filters('taxopress_term_description_sources', $sources);
        }

        public static function handle_suggestion()
        {
            check_ajax_referer('taxopress-ai-ajax-nonce', 'nonce');

            $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
            $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
            $term_name = isset($_POST['term_name']) ? sanitize_text_field(wp_unslash($_POST['term_name'])) : '';
            $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
            $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'local';
            $user_context = isset($_POST['context']) ? sanitize_textarea_field(wp_unslash($_POST['context'])) : '';
            $taxonomy_object = get_taxonomy($taxonomy);

            if (!$taxonomy_object) {
                wp_send_json_error(['message' => esc_html__('The selected taxonomy is invalid.', 'simple-tags')], 400);
            }

            $term = null;
            if ($term_id > 0) {
                $term = get_term($term_id, $taxonomy);
                if (!($term instanceof WP_Term)) {
                    wp_send_json_error(['message' => esc_html__('The selected term could not be found.', 'simple-tags')], 404);
                }
                if (!current_user_can('edit_term', $term_id)) {
                    wp_send_json_error(['message' => esc_html__('You do not have permission to edit this term.', 'simple-tags')], 403);
                }
                $term_name = self::decoded_term_name($term);
                $parent_id = (int) $term->parent;
            } elseif (!current_user_can($taxonomy_object->cap->edit_terms)) {
                wp_send_json_error(['message' => esc_html__('You do not have permission to create terms in this taxonomy.', 'simple-tags')], 403);
            }

            if ($term_name === '') {
                wp_send_json_error(['message' => esc_html__('Enter a term name before requesting a description.', 'simple-tags')], 400);
            }

            $available_sources = wp_list_pluck(self::get_sources(), 'label', 'id');
            if (!isset($available_sources[$source])) {
                wp_send_json_error(['message' => esc_html__('The selected description source is not available.', 'simple-tags')], 400);
            }

            if ($source !== 'local' && !self::allow_remote_generation()) {
                wp_send_json_error(['message' => esc_html__('Too many description requests were made. Please wait a minute and try again.', 'simple-tags')], 429);
            }

            $context = self::build_context($term_id, $taxonomy, $term_name, $parent_id, $user_context);
            if ($source === 'local') {
                $description = self::generate_local_description($context);
            } elseif ($source === 'openai') {
                $description = self::generate_openai_description($context);
            } elseif (strpos($source, 'wordpress_ai:') === 0) {
                $description = self::generate_connector_description(substr($source, 13), $context);
            } else {
                $description = new WP_Error('invalid_source', esc_html__('The selected description source is not supported.', 'simple-tags'));
            }

            if (is_wp_error($description)) {
                wp_send_json_error(['message' => $description->get_error_message()], 400);
            }

            $description = self::sanitize_description($description);
            if ($description === '') {
                wp_send_json_error(['message' => esc_html__('No description could be suggested from the available context.', 'simple-tags')], 400);
            }

            $current_description = $term instanceof WP_Term ? (string) $term->description : '';
            wp_send_json_success([
                'description'     => $description,
                'source'          => $source,
                'source_label'    => $available_sources[$source],
                'context_status'  => $context['status'],
                'context_message' => self::get_context_message($context['status']),
                'original_hash'   => hash('sha256', $current_description),
            ]);
        }

        /** Save a reviewed suggestion without overwriting a concurrent edit. */
        public static function handle_apply()
        {
            check_ajax_referer('taxopress-ai-ajax-nonce', 'nonce');

            $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
            $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
            $description = isset($_POST['description']) ? self::sanitize_description(wp_unslash($_POST['description'])) : '';
            $original_hash = isset($_POST['original_hash']) ? sanitize_text_field(wp_unslash($_POST['original_hash'])) : '';
            $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'local';
            $term = get_term($term_id, $taxonomy);

            if (!($term instanceof WP_Term)) {
                wp_send_json_error(['message' => esc_html__('The selected term could not be found.', 'simple-tags')], 404);
            }
            if (!current_user_can('edit_term', $term_id)) {
                wp_send_json_error(['message' => esc_html__('You do not have permission to edit this term.', 'simple-tags')], 403);
            }
            if ($description === '') {
                wp_send_json_error(['message' => esc_html__('The description cannot be empty.', 'simple-tags')], 400);
            }
            if ($original_hash === '' || !hash_equals(hash('sha256', (string) $term->description), $original_hash)) {
                wp_send_json_error([
                    'message' => esc_html__('This description changed after the suggestion was generated. Reload the term before applying it.', 'simple-tags'),
                ], 409);
            }

            $updated = wp_update_term($term_id, $taxonomy, ['description' => $description]);
            if (is_wp_error($updated)) {
                wp_send_json_error(['message' => $updated->get_error_message()], 400);
            }

            update_term_meta($term_id, '_taxopress_description_source', $source);
            update_term_meta($term_id, '_taxopress_description_generated_at', time());
            update_term_meta($term_id, '_taxopress_description_saved_hash', hash('sha256', $description));

            wp_send_json_success([
                'message'              => esc_html__('The term description was updated.', 'simple-tags'),
                'description'          => $description,
                'rendered_description' => term_description($term_id),
            ]);
        }

        /** Build bounded, published-content context for either a saved or unsaved term. */
        private static function build_context($term_id, $taxonomy, $term_name, $parent_id, $user_context)
        {
            $taxonomy_object = get_taxonomy($taxonomy);
            $parent_name = '';
            if ($parent_id > 0) {
                $parent = get_term($parent_id, $taxonomy);
                if ($parent instanceof WP_Term) {
                    $parent_name = self::decoded_term_name($parent);
                }
            }

            $posts = [];
            if ($term_id > 0) {
                $object_ids = get_objects_in_term($term_id, $taxonomy, ['fields' => 'ids']);
                if (!is_wp_error($object_ids) && !empty($object_ids)) {
                    $post_objects = get_posts([
                        'post__in'            => array_map('intval', array_slice($object_ids, 0, 40)),
                        'post_type'           => 'any',
                        'post_status'         => 'publish',
                        'posts_per_page'      => 6,
                        'orderby'             => 'date',
                        'order'               => 'DESC',
                        'ignore_sticky_posts' => true,
                    ]);

                    foreach ($post_objects as $post) {
                        $excerpt = has_excerpt($post)
                            ? $post->post_excerpt
                            : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45, '…');
                        $posts[] = [
                            'title'   => sanitize_text_field(get_the_title($post)),
                            'excerpt' => sanitize_textarea_field($excerpt),
                        ];
                    }
                }
            }

            $status = !empty($posts) ? 'content_based' : 'term_only';
            if ($user_context !== '') {
                $status = 'user_context';
            }

            return apply_filters('taxopress_term_description_context', [
                'term_id'        => (int) $term_id,
                'term_name'      => $term_name,
                'taxonomy'       => $taxonomy,
                'taxonomy_label' => $taxonomy_object && !empty($taxonomy_object->labels->singular_name)
                    ? $taxonomy_object->labels->singular_name
                    : $taxonomy,
                'parent_name'    => $parent_name,
                'site_name'      => get_bloginfo('name'),
                'locale'         => get_locale(),
                'user_context'   => $user_context,
                'posts'          => $posts,
                'status'         => $status,
            ], $term_id, $taxonomy);
        }

        private static function generate_local_description($context)
        {
            $term_name = $context['term_name'];
            $candidate_texts = [];
            if ($context['user_context'] !== '') {
                $candidate_texts[] = $context['user_context'];
            }
            foreach ($context['posts'] as $post) {
                if ($post['excerpt'] !== '') {
                    $candidate_texts[] = $post['excerpt'];
                }
            }

            $best_sentence = '';
            $best_score = 0;
            foreach ($candidate_texts as $candidate_text) {
                $sentences = preg_split('/(?<=[.!?])\s+/u', trim($candidate_text));
                foreach ((array) $sentences as $sentence) {
                    $sentence = self::sanitize_description($sentence);
                    $length = function_exists('mb_strlen') ? mb_strlen($sentence) : strlen($sentence);
                    if ($length < 35 || $length > 360) {
                        continue;
                    }

                    $score = 1;
                    if (function_exists('mb_stripos') ? mb_stripos($sentence, $term_name) !== false : stripos($sentence, $term_name) !== false) {
                        $score += 10;
                    }
                    if ($length >= 55 && $length <= 220) {
                        $score += 3;
                    }
                    if ($score > $best_score) {
                        $best_score = $score;
                        $best_sentence = $sentence;
                    }
                }
            }

            if ($best_score >= 11) {
                return apply_filters('taxopress_local_term_description', $best_sentence, $context);
            }

            $titles = array_values(array_filter(array_map(static function ($post) {
                return !empty($post['title']) ? '“' . wp_trim_words($post['title'], 10, '…') . '”' : '';
            }, $context['posts'])));

            if (!empty($titles)) {
                $title_list = wp_sprintf_l('%l', array_slice($titles, 0, 2));
                $description = sprintf(
                    /* translators: 1: term name, 2: one or two representative content titles. */
                    esc_html__('%1$s brings together content including %2$s.', 'simple-tags'),
                    $term_name,
                    $title_list
                );
            } elseif ($context['user_context'] !== '') {
                $description = sprintf(
                    /* translators: 1: term name, 2: user-supplied context. */
                    esc_html__('%1$s covers %2$s', 'simple-tags'),
                    $term_name,
                    rtrim($context['user_context'], '. ') . '.'
                );
            } elseif ($context['parent_name'] !== '') {
                $description = sprintf(
                    /* translators: 1: term name, 2: taxonomy label, 3: parent term name. */
                    esc_html__('%1$s is a %2$s organized under %3$s.', 'simple-tags'),
                    $term_name,
                    $context['taxonomy_label'],
                    $context['parent_name']
                );
            } else {
                $description = sprintf(
                    /* translators: 1: term name, 2: taxonomy label. */
                    esc_html__('Content related to %1$s in the %2$s taxonomy.', 'simple-tags'),
                    $term_name,
                    $context['taxonomy_label']
                );
            }

            return apply_filters('taxopress_local_term_description', $description, $context);
        }

        private static function generate_connector_description($provider_id, $context)
        {
            $provider_id = sanitize_key($provider_id);
            $providers = TaxoPressAiApi::get_wordpress_ai_providers();
            if ($provider_id === '' || !isset($providers[$provider_id]) || !function_exists('wp_ai_client_prompt')) {
                return new WP_Error('provider_unavailable', esc_html__('The selected AI Connector is not available.', 'simple-tags'));
            }

            $instructions = self::get_provider_instructions($context);
            $input = self::get_provider_input($context);
            $builder = wp_ai_client_prompt($input)
                ->using_provider($provider_id)
                ->using_system_instruction($instructions)
                ->using_max_tokens(220);

            if (!$builder->is_supported_for_text_generation()) {
                return new WP_Error('unsupported_provider', esc_html__('The selected AI Connector cannot generate text.', 'simple-tags'));
            }

            $result = $builder->generate_text_result();
            if (is_wp_error($result)) {
                return $result;
            }

            $text = method_exists($result, 'toText') ? (string) $result->toText() : '';
            return self::extract_provider_description($text);
        }

        /** Generate a description through the configured direct OpenAI source. */
        private static function generate_openai_description($context)
        {
            $settings = TaxoPressAiUtilities::taxopress_get_ai_settings_data();
            $api_key = !empty($settings['open_ai_api_key']) ? trim((string) $settings['open_ai_api_key']) : '';
            if ($api_key === '') {
                return new WP_Error('missing_api_key', esc_html__('The configured OpenAI source does not have an API key.', 'simple-tags'));
            }

            $model = TaxoPressAiApi::normalize_open_ai_model(
                !empty($settings['open_ai_model']) ? $settings['open_ai_model'] : TaxoPressAiApi::OPEN_AI_MODEL
            );
            $body = [
                'model'        => $model,
                'instructions' => self::get_provider_instructions($context),
                'input'        => [[
                    'role'    => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => self::get_provider_input($context),
                    ]],
                ]],
                'max_output_tokens' => 220,
                'store'             => false,
            ];

            if (TaxoPressAiApi::open_ai_model_supports_temperature($model)) {
                $body['temperature'] = 0.3;
            }
            if (TaxoPressAiApi::open_ai_model_supports_structured_outputs($model)) {
                $body['text'] = [
                    'format' => [
                        'type'   => 'json_schema',
                        'name'   => 'term_description',
                        'strict' => true,
                        'schema' => [
                            'type'                 => 'object',
                            'properties'           => [
                                'description' => ['type' => 'string'],
                            ],
                            'required'             => ['description'],
                            'additionalProperties' => false,
                        ],
                    ],
                ];
            }

            $response = wp_remote_post(TaxoPressAiApi::OPEN_AI_API_URL, [
                // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
                'timeout' => 60,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => wp_json_encode($body),
            ]);
            if (is_wp_error($response)) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            if ($status_code !== 200) {
                $message = !empty($response_body['error']['message'])
                    ? sanitize_text_field($response_body['error']['message'])
                    : esc_html__('The OpenAI request failed.', 'simple-tags');
                return new WP_Error('openai_error', $message);
            }

            $refusal = TaxoPressAiApi::get_open_ai_response_refusal($response_body);
            if ($refusal !== '') {
                return new WP_Error('openai_refusal', $refusal);
            }

            return self::extract_provider_description(TaxoPressAiApi::get_open_ai_response_text($response_body));
        }

        /** Instructions shared by optional text-generation providers. */
        private static function get_provider_instructions($context)
        {
            return sprintf(
                'Write a concise, factual WordPress term description in the site locale %s. Use one or two plain-text sentences. Use only the supplied context, do not invent facts, do not use Markdown, and do not add a heading. Return JSON with one string property named description.',
                sanitize_text_field($context['locale'])
            );
        }

        /** Encode bounded term context separately from provider instructions. */
        private static function get_provider_input($context)
        {
            return wp_json_encode([
                'term_name'      => $context['term_name'],
                'taxonomy'       => $context['taxonomy_label'],
                'parent_term'    => $context['parent_name'],
                'site_name'      => $context['site_name'],
                'user_context'   => $context['user_context'],
                'representative_content' => $context['posts'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        /** Extract a plain description from structured or plain provider output. */
        private static function extract_provider_description($text)
        {
            $text = trim((string) $text);
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
            $decoded = json_decode($text, true);
            if (!is_array($decoded) && preg_match('/\{.*\}/s', $text, $match)) {
                $decoded = json_decode($match[0], true);
            }
            if (is_array($decoded) && isset($decoded['description']) && is_scalar($decoded['description'])) {
                $text = (string) $decoded['description'];
            }

            $description = self::sanitize_description($text);
            return $description !== ''
                ? $description
                : new WP_Error('empty_description', esc_html__('The provider returned an empty description.', 'simple-tags'));
        }

        /** Keep generated descriptions plain, bounded, and safe for review. */
        private static function sanitize_description($description)
        {
            $description = sanitize_textarea_field(wp_strip_all_tags((string) $description));
            if (function_exists('mb_substr')) {
                return trim(mb_substr($description, 0, 1200));
            }

            return trim(substr($description, 0, 1200));
        }

        /** Explain how much context supported the suggestion. */
        private static function get_context_message($status)
        {
            if ($status === 'content_based') {
                return esc_html__('Based on published content assigned directly to this term.', 'simple-tags');
            }
            if ($status === 'user_context') {
                return esc_html__('Based on the additional context you supplied.', 'simple-tags');
            }

            return esc_html__('Based only on the term and taxonomy because no assigned published content was found.', 'simple-tags');
        }

        /** Rate-limit only requests that leave the site. */
        private static function allow_remote_generation()
        {
            $key = 'taxopress_desc_rate_' . get_current_user_id() . '_' . (int) floor(time() / MINUTE_IN_SECONDS);
            $count = (int) get_transient($key);
            if ($count >= (int) apply_filters('taxopress_term_description_rate_limit', 10)) {
                return false;
            }

            set_transient($key, $count + 1, 2 * MINUTE_IN_SECONDS);
            return true;
        }

        /** Compare form values with decoded WordPress term names. */
        private static function decoded_term_name($term)
        {
            return html_entity_decode((string) $term->name, ENT_QUOTES, get_bloginfo('charset'));
        }
    }
}
