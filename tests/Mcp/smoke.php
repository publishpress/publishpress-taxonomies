<?php
/** Native Taxonomies workflows through released MCP Adapter 0.7.1. Disposable database only. */
define('REST_REQUEST', true);
require $argv[1];
if (strpos(DB_NAME, 'taxonomies_mcp_') !== 0) { throw new RuntimeException('Disposable taxonomies_mcp_* database required'); }
require $argv[2] . '/vendor/autoload.php';
wp_set_current_user(1);
if (class_exists('TaxoPress_Linked_Terms')) { TaxoPress_Linked_Terms::get_instance()->run_installer_task(); }
$assertions = 0;
function check($condition, $message) { global $assertions; ++$assertions; if (!$condition) { throw new RuntimeException($message); } }
function callTax($name, $input = []) {
    $ability = wp_get_ability('publishpress-taxonomies/' . $name);
    check($ability !== null, 'Missing ability ' . $name);
    $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility($ability);
    check(!is_wp_error($tool), 'Invalid Adapter tool ' . $name);
    $result = $tool->execute($input);
    if (!is_wp_error($result)) { check(!is_wp_error(rest_validate_value_from_schema($result, $ability->get_output_schema())), 'Output schema ' . $name); }
    return $result;
}
function success($result, $message) { check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_message() : '')); return $result; }
$features = success(callTax('list-features'), 'Features');
check(($features['edition'] === 'pro') === (getenv('TAXONOMIES_MCP_MODE') === 'pro'), 'Edition detection');
check(count($features['features']) >= 16, 'Native feature inventory missing');
$native = SimpleTags_Plugin::get_option();
$native['open_ai_api_key'] = 'synthetic-secret';
SimpleTags_Plugin::set_option($native);
$settings = success(callTax('get-plugin-settings'), 'Settings');
$originalSettings = get_option(STAGS_OPTIONS_NAME);
$malformedNativeCheckbox = $originalSettings;
$malformedNativeCheckbox['enable_taxopress_ai_post_metabox'] = 'post_tag';
update_option(STAGS_OPTIONS_NAME, $malformedNativeCheckbox);
$effectiveCheckbox = success(callTax('get-plugin-settings'), 'Native checkbox effective value');
check($effectiveCheckbox['settings']->enable_taxopress_ai_post_metabox === false, 'Nonnumeric native checkbox reported enabled');
update_option(STAGS_OPTIONS_NAME, $originalSettings);
$settings = success(callTax('get-plugin-settings'), 'Settings after native checkbox fixture');

check(strpos(wp_json_encode($settings), 'synthetic-secret') === false, 'Secret exposed');
$labelKey = 'taxopress_ai_create_terms_tab_label';
$labelToken = $settings['expected_hash'];
update_option($labelKey, 'Native dedicated label');
$settings = success(callTax('get-plugin-settings'), 'Native dedicated label discovery');
check($settings['settings']->$labelKey === 'Native dedicated label', 'Discovery ignored native dedicated option');
check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) [$labelKey => 'Stale label'], 'expected_hash' => $labelToken])), 'Dedicated label did not invalidate revision');
$labelOptions = get_option(STAGS_OPTIONS_NAME);
check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) [$labelKey => '   ', 'active_auto_links' => false], 'expected_hash' => $settings['expected_hash']])), 'Blank native label accepted');
check(get_option(STAGS_OPTIONS_NAME) === $labelOptions && get_option($labelKey) === 'Native dedicated label', 'Invalid label patch wrote sibling setting');
$denyLabelCap = static function ($caps, $cap) { return $cap === 'simple_tags' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyLabelCap, PHP_INT_MAX, 2);
try { check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) [$labelKey => 'Denied label'], 'expected_hash' => $settings['expected_hash']])), 'Native label management denial bypassed'); }
finally { remove_filter('map_meta_cap', $denyLabelCap, PHP_INT_MAX); }
$labelPreview = success(callTax('update-plugin-settings', ['settings' => (object) [$labelKey => 'Create a topic'], 'expected_hash' => $settings['expected_hash'], 'dry_run' => true]), 'Native label preview');
check($labelPreview['settings']->$labelKey === 'Create a topic' && get_option($labelKey) === 'Native dedicated label', 'Native label preview wrote');
$settings = success(callTax('update-plugin-settings', ['settings' => (object) [$labelKey => 'Create a topic'], 'expected_hash' => $settings['expected_hash']]), 'Native label save');
check(get_option($labelKey) === 'Create a topic' && SimpleTags_Plugin::get_option_value($labelKey) === 'Create a topic', 'Native label representations diverged');

foreach ((array) $settings['fields'] as $key => $field) {
    $value = $settings['settings']->$key;
    check(!is_wp_error(rest_validate_value_from_schema($value, $field)), 'Discovery schema ' . $key . ': ' . wp_json_encode($value));
    $before = get_option(STAGS_OPTIONS_NAME);
    success(callTax('update-plugin-settings', ['settings' => (object) [$key => $value], 'expected_hash' => $settings['expected_hash'], 'dry_run' => true]), 'Preview ' . $key);
    check(get_option(STAGS_OPTIONS_NAME) === $before, 'Preview wrote ' . $key);
    $settings = success(callTax('update-plugin-settings', ['settings' => (object) [$key => $value], 'expected_hash' => $settings['expected_hash']]), 'Save ' . $key);
    check($settings['settings']->$key === $value, 'Readback ' . $key);
    check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) [$key => null], 'expected_hash' => $settings['expected_hash']])), 'Null accepted ' . $key);
    if ($field['type'] === 'boolean') { check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) [$key => 'false'], 'expected_hash' => $settings['expected_hash']])), 'String false accepted'); }
}
$before = get_option(STAGS_OPTIONS_NAME);
check(is_wp_error(callTax('update-plugin-settings', ['settings' => (object) ['active_auto_links' => true, 'unknown' => 1], 'expected_hash' => $settings['expected_hash']])), 'Unknown setting accepted');
check(get_option(STAGS_OPTIONS_NAME) === $before, 'Mixed invalid patch wrote');
check(get_option(STAGS_OPTIONS_NAME)['open_ai_api_key'] === 'synthetic-secret', 'Secret overwritten');
$settings = success(callTax('update-plugin-settings', ['settings' => (object) ['active_auto_links' => true, 'active_auto_terms' => true, 'active_terms_display' => true, 'active_post_tags' => true, 'active_related_posts_new' => true, 'active_taxonomies' => true], 'expected_hash' => $settings['expected_hash']]), 'Enable features');
foreach (array_keys(SimpleTags_Mcp_Contracts::kinds()) as $kind) {
    $discovery = success(callTax('get-preset-fields', ['kind' => $kind]), 'Preset discovery ' . $kind);
    foreach ((array) $discovery['fields'] as $key => $field) { check(!is_wp_error(rest_validate_value_from_schema($discovery['values']->$key, $field)), 'Preset default ' . $kind . ':' . $key . '=' . wp_json_encode($discovery['values']->$key)); }
    $all = call_user_func(SimpleTags_Mcp_Contracts::kinds()[$kind]['get']);
    if ($all && !SimpleTags_Mcp::pro()) { $id = (int) array_key_first($all); $discovery = success(callTax('get-preset', ['kind' => $kind, 'id' => $id]), 'Existing preset'); }
    else { $id = 0; }
    $data = ['kind' => $kind, 'id' => $id, 'fields' => (object) ['title' => 'MCP ' . $kind], 'expected_hash' => $discovery['expected_hash']];
    success(callTax('save-preset', $data + ['dry_run' => true]), 'Preset preview ' . $kind);
    $saved = success(callTax('save-preset', $data), 'Preset save ' . $kind);
    check($saved['values']->title === 'MCP ' . $kind, 'Preset title readback');
    foreach ((array) $saved['fields'] as $key => $field) {
        $value = $saved['values']->$key;
        $saved = success(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) [$key => $value], 'expected_hash' => $saved['expected_hash']]), 'Field save ' . $kind . ':' . $key);
        check($saved['values']->$key === $value, 'Preset field readback ' . $kind . ':' . $key);
        check(is_wp_error(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) [$key => null], 'expected_hash' => $saved['expected_hash']])), 'Preset null accepted ' . $key);
    }

    $literalTitle = "Folder\\Ola's & terms";
    $literal = ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['title' => $literalTitle], 'expected_hash' => $saved['expected_hash']];
    $titlePreview = success(callTax('save-preset', $literal + ['dry_run' => true]), 'Literal title preview');
    check($titlePreview['values']->title === $literalTitle, 'Title preview lost literal characters');
    $saved = success(callTax('save-preset', $literal), 'Literal title save');
    check($saved['values']->title === $literalTitle, 'Native title lost slash or encoded entities');
    if (isset($saved['fields']->xformat)) {
        $template = "Literal\\path %tag_name%";
        $saved = success(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['xformat' => $template], 'expected_hash' => $saved['expected_hash']]), 'Literal native template');
        check($saved['values']->xformat === $template, 'Native template lost slash');
    }
    if ($kind === 'autolink') {
        check($saved['values']->autolink_min_char === '', 'Blank numeric input coerced to zero');
        foreach (['12', 'false', false, []] as $badNumber) {
            check(is_wp_error(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['autolink_min_char' => $badNumber], 'expected_hash' => $saved['expected_hash']])), 'Invalid optional numeric input accepted');
        }
    }
    if (isset($saved['fields']->mincolor)) {
        $beforeInvalidColor = call_user_func(SimpleTags_Mcp_Contracts::kinds()[$kind]['get']);
        check(is_wp_error(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['mincolor' => 'invalid-color', 'title' => 'Must not save'], 'expected_hash' => $saved['expected_hash']])), 'Malformed color accepted');
        check(call_user_func(SimpleTags_Mcp_Contracts::kinds()[$kind]['get']) === $beforeInvalidColor, 'Invalid color patch wrote sibling title');
    }
    $saved = success(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['title' => 'Renamed'], 'expected_hash' => $saved['expected_hash']]), 'Preset patch ' . $kind);
    check($saved['values']->title === 'Renamed', 'Preset patch readback');
    check(is_wp_error(callTax('save-preset', ['kind' => $kind, 'id' => $saved['id'], 'fields' => (object) ['unknown' => true], 'expected_hash' => $saved['expected_hash']])), 'Unknown preset field accepted');
    if (SimpleTags_Mcp::pro()) {
        $copy = success(callTax('copy-preset', ['kind' => $kind, 'id' => $saved['id'], 'expected_hash' => $saved['expected_hash']]), 'Native Pro copy ' . $kind);
        check($copy['values']->title === 'Renamed-copy', 'Copy title readback');
        success(callTax('delete-preset', ['kind' => $kind, 'id' => $copy['id'], 'expected_hash' => $copy['expected_hash']]), 'Delete copied configuration');
    }
    success(callTax('delete-preset', ['kind' => $kind, 'id' => $saved['id'], 'expected_hash' => $saved['expected_hash']]), 'Native preset deletion');
}
$taxonomies = success(callTax('list-taxonomies'), 'Taxonomy discovery');
$taxonomy = success(callTax('save-taxonomy', ['name' => 'mcp_topic', 'fields' => (object) ['label' => 'MCP Topics', 'singular_label' => 'MCP Topic', 'object_types' => ['post']], 'expected_hash' => $taxonomies['new_expected_hash']]), 'Taxonomy create');
check(taxonomy_exists('mcp_topic'), 'Native taxonomy not registered');
$taxonomy = success(callTax('save-taxonomy', ['name' => 'mcp_topic', 'fields' => (object) ['description' => 'Native patch'], 'expected_hash' => $taxonomy['expected_hash']]), 'Taxonomy patch');
check($taxonomy['values']->label === 'MCP Topics', 'Omitted taxonomy label lost');
$list = success(callTax('list-terms', ['taxonomy' => 'mcp_topic']), 'Terms');
$term = success(callTax('save-term', ['taxonomy' => 'mcp_topic', 'id' => 0, 'fields' => (object) ['name' => 'Review'], 'expected_hash' => $list['new_expected_hash']]), 'Term create');
$directTerm = success(callTax('get-term', ['id' => $term['id']]), 'Direct native term lookup');
check($directTerm['name'] === 'Review', 'Known term lookup returned wrong data');
$fixturePostId = wp_insert_post(['post_title' => 'MCP fixture', 'post_type' => 'post', 'post_status' => 'draft']);
$postSearch = success(callTax('list-posts', ['term_id' => $term['id'], 'post_type' => 'post']), 'Native Posts search before assignment');
check(!in_array($fixturePostId, array_column($postSearch['items'], 'id'), true), 'Term filter returned unrelated post');
$selection = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Post discovery');
$selection = success(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash']]), 'Native assignment');
check(has_term($term['id'], 'mcp_topic', $fixturePostId), 'Assignment readback');
$postSearch = success(callTax('list-posts', ['term_id' => $term['id'], 'search' => 'MCP fixture']), 'Native Posts term/title search');
check(in_array($fixturePostId, array_column($postSearch['items'], 'id'), true), 'Native Posts search missed assigned post');
$denyListedPost = static function ($caps, $cap, $userId, $args) use ($fixturePostId) { return $cap === 'read_post' && (int) ($args[0] ?? 0) === $fixturePostId ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyListedPost, PHP_INT_MAX, 4);
try {
    $hiddenPosts = success(callTax('list-posts', ['term_id' => $term['id']]), 'Denied post filtered from search');
    check(!in_array($fixturePostId, array_column($hiddenPosts['items'], 'id'), true) && !isset($hiddenPosts['max_num_posts']), 'Denied post or private total exposed');
} finally { remove_filter('map_meta_cap', $denyListedPost, PHP_INT_MAX); }
check(is_wp_error(callTax('list-posts', ['post_type' => 'not-a-post-type'])), 'Unknown post type accepted');
check(is_wp_error(callTax('list-posts', ['term_id' => (string) $term['id']])), 'String filter ID accepted');

check(is_wp_error(callTax('set-post-terms', ['id' => (string) $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [], 'expected_hash' => $selection['expected_hash']])), 'Numeric string ID accepted');
$selection = success(callTax('set-post-features', ['id' => $fixturePostId, 'features' => (object) ['autolinks' => 'disabled'], 'expected_hash' => $selection['expected_hash']]), 'Post override');
check(get_post_meta($fixturePostId, '_exclude_autolinks', true), 'Legacy override not maintained');
$blockStatusWrite = static function ($result, $id, $key) use ($fixturePostId) { return (int) $id === $fixturePostId && in_array($key, ['_taxopress_autolinks_status', '_exclude_autolinks'], true) ? false : $result; };
add_filter('update_post_metadata', $blockStatusWrite, PHP_INT_MAX, 3);
try {
    check(is_wp_error(callTax('set-post-features', ['id' => $fixturePostId, 'features' => (object) ['autolinks' => 'enabled'], 'expected_hash' => $selection['expected_hash']])), 'Failed native feature persistence reported success');
} finally { remove_filter('update_post_metadata', $blockStatusWrite, PHP_INT_MAX); }


$settings = success(callTax('get-plugin-settings'), 'Refresh feature revision');
$settings = success(callTax('update-plugin-settings', ['settings' => (object) ['active_manage' => true], 'expected_hash' => $settings['expected_hash']]), 'Enable Manage Terms');
$defaults = success(callTax('get-preset-fields', ['kind' => 'autoterm']), 'Local auto defaults');
$auto = success(callTax('save-preset', ['kind' => 'autoterm', 'id' => 0, 'fields' => (object) ['title' => 'Local matching', 'taxonomy' => 'mcp_topic', 'post_types' => ['post'], 'post_status' => ['draft'], 'autoterm_use_taxonomy' => '1', 'autoterm_useall' => '1', 'autoterm_for_schedule' => '1'], 'expected_hash' => $defaults['expected_hash']]), 'Local matching configuration');
$rawAuto = get_option('taxopress_autoterms');
$rawAuto[$auto['id']]['open_ai_api_key'] = 'synthetic-preset-secret';
$rawAuto[$auto['id']]['mcp_unrelated_fixture'] = ['preserve' => 'native'];
update_option('taxopress_autoterms', $rawAuto);
$auto = success(callTax('get-preset', ['kind' => 'autoterm', 'id' => $auto['id']]), 'Protected native configuration read');
check(strpos(wp_json_encode($auto), 'synthetic-preset-secret') === false, 'Preset secret exposed');
$auto = success(callTax('save-preset', ['kind' => 'autoterm', 'id' => $auto['id'], 'fields' => (object) ['title' => 'Protected native configuration'], 'expected_hash' => $auto['expected_hash']]), 'Preserve protected native configuration');
$preservedAuto = get_option('taxopress_autoterms')[$auto['id']];
check($preservedAuto['open_ai_api_key'] === 'synthetic-preset-secret' && $preservedAuto['mcp_unrelated_fixture'] === ['preserve' => 'native'], 'Omitted protected or unrelated preset data changed');
wp_update_post(['ID' => $fixturePostId, 'post_content' => 'Review content for testing.']);
$beforeMeta = get_post_meta($fixturePostId);
$beforeCount = wp_count_posts('taxopress_logs');
$preview = success(callTax('preview-autoterms', ['id' => $fixturePostId, 'preset_id' => $auto['id']]), 'Native local matching');
check(get_post_meta($fixturePostId) === $beforeMeta, 'Local preview changed metadata: ' . wp_json_encode([$beforeMeta, get_post_meta($fixturePostId)]));
check(wp_count_posts('taxopress_logs') == $beforeCount, 'Local preview wrote logs: ' . wp_json_encode([$beforeCount, wp_count_posts('taxopress_logs')]));
check(!empty($preview['plan']->mcp_topic), 'Native matching missed Review');
success(callTax('apply-autoterms', ['id' => $fixturePostId, 'preset_id' => $auto['id'], 'expected_hash' => $preview['expected_hash']]), 'Apply local matching');
wp_update_post(['ID' => $fixturePostId, 'post_content' => 'No matching vocabulary.']);
$beforeCount = wp_count_posts('taxopress_logs');
$empty = success(callTax('preview-autoterms', ['id' => $fixturePostId, 'preset_id' => $auto['id']]), 'Empty preview');
check($empty['plan']->mcp_topic === [] && wp_count_posts('taxopress_logs') == $beforeCount, 'Empty preview wrote logs');
if (SimpleTags_Mcp::pro()) {
    $auto = success(callTax('save-preset', ['kind' => 'autoterm', 'id' => $auto['id'], 'fields' => (object) ['additional_taxonomies' => ['category']], 'expected_hash' => $auto['expected_hash']]), 'Additional provider targets');
    $localScope = success(callTax('preview-autoterms', ['id' => $fixturePostId, 'preset_id' => $auto['id']]), 'Local matching scope');
    check(array_keys((array) $localScope['plan']) === ['mcp_topic'], 'Local matcher used OpenAI-only additional targets');
}
$schedule = success(callTax('get-autoterms-schedule'), 'Schedule discovery');
$schedule = success(callTax('update-autoterms-schedule', ['fields' => (object) ['cron_schedule' => 'weekly', 'autoterm_id' => [$auto['id']]], 'expected_hash' => $schedule['expected_hash']]), 'Schedule save');
check((bool) wp_next_scheduled('taxopress_cron_autoterms_weekly'), 'Weekly event missing');
$schedule = success(callTax('update-autoterms-schedule', ['fields' => (object) ['cron_schedule' => 'disable'], 'expected_hash' => $schedule['expected_hash']]), 'Disable schedule');
check(!wp_next_scheduled('taxopress_cron_autoterms_weekly'), 'Disabled schedule remains');
$terms = success(callTax('list-terms', ['taxonomy' => 'mcp_topic']), 'New merge source revision');
$source = success(callTax('save-term', ['taxonomy' => 'mcp_topic', 'id' => 0, 'fields' => (object) ['name' => 'Merge source'], 'expected_hash' => $terms['new_expected_hash']]), 'Merge source create');
$merge = success(callTax('preview-merge-terms', ['taxonomy' => 'mcp_topic', 'source_ids' => [$source['id']], 'target_id' => $term['id']]), 'Merge preview');
$denyMergeTerm = static function ($caps, $cap, $userId, $args) use ($source) { return $cap === 'assign_term' && (int) ($args[0] ?? 0) === $source['id'] ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyMergeTerm, PHP_INT_MAX, 4);
try {
    check(is_wp_error(callTax('merge-terms', ['taxonomy' => 'mcp_topic', 'source_ids' => [$source['id']], 'target_id' => $term['id'], 'expected_hash' => $merge['expected_hash']])), 'Denied source term assignment merged');
    check((bool) get_term($source['id']), 'Denied merge deleted source');
} finally { remove_filter('map_meta_cap', $denyMergeTerm, PHP_INT_MAX); }
$merged = success(callTax('merge-terms', ['taxonomy' => 'mcp_topic', 'source_ids' => [$source['id']], 'target_id' => $term['id'], 'expected_hash' => $merge['expected_hash']]), 'Native merge');
check(!get_term($source['id'], 'mcp_topic') && $merged['complete'], 'Merge did not delete source');
if (SimpleTags_Mcp::pro()) {
    $settings = success(callTax('get-plugin-settings'), 'Pro switches');
    $settings = success(callTax('update-plugin-settings', ['settings' => (object) ['active_features_synonyms' => true, 'active_features_linked_terms' => true, 'synonyms_taxonomies' => ['mcp_topic'], 'linked_terms_taxonomies' => ['mcp_topic']], 'expected_hash' => $settings['expected_hash']]), 'Pro relation switches');
    $terms = success(callTax('list-terms', ['taxonomy' => 'mcp_topic']), 'Relation term revision');
    $other = success(callTax('save-term', ['taxonomy' => 'mcp_topic', 'id' => 0, 'fields' => (object) ['name' => 'Related'], 'expected_hash' => $terms['new_expected_hash']]), 'Relation term');
    $relation = success(callTax('get-term-relations', ['id' => $term['id']]), 'Relations');
    $relation = success(callTax('set-term-relations', ['id' => $term['id'], 'synonyms' => ['Reviewing'], 'linked_ids' => [$other['id']], 'expected_hash' => $relation['expected_hash']]), 'Pro native relationship save');
    check($relation['linked_ids'] === [$other['id']] && $relation['synonyms'] === ['Reviewing'], 'Native relation readback');
    $direct = success(callTax('get-term', ['id' => $term['id']]), 'Direct term discovery');
    check($direct['name'] === 'Review', 'Direct term lookup mismatch');
    $selection = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Linked assignment token');
    $linkedPreview = success(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash'], 'dry_run' => true]), 'Native linked assignment preview');
    check(in_array($other['id'], $linkedPreview['terms']->mcp_topic, true), 'Linked term absent from assignment preview');
    $denyLinked = static function ($caps, $cap, $userId, $args) use ($other) {
        if ($cap === 'assign_term' && (int) ($args[0] ?? 0) === $other['id']) { return ['do_not_allow']; }
        return $caps;
    };
    $beforeDeniedLinked = wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']);
    add_filter('map_meta_cap', $denyLinked, PHP_INT_MAX, 4);
    try { check(is_wp_error(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash']])), 'Denied linked assignment accepted'); }
    finally { remove_filter('map_meta_cap', $denyLinked, PHP_INT_MAX); }
    check(wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']) === $beforeDeniedLinked, 'Denied propagation changed the requested taxonomy');
    register_taxonomy('mcp_locked_topic', ['post'], ['public' => true, 'show_ui' => true, 'capabilities' => ['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'mcp_forbidden_assignment']]);
    $lockedTerm = wp_insert_term('Restricted linked destination', 'mcp_locked_topic');
    TaxoPress_Linked_Terms::addLinkedTermsRelation($term['id'], 'Review', 'mcp_topic', $lockedTerm['term_id'], 'Restricted linked destination', 'mcp_locked_topic');
    $restrictedToken = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Restricted linked token');
    $beforeRestricted = wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']);
    $deniedRestricted = callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $restrictedToken['expected_hash']]);
    check(is_wp_error($deniedRestricted) && ($deniedRestricted->get_error_data()['status'] ?? 0) === 403, 'Denied cross-taxonomy assignment not rejected');
    check(wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']) === $beforeRestricted, 'Cross-taxonomy denial wrote primary selection');
    TaxoPress_Linked_Terms::get_instance()->deleteLinkedTermsRelation($term['id'], $lockedTerm['term_id']);
    unregister_taxonomy('mcp_locked_topic');
    $selection = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Restored linked revision');
    $selection = success(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash']]), 'Native linked propagation');
    check(has_term($other['id'], 'mcp_topic', $fixturePostId), 'Native linked term was not assigned');
    $settingsForDirection = success(callTax('get-plugin-settings'), 'Direction token');
    if (isset($settingsForDirection['fields']->linked_terms_type)) {
        $settingsForDirection = success(callTax('update-plugin-settings', ['settings' => (object) ['linked_terms_type' => 'primary'], 'expected_hash' => $settingsForDirection['expected_hash']]), 'Native direction');
        check(is_wp_error(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash']])), 'Changed linked direction did not invalidate revision');
        $directionToken = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Primary direction token');
        $directionResult = success(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$other['id']], 'expected_hash' => $directionToken['expected_hash']]), 'Native primary-only direction');
        check($directionResult['terms']->mcp_topic === [$other['id']], 'Primary direction propagated backwards');
    }
    $copyPost = wp_insert_post(['post_title' => 'Native term copy fixture', 'post_content' => 'No matched words', 'post_type' => 'post', 'post_status' => 'publish']);
    wp_set_object_terms($copyPost, [$term['id']], 'mcp_topic', false);
    update_term_meta($term['id'], 'mcp_fixture_note', wp_slash("Opaque\\metadata"));
    $copyPreview = success(callTax('preview-copy-term', ['id' => $term['id']]), 'Pro native term copy preview');
    check(strpos(wp_json_encode($copyPreview), 'Opaque') === false, 'Term metadata exposed in copy preview');
    $copied = success(callTax('copy-term', ['id' => $term['id'], 'expected_hash' => $copyPreview['expected_hash']]), 'Pro native term copy');
    check(get_term_meta($copied['term']->id, 'mcp_fixture_note', true) === "Opaque\\metadata", 'Copied metadata lost literal slash');
    check(has_term($copied['term']->id, 'mcp_topic', $copyPost), 'Copy did not retain native published-post assignments');
    $copyPreview = success(callTax('preview-copy-term', ['id' => $term['id']]), 'Copy metadata revision');
    update_term_meta($term['id'], 'mcp_fixture_note', 'Changed native metadata');
    check(is_wp_error(callTax('copy-term', ['id' => $term['id'], 'expected_hash' => $copyPreview['expected_hash']])), 'Stale metadata copy accepted');
    $copyPreview = success(callTax('preview-copy-term', ['id' => $term['id']]), 'Copy denied post preview');
    $newCopyPreview = success(callTax('preview-copy-term', ['id' => $term['id']]), 'Destination-permission copy preview');
    $denyCopiedTerm = static function ($caps, $cap, $userId, $args) use ($term) {
        $target = $cap === 'assign_term' ? get_term((int) ($args[0] ?? 0)) : null;
        return $target && !is_wp_error($target) && strpos($target->slug, 'review-copy') === 0 ? ['do_not_allow'] : $caps;
    };
    $beforeDeniedCopy = wp_get_object_terms($copyPost, 'mcp_topic', ['fields' => 'ids']);
    add_filter('map_meta_cap', $denyCopiedTerm, PHP_INT_MAX, 4);
    try {
        check(is_wp_error(callTax('copy-term', ['id' => $term['id'], 'expected_hash' => $newCopyPreview['expected_hash']])), 'Denied new copy term assigned');
        check(wp_get_object_terms($copyPost, 'mcp_topic', ['fields' => 'ids']) === $beforeDeniedCopy, 'Denied new copy changed assignments');
    } finally { remove_filter('map_meta_cap', $denyCopiedTerm, PHP_INT_MAX); }
    $denyCopyPost = static function ($caps, $cap, $userId, $args) use ($copyPost) { return $cap === 'edit_post' && (int) ($args[0] ?? 0) === $copyPost ? ['do_not_allow'] : $caps; };
    $copyCount = wp_count_terms(['taxonomy' => 'mcp_topic', 'hide_empty' => false]);
    add_filter('map_meta_cap', $denyCopyPost, PHP_INT_MAX, 4);
    try { check(is_wp_error(callTax('copy-term', ['id' => $term['id'], 'expected_hash' => $copyPreview['expected_hash']])), 'Copy accepted denied native target'); }
    finally { remove_filter('map_meta_cap', $denyCopyPost, PHP_INT_MAX); }
    check(wp_count_terms(['taxonomy' => 'mcp_topic', 'hide_empty' => false]) === $copyCount, 'Denied copy created a term');


    check(is_wp_error(callTax('set-term-relations', ['id' => $term['id'], 'synonyms' => ['Related'], 'linked_ids' => [], 'expected_hash' => $relation['expected_hash']])), 'Duplicate synonym accepted');
    $unchanged = success(callTax('get-term-relations', ['id' => $term['id']]), 'Relationship after invalid mixed patch');
    check($unchanged['linked_ids'] === [$other['id']] && $unchanged['synonyms'] === ['Reviewing'], 'Invalid mixed relationship patch wrote');
    $taxonomy = success(callTax('get-taxonomy', ['name' => 'mcp_topic']), 'Ordering taxonomy revision');
    success(callTax('save-taxonomy', ['name' => 'mcp_topic', 'fields' => (object) ['enable_taxopress_ordering' => true, 'orderby' => 'taxopress_term_order'], 'expected_hash' => $taxonomy['expected_hash']]), 'Enable ordering');
    $order = success(callTax('get-term-order', ['taxonomy' => 'mcp_topic']), 'Order discovery');
    $order = success(callTax('set-term-order', ['taxonomy' => 'mcp_topic', 'term_ids' => [$other['id'], $term['id']], 'expected_hash' => $order['expected_hash']]), 'Native partial order');
    check(array_slice(array_map('intval', $order['saved_order']), 0, 2) === [$other['id'], $term['id']], 'Order readback');
}
$selection = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Bulk current revision');
$beforeAssignments = wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']);
check(is_wp_error(callTax('bulk-set-post-terms', ['items' => [['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [], 'expected_hash' => $selection['expected_hash']], ['id' => 999999, 'taxonomy' => 'mcp_topic', 'term_ids' => [], 'expected_hash' => str_repeat('0', 64)]]])), 'Invalid bulk accepted');
check(wp_get_object_terms($fixturePostId, 'mcp_topic', ['fields' => 'ids']) === $beforeAssignments, 'Bulk preflight wrote a valid sibling');
$secondPost = wp_insert_post(['post_title' => 'Second bulk fixture', 'post_status' => 'draft']);
$firstToken = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Partial first token');
$secondToken = success(callTax('get-post-terms', ['id' => $secondPost]), 'Partial second token');
$afterFirstWrite = false;
$markFirstWrite = static function ($id) use ($fixturePostId, &$afterFirstWrite) { if ((int) $id === $fixturePostId) { $afterFirstWrite = true; } };
$denyAfterFirstWrite = static function ($caps, $cap, $userId, $args) use ($secondPost, &$afterFirstWrite) { return $afterFirstWrite && $cap === 'edit_post' && (int) ($args[0] ?? 0) === $secondPost ? ['do_not_allow'] : $caps; };
add_action('set_object_terms', $markFirstWrite);
add_filter('map_meta_cap', $denyAfterFirstWrite, PHP_INT_MAX, 4);
try {
    $partial = success(callTax('bulk-set-post-terms', ['items' => [
        ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [], 'expected_hash' => $firstToken['expected_hash']],
        ['id' => $secondPost, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $secondToken['expected_hash']],
    ]]), 'Honest native partial bulk');
    check($partial['complete'] === false && $partial['failed_id'] === $secondPost && count($partial['results']) === 1, 'Partial bulk claimed completion or lost saved result');
} finally { remove_action('set_object_terms', $markFirstWrite); remove_filter('map_meta_cap', $denyAfterFirstWrite, PHP_INT_MAX); }
// Keep an association for CSV permission tests.
wp_set_object_terms($fixturePostId, [$term['id']], 'mcp_topic');
$selection = success(callTax('get-post-terms', ['id' => $fixturePostId]), 'Revision before external term change');
wp_set_object_terms($fixturePostId, [], 'mcp_topic');
check(is_wp_error(callTax('set-post-terms', ['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [$term['id']], 'expected_hash' => $selection['expected_hash']])), 'Stale post revision accepted');
wp_set_object_terms($fixturePostId, [$term['id']], 'mcp_topic');
$deny = static function ($caps, $cap) { return in_array($cap, ['manage_categories', 'admin_simple_tags', 'edit_post'], true) ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $deny, PHP_INT_MAX, 2);
try {
    check(is_wp_error(callTax('get-plugin-settings')), 'Denied administrator read settings');
    check(is_wp_error(callTax('save-term', ['taxonomy' => 'mcp_topic', 'id' => $term['id'], 'fields' => (object) ['name' => 'Denied'], 'expected_hash' => $term['expected_hash']])), 'Denied administrator edited term');
    check(is_wp_error(callTax('get-post-terms', ['id' => $fixturePostId])), 'Denied administrator read post');
} finally { remove_filter('map_meta_cap', $deny, PHP_INT_MAX); }
check(get_term($term['id'])->name === 'Review', 'Denied change persisted');
$export = success(callTax('export-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post']), 'Native CSV export');
check(strpos($export['csv'], 'Review') !== false, 'CSV missing term name');
$beforeTransfer = wp_count_terms(['taxonomy' => 'mcp_topic', 'hide_empty' => false]);
success(callTax('import-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post', 'csv' => $export['csv'], 'expected_hash' => $export['expected_hash'], 'dry_run' => true]), 'Native CSV preview');
check(wp_count_terms(['taxonomy' => 'mcp_topic', 'hide_empty' => false]) === $beforeTransfer, 'CSV preview wrote terms');
$denyTransferTerm = static function ($caps, $cap, $userId, $args) use ($term) { return $cap === 'assign_term' && (int) ($args[0] ?? 0) === $term['id'] ? ['do_not_allow'] : $caps; };
$beforeRejectedImport = get_term($term['id']);
add_filter('map_meta_cap', $denyTransferTerm, PHP_INT_MAX, 4);
try {
    check(is_wp_error(callTax('import-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post', 'csv' => $export['csv'], 'expected_hash' => $export['expected_hash'], 'update_existing' => true])), 'Denied term CSV assignment accepted');
    check(get_term($term['id']) == $beforeRejectedImport, 'Rejected CSV patch changed term');
} finally { remove_filter('map_meta_cap', $denyTransferTerm, PHP_INT_MAX); }
$import = success(callTax('import-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post', 'csv' => $export['csv'], 'expected_hash' => $export['expected_hash']]), 'Native CSV import');
check($import['complete'], 'Native CSV import reported failure');
$freshExport = success(callTax('export-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post']), 'New-term CSV revision');
$deniedNewSlug = 'mcp-denied-new-assignment';
$newCsv = 'slug,name,description,post_ids' . "\n" . $deniedNewSlug . ',Denied new assignment' . ',Native creation,' . $fixturePostId . "\n";
$denyNewTerm = static function ($caps, $cap, $userId, $args) use ($deniedNewSlug) {
    $target = $cap === 'assign_term' ? get_term((int) ($args[0] ?? 0)) : null;
    return $target && !is_wp_error($target) && $target->slug === $deniedNewSlug ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $denyNewTerm, PHP_INT_MAX, 4);
try {
    $newImport = success(callTax('import-terms', ['taxonomy' => 'mcp_topic', 'post_type' => 'post', 'csv' => $newCsv, 'expected_hash' => $freshExport['expected_hash']]), 'New-term permission recheck');
    $newTerm = get_term_by('slug', $deniedNewSlug, 'mcp_topic');
    check($newImport['complete'] === false && $newImport['result']['created'] === 1, 'New-term partial import reported incorrectly');
    check($newTerm && !has_term($newTerm->term_id, 'mcp_topic', $fixturePostId), 'New term assigned despite denied object permission');
} finally { remove_filter('map_meta_cap', $denyNewTerm, PHP_INT_MAX); }
$logs = success(callTax('list-autoterms-logs'), 'Native log summaries');
check(strpos(wp_json_encode($logs), 'synthetic-secret') === false, 'Saved credential leaked in logs');
$featureSettings = success(callTax('get-plugin-settings'), 'Feature gate token');
$featureSettings = success(callTax('update-plugin-settings', ['settings' => (object) ['active_mass_edit' => false, 'active_st_terms' => false, 'active_st_posts' => false], 'expected_hash' => $featureSettings['expected_hash']]), 'Disable gated features');
check(is_wp_error(callTax('get-term', ['id' => $term['id']])), 'Disabled Terms feature remained available');
check(is_wp_error(callTax('list-posts')), 'Disabled Posts feature remained available');
check(is_wp_error(callTax('bulk-set-post-terms', ['items' => [['id' => $fixturePostId, 'taxonomy' => 'mcp_topic', 'term_ids' => [], 'expected_hash' => $selection['expected_hash']]]])), 'Disabled Mass Edit remained available');
$featureSettings = success(callTax('update-plugin-settings', ['settings' => (object) ['active_mass_edit' => true, 'active_st_terms' => true, 'active_st_posts' => true], 'expected_hash' => $featureSettings['expected_hash']]), 'Restore gated features');
require __DIR__ . '/maintenance-cases.php';
$scopeFile = getenv('TAXONOMIES_MCP_CAPABILITIES_FILE');
if ($scopeFile && is_readable($scopeFile)) {
    require_once $scopeFile;
    update_option('cme_capabilities_application_password_capabilities', true);
    PP_Capabilities_Application_Password_Capabilities::instance();
    $password = WP_Application_Passwords::create_new_application_password(1, ['name' => 'Taxonomies isolated scope']);
    check(!is_wp_error($password), 'Scoped password creation');
    $uuid = $password[1]['uuid'];
    $key = pp_capabilities_application_password_storage_key(1, $uuid);
    update_option(pp_capabilities_application_password_option_name(), [$key => ['user_id' => 1, 'uuid' => $uuid, 'denied' => ['admin_simple_tags' => false, 'simple_tags' => false]]]);
    do_action('application_password_did_authenticate', get_userdata(1), $password[1]);
    check(is_wp_error(callTax('get-plugin-settings')), 'Application-password settings bypass');
    check(is_wp_error(callTax('list-terms', ['taxonomy' => 'mcp_topic'])), 'Application-password management bypass');
    check(is_wp_error(callTax('preview-cleanup-terms', ['taxonomy' => 'mcp_manage_destination', 'below_count' => 1])), 'Application-password maintenance bypass');
    check(is_wp_error(callTax('preview-autoterms', ['id' => $fixturePostId, 'preset_id' => $auto['id']])), 'Application-password Auto Terms capability bypass');
    update_option(pp_capabilities_application_password_option_name(), []);
    update_option('cme_capabilities_application_password_capabilities', false);
    WP_Application_Passwords::delete_application_password(1, $uuid);
    unset($password);
}
foreach (wp_get_abilities() as $ability) {
    if (strpos($ability->get_name(), 'publishpress-taxonomies/') === 0) {
        $meta = $ability->get_meta();
        check(empty($meta['public']) && empty($meta['mcp']['public']) && empty($meta['show_in_rest']), 'Public ability discovery');
    }
}
$editorId = wp_insert_user(['user_login' => 'mcp-editor', 'user_pass' => 'isolated-password', 'role' => 'editor']);
wp_set_current_user($editorId);
success(callTax('list-taxonomies'), 'Native Editor taxonomy discovery');
success(callTax('list-features'), 'Native Editor feature discovery');
check(is_wp_error(callTax('get-plugin-settings')), 'Editor site settings allowed');
wp_set_current_user(1);
wp_set_current_user(wp_create_user('mcp-subscriber', 'isolated-password', 'subscriber@example.test'));
check(is_wp_error(callTax('list-features')), 'Subscriber discovery allowed');
wp_set_current_user(1);
echo 'PASS ' . $assertions . ' assertions (' . getenv('TAXONOMIES_MCP_MODE') . ")\n";
