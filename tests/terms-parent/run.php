<?php
/** Standalone bulk parent regression checks; no live WordPress writes. */
define('ABSPATH', __DIR__);
class WP_Error
{
    public function __construct(public $code, private $message) {}
    public function get_error_message() { return $this->message; }
}
class WP_List_Table
{
    protected $_args = ['plural' => 'Terms'];
    public function __construct($args = []) {}
    public function current_action() { return $_REQUEST['action'] ?? ''; }
}
function add_action(...$args) {}
function __($text, ...$args) { return $text; }
function esc_html__($text, ...$args) { return $text; }
function esc_html($text) { return htmlspecialchars($text); }
function esc_attr($text) { return htmlspecialchars($text); }
function esc_html_e($text, ...$args) { echo htmlspecialchars($text); }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return is_scalar($value) ? strip_tags((string) $value) : ''; }
function wp_unslash($value) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function number_format_i18n($value) { return (string) $value; }
function _n($single, $plural, $count, ...$args) { return $count === 1 ? $single : $plural; }
function taxopress_admin_notices_helper($message, $success) { return ($success ? 'SUCCESS: ' : 'ERROR: ') . $message; }
function check_admin_referer($action) {
    if ($action !== 'bulk-Terms' || empty($GLOBALS['nonce_valid'])) { throw new RuntimeException('Invalid nonce'); }
}
function get_taxonomy($taxonomy) {
    return in_array($taxonomy, ['category', 'post_tag', 'product_cat'], true)
        ? (object) ['name' => $taxonomy, 'hierarchical' => $taxonomy !== 'post_tag', 'cap' => (object) ['edit_terms' => 'edit_' . $taxonomy]] : false;
}
function get_all_taxopress_taxonomies_request() { return ['category' => get_taxonomy('category'), 'post_tag' => get_taxonomy('post_tag')]; }
function current_user_can($cap, $id = 0) { return !in_array($cap . ':' . $id, $GLOBALS['denied'], true); }
function get_term($id, $taxonomy = '') {
    $term = $GLOBALS['terms'][(int) $id] ?? null;
    return $term && (!$taxonomy || $term->taxonomy === $taxonomy) ? clone $term : null;
}
function get_ancestors($id, $taxonomy, $type) {
    $ancestors = [];
    while (($term = get_term($id, $taxonomy)) && $term->parent && !in_array($term->parent, $ancestors, true)) {
        $id = (int) $term->parent;
        $ancestors[] = $id;
    }
    return $ancestors;
}
function wp_update_term($id, $taxonomy, $args) {
    $GLOBALS['writes'][] = [$id, $taxonomy, $args];
    if ($GLOBALS['fail_id'] === $id) { return new WP_Error('save_failed', 'Save failed'); }
    $GLOBALS['terms'][$id]->parent = $args['parent'];
    return ['term_id' => $id];
}
require dirname(__DIR__, 2) . '/inc/terms-functions.php';
require dirname(__DIR__, 2) . '/inc/terms-table.php';
class ParentTermsTable extends Taxopress_Terms_List
{
    public function actions() { return $this->get_bulk_actions(); }
}
function reset_fixture() {
    $GLOBALS['terms'] = [];
    foreach ([1 => ['category', 0], 2 => ['category', 1], 3 => ['category', 2], 4 => ['category', 0], 5 => ['product_cat', 0], 6 => ['post_tag', 0]] as $id => [$taxonomy, $parent]) {
        $GLOBALS['terms'][$id] = (object) ['term_id' => $id, 'taxonomy' => $taxonomy, 'parent' => $parent, 'name' => "Term $id", 'slug' => "term-$id", 'description' => 'Keep description'];
    }
    $GLOBALS['denied'] = [];
    $GLOBALS['writes'] = [];
    $GLOBALS['fail_id'] = 0;
    $GLOBALS['nonce_valid'] = true;
    $_POST = $_REQUEST = [];
}
$checks = 0;
function check($ok, $label) {
    if (!$ok) { throw new RuntimeException($label); }
    ++$GLOBALS['checks'];
    echo "PASS: $label\n";
}
function outcome_counts($result) { return array_intersect_key($result, ['updated' => 0, 'unchanged' => 0, 'failed' => 0]); }
function notice_is_allowed($notice) {
    preg_match('/id="taxopress-bulk-parent-notice" class="([^"]+)"/', $notice, $match);
    // TaxoPress hides .notice:not(.taxopress-notice) on its admin pages.
    return isset($match[1]) && in_array('taxopress-notice', preg_split('/\s+/', $match[1]), true);
}
reset_fixture();
$result = taxopress_set_terms_parent('category', [2, 4, 4], 0);
check(outcome_counts($result) === ['updated' => 1, 'unchanged' => 1, 'failed' => 0], 'Deduplicate selection and count unchanged terms');
check($terms[2]->parent === 0 && $terms[3]->parent === 2, 'Move to top level while keeping descendants attached');
check($writes === [[2, 'category', ['parent' => 0]]], 'Only the parent field is written');
check($terms[2]->name === 'Term 2' && $terms[2]->slug === 'term-2' && $terms[2]->description === 'Keep description', 'Preserve term identity and content');
reset_fixture();
check(taxopress_set_terms_parent('category', [2, 3], 4)['updated'] === 2, 'Move multiple terms beneath the chosen parent');

foreach ([['post_tag', [6], 0, 'invalid_taxonomy'], ['missing', [2], 0, 'invalid_taxonomy'], ['category', [2], '', 'invalid_parent'], ['category', [2], -4, 'invalid_parent'], ['category', [2], 5, 'invalid_parent'], ['category', [2], 999, 'invalid_parent'], ['category', [], 0, 'missing_terms'], ['category', [2, 5], 4, 'invalid_term'], ['category', [2, 999], 4, 'invalid_term'], ['category', [2, -4], 0, 'invalid_term'], ['category', [2, ['bad']], 0, 'invalid_term']] as [$taxonomy, $ids, $parent, $code]) {
    reset_fixture();
    $result = taxopress_set_terms_parent($taxonomy, $ids, $parent);
    check(is_wp_error($result) && $result->code === $code && !$writes, "Reject $code before any write: " . json_encode([$taxonomy, $ids, $parent]));
}
foreach (['simple_tags:0', 'edit_category:0', 'edit_term:4'] as $cap) {
    reset_fixture();
    $denied = [$cap];
    $result = taxopress_set_terms_parent('category', [2, 4], 0);
    check(is_wp_error($result) && $result->code === 'permission_denied' && !$writes, "Enforce $cap before updates");
}
reset_fixture();
$fail_id = 3;
check(outcome_counts(taxopress_set_terms_parent('category', [2, 3], 4)) === ['updated' => 1, 'unchanged' => 0, 'failed' => 1], 'Report partial persistence failure accurately');

reset_fixture();
$result = taxopress_set_terms_parent('category', [4, 1], 3);
check($result['updated'] === 1 && $result['skipped'] === 1 && $terms[4]->parent === 3 && $terms[1]->parent === 0, 'Move safe term while skipping an ancestor of the chosen parent');
check($result['details']['descendant_parent'] === ['Term 1'], 'Identify skipped ancestor separately from self-parenting');
reset_fixture();
$result = taxopress_set_terms_parent('category', [2, 2], 2);
check($result['updated'] === 0 && $result['skipped'] === 1 && !$writes && $result['details']['self_parent'] === ['Term 2'], 'Self-parent-only selection is deduplicated and skipped without writes');

$table = new ParentTermsTable();
foreach (['' => false, 'post_tag' => false, 'category' => true, 'product_cat' => true] as $taxonomy => $visible) {
    reset_fixture();
    $_REQUEST['terms_filter_taxonomy'] = $taxonomy;
    check(isset($table->actions()['taxopress-terms-set-parent']) === $visible, "Bulk action visibility for '$taxonomy'");
}
reset_fixture();
$_REQUEST['taxopress_terms_taxonomy'] = 'category';
check(isset($table->actions()['taxopress-terms-set-parent']), 'Support taxonomy-specific Terms screen');
$denied = ['edit_category:0'];
check(!isset($table->actions()['taxopress-terms-set-parent']), 'Hide action without taxonomy edit permission');

reset_fixture();
$_POST = ['taxopress_terms' => ['2', '3'], 'taxopress_parent_term' => '4'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
ob_start();
$table->process_bulk_action();
$table->display_bulk_parent_notice();
$notice = ob_get_clean();
check(count($writes) === 2 && strpos($notice, '2 terms updated.') !== false && strpos($notice, 'notice-success') !== false, 'Controller processes a nonce-verified selection and reports counts');
check(notice_is_allowed($notice), 'Successful batch notice is exempt from the admin notice hiding rule');

reset_fixture();
$_POST = ['taxopress_terms' => ['2'], 'taxopress_parent_term' => '4'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
$nonce_valid = false;
try {
    $table->process_bulk_action();
    throw new RuntimeException('Nonce check was skipped');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'Invalid nonce' && !$writes, 'Reject invalid nonce without writes');
}
reset_fixture();
$_REQUEST = ['terms_filter_taxonomy' => 'post_tag', 'action' => 'taxopress-terms-set-parent'];
$_POST = ['taxopress_terms' => ['6'], 'taxopress_parent_term' => '0'];
ob_start();
$table->process_bulk_action();
$table->display_bulk_parent_notice();
$notice = ob_get_clean();
check(!$writes && strpos($notice, 'notice-error') !== false, 'Forged non-hierarchical action is rejected server-side');
check(notice_is_allowed($notice), 'Validation error notice is exempt from the admin notice hiding rule');

reset_fixture();
foreach ([1 => 'Holiday', 2 => 'Fat', 3 => 'wellness', 4 => 'School'] as $id => $name) {
    $terms[$id]->name = $name;
    $terms[$id]->parent = 0;
}
$_POST = ['taxopress_terms' => ['1', '2', '3', '4'], 'taxopress_parent_term' => '1'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
ob_start();
$table->process_bulk_action();
check(ob_get_clean() === '', 'Processing stores feedback for the dedicated screen notice region');
ob_start();
$table->display_bulk_parent_notice();
$notice = html_entity_decode(ob_get_clean());
check(count($writes) === 3 && $terms[1]->parent === 0 && $terms[2]->parent === 1 && $terms[3]->parent === 1 && $terms[4]->parent === 1, 'Holiday example assigns the other three terms and retains Holiday');
check(strpos($notice, '3 terms updated. 1 term skipped.') !== false && strpos($notice, 'notice-warning') !== false, 'Mixed result has a visible warning with accurate counts');
check(notice_is_allowed($notice), 'Mixed updated/skipped batch notice is exempt from the admin notice hiding rule');
check(strpos($notice, 'Assigned to "Holiday": Fat, wellness, School.') !== false, 'Name every term successfully assigned to Holiday');
check(strpos($notice, 'Skipped: Holiday. A term cannot be its own parent.') !== false, 'Explain exactly why Holiday was skipped');
ob_start();
$table->display_bulk_parent_notice();
check(ob_get_clean() === '', 'Consume feedback once rather than duplicating notices');

foreach ([[1], [1, 2, 3]] as $ids) {
    $_POST['taxopress_terms'] = $ids;
    ob_start();
    $table->process_bulk_action();
    $table->display_bulk_parent_notice();
    $notice = ob_get_clean();
    check(strpos($notice, 'taxopress-bulk-parent-notice') !== false && strpos($notice, 'A term cannot be its own parent.') !== false, 'Feedback survives single or mixed unchanged/skipped selections');
    check(notice_is_allowed($notice), 'Skipped-only or unchanged/skipped notice remains visible under admin notice filtering');
}

reset_fixture();
$terms[2]->name = '<script>alert(1)</script>';
$_POST = ['taxopress_terms' => ['2'], 'taxopress_parent_term' => '4'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
ob_start();
$table->process_bulk_action();
$table->display_bulk_parent_notice();
$notice = ob_get_clean();
check(strpos($notice, '<script>') === false && strpos($notice, '&lt;script&gt;') !== false, 'Escape term names in detailed feedback');

reset_fixture();
$_POST = ['taxopress_terms' => ['1', '4'], 'taxopress_parent_term' => '3'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
ob_start();
$table->process_bulk_action();
$table->display_bulk_parent_notice();
$notice = ob_get_clean();
check(strpos($notice, 'Skipped: Term 1.') !== false && strpos($notice, 'circular hierarchy') !== false && strpos($notice, 'Assigned to') !== false, 'Mixed descendant conflict notice identifies both the move and its skipped ancestor');

reset_fixture();
for ($id = 10; $id < 22; ++$id) {
    $terms[$id] = (object) ['term_id' => $id, 'taxonomy' => 'category', 'parent' => 0, 'name' => 'Bulk ' . $id];
}
$_POST = ['taxopress_terms' => range(10, 21), 'taxopress_parent_term' => '1'];
$_REQUEST = $_POST + ['terms_filter_taxonomy' => 'category', 'action' => 'taxopress-terms-set-parent'];
ob_start();
$table->process_bulk_action();
$table->display_bulk_parent_notice();
$notice = ob_get_clean();
check(strpos($notice, '12 terms updated.') !== false && strpos($notice, '<details>') !== false && strpos($notice, 'Bulk 21') !== false, 'Large-batch notice keeps counts visible and all term names available in expandable details');
check(notice_is_allowed($notice), 'Large-batch notice remains visible under admin notice filtering');
echo "$checks checks passed.\n";
