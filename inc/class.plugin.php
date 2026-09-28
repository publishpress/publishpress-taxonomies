<?php

class SimpleTags_Plugin
{
    public static $options = null;

    /**
     * Add initial ST options in DB, init roles/permissions
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function activation()
    {
        // Put default options
        $options_from_table = get_option(STAGS_OPTIONS_NAME);
        if (empty($options_from_table)) {
            add_option(STAGS_OPTIONS_NAME, self::load_default_option());
        }

        // Init roles
        if (function_exists('get_role')) {
            $role = get_role('administrator');
            if (null !== $role && ! $role->has_cap('simple_tags')) {
                $role->add_cap('simple_tags');
            }

            if (null !== $role && ! $role->has_cap('admin_simple_tags')) {
                $role->add_cap('admin_simple_tags');
            }

            $role = get_role('editor');
            if (null !== $role && ! $role->has_cap('simple_tags')) {
                $role->add_cap('simple_tags');
            }
        }

        // disable media tag
        taxopress_deactivate_taxonomy('media_tag');

        // add activated option
        add_option('taxopress_activate', true);
    }

    /**
     * Do nothing :)
     */
    public static function deactivation()
    {
    }

    /**
     * Load default option from specific file
     *
     * @return array
     */
    private static function load_default_option()
    {
        $default_options = (array) include STAGS_DIR . '/inc/helper.options.default.php';

        // add taxopress ai post type and taxonomies options so we can have all post types. TODO: This need to be a filter
        foreach (get_post_types(['public' => true], 'names') as $post_type => $post_type_object) {
            if ($post_type == 'post') {
                $opt_default_value = 'post_tag';
            } else {
                $opt_default_value = 0;
            }
            $default_options['taxopress_ai_' . $post_type . '_metabox_default_taxonomy'] = $opt_default_value;
            $default_options['taxopress_ai_' . $post_type . '_metabox_display_option'] = 'default';

            $default_options['taxopress_ai_' . $post_type . '_metabox_orderby'] = 'count';
            $default_options['taxopress_ai_' . $post_type . '_metabox_order'] = 'desc';
            $default_options['taxopress_ai_' . $post_type . '_metabox_maximum_terms'] = 45;
            $default_options['taxopress_ai_' . $post_type . '_metabox_show_post_count'] = 0;
            $default_options['taxopress_ai_' . $post_type . '_metabox_show_term_slug'] = 0;

            $default_options['taxopress_ai_' . $post_type . '_minimum_term_length'] = 2;
            $default_options['taxopress_ai_' . $post_type . '_maximum_term_length'] = 40;

            $default_options['taxopress_ai_' . $post_type . '_support_private_taxonomy'] = 0;
            $default_options['taxopress_ai_' . $post_type . '_exclusions'] = '';
            $default_options['enable_taxopress_ai_' . $post_type . '_metabox'] = $opt_default_value;
            $default_options['taxopress_ai_' . $post_type . '_metabox_filters'] = 1;

            foreach (['post_terms', 'existing_terms', 'suggest_local_terms', 'create_terms'] as $taxopress_ai_tab) {
                $default_options['enable_taxopress_ai_' . $post_type . '_' . $taxopress_ai_tab . '_tab'] = ($post_type == 'post') ? 1 : 0;
            }
        }

        // add metabox post type and taxonomies options so we can have all post types. TODO: This need to be a filter
        $tax_names = array_keys(get_taxonomies([], 'names'));
        foreach (taxopress_get_all_wp_roles() as $role_name => $role_info) {
            if (in_array($role_name, ['administrator', 'editor', 'author', 'contributor'])) {
                $enable_acess_default_value = 1;
            } else {
                $enable_acess_default_value = 0;
            }
            $default_options['enable_' . $role_name . '_metabox'] = $enable_acess_default_value;
            $default_options['enable_restrict_' . $role_name . '_metabox'] = $enable_acess_default_value;

            // ONLY admin + editor can edit the metabox label by default
            $default_options['enable_edit_' . $role_name . '_metabox'] = in_array($role_name, ['administrator', 'editor']) ? 1 : 0;

            $options['enable_metabox_' . $role_name . ''] = $tax_names;
            $options['remove_taxonomy_metabox_' . $role_name . ''] = [];
        }

        return $default_options;
    }

    /**
     * Load plugin option, combine DB options with default
     */
    private static function load_option()
    {
        $saved_option = wp_parse_args((array) get_option(STAGS_OPTIONS_NAME), self::load_default_option());

        self::$options = $saved_option;
    }

    /*
     * Get all options into an array
     *
     * @return array
     */
    public static function get_option()
    {
        if (null === self::$options) {
            self::load_option();
        }

        return self::$options;
    }

    /**
     * Get one option value from all options
     *
     * @param string $key
     *
     * @return bool|mixed
     */
    public static function get_option_value($key = '')
    {
        if (null === self::$options) {
            self::load_option();
        }

        return isset(self::$options[ $key ]) ? self::$options[ $key ] : false;
    }

    /**
     * Update one option value from all options
     *
     * @param string $key
     * @param string $value
     * @param bool $auto_update
     */
    public static function set_option_value($key = '', $value = '', $auto_update = true)
    {
        if (null === self::$options) {
            self::load_option();
        }

        if (isset(self::$options[ $key ])) {
            self::$options[ $key ] = $value;

            if (true === $auto_update) {
                self::update_option();
            }
        }
    }

    /**
     * Update all options
     *
     * @param $value
     * @param bool $auto_update
     */
    public static function set_option($value, $auto_update = true)
    {
        self::$options = $value;

        if (true === $auto_update) {
            self::update_option();
        }
    }

    /**
     * Set default option into DB
     */
    public static function set_default_option()
    {
        self::$options = self::load_default_option();
        self::update_option();
    }

    /**
     * Update options into DB
     *
     * @return bool
     */
    public static function update_option()
    {
        if (null === self::$options) {
            self::load_option();
        }

        return update_option(STAGS_OPTIONS_NAME, self::$options);
    }
}
