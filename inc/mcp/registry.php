<?php

/**
 * Private Abilities API integration for Taxonomies.
 * @package Taxonomies
 */

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps, PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- Native direct-access guard.
defined('ABSPATH') || exit;

class SimpleTags_Mcp
{
    public static function boot()
    {
        add_action('wp_abilities_api_categories_init', static function () {
            if (function_exists('wp_has_ability_category') && !wp_has_ability_category('publishpress')) {
                wp_register_ability_category('publishpress', array('label' => __('PublishPress', 'simple-tags'), 'description' => __('PublishPress plugin actions.', 'simple-tags')));
            }
        });
        add_action('wp_abilities_api_init', array(__CLASS__, 'register'));
    }

    public static function pro()
    {
        return defined('PUBLISHPRESS_TAXONOMIES_PRO_VERSION') || defined('TAXOPRESS_PRO_VERSION');
    }

    public static function error($message = '', $status = 400)
    {
        return new WP_Error('taxonomies-mcp', $message ?: __('The operation could not be confirmed. Read current values before retrying; some changes may have been saved.', 'simple-tags'), ['status' => $status]);
    }

    public static function allowed($scope, $write = false, $id = null)
    {
        if (!get_current_user_id()) {
            return false;
        }
        if (in_array($scope, ['posts', 'autoterms-posts'], true)) {
            return ($scope !== 'autoterms-posts' || current_user_can('simple_tags')) && $id && current_user_can('read_post', $id) && current_user_can('edit_post', $id) && (!$write || self::editable_post($id));
        }
        return current_user_can($scope === 'settings' ? 'admin_simple_tags' : 'simple_tags');
    }

    /** Native object permissions and WordPress editor lock, shared by MCP writes. */
    public static function editable_post($id)
    {
        if (!current_user_can('read_post', $id) || !current_user_can('edit_post', $id)) {
            return false;
        }
        if (!function_exists('wp_check_post_lock')) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }
        return !wp_check_post_lock($id);
    }

    public static function hash($value)
    {
        $json = wp_json_encode($value);
        if (!is_string($json)) {
            throw new RuntimeException('Unencodable revision');
        }
        return hash_hmac('sha256', $json, wp_salt('auth'));
    }

    public static function stale($input, $state)
    {
        return !isset($input['expected_hash']) || !hash_equals(self::hash($state), $input['expected_hash']);
    }

    public static function object_schema($properties, $required = array())
    {
        return array('type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false);
    }

    /** Reject scalar coercion and list/object ambiguity before native sanitization. */
    public static function validate($value, $schema)
    {
        if (isset($schema['oneOf'])) {
            $matches = 0;
            foreach ($schema['oneOf'] as $choice) {
                if (!is_wp_error(self::validate($value, $choice))) {
                    ++$matches;
                }
            }
            return $matches === 1 ? true : self::error(__('Incorrect JSON type or value.', 'simple-tags'));
        }
        $type = $schema['type'] ?? '';
        $list = is_array($value) && ($value === array() || array_keys($value) === range(0, count($value) - 1));
        $valid = ($type === 'object' && (is_object($value) || (is_array($value) && (!$list || !$value))))
            || ($type === 'array' && $list)
            || ($type === 'integer' && is_int($value))
            || ($type === 'number' && (is_int($value) || is_float($value)))
            || ($type === 'boolean' && is_bool($value))
            || ($type === 'string' && is_string($value));
        if (!$valid) {
            return self::error(__('Incorrect JSON type.', 'simple-tags'));
        }
        if ($type === 'object') {
            foreach ((array) $value as $key => $child) {
                if (isset($schema['properties'][$key])) {
                    $result = self::validate($child, $schema['properties'][$key]);
                    if (is_wp_error($result)) {
                        return $result;
                    }
                }
            }
        } elseif ($type === 'array') {
            foreach ($value as $child) {
                $result = self::validate($child, $schema['items']);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
        }
        return rest_validate_value_from_schema($value, $schema);
    }

    private static function execute_native($action, $input)
    {
        global $wpdb;
        $previous = $wpdb->suppress_errors();
        $level = ob_get_level();
        ob_start();
        try {
            SimpleTags_Plugin::load_option();
            return SimpleTags_Mcp_Operations::execute($action, $input);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $wpdb->suppress_errors($previous);
        }
    }

    public static function output_schema($action)
    {
        $object = ['type' => 'object'];
        $text = ['type' => 'string'];
        $integer = ['type' => 'integer'];
        $boolean = ['type' => 'boolean'];
        $ids = ['type' => 'array', 'items' => $integer];
        $properties = [
            'expected_hash' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
            'dry_run' => $boolean, 'complete' => $boolean,
        ];
        if (SimpleTags_Mcp_Manage::handles($action)) {
            return self::object_schema(['plan' => $object, 'expected_hash' => $text, 'dry_run' => $boolean, 'complete' => $boolean, 'results' => ['type' => 'array', 'items' => $object], 'failed_id' => $integer, 'message' => $text]);
        }
        if ($action === 'list-features') {
            $properties += ['edition' => $text, 'preset_kinds' => ['type' => 'array', 'items' => $text], 'settings_capability' => $text, 'management_capability' => $text, 'credentials_available' => $boolean, 'features' => ['type' => 'array', 'items' => $object]];
        } elseif (strpos($action, 'plugin-settings') !== false) {
            $properties += ['settings' => $object, 'fields' => $object];
        } elseif ($action === 'export-terms' || $action === 'import-terms') {
            $properties += ['csv' => $text, 'taxonomy' => $text, 'post_type' => $text, 'validated_rows' => $integer, 'result' => $object];
        } elseif (strpos($action, 'merge-terms') !== false) {
            $properties += ['terms' => $object, 'target' => $object, 'removed_ids' => $ids];
        } elseif (strpos($action, 'copy-term') !== false) {
            $properties += ['source' => $object, 'post_ids' => $ids, 'term' => $object];
        } elseif ($action === 'bulk-set-post-terms') {
            $properties += ['validated' => $integer, 'results' => ['type' => 'array', 'items' => $object], 'failed_id' => $integer, 'message' => $text];
        } elseif (strpos($action, 'term-order') !== false) {
            $properties += ['saved_order' => $ids, 'term_ids' => $ids, 'settings' => $object];
        } elseif (in_array($action, ['preview-autoterms', 'apply-autoterms'], true)) {
            $properties += ['plan' => $object, 'linked_terms' => $object, 'replace_type' => $text, 'terms' => $object, 'post' => $object];
        } elseif (strpos($action, 'list-') === 0) {
            $properties += ['items' => ['type' => 'array', 'items' => $object], 'new_expected_hash' => $text, 'fields' => $object];
        } elseif (strpos($action, 'preset') !== false) {
            $properties += ['id' => $integer, 'kind' => $text, 'fields' => $object, 'values' => $object, 'deleted' => $integer];
        } elseif (strpos($action, 'autoterms-schedule') !== false) {
            $properties += ['values' => $object, 'fields' => $object];
        } elseif (strpos($action, 'taxonomy') !== false) {
            $properties += ['name' => $text, 'managed' => $boolean, 'values' => $object, 'fields' => $object, 'deleted' => $text];
        } else {
            $properties += ['id' => $integer, 'taxonomy' => $text, 'name' => $text, 'slug' => $text, 'description' => $text, 'parent' => $integer, 'custom_url' => $text, 'linked_ids' => $ids, 'synonyms' => ['type' => 'array', 'items' => $text], 'fields' => $object, 'features' => $object, 'terms' => $object, 'linked_revision' => $text, 'modified_gmt' => $text, 'term_ids' => $ids, 'deleted' => $integer];
        }
        return self::object_schema($properties);
    }

    public static function register()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        $id = ['type' => 'integer', 'minimum' => 1];
        $zero = ['type' => 'integer', 'minimum' => 0];
        $text = ['type' => 'string'];
        $kind = ['type' => 'string', 'enum' => array_keys(SimpleTags_Mcp_Contracts::kinds())];
        $ids = ['type' => 'array', 'items' => $id, 'uniqueItems' => true, 'maxItems' => 200];
        $write = ['expected_hash' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64], 'dry_run' => ['type' => 'boolean']];
        $defs = [
            'list-features' => ['terms', false, [], [], __('Discover Taxonomies Free and Pro features, native permissions and supported operations.', 'simple-tags')],
            'get-plugin-settings' => ['settings', false, [], [], __('Read native site settings and field definitions. Credentials and license values are excluded.', 'simple-tags')],
            'update-plugin-settings' => ['settings', true, $write + ['settings' => SimpleTags_Mcp_Contracts::site_schema(true)], ['expected_hash', 'settings'], __('Patch native site settings; omitted settings and unrelated values remain unchanged.', 'simple-tags')],
            'get-autoterms-schedule' => ['presets', false, [], [], __('Read the native Auto Terms schedule and edition-specific frequencies.', 'simple-tags')],
            'update-autoterms-schedule' => ['presets', true, $write + ['fields' => ['type' => 'object']], ['expected_hash', 'fields'], __('Patch the native Auto Terms schedule after authorizing all selected configurations and post types.', 'simple-tags')],
            'list-presets' => ['presets', false, ['kind' => $kind], ['kind'], __('List Terms Display, Terms for Current Post, Related Posts, Auto Links or Auto Terms configurations.', 'simple-tags')],
            'get-preset' => ['presets', false, ['kind' => $kind, 'id' => $id], ['kind', 'id'], __('Read a saved configuration and its editable native fields, edition gates and revision.', 'simple-tags')],
            'get-preset-fields' => ['presets', false, ['kind' => $kind], ['kind'], __('Discover native defaults, types and choices for a new configuration.', 'simple-tags')],
            'save-preset' => ['presets', true, $write + ['kind' => $kind, 'id' => $zero, 'fields' => ['type' => 'object']], ['expected_hash', 'kind', 'id', 'fields'], __('Create or patch a configuration using its native save service. Use id zero to create; preview before saving.', 'simple-tags')],
            'delete-preset' => ['presets', true, $write + ['kind' => $kind, 'id' => $id], ['expected_hash', 'kind', 'id'], __('Delete one saved configuration without deleting terms or posts.', 'simple-tags')],
            'list-taxonomies' => ['taxonomies', false, [], [], __('Discover registered taxonomies and Taxonomies-managed definitions.', 'simple-tags')],
            'get-taxonomy' => ['taxonomies', false, ['name' => $text], ['name'], __('Read native taxonomy registration properties, labels, attached post types and revision.', 'simple-tags')],
            'save-taxonomy' => ['taxonomies', true, $write + ['name' => $text, 'fields' => ['type' => 'object']], ['expected_hash', 'name', 'fields'], __('Create or patch a Taxonomies-managed taxonomy. Slugs are immutable; arbitrary PHP callbacks and capabilities are unavailable.', 'simple-tags')],
            'delete-taxonomy' => ['taxonomies', true, $write + ['name' => $text], ['expected_hash', 'name'], __('Remove an unused managed taxonomy definition. Core and third-party definitions are protected.', 'simple-tags')],
            'preview-autoterms' => ['autoterms-posts', false, ['id' => $id, 'preset_id' => $id], ['id', 'preset_id'], __('Preview native local Auto Terms matches for one permitted post; no external provider calls or writes.', 'simple-tags')],
            'apply-autoterms' => ['autoterms-posts', true, $write + ['id' => $id, 'preset_id' => $id], ['id', 'preset_id', 'expected_hash'], __('Apply a current local Auto Terms preview using native matching and permission-preserving WordPress assignment.', 'simple-tags')],
            'preview-merge-terms' => ['terms', false, ['taxonomy' => $text, 'source_ids' => $ids, 'target_id' => $id], ['taxonomy', 'source_ids', 'target_id'], __('Preview merging existing terms into one existing term, including affected posts.', 'simple-tags')],
            'merge-terms' => ['terms', true, $write + ['taxonomy' => $text, 'source_ids' => $ids, 'target_id' => $id], ['taxonomy', 'source_ids', 'target_id', 'expected_hash'], __('Merge a current preview using the native Manage Terms service. Source terms are deleted; all affected posts must be editable.', 'simple-tags')],
            'list-autoterms-logs' => ['terms', false, ['page' => $id, 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]], [], __('Read bounded Auto Terms log summaries only for permitted content; saved credentials and provider diagnostics are excluded.', 'simple-tags')],
            'export-terms' => ['terms', false, ['taxonomy' => $text, 'post_type' => $text], ['taxonomy', 'post_type'], __('Export bounded native term-transfer CSV with readable content assignments and a revision.', 'simple-tags')],
            'import-terms' => ['terms', true, $write + ['taxonomy' => $text, 'post_type' => $text, 'csv' => ['type' => 'string', 'maxLength' => 1048576], 'update_existing' => ['type' => 'boolean']], ['taxonomy', 'post_type', 'csv', 'expected_hash'], __('Preview or import native term-transfer CSV after validating every row, term and content assignment.', 'simple-tags')],
            'get-term' => ['terms', false, ['id' => $id, 'taxonomy' => $text], ['id'], __('Read one permitted term and its current native settings and revision.', 'simple-tags')],
            'list-posts' => ['terms', false, ['term_id' => $id, 'post_type' => $text, 'search' => ['type' => 'string', 'maxLength' => 200], 'page' => $id, 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]], [], __('Find permitted content by term, title or post type using the native Posts screen query. Results exclude content you cannot read and edit; no content bodies or private totals are returned.', 'simple-tags')],
            'list-terms' => ['terms', false, ['taxonomy' => $text, 'page' => $id, 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'search' => $text], ['taxonomy'], __('Find terms in a permitted taxonomy with native identifiers and revisions.', 'simple-tags')],
            'save-term' => ['terms', true, $write + ['taxonomy' => $text, 'id' => $zero, 'fields' => self::object_schema(['name' => $text, 'slug' => $text, 'description' => $text, 'parent' => $zero, 'custom_url' => ['type' => 'string', 'maxLength' => 2000]])], ['expected_hash', 'taxonomy', 'id', 'fields'], __('Create or update a term using native sanitization and taxonomy/object permissions.', 'simple-tags')],
            'delete-term' => ['terms', true, $write + ['taxonomy' => $text, 'id' => $id], ['expected_hash', 'taxonomy', 'id'], __('Delete a term only after authorizing every affected post and native related term.', 'simple-tags')],
            'get-post-terms' => ['posts', false, ['id' => $id], ['id'], __('Read permitted native post term selections and Auto Links/Auto Terms feature overrides.', 'simple-tags')],
            'set-post-terms' => ['posts', true, $write + ['id' => $id, 'taxonomy' => $text, 'term_ids' => $ids], ['expected_hash', 'id', 'taxonomy', 'term_ids'], __('Replace one post taxonomy selection, preserving other taxonomies and respecting editor locks.', 'simple-tags')],
            'bulk-set-post-terms' => ['terms', true, ['dry_run' => ['type' => 'boolean'], 'items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 20, 'items' => self::object_schema(['id' => $id, 'taxonomy' => $text, 'term_ids' => $ids, 'expected_hash' => $write['expected_hash']], ['id', 'taxonomy', 'term_ids', 'expected_hash'])]], ['items'], __('Validate all post selections before bulk assignment and report partial results honestly.', 'simple-tags')],
            'set-post-features' => ['posts', true, $write + ['id' => $id, 'features' => self::object_schema(['autolinks' => ['type' => 'string', 'enum' => ['default', 'enabled', 'disabled']], 'autoterms' => ['type' => 'string', 'enum' => ['default', 'enabled', 'disabled']]])], ['expected_hash', 'id', 'features'], __('Set native per-post Auto Links or Auto Terms overrides without modifying content.', 'simple-tags')],
        ];
        if (self::pro()) {
            $defs['preview-copy-term'] = ['terms', false, ['id' => $id], ['id'], __('Preview a native Pro term copy with metadata, without exposing metadata values.', 'simple-tags')];
            $defs['copy-term'] = ['terms', true, $write + ['id' => $id], ['id', 'expected_hash'], __('Copy a current Pro term preview through the native service; all associated content must be editable.', 'simple-tags')];
            $defs['copy-preset'] = ['presets', true, $write + ['kind' => $kind, 'id' => $id], ['kind', 'id', 'expected_hash'], __('Copy a permitted Pro configuration through its native copy service without exposing credentials.', 'simple-tags')];
            $defs['get-term-order'] = ['terms', false, ['taxonomy' => $text], ['taxonomy'], __('Read native Pro term ordering and its revision.', 'simple-tags')];
            $defs['set-term-order'] = ['terms', true, $write + ['taxonomy' => $text, 'term_ids' => $ids], ['taxonomy', 'term_ids', 'expected_hash'], __('Reorder permitted Pro terms using native persistence; omitted terms retain their positions.', 'simple-tags')];
            $defs['get-term-relations'] = ['terms', false, ['id' => $id], ['id'], __('Read native Pro term synonyms and linked-term relations.', 'simple-tags')];
            $defs['set-term-relations'] = ['terms', true, $write + ['id' => $id, 'synonyms' => ['type' => 'array', 'uniqueItems' => true, 'maxItems' => 100, 'items' => $text], 'linked_ids' => $ids], ['expected_hash', 'id'], __('Patch Pro synonyms or linked terms with native feature gates and permission checks for all related objects.', 'simple-tags')];
        }
        $labels = [
            'list-features' => __('Discover Taxonomies features', 'simple-tags'),
            'list-posts' => __('Find posts by term', 'simple-tags'),
            'get-plugin-settings' => __('Get plugin settings', 'simple-tags'),
            'update-plugin-settings' => __('Update plugin settings', 'simple-tags'),
            'get-autoterms-schedule' => __('Get Auto Terms schedule', 'simple-tags'),
            'update-autoterms-schedule' => __('Update Auto Terms schedule', 'simple-tags'),
            'list-presets' => __('List configurations', 'simple-tags'),
            'get-preset' => __('Get configuration', 'simple-tags'),
            'get-preset-fields' => __('Get configuration fields', 'simple-tags'),
            'save-preset' => __('Save configuration', 'simple-tags'),
            'delete-preset' => __('Delete configuration', 'simple-tags'),
            'copy-preset' => __('Copy configuration', 'simple-tags'),
            'preview-copy-term' => __('Preview term copy', 'simple-tags'),
            'copy-term' => __('Copy term', 'simple-tags'),
            'list-taxonomies' => __('List taxonomies', 'simple-tags'),
            'get-taxonomy' => __('Get taxonomy', 'simple-tags'),
            'save-taxonomy' => __('Save taxonomy', 'simple-tags'),
            'delete-taxonomy' => __('Delete taxonomy', 'simple-tags'),
            'list-terms' => __('List terms', 'simple-tags'),
            'get-term' => __('Get term', 'simple-tags'),
            'save-term' => __('Save term', 'simple-tags'),
            'delete-term' => __('Delete term', 'simple-tags'),
            'get-post-terms' => __('Get post terms', 'simple-tags'),
            'set-post-terms' => __('Set post terms', 'simple-tags'),
            'bulk-set-post-terms' => __('Bulk set post terms', 'simple-tags'),
            'set-post-features' => __('Set post features', 'simple-tags'),
            'preview-autoterms' => __('Preview Auto Terms', 'simple-tags'),
            'apply-autoterms' => __('Apply Auto Terms', 'simple-tags'),
            'list-autoterms-logs' => __('List Auto Terms logs', 'simple-tags'),
            'preview-merge-terms' => __('Preview term merge', 'simple-tags'),
            'merge-terms' => __('Merge terms', 'simple-tags'),
            'export-terms' => __('Export terms', 'simple-tags'),
            'import-terms' => __('Import terms', 'simple-tags'),
            'get-term-relations' => __('Get term relationships', 'simple-tags'),
            'set-term-relations' => __('Set term relationships', 'simple-tags'),
            'get-term-order' => __('Get term order', 'simple-tags'),
            'set-term-order' => __('Set term order', 'simple-tags'),
        ];
        $maintenance = [
            'rename-taxonomy' => [['source' => $text, 'new_name' => $text], __('Rename a managed taxonomy slug and convert its terms while preserving IDs, metadata and relationships. Requires site settings and all affected object permissions. Native preset/settings references are updated; third-party references are not rewritten.', 'simple-tags'), __('Rename taxonomy', 'simple-tags')],
            'convert-taxonomy' => [['source' => $text, 'destination' => $text], __('Convert all terms of a managed taxonomy into an existing permitted taxonomy and remove the source definition. Name/slug conflicts and incompatible hierarchies are rejected. Term IDs and metadata are preserved.', 'simple-tags'), __('Convert taxonomy', 'simple-tags')],
            'move-terms' => [['source' => $text, 'destination' => $text, 'term_ids' => $ids + ['minItems' => 1]], __('Move explicit complete term hierarchies between existing permitted taxonomies, preserving IDs, metadata and assignments. Source definitions remain; default terms and conflicts are protected.', 'simple-tags'), __('Move terms between taxonomies', 'simple-tags')],
            'replace-term-text' => [['taxonomy' => $text, 'find' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'replace' => ['type' => 'string', 'maxLength' => 200]], __('Replace literal case-sensitive text in matching term names across one taxonomy. Post bodies and slugs are unchanged; IDs, metadata and relationships are preserved. Conflicts reject the entire plan.', 'simple-tags'), __('Find and replace term names', 'simple-tags')],
            'cleanup-terms' => [['taxonomy' => $text, 'below_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]], __('Delete terms used fewer than the native threshold. Preview lists candidates, protected default terms and affected posts. Usage and permissions are rechecked before deletion.', 'simple-tags'), __('Clean up rarely used terms', 'simple-tags')],
            'match-terms' => [['taxonomy' => $text, 'post_type' => $text, 'match_ids' => $ids, 'target_ids' => $ids + ['minItems' => 1], 'mode' => ['type' => 'string', 'enum' => ['add', 'remove', 'replace']]], __('Find posts carrying any selected term, then add, remove or replace those assignments using explicit existing term IDs. Empty match IDs selects published/inherited content of the selected type. Unrelated assignments are preserved and native Pro linked-term propagation applies.', 'simple-tags'), __('Change matching post terms', 'simple-tags')],
        ];
        foreach ($maintenance as $operation => $definition) {
            [$properties, $description, $label] = $definition;
            $required = array_keys($properties);
            $defs['preview-' . $operation] = ['terms', false, $properties, $required, $description];
            $defs[$operation] = ['terms', true, $properties + $write, array_merge($required, ['expected_hash']), $description];
            $labels[$operation] = $label;
            $labels['preview-' . $operation] = sprintf(__('Preview: %s', 'simple-tags'), $label);
        }
        foreach ($defs as $name => $def) {
            [$scope, $is_write, $properties, $required, $description] = $def;
            $schema = self::object_schema($properties, $required);
            wp_register_ability('publishpress-taxonomies/' . $name, [
                'label' => $labels[$name], 'description' => $description, 'category' => 'publishpress',
                'input_schema' => $schema, 'output_schema' => self::output_schema($name),
                'permission_callback' => static function ($input) use ($schema, $scope, $is_write) {
                    return !is_wp_error(self::validate($input, $schema)) && self::allowed($scope, $is_write, ((array) $input)['id'] ?? null);
                },
                'execute_callback' => static function ($input) use ($schema, $scope, $is_write, $name) {
                    try {
                        if (is_wp_error(self::validate($input, $schema)) || !self::allowed($scope, $is_write, ((array) $input)['id'] ?? null)) {
                            return self::error(__('Invalid input or insufficient permission.', 'simple-tags'), 403);
                        }
                        return self::execute_native($name, json_decode(wp_json_encode($input), true));
                    } catch (Throwable $error) {
                        return self::error();
                    }
                },
                'meta' => ['public' => false, 'show_in_rest' => false, 'mcp' => ['public' => false], 'annotations' => ['readonly' => !$is_write, 'destructive' => $is_write, 'idempotent' => !$is_write]],
            ]);
        }
    }
}
