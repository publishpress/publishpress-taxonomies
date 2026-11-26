<?php
/** Run with WP-CLI, --skip-plugins --skip-themes, on an explicitly opted-in local site. */
if (!defined('WP_CLI') || !WP_CLI || getenv('TAXOPRESS_TERM_AUTHOR_TESTS') !== '1'
    || !in_array(explode(':', DB_HOST)[0], ['localhost', '127.0.0.1', 'mysql'], true)) {
    throw new RuntimeException('Term author integration tests require an opted-in local WordPress site.');
}
$root = dirname(__DIR__, 2);
if (class_exists('Taxopress_Term_Authors') || class_exists('SimpleTags_Plugin')) {
    throw new RuntimeException('Use --skip-plugins --skip-themes to isolate this test.');
}
define('STAGS_DIR', $root);
define('STAGS_URL', 'https://example.test/plugin');
define('STAGS_VERSION', 'test');
define('STAGS_OPTIONS_NAME', 'simpletags');
require $root . '/inc/class.plugin.php';
require $root . '/inc/taxonomies-functions.php';
require $root . '/inc/functions.inc.php';
require $root . '/inc/term-authors.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-terms-list-table.php';
require $root . '/inc/terms-table.php';
add_filter('pre_http_request', fn() => new WP_Error('test_no_http', 'External HTTP is disabled during tests.'));
add_filter('pre_wp_mail', '__return_true');
$suffix = strtolower(wp_generate_password(6, false, false));
$taxonomy = 'tpa_' . $suffix;
$custom_taxonomy = 'tpa_flat_' . $suffix;
$terms = [];
$created_users = [];
$settings = [];
$checks = 0;
$previous_user = get_current_user_id();
$previous_request = $_REQUEST;
$previous_post = $_POST;
$previous_screen = get_current_screen();
$previous_options = SimpleTags_Plugin::$options;
$override = static function () use (&$settings) { return $settings; };
add_filter('taxopress_get_taxonomy_data', $override);
add_filter('taxopress_get_extername_taxonomy_data', '__return_empty_array');
$check = static function ($condition, $message) use (&$checks) {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
$insert = static function ($name, $tax, $args = []) use (&$terms) {
    $result = wp_insert_term($name, $tax, $args);
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    $terms[] = [$result['term_id'], $tax];
    return (int) $result['term_id'];
};
try {
    $administrator = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$administrator) { throw new RuntimeException('An existing local administrator is required.'); }
    wp_set_current_user((int) $administrator[0]);
    $other_author = wp_insert_user(['user_login' => 'tpa_author_' . $suffix, 'user_pass' => wp_generate_password(), 'role' => 'author']);
    if (is_wp_error($other_author)) { throw new RuntimeException($other_author->get_error_message()); }
    $created_users[] = $other_author;
    register_taxonomy($taxonomy, 'post', ['public' => true, 'show_ui' => true, 'hierarchical' => true]);
    register_taxonomy($custom_taxonomy, 'post', ['public' => true, 'show_ui' => true]);
    $settings = [$taxonomy => ['enable_term_authors' => 'default'], $custom_taxonomy => ['enable_term_authors' => '1']];
    SimpleTags_Plugin::$options = ['enable_term_authors' => 1];
    Taxopress_Term_Authors::register();
    Taxopress_Term_Authors::register_meta();
    $parent = $insert('Parent ' . $suffix, $taxonomy);
    $child = $insert('Child ' . $suffix, $taxonomy, ['parent' => $parent]);
    $second = $insert('Second ' . $suffix, $taxonomy);
    $third = $insert('Third ' . $suffix, $taxonomy);
    $flat = $insert('Custom ' . $suffix, $custom_taxonomy);
    foreach ([$child, $second, $third, $flat] as $id) { update_term_meta($id, Taxopress_Term_Authors::META_KEY, $other_author); }
    add_term_meta($child, Taxopress_Term_Authors::META_KEY, $other_author);
    $check(Taxopress_Term_Authors::get_author_id($parent) === get_current_user_id(), 'Creation hook must attribute new terms to the current user');
    wp_set_current_user(0);
    $background = $insert('Background ' . $suffix, $taxonomy);
    $check(Taxopress_Term_Authors::get_author_id($background) === 0, 'Background terms must remain unassigned');
    wp_set_current_user((int) $administrator[0]);
    $_REQUEST = ['taxopress_author' => (string) $other_author];
    $_POST = [];
    $screen = WP_Screen::get('edit-' . $taxonomy);
    $screen->taxonomy = $taxonomy;
    $screen->post_type = 'post';
    set_current_screen($screen);
    // Match the page load lifecycle; author filtering must not require a global table object.
    Taxopress_Term_Authors::prepare_native_filter();
    add_filter('get_terms_args', 'taxopress_get_terms_args', 5, 2);
    add_filter('get_terms', 'taxopress_filter_terms', 5, 4);
    add_filter('edit_' . $taxonomy . '_per_page', static fn() => 2);
    $wp_list_table = new WP_Terms_List_Table(['screen' => $screen]);
    $wp_list_table->prepare_items();
    $check(count($wp_list_table->items) === 2, 'Native hierarchical author filter must paginate');
    $check((int) $wp_list_table->get_pagination_arg('total_items') === 3, 'Native count must match all matching terms');
    $check(in_array($child, wp_list_pluck($wp_list_table->items, 'term_id'), true), 'Matching child must be present without its parent');
    ob_start(); $wp_list_table->display_rows_or_placeholder(); $rows = ob_get_clean();
    $check(str_contains($rows, 'tag-' . $child) && !str_contains($rows, 'tag-' . $parent . '"'), 'Native rows must render a matching child without its nonmatching parent');
    $_REQUEST['paged'] = 2;
    $wp_list_table->prepare_items();
    $check(count($wp_list_table->items) === 1, 'Second native page must contain only the remaining term');
    $parents = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'hierarchical' => true]);
    $check(count($parents) === 5, 'Parent term queries must remain unfiltered');
    $_REQUEST = ['taxopress_author' => 'none'];
    Taxopress_Term_Authors::prepare_native_filter();
    $wp_list_table->prepare_items();
    $check(wp_list_pluck($wp_list_table->items, 'term_id') === [$background], 'Native No author must return only unassigned terms');
    update_term_meta($background, Taxopress_Term_Authors::META_KEY, '999999999');
    $wp_list_table->prepare_items();
    $check(wp_list_pluck($wp_list_table->items, 'term_id') === [$background], 'No author must include references to deleted users');
    $_REQUEST = ['page' => 'st_terms', 'terms_filter_taxonomy' => $taxonomy, 'taxonomy_type' => 'all', 'taxopress_author' => (string) $other_author];
    set_current_screen('taxonomies_page_st_terms');
    add_filter('st_terms_per_page', static fn() => 2);
    $table = new Taxopress_Terms_List();
    $check(count($table->get_all_terms()) === 2 && $table->record_count() === 3, 'TaxoPress list and count must filter before pagination');
    $_REQUEST['paged'] = 2;
    $check(count($table->get_all_terms()) === 1, 'TaxoPress pagination must use the filtered result set');
    unset($_REQUEST['paged']);
    $_REQUEST['s'] = 'Child';
    $check(wp_list_pluck($table->get_all_terms(), 'term_id') === [$child] && $table->record_count() === 1, 'Search and author filters must combine in rows and counts');
    unset($_REQUEST['s']);
    $settings[$taxonomy]['orderby'] = 'taxopress_term_order';
    $settings[$taxonomy]['order'] = 'asc';
    $manual = [$third, $parent, $second, $child, $background];
    add_filter('pre_option_taxopress_term_order_' . $taxonomy, static fn() => $manual);
    $check(wp_list_pluck($table->get_all_terms(), 'term_id') === [$third, $second], 'Manual ordering must preserve author filtering');
    $_REQUEST['terms_filter_taxonomy'] = $custom_taxonomy;
    $check(wp_list_pluck($table->get_all_terms(), 'term_id') === [$flat] && $table->record_count() === 1, 'Custom taxonomy author filtering must work');
    $_REQUEST['terms_filter_taxonomy'] = $taxonomy;
    $_REQUEST['taxopress_author'] = 'none';
    $check(wp_list_pluck($table->get_all_terms(), 'term_id') === [$background] && $table->record_count() === 1, 'No author must use consistent rows and counts');
    $_POST = ['taxopress_term_author' => (string) $other_author, 'taxopress_term_author_nonce' => wp_create_nonce('taxopress-term-author')];
    Taxopress_Term_Authors::save_from_request($background, $taxonomy);
    $check(Taxopress_Term_Authors::get_author_id($background) === $other_author, 'Nonce-verified save must persist author metadata');
    $check($table->record_count() === 0, 'Cached author counts must refresh immediately after reassignment');
    $_POST = [];
    $settings[$taxonomy]['enable_term_authors'] = '0';
    $check(!Taxopress_Term_Authors::is_enabled($taxonomy), 'Individual disable must override global enable');
    SimpleTags_Plugin::$options['enable_term_authors'] = 0;
    $check(Taxopress_Term_Authors::is_enabled($custom_taxonomy), 'Individual enable must override global disable');
    echo "$checks WordPress integration checks passed.\n";
} finally {
    $_POST = [];
    $_REQUEST = [];
    foreach (array_reverse($terms) as [$id, $tax]) { wp_delete_term($id, $tax); }
    foreach ($created_users as $id) { wp_delete_user($id); }
    unregister_taxonomy($taxonomy);
    unregister_taxonomy($custom_taxonomy);
    remove_filter('taxopress_get_taxonomy_data', $override);
    remove_filter('taxopress_get_extername_taxonomy_data', '__return_empty_array');
    SimpleTags_Plugin::$options = $previous_options;
    wp_set_current_user($previous_user);
    $_POST = $previous_post;
    $_REQUEST = $previous_request;
    set_current_screen($previous_screen);
}
