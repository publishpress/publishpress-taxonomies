<?php
/** WP-CLI association/fidelity/resilient-assignment regression; disposable local fixtures only. */
require_once __DIR__ . '/bootstrap.php';
if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run service-runtime.php through wp eval-file.');
}
taxopress_transfer_test_guard_runtime();

(function () {
    global $wpdb;
    $pass = 0;
    $fail = 0;
    $prefix = 'ttt-test-' . time() . '-' . strtolower(wp_generate_password(8, false, false));
    $owned_posts = array();
    $owned_tags = array();
    $initial = null;
    $old_user = get_current_user_id();
    $importing = false;
    $import_post_events = array();
    $headers = array('slug', 'name', 'description', 'post_ids');

    $check = function ($ok, $label) use (&$pass, &$fail) {
        if ($ok) {
            ++$pass;
        } else {
            ++$fail;
        }
        echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
        return (bool) $ok;
    };
    $require = function ($ok, $label) use ($check) {
        if (!$check($ok, $label)) {
            throw new RuntimeException('Cannot continue: ' . $label);
        }
    };
    $query = function ($sql) use ($wpdb) {
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Snapshot query failed: ' . $wpdb->last_error);
        }
        return $rows;
    };
    $posts = function () use ($wpdb, $query) {
        // All columns include ID/type/title/content/status/modified and all statuses.
        return $query("SELECT * FROM {$wpdb->posts} ORDER BY ID");
    };
    $relations = function () use ($wpdb, $query) {
        return $query("SELECT r.* FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id WHERE tt.taxonomy = 'post_tag' ORDER BY r.object_id, r.term_taxonomy_id");
    };
    $state = function () use ($wpdb, $query, $posts, $relations) {
        return array(
            'posts' => $posts(),
            'tags' => $query("SELECT t.*, tt.* FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'post_tag' ORDER BY t.term_id"),
            'relations' => $relations(),
        );
    };
    $ids = function ($value) {
        $values = is_array($value) ? $value : ($value === '' ? array() : explode('|', $value));
        $result = array_map('intval', $values);
        sort($result, SORT_NUMERIC);
        return $result;
    };
    $post_ids_for_tag = function ($slug) use ($require) {
        $term = get_term_by('slug', $slug, 'post_tag');
        $require($term !== false, 'Read owned tag ' . $slug);
        $result = get_objects_in_term((int) $term->term_id, 'post_tag');
        $require(!is_wp_error($result), 'Read stored tag relationships');
        $result = array_map('intval', $result);
        sort($result, SORT_NUMERIC);
        return $result;
    };
    $parse_cells = function ($cells, $columns, $skip_missing = false) use ($state, $check) {
        $before = $state();
        $stream = fopen('php://temp', 'w+');
        if (!$stream) {
            throw new RuntimeException('Cannot open CSV stream.');
        }
        try {
            // RFC quoting preserves backslashes without PHP 7.2 escape ambiguity.
            foreach (array_merge(array($columns), $cells) as $record) {
                $quoted = array();
                foreach ($record as $cell) {
                    $quoted[] = '"' . str_replace('"', '""', $cell) . '"';
                }
                if (fwrite($stream, implode(',', $quoted) . "\r\n") === false) {
                    throw new RuntimeException('Cannot write test CSV.');
                }
            }
            rewind($stream);
            $result = TaxoPress_Tag_CSV_Service::parse_csv($stream, $skip_missing);
        } finally {
            fclose($stream);
        }
        $check($state() === $before, 'Parsing creates no posts/tags/relationships or content changes');
        return $result;
    };
    $observe_post = function ($id, $post, $update) use (&$importing, &$import_post_events) {
        if ($importing) {
            $import_post_events[] = array((int) $id, (bool) $update);
        }
    };
    $observe_tag = function ($id, $tt_id, $taxonomy) use (&$owned_tags, $prefix) {
        if ($taxonomy !== 'post_tag') {
            return;
        }
        $term = get_term($id, 'post_tag', OBJECT, 'raw');
        if ($term && !is_wp_error($term) && strpos($term->slug, $prefix . '-') === 0) {
            $owned_tags[(int) $id] = true;
        }
    };
    $import = function ($rows, $update = false, $expect_failure = false, $skip_missing = false) use (
        $posts, $check, $require, &$importing, &$import_post_events, &$owned_posts, $prefix, $ids
    ) {
        foreach ($rows as $row) {
            $require(strpos($row['slug'], $prefix . '-') === 0 && strpos($row['name'], $prefix) !== false, 'Import only disposable tag row');
            if (!$expect_failure && isset($row['post_ids'])) {
                foreach ($ids($row['post_ids']) as $id) {
                    $require(isset($owned_posts[$id]) || !get_post($id), 'Successful import targets only owned posts or truly absent skipped IDs');
                }
            }
        }
        $before = $posts();
        $events_before = count($import_post_events);
        $importing = true;
        try {
            $result = TaxoPress_Tag_CSV_Service::import_rows($rows, $update, $skip_missing);
        } finally {
            $importing = false;
            $check($posts() === $before, 'Importer leaves ALL post rows unchanged (IDs/type/title/content/status/modified)');
            $check(count($import_post_events) === $events_before, 'Importer never creates/updates posts, including temporary creations');
        }
        $require(is_array($result) && isset($result['created'], $result['updated'], $result['skipped'], $result['errors'], $result['error_messages'], $result['relationships_added'], $result['relationships_unchanged']), 'Import result tag/relationship count API');
        $check($expect_failure ? $result['errors'] > 0 : $result['errors'] === 0, 'Expected import error count');
        if (!$expect_failure) {
            $check($result['error_messages'] === array(), 'No import error details');
            $check($result['created'] + $result['updated'] + $result['skipped'] === count($rows), 'Tag result counts account for all rows');
        }
        return $result;
    };
    add_action('created_term', $observe_tag, 10, 3);
    add_action('wp_insert_post', $observe_post, 10, 3);

    try {
        $initial = $state();
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ID'));
        $require(!empty($admins), 'Existing local administrator available');
        wp_set_current_user((int) $admins[0]);
        taxopress_transfer_test_load_service();
        $require(class_exists('TaxoPress_Tag_CSV_Service'), 'Copied service loaded');
        $require(is_callable(array('TaxoPress_Tag_CSV_Service', 'validate_assignments')), 'Actual public assignment-preflight API loaded');

        $drafts = array();
        for ($i = 1; $i <= 2; ++$i) {
            $id = wp_insert_post(array('post_type' => 'post', 'post_status' => 'draft',
                'post_title' => $prefix . ' draft ' . $i,
                'post_content' => $prefix . " content, \"quoted\"\nLiteral \\backslash " . $i), true);
            $require(!is_wp_error($id) && $id > 0, 'Create owned draft ' . $i);
            $owned_posts[(int) $id] = true;
            $drafts[] = (int) $id;
        }
        sort($drafts, SORT_NUMERIC);
        $accent = "Caf\xC3\xA9 na\xC3\xAFve";
        $fixtures = array(
            'a' => array('slug' => $prefix . '-a', 'name' => $prefix . ' ' . $accent . ', "quoted"',
                'description' => $accent . ", \"quotes\"\r\nLine 2 \\path\\file\nLine 3", 'posts' => $drafts),
            'b' => array('slug' => $prefix . '-b', 'name' => '=SUM(1,2) ' . $prefix,
                'description' => "=SUM(1,2)\n+123\\literal\n-456\n@formula", 'posts' => array($drafts[0])),
            'c' => array('slug' => $prefix . '-c', 'name' => "'" . $prefix . ' apostrophe',
                'description' => "'Literal leading apostrophe\nBackslash \\ and comma, \"quoted\"", 'posts' => array()),
        );
        $baseline = array();
        foreach ($fixtures as $key => $fixture) {
            $require(!term_exists($fixture['name'], 'post_tag') && !get_term_by('slug', $fixture['slug'], 'post_tag'), 'Fixture does not preexist');
            $written = wp_insert_term(wp_slash($fixture['name']), 'post_tag', wp_slash(array('slug' => $fixture['slug'], 'description' => $fixture['description'])));
            $require(!is_wp_error($written), 'Create owned fixture tag ' . $key);
            $term_id = (int) $written['term_id'];
            $owned_tags[$term_id] = true;
            foreach ($fixture['posts'] as $post_id) {
                $require(!is_wp_error(wp_set_object_terms($post_id, array($term_id), 'post_tag', true)), 'Assign owned fixture tag');
            }
            $raw = get_term($term_id, 'post_tag', OBJECT, 'raw');
            $baseline[$key] = array('slug' => $raw->slug, 'name' => $raw->name, 'description' => $raw->description);
            $check($raw->name === $fixture['name'] && $raw->description === $fixture['description'], 'Fixture fidelity reaches raw WP storage');
        }

        $before_parse = $state();
        $stream = fopen('php://temp', 'w+');
        $require(is_resource($stream), 'Open export stream');
        try {
            $export_count = TaxoPress_Tag_CSV_Service::write_csv($stream);
            $require(is_int($export_count), 'Export returns tag count');
            rewind($stream);
            $context = fgetcsv($stream);
            if (isset($context[0])) {
                $context[0] = preg_replace('/^\xEF\xBB\xBF/', '', $context[0]);
            }
            $require($context === array('taxopress_terms_csv', '1', 'post_tag', 'post'), 'Export records selected taxonomy and post type');
            $header = fgetcsv($stream);
            $require($header === $headers, 'Exact four export columns; no post content columns');
            $raw_csv_rows = array();
            while (($record = fgetcsv($stream)) !== false) {
                $require(count($record) === 4, 'Export record contains only four fields');
                $raw_csv_rows[$record[0]] = $record;
            }
            foreach ($fixtures as $key => $fixture) {
                $require(isset($raw_csv_rows[$fixture['slug']]), 'Raw CSV contains fixture ' . $key);
                $expected_name = $key === 'a' ? $baseline[$key]['name'] : "'" . $baseline[$key]['name'];
                $expected_description = $key === 'a' ? $baseline[$key]['description'] : "'" . $baseline[$key]['description'];
                $check($raw_csv_rows[$fixture['slug']][1] === $expected_name, 'Raw name formula/apostrophe escaping ' . $key);
                $check($raw_csv_rows[$fixture['slug']][2] === $expected_description, 'Raw description formula/apostrophe escaping ' . $key);
                $check($raw_csv_rows[$fixture['slug']][3] === implode('|', $fixture['posts']), 'Raw fourth column uses exact pipe-separated IDs ' . $key);
            }
            rewind($stream);
            $export_rows = TaxoPress_Tag_CSV_Service::parse_csv($stream);
            $require(is_array($export_rows), 'Contextual four-column export parses');
        } finally {
            fclose($stream);
        }
        $check($state() === $before_parse, 'Export/parse performs no database mutations');
        $check($export_count === count($before_parse['tags']) && count($export_rows) === $export_count, 'Export includes every tag, including unused');
        $filtered = array();
        foreach ($fixtures as $key => $fixture) {
            foreach ($export_rows as $row) {
                if ($row['slug'] === $fixture['slug']) {
                    $require(isset($row['post_ids']) && is_string($row['post_ids']), 'Parsed association field is pipe-delimited string');
                    $filtered[$key] = $row;
                }
            }
            $require(isset($filtered[$key]), 'Export contains fixture ' . $key);
            foreach ($baseline[$key] as $field => $value) {
                $check($filtered[$key][$field] === $value, 'Exact CSV fidelity ' . $key . ' ' . $field);
            }
            $check($ids($filtered[$key]['post_ids']) === $post_ids_for_tag($fixture['slug']), 'Export IDs match stored associations ' . $key);
            $check($ids($filtered[$key]['post_ids']) === $fixture['posts'], 'Export retains unchanged owned post IDs ' . $key);
            $check(count($ids($filtered[$key]['post_ids'])) === count(array_unique($ids($filtered[$key]['post_ids']))), 'No duplicate exported IDs');
        }

        $helper_before = $state();
        $check(TaxoPress_Tag_CSV_Service::validate_assignments(array_values($filtered), true) === true, 'Admin passes assignment permission preflight');
        try {
            wp_set_current_user(0);
            $denied = TaxoPress_Tag_CSV_Service::validate_assignments(array_values($filtered), true);
            $check(is_wp_error($denied)
                && $denied->get_error_code() !== ''
                && strpos($denied->get_error_message(), __('You do not have permission to assign terms in the selected taxonomy. No terms or assignments were written.', 'simple-tags')) !== false,
                'User 0 denied by opt-in assignment permission helper with row-specific permission error');
            $check(TaxoPress_Tag_CSV_Service::validate_assignments(array_values($filtered), false) === true, 'Trusted structural preflight does not invent service authorization');
            $permission_stream = fopen('php://temp', 'w+');
            $require(is_resource($permission_stream), 'Open denied export permission stream');
            try {
                $denied_export = TaxoPress_Tag_CSV_Service::write_csv($permission_stream, true);
                $check(is_wp_error($denied_export)
                    && $denied_export->get_error_code() !== ''
                    && $denied_export->get_error_message() === __('You cannot read every associated post. Export was cancelled without downloading a partial file.', 'simple-tags'),
                    'User 0 export denied for unreadable associated drafts');
            } finally {
                fclose($permission_stream);
            }
        } finally {
            wp_set_current_user((int) $admins[0]);
        }
        $permission_stream = fopen('php://temp', 'w+');
        $require(is_resource($permission_stream), 'Open authorized export permission stream');
        try {
            $check(TaxoPress_Tag_CSV_Service::write_csv($permission_stream, true) === $export_count, 'Restored admin passes read_post export permission checks');
        } finally {
            fclose($permission_stream);
        }
        $check($state() === $helper_before, 'Authorized/unauthorized helper and export checks perform no writes');

        // D is an extra relationship outside imported rows; C stays unused.
        $extra = wp_insert_term($prefix . ' extra D', 'post_tag', array('slug' => $prefix . '-d'));
        $require(!is_wp_error($extra), 'Create unrelated owned fixture D');
        $extra_id = (int) $extra['term_id'];
        $owned_tags[$extra_id] = true;
        $require(!is_wp_error(wp_set_object_terms($drafts[0], array($extra_id), 'post_tag', true)), 'Assign extra D');
        foreach (array('a', 'b') as $key) {
            $term = get_term_by('slug', $fixtures[$key]['slug'], 'post_tag');
            foreach ($fixtures[$key]['posts'] as $id) {
                $require(!is_wp_error(wp_remove_object_terms($id, array((int) $term->term_id), 'post_tag')), 'Remove only owned A/B relationship');
            }
            $require(wp_delete_term($term->term_id, 'post_tag') === true, 'Delete only owned A/B tag');
        }
        $result = $import(array_values($filtered));
        $check($result['created'] === 2 && $result['skipped'] === 1 && $result['updated'] === 0, 'Restore creates A/B and skips existing C');
        $check($result['relationships_added'] === 3 && $result['relationships_unchanged'] === 0, 'Restore reports exactly three new associations');
        foreach ($fixtures as $fixture) {
            $check($post_ids_for_tag($fixture['slug']) === $fixture['posts'], 'Exact restored associations ' . $fixture['slug']);
        }
        foreach ($baseline as $expected) {
            $raw = get_term_by('slug', $expected['slug'], 'post_tag', OBJECT, 'raw');
            $check($raw && $raw->name === $expected['name'] && $raw->description === $expected['description'], 'Restored Unicode/quotes/multiline/backslash/formula fidelity');
        }
        $check($post_ids_for_tag($prefix . '-d') === array($drafts[0]), 'Restore preserves unrelated D relationship');

        $a = get_term_by('slug', $fixtures['a']['slug'], 'post_tag');
        $require(!is_wp_error(wp_remove_object_terms($drafts[1], array((int) $a->term_id), 'post_tag')), 'Remove one owned A relation');
        $result = $import(array($filtered['a']));
        $check($result['created'] === 0 && $result['updated'] === 0 && $result['skipped'] === 1, 'Default-skip tag row still restores associations');
        $check($result['relationships_added'] === 1 && $result['relationships_unchanged'] === 1, 'Skipped tag reports one added and one unchanged association');
        $check($post_ids_for_tag($fixtures['a']['slug']) === $drafts, 'Missing relation restored even when tag skipped');
        $stable = $state();
        $result = $import(array_values($filtered));
        $check($result['relationships_added'] === 0 && $result['relationships_unchanged'] === 3, 'Repeat reports all three associations unchanged');
        $check($state() === $stable, 'Repeat import idempotent: no duplicate tags/relationships');

        $changed = $filtered['a'];
        $changed['name'] = $prefix . ' updated ' . $accent;
        $changed['description'] = "Updated \"quoted\"\n\\backslash";
        $result = $import(array($changed), true);
        $check($result['updated'] === 1 && $result['created'] === 0, 'Update changes existing tag only');
        $check($result['relationships_added'] === 0 && $result['relationships_unchanged'] === 2, 'Update retains both existing associations');
        $a_after = get_term_by('slug', $fixtures['a']['slug'], 'post_tag', OBJECT, 'raw');
        $check((int) $a_after->term_id === (int) $a->term_id && $a_after->name === $changed['name'] && $a_after->description === $changed['description'], 'Update preserves tag ID and exact content');
        $check($relations() === $stable['relations'], 'Update preserves all other relationships');

        $legacy = $parse_cells(array(array($a_after->slug, $a_after->name, $a_after->description)), array('slug', 'name', 'description'));
        $require(is_array($legacy), 'Legacy three-column CSV accepted');
        $legacy_relations = $relations();
        $result = $import($legacy, true);
        $check($result['relationships_added'] === 0 && $result['relationships_unchanged'] === 0, 'Legacy import performs no assignment work');
        $check($relations() === $legacy_relations, 'Legacy import does not change any assignments');

        foreach (array('0', '-1', '1.5', 'abc', '922337203685477580899999', $drafts[0] . '|' . $drafts[0], '01', $drafts[0] . '|', ' ' . $drafts[0]) as $bad_ids) {
            $bad = $parse_cells(array(array($prefix . '-bad', $prefix . ' invalid', 'Invalid IDs', $bad_ids)), $headers);
            $check(is_wp_error($bad), 'Reject malformed fourth column: ' . $bad_ids);
        }

        $revision = wp_insert_post(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $drafts[0],
            'post_title' => $prefix . ' incompatible revision', 'post_content' => $prefix . ' revision'), true);
        $require(!is_wp_error($revision) && $revision > 0, 'Create owned incompatible revision fixture');
        $owned_posts[(int) $revision] = true;
        $require(!is_object_in_taxonomy('revision', 'post_tag'), 'Revision does not support standard tags');
        $max_post_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
        $missing_id = $max_post_id + 10000;
        $require(!get_post($missing_id), 'Missing ID truly absent');
        foreach (array('missing' => $missing_id, 'incompatible' => (int) $revision) as $label => $invalid_id) {
            $good = $filtered['a'];
            $good['slug'] = $prefix . '-preflight-good-' . $label;
            $good['name'] = $prefix . ' valid first ' . $label;
            $bad = $filtered['b'];
            $bad['slug'] = $prefix . '-preflight-bad-' . $label;
            $bad['name'] = $prefix . ' invalid later ' . $label;
            $bad['post_ids'] = (string) $invalid_id;
            $csv_bad = $parse_cells(array(
                array($good['slug'], $good['name'], $good['description'], $good['post_ids']),
                array($bad['slug'], $bad['name'], $bad['description'], $bad['post_ids']),
            ), $headers);
            $check(is_array($csv_bad), 'CSV parser retains terms with a later ' . $label . ' content ID');
            $before = $state();
            $result = $import(array($good, $bad));
            $check($result['created'] === 2 && $result['updated'] === 0 && $result['skipped'] === 0, 'A later ' . $label . ' content ID does not block either term');
            $check($result['relationships_added'] > 0 && $result['relationships_skipped'] === 1, 'Valid assignments continue while the ' . $label . ' assignment is skipped');
            $check($state() !== $before, 'Resilient import writes the valid term and assignment data');
        }

        // Same missing/unsupported IDs recur across rows: four skipped relations,
        // but only two unique skipped post IDs. Valid drafts must still attach.
        $skip_cells = array(
            array($prefix . '-skip-x', $prefix . ' skip X', 'Skip fixture X', $drafts[0] . '|' . $missing_id . '|' . $revision),
            array($prefix . '-skip-y', $prefix . ' skip Y', 'Skip fixture Y', $drafts[1] . '|' . $missing_id . '|' . $revision),
        );
        $parse_before = $state();
        $skip_rows = $parse_cells($skip_cells, $headers);
        $require(is_array($skip_rows) && count($skip_rows) === 2, 'Default parser accepts mixed valid/missing/unsupported targets');
        $check($skip_rows[0]['post_ids'] === $skip_cells[0][3] && $skip_rows[1]['post_ids'] === $skip_cells[1][3], 'Parser preserves IDs for accurate skipped counts');
        $check($state() === $parse_before, 'Resilient assignment preflight remains read-only');

        $permission_before = $state();
        $check(TaxoPress_Tag_CSV_Service::validate_assignments($skip_rows, true, true) === true, 'Admin permission preflight accepts skip mode');
        try {
            wp_set_current_user(0);
            $denied = TaxoPress_Tag_CSV_Service::validate_assignments($skip_rows, true, true);
            $check(is_wp_error($denied) && $denied->get_error_code() !== ''
                && strpos($denied->get_error_message(), __('You do not have permission to assign terms in the selected taxonomy. No terms or assignments were written.', 'simple-tags')) !== false,
                'Skip NEVER suppresses assign_terms permission denial');
            // Same gate as request handler; trusted importer is not an auth layer.
            if (!is_wp_error($denied)) {
                $check(false, 'Permission gate unexpectedly allowed importer');
            }
        } finally {
            wp_set_current_user((int) $admins[0]);
        }
        $deny_edit = function ($caps, $cap, $user_id, $args) use ($drafts) {
            if ($cap === 'edit_post' && isset($args[0]) && (int) $args[0] === $drafts[1]) {
                return array('do_not_allow');
            }
            return $caps;
        };
        add_filter('map_meta_cap', $deny_edit, 999, 4);
        try {
            $check(current_user_can(get_taxonomy('post_tag')->cap->assign_terms), 'Edit-denial test retains assign_terms permission');
            $denied = TaxoPress_Tag_CSV_Service::validate_assignments($skip_rows, true, true);
            $check(is_wp_error($denied) && $denied->get_error_code() !== ''
                && strpos($denied->get_error_message(), sprintf(__('You do not have permission to edit content ID %d. No terms or assignments were written.', 'simple-tags'), $drafts[1])) !== false,
                'Skip NEVER suppresses later edit_post permission denial');
        } finally {
            remove_filter('map_meta_cap', $deny_edit, 999);
        }
        $check($state() === $permission_before, 'Both permission failures preflight with zero tags/posts/assignment writes');

        $relations_before_skip = $relations();
        $result = $import($skip_rows, false, true);
        $require(isset($result['relationships_skipped'], $result['skipped_post_ids']), 'Actual 1.2.0 skip result API');
        $check($result['created'] === 2 && $result['updated'] === 0 && $result['skipped'] === 0, 'Unchecked skip option still creates both tags');
        $check($result['relationships_added'] === 2 && $result['relationships_unchanged'] === 0 && $result['relationships_skipped'] === 4, 'Unchecked skip option adds two valid and skips four missing/unsupported relations');
        $check($result['errors'] === 1 && count($result['error_messages']) === 1, 'Unchecked skip option reports one aggregate error without aborting the import');
        $expected_skipped = array($missing_id, (int) $revision);
        sort($expected_skipped, SORT_NUMERIC);
        $reported_skipped = $result['skipped_post_ids'];
        sort($reported_skipped, SORT_NUMERIC);
        $check($reported_skipped === $expected_skipped && count($result['skipped_post_ids']) === 2, 'Internal skipped IDs contain missing and unsupported targets exactly once');
        $check($post_ids_for_tag($prefix . '-skip-x') === array($drafts[0])
            && $post_ids_for_tag($prefix . '-skip-y') === array($drafts[1]), 'Only valid unchanged draft IDs attached');
        $relations_after_skip = $relations();
        foreach ($relations_before_skip as $relation) {
            $check(in_array($relation, $relations_after_skip, true), 'Skip mode preserves unrelated existing relationship');
        }
        $skip_stable = $state();
        $result = $import($skip_rows, false, false, true);
        $check($result['created'] === 0 && $result['skipped'] === 2 && $result['relationships_added'] === 0
            && $result['relationships_unchanged'] === 2 && $result['relationships_skipped'] === 4, 'Skip reimport is idempotent with accurate unchanged/skipped counts');
        $check($state() === $skip_stable, 'Skip reimport adds no duplicate tags or relationships');
        $updated_skip = $skip_rows[0];
        $updated_skip['name'] = $prefix . ' skip X updated';
        $updated_skip['description'] = "Updated skip description\n\\literal";
        $result = $import(array($updated_skip), true, false, true);
        $check($result['updated'] === 1 && $result['relationships_unchanged'] === 1 && $result['relationships_skipped'] === 2, 'Skip update changes tag while retaining honest association counts');
        $check($relations() === $skip_stable['relations'], 'Skip update preserves unrelated and existing relationships');
        $legacy_skip = $parse_cells(array(array($prefix . '-legacy-skip', $prefix . ' legacy skip', 'Tags only')), array('slug', 'name', 'description'), true);
        $require(is_array($legacy_skip), 'Legacy three-column CSV accepts skip toggle');
        $legacy_before_skip = $relations();
        $result = $import($legacy_skip, false, false, true);
        $check($result['created'] === 1 && $result['relationships_added'] === 0 && $result['relationships_skipped'] === 0
            && $result['skipped_post_ids'] === array(), 'Legacy skip import creates tag with no assignment work');
        $check($relations() === $legacy_before_skip, 'Legacy skip toggle leaves all associations unchanged');
        foreach (array('0', '-1', 'abc', $drafts[0] . '|' . $drafts[0]) as $bad_ids) {
            $bad_skip = $parse_cells(array(array($prefix . '-skip-invalid', $prefix . ' skip invalid', '', $bad_ids)), $headers, true);
            $check(is_wp_error($bad_skip), 'Skip does not relax malformed/duplicate ID validation: ' . $bad_ids);
        }

        foreach (array('a', 'b', 'c', 'd') as $key) {
            $term = get_term_by('slug', $prefix . '-' . $key, 'post_tag', OBJECT, 'raw');
            $require($term !== false, 'Count fixture exists');
            $stored_ids = $post_ids_for_tag($term->slug);
            $expected_ids = $key === 'd' ? array($drafts[0]) : $fixtures[$key]['posts'];
            $check($stored_ids === $expected_ids, 'Final exact association set ' . $key);
            $check((int) $term->count === 0, 'Accurate core published-post count for draft-only fixture ' . $key);
        }
    } catch (Throwable $error) {
        $check(false, get_class($error) . ': ' . $error->getMessage());
    } finally {
        foreach (array_reverse(array_keys($owned_posts)) as $id) {
            try {
                $post = get_post($id);
                if ($post) {
                    if (strpos($post->post_title, $prefix) === false) {
                        $check(false, 'Refusing cleanup of post without owned prefix ' . $id);
                        continue;
                    }
                    $check(wp_delete_post($id, true) !== false, 'Cleanup owned post ' . $id);
                }
            } catch (Throwable $error) {
                $check(false, 'Post cleanup ' . $id . ': ' . $error->getMessage());
            }
        }
        foreach (array_keys($owned_tags) as $id) {
            try {
                $term = get_term($id, 'post_tag', OBJECT, 'raw');
                if ($term && !is_wp_error($term)) {
                    if (strpos($term->slug, $prefix . '-') !== 0) {
                        $check(false, 'Refusing cleanup of tag without owned prefix ' . $id);
                        continue;
                    }
                    $check(wp_delete_term($id, 'post_tag') === true, 'Cleanup owned tag ' . $id);
                }
            } catch (Throwable $error) {
                $check(false, 'Tag cleanup ' . $id . ': ' . $error->getMessage());
            }
        }
        remove_action('created_term', $observe_tag, 10);
        remove_action('wp_insert_post', $observe_post, 10);
        wp_set_current_user($old_user);
        try {
            if ($initial !== null) {
                $check($state() === $initial, 'Cleanup complete; pre-existing posts/tags/relationships unchanged');
            }
        } catch (Throwable $error) {
            $check(false, 'Final snapshot: ' . $error->getMessage());
        }
        echo sprintf("SUMMARY: PASS=%d FAIL=%d\n", $pass, $fail);
    }
    if ($fail > 0) {
        throw new RuntimeException('Association integration test failed: ' . $fail . ' checks.');
    }
})();
