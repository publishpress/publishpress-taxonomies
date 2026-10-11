<?php
/** Loopback-only integrated-plugin harness; never deploy in wp-content. */
require_once __DIR__ . '/bootstrap.php';
$token = getenv('TAXOPRESS_TEST_HTTP_TOKEN');
if (PHP_SAPI !== 'cli-server' || !$token || strlen($token) < 16
    || !isset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_TAXOPRESS_TEST_TOKEN'])
    || $_SERVER['REMOTE_ADDR'] !== '127.0.0.1'
    || !preg_match('/\A127\.0\.0\.1:[0-9]+\z/', $_SERVER['HTTP_HOST'])
    || !hash_equals($token, $_SERVER['HTTP_X_TAXOPRESS_TEST_TOKEN'])) {
    http_response_code(403);
    exit;
}
$root = taxopress_transfer_test_root();
$plugin_dir = taxopress_transfer_test_plugin_dir();
define('WP_ADMIN', true);
define('DISABLE_WP_CRON', true);
require_once $root . '/wp-includes/plugin.php';
// DISABLE_WP_CRON stops spawning, not scheduling during plugin initialization.
add_filter('pre_schedule_event', '__return_false', PHP_INT_MAX);
add_filter('pre_reschedule_event', '__return_false', PHP_INT_MAX);
// Admin constructor otherwise upgrades the installed 3.x options while merely
// rendering the external checkout. Pretend current version in THIS request only.
add_filter('pre_option_simpletags-version', function () { return '4.0.0'; }, PHP_INT_MAX);
add_action('admin_init', function () {
    throw new RuntimeException('Harness must not run admin_init migrations, installers, or activation redirects.');
}, -PHP_INT_MAX);
// Isolate all installed plugins in THIS request only: third-party schedulers
// otherwise persist options during bootstrap. Never update active_plugins.
add_filter('option_active_plugins', '__return_empty_array', PHP_INT_MAX);
add_filter('pre_http_request', function () {
    return new WP_Error('taxopress_test_network_disabled', 'No external HTTP in local tests.');
}, PHP_INT_MAX);
add_action('muplugins_loaded', function () use ($plugin_dir) {
    if (class_exists('SimpleTags_Plugin', false)) {
        throw new RuntimeException('An existing plugin loaded legacy classes before the isolated checkout.');
    }
    require_once $plugin_dir . '/simple-tags.php';
}, 0);
require_once $root . '/wp-load.php';
taxopress_transfer_test_guard_runtime();
require_once ABSPATH . 'wp-admin/includes/admin.php';
// Legacy selectors normally persist preferences during render. Keep test renders
// read-only without changing the product implementation or stored preferences.
foreach (array('add-terms', 'remove-terms', 'rename-terms', 'merge-terms', 'delete-unuused-terms') as $tab) {
    foreach (array('taxo', 'cpt') as $field) {
        add_filter('pre_update_option_' . $tab . '_' . $field, function ($value, $old) { return $old; }, 999, 2);
    }
}
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID'));
if (!$admins) { http_response_code(503); exit; }
wp_set_current_user(isset($_GET['deny']) ? 0 : (int) $admins[0]);
if (isset($_GET['deny_cap']) && is_string($_GET['deny_cap'])) {
    $key = $_GET['deny_cap'];
    $taxonomy = get_taxonomy('post_tag');
    $caps = array('simple_tags' => 'simple_tags', 'manage_terms' => $taxonomy->cap->manage_terms,
        'edit_terms' => $taxonomy->cap->edit_terms, 'assign_terms' => $taxonomy->cap->assign_terms);
    if (!isset($caps[$key])) { http_response_code(400); exit; }
    $denied_cap = $caps[$key];
    add_filter('map_meta_cap', function ($mapped, $requested) use ($denied_cap) {
        return $requested === $denied_cap ? array('do_not_allow') : $mapped;
    }, PHP_INT_MAX, 2);
    add_filter('user_has_cap', function ($allcaps) use ($denied_cap) {
        $allcaps[$denied_cap] = false;
        return $allcaps;
    }, 999);
}
if (isset($_GET['deny_edit']) && is_string($_GET['deny_edit'])) {
    $deny_id = (int) $_GET['deny_edit'];
    $post = get_post($deny_id);
    if (!$post || strpos($post->post_title, 'ttt-http-') !== 0) { http_response_code(400); exit; }
    add_filter('map_meta_cap', function ($caps, $cap, $user, $args) use ($deny_id) {
        return $cap === 'edit_post' && isset($args[0]) && (int) $args[0] === $deny_id ? array('do_not_allow') : $caps;
    }, 999, 4);
}
if (isset($_GET['feature_disabled'])) {
    SimpleTags_Plugin::$options['active_manage'] = 0;
}
if (isset($_GET['registration_disabled'])) {
    SimpleTags_Plugin::$options['active_manage'] = 0;
    remove_action('admin_post_taxopress_import_tags', array('TaxoPress_Tag_Transfer', 'import'));
    remove_action('admin_post_taxopress_export_tags', array('TaxoPress_Tag_Transfer', 'export'));
    TaxoPress_Tag_Transfer::register();
}
if (isset($_REQUEST['action'])) {
    $action = is_string($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
    if (!in_array($action, array('taxopress_import_tags', 'taxopress_export_tags'), true)) { http_response_code(400); exit; }
    if (!has_action('admin_post_' . $action)) { http_response_code(500); exit; }
    do_action('admin_post_' . $action);
    exit;
}
if (isset($_GET['view'])) {
    $_GET['page'] = 'st_manage';
    $GLOBALS['pagenow'] = 'admin.php';
    set_current_screen('taxonomies_page_st_manage');
    header('Content-Type: text/html; charset=UTF-8');
    SimpleTags_Admin_Manage::get_instance()->page_manage_tags();
    exit;
}
if (isset($_GET['adapter_view'])) {
    header('Content-Type: text/html; charset=UTF-8');
    TaxoPress_Tag_Transfer::render($_GET['adapter_view'] !== 'inactive');
    exit;
}
$manage = new ReflectionClass('SimpleTags_Admin_Manage');
header('Content-Type: application/json');
echo wp_json_encode(array('nonce' => wp_create_nonce('taxopress_import_tags'),
    'export_nonce' => wp_create_nonce('taxopress_export_tags'), 'legacy_nonce' => wp_create_nonce('simpletags_admin'),
    'result' => get_transient('taxopress_tags_result_' . get_current_user_id()),
    'version' => STAGS_VERSION, 'manage_source' => $manage->getFileName(),
    'source_hashes' => array('service' => hash_file('sha256', $plugin_dir . '/inc/class-taxopress-tag-csv-service.php'),
        'adapter' => hash_file('sha256', $plugin_dir . '/inc/class-taxopress-tag-transfer.php'),
        'manage' => hash_file('sha256', $manage->getFileName())),
    'import_registered' => (bool) has_action('admin_post_taxopress_import_tags'),
    'export_registered' => (bool) has_action('admin_post_taxopress_export_tags'),
    'simple_tags' => current_user_can('simple_tags')));
