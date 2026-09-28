<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

if (!class_exists('SimpleTags_Hidden_Terms')) {
    class SimpleTags_Hidden_Terms
    {
        public const MENU_SLUG = 'st_options';

        // class instance
        public static $instance;

        public static function get_instance()
        {
            if (! isset(self::$instance)) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
        * Class Constructor
        */
        public function __construct()
        {

            add_action('taxopress_settings_saved', [$this, 'taxopress_schedule_hidden_terms_cron']);
            add_action('taxopress_update_hidden_terms_event', [$this, 'taxopress_set_hidden_terms']);
            add_filter('term_link', [$this, 'taxopress_modify_hidden_term_links'], 10, 3);
            add_filter('get_the_terms', [$this,'taxopress_remove_hidden_terms'], 10, 3);
            add_action('taxopress_settings_saved', [$this, 'taxopress_update_hidden_terms_immediately']);
        }

        /**
        * Schedule the cron job to update hidden terms once a day if enabled
        */
        public function taxopress_schedule_hidden_terms_cron()
        {
            if ((int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                if (!wp_next_scheduled('taxopress_update_hidden_terms_event')) {
                    wp_schedule_event(time(), 'daily', 'taxopress_update_hidden_terms_event');
                }
            } else {
                wp_clear_scheduled_hook('taxopress_update_hidden_terms_event');
            }
        }

        public function taxopress_set_hidden_terms($taxonomy = 'post_tag', $min_usage = 0)
        {
            if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                return;
            }
            global $wpdb;

            $min_usage = (int) SimpleTags_Plugin::get_option_value('hide-rarely');
            if ((int) $min_usage > 100) {
                return;
            }

            $taxonomies = array_keys(get_taxonomies(['public' => true, 'show_ui' => true]));

            foreach ($taxonomies as $taxonomy) {
                $offset = 0;
                $limit = 5000;
                $all_hidden_terms = [];

                do {
                    $terms_id = $wpdb->get_col($wpdb->prepare(
                        "SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND count < %d LIMIT %d OFFSET %d",
                        $taxonomy,
                        $min_usage,
                        $limit,
                        $offset
                    ));

                    if (!empty($terms_id)) {
                        $all_hidden_terms = array_merge($all_hidden_terms, $terms_id);
                        clean_term_cache($terms_id, $taxonomy);
                    }

                    $offset += $limit;
                } while (!empty($terms_id));

                // Check existing transient and update only if changed
                $existing_hidden_terms = get_transient('taxopress_hidden_terms_' . $taxonomy);
                if ($existing_hidden_terms === false || array_diff($all_hidden_terms, $existing_hidden_terms) || array_diff($existing_hidden_terms, $all_hidden_terms)) {
                    set_transient('taxopress_hidden_terms_' . $taxonomy, $all_hidden_terms, DAY_IN_SECONDS);
                }
            }

            return true;
        }

        public static function taxopress_modify_hidden_term_links($term_link, $term, $taxonomy)
        {
            if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                return $term_link;
            }

            static $hidden_terms_cache = [];

            if (!isset($hidden_terms_cache[$taxonomy])) {
                $hidden_terms_cache[$taxonomy] = get_transient('taxopress_hidden_terms_' . $taxonomy);
            }

            if (!empty($hidden_terms_cache[$taxonomy]) && in_array($term->term_id, $hidden_terms_cache[$taxonomy])) {
                return home_url();
            }

            return $term_link;
        }

        /**
         * Remove hidden terms from the list on the frontend.
         *
         * @param array  $terms    List of terms.
         * @param int    $post_id  Post ID.
         * @param string $taxonomy Taxonomy slug.
         * @return array Modified list of terms.
         */
        public static function taxopress_remove_hidden_terms($terms, $post_id, $taxonomy)
        {
            if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                return $terms;
            }

            if (!is_admin() && !empty($terms)) {
                $hidden_terms = get_transient('taxopress_hidden_terms_' . $taxonomy);

                if (!empty($hidden_terms)) {
                    $hidden_terms_lookup = array_flip($hidden_terms);

                    $terms = array_filter($terms, function ($term) use ($hidden_terms_lookup) {
                        return !isset($hidden_terms_lookup[$term->term_id]);
                    });

                    // Re-index the array to prevent issues with numeric keys
                    $terms = array_values($terms);
                }
            }

            return $terms;
        }


        /**
         * Run the hidden terms update when settings are saved.
         */
        public function taxopress_update_hidden_terms_immediately()
        {
            if ((int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                $this->taxopress_set_hidden_terms();
            }
        }
    }

    SimpleTags_Hidden_Terms::get_instance();
}
