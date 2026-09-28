<?php

if (!class_exists('TaxoPressAiFields')) {
    class TaxoPressAiFields
    {
        /**
         * Get the fields tabs to be rendered on taxopress ai screen
         *
         * @return array
         */
        public static function get_fields_tabs()
        {
            $fields_tabs = [];

            // Get all editable labels
            $existing_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_existing_terms_tab_label');
            if (empty($existing_terms_label)) {
                $existing_terms_label = esc_html__('Show All Existing Terms', 'simple-tags');
            }

            $post_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_post_terms_tab_label');
            if (empty($post_terms_label)) {
                $post_terms_label = esc_html__('Manage Post Terms', 'simple-tags');
            }

            $suggest_local_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_suggest_local_terms_tab_label');
            if (empty($suggest_local_terms_label)) {
                $suggest_local_terms_label = esc_html__('Auto Terms', 'simple-tags');
            }

            $create_terms_label = SimpleTags_Plugin::get_option_value('taxopress_ai_create_terms_tab_label');
            if (empty($create_terms_label)) {
                $create_terms_label = esc_html__('Create Terms', 'simple-tags');
            }

            $fields_tabs['existing_terms'] = [
                'label' => $existing_terms_label,
                'button_label'   => esc_html__('View Existing Terms', 'simple-tags'),
                'description'  => esc_html__('This feature allows you to browse all the terms in a taxonomy.', 'simple-tags'),
            ];

            $fields_tabs['post_terms'] = [
                'label' => $post_terms_label,
                'button_label'   => esc_html__('View Current Post Terms', 'simple-tags'),
                'description'  => esc_html__('This feature allows you to manage all the terms that are currently attached to a post.', 'simple-tags'),
            ];

            $fields_tabs['autoterms'] = [
                'label' => $suggest_local_terms_label,
                'button_label'   => esc_html__('View Terms', 'simple-tags'),
                'description'  => esc_html__('This feature allows you to browse based on selected auto terms settings and sources.', 'simple-tags'),
            ];

            /**
             * Customize fields tabs presented on taxopress ai screen.
             *
             * @param array $fields_tabs Existing fields tabs to display.
             */
            $fields_tabs = apply_filters('taxopress_ai_tabs', $fields_tabs);

            return $fields_tabs;
        }
    }
}
