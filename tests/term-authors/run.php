<?php
/** Offline attribution regression checks. No database or live site writes. */
define('ABSPATH', __DIR__);
define('STAGS_DIR', dirname(__DIR__, 2));
define('STAGS_URL', 'https://example.test/plugin');
define('STAGS_VERSION', 'test');
class WP_Error { public function __construct(public $code, public $message) {} }
class WP_List_Table { public function __construct($args = []) {} }
class WP_Terms_List_Table { public $callback_args = ['number' => 2]; }
class AuthorQueryDatabase {
    public $termmeta = 'wp_termmeta';
    public $users = 'wp_users';
    public $usermeta = 'wp_usermeta';
    public function get_blog_prefix() { return 'wp_'; }
    public function prepare($sql, ...$values) {
        if (isset($values[0]) && is_array($values[0])) { $values = $values[0]; }
        foreach ($values as $value) {
            $sql = preg_replace_callback('/%[sd]/', fn($match) => $match[0] === '%d' ? (string) (int) $value : "'" . str_replace("'", "''", (string) $value) . "'", $sql, 1);
        }
        return $sql;
    }
}
class SimpleTags_Plugin { public static function get_option_value($name) { return $GLOBALS['general_options'][$name] ?? 0; } }
function add_action($hook, $callback, ...$args) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter($hook, $callback, ...$args) { add_action($hook, $callback); }
function apply_filters($hook, $value, ...$args) { return $value; }
function get_current_blog_id() { return 1; }
function get_option($key, $default = []) { return $GLOBALS['options'][$key] ?? $default; }
function get_current_user_id() { return $GLOBALS['current_user']; }
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function is_user_member_of_blog($id, $blog = null) { return in_array($id, [1, 2, 3], true); }
function current_user_can($capability, $id = 0) { return $GLOBALS['allowed']; }
function get_term($id) { return $GLOBALS['terms'][$id] ?? null; }
function get_term_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function add_term_meta($id, $key, $value, $unique = false) {
    if ($unique && isset($GLOBALS['meta'][$id][$key])) { return false; }
    $GLOBALS['meta'][$id][$key] = $value;
    return true;
}
function update_term_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function delete_term_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid' && $action === 'taxopress-term-author'; }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function sanitize_key($value) { return $value; }
function __($text, ...$args) { return $text; }
function esc_html__($text, ...$args) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr__($text, ...$args) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_attr($text) { return esc_html($text); }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function is_admin() { return $GLOBALS['admin'] ?? true; }
function is_multisite() { return $GLOBALS['multisite'] ?? false; }
function register_term_meta($taxonomy, $key, $args) { $GLOBALS['registered_meta'] = $args; }
function get_taxonomies($args = [], $output = 'names') {
    $names = ['category' => 'category', 'post_tag' => 'post_tag', 'genre' => 'genre'];
    return $output === 'names' ? $names : array_map(fn($name) => (object) ['name' => $name], $names);
}
function taxopress_is_pro_version() { return false; }
function get_current_screen() { return $GLOBALS['screen']; }
function wp_enqueue_script(...$args) { $GLOBALS['enqueued'][] = $args; }
function wp_dropdown_users($args) {
    $GLOBALS['dropdown'] = $args;
    echo '<select id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['name']) . '" class="' . esc_attr($args['class'] ?? '') . '">';
    if (!empty($args['show_option_all'])) { echo '<option value="0">' . esc_html($args['show_option_all']) . '</option>'; }
    echo '<option value="' . esc_attr($args['option_none_value']) . '"' . ((string) $args['selected'] === (string) $args['option_none_value'] ? ' selected' : '') . '>' . esc_html($args['show_option_none']) . '</option>';
    foreach ($GLOBALS['users'] as $id => $user) {
        if (is_user_member_of_blog($id)) {
            echo '<option value="' . $id . '"' . ((string) $args['selected'] === (string) $id ? ' selected' : '') . '>' . esc_html($user->display_name) . '</option>';
        }
    }
    echo '</select>';
}
function wp_nonce_field($action, $name, $referer = true) { echo '<input type="hidden" name="' . $name . '" value="valid">'; }
require STAGS_DIR . '/inc/taxonomies-functions.php';
require STAGS_DIR . '/inc/term-authors.php';
require STAGS_DIR . '/inc/terms-table.php';

$checks = 0;
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    ++$GLOBALS['checks'];
}
function fixture() {
    $GLOBALS['options'] = [
        'taxopress_external_taxonomies' => ['category' => ['enable_term_authors' => 'true'], 'post_tag' => ['enable_term_authors' => 'false']],
        'taxopress_taxonomies' => ['genre' => ['enable_term_authors' => 'true']],
    ];
    $GLOBALS['current_user'] = 1;
    $GLOBALS['users'] = [1 => (object) ['display_name' => 'Creator'], 2 => (object) ['display_name' => '<Other & user>'], 3 => (object) ['display_name' => 'Member'], 4 => (object) ['display_name' => 'Other site']];
    $GLOBALS['allowed'] = true;
    $GLOBALS['general_options'] = [];
    $GLOBALS['admin'] = true;
    $GLOBALS['multisite'] = false;
    $GLOBALS['ajax'] = false;
    $GLOBALS['meta'] = [];
    $GLOBALS['terms'] = [10 => (object) ['term_id' => 10, 'taxonomy' => 'category'], 11 => (object) ['term_id' => 11, 'taxonomy' => 'post_tag']];
    $GLOBALS['enqueued'] = [];
    $_POST = $_REQUEST = [];
}
function request_author($author, $nonce = 'valid') {
    $_POST = ['taxopress_term_author' => $author, 'taxopress_term_author_nonce' => $nonce];
}
function author($id = 10) { return Taxopress_Term_Authors::get_author_id($id); }
fixture();
$meta_key = Taxopress_Term_Authors::META_KEY;
check(Taxopress_Term_Authors::is_enabled('category') && Taxopress_Term_Authors::is_enabled('genre'), 'Built-in and custom taxonomy settings enable authors');
check(!Taxopress_Term_Authors::is_enabled('post_tag') && !Taxopress_Term_Authors::is_enabled('missing'), 'False and absent settings keep authors off');
Taxopress_Term_Authors::register_meta();
check($registered_meta['type'] === 'integer' && $registered_meta['single'] && !$registered_meta['show_in_rest'], 'Attribution is a single integer and is not exposed through public REST');
Taxopress_Term_Authors::register_admin_hooks();
check(isset($hooks['category_add_form_fields'], $hooks['genre_edit_form_fields']) && !isset($hooks['post_tag_add_form_fields']), 'Native author controls only hook enabled taxonomies');
check(author() === 0, 'Existing terms are not retroactively attributed');
Taxopress_Term_Authors::created_term(10, 10, 'category');
check(author() === 1, 'New terms are attributed to the current site user');
Taxopress_Term_Authors::created_term(11, 11, 'post_tag');
check(author(11) === 0, 'Disabled taxonomies do not acquire author metadata');
$current_user = 0;
Taxopress_Term_Authors::created_term(12, 12, 'genre');
check(author(12) === 0, 'Unauthenticated background jobs leave attribution unassigned');
$current_user = 4;
Taxopress_Term_Authors::created_term(13, 13, 'category');
check(author(13) === 0, 'A user from another site is not used as the default author');
$current_user = 1;
request_author('2');
Taxopress_Term_Authors::created_term(14, 14, 'category');
check(author(14) === 2, 'New term form can select a different author');
request_author('0');
Taxopress_Term_Authors::created_term(15, 15, 'category');
check(author(15) === 0, 'Explicit No author prevents automatic attribution');
$_POST = [];
$meta[16][$meta_key] = 2;
Taxopress_Term_Authors::created_term(16, 16, 'category');
check(author(16) === 2, 'Creation hooks preserve attribution supplied by an integration');
request_author('2');
Taxopress_Term_Authors::edited_term(10, 10, 'category');
check(author() === 2, 'Normal Edit and native Quick Edit can reassign an author');
$_POST = [];
Taxopress_Term_Authors::edited_term(10, 10, 'category');
check(author() === 2, 'Bulk parent changes and integrations preserve authors without author fields');
foreach (['invalid', ''] as $nonce) {
    request_author('1', $nonce);
    Taxopress_Term_Authors::save_from_request(10, 'category');
    check(author() === 2, 'Invalid or absent author nonce cannot change attribution');
}
request_author('1'); unset($_POST['taxopress_term_author_nonce']);
Taxopress_Term_Authors::save_from_request(10, 'category');
check(author() === 2, 'Forged author field without a nonce cannot change attribution');
foreach (['999', '4', '-2', '2.5', 'text', ['2']] as $invalid) {
    request_author($invalid);
    Taxopress_Term_Authors::save_from_request(10, 'category');
    check(author() === 2, 'Reject invalid, deleted, nonmember, negative, fractional or array author IDs');
}
request_author('1'); $allowed = false;
Taxopress_Term_Authors::save_from_request(10, 'category');
check(author() === 2 && !Taxopress_Term_Authors::can_edit_meta(false, $meta_key, 10), 'Term editing capability is required');
$allowed = true;
request_author('1');
Taxopress_Term_Authors::save_from_request(10, 'post_tag');
check(author() === 2, 'Disabled taxonomy rejects submitted author changes');
check(Taxopress_Term_Authors::can_edit_meta(false, $meta_key, 10) && !Taxopress_Term_Authors::can_edit_meta(false, $meta_key, 11), 'Metadata permissions follow term capability and taxonomy setting');
$ajax = true; request_author('1'); $_POST['action'] = 'taxopress_terms_inline_save_term';
Taxopress_Term_Authors::edited_term(10, 10, 'category');
check(author() === 2, 'Custom Quick Edit defers author saving until after a taxonomy move');
$terms[10]->taxonomy = 'genre';
Taxopress_Term_Authors::save_from_request(10, 'genre');
check(author() === 1, 'Custom Quick Edit saves after final destination is known');
$terms[10]->taxonomy = 'category';
request_author('2');
Taxopress_Term_Authors::save_from_request(10, 'genre');
check(author() === 1, 'A mismatched taxonomy cannot bypass its author setting');
request_author('0');
Taxopress_Term_Authors::save_from_request(10, 'category');
check(author() === 0 && !isset($meta[10][$meta_key]), 'No author removes the assignment');
$meta[10][$meta_key] = 2;
check(str_contains(Taxopress_Term_Authors::column(10, 'category'), '&lt;Other &amp; user&gt;'), 'User display names are escaped in list columns');
unset($users[2]);
check(author() === 0, 'Deleted accounts display as unassigned');
$meta[10][$meta_key] = 4;
check(author() === 0, 'Removed site members display as unassigned');
$meta[10][$meta_key] = 1;
$options['taxopress_external_taxonomies']['category']['enable_term_authors'] = 'false';
request_author('0'); Taxopress_Term_Authors::save_from_request(10, 'category');
check($meta[10][$meta_key] === 1 && Taxopress_Term_Authors::column(10, 'category') === '&mdash;', 'Turning off authors hides but preserves attribution');
$options['taxopress_external_taxonomies']['category']['enable_term_authors'] = 'true';
check(author() === 1, 'Re-enabling authors restores attribution');
$table = new Taxopress_Terms_List();
$_REQUEST = ['terms_filter_taxonomy' => 'category'];
check(isset($table->get_columns()[Taxopress_Term_Authors::COLUMN]), 'Terms screen shows Author for an enabled taxonomy');
$_REQUEST = ['terms_filter_taxonomy' => 'post_tag'];
check(!isset($table->get_columns()[Taxopress_Term_Authors::COLUMN]), 'Terms screen hides Author for a disabled taxonomy');
$_REQUEST = [];
check(isset($table->get_columns()[Taxopress_Term_Authors::COLUMN]), 'Mixed taxonomy Terms screen includes authors');
foreach ([['term', 'term', 'category', true], ['edit-tags', 'edit-post_tag', 'post_tag', false], ['edit-tags', 'edit-category', 'category', true], ['taxonomies_page_st_terms', 'taxonomies_page_st_terms', '', true], ['dashboard', 'dashboard', '', false]] as [$base, $id, $taxonomy, $expected]) {
    $screen = (object) compact('base', 'id', 'taxonomy');
    $enqueued = [];
    Taxopress_Term_Authors::enqueue_scripts();
    check(!empty($enqueued) === $expected, 'Assets are scoped to term author screens');
}
ob_start(); Taxopress_Term_Authors::add_field('category'); $html = ob_get_clean();
check($dropdown['selected'] === 1 && str_contains($html, 'taxopress_term_author_nonce'), 'Add form selects the current user and includes its author nonce');
ob_start(); Taxopress_Term_Authors::edit_field($terms[10]); ob_end_clean();
check($dropdown['selected'] === 1, 'Edit form preselects saved attribution');
fixture();
$general_options['enable_term_authors'] = 1;
check(Taxopress_Term_Authors::is_enabled('unconfigured'), 'Global setting enables taxonomies without individual choices');
check(Taxopress_Term_Authors::is_enabled('category') && !Taxopress_Term_Authors::is_enabled('post_tag'), 'Existing individual choices override a global enable');
$options['taxopress_external_taxonomies']['category']['enable_term_authors'] = 'default';
check(Taxopress_Term_Authors::is_enabled('category'), 'Use global setting follows the global enable');
$general_options['enable_term_authors'] = 0;
check(!Taxopress_Term_Authors::is_enabled('category') && Taxopress_Term_Authors::is_enabled('genre'), 'Individual enable survives a global disable');
foreach ([true => '1', false => '0', 'true' => '1', 'false' => '0', 'default' => 'default', 'invalid' => 'default'] as $input => $expected) {
    check(Taxopress_Term_Authors::normalize_override($input) === $expected, 'Normalize persisted taxonomy choices');
}
foreach ([null, [], 'unexpected'] as $invalid) { check(Taxopress_Term_Authors::normalize_override($invalid) === 'default', 'Invalid choices follow global instead of enabling authors'); }
foreach (['none' => 'none', '2' => '2', '002' => '2', '4' => '', '-1' => '', '2 OR 1=1' => '', '0' => ''] as $input => $expected) {
    $_REQUEST = ['taxopress_author' => (string) $input];
    check(Taxopress_Term_Authors::selected_author() === $expected, 'Author filter validates and canonicalizes IDs');
}
$_REQUEST = ['taxopress_author' => ['2']];
check(Taxopress_Term_Authors::selected_author() === '', 'Array author filters are ignored safely');
$_REQUEST = ['taxopress_author' => '2'];
$options['taxopress_external_taxonomies']['category']['enable_term_authors'] = '1';
$wpdb = new AuthorQueryDatabase();
$screen = (object) ['base' => 'edit-tags', 'taxonomy' => 'category'];
$wp_list_table = new WP_Terms_List_Table();
Taxopress_Term_Authors::prepare_native_filter();
check($_REQUEST['orderby'] === 'name', 'Native filtering selects flat list rendering before core prepares the table');
$args = ['page' => 2, 'number' => 2, 'offset' => 2, 'orderby' => 'name'];
unset($wp_list_table);
$filtered = Taxopress_Term_Authors::native_query_args($args, ['category']);
check($filtered['number'] === 2 && $filtered['offset'] === 2 && $filtered['taxopress_term_author_filter'] === '2', 'Native filtering works without a global list-table instance');
$_REQUEST['orderby'] = 'slug';
Taxopress_Term_Authors::prepare_native_filter();
check($_REQUEST['orderby'] === 'slug', 'Explicit native sorting is preserved');
$parent_args = ['fields' => 'all', 'hierarchical' => true];
check(Taxopress_Term_Authors::native_query_args($parent_args, ['category']) === $parent_args, 'Parent dropdown queries are not restricted');
check(Taxopress_Term_Authors::native_query_args(['number' => 20, 'fields' => 'all'], ['category' => 'category'])['taxopress_term_author_filter'] === '2', 'Native paginated queries accept keyed taxonomy arrays without a page argument');
$count = Taxopress_Term_Authors::native_query_args(['fields' => 'count'], ['category']);
check($count['taxopress_term_author_filter'] === '2', 'Native count uses the same author restriction');
check(Taxopress_Term_Authors::native_query_args($args, ['post_tag']) === $args, 'Other taxonomies on the same page remain unaffected');
$screen->taxonomy = 'genre';
check(Taxopress_Term_Authors::native_query_args($args, ['genre'])['taxopress_term_author_filter'] === '2', 'Native custom taxonomy lists support author filters');
$screen->base = 'dashboard';
check(Taxopress_Term_Authors::native_query_args($args, ['genre']) === $args, 'Unrelated screens remain unaffected');
$screen->base = 'edit-tags'; $admin = false;
check(Taxopress_Term_Authors::native_query_args($args, ['genre']) === $args, 'Frontend queries remain unaffected');
$admin = true; $ajax = true;
check(Taxopress_Term_Authors::native_query_args($args, ['genre']) === $args, 'Ajax parent search and inline saves remain unaffected');
$ajax = false;
ob_start(); Taxopress_Term_Authors::filter_dropdown('author-filter'); $html = ob_get_clean();
check(str_contains($html, 'All authors') && str_contains($html, 'No author') && $dropdown['selected'] === '2', 'Dropdown provides All authors and No author and retains selection');
$_REQUEST = ['taxopress_author' => 'none'];
ob_start(); Taxopress_Term_Authors::filter_dropdown('author-filter'); $html = ob_get_clean();
check(str_contains($html, 'value="none" selected'), 'No author remains selected after reload');
$sql_fixture = ['assigned' => Taxopress_Term_Authors::author_where('2'), 'unassigned' => Taxopress_Term_Authors::author_where('none')];
$multisite = true;
$sql_fixture['unassigned_multisite'] = Taxopress_Term_Authors::author_where('none');
$multisite = false;
check(Taxopress_Term_Authors::filter_clauses(['where' => '1=1'], ['category'], [])['where'] === '1=1', 'Unmarked term queries are never filtered');
check(Taxopress_Term_Authors::filter_clauses(['where' => '1=1'], ['category'], ['taxopress_term_author_filter' => "2' OR 1=1"])['where'] === '1=1', 'Malformed query markers cannot alter SQL');
check(Taxopress_Term_Authors::filter_clauses(['where' => '1=1'], ['category'], ['taxopress_term_author_filter' => '2'])['where'] === '1=1 AND ' . $sql_fixture['assigned'], 'Marked custom and native queries receive the same SQL restriction');
echo "$checks term author checks passed.\n";
