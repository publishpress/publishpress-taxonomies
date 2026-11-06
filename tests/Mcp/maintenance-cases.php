<?php
/** Native maintenance workflows through the released Adapter, loaded by smoke.php. */
function maintenanceTaxonomy($name)
{
    $discovery = success(callTax('list-taxonomies'), 'Maintenance taxonomy discovery');
    return success(callTax('save-taxonomy', ['name' => $name, 'fields' => (object) ['label' => $name, 'singular_label' => $name, 'hierarchical' => true, 'object_types' => ['post']], 'expected_hash' => $discovery['new_expected_hash']]), 'Create maintenance taxonomy');
}
function maintenanceTerm($taxonomy, $name, $parent = 0)
{
    $list = success(callTax('list-terms', ['taxonomy' => $taxonomy]), 'Maintenance term revision');
    return success(callTax('save-term', ['taxonomy' => $taxonomy, 'id' => 0, 'fields' => (object) ['name' => $name, 'parent' => $parent], 'expected_hash' => $list['new_expected_hash']]), 'Create maintenance term');
}
$src = 'mcp_manage_source';
$dst = 'mcp_manage_destination';
maintenanceTaxonomy($src);
maintenanceTaxonomy($dst);
$parentTerm = maintenanceTerm($src, 'Old parent');
$childTerm = maintenanceTerm($src, 'Old child', $parentTerm['id']);
$keepTerm = maintenanceTerm($dst, 'Unrelated destination');
$maintenancePost = wp_insert_post(['post_title' => 'Maintenance published', 'post_status' => 'publish']);
$maintenanceDraft = wp_insert_post(['post_title' => 'Maintenance draft', 'post_status' => 'draft']);
wp_set_object_terms($maintenancePost, [$childTerm['id']], $src);
update_term_meta($childTerm['id'], 'mcp_maintenance_metadata', wp_slash('Preserve literal \ path'));
$moveRequest = ['source' => $src, 'destination' => $dst, 'term_ids' => [$parentTerm['id'], $childTerm['id']]];
check(is_wp_error(callTax('preview-move-terms', ['source' => $src, 'destination' => $dst, 'term_ids' => [$childTerm['id']]])), 'Orphaned hierarchy move accepted');
check(is_wp_error(callTax('preview-move-terms', ['source' => $src, 'destination' => $dst, 'term_ids' => [(string) $parentTerm['id']]])), 'Numeric string migration ID accepted');
update_option('default_term_' . $src, $parentTerm['id']);
check(is_wp_error(callTax('preview-move-terms', $moveRequest)), 'Default term move accepted');
delete_option('default_term_' . $src);
$collision = wp_insert_term('Old parent', $dst);
check(is_wp_error(callTax('preview-move-terms', $moveRequest)), 'Destination conflict accepted');
wp_delete_term($collision['term_id'], $dst);
register_taxonomy('mcp_page_only', ['page'], ['public' => true, 'show_ui' => true, 'hierarchical' => true]);
check(is_wp_error(callTax('preview-move-terms', array_replace($moveRequest, ['destination' => 'mcp_page_only']))), 'Incompatible destination post type accepted');
register_taxonomy('mcp_denied_destination', ['post'], ['public' => true, 'show_ui' => true, 'capabilities' => ['assign_terms' => 'mcp_disallowed_assignment']]);
check(is_wp_error(callTax('preview-move-terms', array_replace($moveRequest, ['destination' => 'mcp_denied_destination']))), 'Destination taxonomy assignment denial bypassed');
$movePreview = success(callTax('preview-move-terms', $moveRequest), 'Complete hierarchy preview');
check(get_term($childTerm['id'])->taxonomy === $src && count($movePreview['plan']->terms) === 2, 'Move preview wrote or omitted terms');
$denyMaintenancePost = static function ($caps, $cap, $userId, $args) use ($maintenancePost) { return $cap === 'edit_post' && (int) ($args[0] ?? 0) === $maintenancePost ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyMaintenancePost, PHP_INT_MAX, 4);
try { check(is_wp_error(callTax('move-terms', $moveRequest + ['expected_hash' => $movePreview['expected_hash']])), 'Denied post migration accepted'); }
finally { remove_filter('map_meta_cap', $denyMaintenancePost, PHP_INT_MAX); }
check(get_term($parentTerm['id'])->taxonomy === $src, 'Denied migration changed sibling term');
wp_set_object_terms($maintenanceDraft, [$parentTerm['id']], $src);
check(is_wp_error(callTax('move-terms', $moveRequest + ['expected_hash' => $movePreview['expected_hash']])), 'Stale migration relationships accepted');
if (SimpleTags_Mcp::pro()) {
    TaxoPress_Linked_Terms::addLinkedTermsRelation($childTerm['id'], 'Old child', $src, $keepTerm['id'], 'Unrelated destination', $dst);
    update_option('taxopress_term_order_' . $src, [$childTerm['id'], $parentTerm['id']]);
}
$beforeHiddenOptions = SimpleTags_Plugin::get_option();
$hiddenOptions = $beforeHiddenOptions;
$hiddenOptions['enable_hidden_terms'] = 1;
$hiddenOptions['hide-rarely'] = 2;
SimpleTags_Plugin::set_option($hiddenOptions);
SimpleTags_Hidden_Terms::get_instance()->taxopress_set_hidden_terms('post_tag', 0, [$src, $dst]);
$movePreview = success(callTax('preview-move-terms', $moveRequest), 'Fresh move preview');
$moved = success(callTax('move-terms', $moveRequest + ['expected_hash' => $movePreview['expected_hash']]), 'Native hierarchy move');
check($moved['complete'] && get_term($childTerm['id'])->taxonomy === $dst && (int) get_term($childTerm['id'])->parent === $parentTerm['id'], 'Move lost IDs or hierarchy');
check(has_term($childTerm['id'], $dst, $maintenancePost) && get_term_meta($childTerm['id'], 'mcp_maintenance_metadata', true) === 'Preserve literal \ path', 'Move lost relationship or metadata');
check(taxonomy_exists($src) && (bool) get_term($keepTerm['id']), 'Move removed source definition or unrelated destination');
check(in_array($childTerm['id'], array_map('intval', (array) get_transient('taxopress_hidden_terms_' . $dst)), true), 'Moved hidden term became visible through stale destination state');
SimpleTags_Plugin::set_option($beforeHiddenOptions);
if (SimpleTags_Mcp::pro()) {
    $rows = taxopress_get_linked_terms($childTerm['id'], '', false, 'both');
    check($rows && $rows[0]->term_taxonomy === $dst, 'Pro linked taxonomy identity stale');
    check(in_array($childTerm['id'], get_option('taxopress_term_order_' . $dst), true), 'Pro term order not migrated');
}
$replaceRequest = ['taxonomy' => $dst, 'find' => 'Old ', 'replace' => 'New '];
$collision = wp_insert_term('New parent', $dst);
check(is_wp_error(callTax('preview-replace-term-text', $replaceRequest)), 'Global replacement collision accepted');
check(get_term($childTerm['id'])->name === 'Old child', 'Invalid replacement wrote a valid sibling');
wp_delete_term($collision['term_id'], $dst);
$replacePreview = success(callTax('preview-replace-term-text', $replaceRequest), 'Literal global replacement preview');
wp_update_term($childTerm['id'], $dst, ['description' => 'Changed outside MCP']);
check(is_wp_error(callTax('replace-term-text', $replaceRequest + ['expected_hash' => $replacePreview['expected_hash']])), 'Stale replacement accepted');
$replacePreview = success(callTax('preview-replace-term-text', $replaceRequest), 'Fresh replacement preview');
$replaced = success(callTax('replace-term-text', $replaceRequest + ['expected_hash' => $replacePreview['expected_hash']]), 'Global term-name replacement');
check($replaced['complete'] && get_term($childTerm['id'])->name === 'New child' && get_term($childTerm['id'])->slug === 'old-child', 'Replacement lost identity or changed slug');
check((int) get_term($childTerm['id'])->parent === $parentTerm['id'] && has_term($childTerm['id'], $dst, $maintenancePost), 'Replacement lost hierarchy or assignments');
$matchRequest = ['taxonomy' => $dst, 'post_type' => 'post', 'match_ids' => [$parentTerm['id']], 'target_ids' => [$keepTerm['id']], 'mode' => 'replace'];
$denyTargetAssignment = static function ($caps, $cap, $userId, $args) use ($keepTerm) { return $cap === 'assign_term' && (int) ($args[0] ?? 0) === $keepTerm['id'] ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyTargetAssignment, PHP_INT_MAX, 4);
try { check(is_wp_error(callTax('preview-match-terms', $matchRequest)), 'Denied target object assignment accepted'); }
finally { remove_filter('map_meta_cap', $denyTargetAssignment, PHP_INT_MAX); }
$matchPreview = success(callTax('preview-match-terms', $matchRequest), 'Native match assignment preview');
check(in_array($maintenanceDraft, $matchPreview['plan']->posts, true), 'Matching draft omitted');
$matched = success(callTax('match-terms', $matchRequest + ['expected_hash' => $matchPreview['expected_hash']]), 'Matched assignment replacement');
check($matched['complete'] && has_term($keepTerm['id'], $dst, $maintenanceDraft) && !has_term($parentTerm['id'], $dst, $maintenanceDraft), 'Match replacement did not preserve intended selection');
$allPublished = success(callTax('preview-match-terms', ['taxonomy' => $dst, 'post_type' => 'post', 'match_ids' => [], 'target_ids' => [$keepTerm['id']], 'mode' => 'add']), 'Native published-content scope');
check(!in_array($maintenanceDraft, $allPublished['plan']->posts, true), 'Empty-match scope included drafts');
$lateFirst = maintenanceTerm($dst, 'Late first');
$lateSecond = maintenanceTerm($dst, 'Late second');
$lateRequest = ['taxonomy' => $dst, 'find' => 'Late ', 'replace' => 'Changed '];
$latePreview = success(callTax('preview-replace-term-text', $lateRequest), 'Partial replacement preview');
$changeLaterTerm = static function ($id) use ($lateFirst, $lateSecond, $dst) { if ((int) $id === $lateFirst['id']) { wp_update_term($lateSecond['id'], $dst, ['description' => 'Changed by another hook']); } };
add_action('edited_term', $changeLaterTerm, 30);
try {
    $lateResult = success(callTax('replace-term-text', $lateRequest + ['expected_hash' => $latePreview['expected_hash']]), 'Partial replacement outcome');
    check($lateResult['complete'] === false && count($lateResult['results']) === 1 && get_term($lateSecond['id'])->name === 'Late second', 'Partial failure overwrote changed later term or claimed completion');
} finally { remove_action('edited_term', $changeLaterTerm, 30); }
$unused = maintenanceTerm($src, 'Unused candidate');
$used = maintenanceTerm($src, 'Used candidate');
$protected = maintenanceTerm($src, 'Protected default');
update_option('default_term_' . $src, $protected['id']);
wp_set_object_terms($maintenancePost, [$used['id']], $src);
$cleanupRequest = ['taxonomy' => $src, 'below_count' => 1];
check(is_wp_error(callTax('preview-cleanup-terms', ['taxonomy' => $src, 'below_count' => '1'])), 'String threshold accepted');
$denyCleanupTerm = static function ($caps, $cap, $userId, $args) use ($unused) { return $cap === 'delete_term' && (int) ($args[0] ?? 0) === $unused['id'] ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyCleanupTerm, PHP_INT_MAX, 4);
try { check(is_wp_error(callTax('preview-cleanup-terms', $cleanupRequest)), 'Denied candidate deletion accepted'); }
finally { remove_filter('map_meta_cap', $denyCleanupTerm, PHP_INT_MAX); }
$locker = wp_insert_user(['user_login' => 'maintenance-locker', 'user_pass' => 'isolated-password', 'role' => 'editor']);
update_post_meta($maintenancePost, '_edit_lock', time() . ':' . $locker);
check(is_wp_error(callTax('preview-cleanup-terms', ['taxonomy' => $src, 'below_count' => 2])), 'Cleanup ignored affected post lock');
delete_post_meta($maintenancePost, '_edit_lock');
$cleanup = success(callTax('preview-cleanup-terms', $cleanupRequest), 'Native usage threshold preview');
check(in_array($protected['id'], $cleanup['plan']->protected_ids, true) && in_array($unused['id'], array_column($cleanup['plan']->terms, 'id'), true), 'Cleanup candidates/default protection incorrect');
wp_set_object_terms($maintenanceDraft, [$unused['id']], $src);
check(is_wp_error(callTax('cleanup-terms', $cleanupRequest + ['expected_hash' => $cleanup['expected_hash']])), 'Changed cleanup usage accepted');
wp_set_object_terms($maintenanceDraft, [], $src);
if (SimpleTags_Mcp::pro()) { update_option('taxopress_term_order_' . $src, [$unused['id'], $used['id'], $protected['id']]); }
$cleanup = success(callTax('preview-cleanup-terms', $cleanupRequest), 'Fresh cleanup preview');
$cleaned = success(callTax('cleanup-terms', $cleanupRequest + ['expected_hash' => $cleanup['expected_hash']]), 'Native rare-term deletion');
check($cleaned['complete'] && !get_term($unused['id']) && get_term($used['id']) && get_term($protected['id']), 'Cleanup deleted used/default term or retained candidate');
if (SimpleTags_Mcp::pro()) { check(get_option('taxopress_term_order_' . $src) === [$used['id'], $protected['id']], 'Cleanup retained deleted Pro order IDs or lost unrelated ordering'); }
$presetOptions = ['taxopress_tagclouds', 'taxopress_posttagss', 'taxopress_relatedposts', 'taxopress_autolinks', 'taxopress_autoterms'];
$beforePresetOptions = [];
foreach ($presetOptions as $option) {
    $beforePresetOptions[$option] = get_option($option, []);
    $fixturePresets = $beforePresetOptions[$option];
    $fixturePresets[9001] = ['title' => 'Native reference fixture', 'taxonomy' => $src, 'additional_taxonomies' => [$dst], 'preserve_fixture' => 'native', 'open_ai_api_key' => 'maintenance-secret'];
    update_option($option, $fixturePresets);
}
$settingsBeforeReference = SimpleTags_Plugin::get_option();
$settingsForReference = $settingsBeforeReference;
$settingsForReference['enable_metabox_administrator'] = [$src, $dst];
SimpleTags_Plugin::set_option($settingsForReference);
$renameRequest = ['source' => $src, 'new_name' => 'mcp_manage_renamed'];
$denyMigrationSettings = static function ($caps, $cap) { return $cap === 'admin_simple_tags' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyMigrationSettings, PHP_INT_MAX, 2);
try { check(is_wp_error(callTax('preview-rename-taxonomy', $renameRequest)), 'Denied site-setting migration accepted'); }
finally { remove_filter('map_meta_cap', $denyMigrationSettings, PHP_INT_MAX); }
$rename = success(callTax('preview-rename-taxonomy', $renameRequest), 'Managed taxonomy rename preview');
check(strpos(wp_json_encode($rename), 'maintenance-secret') === false, 'Migration preview leaked preset credentials');
$nativeOptions = SimpleTags_Plugin::get_option();
$nativeOptions['mcp_unrelated_revision_fixture'] = true;
SimpleTags_Plugin::set_option($nativeOptions);
check(is_wp_error(callTax('rename-taxonomy', $renameRequest + ['expected_hash' => $rename['expected_hash']])), 'Changed references did not invalidate migration');
check(!taxonomy_exists($renameRequest['new_name']), 'Stale rename created destination');
$rename = success(callTax('preview-rename-taxonomy', $renameRequest), 'Fresh taxonomy rename preview');
$renamed = success(callTax('rename-taxonomy', $renameRequest + ['expected_hash' => $rename['expected_hash']]), 'Native taxonomy rename/conversion');
check($renamed['complete'] && !taxonomy_exists($src) && taxonomy_exists($renameRequest['new_name']), 'Rename did not update definition registration');
check(get_term($used['id'])->taxonomy === $renameRequest['new_name'] && get_option('default_term_' . $renameRequest['new_name']) === $protected['id'], 'Rename lost term/default identity');
foreach ($presetOptions as $option) {
    $nativeReference = get_option($option)[9001];
    check($nativeReference['taxonomy'] === $renameRequest['new_name'] && $nativeReference['open_ai_api_key'] === 'maintenance-secret' && $nativeReference['preserve_fixture'] === 'native', 'Rename lost native reference, unrelated data or credentials: ' . $option);
}
check(SimpleTags_Plugin::get_option_value('enable_metabox_administrator') === [$renameRequest['new_name'], $dst], 'Rename left native role taxonomy reference stale');
$convertRequest = ['source' => $renameRequest['new_name'], 'destination' => $dst];
$convert = success(callTax('preview-convert-taxonomy', $convertRequest), 'Existing taxonomy conversion preview');
$converted = success(callTax('convert-taxonomy', $convertRequest + ['expected_hash' => $convert['expected_hash']]), 'Convert to existing taxonomy');
check($converted['complete'] && !taxonomy_exists($renameRequest['new_name']) && get_term($used['id'])->taxonomy === $dst && get_term($parentTerm['id']), 'Conversion lost terms or source definition retained');
foreach ($presetOptions as $option) {
    check(get_option($option)[9001]['taxonomy'] === $dst, 'Conversion left native reference stale');
    update_option($option, $beforePresetOptions[$option]);
}
check(SimpleTags_Plugin::get_option_value('enable_metabox_administrator') === [$dst], 'Conversion retained duplicate native taxonomy choices');
SimpleTags_Plugin::set_option($settingsBeforeReference);
taxopress_get_autolink_data(true);
maintenanceTaxonomy('mcp_maintenance_limit');
for ($i = 0; $i < 201; ++$i) { wp_insert_term('Bounded candidate ' . $i, 'mcp_maintenance_limit'); }
check(is_wp_error(callTax('preview-cleanup-terms', ['taxonomy' => 'mcp_maintenance_limit', 'below_count' => 1])), 'Oversized cleanup accepted');
check((int) wp_count_terms(['taxonomy' => 'mcp_maintenance_limit', 'hide_empty' => false]) === 201, 'Oversized cleanup wrote before rejecting');


maintenanceTaxonomy('mcp_partial_source');
$partialTerm = maintenanceTerm('mcp_partial_source', 'Partial migration');
$partialPresetsBefore = get_option('taxopress_autoterms', []);
$partialPresets = $partialPresetsBefore;
$partialPresets[9002] = ['title' => 'Reference save failure', 'taxonomy' => 'mcp_partial_source'];
update_option('taxopress_autoterms', $partialPresets);
$partialRename = ['source' => 'mcp_partial_source', 'new_name' => 'mcp_partial_destination'];
$partialPreview = success(callTax('preview-rename-taxonomy', $partialRename), 'Partial native reference-save preview');
$blockReferenceSave = static function ($next, $old) { return $old; };
add_filter('pre_update_option_taxopress_autoterms', $blockReferenceSave, PHP_INT_MAX, 2);
try {
    $partialError = callTax('rename-taxonomy', $partialRename + ['expected_hash' => $partialPreview['expected_hash']]);
    check(is_wp_error($partialError) && strpos($partialError->get_error_message(), 'some changes may have been saved') !== false, 'Native reference-save failure claimed an unchanged or complete result');
    check(get_term($partialTerm['id'])->taxonomy === 'mcp_partial_destination' && isset(taxopress_get_taxonomy_data()['mcp_partial_source']), 'Partial migration state incorrectly reported');
} finally { remove_filter('pre_update_option_taxopress_autoterms', $blockReferenceSave, PHP_INT_MAX); }
update_option('taxopress_autoterms', $partialPresetsBefore);
