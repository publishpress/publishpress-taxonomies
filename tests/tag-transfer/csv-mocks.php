<?php
/** Standalone parser regression: real core formatting, isolated posts, no wp-load/DB. */
if (PHP_SAPI !== 'cli' || defined('ABSPATH') || isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('Run standalone PHP, not wp eval-file.');
}
require_once __DIR__ . '/bootstrap.php';
define('ABSPATH', taxopress_transfer_test_root() . '/');
require_once ABSPATH . 'wp-includes/plugin.php';
require_once ABSPATH . 'wp-includes/class-wp-error.php';
if (is_file(ABSPATH . 'wp-includes/utf8.php')) {
    require_once ABSPATH . 'wp-includes/utf8.php';
}
require_once ABSPATH . 'wp-includes/formatting.php';
add_filter('sanitize_title', 'sanitize_title_with_dashes', 10, 3);
function __($message, $domain = null) { return $message; }
function get_locale() { return 'en_US'; }
function mbstring_binary_safe_encoding($reset = false) {
    if ((int) ini_get('mbstring.func_overload') !== 0) {
        throw new RuntimeException('Mock harness requires mbstring.func_overload=0.');
    }
}
function reset_mbstring_encoding() {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_taxonomy($taxonomy) {
    if ($taxonomy === 'post_tag') { return (object) array('hierarchical' => false, 'cap' => (object) array('assign_terms' => 'edit_posts')); }
    if ($taxonomy === 'category') { return (object) array('hierarchical' => true, 'cap' => (object) array('assign_terms' => 'edit_posts')); }
    return false;
}
function taxonomy_exists($taxonomy) { return in_array($taxonomy, array('post_tag', 'category'), true); }
function post_type_exists($post_type) { return $post_type === 'post'; }
function get_term_by($field, $value, $taxonomy, $output = null, $filter = 'raw') { return false; }
function get_post($id) {
    ++$GLOBALS['taxopress_test_lookups'];
    return isset($GLOBALS['taxopress_test_posts'][(int) $id]) ? $GLOBALS['taxopress_test_posts'][(int) $id] : null;
}
function is_object_in_taxonomy($type, $taxonomy) { return $type === 'post' && in_array($taxonomy, array('post_tag', 'category'), true); }
function current_user_can($cap, $id = null) { return $GLOBALS['taxopress_test_permissions']; }
function wp_insert_term($name, $taxonomy, $args = array()) { throw new RuntimeException('Forbidden mock DB write.'); }
function wp_update_term($id, $taxonomy, $args = array()) { throw new RuntimeException('Forbidden mock DB write.'); }
function wp_set_object_terms($id, $terms, $taxonomy, $append = false) { throw new RuntimeException('Forbidden mock DB write.'); }
$GLOBALS['taxopress_test_posts'] = array();
$GLOBALS['taxopress_test_permissions'] = true;
$GLOBALS['taxopress_test_lookups'] = 0;
taxopress_transfer_test_load_service();

(function () {
    $pass = 0;
    $fail = 0;
    $check = function ($ok, $label) use (&$pass, &$fail) {
        if ($ok) { ++$pass; } else { ++$fail; }
        echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    };
    $parse = function ($records, $skip = false, $header = null, $taxonomy = 'post_tag', $post_type = 'post', $context = null) {
        $stream = fopen('php://temp', 'w+');
        if (!$stream) { throw new RuntimeException('Cannot open mock CSV.'); }
        try {
            $header = $header === null ? array('slug', 'name', 'description', 'post_ids') : $header;
            fwrite($stream, "\xEF\xBB\xBF");
            $all_records = $context === null ? array_merge(array($header), $records) : array_merge(array($context, $header), $records);
            foreach ($all_records as $record) {
                $cells = array();
                foreach ($record as $cell) { $cells[] = '"' . str_replace('"', '""', $cell) . '"'; }
                fwrite($stream, implode(',', $cells) . "\r\n");
            }
            rewind($stream);
            return TaxoPress_Tag_CSV_Service::parse_csv($stream, $skip, $taxonomy, $post_type);
        } finally { fclose($stream); }
    };
    $records = array();
    $associations = 0;
    for ($i = 0; $i < 159; ++$i) {
        $ids = array(1000 + ($i % 31));
        if ($i < 82) { $ids[] = 1000 + (($i + 1) % 31); }
        $associations += count($ids);
        $records[] = array('synthetic-tag-' . $i, 'Synthetic tag ' . $i, "Caf\xC3\xA9, \"quoted\"\n\\literal", implode('|', $ids));
        foreach ($ids as $id) { $GLOBALS['taxopress_test_posts'][$id] = (object) array('ID' => $id, 'post_type' => 'post'); }
    }
    $check(count($records) === 159 && $associations === 241 && count($GLOBALS['taxopress_test_posts']) === 31, 'Synthetic fixture shape: 159 tags, 241 associations, 31 IDs');
    $rows = $parse($records);
    $check(is_array($rows) && count($rows) === 159, 'Actual integrated parser accepts isolated compatible post mocks');
    if (!is_array($rows)) { throw new RuntimeException(is_wp_error($rows) ? $rows->get_error_message() : 'Unexpected parser result.'); }
    foreach ($rows as $i => $row) {
        $check($row['slug'] === $records[$i][0] && $row['name'] === $records[$i][1]
            && $row['description'] === $records[$i][2] && $row['post_ids'] === $records[$i][3], 'Exact synthetic CSV fidelity record ' . ($i + 2));
    }
    $check($GLOBALS['taxopress_test_lookups'] === 31, 'Preflight caches repeated IDs');
    $GLOBALS['taxopress_test_posts'] = array();
    $check($parse($records) === $rows, 'Missing content retains all terms and original IDs for skipped reporting');
    $check($parse($records, true) === $rows, 'Legacy skip flag retains all terms and original IDs for reporting');
    $check(TaxoPress_Tag_CSV_Service::validate_assignments($rows, true) === true, 'Permitted preflight skips all missing targets');
    for ($id = 1000; $id < 1031; ++$id) {
        $GLOBALS['taxopress_test_posts'][$id] = (object) array('ID' => $id, 'post_type' => 'revision');
    }
    $check($parse($records) === $rows, 'Unsupported content does not reject otherwise valid terms');
    $check($parse($records, true) === $rows, 'Legacy skip flag accepts unsupported mocks without DB');
    $GLOBALS['taxopress_test_permissions'] = false;
    $check(is_wp_error(TaxoPress_Tag_CSV_Service::validate_assignments($rows, true, true)), 'Skip NEVER suppresses permission failure');
    $GLOBALS['taxopress_test_permissions'] = true;
    $GLOBALS['taxopress_test_posts'] = array();
    foreach (array('0', '-1', 'abc', '1.5', '922337203685477580899999', '1000|1000') as $invalid) {
        $check(is_wp_error($parse(array(array('invalid-tag', 'Invalid', '', $invalid)), true)), 'Skip rejects malformed/duplicate IDs: ' . $invalid);
    }
    $check(is_wp_error($parse(array(array('tag', 'Tag', '', '')), false, array('name', 'slug', 'description', 'post_ids'))), 'Reject wrong header order');
    $check(is_wp_error($parse(array(array('tag', 'Tag')), true)), 'Reject short record');
    $check(is_wp_error($parse(array(array('tag', 'Tag', '', ''), array('tag', 'Duplicate', '', '')), true)), 'Reject duplicate slug');
    $check(is_wp_error($parse(array(array('../unsafe slug', 'Unsafe', '', '')), true)), 'Reject unsafe slug');
    $check(is_wp_error($parse(array(array('bad-utf8', "Invalid \xFF", '', '')), true)), 'Reject invalid UTF-8');
    $hierarchical_header = array('slug', 'name', 'description', 'parent_slug', 'post_ids');
    $hierarchical_rows = $parse(
        array(
            array('child', 'Child', '', 'parent', ''),
            array('parent', 'Parent', '', '', ''),
        ),
        false,
        $hierarchical_header,
        'category'
    );
    $check(is_array($hierarchical_rows) && $hierarchical_rows[0]['parent_slug'] === 'parent', 'Accept hierarchical terms even when a child precedes its parent');
    $check(is_wp_error($parse(array(array('one', 'One', '', 'two', ''), array('two', 'Two', '', 'one', '')), false, $hierarchical_header, 'category')), 'Reject circular term hierarchy');
    $check(is_wp_error($parse(array(array('tag', 'Tag', '', 'parent', '')), false, $hierarchical_header)), 'Reject parent_slug for non-hierarchical taxonomy');
    $context = array('taxopress_terms_csv', '1', 'category', 'post');
    $context_rows = $parse(array(array('category', 'Category', '', '', '')), false, $hierarchical_header, 'category', 'post', $context);
    $check(is_array($context_rows) && $context_rows[0]['row'] === 3, 'Accept matching export context and retain accurate CSV record numbers');
    $context_error = $parse(array(array('category', 'Category', '', '', '')), false, $hierarchical_header, 'post_tag', 'post', $context);
    $check(is_wp_error($context_error) && $context_error->get_error_code() === 'taxopress_tag_csv_context', 'Reject CSV imported under a different taxonomy selection');
    $limit = array();
    for ($i = 0; $i < TaxoPress_Tag_CSV_Service::MAX_ROWS; ++$i) { $limit[] = array('limit-' . $i, 'Limit ' . $i, '', ''); }
    $check(is_array($parse($limit, true)), 'Accept exact MAX_ROWS boundary');
    $limit[] = array('limit-extra', 'Extra', '', '');
    $check(is_wp_error($parse($limit, true)), 'Reject MAX_ROWS + 1');
    $assignment_ids = array();
    for ($i = 1; $i <= TaxoPress_Tag_CSV_Service::MAX_ASSOCIATIONS; ++$i) { $assignment_ids[] = (string) $i; }
    $check(is_array($parse(array(array('assignment-limit', 'Assignment limit', '', implode('|', $assignment_ids))), true)), 'Accept exact MAX_ASSOCIATIONS with missing mocks');
    $assignment_ids[] = (string) (TaxoPress_Tag_CSV_Service::MAX_ASSOCIATIONS + 1);
    $check(is_wp_error($parse(array(array('assignment-over-limit', 'Over limit', '', implode('|', $assignment_ids))), true)), 'Reject MAX_ASSOCIATIONS + 1 even in skip mode');
    $stream = fopen('php://temp', 'w+');
    try {
        fwrite($stream, str_repeat('x', TaxoPress_Tag_CSV_Service::MAX_BYTES + 1));
        rewind($stream);
        $check(is_wp_error(TaxoPress_Tag_CSV_Service::parse_csv($stream, true)), 'Reject MAX_BYTES + 1');
    } finally { fclose($stream); }
    echo sprintf("SUMMARY CSV MOCKS: PASS=%d FAIL=%d\n", $pass, $fail);
    if ($fail) { throw new RuntimeException('CSV mock regressions: ' . $fail); }
})();
