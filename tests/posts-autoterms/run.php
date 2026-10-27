<?php
// Standalone request-boundary checks. The term engine is mocked, not live WordPress.
define('ABSPATH', __DIR__);
require dirname(__DIR__, 2) . '/inc/class.admin.autoterms-posts.php';

class WP_Error
{
    public $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class SimpleTags_Plugin
{
    public static $enabled = 1;
    public static function get_option_value($key) { return self::$enabled; }
}
class SimpleTags_Client_Autoterms
{
    public static $calls = [];
    public static $throws = false;
    public static function auto_terms_post(...$args)
    {
        if (self::$throws) { throw new RuntimeException('Engine error'); }
        self::$calls[] = $args;
        return true;
    }
}
$permissions = [];
$options = [];
$configurations = [
    1 => [
        'title' => 'Selected posts test',
        'taxonomy' => 'post_tag',
        'post_types' => ['post'],
        'post_status' => ['publish'],
        'autoterm_for_posts_screen' => 1,
        'autoterm_for_post' => 0,
        'post_replace_type' => 'append',
        'open_ai_api_key' => 'not-for-the-browser',
    ],
];
$posts = [42 => (object) ['ID' => 42, 'post_type' => 'post', 'post_status' => 'publish']];
function __($text, $domain) { return $text; }
function current_user_can($capability, ...$args) {
    global $permissions;
    return $permissions[$capability . ':' . ($args[0] ?? '')] ?? $permissions[$capability] ?? true;
}
function taxopress_get_autoterm_data() { global $configurations; return $configurations; }
function get_taxonomy($name) {
    return $name === 'missing' ? false : (object) ['cap' => (object) [
        'assign_terms' => 'assign_' . $name, 'edit_terms' => 'edit_' . $name,
    ]];
}
function is_object_in_taxonomy($type, $taxonomy) { return $taxonomy !== 'unattached'; }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function add_option($key, $value, ...$args) {
    global $options;
    if (isset($options[$key])) { return false; }
    $options[$key] = $value;
    return true;
}
function delete_option($key) { global $options; unset($options[$key]); }
function wp_generate_uuid4() { return uniqid('test', true); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function rejected($code) {
    $before = count(SimpleTags_Client_Autoterms::$calls);
    $result = TaxoPress_Autoterms_Posts::process(42, 1);
    check($result instanceof WP_Error && $result->code === $code, $code);
    check(count(SimpleTags_Client_Autoterms::$calls) === $before, 'Rejected request does not call engine');
}
check(count(TaxoPress_Autoterms_Posts::configurations('post')) === 1, 'Enabled configuration available');
check(TaxoPress_Autoterms_Posts::configurations('page') === [], 'Other post types excluded');
unset($configurations[1]['autoterm_for_posts_screen']);
check(TaxoPress_Autoterms_Posts::configurations('post') === [], 'Existing configurations default off');
$configurations[1]['autoterm_for_posts_screen'] = 1;
$result = TaxoPress_Autoterms_Posts::process(42, 1);
check($result === ['post_id' => 42, 'added' => true], 'Selected post processed independently of automatic save toggle');
check(SimpleTags_Client_Autoterms::$calls[0][0]->ID === 42, 'Only requested post passed to engine');
check(SimpleTags_Client_Autoterms::$calls[0][2]['replace_type'] === 'append', 'Post replacement setting preserved');
check(SimpleTags_Client_Autoterms::$calls[0][4] === 'save_posts', 'Post matching constraints reused');
check($options === [], 'Successful request releases lock');
$permissions['simple_tags'] = false;
rejected('permission_denied');
$permissions = ['edit_post:42' => false];
rejected('permission_denied');
$permissions = ['assign_post_tag' => false];
rejected('invalid_configuration');
$permissions = ['edit_post_tag' => false];
rejected('invalid_configuration');
$permissions = [];
$configurations[1]['additional_taxonomies'] = ['private_taxonomy'];
$permissions['assign_private_taxonomy'] = false;
rejected('invalid_configuration');
$permissions = [];
unset($configurations[1]['additional_taxonomies']);
$configurations[1]['autoterm_for_posts_screen'] = 0;
rejected('invalid_configuration');
$configurations[1]['autoterm_for_posts_screen'] = 1;
$posts[42]->post_status = 'draft';
rejected('invalid_status');
$posts[42]->post_status = 'trash';
rejected('invalid_post');
$posts[42]->post_status = 'publish';
SimpleTags_Plugin::$enabled = 0;
rejected('invalid_configuration');
SimpleTags_Plugin::$enabled = 1;
$options['taxopress_posts_autoterms_lock_42_1'] = ['token' => 'another', 'time' => time()];
rejected('already_running');
$options['taxopress_posts_autoterms_lock_42_1']['time'] = time() - 301;
check(!is_wp_error(TaxoPress_Autoterms_Posts::process(42, 1)), 'Expired lock can be recovered');
SimpleTags_Client_Autoterms::$throws = true;
check(TaxoPress_Autoterms_Posts::process(42, 1)->code === 'processing_failed', 'Engine exception returns a safe error');
check($options === [], 'Engine exception releases lock');

function check_ajax_referer($action, $field) {
    check($action === 'taxopress-run-selected-autoterms' && $field === 'nonce', 'Endpoint checks dedicated nonce');
    throw new RuntimeException('nonce-rejected');
}
$before = count(SimpleTags_Client_Autoterms::$calls);
$_SERVER['REQUEST_METHOD'] = 'POST';
try { TaxoPress_Autoterms_Posts::ajax(); } catch (RuntimeException $error) {
    check($error->getMessage() === 'nonce-rejected', 'Invalid nonce stops request');
}
check(count(SimpleTags_Client_Autoterms::$calls) === $before, 'Invalid nonce does not call engine');
define('STAGS_URL', 'https://example.test/plugins/simple-tags');
define('STAGS_VERSION', 'test');
$screen = (object) ['base' => 'edit', 'post_type' => 'post'];
$localized = [];
function get_current_screen() { global $screen; return $screen; }
function wp_enqueue_script(...$args) {}
function wp_localize_script($handle, $name, $data) { global $localized; $localized = $data; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'test-nonce'; }
TaxoPress_Autoterms_Posts::enqueue('post.php');
check($localized === [], 'Assets not loaded on unrelated screens');
TaxoPress_Autoterms_Posts::enqueue('edit.php');
check($localized['configurations'] === [['id' => 1, 'title' => 'Selected posts test']], 'Only IDs and titles sent to browser');
check(strpos(json_encode($localized), 'not-for-the-browser') === false, 'Credentials not sent to browser');
