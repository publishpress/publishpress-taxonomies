<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.Security.NonceVerification.Recommended -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

/**
 * trim and remove empty element
 *
 * @param string $element
 *
 * @return string
 */
function _delete_empty_element($element)
{
    $element = stripslashes($element);
    $element = trim($element);
    if (!empty($element)) {
        return $element;
    }

    return false;
}

/**
 * Test if page have tags or not...
 *
 * @return boolean
 * @author WebFactory Ltd
 */
function is_page_have_tags()
{
    $taxonomies = get_object_taxonomies('page');

    return in_array('post_tag', $taxonomies, true);
}

/**
 * Register widget on WP
 */
function st_register_widget()
{
    register_widget('SimpleTags_Widget');
    if (1 === (int) SimpleTags_Plugin::get_option_value('active_terms_display')) {
        register_widget('SimpleTags_Shortcode_Widget');
    }
    if (1 === (int) SimpleTags_Plugin::get_option_value('active_post_tags')) {
        register_widget('SimpleTags_PostTags_Widget');
    }
    if (1 === (int) SimpleTags_Plugin::get_option_value('active_related_posts_new')) {
        register_widget('SimpleTags_RelatedPosts_Widget');
    }
}

function taxopress_menu_separator($identifier, $parent)
{
    // create a separator menu
    $separator_menu = [];
    $separator_menu[] = '';
    $separator_menu[] = $parent;
    $separator_menu[] = $parent . $identifier;
    $separator_menu[] = '';
    $separator_menu[] = 'taxopress-menu-separator ' . $parent . $identifier;

    return $separator_menu;
}

/**
 * Method for adding default hidden columns to the PublishPress Taxonomies Terms screen.
 */
add_filter('default_hidden_columns', 'taxopress_terms_default_hidden_columns', 10, 2);
function taxopress_terms_default_hidden_columns($hidden, $screen)
{
    if (isset($screen->id) && $screen->id === 'taxopress_page_st_terms') {
        $hidden[] = 'taxopress_custom_url';
    }
    return $hidden;
}

/**
 * Change menu item order
 */
add_action('admin_menu', 'taxopress_re_order_menu', 15);
function taxopress_re_order_menu()
{
    global $submenu;

    foreach ($submenu as $menuName => $menuItems) {
        if ('st_options' === $menuName) {
            $taxopress_settings = $taxopress_dashboard = $taxopress_taxonomies = $taxopress_linked_terms = $taxopress_autoterms_schedule = $taxopress_upgrade = false;

            $taxopress_submenus     = $submenu['st_options'];
            $dashboard_options      = taxopress_dashboard_options();
            $dashboard_option_pages = array_keys($dashboard_options);
            foreach ($taxopress_submenus as $key => $taxopress_submenu) {
                $slug_ = $taxopress_submenu[2];
                $parent_ = $taxopress_submenu[1];
                if (in_array($slug_, $dashboard_option_pages)) {
                    if (1 === (int) SimpleTags_Plugin::get_option_value($dashboard_options[$slug_]['option_key'])) {
                        $showHide = '';
                    } else {
                        $showHide = ' taxopress-hide-menu-item';
                    }
                    $taxopress_submenus[$key][4] = $slug_ . '-menu-item' . $showHide;
                }

                if ($slug_ === 'st_options') { //settings
                    $taxopress_settings = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }
                if ($slug_ === 'st_taxonomies') { //taxonomies
                    $taxopress_taxonomies = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }
                if ($slug_ === 'st_dashboard') { //dashboard
                    $taxopress_dashboard = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }
                if ($slug_ === 'st_linked_terms') { //linked terms
                    $taxopress_linked_terms = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }
                if ($slug_ === 'st_autoterms_schedule') { //autoterms schedule
                    $taxopress_autoterms_schedule = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }

                if ($slug_ === 'st_options-menu-upgrade-link') { //upgrade to pro link
                    $taxopress_upgrade = $taxopress_submenus[$key];
                    unset($taxopress_submenus[$key]);
                }
            }
            // add taxonomies first so it can fall as second item after dashboard
            if ($taxopress_taxonomies) {
                $taxopress_submenus = array_merge([$taxopress_taxonomies], $taxopress_submenus);
            }
            // add dashboard as first item
            if ($taxopress_dashboard) {
                $taxopress_submenus = array_merge([$taxopress_dashboard], $taxopress_submenus);
            }
            // add linked terms
            if ($taxopress_linked_terms) {
                $taxopress_submenus = taxopress_add_at_menu_index(['st_terms', 'st_taxonomies', 'st_dashboard'], $taxopress_linked_terms, $taxopress_submenus);
            }
            // add autoterms schedule
            if ($taxopress_autoterms_schedule) {
                $taxopress_submenus = taxopress_add_at_menu_index(['st_autoterms', 'st_autoterms_content', 'st_taxopress_ai'], $taxopress_autoterms_schedule, $taxopress_submenus);
            }
            // add settings as last item
            if ($taxopress_settings) {
                $taxopress_submenus = array_merge($taxopress_submenus, [$taxopress_settings]);
            }
            // upgrade to pro should be last item if exists
            if ($taxopress_upgrade) {
                $taxopress_submenus = array_merge($taxopress_submenus, [$taxopress_upgrade]);
            }

            //resort array
            ksort($taxopress_submenus);

            // add separator 1 to menus
            $separator1_positions = ['st_posts', 'st_linked_terms', 'st_terms', 'st_taxonomies', 'st_dashboard'];
            foreach ($separator1_positions as $pos) {
                $index = array_search($pos, array_column($taxopress_submenus, 2));
                if ($index !== false) {
                    $separator = taxopress_menu_separator('st_separator_end', 'simple_tags');
                    array_splice($taxopress_submenus, $index + 1, 0, [$separator]);
                    break;
                }
            }

            // Add separator 2 to menus
            $separator2_positions = ['st_autolinks', 'st_related_posts', 'st_post_tags', 'st_terms_display'];
            foreach ($separator2_positions as $pos) {
                $index = array_search($pos, array_column($taxopress_submenus, 2));
                if ($index !== false) {
                    $separator = taxopress_menu_separator('st_separator_end', 'simple_tags');
                    array_splice($taxopress_submenus, $index + 1, 0, [$separator]);
                    break;
                }
            }

            // Add separator 3 to menus
            $separator2_positions = ['st_autoterms_content', 'st_autoterms_schedule', 'st_autoterms'];
            foreach ($separator2_positions as $pos) {
                $index = array_search($pos, array_column($taxopress_submenus, 2));
                if ($index !== false) {
                    $separator = taxopress_menu_separator('st_separator_end', 'simple_tags');
                    array_splice($taxopress_submenus, $index + 1, 0, [$separator]);
                    break;
                }
            }

            if (!taxopress_is_pro_version()) {
                // Add separator 4 to menus
                $index = array_search('st_options', array_column($taxopress_submenus, 3));
                if ($index !== false) {
                    $separator = taxopress_menu_separator('st_separator_end', 'simple_tags');
                    array_splice($taxopress_submenus, $index + 1, 0, [$separator]);
                }
            }

            //resort array
            ksort($taxopress_submenus);
            $submenu['st_options'] = $taxopress_submenus;

            break;
        }
    }
}

add_action('admin_init', 'taxopress_suppress_tag_groups_notice_on_taxopress_screens', 1);
function taxopress_suppress_tag_groups_notice_on_taxopress_screens()
{
    if (! is_admin()) {
        return;
    }

    if (
        ! defined('TAG_GROUPS_FILE')
        && ! defined('TAG_GROUPS_PLUGIN_BASENAME')
        && ! defined('TAG_GROUPS_PLUGIN_ABSOLUTE_PATH')
        && ! function_exists('tag_groups_init')
    ) {
        return;
    }

    if (! class_exists('PublishPress\\WordpressVersionNotices\\Module\\TopNotice\\Module')) {
        return;
    }

    add_filter(
        \PublishPress\WordpressVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER,
        function ($settings) {
            if (! is_admin()) {
                return $settings;
            }

            $isTaxoPressScreen = false;

            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen && isset($screen->base)) {
                    $base = (string) $screen->base;
                    $isTaxoPressScreen = (
                        strpos($base, 'taxopress_page_') === 0
                        || $base === 'toplevel_page_st_options'
                        || $base === 'toplevel_page_st_posts'
                    );
                }
            }

            if (! $isTaxoPressScreen) {
                $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

                if (
                    $page !== ''
                    && (
                        strpos($page, 'st_') === 0
                        || $page === 'st_options'
                        || $page === 'st_posts'
                    )
                ) {
                    $isTaxoPressScreen = true;
                }
            }

            if ($isTaxoPressScreen && isset($settings['taxopress-tag_groups'])) {
                unset($settings['taxopress-tag_groups']);
            }

            return $settings;
        },
        9999
    );
}

function taxopress_add_at_menu_index($key_options, $new_menu, $existing_menus)
{

    foreach ($key_options as $key_option) {
        $index = array_search($key_option, array_column($existing_menus, 2));
        if ($index !== false) {
            $index++;//add it after
            array_splice($existing_menus, $index, 0, [$new_menu]);
            break;
        }
    }
    // add as last if index is false;
    if ($index === false) {
        $existing_menus = array_merge($existing_menus, [$new_menu]);
    }

    return $existing_menus;
}

// Init PublishPress Taxonomies
function init_simple_tags()
{
    new SimpleTags_Client();
    new SimpleTags_Client_TagCloud();

    if (1 === (int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
        new SimpleTags_Client_Schedule();
    }

    // Admin and XML-RPC
    if (is_admin()) {
        require STAGS_DIR . '/inc/class.admin.php';
        new SimpleTags_Admin();
    }

    add_action('widgets_init', 'st_register_widget');
}

function taxopress_admin_pages()
{

    $taxopress_pages = [
        'st_dashboard',
        'st_mass_terms',
        'st_auto',
        'st_options',
        'st_manage',
        'st_taxonomies',
        'st_terms_display',
        'st_post_tags',
        'st_related_posts',
        'st_autolinks',
        'st_autoterms',
        'st_autoterms_content',
        'st_autoterms_schedule',
        'st_terms',
        'st_posts',
        'st_taxopress_ai'
    ];

    return apply_filters('taxopress_admin_pages', $taxopress_pages);
}

function is_taxopress_admin_page()
{

    $taxopress_pages = taxopress_admin_pages();

    $is_taxopress_page = false;
    if (isset($_GET['page']) && in_array($_GET['page'], $taxopress_pages)) {
        $is_taxopress_page = true;
    }

    return apply_filters('is_taxopress_admin_page', $is_taxopress_page);
}

function taxopress_starts_with($haystack, $needle)
{
    $length = strlen($needle);
    return substr($haystack, 0, $length) === $needle;
}

function taxopress_is_html($string)
{
    return preg_match("/<[^<]+>/", $string, $m) != 0;
}



function taxopress_is_screen_main_page()
{

    if (isset($_GET['action']) && $_GET['action'] === 'edit') {
        return false;
    }

    if (isset($_GET['add']) && $_GET['add'] === 'new_item') {
        return false;
    }

    if (isset($_GET['add']) && $_GET['add'] === 'taxonomy') {
        return false;
    }

    return true;
}



function taxopress_format_class($class)
{
    return esc_attr(ltrim($class, '.'));
}

function taxopress_add_class_to_format($xformat, $class)
{
    $classes = $class;
    $html    = $xformat;

    $patterns = array();
    $replacements = array();

    // matches where anchor has existing classes contained in single quotes
    $patterns[0] = '/<a([^>]*)class=\'([^\']*)\'([^>]*)>/';
    $replacements[0] = '<a\1class="' . $classes . ' \2"\3>';

    // matches where anchor has existing classes contained in double quotes
    $patterns[1] = '/<a([^>]*)class="([^"]*)"([^>]*)>/';
    $replacements[1] = '<a\1class="' . $classes . ' \2"\3>';

    // matches where anchor tag has no existing classes
    $patterns[2] = '/<a(?![^>]*class)([^>]*)>/';
    $replacements[2] = '<a\1 class="' . $classes . '">';

    $html = preg_replace($patterns, $replacements, $html);

    return $html;
}

function taxopress_change_to_strings($join)
{
    if (is_array($join)) {
        $join = join(", ", $join);
    }
    return $join;
}

function taxopress_change_to_array($array)
{
    if (!is_array($array)) {
        $array = preg_split("/\s*,\s*/", $array);
    }
    return $array;
}

function taxopress_html_character_and_entity($enity_code_as_key = false)
{
    #Source https://dev.w3.org/html5/html-author/charref
    $character_set = [
        //'&amp;' => '&#38;',
        '&lt;' => '&#60;',
        '&gt;' => '&#62;',
        '&nbsp;' => '&#160;',
        '&excl;' => '&#33;',
        '&quot;' => '&#34;',
        '&num;'  => '&#35;',
        '&dollar;' => '&#36;',
        '&percnt;' => '&#37;',
        '&apos;' => '&#39;',
        '&lpar;' => '&#40;',
        '&rpar;' => '&#41;',
        '&ast; ' => '&#42;',
        '&plus;' => '&#43;',
        '&comma;' => '&#44;',
        '&period;' => '&#46;',
        '&sol;' => '&#47;',
        '&colon;' => '&#58',
        '&semi;' => '&#59;',
        '&equals;' => '&#61;',
        '&quest;' => '&#63;',
        '&commat;' => '&#64;',
        '&lsqb;' => '&#91;',
        '&bsol;' => '&#92;',
        '&rsqb;' => '&#93;',
        '&Hat;' => '&#94;',
        '&lowbar;' => '&#95;',
        '&grave;' => '&#96;',
        '&lcub;' => '&#123;',
        '&verbar;' => '&#124;',
        '&rcub;' => '&#125;',
        '&iexcl;' => '&#161;',
        '&cent;' => '&#162;',
        '&pound;' => '&#163;',
        '&copy;' => '&#169;',
        '&ordf;' => '&#170;',
        '&laquo;' => '&#171;',
        '&reg;' => '&#174;',
        '&deg;' => '&#176;',
        '&plusmn;' => '&#177;',
        '&sup2;' => '&#178;',
        '&sup3;' => '&#179;',
        '&acute;' => '&#180;',
        '&sup1;' => '&#185;',
        '&ordm;' => '&#186;',
        '&raquo;' => '&#187;',
        '&frac14;' => '&#188;',
        '&frac12;' => '&#189;',
        '&frac34;' => '&#190;',
        '&rarr;'   => '&#8594;',
        '&larr;'   => '&#8592;',
        '&uarr;'   => '&#8593;',
        '&darr;'   => '&#8595;',
        '&trade;'   => '&#8482;',
        '&rsquo;'   => '&#8217;',
        '&rsquor;'   => '&#8217;',
        '&lsquo;'   => '&#8216;',
        '&lsquor;'   => '&#8218;',
        '&amp;'   => 'taxopressamp',
    ];

    if ($enity_code_as_key) {
        $character_set = array_flip($character_set);
    }

    return $character_set;
}

/**
 * Sanitize PublishPress Taxonomies text field
 *
 * @param string $content
 * @return string
 */
function taxopress_sanitize_text_field($content)
{

    if (is_array($content)) {
        return stripslashes_deep(array_map('taxopress_sanitize_text_field', $content));
    }

    $content = stripslashes_deep($content);

    $content = wp_kses_post($content);

    return $content;
}


/**
 * Enhanced sanitization function specifically for post types and post status arrays
 * to prevent SQL injection vulnerabilities
 *
 * @param array|string $data The data to sanitize
 * @return array|string Sanitized data
 */
function taxopress_sanitize_post_type_status($data)
{
    if (is_array($data)) {
        return array_map('sanitize_key', $data);
    }

    return sanitize_key($data);
}

/**
 * Dashboard items
 *
 * @param mixed $current
 *
 * @return array
 */
function taxopress_dashboard_options()
{

    $features = [];

    $features['st_taxonomies'] = [
        'label'        => esc_html__('Taxonomies', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to create new taxonomies and edit all the settings for each taxonomy.', 'simple-tags'),
        'option_key'   => 'active_taxonomies',
    ];

    $features['st_terms'] = [
        'label'        => esc_html__('Terms', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to search and edit all the terms on your site.', 'simple-tags'),
        'option_key'   => 'active_st_terms',
    ];

    $features['st_posts'] = [
        'label'        => esc_html__('Posts', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to search for terms and see all the posts attached to that term.', 'simple-tags'),
        'option_key'   => 'active_st_posts',
    ];

    $features['st_terms_display'] = [
        'label'        => esc_html__('Terms Display', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to create a customizable display of all the terms in one taxonomy.', 'simple-tags'),
        'option_key'   => 'active_terms_display',
    ];

    $features['st_post_tags'] = [
        'label'        => esc_html__('Terms for Current Post', 'simple-tags'),
        'description'  => esc_html__('This feature allows you create a customizable display of all the terms assigned to the current post.', 'simple-tags'),
        'option_key'   => 'active_post_tags',
    ];

    $features['st_related_posts'] = [
        'label'        => esc_html__('Related Posts', 'simple-tags'),
        'description'  => esc_html__('This feature creates a display of similar posts. If a post has the terms “WordPress” and “Website”, Related Posts will display other posts with those same terms.', 'simple-tags'),
        'option_key'   => 'active_related_posts_new',
    ];

    $features['st_autolinks'] = [
        'label'        => esc_html__('Auto Links', 'simple-tags'),
        'description'  => esc_html__('This feature automatically adds links to your chosen terms. If you have a term called “WordPress”, Auto Links finds the word “WordPress” in your content and add links to the archive page for that term.', 'simple-tags'),
        'option_key'   => 'active_auto_links',
    ];

    $features['st_autoterms'] = [
        'label'        => esc_html__('Auto Terms', 'simple-tags'),
        'description'  => esc_html__('Auto Terms can scan your content and automatically assign new and existing terms.', 'simple-tags'),
        'option_key'   => 'active_auto_terms',
    ];

    $features['st_mass_terms'] = [
        'label'        => esc_html__('Mass Edit Terms', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to quickly edit the terms attached to multiple posts at the same time.', 'simple-tags'),
        'option_key'   => 'active_mass_edit',
    ];

    $features['st_manage'] = [
        'label'        => esc_html__('Manage Terms', 'simple-tags'),
        'description'  => esc_html__('This feature allows you to add, rename, merge, and delete terms for any taxonomy.', 'simple-tags'),
        'option_key'   => 'active_manage',
    ];

    $features = apply_filters('taxopress_dashboard_features', $features);

    return $features;
}

/**
 * Check if current version of PublishPress Taxonomies is
 * pro version
 *
 * @return void
 */
function taxopress_is_pro_version()
{
    // Keep the legacy constant as a fallback for existing TaxoPress Pro integrations.
    return defined('PUBLISHPRESS_TAXONOMIES_PRO_VERSION') || defined('TAXOPRESS_PRO_VERSION');
}

/**
 * Case insensitive in_array function
 *
 * @param string $needle
 * @param array $haystack
 * @return bool
 */
function taxopress_in_array_i($needle, $haystack)
{
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

/**
 * Check if synonyms is enabled
 *
 * @return bool
 */
function taxopress_is_synonyms_enabled()
{
    return ((int) SimpleTags_Plugin::get_option_value('active_features_synonyms') === 1);
}

/**
 * Check if linked term is enabled
 *
 * @return bool
 */
function taxopress_is_linked_terms_enabled()
{
    return ((int) SimpleTags_Plugin::get_option_value('active_features_linked_terms') === 1);
}

/**
 * Get term synonyms
 *
 * @param string|integer $term
 * @param string $taxonomy
 * @return array  $term_synonyms
 */
function taxopress_get_term_synonyms($term, $taxonomy = '')
{
    $term_synonyms = [];

    if (!taxopress_is_synonyms_enabled()) {
        // simply return empty array if feature is disabled
        return $term_synonyms;
    }

    if ((int)$term > 0) {
        $term_synonyms = (array) get_term_meta($term, '_taxopress_term_synonyms', true);
    } else {
        $terms_object = get_term_by('name', esc_attr($term), $taxonomy);
        if (is_object($terms_object) && isset($terms_object->term_id)) {
            $term_synonyms = (array) get_term_meta($terms_object->term_id, '_taxopress_term_synonyms', true);
        }
    }
    $term_synonyms = array_filter($term_synonyms);

    return $term_synonyms;
}

/**
 * Get linked terms
 *
 * @param int|string $term_id
 * @param string $taxonomy
 * @param bool $term_object
 * @param string $linked_terms_type
 * @return array $linked_terms
 */
function taxopress_get_linked_terms($term_id, $taxonomy = '', $term_object = false, $linked_terms_type = 'main')
{
    global $wpdb;

    $linked_terms = [];

    if (!taxopress_is_pro_version() || !taxopress_is_linked_terms_enabled()) {
        // Simply return an empty array if the feature is disabled
        return $linked_terms;
    }

    $table_name = $wpdb->prefix . 'taxopress_linked_terms';

    if ($linked_terms_type == 'primary') {
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE term_id = %d",
            $term_id
        );
    } elseif ($linked_terms_type == 'secondary') {
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE linked_term_id = %d",
            $term_id
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE term_id = %d OR linked_term_id = %d",
            $term_id,
            $term_id
        );
    }

    $linked_terms = $wpdb->get_results($query);

    return $linked_terms;
}


/**
 * Find out which is a linked term in our data
 */
function taxopress_get_linked_term_data($linked_term_option, $term_id)
{

    if ((int)$linked_term_option->linked_term_id === (int)$term_id) {
        $taxopress_linked_term_id       = $linked_term_option->term_id;
        $taxopress_linked_term_name     = $linked_term_option->term_name;
        $taxopress_linked_term_taxonomy = $linked_term_option->term_taxonomy;
    } else {
        $taxopress_linked_term_id       = $linked_term_option->linked_term_id;
        $taxopress_linked_term_name     = $linked_term_option->linked_term_name;
        $taxopress_linked_term_taxonomy = $linked_term_option->linked_term_taxonomy;
    }

    $linked_term = (object) [
        'term_id'       => $taxopress_linked_term_id,
        'term_name'     => $taxopress_linked_term_name,
        'term_taxonomy' => $taxopress_linked_term_taxonomy
    ];

    return $linked_term;
}

/**
 * Add linked terms and it synonyms to list
 *
 * @param array $lists
 * @param string|integer $term
 * @param string $lists
 * @param bool $linked
 * @param bool $named_term
 *
 * @return array $term_object
 */
function taxopress_add_linked_term_options($lists, $term, $taxonomy, $linked = false, $named_term = false)
{

    if (!taxopress_is_linked_terms_enabled()) {
        // simply return $lists if feature is disabled
        return $lists;
    }

    if ((int)$term > 0) {
        $term_id = $term;
    } else {
        $terms_object = get_term_by('name', esc_attr($term), $taxonomy);
        if (is_object($terms_object) && isset($terms_object->term_id)) {
            $term_id = $terms_object->term_id;
        } else {
            $term_id = 0;
        }
    }

    if ($term_id > 0) {
        // get linked terms
        $linked_terms_type = SimpleTags_Plugin::get_option_value('linked_terms_type');
        $linked_terms = taxopress_get_linked_terms($term_id, $taxonomy, true, $linked_terms_type);
        if (!empty($linked_terms)) {
            if (!empty($linked_terms)) {
                foreach ($linked_terms as $linked_term) {
                    // skip this if term and linked term is not in the same category as linked terms is now category wide.
                    if ($linked_term->term_taxonomy !== $linked_term->linked_term_taxonomy) {
                        continue;
                    }
                    $linked_term_data = taxopress_get_linked_term_data($linked_term, $term_id);
                    $linked_term_name = stripslashes($linked_term_data->term_name);
                    $linked_term_id   = $linked_term_data->term_id;
                    if ($linked) {
                        $term_value = get_term_link($linked_term->term_id, $linked_term_data->term_taxonomy);
                        if (is_wp_error($term_value)) {
                            $term_value = '';
                        }
                    } elseif ($named_term) {
                        $term_value = $linked_term_name;
                    } else {
                        $term_value = $linked_term_id;
                    }

                    // add linked term
                    $lists[$linked_term_name] = $term_value;

                    // add linked term synonyms
                    $term_synonyms = taxopress_get_term_synonyms($linked_term_id);
                    if (!empty($term_synonyms)) {
                        foreach ($term_synonyms as $term_synonym) {
                            $lists[$term_synonym] = $term_value;
                        }
                    }
                }
            }
        }
    }

    return $lists;
}

/**
 * Fetch our PublishPress Taxonomies SuggestTerms option.
 * SuggestTerms screen has been removed but we need this function
 * to migrate the needed settings.
 *
 * @return mixed
 */
function taxopress_get_suggestterm_data()
{
    return array_filter((array)apply_filters(
        'taxopress_get_suggestterm_data',
        get_option('taxopress_suggestterms', []),
        get_current_blog_id()
    ));
}

function taxopress_get_all_wp_roles()
{
    global $wp_roles;

    if (!isset($wp_roles)) {
        $wp_roles = new \WP_Roles();
    }

    return $wp_roles->roles;
}

/**
 * Check if current user can manage PublishPress Taxonomies metabox
 */
function can_manage_taxopress_metabox($user_id = false)
{
    $can_manage = false;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $user = get_userdata($user_id);
    if (is_object($user) && isset($user->roles)) {
        foreach ($user->roles as $role_name) {
            if (!empty(SimpleTags_Plugin::get_option_value('enable_' . $role_name . '_metabox'))) {
                $can_manage = true;
                break;
            }
        }
    }

    return $can_manage;
}

/**
 * Check if current user can manage metabox taxonomy
 */
function can_manage_taxopress_metabox_taxonomy($taxonomy, $user_id = false, $preview_role = '')
{

    if (!empty($preview_role)) {
        $role_options = (array) SimpleTags_Plugin::get_option_value('enable_metabox_' . $preview_role . '');
        return in_array($taxonomy, $role_options, true);
    }

    $can_manage = false;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $user = get_userdata($user_id);
    if (is_object($user) && isset($user->roles)) {
        foreach ($user->roles as $role_name) {
            $role_options = (array) SimpleTags_Plugin::get_option_value('enable_metabox_' . $role_name . '');
            if (in_array($taxonomy, $role_options, true)) {
                $can_manage = true;
                break;
            }
        }
    }

    return $can_manage;
}

/**
 * Check if current user can edit (rename) PublishPress Taxonomies metabox labels.
 * Only administrators can edit labels.
 *
 * @param int|false $user_id
 * @return bool
 */
function can_edit_taxopress_metabox_labels($user_id = false)
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    // Check if user is an administrator
    return user_can($user_id, 'administrator');
}


/**
 * Get all the taxonomy removed for current user
 */
function taxopress_user_role_removed_taxonomy($user_id = false)
{

    $removed_taxonomies_tax = [];
    $removed_taxonomies_css = [];
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $user = get_userdata($user_id);
    if (is_object($user) && isset($user->roles)) {
        foreach ($user->roles as $role_name) {
            $role_options = (array) SimpleTags_Plugin::get_option_value('remove_taxonomy_metabox_' . $role_name . '');
            $role_options = array_filter($role_options);
            if (!empty($role_options)) {
                foreach ($role_options as $removed_tax) {
                    $removed_taxonomies_tax[] = $removed_tax;
                    if ($removed_tax == 'category') {
                        $removed_taxonomies_css[] = '#category-add-toggle, #categories, #categorydiv, #categorydivsb, th.column-categories, td.categories, #screen-options-wrap label[for=categorydiv-hide]';
                    } elseif ($removed_tax == 'post_tag') {
                        $removed_taxonomies_css[] = '#tags, #tagsdiv,#tagsdivsb,#tagsdiv-post_tag, th.column-tags, td.tags, #screen-options-wrap label[for=tagsdiv-post_tag-hide]';
                    } else {
                        $removed_taxonomies_css[] = "#{$removed_tax}, #{$removed_tax}div,#{$removed_tax}divsb,#tagsdiv-{$removed_tax}, th.column-{$removed_tax}, td.{$removed_tax}, #screen-options-wrap label[for=tagsdiv-{$removed_tax}-hide], #screen-options-wrap label[for={$removed_tax}div-hide]";
                    }
                }
            }
        }
    }

    $removed_taxonomies = [
        'taxonomies' => $removed_taxonomies_tax,
        'custom_css' => $removed_taxonomies_css
    ];

    return $removed_taxonomies;
}

/**
 * Get post statuses
 */
function taxopress_get_post_statuses()
{

    $post_statuses = get_post_stati(array(), 'objects');

    return $post_statuses;
}

/**
 * Check if the current user role matches the given role name
 *
 * @param string $role_name
 * @return bool
 */
function taxopress_is_current_user_role($role_name)
{
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        return in_array($role_name, (array) $user->roles);
    }
    return false;
}

/**
 * Check if the current user can manage specific metabox tabs
 *
 * @param array $tabs
 * @return array
 */
function can_manage_taxopress_metabox_tabs($tabs)
{
    $user = wp_get_current_user();
    $role_name = $user->roles[0] ?? false;

    if ($role_name) {
        $restrict_metabox = SimpleTags_Plugin::get_option_value('enable_restrict' . $role_name . '_metabox');
        if ($restrict_metabox && taxopress_is_current_user_role($role_name)) {
            $tabs['post_terms']['enabled'] = true;
            $tabs['suggest_local_terms']['enabled'] = true;
            $tabs['create_term']['enabled'] = false;
        }
    }

    return $tabs;
}
