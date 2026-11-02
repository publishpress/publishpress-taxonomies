<?php

/** Native Taxonomies settings, presets and definition operations. */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- Native direct-access guard.
defined('ABSPATH') || exit;
class SimpleTags_Mcp_Operations
{
    public static function execute($action, $input)
    {
        if (SimpleTags_Mcp_Manage::handles($action)) {
            return SimpleTags_Mcp_Manage::execute($action, $input);
        }
        if ($action === 'list-features') {
            return ['edition' => SimpleTags_Mcp::pro() ? 'pro' : 'free', 'preset_kinds' => array_keys(SimpleTags_Mcp_Contracts::kinds()), 'settings_capability' => 'admin_simple_tags', 'management_capability' => 'simple_tags', 'credentials_available' => false, 'features' => self::features()];
        }
        if (strpos($action, 'autoterms-schedule') !== false) {
            return self::schedule($action, $input);
        }
        if (strpos($action, 'plugin-settings') !== false) {
            return self::settings($action, $input);
        }
        if (strpos($action, 'preset') !== false) {
            return self::presets($action, $input);
        }
        if (in_array($action, ['list-taxonomies', 'get-taxonomy', 'save-taxonomy', 'delete-taxonomy'], true)) {
            return self::taxonomy($action, $input);
        }
        return SimpleTags_Mcp_Terms::execute($action, $input);
    }

    public static function features()
    {
        $items = [];
        $definitions = [
            ['settings', __('Settings and Fast Update', 'simple-tags'), '', false, ['admin_simple_tags'], ['get-plugin-settings', 'update-plugin-settings']],
            ['taxonomies', __('Taxonomies', 'simple-tags'), 'active_taxonomies', false, ['simple_tags', 'manage_terms'], ['list-taxonomies', 'get-taxonomy', 'save-taxonomy', 'delete-taxonomy']],
            ['terms', __('Terms', 'simple-tags'), 'active_st_terms', false, ['simple_tags', 'edit_terms'], ['list-terms', 'get-term', 'save-term', 'delete-term']],
            ['manage', __('Manage Terms', 'simple-tags'), 'active_manage', false, ['simple_tags', 'edit_terms', 'delete_terms', 'assign_terms'], ['preview-merge-terms', 'merge-terms', 'export-terms', 'import-terms']],
            ['posts', __('Posts', 'simple-tags'), 'active_st_posts', false, ['simple_tags', 'read_post', 'edit_post'], ['list-posts', 'get-post-terms']],
            ['mass-edit', __('Mass Edit', 'simple-tags'), 'active_mass_edit', false, ['edit_post', 'assign_terms'], ['get-post-terms', 'set-post-terms', 'bulk-set-post-terms']],
            ['schedule', __('Auto Terms Schedule', 'simple-tags'), 'active_auto_terms', false, ['simple_tags', 'admin_simple_tags'], ['get-autoterms-schedule', 'update-autoterms-schedule']],
            ['local-matching', __('Local Auto Terms', 'simple-tags'), 'active_auto_terms', false, ['simple_tags', 'edit_post', 'assign_terms'], ['preview-autoterms', 'apply-autoterms', 'list-autoterms-logs']],
            ['synonyms', __('Synonyms', 'simple-tags'), 'active_features_synonyms', true, ['simple_tags', 'edit_terms'], ['get-term-relations', 'set-term-relations']],
            ['linked-terms', __('Linked Terms', 'simple-tags'), 'active_features_linked_terms', true, ['simple_tags', 'edit_terms', 'assign_terms'], ['get-term-relations', 'set-term-relations']],
            ['term-order', __('Term Order', 'simple-tags'), 'active_st_terms', true, ['simple_tags', 'edit_terms'], ['get-term-order', 'set-term-order']],
            ['term-copy', __('Copy Term with Metadata', 'simple-tags'), 'active_st_terms', true, ['simple_tags', 'edit_terms', 'assign_terms'], ['preview-copy-term', 'copy-term']],
        ];
        $definitions[] = ['maintenance', __('Manage Terms: Find, Replace and Cleanup', 'simple-tags'), 'active_manage', false, ['simple_tags', 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'], ['preview-replace-term-text', 'replace-term-text', 'preview-cleanup-terms', 'cleanup-terms', 'preview-match-terms', 'match-terms']];
        $definitions[] = ['conversion', __('Taxonomy Conversion and Term Moves', 'simple-tags'), 'active_taxonomies', false, ['simple_tags', 'admin_simple_tags', 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'], ['preview-rename-taxonomy', 'rename-taxonomy', 'preview-convert-taxonomy', 'convert-taxonomy']];
        $definitions[] = ['term-moves', __('Move Terms between Taxonomies', 'simple-tags'), 'active_taxonomies', false, ['simple_tags', 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'], ['preview-move-terms', 'move-terms']];
        foreach (self::preset_feature_definitions() as $definition) {
            $definitions[] = $definition;
        }
        foreach ($definitions as $definition) {
            [$id, $label, $option, $pro, $caps, $actions] = $definition;
            $available = !$pro || SimpleTags_Mcp::pro();
            $taxonomy_caps = array_values(array_intersect($caps, ['manage_terms', 'edit_terms', 'delete_terms', 'assign_terms']));
            $caps = array_values(array_diff($caps, $taxonomy_caps));
            $items[] = ['id' => $id, 'label' => $label, 'available' => $available, 'enabled' => $available && (!$option || (int) SimpleTags_Plugin::get_option_value($option) === 1), 'pro' => $pro, 'required_capabilities' => $caps, 'taxonomy_capabilities' => $taxonomy_caps, 'actions' => $available ? $actions : []];
        }
        return $items;
    }

    private static function preset_feature_definitions()
    {
        $items = [];
        $labels = ['tag_cloud' => __('Terms Display', 'simple-tags'), 'post_tags' => __('Terms for Current Post', 'simple-tags'), 'related_posts' => __('Related Posts', 'simple-tags'), 'autolink' => __('Auto Links', 'simple-tags'), 'autoterm' => __('Auto Terms', 'simple-tags')];
        foreach (SimpleTags_Mcp_Contracts::kinds() as $kind => $meta) {
            $items[] = [$kind, $labels[$kind], $meta['feature'], false, ['simple_tags', 'manage_terms'], ['list-presets', 'get-preset-fields', 'get-preset', 'save-preset', 'delete-preset']];
        }
        return $items;
    }

    public static function schedule_fields()
    {
        $frequency = SimpleTags_Mcp::pro() ? ['disable', 'weekly', 'daily', 'hourly'] : ['disable', 'weekly'];
        return [
            'autoterm_id' => ['type' => 'array', 'uniqueItems' => true, 'maxItems' => 100, 'items' => ['type' => 'integer', 'minimum' => 1], 'default' => []],
            'cron_schedule' => ['type' => 'string', 'enum' => $frequency, 'default' => 'disable'],
            'schedule_terms_batches' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'schedule_terms_sleep' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60, 'default' => 3],
            'schedule_terms_limit_days' => ['type' => 'string', 'enum' => ['', '1', '7', '14', '30', '180', '365', '0'], 'default' => ''],
            'autoterm_schedule_exclude' => ['type' => 'boolean', 'default' => false],
        ];
    }

    private static function schedule($action, $input)
    {
        if (!(int) SimpleTags_Plugin::get_option_value('active_auto_terms')) {
            return SimpleTags_Mcp::error(__('Enable Auto Terms first.', 'simple-tags'));
        }
        $raw = (array) get_option('taxopress_autoterms_schedule', []);
        $fields = self::schedule_fields();
        $values = self::view($raw, $fields);
        if ($action === 'get-autoterms-schedule') {
            return ['values' => (object) $values, 'fields' => (object) $fields, 'expected_hash' => SimpleTags_Mcp::hash($raw)];
        }
        if (!current_user_can('admin_simple_tags')) {
            return SimpleTags_Mcp::error(__('Schedule configuration requires site settings permission.', 'simple-tags'), 403);
        }
        if (SimpleTags_Mcp::stale($input, $raw)) {
            return SimpleTags_Mcp::error(__('Schedule changed.', 'simple-tags'), 409);
        }
        $valid = SimpleTags_Mcp::validate($input['fields'], SimpleTags_Mcp::object_schema($fields));
        if (is_wp_error($valid)) {
            return $valid;
        }
        $next = array_replace($raw, $values, $input['fields']);
        $presets = taxopress_get_autoterm_data();
        foreach ($next['autoterm_id'] as $id) {
            if (!isset($presets[$id]) || empty($presets[$id]['autoterm_for_schedule'])) {
                return SimpleTags_Mcp::error(__('Select existing configurations with native scheduling enabled.', 'simple-tags'));
            }
            foreach (array_merge([$presets[$id]['taxonomy']], $presets[$id]['additional_taxonomies'] ?? []) as $name) {
                if (!self::permitted_taxonomy($name) || !self::permitted_taxonomy($name, 'assign_terms')) {
                    return SimpleTags_Mcp::error(__('A scheduled taxonomy is not permitted.', 'simple-tags'), 403);
                }
            }
            foreach ($presets[$id]['post_types'] ?? [] as $post_type) {
                $type = get_post_type_object($post_type);
                if (!$type) {
                    return SimpleTags_Mcp::error(__('Invalid scheduled post type.', 'simple-tags'));
                }
                foreach (['edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts'] as $cap) {
                    if (!current_user_can($type->cap->$cap)) {
                        return SimpleTags_Mcp::error(__('Scheduled post scope is not permitted.', 'simple-tags'), 403);
                    }
                }
            }
        }
        if ($next['cron_schedule'] !== 'disable' && !$next['autoterm_id']) {
            return SimpleTags_Mcp::error(__('Select an Auto Terms configuration.', 'simple-tags'));
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'values' => (object) $next];
        }
        $next['autoterm_schedule_exclude'] = (int) $next['autoterm_schedule_exclude'];
        SimpleTags_Autoterms_Schedule::persist_schedule($next);
        if (get_option('taxopress_autoterms_schedule') !== $next) {
            return SimpleTags_Mcp::error();
        }
        return self::schedule('get-autoterms-schedule', []);
    }

    public static function permitted_taxonomy($name, $cap = 'manage_terms')
    {
        $taxonomy = get_taxonomy($name);
        return $taxonomy && !empty($taxonomy->cap->$cap) && current_user_can($taxonomy->cap->$cap);
    }

    public static function view($values, $fields)
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $result[$key] = SimpleTags_Mcp_Contracts::canonical($values[$key] ?? $field['default'] ?? '', $field);
        }
        return $result;
    }

    private static function settings($action, $input)
    {
        $raw = (array) get_option(STAGS_OPTIONS_NAME, []);
        $fields = SimpleTags_Mcp_Contracts::site_fields();
        $labels = TaxoPress_AI_Module::get_tab_labels();
        $state = ['options' => $raw, 'tab_labels' => $labels];
        $values = self::view(array_replace(SimpleTags_Plugin::get_option(), $labels), $fields);
        if ($action === 'get-plugin-settings') {
            return ['settings' => (object) $values, 'fields' => (object) $fields, 'expected_hash' => SimpleTags_Mcp::hash($state)];
        }
        if (SimpleTags_Mcp::stale($input, $state)) {
            return SimpleTags_Mcp::error(__('Settings changed. Read them again.', 'simple-tags'), 409);
        }
        // Feature settings and hidden-term refreshes can affect every visible taxonomy.
        foreach (get_taxonomies(['show_ui' => true, 'public' => true]) as $name) {
            if (!self::permitted_taxonomy($name)) {
                return SimpleTags_Mcp::error(__('A taxonomy is not manageable by this connection: ', 'simple-tags') . $name, 403);
            }
        }
        $patch = $input['settings'];
        $valid = SimpleTags_Mcp::validate($patch, SimpleTags_Mcp_Contracts::site_schema());
        if (is_wp_error($valid)) {
            return $valid;
        }
        foreach (array_intersect_key($patch, $labels) as $key => $value) {
            if (!current_user_can('simple_tags') || !can_edit_taxopress_metabox_labels()) {
                return SimpleTags_Mcp::error(__('Native metabox labels are not editable.', 'simple-tags'), 403);
            }
            $label = TaxoPress_AI_Module::validate_tab_label($value);
            if (is_wp_error($label)) {
                return $label;
            }
            $patch[$key] = $label;
        }
        foreach ($patch as $key => $value) {
            $patch[$key] = taxopress_sanitize_text_field(wp_slash(is_bool($value) ? (int) $value : $value));
        }
        $next = array_replace(SimpleTags_Plugin::get_option(), $patch);
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'settings' => (object) self::view(array_replace($next, $labels, array_intersect_key($patch, $labels)), $fields), 'expected_hash' => SimpleTags_Mcp::hash($state)];
        }
        SimpleTags_Plugin::set_option($next);
        if (get_option(STAGS_OPTIONS_NAME) !== $next) {
            return SimpleTags_Mcp::error();
        }
        foreach (array_intersect_key($patch, $labels) as $key => $value) {
            $saved = TaxoPress_AI_Module::persist_tab_label($key, $value);
            if (is_wp_error($saved)) {
                return SimpleTags_Mcp::error();
            }
        }
        do_action('simpletags_settings_save_general_end');
        do_action('taxopress_settings_saved');
        SimpleTags_Plugin::load_option();
        return self::settings('get-plugin-settings', []);
    }

    private static function presets($action, $input)
    {
        $kind = $input['kind'];
        $meta = SimpleTags_Mcp_Contracts::kinds()[$kind];
        if (!(int) SimpleTags_Plugin::get_option_value($meta['feature'])) {
            return SimpleTags_Mcp::error(__('Enable this native feature first.', 'simple-tags'), 409);
        }
        $get = $meta['get'];
        $all = (array) $get(true);
        $id = $input['id'] ?? 0;
        $fields = SimpleTags_Mcp_Contracts::preset_fields($kind);
        if ($action === 'list-presets') {
            $items = [];
            foreach ($all as $key => $record) {
                $permitted = true;
                foreach (array_merge([$record['taxonomy'] ?? 'post_tag'], $record['additional_taxonomies'] ?? []) as $taxonomy) {
                    if (!self::permitted_taxonomy($taxonomy)) {
                        $permitted = false;
                        break;
                    }
                }
                if ($permitted) {
                    $items[] = ['id' => (int) $key, 'title' => html_entity_decode($record['title'] ?? '', ENT_QUOTES, 'UTF-8'), 'taxonomy' => $record['taxonomy'] ?? 'post_tag'];
                }
            }
            return ['items' => $items];
        }
        if ($id && !isset($all[$id])) {
            return SimpleTags_Mcp::error(__('Configuration not found.', 'simple-tags'), 404);
        }
        $old = $id ? $all[$id] : SimpleTags_Mcp_Contracts::defaults($kind);
        if (!self::permitted_taxonomy($old['taxonomy'] ?? 'post_tag')) {
            return SimpleTags_Mcp::error(__('Insufficient taxonomy permission.', 'simple-tags'), 403);
        }
        foreach (array_merge([$old['taxonomy'] ?? 'post_tag'], $old['additional_taxonomies'] ?? []) as $name) {
            if (!self::permitted_taxonomy($name)) {
                return SimpleTags_Mcp::error(__('A configuration target is not permitted.', 'simple-tags'), 403);
            }
        }
        $state = $id ? $old : $all;
        if (in_array($action, ['get-preset', 'get-preset-fields'], true)) {
            return ['id' => $id, 'kind' => $kind, 'fields' => (object) $fields, 'values' => (object) self::view($old, $fields), 'expected_hash' => SimpleTags_Mcp::hash($state)];
        }
        if (SimpleTags_Mcp::stale($input, $state)) {
            return SimpleTags_Mcp::error(__('Configuration changed. Read it again.', 'simple-tags'), 409);
        }
        if ($action === 'copy-preset') {
            if (!SimpleTags_Mcp::pro()) {
                return SimpleTags_Mcp::error(__('Pro is required.', 'simple-tags'));
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'id' => $id, 'kind' => $kind];
            }
            $services = [
                'autolink' => ['TaxoPress_Pro_Auto_Links', 'taxopress_action_copy_autolink'],
                'autoterm' => ['TaxoPress_Pro_Auto_Terms', 'taxopress_action_copy_autoterm'],
                'tag_cloud' => ['TaxoPress_Pro_Tag_Clouds', 'taxopress_action_copy_tagcloud'],
                'post_tags' => ['TaxoPress_Pro_Post_Tags', 'taxopress_action_copy_posttags'],
                'related_posts' => ['TaxoPress_Pro_Related_Posts', 'taxopress_action_copy_relatedpost'],
            ];
            [$class, $method] = $services[$kind];
            $new_id = $class::get_instance()->$method($id, false);
            $after = $get(true);
            if (!$new_id || !isset($after[$new_id]) || ($after[$id] ?? null) !== $old) {
                return SimpleTags_Mcp::error();
            }
            return self::presets('get-preset', ['kind' => $kind, 'id' => (int) $new_id]);
        }
        if ($action === 'delete-preset') {
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'id' => $id];
            }
            $delete = $meta['delete'];
            $delete($id, false);
            return !isset($get(true)[$id]) ? ['deleted' => $id] : SimpleTags_Mcp::error();
        }
        if (!$id && $all && apply_filters($meta['limit'], true)) {
            return SimpleTags_Mcp::error(__('This edition allows one configuration of this kind.', 'simple-tags'), 409);
        }
        $patch = $input['fields'];
        $valid = SimpleTags_Mcp::validate($patch, SimpleTags_Mcp::object_schema($fields));
        if (is_wp_error($valid)) {
            return $valid;
        }
        $next = array_replace($old, $patch);
        if (isset($patch['title'])) {
            $next['title'] = taxopress_sanitize_preset_title(taxopress_sanitize_text_field(wp_slash($patch['title'])));
        }
        if (!empty($next['terms_regex_code']) && @preg_match($next['terms_regex_code'], '') === false) {
            return SimpleTags_Mcp::error(__('Invalid native regular expression.', 'simple-tags'));
        }
        foreach (array_merge([$next['taxonomy'] ?? 'post_tag'], $next['additional_taxonomies'] ?? [], $next['enable_customurl_field'] ?? []) as $taxonomy) {
            if (!self::permitted_taxonomy($taxonomy)) {
                return SimpleTags_Mcp::error(__('Insufficient taxonomy permission.', 'simple-tags'), 403);
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'values' => (object) self::view($next, $fields), 'expected_hash' => SimpleTags_Mcp::hash($state)];
        }
        $form = [$meta['inner'] => $next];
        foreach (['embedded', 'specific_terms', 'post_types', 'post_status', 'html_exclusion', 'html_exclusion_customs', 'html_exclusion_customs_entry', 'shortcodes_exclusion', 'shortcodes_exclusion_entries', 'blocks_exclusion', 'blocks_exclusion_entries', 'find_in_customs_entries', 'find_in_custom_fields_custom_items', 'find_in_taxonomies_custom_items'] as $key) {
            if (isset($next[$key])) {
                $form[$key] = $next[$key];
            }
        }
        if ($id) {
            $form[$meta['edit']] = $id;
        }
        $save = $meta['save'];
        $form = wp_slash($form);
        if (isset($next['terms_regex_code'])) {
            $form[$meta['inner']]['terms_regex_code'] = $next['terms_regex_code'];
        }
        $saved = $save($form);
        $after = (array) $get(true);
        if (!is_numeric($saved) || !isset($after[$saved])) {
            return SimpleTags_Mcp::error();
        }
        // Confirm unrelated configurations survived the native full-array writer.
        foreach ($all as $other_id => $record) {
            if ((int) $other_id !== (int) $saved && ($after[$other_id] ?? null) !== $record) {
                return SimpleTags_Mcp::error();
            }
        }
        foreach ($patch as $key => $value) {
            $actual = self::view($after[$saved], $fields)[$key];
            $expected = SimpleTags_Mcp_Contracts::canonical(taxopress_sanitize_text_field(wp_slash($value)), $fields[$key]);
            if ($key === 'title') {
                $expected = SimpleTags_Mcp_Contracts::canonical(taxopress_sanitize_preset_title(taxopress_sanitize_text_field(wp_slash($value))), $fields[$key]);
            }
            if ($actual !== $expected) {
                return SimpleTags_Mcp::error();
            }
        }
        return self::presets('get-preset', ['kind' => $kind, 'id' => (int) $saved]);
    }

    public static function taxonomy_fields()
    {
        $fields = [];
        foreach (['label', 'singular_label', 'description', 'query_var_slug', 'rewrite_slug', 'rest_base', 'default_term'] as $key) {
            $fields[$key] = ['type' => 'string', 'default' => '', 'maxLength' => 2000];
        }
        foreach (['public', 'publicly_queryable', 'include_in_result', 'hierarchical', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'query_var', 'rewrite', 'rewrite_withfront', 'rewrite_hierarchical', 'show_admin_column', 'show_in_rest', 'show_in_quick_edit', 'show_in_filter'] as $key) {
            $fields[$key] = ['type' => 'boolean', 'default' => !in_array($key, ['hierarchical', 'rewrite_hierarchical', 'show_in_filter'], true)];
        }
        $labels = [];
        foreach (['name', 'singular_name', 'menu_name', 'all_items', 'edit_item', 'view_item', 'update_item', 'add_new_item', 'new_item_name', 'parent_item', 'parent_item_colon', 'search_items', 'popular_items', 'separate_items_with_commas', 'add_or_remove_items', 'choose_from_most_used', 'not_found', 'no_terms', 'items_list_navigation', 'items_list', 'back_to_items', 'name_field_description', 'slug_field_description', 'parent_field_description', 'desc_field_description'] as $key) {
            $labels[$key] = ['type' => 'string', 'maxLength' => 2000];
        }
        $fields['labels'] = SimpleTags_Mcp::object_schema($labels);
        $fields['labels']['default'] = (object) [];
        $fields['object_types'] = ['type' => 'array', 'uniqueItems' => true, 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => array_values(get_post_types(['show_ui' => true]))], 'default' => ['post']];
        if (SimpleTags_Mcp::pro()) {
            $fields['order'] = ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'];
            $fields['orderby'] = ['type' => 'string', 'enum' => ['term_id', 'name', 'count', 'random', 'taxopress_term_order'], 'default' => 'term_id'];
            $fields['enable_taxopress_ordering'] = ['type' => 'boolean', 'default' => false];
        }
        return $fields;
    }

    private static function taxonomy_view($name, $all)
    {
        $taxonomy = get_taxonomy($name);
        $fields = self::taxonomy_fields();
        $raw = $all[$name] ?? [];
        if (!$raw && $taxonomy) {
            foreach ($fields as $key => $field) {
                if (isset($taxonomy->$key)) {
                    $raw[$key] = $taxonomy->$key;
                }
            }
            $raw['object_types'] = $taxonomy->object_type;
            $raw['label'] = $taxonomy->label;
        }
        foreach ($fields as $key => $field) {
            if ($field['type'] === 'boolean' && isset($raw[$key])) {
                $raw[$key] = get_taxopress_disp_boolean($raw[$key]);
            }
        }
        $values = self::view($raw, $fields);
        return ['name' => $name, 'managed' => isset($all[$name]), 'values' => (object) $values, 'fields' => (object) $fields, 'expected_hash' => SimpleTags_Mcp::hash($all[$name] ?? $all)];
    }

    private static function taxonomy($action, $input)
    {
        $all = taxopress_get_taxonomy_data();
        if ($action === 'list-taxonomies') {
            $items = [];
            foreach (get_taxonomies([], 'objects') as $name => $taxonomy) {
                if (($taxonomy->show_ui || isset($all[$name])) && self::permitted_taxonomy($name)) {
                    $items[] = ['name' => $name, 'label' => $taxonomy->label, 'capabilities' => (object) $taxonomy->cap, 'managed' => isset($all[$name])];
                }
            }
            return ['items' => $items, 'new_expected_hash' => SimpleTags_Mcp::hash($all), 'fields' => (object) self::taxonomy_fields()];
        }
        $name = $input['name'];
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $name)) {
            return SimpleTags_Mcp::error(__('Invalid taxonomy slug.', 'simple-tags'));
        }
        $exists = taxonomy_exists($name);
        if (!$exists && !current_user_can('manage_categories')) {
            return SimpleTags_Mcp::error(__('Native term-management permission is required for a new taxonomy.', 'simple-tags'), 403);
        }
        if ($exists && !self::permitted_taxonomy($name)) {
            return SimpleTags_Mcp::error(__('Insufficient taxonomy permission.', 'simple-tags'), 403);
        }
        if ($action === 'get-taxonomy') {
            return $exists ? self::taxonomy_view($name, $all) : SimpleTags_Mcp::error(__('Taxonomy not found.', 'simple-tags'), 404);
        }
        if (!(int) SimpleTags_Plugin::get_option_value('active_taxonomies')) {
            return SimpleTags_Mcp::error(__('Enable taxonomy management first.', 'simple-tags'), 409);
        }
        if (($exists && !isset($all[$name])) || $name === 'media_tag' || (!$exists && in_array($name, taxopress_reserved_taxonomies(), true))) {
            return SimpleTags_Mcp::error(__('This definition is protected.', 'simple-tags'));
        }
        if (SimpleTags_Mcp::stale($input, $all[$name] ?? $all)) {
            return SimpleTags_Mcp::error(__('Taxonomy definitions changed.', 'simple-tags'), 409);
        }
        if ($action === 'delete-taxonomy') {
            if (!isset($all[$name]) || wp_count_terms(['taxonomy' => $name, 'hide_empty' => false]) !== 0) {
                return SimpleTags_Mcp::error(__('Only unused managed definitions can be removed.', 'simple-tags'));
            }
            if (!empty($input['dry_run'])) {
                return ['dry_run' => true, 'name' => $name];
            }
            taxopress_delete_taxonomy(['cpt_custom_tax' => ['name' => $name]]);
            if (isset(taxopress_get_taxonomy_data()[$name])) {
                return SimpleTags_Mcp::error();
            }
            unregister_taxonomy($name);
            return ['deleted' => $name];
        }
        $fields = self::taxonomy_fields();
        $valid = SimpleTags_Mcp::validate($input['fields'], SimpleTags_Mcp::object_schema($fields));
        if (is_wp_error($valid)) {
            return $valid;
        }
        $old = $all[$name] ?? [];
        $defaults = [];
        foreach ($fields as $key => $field) {
            $defaults[$key] = $field['default'];
        }
        $next = array_replace($defaults, $old, $input['fields']);
        if (isset($input['fields']['labels'])) {
            $next['labels'] = array_replace((array) ($old['labels'] ?? []), (array) $input['fields']['labels']);
        }
        foreach ($next['object_types'] as $post_type) {
            $type = get_post_type_object($post_type);
            if (!$type || !current_user_can($type->cap->edit_posts)) {
                return SimpleTags_Mcp::error(__('Insufficient post type permission.', 'simple-tags'), 403);
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'values' => (object) self::view($next, $fields)];
        }
        $next['name'] = $name;
        $next += ['meta_box_cb' => '', 'rest_controller_class' => '', 'order' => 'asc', 'orderby' => 'term_id'];
        foreach ($next as $key => $value) {
            if (is_bool($value)) {
                $next[$key] = (int) $value;
            }
        }
        $form = ['cpt_custom_tax' => $next, 'cpt_tax_labels' => (array) $next['labels'], 'cpt_post_types' => $next['object_types'], 'cpt_tax_status' => $exists ? 'edit' : 'new'];
        unset($form['cpt_custom_tax']['labels'], $form['cpt_custom_tax']['object_types']);
        if ($exists) {
            $form['tax_original'] = $name;
        }
        $result = taxopress_update_taxonomy(wp_slash($form));
        $after = taxopress_get_taxonomy_data();
        if (!in_array($result, ['add_success', 'update_success'], true) || !isset($after[$name])) {
            return SimpleTags_Mcp::error();
        }
        taxopress_register_single_taxonomy($after[$name]);
        return self::taxonomy_view($name, $after);
    }
}
