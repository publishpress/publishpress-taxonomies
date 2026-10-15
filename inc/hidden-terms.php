<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

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
            add_action('template_redirect', [$this, 'taxopress_redirect_hidden_term_archive'], 1);
            add_filter('get_the_terms', [$this,'taxopress_remove_hidden_terms'], 10, 3);
            add_filter('wp_sitemaps_taxonomies_query_args', [$this, 'taxopress_exclude_hidden_terms_from_core_sitemap'], 10, 2);
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

        /**
         * Redirect a hidden term only when its archive is directly requested.
         *
         * Term permalinks must remain unchanged so canonical tags, admin links,
         * sitemaps, and third-party integrations receive the real archive URL.
         */
        public static function taxopress_redirect_hidden_term_archive()
        {
            if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                return;
            }

            if (!is_category() && !is_tag() && !is_tax()) {
                return;
            }

            $term = get_queried_object();
            if (!($term instanceof WP_Term)) {
                return;
            }

            $hidden_terms = wp_parse_id_list(get_transient('taxopress_hidden_terms_' . $term->taxonomy));
            if (!in_array((int) $term->term_id, $hidden_terms, true)) {
                return;
            }

            if (wp_safe_redirect(home_url('/'), 302, 'PublishPress Taxonomies')) {
                exit;
            }
        }

        /**
         * Exclude hidden term archives from the WordPress core XML sitemap.
         *
         * @param array  $args     Taxonomy term query arguments.
         * @param string $taxonomy Taxonomy slug.
         * @return array Modified taxonomy term query arguments.
         */
        public static function taxopress_exclude_hidden_terms_from_core_sitemap($args, $taxonomy)
        {
            if (!(int) SimpleTags_Plugin::get_option_value('enable_hidden_terms')) {
                return $args;
            }

            $hidden_terms = wp_parse_id_list(get_transient('taxopress_hidden_terms_' . $taxonomy));
            if (empty($hidden_terms)) {
                return $args;
            }

            $excluded_terms  = isset($args['exclude']) ? wp_parse_id_list($args['exclude']) : [];
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Core sitemap term queries require the exclude argument.
            $args['exclude'] = array_values(array_unique(array_merge($excluded_terms, $hidden_terms)));

            return $args;
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
