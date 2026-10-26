<?php
// Standalone integration harness: real provider method with mocked WordPress HTTP/storage.
require $argv[1];
$catalog = [
    (object) ['term_id' => 11, 'name' => 'Precision Sniper, DMR, ELR & Anti-Materiel Rifles'],
    (object) ['term_id' => 12, 'name' => 'Fire Control Groups & Aftermarket Triggers'],
    (object) ['term_id' => 13, 'name' => 'Under $500'],
    (object) ['term_id' => 14, 'name' => 'Apples &amp; Pears'],
];
$meta = [];
$calls = 0;
$raw = '{"term_ids":[11,12,13,14,11]}';
function esc_html__($text, $domain) { return $text; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function current_user_can($capability) { global $manager; return $manager ?? true; }
function get_terms($args) { global $catalog; return $catalog; }
function is_wp_error($value) { return false; }
function sanitize_textarea_field($text) { return $text; }
function sanitize_text_field($text) { return $text; }
function stripslashes_deep($text) { return $text; }
function wp_get_post_terms($id, $taxonomy, $args) { return []; }
function get_post_meta($id, $key, $single) { global $meta; return $meta[$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { global $meta; $meta[$id][$key] = $value; }
function wp_remote_post($url, $args) {
    global $raw, $calls, $sent;
    ++$calls;
    $sent = json_decode($args['body'], true);
    return ['body' => json_encode(['choices' => [['message' => ['content' => $raw]]]])];
}
function wp_remote_retrieve_response_code($response) { return 200; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$args = [
    'post_id' => 42,
    'taxonomy' => 'post_tag',
    'content' => 'Precision Sniper. Fire Control Groups. Apples Pears. Under $500.',
    'clean_content' => 'Precision Sniper. Fire Control Groups. Apples Pears. Under $500.',
    'content_source' => 'test',
    'settings_data' => [
        'open_ai_api_key' => 'mock-key',
        'open_ai_model' => 'gpt-5',
        'open_ai_cache_result' => 1,
        'open_ai_tag_prompt' => "Scan {content}; choose from {site_terms}",
    ],
];
$result = TaxoPressAiApi::get_open_ai_results($args);
check($result['results'] === array_column($catalog, 'name'), 'Full response path preserves commas, hyphens, dollars, entities; deduplicates IDs');
check(strpos($sent['messages'][0]['content'], '"id":11') !== false, 'Request includes catalog IDs');
check(strpos($sent['messages'][0]['content'], 'named component') !== false, 'Request includes general compound-topic rule');
check(strpos($sent['messages'][0]['content'], 'Apples & Pears') !== false, 'Prompt decodes HTML entities without changing stored name');
TaxoPressAiApi::get_open_ai_results($args);
check($calls === 1, 'Identical request uses cache');
$args['settings_data']['open_ai_tag_prompt'] .= ' changed';
TaxoPressAiApi::get_open_ai_results($args);
check($calls === 2, 'Prompt change invalidates cache');
$args['settings_data']['open_ai_model'] = 'gpt-5-mini';
TaxoPressAiApi::get_open_ai_results($args);
check($calls === 3, 'Model change invalidates cache');
$catalog[0]->name .= ' Updated';
TaxoPressAiApi::get_open_ai_results($args);
check($calls === 4, 'Catalog change invalidates cache');
$args['settings_data']['open_ai_cache_result'] = 0;
$raw = '{"term_ids":[9999]}';
check(TaxoPressAiApi::get_open_ai_results($args)['results'] === [], 'Unknown IDs rejected');
$raw = '{"term_ids":["11"]}';
check(TaxoPressAiApi::get_open_ai_results($args)['results'] === [], 'String IDs rejected');
$raw = '{"term_ids":{"named":11}}';
check(TaxoPressAiApi::get_open_ai_results($args)['status'] === 'error', 'Non-list ID object rejected');
$raw = 'Precision Sniper, DMR, ELR & Anti-Materiel Rifles';
check(TaxoPressAiApi::get_open_ai_results($args)['status'] === 'error', 'Invalid JSON gives visible error');
$raw = '{"term_ids":[]}';
check(TaxoPressAiApi::get_open_ai_results($args)['results'] === [], 'Empty selection assigns nothing');
$args['settings_data']['open_ai_tag_prompt'] = 'Extract tags from {content}';
$raw = 'Norway, Arctic';
check(TaxoPressAiApi::get_open_ai_results($args)['results'] === ['Norway', 'Arctic'], 'Legacy new-term prompt keeps its existing behavior');
$catalog[] = (object) ['term_id' => 15, 'name' => 'Variable Magnified Scopes (LPVO, MPVO & Precision)'];
$args['settings_data']['open_ai_tag_prompt'] = 'Scan {content}; choose from {site_terms}';
$args['settings_data']['open_ai_cache_result'] = 1;
$args['content'] = $args['clean_content'] = 'LPVO';
$args['screen_source'] = 'st_autoterms';
$raw = '{"term_ids":[15]}';
$result = TaxoPressAiApi::get_open_ai_results($args);
check($result['results'] === ['Variable Magnified Scopes (LPVO, MPVO & Precision)'], 'LPVO-selected ID keeps parentheses and comma intact');
check($result['diagnostics']['returned_ids'] === [15], 'Preview exposes returned IDs');
check($result['diagnostics']['resolved_names'] === $result['results'], 'Preview exposes resolved names');
check($result['diagnostics']['raw_responses'][0]['content'] === $raw, 'Preview exposes original model output');
check(strpos($sent['messages'][0]['content'], 'inside parentheses') !== false, 'Prompt includes parenthetical aliases rule');
$before_calls = $calls;
TaxoPressAiApi::get_open_ai_results($args);
check($calls === $before_calls + 1, 'Diagnostic preview bypasses cached results');
$raw = '<script>alert("test")</script>';
$result = TaxoPressAiApi::get_open_ai_results($args);
$html = TaxoPressAiApi::get_preview_diagnostics_html($result);
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Raw output escaped in diagnostic HTML');
check(strpos(json_encode($meta), '<script>') === false, 'Raw diagnostics not stored in post meta');
$manager = false;
$raw = '{"term_ids":[15]}';
$result = TaxoPressAiApi::get_open_ai_results($args);
check(!isset($result['diagnostics']), 'Non-manager cannot receive diagnostics');
check(TaxoPressAiApi::get_preview_diagnostics_html(['diagnostics' => ['test' => true]]) === '', 'Renderer also checks manager capability');
