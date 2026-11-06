<?php

/** Native settings and saved-configuration contracts. @package PublishPress Taxonomies */

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- Native direct-access guard.
defined('ABSPATH') || exit;
class SimpleTags_Mcp_Contracts
{
    public static function kinds()
    {
        return [
            'tag_cloud' => ['get' => 'taxopress_get_tagcloud_data', 'save' => 'taxopress_update_tagcloud', 'delete' => 'taxopress_action_delete_tagcloud', 'inner' => 'taxopress_tag_cloud', 'edit' => 'edited_tagcloud', 'feature' => 'active_terms_display', 'limit' => 'taxopress_tag_clouds_create_limit'],
            'post_tags' => ['get' => 'taxopress_get_posttags_data', 'save' => 'taxopress_update_posttags', 'delete' => 'taxopress_action_delete_posttags', 'inner' => 'taxopress_post_tags', 'edit' => 'edited_posttags', 'feature' => 'active_post_tags', 'limit' => 'taxopress_post_tags_create_limit'],
            'related_posts' => ['get' => 'taxopress_get_relatedpost_data', 'save' => 'taxopress_update_relatedpost', 'delete' => 'taxopress_action_delete_relatedpost', 'inner' => 'taxopress_related_post', 'edit' => 'edited_relatedpost', 'feature' => 'active_related_posts_new', 'limit' => 'taxopress_related_posts_create_limit'],
            'autolink' => ['get' => 'taxopress_get_autolink_data', 'save' => 'taxopress_update_autolink', 'delete' => 'taxopress_action_delete_autolink', 'inner' => 'taxopress_autolink', 'edit' => 'edited_autolink', 'feature' => 'active_auto_links', 'limit' => 'taxopress_autolinks_create_limit'],
            'autoterm' => ['get' => 'taxopress_get_autoterm_data', 'save' => 'taxopress_update_autoterm', 'delete' => 'taxopress_action_delete_autoterm', 'inner' => 'taxopress_autoterm', 'edit' => 'edited_autoterm', 'feature' => 'active_auto_terms', 'limit' => 'taxopress_autoterms_create_limit'],
        ];
    }

    public static function secret($name)
    {
        return (bool) preg_match('/api_key|api_token|password|secret|license|licence|api_url|access_token|auth_token/i', $name);
    }

    public static function site_fields()
    {
        $include_fast_update = true;
        $sections = include STAGS_DIR . '/inc/helper.options.admin.php';
        $defaults = SimpleTags_Plugin::load_default_option();
        $fields = [];
        foreach ($sections as $section => $controls) {
            foreach ($controls as $control) {
                [$key, $label, $type, $choices] = array_pad($control, 4, null);
                if (self::secret($key) || in_array($type, ['helper', 'licence_field', 'password'], true)) {
                    continue;
                }
                if ($type === 'sub_multiple_checkbox') {
                    foreach ((array) $choices as $child => $definition) {
                        $fields[$child] = ['type' => 'boolean', 'default' => (int) ($defaults[$child] ?? 0) === 1, 'description' => wp_strip_all_tags($definition['label'] ?? $child), 'section' => $section];
                    }
                    continue;
                }
                $field = ['type' => 'string', 'default' => is_scalar($defaults[$key] ?? '') ? (string) ($defaults[$key] ?? '') : '', 'description' => wp_strip_all_tags($label), 'section' => $section];
                if ($type === 'checkbox') {
                    $field['type'] = 'boolean';
                    $field['default'] = (int) ($defaults[$key] ?? 0) === 1;
                } elseif ($type === 'number') {
                    $field['type'] = 'integer';
                    $field['default'] = (int) ($defaults[$key] ?? 0);
                } elseif (strpos($type, 'multiselect') !== false) {
                    $field['type'] = 'array';
                    $field['items'] = ['type' => 'string', 'enum' => array_map('strval', array_keys((array) $choices))];
                    $field['uniqueItems'] = true;
                    $field['default'] = (array) ($defaults[$key] ?? []);
                } elseif (in_array($type, ['select', 'select_with_icon', 'radio', 'dropdown'], true)) {
                    $field['enum'] = array_map('strval', array_keys((array) $choices));
                    if (!in_array($field['default'], $field['enum'], true)) {
                        $field['enum'][] = $field['default'];
                    }
                } elseif (!in_array($type, ['text', 'text-color', 'textarea'], true)) {
                    continue;
                }
                $fields[$key] = $field;
            }
        }
        foreach ($defaults as $key => $value) {
            if (strpos($key, 'active_') === 0) {
                $fields[$key] = ['type' => 'boolean', 'default' => (int) $value === 1, 'description' => ucwords(str_replace('_', ' ', $key)), 'section' => 'features'];
            }
        }
        foreach (TaxoPress_AI_Module::tab_label_defaults() as $key => $default) {
            $fields[$key] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 80, 'default' => $default, 'description' => $default, 'section' => 'fast_update_labels'];
        }
        if (!SimpleTags_Mcp::pro()) {
            unset($fields['active_features_synonyms'], $fields['active_features_linked_terms']);
        }
        return $fields;
    }

    public static function site_schema($registration = false)
    {
        $fields = self::site_fields();
        if ($registration) {
            // Runtime discovery validates current taxonomy/role choices, including objects created in this request.
            foreach ($fields as &$field) {
                if ($field['type'] === 'array') {
                    unset($field['items']['enum']);
                }
            }
            unset($field);
        }
        return SimpleTags_Mcp::object_schema($fields);
    }

    public static function ui_fields()
    {
        return [
            'autolink' => [
                'title' => ['string', 'Title'],
                'taxonomy' => ['string', 'Taxonomy'],
                'autolink_case' => ['string', 'Autolink case', ['none', 'termcase', 'uppercase', 'lowercase']],
                'autolink_display' => ['string', 'Autolink display', ['post_content', 'post_title', 'post_excerpt', 'posts', 'post_content_excerpt', 'post_title_excerpt', 'posts_excerpt']],
                'autolink_title_attribute' => ['string', 'Autolink title attribute'],
                'autolink_title_attribute_when_using_custom_url' => ['string', 'Autolink title attribute when using custom url'],
                'autolink_usage_min' => ['integer', 'Autolink usage min'],
                'autolink_usage_max' => ['integer', 'Autolink usage max'],
                'autolink_same_usage_max' => ['integer', 'Autolink same usage max'],
                'autolink_min_char' => ['integer', 'Autolink min char'],
                'autolink_max_char' => ['integer', 'Autolink max char'],
                'whole_words' => ['string', 'Whole words', ['0', '1']],
                'auto_link_exclude' => ['string', 'Auto link exclude'],
                'autolink_exclude_class' => ['string', 'Autolink exclude class'],
                'synonyms_link' => ['string', 'Synonyms link', ['0', '1']],
                'unattached_terms' => ['string', 'Unattached terms', ['0', '1']],
                'autolink_dom' => ['string', 'Autolink dom', ['0', '1']],
                'archivepage' => ['string', 'Enable Auto Link to archive pages', ['1', '0']],
                'enable_custom_urls' => ['string', 'Enable Custom URLs', ['1', '0']],
                'link_class' => ['string', 'Term link class'],
                'hook_priority' => ['integer', 'Hook priority'],
            ],
            'autoterm' => [
                'title' => ['string', 'Title'],
                'taxonomy' => ['string', 'Taxonomy'],
                'autoterm_for_post' => ['string', 'Post'],
                'terms_limit' => ['integer', 'Terms limit'],
                'autoterm_target' => ['string', 'Target content'],
                'autoterm_word' => ['string', 'Whole words'],
                'autoterm_hash' => ['string', 'Hashtags'],
                'post_replace_type' => ['string', 'Post replace type'],
                'autoterm_for_schedule' => ['string', 'Schedule'],
                'schedule_terms_limit' => ['integer', 'Schedule terms limit'],
                'schedule_autoterm_target' => ['string', 'Target content'],
                'schedule_autoterm_word' => ['string', 'Whole words'],
                'schedule_autoterm_hash' => ['string', 'Hashtags'],
                'schedule_replace_type' => ['string', 'Schedule replace type'],
                'autoterm_for_existing_content' => ['string', 'Existing Content'],
                'existing_content_terms_limit' => ['integer', 'Existing content terms limit'],
                'existing_content_replace_type' => ['string', 'Existing content replace type'],
                'autoterm_for_metaboxes' => ['string', 'Metaboxes'],
                'autoterm_use_taxonomy' => ['string', 'Existing taxonomy terms', ['0', '1']],
                'autoterm_useall' => ['string', 'Autoterm useall', ['0', '1']],
                'autoterm_useonly' => ['string', 'Autoterm useonly', ['0', '1']],
                'suggest_local_terms_show_post_count' => ['string', 'Show Term Post Count', ['0', '1']],
                'synonyms_term' => ['string', 'Synonyms term', ['0', '1']],
                'autoterm_exclude' => ['string', 'Autoterm exclude'],
                'autoterm_existing_content_exclude' => ['string', 'Exclude previously analyzed content', ['0', '1']],
                'existing_terms_batches' => ['integer', 'Existing terms batches'],
                'existing_terms_sleep' => ['integer', 'Batches wait time'],
                'limit_days' => ['integer', 'Limit days', ['1', '7', '14', '30', '180', '365', '0']],
            ],
            'tag_cloud' => [
                'title' => ['string', 'Title'],
                'hide_title' => ['string', 'Hide title in output?', ['0', '1']],
                'post_type' => ['string', 'Post Type'],
                'taxonomy' => ['string', 'Taxonomy'],
                'parent_term' => ['string', 'Parent Term'],
                'display_mode' => ['string', 'Display Mode', ['parents_only', 'sub_terms_only', 'parents_and_sub']],
                'include_terms' => ['string', 'Custom terms to display'],
                'min_usage' => ['integer', 'Minimum term usage'],
                'limit_days' => ['integer', 'Limit terms based on timeframe', ['1', '7', '14', '30', '180', '365', '0']],
                'order' => ['string', 'Order direction', ['asc', 'desc']],
                'format' => ['string', 'Display format', ['flat', 'list', 'ol', 'comma', 'table', 'border', 'parent/child']],
                'before' => ['string', 'Before'],
                'after' => ['string', 'After'],
                'exclude_terms' => ['string', 'Exclude terms from terms display'],
                'hide_terms' => ['string', 'Exclude hidden terms from Terms Display?', ['0', '1']],
                'hide_output' => ['string', 'Hide display output if no terms?', ['0', '1']],
                'max' => ['integer', 'Maximum terms to display'],
                'smallest' => ['integer', 'Font size minimum'],
                'largest' => ['integer', 'Font size maximum'],
                'unit' => ['string', 'Unit font size', ['pt', 'px', 'em', '%']],
                'color' => ['string', 'Enable colors for terms', ['0', '1']],
                'mincolor' => ['string', 'Font color minimum'],
                'maxcolor' => ['string', 'Font color maximum'],
                'wrap_class' => ['string', 'Term display div class'],
                'link_class' => ['string', 'Term link class'],
                'xformat' => ['string', 'Term link format'],
            ],
            'post_tags' => [
                'title' => ['string', 'Title'],
                'taxonomy' => ['string', 'Taxonomy'],
                'limit_days' => ['integer', 'Limit terms based on timeframe', ['1', '7', '14', '30', '180', '365', '0']],
                'order' => ['string', 'Order direction', ['asc', 'desc']],
                'smallest' => ['integer', 'Font size minimum'],
                'largest' => ['integer', 'Font size maximum'],
                'unit' => ['string', 'Unit font size', ['pt', 'px', 'em', '%']],
                'color' => ['string', 'Enable colors for terms', ['0', '1']],
                'mincolor' => ['string', 'Font color minimum'],
                'maxcolor' => ['string', 'Font color maximum'],
                'display_position' => ['string', 'Automatic display position', ['0', '1', 'above', 'below']],
                'number' => ['integer', 'Number'],
                'separator' => ['string', 'Separator'],
                'before' => ['string', 'Before'],
                'after' => ['string', 'After'],
                'notagtext' => ['string', 'Notagtext'],
                'hide_output' => ['string', 'Hide output', ['0', '1']],
                'wrap_class' => ['string', 'Terms for Current Post div class'],
                'link_class' => ['string', 'Term link class'],
                'xformat' => ['string', 'Term link format'],
                'hide_terms' => ['string', 'Exclude hidden terms from Current Post?', ['0', '1']],
            ],
            'related_posts' => [
                'title' => ['string', 'Title'],
                'hide_title' => ['string', 'Hide title', ['0', '1']],
                'title_header' => ['string', 'Title header', ['', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']],
                'taxonomy' => ['string', 'Taxonomy'],
                'smallest' => ['integer', 'Font size minimum'],
                'largest' => ['integer', 'Font size maximum'],
                'unit' => ['string', 'Unit font size', ['pt', 'px', 'em', '%']],
                'color' => ['string', 'Enable colors for terms', ['0', '1']],
                'mincolor' => ['string', 'Font color minimum'],
                'maxcolor' => ['string', 'Font color maximum'],
                'max_related_posts' => ['integer', 'Max related posts'],
                'format' => ['string', 'Display format', ['box', 'list', 'ol']],
                'xformat' => ['string', 'Term link format'],
                'before' => ['string', 'Before'],
                'after' => ['string', 'After'],
                'limit_days' => ['integer', 'Limit days', ['1', '7', '14', '30', '180', '365', '0']],
                'order' => ['string', 'Order', ['count-asc', 'count-desc', 'date-asc', 'date-desc', 'name-asc', 'name-desc', 'random']],
                'nopoststext' => ['string', 'Nopoststext'],
                'hide_output' => ['string', 'Hide output', ['0', '1']],
                'wrap_class' => ['string', 'Related Posts div class'],
                'link_class' => ['string', 'Term link class'],
                'dateformat' => ['string', 'Post date format', ['d.m.Y', 'F j, Y', 'Y-m-d', 'm/d/Y', 'd M, Y']],
                'max_post_chars' => ['integer', 'Max post chars'],
                'taxopress_max_cats' => ['integer', 'Taxopress max cats'],
                'taxopress_max_tags' => ['integer', 'Taxopress max tags'],
                'imageresolution' => ['string', 'Thumbnail image resolution', ['thumbnail', 'medium', 'large', '1536x1536', '2048x2048']],
            ],
        ];
    }

    public static function defaults($kind)
    {
        $function = 'taxopress_get_' . $kind . '_defaults';
        $data = $function();
        $meta = self::kinds()[$kind];
        $values = $data[$meta['inner']];
        foreach ($data as $key => $value) {
            if (is_array($value) && $key !== $meta['inner']) {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public static function preset_fields($kind)
    {
        $defaults = self::defaults($kind);
        $fields = [];
        $ui = self::ui_fields()[$kind];
        foreach ($ui as $key => $def) {
            if (self::secret($key)) {
                continue;
            }
            $type = isset($def[2]) ? 'string' : $def[0];
            if (isset($defaults[$key]) && is_array($defaults[$key])) {
                $type = 'array';
            }
            $field = ['type' => $type, 'description' => $def[1]];
            if ($type === 'array') {
                $field['items'] = ['type' => 'string'];
                $field['default'] = $defaults[$key] ?? [];
            } elseif ($type === 'boolean') {
                $field['default'] = (bool) ($defaults[$key] ?? 0);
            } elseif ($type === 'integer') {
                $field['default'] = (int) ($defaults[$key] ?? 0);
            } else {
                $field['default'] = (string) ($defaults[$key] ?? '');
            }
            if (isset($def[2]) && $type === 'string') {
                $field['enum'] = array_values(array_unique(array_merge($def[2], [$field['default']])));
            }
            if (isset($field['enum']) && $field['default'] === '' && !in_array('', $def[2], true)) {
                $field['default'] = (string) $def[2][0];
            }
            if ($key === 'taxonomy') {
                $field['enum'] = array_values(get_taxonomies(['show_ui' => true]));
            }
            $fields[$key] = $field;
        }
        foreach ($defaults as $key => $value) {
            if (self::secret($key) || isset($fields[$key]) || $key === 'ID') {
                continue;
            }
            if (is_array($value)) {
                $fields[$key] = ['type' => 'array', 'items' => ['type' => 'string'], 'default' => []];
            } else {
                $fields[$key] = ['type' => is_int($value) ? 'integer' : 'string', 'default' => $value];
            }
        }
        $fields['title'] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'html_entities' => true, 'default' => $defaults['title'] ?? $kind];
        if (SimpleTags_Mcp::pro() && $kind === 'autoterm') {
            $fields['additional_taxonomies'] = ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_values(get_taxonomies(['show_ui' => true]))], 'uniqueItems' => true, 'default' => []];
        }
        if ($kind === 'autoterm') {
            foreach (['autoterm_for_post', 'autoterm_target', 'autoterm_word', 'autoterm_hash', 'autoterm_for_schedule', 'schedule_autoterm_target', 'schedule_autoterm_word', 'schedule_autoterm_hash', 'autoterm_for_existing_content', 'autoterm_for_metaboxes'] as $key) {
                $fields[$key]['enum'] = ['0', '1'];
                $fields[$key]['default'] = get_taxopress_disp_boolean($defaults[$key] ?? '0') ? '1' : '0';
            }
            foreach (['post_replace_type', 'schedule_replace_type', 'existing_content_replace_type'] as $key) {
                $fields[$key]['enum'] = ['', 'append', 'replace', 'append_and_replace'];
            }
            $fields['autoterm_from']['enum'] = ['post_title', 'post_content', 'posts'];
            $fields['existing_terms_batches']['default'] = 2;
            $fields['existing_terms_sleep']['default'] = 10;
            $fields['html_exclusion'] = ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'script', 'style', 'pre', 'code']], 'default' => [], 'uniqueItems' => true];
        }
        if ($kind === 'post_tags') {
            $fields['display_position']['enum'] = ['above', 'below'];
        }
        if ($kind === 'autolink') {
            $fields['ignore_case']['enum'] = ['0', '1'];
        }
        foreach (['post_types', 'enable_customurl_field'] as $key) {
            if (isset($fields[$key])) {
                $fields[$key]['items']['enum'] = $key === 'post_types' ? array_values(get_post_types(['public' => true])) : array_values(get_taxonomies(['show_ui' => true]));
            }
        }
        if (isset($fields['post_status'])) {
            $fields['post_status']['items']['enum'] = ['publish', 'future', 'draft', 'pending'];
        }
        if (!SimpleTags_Mcp::pro()) {
            if ($kind === 'autolink') {
                foreach (['html_exclusion_customs', 'html_exclusion_customs_entry', 'shortcodes_exclusion', 'shortcodes_exclusion_entries', 'blocks_exclusion', 'blocks_exclusion_entries', 'synonyms_link'] as $key) {
                    unset($fields[$key]);
                }
            }
            if ($kind === 'autoterm') {
                unset($fields['synonyms_term']);
            }
        }
        $fields = apply_filters('taxopress_mcp_preset_fields', $fields, $kind);
        foreach ($fields as &$field) {
            if ($field['type'] === 'string') {
                $field += ['maxLength' => 20000];
            }
            if ($field['type'] === 'array') {
                $field += ['maxItems' => 500];
                $field['items'] += ['maxLength' => 2000];
            }
            if ($field['type'] === 'integer') {
                $field += ['minimum' => 0, 'maximum' => 100000];
            }
        }
        unset($field);
        if ($kind === 'autolink') {
            foreach (['autolink_min_char', 'autolink_max_char'] as $key) {
                $fields[$key] = ['oneOf' => [['type' => 'integer', 'minimum' => 0, 'maximum' => 100000], ['type' => 'string', 'enum' => ['']]], 'default' => $defaults[$key], 'description' => $fields[$key]['description']];
            }
        }
        foreach (['mincolor', 'maxcolor'] as $key) {
            if (isset($fields[$key])) {
                $fields[$key]['pattern'] = '^(?:#[a-fA-F0-9]{3}|#[a-fA-F0-9]{6}|#[a-fA-F0-9]{8})?$';
            }
        }
        return $fields;
    }

    public static function canonical($value, $field)
    {
        if (!empty($field['html_entities'])) {
            return htmlspecialchars_decode((string) $value, ENT_QUOTES);
        }
        if (isset($field['oneOf'])) {
            return $value === '' ? '' : (int) $value;
        }
        if ($field['type'] === 'string' && isset($field['enum']) && count($field['enum']) === 2 && !array_diff($field['enum'], ['0', '1'])) {
            return get_taxopress_disp_boolean($value) ? '1' : '0';
        }
        if ($field['type'] === 'object') {
            return (object) $value;
        }
        if ($field['type'] === 'boolean') {
            return (int) $value === 1;
        }
        if ($field['type'] === 'integer') {
            return (int) $value;
        }
        if ($field['type'] === 'number') {
            return (float) $value;
        }
        if ($field['type'] === 'array') {
            return array_values((array) $value);
        }
        return (string) $value;
    }
}
