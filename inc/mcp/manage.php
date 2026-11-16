<?php

/** Bounded native taxonomy migration and Manage Terms workflows. */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- Native direct-access guard.
defined('ABSPATH') || exit;
class SimpleTags_Mcp_Manage
{
    public static function actions()
    {
        return ['rename-taxonomy', 'convert-taxonomy', 'move-terms', 'replace-term-text', 'cleanup-terms', 'match-terms'];
    }

    public static function handles($action)
    {
        return in_array(str_replace('preview-', '', $action), self::actions(), true);
    }

    private static function deny($message = '')
    {
        return SimpleTags_Mcp::error($message ?: __('A target is unavailable or not permitted.', 'simple-tags'), 403);
    }

    private static function defaults($taxonomy)
    {
        return array_values(array_filter([(int) get_option('default_term_' . $taxonomy), $taxonomy === 'category' ? (int) get_option('default_category') : 0]));
    }

    /** Include relationships, children and metadata in the opaque revision; never export metadata. */
    private static function snapshot($id, $taxonomy, $deleting = false)
    {
        $term = SimpleTags_Mcp_Terms::term($id, $taxonomy);
        if (is_wp_error($term) || !current_user_can('assign_term', $id) || ($deleting && !current_user_can('delete_term', $id))) {
            return self::deny();
        }
        $state = SimpleTags_Mcp_Terms::state($term);
        $posts = SimpleTags_Mcp_Terms::affected($term);
        if (is_wp_error($state) || is_wp_error($posts)) {
            return is_wp_error($state) ? $state : $posts;
        }
        sort($posts);
        $children = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => $id, 'number' => 201]);
        if (is_wp_error($children) || count($children) > 200) {
            return SimpleTags_Mcp::error(__('Child-term scope exceeds the limit.', 'simple-tags'));
        }
        $child_state = [];
        foreach ($children as $child) {
            if (is_wp_error(SimpleTags_Mcp_Terms::term($child->term_id, $taxonomy)) || is_wp_error(SimpleTags_Mcp_Terms::affected($child))) {
                return self::deny();
            }
            $child_state[] = (array) $child;
        }
        $state['tt_id'] = (int) $term->term_taxonomy_id;
        $state['posts'] = $posts;
        $state['count'] = (int) $term->count;
        $state['children'] = $child_state;
        $state['metadata_revision'] = SimpleTags_Mcp::hash(get_term_meta($id));
        return $state;
    }

    private static function references()
    {
        $data = ['definitions' => taxopress_get_taxonomy_data(), 'settings' => get_option(STAGS_OPTIONS_NAME), 'orders' => []];
        foreach (SimpleTags_Mcp_Contracts::kinds() as $kind => $meta) {
            $data[$kind] = call_user_func($meta['get'], true);
        }
        return $data;
    }

    public static function execute($action, $input)
    {
        $kind = str_replace('preview-', '', $action);
        $preview = strpos($action, 'preview-') === 0;
        $migration = in_array($kind, ['rename-taxonomy', 'convert-taxonomy', 'move-terms'], true);
        $taxonomy = $migration ? $input['source'] : $input['taxonomy'];
        $caps = ['manage_terms', 'edit_terms', 'assign_terms'];
        if ($migration || $kind === 'cleanup-terms') {
            $caps[] = 'delete_terms';
        }
        if (!taxopress_current_user_can_for_taxonomy($taxonomy, $caps) || (int) SimpleTags_Plugin::get_option_value($migration ? 'active_taxonomies' : 'active_manage') !== 1) {
            return self::deny(__('Enable the native feature and its required taxonomy permissions.', 'simple-tags'));
        }
        if (!$migration && $kind !== 'match-terms' && (int) SimpleTags_Plugin::get_option_value('active_st_terms') !== 1) {
            return SimpleTags_Mcp::error(__('Enable native Terms management first.', 'simple-tags'), 409);
        }
        require_once ABSPATH . 'wp-admin/includes/template.php';
        require_once STAGS_DIR . '/inc/class.admin.manage.php';
        $states = [];
        $plan = ['operation' => $kind, 'taxonomy' => $taxonomy, 'terms' => [], 'posts' => [], 'protected_ids' => []];
        $native = self::references();
        if ($migration) {
            $result = self::migration_plan($kind, $input, $plan, $states, $native);
        } elseif ($kind === 'match-terms') {
            $result = self::match_plan($input, $plan, $states);
        } else {
            $ids = $kind === 'cleanup-terms'
                ? SimpleTags_Admin_Manage::rarely_used_term_ids($taxonomy, $input['below_count'], 201)
                : get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $input['find'], 'fields' => 'ids', 'number' => 201]);
            if (is_wp_error($ids) || count($ids) > 200) {
                return SimpleTags_Mcp::error(__('More than 200 candidate terms; narrow the operation.', 'simple-tags'));
            }
            $result = true;
            $names = [];
            foreach ($ids as $id) {
                if ($kind === 'cleanup-terms' && in_array((int) $id, self::defaults($taxonomy), true)) {
                    if (!current_user_can('edit_term', (int) $id)) {
                        return self::deny();
                    }
                    $plan['protected_ids'][] = (int) $id;
                    continue;
                }
                $state = self::snapshot((int) $id, $taxonomy, $kind === 'cleanup-terms');
                if (is_wp_error($state)) {
                    return $state;
                }
                $new_name = $kind === 'replace-term-text' ? str_replace($input['find'], $input['replace'], $state['name']) : '';
                if ($kind === 'replace-term-text') {
                    if ($new_name === $state['name']) {
                        continue;
                    }
                    $conflict = get_term_by('name', $new_name, $taxonomy);
                    if (trim($new_name) === '' || mb_strlen($new_name) > 200 || sanitize_text_field($new_name) !== $new_name || ($conflict && (int) $conflict->term_id !== (int) $id) || isset($names[mb_strtolower($new_name)])) {
                        return SimpleTags_Mcp::error(__('Replacement creates an empty, invalid or conflicting term name.', 'simple-tags'));
                    }
                    $names[mb_strtolower($new_name)] = true;
                }
                $states[$id] = $state;
                $plan['terms'][] = ['id' => (int) $id, 'name' => $state['name'], 'count' => $state['count'], 'new_name' => $new_name];
            }
        }
        if (is_wp_error($result)) {
            return $result;
        }
        $posts = $plan['posts'];
        foreach ($states as $state) {
            $posts = array_merge($posts, $state['posts'] ?? []);
        }
        $plan['posts'] = array_values(array_unique(array_map('intval', $posts)));
        sort($plan['posts']);
        if (count($plan['posts']) > 500) {
            return SimpleTags_Mcp::error(__('More than 500 affected posts; narrow the operation.', 'simple-tags'));
        }
        $post_states = [];
        foreach ($plan['posts'] as $id) {
            if (!SimpleTags_Mcp::editable_post($id)) {
                return self::deny();
            }
            $post_states[$id] = ['modified' => get_post($id)->post_modified_gmt, 'terms' => wp_get_object_terms($id, get_object_taxonomies(get_post($id)), ['fields' => 'all'])];
        }
        $request = array_diff_key($input, ['expected_hash' => true, 'dry_run' => true]);
        $hash = SimpleTags_Mcp::hash([$kind, $request, $plan, $states, $post_states, $native, self::defaults($taxonomy)]);
        if (!$preview && !hash_equals($hash, $input['expected_hash'])) {
            return SimpleTags_Mcp::error(__('The operation scope changed. Preview again.', 'simple-tags'), 409);
        }
        if ($preview || !empty($input['dry_run'])) {
            return ['plan' => (object) $plan, 'expected_hash' => $hash, 'dry_run' => true];
        }
        if ($migration) {
            return self::migrate($kind, $input, $states, $native);
        }
        $results = [];
        foreach ($kind === 'match-terms' ? $plan['assignments'] : $plan['terms'] as $item) {
            $id = $item['id'];
            if ($kind === 'match-terms') {
                $saved = SimpleTags_Mcp_Terms::execute('set-post-terms', ['id' => $id, 'taxonomy' => $taxonomy, 'term_ids' => $item['term_ids'], 'expected_hash' => $item['expected_hash']]);
            } else {
                $fresh = self::snapshot($id, $taxonomy, $kind === 'cleanup-terms');
                if (is_wp_error($fresh) || $fresh['name'] !== $states[$id]['name'] || $fresh['description'] !== $states[$id]['description'] || $fresh['slug'] !== $states[$id]['slug'] || $fresh['count'] !== $states[$id]['count'] || $fresh['posts'] !== $states[$id]['posts'] || $fresh['metadata_revision'] !== $states[$id]['metadata_revision']) {
                    return ['complete' => false, 'results' => $results, 'failed_id' => $id, 'message' => __('A remaining term changed during the operation. Preview again.', 'simple-tags')];
                }
                $current = SimpleTags_Mcp_Terms::execute('get-term', ['id' => $id, 'taxonomy' => $taxonomy]);
                $args = ['id' => $id, 'taxonomy' => $taxonomy, 'expected_hash' => $current['expected_hash'] ?? ''];
                $saved = is_wp_error($current) ? $current : SimpleTags_Mcp_Terms::execute($kind === 'cleanup-terms' ? 'delete-term' : 'save-term', $args + ['fields' => ['name' => $item['new_name']]]);
            }
            if (is_wp_error($saved) || ($kind === 'replace-term-text' && ($saved['name'] ?? null) !== $item['new_name'])) {
                return ['complete' => false, 'results' => $results, 'failed_id' => $id, 'message' => __('Some changes may have been saved. Read current values before retrying.', 'simple-tags')];
            }
            $results[] = ['id' => $id];
        }
        return ['complete' => true, 'results' => $results];
    }

    private static function migration_plan($kind, $input, &$plan, &$states, &$native)
    {
        $source = $input['source'];
        $destination = $kind === 'rename-taxonomy' ? $input['new_name'] : $input['destination'];
        $all = taxopress_get_taxonomy_data();
        if ($kind !== 'move-terms' && (!current_user_can('admin_simple_tags') || !isset($all[$source]) || in_array($source, ['category', 'post_tag', 'media_tag'], true))) {
            return self::deny(__('Only managed taxonomy definitions can be renamed or converted.', 'simple-tags'));
        }
        if ($source === $destination || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $destination)) {
            return SimpleTags_Mcp::error(__('Choose a distinct valid taxonomy slug.', 'simple-tags'));
        }
        if ($kind === 'rename-taxonomy') {
            if (taxonomy_exists($destination) || isset($all[$destination]) || in_array($destination, taxopress_reserved_taxonomies(), true)) {
                return SimpleTags_Mcp::error(__('The destination taxonomy slug is unavailable.', 'simple-tags'));
            }
            $destination_types = get_taxonomy($source)->object_type;
        } else {
            if (!taxopress_current_user_can_for_taxonomy($destination, ['manage_terms', 'edit_terms', 'assign_terms'])) {
                return self::deny();
            }
            $destination_types = get_taxonomy($destination)->object_type;
        }
        $ids = $kind === 'move-terms' ? $input['term_ids'] : get_terms(['taxonomy' => $source, 'hide_empty' => false, 'fields' => 'ids', 'number' => 201]);
        if (is_wp_error($ids) || count($ids) > 200) {
            return SimpleTags_Mcp::error(__('The conversion exceeds 200 terms.', 'simple-tags'));
        }
        foreach ($ids as $id) {
            if ($kind === 'move-terms' && in_array($id, self::defaults($source), true)) {
                return SimpleTags_Mcp::error(__('Default terms cannot be moved out of an active taxonomy.', 'simple-tags'));
            }
            $state = self::snapshot($id, $source, $kind === 'move-terms');
            if (is_wp_error($state)) {
                return $state;
            }
            $children = array_column($state['children'], 'term_id');
            if (($state['parent'] && !in_array($state['parent'], $ids, true)) || array_diff($children, $ids)) {
                return SimpleTags_Mcp::error(__('Move complete term hierarchies, including their parents and children.', 'simple-tags'));
            }
            if ($kind !== 'rename-taxonomy' && ($state['parent'] && !is_taxonomy_hierarchical($destination))) {
                return SimpleTags_Mcp::error(__('The destination cannot preserve this hierarchy.', 'simple-tags'));
            }
            if ($kind !== 'rename-taxonomy' && (get_term_by('slug', $state['slug'], $destination) || get_term_by('name', $state['name'], $destination))) {
                return SimpleTags_Mcp::error(__('A destination term has the same name or slug. Resolve the conflict first.', 'simple-tags'));
            }
            foreach ($state['posts'] as $post_id) {
                if (!in_array(get_post_type($post_id), $destination_types, true)) {
                    return SimpleTags_Mcp::error(__('A destination taxonomy does not support an affected post type.', 'simple-tags'));
                }
            }
            $states[$id] = $state;
            $plan['terms'][] = ['id' => $id, 'name' => $state['name'], 'count' => $state['count'], 'parent' => $state['parent']];
        }
        foreach ($destination_types as $type) {
            $object = get_post_type_object($type);
            if (!$object || !current_user_can($object->cap->edit_posts)) {
                return self::deny();
            }
        }
        if ($kind !== 'move-terms') {
            foreach (SimpleTags_Mcp_Contracts::kinds() as $preset_kind => $meta) {
                foreach ($native[$preset_kind] as $record) {
                    $targets = array_merge([$record['taxonomy'] ?? ''], $record['additional_taxonomies'] ?? [], $record['enable_customurl_field'] ?? []);
                    if (in_array($source, $targets, true)) {
                        foreach (array_filter($targets) as $target) {
                            if (!SimpleTags_Mcp_Operations::permitted_taxonomy($target)) {
                                return self::deny(__('A referenced configuration target is not manageable.', 'simple-tags'));
                            }
                        }
                    }
                }
            }
        }
        $plan['native_references'] = $kind === 'move-terms' ? [] : taxopress_retarget_taxonomy_references($source, $destination, false);
        $destination_object = $kind === 'rename-taxonomy' ? get_taxonomy($source) : get_taxonomy($destination);
        $plan['destination_visibility'] = ['public' => (bool) $destination_object->public, 'publicly_queryable' => (bool) $destination_object->publicly_queryable, 'show_in_rest' => (bool) $destination_object->show_in_rest];
        $native['source_registration'] = (array) get_taxonomy($source);
        $plan['destination'] = $destination;
        $plan['preserves_term_ids'] = true;
        $plan['removes_source_definition'] = $kind !== 'move-terms';
        $native['source_default'] = get_option('default_term_' . $source);
        $native['source_order'] = get_option('taxopress_term_order_' . $source, []);
        $native['destination_order'] = get_option('taxopress_term_order_' . $destination, []);
        $native['destination'] = $kind === 'rename-taxonomy' ? null : (array) get_taxonomy($destination);
        return true;
    }

    private static function match_plan($input, &$plan, &$states)
    {
        $taxonomy = $input['taxonomy'];
        $type = $input['post_type'];
        if (!post_type_exists($type) || !is_object_in_taxonomy($type, $taxonomy)) {
            return SimpleTags_Mcp::error(__('The post type does not support this taxonomy.', 'simple-tags'));
        }
        foreach (array_unique(array_merge($input['match_ids'], $input['target_ids'])) as $id) {
            $term = SimpleTags_Mcp_Terms::term($id, $taxonomy);
            $state = is_wp_error($term) ? $term : SimpleTags_Mcp_Terms::state($term);
            if (is_wp_error($state) || !current_user_can('assign_term', $id)) {
                return self::deny();
            }
            $states[$id] = $state;
        }
        // Match-any term semantics; an empty match selects native published/inherited content.
        $posts = SimpleTags_Admin_Manage::matching_post_ids($taxonomy, $input['match_ids'], $type, 501);
        if (is_wp_error($posts) || count($posts) > 500) {
            return SimpleTags_Mcp::error(__('More than 500 matching posts; narrow the match.', 'simple-tags'));
        }
        $plan['assignments'] = [];
        foreach ($posts as $id) {
            if (get_post_type($id) !== $type) {
                continue;
            }
            $post_view = SimpleTags_Mcp_Terms::execute('get-post-terms', ['id' => (int) $id]);
            if (is_wp_error($post_view)) {
                return $post_view;
            }
            $current = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($current)) {
                return SimpleTags_Mcp::error();
            }
            if ($input['mode'] === 'remove') {
                $next = array_values(array_diff($current, $input['target_ids']));
            } elseif ($input['mode'] === 'replace') {
                $next = array_values(array_unique(array_merge(array_diff($current, $input['match_ids']), $input['target_ids'])));
            } else {
                $next = array_values(array_unique(array_merge($current, $input['target_ids'])));
            }
            $changes = SimpleTags_Mcp_Terms::assignment_plan($id, [$taxonomy => $next]);
            if (is_wp_error($changes)) {
                return $changes;
            }
            $plan['assignments'][] = ['id' => (int) $id, 'term_ids' => $next, 'resulting_terms' => (object) $changes, 'expected_hash' => $post_view['expected_hash']];
            $plan['posts'][] = (int) $id;
        }
        return true;
    }

    private static function migrate($kind, $input, $states, $native)
    {
        $source = $input['source'];
        $destination = $kind === 'rename-taxonomy' ? $input['new_name'] : $input['destination'];
        if ($kind === 'rename-taxonomy') {
            $definition = $native['definitions'][$source];
            $definition['name'] = $destination;
            $form = ['cpt_custom_tax' => $definition, 'cpt_tax_labels' => $definition['labels'], 'cpt_post_types' => $definition['object_types'], 'cpt_tax_status' => 'new'];
            unset($form['cpt_custom_tax']['labels'], $form['cpt_custom_tax']['object_types']);
            $saved = taxopress_update_taxonomy(wp_slash($form));
            if ($saved !== 'add_success') {
                return SimpleTags_Mcp::error();
            }
            $registered = taxopress_get_taxonomy_data()[$destination] ?? null;
            if (!$registered) {
                return SimpleTags_Mcp::error();
            }
            // Avoid creating a second default term before the original terms are moved.
            $registered['default_term'] = '';
            taxopress_register_single_taxonomy($registered);
        }
        if (!taxopress_current_user_can_for_taxonomy($destination, ['manage_terms', 'edit_terms', 'assign_terms'])) {
            return self::deny();
        }
        foreach ($states as $id => $state) {
            $fresh = self::snapshot($id, $source, $kind === 'move-terms');
            if (is_wp_error($fresh) || $fresh !== $state) {
                return SimpleTags_Mcp::error(__('A source changed while preparing the conversion. Read current values before retrying.', 'simple-tags'), 409);
            }
        }
        $ids = array_map('intval', array_keys($states));
        $moved = taxopress_convert_taxonomy_terms($source, $destination, $ids, $kind !== 'move-terms');
        if (is_wp_error($moved) || count($moved) !== count($ids)) {
            return SimpleTags_Mcp::error();
        }
        foreach ($states as $id => $state) {
            $term = get_term($id, $destination);
            $posts = get_objects_in_term($id, $destination);
            if (is_wp_error($posts)) {
                return SimpleTags_Mcp::error();
            }
            $posts = array_map('intval', $posts);
            sort($posts);
            if (!$term || is_wp_error($term) || $term->name !== $state['name'] || $term->description !== $state['description'] || $term->slug !== $state['slug'] || (int) $term->term_taxonomy_id !== $state['tt_id'] || (int) $term->parent !== $state['parent'] || $posts !== $state['posts'] || !current_user_can('edit_term', $id) || !current_user_can('assign_term', $id) || SimpleTags_Mcp::hash(get_term_meta($id)) !== $state['metadata_revision']) {
                return SimpleTags_Mcp::error();
            }
        }
        if ($kind !== 'move-terms') {
            if ($kind === 'rename-taxonomy') {
                if ($native['source_default']) {
                    update_option('default_term_' . $destination, $native['source_default']);
                    if (get_option('default_term_' . $destination) !== $native['source_default']) {
                        return SimpleTags_Mcp::error();
                    }
                }
            }
            if (isset(taxopress_get_taxonomy_data()[$source])) {
                return SimpleTags_Mcp::error();
            }
            unregister_taxonomy($source);
            if (taxonomy_exists($source)) {
                return SimpleTags_Mcp::error();
            }
        }
        return ['complete' => true, 'results' => array_map(static function ($id) use ($destination) {
            return ['id' => $id, 'taxonomy' => $destination];
        }, $ids)];
    }
}
