<?php

/** Taxonomy and post object operations using WordPress and native Taxonomies services. */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- Native direct-access guard.
defined('ABSPATH') || exit;
class SimpleTags_Mcp_Terms
{
    public static function term($id, $taxonomy = '')
    {
        $term = get_term($id, $taxonomy);
        if (!$term || is_wp_error($term) || !SimpleTags_Mcp_Operations::permitted_taxonomy($term->taxonomy, 'edit_terms') || !current_user_can('edit_term', $term->term_id)) {
            return SimpleTags_Mcp::error(__('Term unavailable or not permitted.', 'simple-tags'), 403);
        }
        return $term;
    }

    public static function affected($term)
    {
        $ids = get_objects_in_term($term->term_id, $term->taxonomy);
        if (is_wp_error($ids) || count($ids) > 500) {
            return SimpleTags_Mcp::error(__('This operation exceeds the bounded object scope. Use native administration.', 'simple-tags'));
        }
        foreach ($ids as $id) {
            if (!SimpleTags_Mcp::editable_post($id)) {
                return SimpleTags_Mcp::error(__('An affected post is not editable or is locked.', 'simple-tags'), 403);
            }
        }
        return array_map('intval', $ids);
    }

    private static function links($id)
    {
        $ids = [];
        if (SimpleTags_Mcp::pro() && taxopress_is_linked_terms_enabled()) {
            if (!TaxoPress_Linked_Terms_Schema::tableExists(TaxoPress_Linked_Terms_Schema::tableName())) {
                return SimpleTags_Mcp::error(__('Initialize Linked Terms in native administration first.', 'simple-tags'), 409);
            }
            $rows = taxopress_get_linked_terms($id, '', false, 'both', 1001);
            if (count($rows) > 1000) {
                return SimpleTags_Mcp::error(__('Linked term scope is too large.', 'simple-tags'));
            }
            foreach ($rows as $row) {
                $data = taxopress_get_linked_term_data($row, $id);
                $term = self::term((int) $data->term_id);
                if (is_wp_error($term)) {
                    return $term;
                }
                $ids[] = (int) $term->term_id;
            }
        }
        sort($ids, SORT_NUMERIC);
        return array_values(array_unique($ids));
    }

    public static function state($term)
    {
        $links = self::links($term->term_id);
        if (is_wp_error($links)) {
            return $links;
        }
        $data = ['id' => (int) $term->term_id, 'taxonomy' => $term->taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent' => (int) $term->parent, 'custom_url' => (string) get_term_meta($term->term_id, 'taxopress_custom_url', true), 'linked_ids' => $links];
        if (SimpleTags_Mcp::pro() && taxopress_is_synonyms_enabled()) {
            $data['synonyms'] = array_values(taxopress_get_term_synonyms($term->term_id));
        }
        return $data;
    }

    private static function view($term)
    {
        $data = self::state($term);
        if (is_wp_error($data)) {
            return $data;
        }
        $data['expected_hash'] = SimpleTags_Mcp::hash($data);
        return $data;
    }

    public static function execute($action, $input)
    {
        if (in_array($action, ['preview-copy-term', 'copy-term'], true)) {
            return self::copy($action, $input);
        }
        if ($action === 'list-posts') {
            if ((int) SimpleTags_Plugin::get_option_value('active_st_posts') !== 1) {
                return SimpleTags_Mcp::error(__('Enable Posts first.', 'simple-tags'), 409);
            }
            $types = get_post_types(['public' => true]);
            unset($types['attachment']);
            if (isset($input['post_type']) && !isset($types[$input['post_type']])) {
                return SimpleTags_Mcp::error(__('Invalid native post type.', 'simple-tags'));
            }
            if (isset($input['term_id']) && is_wp_error(self::term($input['term_id']))) {
                return SimpleTags_Mcp::error(__('Filter term is not permitted.', 'simple-tags'), 403);
            }
            $result = taxopress_get_posts_data($input + ['per_page' => 25]);
            $items = [];
            foreach ($result['posts'] as $post) {
                if (!current_user_can('read_post', $post->ID) || !current_user_can('edit_post', $post->ID)) {
                    continue;
                }
                $items[] = ['id' => (int) $post->ID, 'title' => $post->post_title, 'post_type' => $post->post_type, 'status' => $post->post_status];
            }
            return ['items' => $items];
        }
        if ($action === 'list-autoterms-logs') {
            if (!(int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
                return SimpleTags_Mcp::error(__('Enable Auto Terms first.', 'simple-tags'));
            }
            $logs = taxopress_autoterms_logs_data($input['per_page'] ?? 25, $input['page'] ?? 1);
            $items = [];
            foreach ($logs['posts'] as $log) {
                $id = (int) get_post_meta($log->ID, '_taxopress_log_post_id', true);
                $taxonomy = (string) get_post_meta($log->ID, '_taxopress_log_taxonomy', true);
                if (!current_user_can('read_post', $id) || !current_user_can('edit_post', $id) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms')) {
                    continue;
                }
                $items[] = ['id' => (int) $log->ID, 'post_id' => $id, 'taxonomy' => $taxonomy, 'date_gmt' => $log->post_date_gmt, 'status' => (string) get_post_meta($log->ID, '_taxopress_log_status', true)];
            }
            return ['items' => $items];
        }
        if (in_array($action, ['export-terms', 'import-terms'], true)) {
            return self::transfer($action, $input);
        }
        if (in_array($action, ['preview-merge-terms', 'merge-terms'], true)) {
            return self::merge($action, $input);
        }
        if (in_array($action, ['get-term-order', 'set-term-order'], true)) {
            return self::order($action, $input);
        }
        if (in_array($action, ['preview-autoterms', 'apply-autoterms'], true)) {
            return self::autoterms($action, $input);
        }
        if ($action === 'bulk-set-post-terms') {
            if ((int) SimpleTags_Plugin::get_option_value('active_mass_edit') !== 1) {
                return SimpleTags_Mcp::error(__('Enable Mass Edit first.', 'simple-tags'), 409);
            }
            return self::bulk($input);
        }
        if (in_array($action, ['get-post-terms', 'set-post-terms', 'set-post-features'], true)) {
            return self::post($action, $input);
        }
        if (in_array($action, ['list-terms', 'get-term', 'save-term', 'delete-term'], true) && (int) SimpleTags_Plugin::get_option_value('active_st_terms') !== 1) {
            return SimpleTags_Mcp::error(__('Enable Terms management first.', 'simple-tags'), 409);
        }
        if ($action === 'list-terms') {
            $taxonomy = $input['taxonomy'];
            if (!SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms')) {
                return SimpleTags_Mcp::error(__('Taxonomy not permitted.', 'simple-tags'), 403);
            }
            $limit = $input['per_page'] ?? 25;
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $input['search'] ?? '', 'number' => $limit, 'offset' => (($input['page'] ?? 1) - 1) * $limit]);
            if (is_wp_error($terms)) {
                return SimpleTags_Mcp::error();
            }
            $items = [];
            foreach ($terms as $term) {
                if (is_wp_error(self::term($term->term_id, $taxonomy))) {
                    continue;
                } $view = self::view($term);
                if (is_wp_error($view)) {
                    if (($view->get_error_data()['status'] ?? 400) !== 403) {
                        return $view;
                    }
                    continue;
                }
                $items[] = $view;
            }
            return ['items' => $items, 'new_expected_hash' => SimpleTags_Mcp::hash(['taxonomy' => $taxonomy])];
        }
        $id = $input['id'];
        $taxonomy = $input['taxonomy'] ?? '';
        $term = $id ? self::term($id, $taxonomy) : null;
        if (is_wp_error($term)) {
            return $term;
        }
        if ($term) {
            $taxonomy = $term->taxonomy;
        }
        if (!SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms')) {
            return SimpleTags_Mcp::error(__('Insufficient taxonomy permission.', 'simple-tags'), 403);
        }
        $state = $term ? self::state($term) : ['taxonomy' => $taxonomy];
        if (is_wp_error($state)) {
            return $state;
        }
        if (in_array($action, ['get-term-relations', 'get-term'], true)) {
            return self::view($term);
        }
        if (SimpleTags_Mcp::stale($input, $state)) {
            return SimpleTags_Mcp::error(__('Term changed. Read it again.', 'simple-tags'), 409);
        }
        if ($term) {
            $affected = self::affected($term);
            if (is_wp_error($affected)) {
                return $affected;
            }
        }
        if ($action === 'set-term-relations') {
            return self::relations($term, $state, $input);
        }
        if ($action === 'delete-term') {
            if (!current_user_can('delete_term', $id) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'delete_terms')) {
                return SimpleTags_Mcp::error(__('Deletion is not permitted.', 'simple-tags'), 403);
            }
            $children = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => $id, 'number' => 501]);
            if (is_wp_error($children) || count($children) > 500) {
                return SimpleTags_Mcp::error(__('Child-term scope is too large.', 'simple-tags'));
            }
            foreach ($children as $child) {
                if (is_wp_error(self::term($child->term_id)) || is_wp_error(self::affected($child))) {
                    return SimpleTags_Mcp::error(__('A child term is not editable.', 'simple-tags'), 403);
                }
            }
            foreach ($state['linked_ids'] as $linked_id) {
                if (is_wp_error(self::affected(get_term($linked_id)))) {
                    return SimpleTags_Mcp::error(__('A related object is not editable.', 'simple-tags'), 403);
                }
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'id' => $id];
            }
            $result = wp_delete_term($id, $taxonomy);
            if (!is_wp_error($result) && $result && SimpleTags_Mcp::pro() && taxopress_is_linked_terms_enabled()) {
                if (TaxoPress_Linked_Terms::get_instance()->deleteLinkedTermsRelation($id) === false) {
                    return SimpleTags_Mcp::error();
                }
            }
            if (!is_wp_error($result) && $result && SimpleTags_Mcp::pro() && in_array($id, array_map('intval', (array) get_option('taxopress_term_order_' . $taxonomy, [])), true)) {
                return SimpleTags_Mcp::error();
            }
            return !is_wp_error($result) && $result && !get_term($id, $taxonomy) ? ['deleted' => $id] : SimpleTags_Mcp::error();
        }
        $patch = $input['fields'];
        if (isset($patch['custom_url'])) {
            $enabled = SimpleTags_Client_Autolinks::get_enabled_taxonomies(taxopress_get_autolink_data(true));
            $url = $patch['custom_url'];
            $parts = $url === '' ? [] : wp_parse_url($url);
            if (!(int) SimpleTags_Plugin::get_option_value('active_auto_links') || !in_array($taxonomy, $enabled, true) || ($id && !current_user_can('edit_term_meta', $id, 'taxopress_custom_url'))) {
                return SimpleTags_Mcp::error(__('Native custom links are not editable for this term.', 'simple-tags'), 403);
            }
            if ($url !== '' && (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || sanitize_url($url) !== $url)) {
                return SimpleTags_Mcp::error(__('Use an HTTP or HTTPS URL without credentials, or an empty string.', 'simple-tags'));
            }
        }
        if (!$id && (empty($patch['name']) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'manage_terms'))) {
            return SimpleTags_Mcp::error(__('Provide a term name and native create permission.', 'simple-tags'));
        }
        if (isset($patch['parent']) && $patch['parent']) {
            $parent = self::term($patch['parent'], $taxonomy);
            if (is_wp_error($parent) || !is_taxonomy_hierarchical($taxonomy) || $patch['parent'] === $id || ($id && term_is_ancestor_of($id, $patch['parent'], $taxonomy))) {
                return SimpleTags_Mcp::error(__('Invalid parent term.', 'simple-tags'));
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'fields' => (object) $patch];
        }
        $native_patch = $patch;
        unset($native_patch['custom_url']);
        $result = $id ? wp_update_term($id, $taxonomy, wp_slash($native_patch)) : wp_insert_term(wp_slash($patch['name']), $taxonomy, wp_slash($native_patch));
        if (is_wp_error($result)) {
            return SimpleTags_Mcp::error(__('Native term validation or persistence failed.', 'simple-tags'));
        }
        if (isset($patch['custom_url'])) {
            if (!current_user_can('edit_term_meta', $result['term_id'], 'taxopress_custom_url')) {
                return SimpleTags_Mcp::error();
            }
            SimpleTags_Client_Autolinks::set_custom_url($result['term_id'], $patch['custom_url']);
            if ((string) get_term_meta($result['term_id'], 'taxopress_custom_url', true) !== $patch['custom_url']) {
                return SimpleTags_Mcp::error();
            }
        }
        return self::view(get_term($result['term_id'], $taxonomy));
    }

    private static function post_state($id)
    {
        if (!SimpleTags_Mcp::editable_post($id)) {
            return SimpleTags_Mcp::error(__('Post unavailable or locked.', 'simple-tags'), 403);
        }
        $post = get_post($id);
        if (!$post || wp_is_post_revision($id) || $post->post_type === 'attachment') {
            return SimpleTags_Mcp::error(__('Unsupported post object.', 'simple-tags'));
        }
        $terms = [];
        foreach (get_object_taxonomies($post) as $taxonomy) {
            if (!SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'assign_terms')) {
                continue;
            }
            $ids = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($ids)) {
                return SimpleTags_Mcp::error();
            }
            sort($ids, SORT_NUMERIC);
            if (count($ids) > 1000) {
                return SimpleTags_Mcp::error(__('Use the native editor for larger term selections.', 'simple-tags'));
            }
            $terms[$taxonomy] = array_map('intval', $ids);
        }
        require_once STAGS_DIR . '/inc/class.admin.post.php';
        $features = [];
        foreach (['autolinks' => '_exclude_autolinks', 'autoterms' => '_exclude_autotags'] as $key => $legacy) {
            $features[$key] = SimpleTags_Admin_Post_Settings::get_status($id, '_taxopress_' . $key . '_status', $legacy);
        }
        $linked = ['enabled' => SimpleTags_Mcp::pro() && taxopress_is_linked_terms_enabled(), 'direction' => SimpleTags_Plugin::get_option_value('linked_terms_type'), 'relations' => []];
        if ($linked['enabled'] && TaxoPress_Linked_Terms_Schema::tableExists(TaxoPress_Linked_Terms_Schema::tableName())) {
            foreach ($terms as $ids) {
                foreach ($ids as $term_id) {
                    $rows = taxopress_get_linked_terms($term_id, '', false, 'both', 1001);
                    if (count($rows) > 1000) {
                        return SimpleTags_Mcp::error(__('Linked term scope is too large.', 'simple-tags'));
                    }
                    $linked['relations'][$term_id] = $rows;
                }
            }
        }
        return ['id' => $id, 'terms' => $terms, 'features' => $features, 'modified_gmt' => $post->post_modified_gmt, 'linked_revision' => SimpleTags_Mcp::hash($linked)];
    }

    private static function post($action, $input)
    {
        $state = self::post_state($input['id']);
        if (is_wp_error($state)) {
            return $state;
        }
        if ($action === 'get-post-terms') {
            $response = $state + ['expected_hash' => SimpleTags_Mcp::hash($state)];
            $response['terms'] = (object) $state['terms'];
            $response['features'] = (object) $state['features'];
            return $response;
        }
        if (SimpleTags_Mcp::stale($input, $state)) {
            return SimpleTags_Mcp::error(__('Post selections changed.', 'simple-tags'), 409);
        }
        if ($action === 'set-post-features') {
            foreach ($input['features'] as $key => $value) {
                if (!(int) SimpleTags_Plugin::get_option_value($key === 'autolinks' ? 'active_auto_links' : 'active_auto_terms')) {
                    return SimpleTags_Mcp::error(__('Enable this native feature first.', 'simple-tags'), 409);
                }
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'features' => (object) $input['features']];
            }
            foreach ($input['features'] as $key => $value) {
                SimpleTags_Admin_Post_Settings::set_status($input['id'], $value, '_taxopress_' . $key . '_status', $key === 'autolinks' ? '_exclude_autolinks' : '_exclude_autotags');
                if (SimpleTags_Admin_Post_Settings::get_status($input['id'], '_taxopress_' . $key . '_status', $key === 'autolinks' ? '_exclude_autolinks' : '_exclude_autotags') !== $value) {
                    return SimpleTags_Mcp::error();
                }
            }
        } else {
            $taxonomy = $input['taxonomy'];
            if (!array_key_exists($taxonomy, $state['terms'])) {
                return SimpleTags_Mcp::error(__('Taxonomy is not assignable to this post.', 'simple-tags'), 403);
            }
            $plan = self::assignment_plan($input['id'], [$taxonomy => $input['term_ids']]);
            if (is_wp_error($plan)) {
                return $plan;
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'id' => $input['id'], 'taxonomy' => $taxonomy, 'term_ids' => $plan[$taxonomy], 'terms' => (object) $plan];
            }
            $result = self::assign($input['id'], $plan);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return self::post('get-post-terms', ['id' => $input['id']]);
    }

    /** Authorize the complete native propagation plan before the first assignment. */
    public static function assignment_plan($id, array $patch)
    {
        if (!SimpleTags_Mcp::editable_post($id)) {
            return SimpleTags_Mcp::error(__('Post is not editable or is locked.', 'simple-tags'), 403);
        }
        $post = get_post($id);
        $before = [];
        foreach (get_object_taxonomies($post) as $taxonomy) {
            $ids = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($ids)) {
                return SimpleTags_Mcp::error();
            }
            if (count($ids) > 1000) {
                return SimpleTags_Mcp::error(__('Use the native editor for larger term selections.', 'simple-tags'));
            }
            $before[$taxonomy] = array_map('intval', $ids);
            sort($before[$taxonomy]);
        }
        foreach ($patch as $taxonomy => $ids) {
            if (!isset($before[$taxonomy])) {
                return SimpleTags_Mcp::error(__('Taxonomy does not belong to this post.', 'simple-tags'));
            }
            foreach ($ids as $term_id) {
                $term = get_term($term_id, $taxonomy);
                if (!$term || is_wp_error($term)) {
                    return SimpleTags_Mcp::error(__('A selected term does not exist.', 'simple-tags'));
                }
            }
        }
        $plan = array_replace($before, $patch);
        if (SimpleTags_Mcp::pro() && taxopress_is_linked_terms_enabled()) {
            if (!TaxoPress_Linked_Terms_Schema::tableExists(TaxoPress_Linked_Terms_Schema::tableName())) {
                return SimpleTags_Mcp::error(__('Initialize Linked Terms in native administration first.', 'simple-tags'), 409);
            }
            $plan = TaxoPress_Linked_Terms::get_post_term_plan($post, $plan, 1000);
            if (is_wp_error($plan)) {
                return SimpleTags_Mcp::error(__('Native linked term planning failed.', 'simple-tags'));
            }
        }
        $changes = [];
        foreach ($plan as $taxonomy => $ids) {
            sort($ids);
            if (!array_key_exists($taxonomy, $patch) && $ids === $before[$taxonomy]) {
                continue;
            }
            if (!SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'assign_terms')) {
                return SimpleTags_Mcp::error(__('A linked taxonomy is not assignable.', 'simple-tags'), 403);
            }
            foreach (array_unique(array_merge($before[$taxonomy], $ids)) as $term_id) {
                if (!current_user_can('assign_term', $term_id)) {
                    return SimpleTags_Mcp::error(__('A selected or linked term is not assignable.', 'simple-tags'), 403);
                }
            }
            $changes[$taxonomy] = $ids;
        }
        return $changes;
    }

    public static function assign($id, array $plan)
    {
        foreach ($plan as $taxonomy => $ids) {
            if (!SimpleTags_Mcp::editable_post($id) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'assign_terms')) {
                return SimpleTags_Mcp::error();
            }
            $saved = wp_set_object_terms($id, $ids, $taxonomy, false);
            if (is_wp_error($saved)) {
                return SimpleTags_Mcp::error();
            }
            $actual = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($actual)) {
                return SimpleTags_Mcp::error();
            }
            $actual = array_map('intval', $actual);
            sort($actual);
            if ($actual !== $ids) {
                return SimpleTags_Mcp::error();
            }
        }
        return true;
    }

    private static function autoterms($action, $input)
    {
        if (!(int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
            return SimpleTags_Mcp::error(__('Enable Auto Terms first.', 'simple-tags'));
        }
        $state = self::post_state($input['id']);
        if (is_wp_error($state)) {
            return $state;
        }
        $all = taxopress_get_autoterm_data();
        $options = $all[$input['preset_id']] ?? null;
        if (!$options) {
            return SimpleTags_Mcp::error(__('Auto Terms configuration not found.', 'simple-tags'));
        }
        foreach (['autoterm_use_wordpress_ai', 'autoterm_use_open_ai', 'autoterm_use_ibm_watson', 'autoterm_use_dandelion', 'autoterm_use_opencalais'] as $provider) {
            if (!empty($options[$provider])) {
                return SimpleTags_Mcp::error(__('Use native administration for external-provider execution. MCP previews and applies local sources only.', 'simple-tags'));
            }
        }
        $post = get_post($input['id']);
        if (!empty($options['post_types']) && !in_array($post->post_type, $options['post_types'], true)) {
            return SimpleTags_Mcp::error(__('The configuration excludes this post type.', 'simple-tags'));
        }
        if (!empty($options['post_status']) && !in_array($post->post_status, $options['post_status'], true)) {
            return SimpleTags_Mcp::error(__('The configuration excludes this post status.', 'simple-tags'));
        }
        // Native additional targets belong to the external OpenAI batch flow, not local matching.
        $taxonomies = [$options['taxonomy']];
        if (count($taxonomies) > 10) {
            return SimpleTags_Mcp::error(__('Too many target taxonomies.', 'simple-tags'));
        }
        $plan = [];
        foreach ($taxonomies as $taxonomy) {
            if (!isset($state['terms'][$taxonomy]) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms')) {
                return SimpleTags_Mcp::error(__('An Auto Terms target is not permitted.', 'simple-tags'), 403);
            }
            if ((int) wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]) > 5000) {
                return SimpleTags_Mcp::error(__('Use native administration for large term catalogs.', 'simple-tags'));
            }
            $matching = SimpleTags_Client_Autoterms::auto_terms_post($post, $taxonomy, $options, false, 'save_posts', 'st_autoterms', true);
            if ($matching === false) {
                return SimpleTags_Mcp::error(__('The native configuration excludes this post or its content is empty.', 'simple-tags'));
            }
            if (count($matching) > 200) {
                return SimpleTags_Mcp::error(__('Too many proposed terms.', 'simple-tags'));
            }
            $entries = [];
            foreach ($matching as $name) {
                $existing = term_exists($name, $taxonomy);
                $id = $existing ? (int) (is_array($existing) ? $existing['term_id'] : $existing) : 0;
                if (!$id && !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy)) {
                    return SimpleTags_Mcp::error(__('Native term creation is not permitted.', 'simple-tags'), 403);
                }
                if ($id && !current_user_can('assign_term', $id)) {
                    return SimpleTags_Mcp::error(__('A proposed term is not assignable.', 'simple-tags'), 403);
                }
                $entries[] = ['id' => $id, 'name' => $name];
            }
            $plan[$taxonomy] = $entries;
        }
        $append = !in_array($options['post_replace_type'] ?? '', ['replace', 'append_and_replace'], true);
        $known = [];
        foreach ($plan as $taxonomy => $entries) {
            $known[$taxonomy] = array_column(array_filter($entries, static function ($entry) {
                return $entry['id'] > 0;
            }), 'id');
            if ($append || !$entries) {
                $known[$taxonomy] = array_values(array_unique(array_merge($state['terms'][$taxonomy], $known[$taxonomy])));
            }
        }
        $known_plan = self::assignment_plan($input['id'], $known);
        if (is_wp_error($known_plan)) {
            return $known_plan;
        }
        $hash = SimpleTags_Mcp::hash(['post' => $state, 'content' => $post->post_content, 'title' => $post->post_title, 'preset' => $options, 'plan' => $plan, 'assignments' => $known_plan]);
        if ($action === 'preview-autoterms') {
            return ['plan' => (object) $plan, 'linked_terms' => (object) $known_plan, 'expected_hash' => $hash, 'replace_type' => $options['post_replace_type'] ?? 'append'];
        }
        if (!hash_equals($hash, $input['expected_hash'])) {
            return SimpleTags_Mcp::error(__('Post, configuration or proposed terms changed. Preview again.', 'simple-tags'), 409);
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'plan' => (object) $plan, 'expected_hash' => $hash];
        }
        $results = [];
        foreach ($plan as $taxonomy => $entries) {
            $ids = [];
            foreach ($entries as $entry) {
                $id = $entry['id'];
                if (!$id) {
                    $created = wp_insert_term(wp_slash($entry['name']), $taxonomy);
                    if (is_wp_error($created)) {
                        return SimpleTags_Mcp::error();
                    } $id = (int) $created['term_id'];
                }
                $ids[] = $id;
            }
            if (!$ids) {
                $ids = $state['terms'][$taxonomy];
            } elseif ($append) {
                $ids = array_values(array_unique(array_merge($state['terms'][$taxonomy], $ids)));
            }
            $results[$taxonomy] = $ids;
        }
        $assignments = self::assignment_plan($input['id'], $results);
        if (is_wp_error($assignments)) {
            return $assignments;
        }
        $saved = self::assign($input['id'], $assignments);
        if (is_wp_error($saved)) {
            return $saved;
        }
        return ['complete' => true, 'terms' => (object) $assignments, 'post' => self::post('get-post-terms', ['id' => $input['id']])];
    }


    private static function copy($action, $input)
    {
        if (!SimpleTags_Mcp::pro() || !(int) SimpleTags_Plugin::get_option_value('active_st_terms')) {
            return SimpleTags_Mcp::error(__('Enable Pro Terms management first.', 'simple-tags'));
        }
        $term = self::term($input['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        if (!taxopress_current_user_can_for_taxonomy($term->taxonomy, ['manage_terms', 'edit_terms', 'assign_terms'])) {
            return SimpleTags_Mcp::error(__('Term copying is not permitted.', 'simple-tags'), 403);
        }
        $affected = TaxoPress_Pro_Terms::get_term_copy_post_ids($term);
        if (count($affected) > 500) {
            return SimpleTags_Mcp::error(__('Copy scope exceeds the bounded content limit.', 'simple-tags'));
        }
        foreach ($affected as $post_id) {
            if (!SimpleTags_Mcp::editable_post($post_id) || !current_user_can('assign_term', $term->term_id)) {
                return SimpleTags_Mcp::error(__('An affected post or term is not assignable.', 'simple-tags'), 403);
            }
        }
        $source = self::state($term);
        if (is_wp_error($source)) {
            return $source;
        }
        $meta = get_term_meta($term->term_id);
        $seo = get_option('wpseo_taxonomy_meta', []);
        $hash = SimpleTags_Mcp::hash(['term' => $source, 'metadata' => $meta, 'post_ids' => $affected, 'order' => get_option('taxopress_term_order_' . $term->taxonomy, []), 'seo' => $seo[$term->taxonomy][$term->term_id] ?? []]);
        if ($action === 'preview-copy-term') {
            return ['source' => (object) $source, 'post_ids' => $affected, 'expected_hash' => $hash];
        }
        if (!hash_equals($hash, $input['expected_hash'])) {
            return SimpleTags_Mcp::error(__('Term, metadata or assignments changed. Preview again.', 'simple-tags'), 409);
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'source' => (object) $source, 'post_ids' => $affected];
        }
        $new_id = TaxoPress_Pro_Terms::get_instance()->taxopress_action_copy_term_with_meta($term->term_id, false, $affected, static function ($term_id, $post_id) use ($term) {
            return current_user_can('assign_term', $term_id) && SimpleTags_Mcp::editable_post($post_id) && SimpleTags_Mcp_Operations::permitted_taxonomy($term->taxonomy, 'assign_terms');
        });
        if (!$new_id || is_wp_error($new_id)) {
            return SimpleTags_Mcp::error();
        }
        $copied = get_term($new_id, $term->taxonomy);
        if (!$copied || is_wp_error($copied) || !current_user_can('edit_term', $new_id) || $copied->description !== $term->description) {
            return SimpleTags_Mcp::error();
        }
        foreach ($meta as $key => $values) {
            if (get_term_meta($new_id, $key) !== array_map('maybe_unserialize', $values)) {
                return SimpleTags_Mcp::error();
            }
        }
        foreach ($affected as $post_id) {
            $post = get_post($post_id);
            if ($post && !has_term($new_id, $term->taxonomy, $post_id)) {
                return SimpleTags_Mcp::error();
            }
        }
        return ['complete' => true, 'term' => (object) self::view($copied)];
    }

    private static function order($action, $input)
    {
        $taxonomy = $input['taxonomy'];
        if (!SimpleTags_Mcp::pro() || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms')) {
            return SimpleTags_Mcp::error(__('Pro term ordering is not permitted.', 'simple-tags'), 403);
        }
        $settings = taxopress_get_all_edited_taxonomy_data();
        if (empty($settings[$taxonomy]['enable_taxopress_ordering']) || !get_taxopress_disp_boolean($settings[$taxonomy]['enable_taxopress_ordering'])) {
            return SimpleTags_Mcp::error(__('Enable native term ordering for this taxonomy first.', 'simple-tags'));
        }
        $ids = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 501, 'fields' => 'ids']);
        if (is_wp_error($ids) || count($ids) > 500) {
            return SimpleTags_Mcp::error(__('Use native administration for larger term catalogs.', 'simple-tags'));
        }
        $raw = (array) get_option('taxopress_term_order_' . $taxonomy, []);
        foreach ($ids as $id) {
            if (is_wp_error(self::term($id, $taxonomy))) {
                return SimpleTags_Mcp::error(__('A term is not editable.', 'simple-tags'), 403);
            }
        }
        $state = ['saved_order' => $raw, 'term_ids' => array_map('intval', $ids), 'settings' => $settings[$taxonomy]];
        if ($action === 'get-term-order') {
            return $state + ['expected_hash' => SimpleTags_Mcp::hash($state)];
        }
        if (SimpleTags_Mcp::stale($input, $state)) {
            return SimpleTags_Mcp::error(__('Term order changed.', 'simple-tags'), 409);
        }
        if (array_diff($input['term_ids'], $ids)) {
            return SimpleTags_Mcp::error(__('The order contains an unknown term.', 'simple-tags'));
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'term_ids' => $input['term_ids']];
        }
        $saved = TaxoPress_Pro_Terms::persist_term_order($taxonomy, $input['term_ids']);
        if (get_option('taxopress_term_order_' . $taxonomy) !== $saved) {
            return SimpleTags_Mcp::error();
        }
        return self::order('get-term-order', ['taxonomy' => $taxonomy]);
    }

    private static function merge($action, $input)
    {
        $taxonomy = $input['taxonomy'];
        if (!(int) SimpleTags_Plugin::get_option_value('active_manage') || !taxopress_current_user_can_for_taxonomy($taxonomy, ['edit_terms', 'delete_terms', 'assign_terms'])) {
            return SimpleTags_Mcp::error(__('Native Manage Terms permission is required.', 'simple-tags'), 403);
        }
        if (!$input['source_ids'] || in_array($input['target_id'], $input['source_ids'], true)) {
            return SimpleTags_Mcp::error(__('Choose distinct source and target terms.', 'simple-tags'));
        }
        $state = [];
        $labels = [];
        foreach (array_merge($input['source_ids'], [$input['target_id']]) as $id) {
            $term = self::term($id, $taxonomy);
            if (is_wp_error($term) || !current_user_can('delete_term', $id) || !current_user_can('assign_term', $id)) {
                return SimpleTags_Mcp::error(__('A merge term is not permitted.', 'simple-tags'), 403);
            }
            $affected = self::affected($term);
            if (is_wp_error($affected)) {
                return $affected;
            }
            $children = get_term_children($id, $taxonomy);
            if (is_wp_error($children) || count($children) > 200) {
                return SimpleTags_Mcp::error(__('Child-term scope exceeds the limit.', 'simple-tags'));
            }
            foreach ($children as $child) {
                $child_term = self::term($child, $taxonomy);
                if (is_wp_error($child_term) || is_wp_error(self::affected($child_term))) {
                    return SimpleTags_Mcp::error(__('An affected child term is not editable.', 'simple-tags'), 403);
                }
            }
            $data = self::state($term);
            if (is_wp_error($data)) {
                return $data;
            }
            $data['affected_ids'] = $affected;
            $data['children'] = $children;
            $state[$id] = $data;
            // Native Manage Terms accepts comma-delimited autocomplete labels.
            if (strpbrk($term->name, ',()') !== false || strpbrk($term->slug, ',()') !== false) {
                return SimpleTags_Mcp::error(__('Use native administration for terms containing commas or parentheses.', 'simple-tags'));
            }
            $labels[$id] = $term->name . ' (' . $term->slug . ')';
        }
        $hash = SimpleTags_Mcp::hash($state);
        if ($action === 'preview-merge-terms') {
            return ['terms' => (object) $state, 'expected_hash' => $hash];
        }
        if (!hash_equals($hash, $input['expected_hash'])) {
            return SimpleTags_Mcp::error(__('Merge targets changed. Preview again.', 'simple-tags'), 409);
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'terms' => (object) $state];
        }
        require_once ABSPATH . 'wp-admin/includes/template.php';
        require_once STAGS_DIR . '/inc/class.admin.manage.php';
        $source_labels = [];
        foreach ($input['source_ids'] as $id) {
            $source_labels[] = $labels[$id];
        }
        $result = SimpleTags_Admin_Manage::mergeTerms($taxonomy, implode(',', $source_labels), $labels[$input['target_id']], 'different_name');
        if (!$result || is_wp_error($result)) {
            return SimpleTags_Mcp::error();
        }
        foreach ($input['source_ids'] as $id) {
            if (get_term($id, $taxonomy)) {
                return SimpleTags_Mcp::error();
            }
        }
        return ['complete' => true, 'target' => self::view(get_term($input['target_id'], $taxonomy)), 'removed_ids' => $input['source_ids']];
    }

    private static function transfer($action, $input)
    {
        $taxonomy = $input['taxonomy'];
        $type = $input['post_type'];
        if (!(int) SimpleTags_Plugin::get_option_value('active_manage') || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'edit_terms') || !is_object_in_taxonomy($type, $taxonomy)) {
            return SimpleTags_Mcp::error(__('Native term transfer is not permitted.', 'simple-tags'), 403);
        }
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 201]);
        if (is_wp_error($terms) || count($terms) > 200) {
            return SimpleTags_Mcp::error(__('Use native administration for larger transfers.', 'simple-tags'));
        }
        $associations = 0;
        foreach ($terms as $term) {
            if (is_wp_error(self::term($term->term_id, $taxonomy))) {
                return SimpleTags_Mcp::error(__('A term is not permitted.', 'simple-tags'), 403);
            }
            $objects = get_objects_in_term($term->term_id, $taxonomy);
            if (is_wp_error($objects) || ($associations += count($objects)) > 500) {
                return SimpleTags_Mcp::error(__('Content assignment scope exceeds the limit.', 'simple-tags'));
            }
        }
        require_once STAGS_DIR . '/inc/class-taxopress-tag-csv-service.php';
        $stream = fopen('php://temp', 'w+');
        if (!$stream) {
            return SimpleTags_Mcp::error();
        }
        try {
            $written = TaxoPress_Tag_CSV_Service::write_csv($stream, true, $taxonomy, $type);
            if (is_wp_error($written)) {
                return SimpleTags_Mcp::error(__('Native export permission or validation failed.', 'simple-tags'));
            }
            rewind($stream);
            $csv = stream_get_contents($stream, 1048577);
            if (!is_string($csv) || strlen($csv) > 1048576) {
                return SimpleTags_Mcp::error(__('Transfer exceeds the MCP byte limit.', 'simple-tags'));
            }
            $hash = SimpleTags_Mcp::hash($csv);
            if ($action === 'export-terms') {
                return ['csv' => $csv, 'expected_hash' => $hash, 'taxonomy' => $taxonomy, 'post_type' => $type];
            }
            if (!hash_equals($hash, $input['expected_hash'])) {
                return SimpleTags_Mcp::error(__('Terms or assignments changed. Export again.', 'simple-tags'), 409);
            }
            ftruncate($stream, 0);
            rewind($stream);
            fwrite($stream, $input['csv']);
            rewind($stream);
            $rows = TaxoPress_Tag_CSV_Service::parse_csv($stream, false, $taxonomy, $type);
            if (is_wp_error($rows) || count($rows) > 200) {
                return SimpleTags_Mcp::error(__('Invalid or oversized native transfer CSV.', 'simple-tags'));
            }
            $valid = TaxoPress_Tag_CSV_Service::validate_assignments($rows, true, false, $taxonomy, $type);
            if (is_wp_error($valid) || !SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy)) {
                return SimpleTags_Mcp::error(__('Native import permission or validation failed.', 'simple-tags'), 403);
            }
            $total = 0;
            foreach ($rows as $row) {
                $existing = get_term_by('slug', $row['slug'], $taxonomy);
                if ($existing && (is_wp_error(self::term($existing->term_id, $taxonomy)) || is_wp_error(self::affected($existing)))) {
                    return SimpleTags_Mcp::error(__('An existing transfer term is not editable.', 'simple-tags'), 403);
                }
                if ($existing && !empty($row['post_ids']) && !current_user_can('assign_term', $existing->term_id)) {
                    return SimpleTags_Mcp::error(__('A transfer term is not assignable.', 'simple-tags'), 403);
                }
                foreach (array_filter(explode('|', $row['post_ids'] ?? '')) as $id) {
                    ++$total;
                    if ($total > 500 || !get_post((int) $id) || !SimpleTags_Mcp::editable_post((int) $id)) {
                        return SimpleTags_Mcp::error(__('A transfer post is unavailable, locked or not editable.', 'simple-tags'), 403);
                    }
                }
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'validated_rows' => count($rows), 'expected_hash' => $hash];
            }
            $result = TaxoPress_Tag_CSV_Service::import_rows($rows, !empty($input['update_existing']), false, $taxonomy, $type, static function ($term_id, $post_id) use ($taxonomy) {
                return current_user_can('assign_term', $term_id) && SimpleTags_Mcp::editable_post($post_id) && SimpleTags_Mcp_Operations::permitted_taxonomy($taxonomy, 'assign_terms');
            });
            // Native diagnostics can name private objects. Return counts and explicit partial status only.
            unset($result['error_messages'], $result['skipped_post_ids']);
            return ['complete' => empty($result['errors']), 'result' => $result];
        } finally {
            fclose($stream);
        }
    }

    private static function bulk($input)
    {
        $seen = [];
        foreach ($input['items'] as $item) {
            $key = $item['id'] . ':' . $item['taxonomy'];
            // A single revision covers all taxonomies on a post; forbid ambiguous repeated posts.
            if (isset($seen[$item['id']])) {
                return SimpleTags_Mcp::error(__('Use one entry per post.', 'simple-tags'));
            }
            $seen[$item['id']] = true;
            $result = self::post('set-post-terms', $item + ['dry_run' => true]);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'validated' => count($seen)];
        }
        $results = [];
        foreach ($input['items'] as $item) {
            $result = self::post('set-post-terms', $item);
            if (is_wp_error($result)) {
                return ['complete' => false, 'results' => $results, 'failed_id' => $item['id'], 'message' => 'Read current selections before retrying.'];
            }
            $results[] = $result;
        }
        return ['complete' => true, 'results' => $results];
    }

    private static function relations($term, $state, $input)
    {
        if (!SimpleTags_Mcp::pro()) {
            return SimpleTags_Mcp::error(__('Pro is required.', 'simple-tags'));
        }
        if (isset($input['synonyms']) && (!taxopress_is_synonyms_enabled() || !in_array($term->taxonomy, (array) SimpleTags_Plugin::get_option_value('synonyms_taxonomies'), true))) {
            return SimpleTags_Mcp::error(__('Enable native synonyms first.', 'simple-tags'));
        }
        if (isset($input['linked_ids']) && (!taxopress_is_linked_terms_enabled() || !in_array($term->taxonomy, (array) SimpleTags_Plugin::get_option_value('linked_terms_taxonomies'), true))) {
            return SimpleTags_Mcp::error(__('Enable native linked terms first.', 'simple-tags'));
        }
        $next = $input['linked_ids'] ?? $state['linked_ids'];
        foreach (array_unique(array_merge($state['linked_ids'], $next)) as $id) {
            $other = self::term($id);
            if ($id === (int) $term->term_id || is_wp_error($other) || is_wp_error(self::affected($other))) {
                return SimpleTags_Mcp::error(__('Invalid or unauthorized linked term.', 'simple-tags'));
            }
        }
        if (isset($input['synonyms'])) {
            foreach ($input['synonyms'] as $synonym) {
                if ($synonym === '' || sanitize_text_field($synonym) !== $synonym) {
                    return SimpleTags_Mcp::error(__('Provide non-empty plain-text synonyms.', 'simple-tags'));
                }
            }
        }
        if (isset($input['synonyms']) && !TaxoPress_Taxonomy_Synonyms::valid_synonyms($term->term_id, $input['synonyms'])) {
            return SimpleTags_Mcp::error(__('A synonym conflicts with an existing term or synonym.', 'simple-tags'));
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'linked_ids' => $next, 'synonyms' => $input['synonyms'] ?? $state['synonyms'] ?? []];
        }
        if (isset($input['synonyms'])) {
            TaxoPress_Taxonomy_Synonyms::set_synonyms($term->term_id, $input['synonyms']);
        }
        if (isset($input['linked_ids'])) {
            $service = TaxoPress_Linked_Terms::get_instance();
            foreach (array_diff($state['linked_ids'], $next) as $id) {
                if ($service->deleteLinkedTermsRelation($term->term_id, $id) === false) {
                    return SimpleTags_Mcp::error();
                }
            }
            foreach (array_diff($next, $state['linked_ids']) as $id) {
                $other = get_term($id);
                if (!TaxoPress_Linked_Terms::addLinkedTermsRelation($term->term_id, $term->name, $term->taxonomy, $id, $other->name, $other->taxonomy)) {
                    return SimpleTags_Mcp::error();
                }
            }
        }
        $after = self::view(get_term($term->term_id));
        if (is_wp_error($after)) {
            return $after;
        }
        $expected = $next;
        sort($expected);
        if ($after['linked_ids'] !== $expected || (isset($input['synonyms']) && $after['synonyms'] !== $input['synonyms'])) {
            return SimpleTags_Mcp::error();
        }
        return $after;
    }
}
