<?php
/** Safety and source selection shared by local-only tag-transfer tests. */
function taxopress_transfer_test_root() {
    $root = getenv('TAXOPRESS_TEST_WP_ROOT');
    if (getenv('TAXOPRESS_TEST_LOCAL') !== '1' || !$root || !is_dir($root)) {
        throw new RuntimeException('Set TAXOPRESS_TEST_LOCAL=1 and TAXOPRESS_TEST_WP_ROOT to an explicit disposable local site.');
    }
    return realpath($root);
}
function taxopress_transfer_test_plugin_dir() {
    $dir = getenv('TAXOPRESS_TEST_PLUGIN_DIR');
    $dir = $dir ? realpath($dir) : realpath(dirname(__DIR__, 2));
    if (!$dir || !is_file($dir . '/simple-tags.php')) {
        throw new RuntimeException('TAXOPRESS_TEST_PLUGIN_DIR must identify the integrated checkout, not standalone Tag CSV Transfer.');
    }
    if (defined('WP_PLUGIN_DIR') && realpath(WP_PLUGIN_DIR)
        && strpos($dir . '/', realpath(WP_PLUGIN_DIR) . '/') === 0) {
        throw new RuntimeException('Refusing a checkout under the active plugin directory. Use an isolated /tmp copy or external worktree.');
    }
    return $dir;
}
function taxopress_transfer_test_guard_runtime() {
    if (!defined('ABSPATH') || realpath(ABSPATH) !== taxopress_transfer_test_root()) {
        throw new RuntimeException('WordPress root does not match explicitly approved local root.');
    }
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    if (!$host || !preg_match('/(?:\A(?:localhost|127\.0\.0\.1|\[?::1\]?)\z|\.(?:local|test)\z)/i', $host)) {
        throw new RuntimeException('Refusing a site whose home URL is not localhost, .local, or .test.');
    }
    // No external requests from test code or plugin hooks.
    add_filter('pre_http_request', function ($response, $args, $url) {
        return new WP_Error('taxopress_test_network_disabled', 'External HTTP is disabled by the local regression harness.');
    }, PHP_INT_MAX, 3);
}
function taxopress_transfer_test_load_service() {
    $dir = taxopress_transfer_test_plugin_dir();
    $file = $dir . '/inc/class-taxopress-tag-csv-service.php';
    if (!is_file($file)) {
        throw new RuntimeException('Integrated service missing: ' . $file);
    }
    if (class_exists('TaxoPress_Tag_CSV_Service', false)) {
        $reflection = new ReflectionClass('TaxoPress_Tag_CSV_Service');
        if (realpath($reflection->getFileName()) !== realpath($file)) {
            throw new RuntimeException('Another checkout already loaded TaxoPress_Tag_CSV_Service.');
        }
    } else {
        require_once $file;
    }
    echo 'SERVICE_SOURCE=' . realpath($file) . ' SHA256=' . hash_file('sha256', $file) . "\n";
}
