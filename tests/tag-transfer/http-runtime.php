<?php
/** WP-CLI real multipart/capability/tab regression against the isolated HTTP router. */
require_once __DIR__ . '/bootstrap.php';
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Run through wp eval-file.'); }
taxopress_transfer_test_guard_runtime();

(function () {
    global $wpdb;
    $pass = 0;
    $fail = 0;
    $prefix = 'ttt-http-' . time() . '-' . strtolower(wp_generate_password(8, false, false));
    $owned_posts = array();
    $csv_path = '/tmp/' . $prefix . '.csv';
    $old_user = get_current_user_id();
    $result_key = null;
    $saved_notice = false;
    $saved_notice_deadline = false;
    $initial = null;
    $option_backup = null;
    $base = 'http://127.0.0.1:' . (getenv('TAXOPRESS_TEST_HTTP_PORT') ? getenv('TAXOPRESS_TEST_HTTP_PORT') : '19274') . '/';
    $check = function ($ok, $label) use (&$pass, &$fail) {
        if ($ok) { ++$pass; } else { ++$fail; }
        echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
        if (!$ok) { throw new RuntimeException($label); }
    };
    $state = function () use ($wpdb) {
        $result = array();
        foreach (array(
            "SELECT * FROM {$wpdb->posts} ORDER BY ID",
            "SELECT t.*, tt.* FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='post_tag' ORDER BY t.term_id",
            "SELECT r.* FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id WHERE tt.taxonomy='post_tag' ORDER BY r.object_id,r.term_taxonomy_id",
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name NOT LIKE '_transient_taxopress_tags_result_%' AND option_name NOT LIKE '_transient_timeout_taxopress_tags_result_%' ORDER BY option_name",
        ) as $sql) {
            $result[] = $wpdb->get_results($sql, ARRAY_A);
            if ($wpdb->last_error) { throw new RuntimeException($wpdb->last_error); }
        }
        return $result;
    };
    $request = function ($query = '', $fields = null) use ($base) {
        $curl = curl_init($base . $query);
        curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array('X-Taxopress-Test-Token: ' . getenv('TAXOPRESS_TEST_HTTP_TOKEN')), CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => ''));
        if ($fields !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        }
        try {
            $body = curl_exec($curl);
            if ($body === false) { throw new RuntimeException(curl_error($curl)); }
            return array((int) curl_getinfo($curl, CURLINFO_HTTP_CODE), $body);
        } finally { curl_close($curl); }
    };
    $metadata = function () use ($request) {
        $response = $request();
        $value = json_decode($response[1], true);
        if ($response[0] !== 200 || !is_array($value) || !isset($value['nonce'])) {
            throw new RuntimeException('Local router metadata failed.');
        }
        return $value;
    };
    try {
        $initial = $state();
        $option_backup = tempnam(sys_get_temp_dir(), 'taxopress-options-backup-');
        if (!$option_backup || file_put_contents($option_backup, serialize($initial[3])) === false) {
            throw new RuntimeException('Cannot persist pre-bootstrap option backup.');
        }
        chmod($option_backup, 0600);
        echo 'PRE_BOOTSTRAP_OPTIONS_BACKUP=' . $option_backup . "\n";
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID'));
        $check(!empty($admins), 'Local administrator exists');
        wp_set_current_user((int) $admins[0]);
        $result_key = 'taxopress_tags_result_' . get_current_user_id();
        $saved_notice = get_transient($result_key);
        $saved_notice_deadline = get_option('_transient_timeout_' . $result_key);
        $post = wp_insert_post(array('post_title' => $prefix . ' draft', 'post_type' => 'post', 'post_status' => 'draft', 'post_content' => $prefix), true);
        $check(!is_wp_error($post) && $post > 0, 'Create owned HTTP draft');
        $owned_posts[] = (int) $post;
        $revision = wp_insert_post(array('post_title' => $prefix . ' revision', 'post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post), true);
        $check(!is_wp_error($revision) && $revision > 0, 'Create owned unsupported revision');
        $owned_posts[] = (int) $revision;
        $missing = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}") + 10000;
        $check(!get_post($missing), 'HTTP missing ID absent');
        $extra = wp_insert_term($prefix . ' unrelated', 'post_tag', array('slug' => $prefix . '-extra'));
        $check(!is_wp_error($extra), 'Create unrelated owned HTTP tag');
        $extra_id = (int) $extra['term_id'];
        $check(!is_wp_error(wp_set_object_terms($post, array($extra_id), 'post_tag', true)), 'Assign unrelated owned tag');
        $stream = fopen($csv_path, 'wb');
        if (!$stream) { throw new RuntimeException('Cannot write disposable upload CSV.'); }
        try {
            foreach (array(array('slug','name','description','post_ids'),
                array($prefix . '-first', $prefix . ' first', '', (string) $post),
                array($prefix . '-second', $prefix . ' second', '', $post . '|' . $missing . '|' . $revision)) as $row) {
                fputcsv($stream, $row);
            }
        } finally { fclose($stream); }
        $fixture_state = $state();
        $meta = $metadata();
        $plugin_dir = taxopress_transfer_test_plugin_dir();
        $check($meta['version'] === '4.0.0' && realpath($meta['manage_source']) === realpath($plugin_dir . '/inc/class.admin.manage.php'), 'Full bootstrap uses integrated 4.0.0 Manage Terms, not active legacy plugin');
        $check($meta['simple_tags'] && $meta['import_registered'] && $meta['export_registered'], 'Existing admin capability and real integrated action hooks available');
        $check($meta['source_hashes']['service'] === hash_file('sha256', $plugin_dir . '/inc/class-taxopress-tag-csv-service.php')
            && $meta['source_hashes']['adapter'] === hash_file('sha256', $plugin_dir . '/inc/class-taxopress-tag-transfer.php')
            && $meta['source_hashes']['manage'] === hash_file('sha256', $plugin_dir . '/inc/class.admin.manage.php'), 'Router tests exact selected checkout hashes');
        echo 'HTTP_SOURCE_HASHES=' . wp_json_encode($meta['source_hashes']) . "\n";
        $navigation = function ($html) {
            preg_match_all('/<li\b[^>]*class="([^"]*\bnav-tab\b[^"]*)"[^>]*data-page="([^"]+)"/', $html, $matches, PREG_SET_ORDER);
            $tabs = array();
            $active = array();
            foreach ($matches as $match) {
                $tabs[] = $match[2];
                if (strpos($match[1], 'nav-tab-active') !== false) { $active[] = $match[2]; }
            }
            return array($tabs, $active);
        };
        $view = $request('?view=1');
        $nav = $navigation($view[1]);
        $expected_tabs = array('.st-import-export-tags', '.st-add-terms', '.st-remove-terms', '.st-rename-terms', '.st-merge-terms', '.st-delete-unuused-terms');
        $check($view[0] === 200 && $nav[0] === $expected_tabs && $nav[1] === array('.st-import-export-tags'), 'Import/Export first and default; all existing tabs retained in order');
        foreach (array('add-terms', 'remove-terms', 'rename-terms', 'merge-terms', 'delete-unuused-terms') as $tab) {
            $view = $request('?view=1&tab=' . $tab);
            $nav = $navigation($view[1]);
            $check($view[0] === 200 && $nav[1] === array('.st-' . $tab), 'Existing explicit tab preserved: ' . $tab);
        }
        $view = $request('?view=1&tab=not-a-tool');
        $nav = $navigation($view[1]);
        $check($nav[1] === array('.st-import-export-tags'), 'Unknown tab safely defaults to first tab');
        $check($state() === $fixture_state, 'Full bootstrap and page renders leave persisted options/posts/tags/relationships unchanged');
        $fields = array('action' => 'taxopress_import_tags', 'taxopress_tags_nonce' => $meta['nonce'], 'taxopress_tags_csv' => new CURLFile($csv_path, 'text/csv', 'fixture.csv'));
        foreach (array('simple_tags', 'manage_terms', 'edit_terms') as $cap) {
            $response = $request('?deny_cap=' . $cap, $fields);
            $check($response[0] === 403 && $state() === $fixture_state, 'Import requires capability ' . $cap . ' with zero writes');
        }
        $response = $request('?feature_disabled=1', $fields);
        $check($response[0] === 403 && $state() === $fixture_state, 'Disabled Manage Terms feature rejects endpoint');
        $response = $request('?registration_disabled=1');
        $disabled = json_decode($response[1], true);
        $check(is_array($disabled) && !$disabled['import_registered'] && !$disabled['export_registered'], 'Disabled feature does not register transfer endpoints');
        $response = $request('?action=taxopress_import_tags&taxopress_tags_nonce=' . $meta['nonce']);
        $check($response[0] === 405 && $state() === $fixture_state, 'Import rejects GET before any writes');
        $bad_nonce = $fields;
        $bad_nonce['taxopress_tags_nonce'] = 'invalid-test-nonce';
        $response = $request('', $bad_nonce);
        $check($response[0] === 403 && $state() === $fixture_state, 'Import rejects wrong nonce with zero writes');
        $export_fields = array('action' => 'taxopress_export_tags', 'taxopress_tags_nonce' => $meta['export_nonce']);
        $response = $request('?action=taxopress_export_tags&taxopress_tags_nonce=' . $meta['export_nonce']);
        $check($response[0] === 405, 'Export requires POST');
        $wrong_export = $export_fields;
        $wrong_export['taxopress_tags_nonce'] = 'invalid-test-nonce';
        $check($request('', $wrong_export)[0] === 403, 'Export requires valid nonce');
        $check($request('?deny_cap=simple_tags', $export_fields)[0] === 403, 'Export requires simple_tags');
        $check($request('?deny_cap=manage_terms', $export_fields)[0] === 403, 'Export requires taxonomy manage_terms');
        $export_response = $request('', $export_fields);
        $check($export_response[0] === 200 && strpos($export_response[1], "\xEF\xBB\xBFslug,name,description,post_ids") === 0, 'Real export hook downloads four-column CSV');
        $response = $request('', $fields);
        $check($response[0] === 303, 'Strict genuine multipart upload redirects to validation result');
        $result = $metadata()['result'];
        $check(is_array($result) && $result['errors'] === 1, 'Unchecked checkbox strictly rejects missing/unsupported targets');
        $check($state() === $fixture_state, 'Strict HTTP rejection creates no earlier tags or associations');
        $fields['taxopress_tags_skip_missing_posts'] = '1';
        $response = $request('?deny_edit=' . $post, $fields);
        $check($response[0] === 303, 'Permission failure uses real handler result redirect');
        $result = $metadata()['result'];
        $check(is_array($result) && $result['errors'] === 1
            && strpos(implode(' ', $result['error_messages']), 'permission to edit post ID') !== false,
            'Checked skip mode NEVER skips HTTP edit_post permission failure');
        $check($state() === $fixture_state, 'HTTP permission preflight has zero writes');
        $response = $request('?deny=1', $fields);
        $check($response[0] === 403, 'Checked skip mode still denies anonymous request');
        $check($state() === $fixture_state, 'Anonymous HTTP denial has zero writes');
        $response = $request('', $fields);
        $check($response[0] === 303, 'Checked genuine upload reaches successful real handler import');
        $result = $metadata()['result'];
        $check($result['created'] === 2 && $result['errors'] === 0 && $result['relationships_added'] === 2
            && $result['relationships_skipped'] === 2 && count($result['skipped_post_ids']) === 2,
            'Checkbox reaches parse/preflight/import: two tags, two valid associations, two skips');
        $after_skip = $state();
        $check($after_skip[0] === $fixture_state[0], 'HTTP importer leaves all post rows unchanged');
        $check(is_object_in_term($post, 'post_tag', $extra_id) === true, 'HTTP import preserves unrelated assignment');
        $response = $request('', $fields);
        $result = $metadata()['result'];
        $check($response[0] === 303 && $result['created'] === 0 && $result['skipped'] === 2
            && $result['relationships_unchanged'] === 2 && $result['relationships_skipped'] === 2,
            'Checked HTTP repeat idempotent with accurate skip counts');
        $check($state() === $after_skip, 'HTTP repeat adds no duplicate data');
        $notice_before = $metadata()['result'];
        $inactive = $request('?adapter_view=inactive');
        $check($inactive[0] === 200 && strpos($inactive[1], 'taxopress-tags-results') === false
            && $metadata()['result'] === $notice_before, 'render(false) does not show or consume result transient');
        $inactive = $request('?view=1&tab=merge-terms');
        $check($inactive[0] === 200 && strpos($inactive[1], 'taxopress-tags-results') === false
            && $metadata()['result'] === $notice_before, 'Real inactive Manage Terms tab preserves summary');
        $view = $request('?view=1');
        $check($view[0] === 200 && strpos($view[1], 'name="taxopress_tags_skip_missing_posts" value="1"') !== false, 'Actual render contains opt-in skip checkbox');
        $check(strpos($view[1], 'Relationships skipped (missing or unsupported posts): 2. Unique skipped post IDs: 2.') !== false,
            'Actual render shows correct escaped skipping summary');
        $check($metadata()['result'] === false, 'Active real Manage Terms render(true) consumes summary exactly once');
        set_transient($result_key, array('relationships_skipped' => 1, 'skipped_post_ids' => array('<script>ttt-xss</script>'), 'error_messages' => array('<img src=x onerror=ttt>')), 60);
        $view = $request('?view=1');
        $check(strpos($view[1], '&lt;script&gt;ttt-xss&lt;/script&gt;') !== false
            && strpos($view[1], '<script>ttt-xss</script>') === false
            && strpos($view[1], '&lt;img src=x onerror=ttt&gt;') !== false, 'Render escapes skipped ID and error details, not executable HTML');
    } catch (Throwable $error) {
        ++$fail;
        echo 'FAIL: ' . $error->getMessage() . "\n";
    } finally {
        foreach (array_reverse($owned_posts) as $id) {
            try { if (get_post($id)) { $check((bool) wp_delete_post($id, true), 'Cleanup owned HTTP post ' . $id); } }
            catch (Throwable $error) { ++$fail; echo 'FAIL cleanup: ' . $error->getMessage() . "\n"; }
        }
        $tag_ids = $wpdb->get_col($wpdb->prepare("SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='post_tag' AND t.slug LIKE %s", $wpdb->esc_like($prefix . '-') . '%'));
        foreach ($tag_ids as $id) {
            try { $check(wp_delete_term((int) $id, 'post_tag') === true, 'Cleanup owned HTTP tag ' . $id); }
            catch (Throwable $error) { ++$fail; echo 'FAIL cleanup: ' . $error->getMessage() . "\n"; }
        }
        if (is_file($csv_path)) { unlink($csv_path); }
        if ($result_key !== null) {
            if ($saved_notice === false) { delete_transient($result_key); }
            else { set_transient($result_key, $saved_notice, $saved_notice_deadline ? max(1, (int) $saved_notice_deadline - time()) : 0); }
        }
        wp_set_current_user($old_user);
        try { if ($initial !== null) { $check($state() === $initial, 'HTTP cleanup restores initial posts/tags/relationships'); } }
        catch (Throwable $error) { ++$fail; echo 'FAIL final: ' . $error->getMessage() . "\n"; }
        echo sprintf("SUMMARY HTTP: PASS=%d FAIL=%d\n", $pass, $fail);
    }
    if ($fail) { throw new RuntimeException('HTTP skip test failed: ' . $fail); }
})();
