<?php

defined('ABSPATH') || exit;

/** Optional attribution for terms. Author assignments do not change term permissions. */
class Taxopress_Term_Authors
{
    public const META_KEY = '_taxopress_term_author';
    public const COLUMN = 'taxopress_term_author';

    public static function register()
    {
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('admin_init', [__CLASS__, 'register_admin_hooks']);
        add_action('created_term', [__CLASS__, 'created_term'], 10, 3);
        add_action('edited_term', [__CLASS__, 'edited_term'], 10, 3);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit'], 10, 3);
        add_filter('get_terms_args', [__CLASS__, 'native_query_args'], 20, 2);
        add_filter('terms_clauses', [__CLASS__, 'filter_clauses'], 20, 3);
        add_action('load-edit-tags.php', [__CLASS__, 'prepare_native_filter']);
    }

    public static function register_meta()
    {
        register_term_meta('', self::META_KEY, [
            'type' => 'integer',
            'single' => true,
            'sanitize_callback' => 'absint',
            'show_in_rest' => false,
            'auth_callback' => [__CLASS__, 'can_edit_meta'],
        ]);
    }

    public static function can_edit_meta($allowed, $meta_key, $term_id)
    {
        $term = get_term($term_id);
        return $term && !is_wp_error($term) && self::is_enabled($term->taxonomy)
            && current_user_can('edit_term', $term_id);
    }

    public static function is_enabled($taxonomy)
    {
        $settings = taxopress_get_all_edited_taxonomy_data();
        $override = self::normalize_override($settings[$taxonomy]['enable_term_authors'] ?? 'default');
        return 'default' === $override
            ? get_taxopress_disp_boolean(SimpleTags_Plugin::get_option_value('enable_term_authors'))
            : '1' === $override;
    }

    /** Preserve earlier on/off choices while allowing taxonomies to follow the global setting. */
    public static function normalize_override($value)
    {
        if (in_array($value, [true, 1, '1', 'true'], true)) {
            return '1';
        }
        return in_array($value, [false, 0, '0', 'false'], true) ? '0' : 'default';
    }

    public static function has_enabled_taxonomy($taxonomies)
    {
        foreach ($taxonomies as $taxonomy) {
            if (self::is_enabled($taxonomy)) {
                return true;
            }
        }
        return false;
    }

    /** Deleted users and users outside the current site are treated as unassigned. */
    public static function get_author_id($term_id)
    {
        $author_id = (int) get_term_meta($term_id, self::META_KEY, true);
        return self::is_site_user($author_id) ? $author_id : 0;
    }

    private static function is_site_user($user_id)
    {
        return $user_id > 0 && get_userdata($user_id)
            && is_user_member_of_blog($user_id, get_current_blog_id());
    }

    public static function created_term($term_id, $tt_id, $taxonomy)
    {
        if (!self::is_enabled($taxonomy)) {
            return;
        }

        $author_id = self::requested_author($term_id);
        if (null === $author_id) {
            $author_id = get_current_user_id();
        }
        if (!is_wp_error($author_id) && self::is_site_user($author_id)) {
            // Do not overwrite attribution already supplied by another creation hook.
            add_term_meta($term_id, self::META_KEY, $author_id, true);
        }
    }

    public static function edited_term($term_id, $tt_id, $taxonomy)
    {
        // The Terms screen can move a term to another taxonomy after wp_update_term().
        // Its callback saves attribution once the destination taxonomy is known.
        if (
            wp_doing_ajax()
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing only; save_from_request() checks the author nonce.
            && isset($_POST['action'])
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Routing only; save_from_request() checks the author nonce.
            && 'taxopress_terms_inline_save_term' === sanitize_key(wp_unslash($_POST['action']))
        ) {
            return;
        }
        self::save_from_request($term_id, $taxonomy);
    }

    public static function save_from_request($term_id, $taxonomy)
    {
        $term = get_term($term_id);
        if (!$term || is_wp_error($term) || $term->taxonomy !== $taxonomy || !self::is_enabled($taxonomy)) {
            return;
        }
        $author_id = self::requested_author($term_id);
        if (null === $author_id || is_wp_error($author_id)) {
            return;
        }
        if (0 === $author_id) {
            delete_term_meta($term_id, self::META_KEY);
        } else {
            update_term_meta($term_id, self::META_KEY, $author_id);
        }
    }

    /** Missing fields preserve attribution during bulk edits and programmatic updates. */
    private static function requested_author($term_id)
    {
        if (
            !isset($_POST['taxopress_term_author'], $_POST['taxopress_term_author_nonce'])
            || !is_scalar($_POST['taxopress_term_author_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['taxopress_term_author_nonce'])), 'taxopress-term-author')
            || !current_user_can('edit_term', $term_id)
        ) {
            return null;
        }

        if (!is_scalar($_POST['taxopress_term_author'])) {
            return new WP_Error('invalid_term_author', __('Please select a valid author.', 'simple-tags'));
        }
        $value = sanitize_text_field(wp_unslash($_POST['taxopress_term_author']));
        if (!ctype_digit($value) || ((int) $value > 0 && !self::is_site_user((int) $value))) {
            return new WP_Error('invalid_term_author', __('Please select a valid author.', 'simple-tags'));
        }
        return (int) $value;
    }

    public static function register_admin_hooks()
    {
        foreach (get_taxonomies([], 'names') as $taxonomy) {
            if (!self::is_enabled($taxonomy)) {
                continue;
            }
            add_action($taxonomy . '_add_form_fields', [__CLASS__, 'add_field']);
            add_action($taxonomy . '_edit_form_fields', [__CLASS__, 'edit_field']);
            add_filter('manage_edit-' . $taxonomy . '_columns', [__CLASS__, 'columns']);
            add_filter('manage_' . $taxonomy . '_custom_column', [__CLASS__, 'native_column'], 10, 3);
            add_action('after-' . $taxonomy . '-table', [__CLASS__, 'native_filter']);
        }
    }

    /** Read a list filter without changing any term or attribution. */
    public static function selected_author()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
        $value = isset($_REQUEST['taxopress_author']) && is_scalar($_REQUEST['taxopress_author'])
            ? sanitize_text_field(wp_unslash($_REQUEST['taxopress_author'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
        if ('none' === $value) {
            return $value;
        }
        return ctype_digit($value) && self::is_site_user((int) $value) ? (string) (int) $value : '';
    }

    public static function filter_dropdown($id, $name = 'taxopress_author', $class = '')
    {
        echo '<label class="screen-reader-text" for="' . esc_attr($id) . '">' . esc_html__('Filter by term author', 'simple-tags') . '</label>';
        wp_dropdown_users([
            'name' => $name,
            'id' => $id,
            'class' => $class,
            'selected' => self::selected_author(),
            'show_option_all' => __('All authors', 'simple-tags'),
            'show_option_none' => __('No author', 'simple-tags'),
            'option_none_value' => 'none',
            'orderby' => 'display_name',
            'blog_id' => get_current_blog_id(),
        ]);
    }

    public static function native_filter($taxonomy)
    {
        if (!self::is_enabled($taxonomy)) {
            return;
        }
        // Move into the native table toolbar after the table has rendered.
        echo '<div id="taxopress-native-author-filter" class="alignleft actions" hidden>';
        self::filter_dropdown('taxopress-native-author');
        echo '<button type="button" class="button">' . esc_html__('Filter', 'simple-tags') . '</button></div>';
    }

    /** Ask core to render a paginated list rather than an unfiltered hierarchy. */
    public static function prepare_native_filter()
    {
        $screen = get_current_screen();
        if (!$screen || 'edit-tags' !== $screen->base || !self::is_enabled($screen->taxonomy) || !self::selected_author()) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Default sorting for a read-only author filter.
        if (empty($_REQUEST['orderby'])) {
            $_REQUEST['orderby'] = 'name';
        }
    }

    /** Only the native list and its count receive the selected filter, never parent selectors. */
    public static function native_query_args($args, $taxonomies)
    {
        if (!is_admin() || wp_doing_ajax()) {
            return $args;
        }
        $screen = get_current_screen();
        $selection = self::selected_author();
        if (
            !$screen || 'edit-tags' !== $screen->base || !$selection
            || array_values((array) $taxonomies) !== [$screen->taxonomy] || !self::is_enabled($screen->taxonomy)
        ) {
            return $args;
        }
        if (!isset($args['page']) && empty($args['number']) && 'count' !== ($args['fields'] ?? '')) {
            return $args;
        }
        $args['taxopress_term_author_filter'] = $selection;
        return $args;
    }

    /** Shared by native/custom list queries and the Terms screen's cached count query. */
    public static function author_where($selection)
    {
        global $wpdb;
        if ('' === $selection) {
            return '';
        }
        if ('none' === $selection) {
            $membership = '';
            if (is_multisite()) {
                $membership = $wpdb->prepare(
                    " AND EXISTS (SELECT 1 FROM {$wpdb->usermeta} AS taxopress_author_membership WHERE taxopress_author_membership.user_id = taxopress_author_user.ID AND taxopress_author_membership.meta_key = %s)",
                    $wpdb->get_blog_prefix() . 'capabilities'
                );
            }
            return $wpdb->prepare(
                "NOT EXISTS (SELECT 1 FROM {$wpdb->termmeta} AS taxopress_author_meta INNER JOIN {$wpdb->users} AS taxopress_author_user ON taxopress_author_user.ID = taxopress_author_meta.meta_value WHERE taxopress_author_meta.term_id = t.term_id AND taxopress_author_meta.meta_key = %s {$membership})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Trusted tables/prepared condition; excludes deleted authors without loading all site users.
                self::META_KEY
            );
        }
        return $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->termmeta} AS taxopress_author_meta WHERE taxopress_author_meta.term_id = t.term_id AND taxopress_author_meta.meta_key = %s AND taxopress_author_meta.meta_value = %s)",
            self::META_KEY,
            $selection
        );
    }

    public static function filter_clauses($clauses, $taxonomies, $args)
    {
        $selection = $args['taxopress_term_author_filter'] ?? '';
        if (is_string($selection) && ('' === $selection || 'none' === $selection || ctype_digit($selection))) {
            $condition = self::author_where($selection);
            if ($condition) {
                $clauses['where'] .= ' AND ' . $condition;
            }
        }
        return $clauses;
    }

    public static function columns($columns)
    {
        $columns[self::COLUMN] = __('Author', 'simple-tags');
        return $columns;
    }

    public static function column($term_id, $taxonomy)
    {
        if (!self::is_enabled($taxonomy)) {
            return '&mdash;';
        }
        $author_id = self::get_author_id($term_id);
        $user = $author_id ? get_userdata($author_id) : false;
        return '<span class="taxopress-term-author-data" data-author-id="' . esc_attr($author_id) . '">'
            . ($user ? esc_html($user->display_name) : '&mdash;') . '</span>';
    }

    public static function native_column($content, $column, $term_id)
    {
        if (self::COLUMN !== $column) {
            return $content;
        }
        $term = get_term($term_id);
        return $term && !is_wp_error($term) ? self::column($term_id, $term->taxonomy) : $content;
    }

    private static function author_select($selected)
    {
        wp_dropdown_users([
            'name' => 'taxopress_term_author',
            'id' => 'taxopress-term-author',
            'selected' => $selected,
            'show_option_none' => __('— No author —', 'simple-tags'),
            'option_none_value' => '0',
            'orderby' => 'display_name',
            'blog_id' => get_current_blog_id(),
        ]);
        wp_nonce_field('taxopress-term-author', 'taxopress_term_author_nonce', false);
    }

    public static function add_field($taxonomy)
    {
        if (!self::is_enabled($taxonomy)) {
            return;
        }
        echo '<div class="form-field taxopress-term-author-field"><label for="taxopress-term-author">'
            . esc_html__('Author', 'simple-tags') . '</label>';
        self::author_select(get_current_user_id());
        echo '<p>' . esc_html__('The user credited with creating this term.', 'simple-tags') . '</p></div>';
    }

    public static function edit_field($term)
    {
        if (!self::is_enabled($term->taxonomy)) {
            return;
        }
        echo '<tr class="form-field taxopress-term-author-field"><th scope="row"><label for="taxopress-term-author">'
            . esc_html__('Author', 'simple-tags') . '</label></th><td>';
        self::author_select(self::get_author_id($term->term_id));
        echo '<p class="description">' . esc_html__('The user credited with creating this term.', 'simple-tags') . '</p></td></tr>';
    }

    public static function quick_edit($column, $screen, $taxonomy)
    {
        if (self::COLUMN !== $column || 'edit-tags' !== $screen || !self::is_enabled($taxonomy)) {
            return;
        }
        self::quick_edit_field();
    }

    public static function quick_edit_field()
    {
        echo '<fieldset class="taxopress-term-author-field"><div class="inline-edit-col"><label>'
            . '<span class="title">' . esc_html__('Author', 'simple-tags') . '</span>';
        self::author_select(0);
        echo '</label></div></fieldset>';
    }

    public static function enqueue_scripts()
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        $native = in_array($screen->base, ['edit-tags', 'term'], true) && self::is_enabled($screen->taxonomy);
        $terms = false !== strpos($screen->id, '_page_st_terms')
            && self::has_enabled_taxonomy(get_taxonomies([], 'names'));
        if (!$native && !$terms) {
            return;
        }
        $path = STAGS_DIR . '/assets/js/term-authors.js';
        wp_enqueue_script(
            'taxopress-term-authors',
            STAGS_URL . '/assets/js/term-authors.js',
            'term' === $screen->base ? ['jquery'] : ['jquery', 'inline-edit-tax'],
            STAGS_VERSION . '.' . filemtime($path),
            true
        );
    }
}
