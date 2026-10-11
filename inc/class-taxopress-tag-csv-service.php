<?php

/**
 * CSV parsing and tag transfer, independent of HTTP handlers.
 *
 * @package TaxoPress
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Trusted-call service for validated standard-tag CSV transfers. */
class TaxoPress_Tag_CSV_Service
{
    public const MAX_BYTES        = 5242880;
    public const MAX_ROWS         = 5000;
    public const MAX_ASSOCIATIONS = 25000;
    public const BATCH_SIZE       = 250;

    /**
     * Parse from the stream's current position. No database mutations.
     *
     * @param resource $stream Readable stream containing UTF-8 CSV.
     * @param bool     $skip_missing_posts Allow missing or taxonomy-incompatible targets.
     * @return array|WP_Error Rows with slug/name/description/row and optional pipe-delimited post_ids.
     */
    public static function parse_csv($stream, $skip_missing_posts = false)
    {
        if (! is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            return new WP_Error('taxopress_tag_csv_stream', __('A readable CSV stream is required.', 'simple-tags'));
        }
        $data = stream_get_contents($stream, self::MAX_BYTES + 1);
        if (false === $data) {
            return new WP_Error('taxopress_tag_csv_read', __('The CSV file could not be read.', 'simple-tags'));
        }
        if (strlen($data) > self::MAX_BYTES) {
            return new WP_Error('taxopress_tag_csv_size', __('The CSV file exceeds the 5 MiB import limit.', 'simple-tags'));
        }
        if (0 === strpos($data, "\xEF\xBB\xBF")) {
            $data = substr($data, 3);
        }
        $records = self::read_records($data);
        if (is_wp_error($records)) {
            return $records;
        }
        $header = empty($records) ? array() : array_shift($records);
        if (array( 'slug', 'name', 'description' ) !== $header && array( 'slug', 'name', 'description', 'post_ids' ) !== $header) {
            return new WP_Error('taxopress_tag_csv_header', __('CSV record 1 must contain exactly slug,name,description,post_ids or the legacy slug,name,description header.', 'simple-tags'));
        }
        $width = count($header);
        $rows  = array();
        foreach ($records as $index => $record) {
            $number = $index + 2;
            if (count($record) !== $width) {
                return self::row_error($number, __('Column count must match the header exactly.', 'simple-tags'));
            }
            $row = array(
                'slug'        => self::unescape_cell($record[0]),
                'name'        => self::unescape_cell($record[1]),
                'description' => self::unescape_cell($record[2]),
                'row'         => $number,
            );
            if (4 === $width) {
                $row['post_ids'] = self::unescape_cell($record[3]);
            }
            $rows[] = $row;
        }
        $valid = self::validate_rows($rows, $skip_missing_posts);
        return is_wp_error($valid) ? $valid : $rows;
    }

    /**
     * Strict RFC-style CSV parser. Embedded CR/LF are retained verbatim.
     * Reject malformed quotes rather than accepting ambiguous fgetcsv() input.
     *
     * @param string $data Bounded CSV bytes without BOM.
     * @return array|WP_Error
     */
    private static function read_records($data)
    {
        $records = array();
        $record  = array();
        $field   = '';
        $state   = 'start';
        $pending = false;
        $length  = strlen($data);
        for ($i = 0; $i < $length; ++$i) {
            $char    = $data[ $i ];
            $pending = true;
            if ('quoted' === $state) {
                if ('"' === $char) {
                    if ($i + 1 < $length && '"' === $data[ $i + 1 ]) {
                        $field .= '"';
                        ++$i;
                    } else {
                        $state = 'closed';
                    }
                } else {
                    $field .= $char;
                }
                continue;
            }
            if (',' === $char || "\r" === $char || "\n" === $char) {
                $record[] = $field;
                $field    = '';
                $state    = 'start';
                if (',' === $char) {
                    if (count($record) > 3) {
                        return self::row_error(count($records) + 1, __('CSV records cannot contain more than four columns.', 'simple-tags'));
                    }
                    continue;
                }
                $records[] = $record;
                $record    = array();
                $pending   = false;
                if (count($records) > self::MAX_ROWS + 1) {
                    return self::row_error(count($records), __('The CSV file exceeds the 5,000-tag import limit.', 'simple-tags'));
                }
                if ("\r" === $char && $i + 1 < $length && "\n" === $data[ $i + 1 ]) {
                    ++$i;
                }
                continue;
            }
            if ('closed' === $state || ( 'plain' === $state && '"' === $char )) {
                return self::row_error(count($records) + 1, __('Malformed CSV quoting.', 'simple-tags'));
            }
            if ('start' === $state && '"' === $char) {
                $state = 'quoted';
            } else {
                $state  = 'plain';
                $field .= $char;
            }
        }
        if ('quoted' === $state) {
            return self::row_error(count($records) + 1, __('Unclosed quoted CSV field.', 'simple-tags'));
        }
        if ($pending) {
            $record[]  = $field;
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Validate all rows again so direct service callers cannot bypass preflight.
     *
     * @param array $rows Raw associative tag records.
     * @param bool  $skip_missing_posts Allow missing or taxonomy-incompatible targets.
     * @return array|WP_Error Map of target IDs to skip, or a validation failure.
     */
    private static function validate_rows($rows, $skip_missing_posts = false)
    {
        if (! is_array($rows) || count($rows) > self::MAX_ROWS) {
            return new WP_Error('taxopress_tag_csv_rows', __('Import requires an array of no more than 5,000 tags.', 'simple-tags'));
        }
        $seen   = array();
        $number = 1;
        foreach ($rows as $row) {
            ++$number;
            foreach (array( 'slug', 'name', 'description' ) as $key) {
                if (! is_array($row) || ! isset($row[ $key ]) || ! is_string($row[ $key ])) {
                    return self::row_error($number, __('Each tag must have string slug, name, and description fields.', 'simple-tags'));
                }
                if (1 !== preg_match('//u', $row[ $key ]) || false !== strpos($row[ $key ], "\0")) {
                    return self::row_error($number, __('Fields must contain valid UTF-8 without null bytes.', 'simple-tags'));
                }
            }
            if ('' === trim($row['name']) || '' === trim(wp_strip_all_tags($row['name'])) || '' === trim($row['slug'])) {
                return self::row_error($number, __('Name and slug must be nonempty and usable.', 'simple-tags'));
            }
            if (strlen($row['name']) > 200 || strlen($row['slug']) > 200) {
                return self::row_error($number, __('Name and slug must each be at most 200 bytes; values are not truncated.', 'simple-tags'));
            }
            if (strlen($row['description']) > 10000) {
                return self::row_error($number, __('Description must be at most 10,000 bytes.', 'simple-tags'));
            }
            $canonical = sanitize_title($row['slug']);
            if ('' === $canonical || $canonical !== $row['slug'] || '0' === $canonical) {
                return self::row_error($number, __('Slug must already be a canonical WordPress slug and cannot be "0".', 'simple-tags'));
            }
            if (isset($seen[ $row['slug'] ])) {
                /* translators: %d: Earlier CSV record number. */
                return self::row_error($number, sprintf(__('Duplicate slug; first used in CSV record %d.', 'simple-tags'), $seen[ $row['slug'] ]));
            }
            $seen[ $row['slug'] ] = $number;
        }
        return self::assignment_plan($rows, false, $skip_missing_posts);
    }

    /**
     * Preflight every assignment without writes, caching repeated post lookups.
     *
     * @param array $rows Tag records; omitted post_ids means legacy tags-only import.
     * @param bool  $check_permissions Require assign_terms and edit_post for every ID.
     * @param bool  $skip_missing_posts Skip only missing or taxonomy-incompatible targets.
     * @return true|WP_Error
     */
    public static function validate_assignments($rows, $check_permissions = false, $skip_missing_posts = false)
    {
        $plan = self::assignment_plan($rows, $check_permissions, $skip_missing_posts);
        return is_wp_error($plan) ? $plan : true;
    }

    /**
     * Snapshot skipped targets before writes so recreated IDs are not attached.
     *
     * @param array $rows Tag records to preflight.
     * @param bool  $check_permissions Check assignment permissions for usable posts.
     * @param bool  $skip_missing_posts Allow missing or unsupported targets.
     * @return array|WP_Error Map of skipped IDs to true; syntax/auth failures are never skipped.
     */
    private static function assignment_plan($rows, $check_permissions, $skip_missing_posts)
    {
        if (! is_array($rows) || count($rows) > self::MAX_ROWS) {
            return new WP_Error('taxopress_tag_csv_rows', __('Import requires an array of no more than 5,000 tags.', 'simple-tags'));
        }
        $taxonomy = get_taxonomy('post_tag');
        if (! $taxonomy) {
            return new WP_Error('taxopress_tag_csv_taxonomy', __('The standard post_tag taxonomy is unavailable.', 'simple-tags'));
        }
        $posts       = array();
        $skipped_ids = array();
        $total       = 0;
        $number      = 1;
        foreach ($rows as $row) {
            ++$number;
            $ids = self::assignment_ids($row, $number);
            if (is_wp_error($ids)) {
                return $ids;
            }
            if (! empty($ids) && $check_permissions && ! current_user_can($taxonomy->cap->assign_terms)) {
                return self::row_error($number, __('You do not have permission to assign standard post tags. No tags or assignments were written.', 'simple-tags'));
            }
            $total += count($ids);
            if ($total > self::MAX_ASSOCIATIONS) {
                return self::row_error($number, __('The file exceeds the 25,000 tag-to-post assignment import limit.', 'simple-tags'));
            }
            foreach ($ids as $id) {
                if (! isset($posts[ $id ])) {
                    $post = get_post($id);
                    if (! $post || ! is_object_in_taxonomy($post->post_type, 'post_tag')) {
                        if ($skip_missing_posts) {
                            $skipped_ids[ $id ] = true;
                            $posts[ $id ]       = true;
                            continue;
                        }
                        /* translators: %d: Destination post ID. */
                        return self::row_error($number, sprintf(__('Post ID %d is missing or its post type does not support post_tag.', 'simple-tags'), $id));
                    }
                    if ($check_permissions && ! current_user_can('edit_post', $id)) {
                        /* translators: %d: Destination post ID. */
                        return self::row_error($number, sprintf(__('You do not have permission to edit post ID %d. No tags or assignments were written.', 'simple-tags'), $id));
                    }
                    $posts[ $id ] = true;
                }
            }
        }
        return $skipped_ids;
    }

    /**
     * Decode bounded, canonical positive decimal IDs without overflow casts.
     *
     * @param array $row Tag record with optional string post_ids.
     * @param int   $number CSV record number.
     * @return array|WP_Error Integer IDs, never names or floats.
     */
    private static function assignment_ids($row, $number)
    {
        if (! is_array($row) || ( array_key_exists('post_ids', $row) && ! is_string($row['post_ids']) )) {
            return self::row_error($number, __('post_ids must be a pipe-delimited string of positive integer IDs.', 'simple-tags'));
        }
        if (! isset($row['post_ids']) || '' === $row['post_ids']) {
            return array();
        }
        // Bound direct callers as well as CSV uploads before allocating the ID list.
        if (strlen($row['post_ids']) > self::MAX_ASSOCIATIONS * ( strlen((string) PHP_INT_MAX) + 1 )) {
            return self::row_error($number, __('Too many or oversized post IDs.', 'simple-tags'));
        }
        $values = explode('|', $row['post_ids'], self::MAX_ASSOCIATIONS + 1);
        if (count($values) > self::MAX_ASSOCIATIONS) {
            return self::row_error($number, __('The file exceeds the 25,000 tag-to-post assignment import limit.', 'simple-tags'));
        }
        $ids  = array();
        $seen = array();
        $max  = (string) PHP_INT_MAX;
        foreach ($values as $value) {
            if (1 !== preg_match('/^[1-9][0-9]*\z/', $value) || strlen($value) > strlen($max) || ( strlen($value) === strlen($max) && strcmp($value, $max) > 0 )) {
                return self::row_error($number, __('Post IDs must be canonical positive decimal integers within the PHP integer range (no spaces, signs, leading zeros, or empty IDs).', 'simple-tags'));
            }
            if (isset($seen[ $value ])) {
                return self::row_error($number, __('Duplicate post ID within this tag record.', 'simple-tags'));
            }
            $seen[ $value ] = true;
            $ids[]          = (int) $value;
        }
        return $ids;
    }

    /**
     * Import preflighted rows. Caller owns authorization; no HTTP/output side effects.
     *
     * @param array $rows Rows returned by parse_csv(), or equivalent associative rows.
     * @param bool  $update_existing Update name/description for matching slugs.
     * @param bool  $skip_missing_posts Skip only missing or unsupported assignment targets.
     * @return array Counts created, updated, skipped, errors; error_messages contains details.
     */
    public static function import_rows($rows, $update_existing = false, $skip_missing_posts = false)
    {
        $result = array(
            'created'                 => 0,
            'updated'                 => 0,
            'skipped'                 => 0,
            'errors'                  => 0,
            'relationships_added'     => 0,
            'relationships_unchanged' => 0,
            'relationships_skipped'   => 0,
            'skipped_post_ids'        => array(),
            'error_messages'          => array(),
        );
        $valid  = self::validate_rows($rows, $skip_missing_posts);
        if (! taxonomy_exists('post_tag')) {
            $valid = new WP_Error('taxopress_tag_csv_taxonomy', __('The standard post_tag taxonomy is unavailable.', 'simple-tags'));
        }
        if (is_wp_error($valid)) {
            $result['errors']         = 1;
            $result['error_messages'] = $valid->get_error_messages();
            return $result;
        }
        $number = 1;
        foreach ($rows as $row) {
            ++$number;
            // Count planned skips even if this tag's later write fails.
            foreach (self::assignment_ids($row, $number) as $id) {
                if (isset($valid[ $id ])) {
                    self::record_skipped_post($id, $result);
                }
            }
            $existing = get_term_by('slug', $row['slug'], 'post_tag', OBJECT, 'raw');
            if ($existing && ! $update_existing) {
                ++$result['skipped'];
                self::attach_posts($row, (int) $existing->term_id, $number, $result, $skip_missing_posts, $valid);
                continue;
            }
            // Core term APIs expect slashed name/description; CSV values stay raw in memory.
            $args = wp_slash(
                array(
                    'name'        => $row['name'],
                    'description' => $row['description'],
                    'slug'        => $row['slug'],
                )
            );
            if ($existing) {
                $written = wp_update_term($existing->term_id, 'post_tag', $args);
            } else {
                $written = wp_insert_term($args['name'], 'post_tag', $args);
            }
            if (is_wp_error($written)) {
                ++$result['errors'];
                $result['error_messages'][] = self::row_error($number, $written->get_error_message())->get_error_message();
            } else {
                ++$result[ $existing ? 'updated' : 'created' ];
                self::attach_posts($row, (int) $written['term_id'], $number, $result, $skip_missing_posts, $valid);
            }
        }
        $result['skipped_post_ids'] = array_values($result['skipped_post_ids']);
        return $result;
    }

    /**
     * Count skipped relationships while retaining unique numeric post IDs.
     *
     * @param int   $id Skipped target post ID.
     * @param array $result Import counts and unique IDs, updated by reference.
     */
    private static function record_skipped_post($id, &$result)
    {
        ++$result['relationships_skipped'];
        $result['skipped_post_ids'][ $id ] = $id;
    }

    /**
     * Add assignments without replacing other tags; count repeat imports unchanged.
     *
     * @param array $row Preflighted tag record.
     * @param int   $term_id Destination integer term ID.
     * @param int   $number CSV record number.
     * @param array $result Counts and messages, updated by reference.
     * @param bool  $skip_missing_posts Skip targets lost at runtime, not other failures.
     * @param array $skipped_ids Targets excluded during preflight.
     */
    private static function attach_posts($row, $term_id, $number, &$result, $skip_missing_posts, $skipped_ids)
    {
        $ids = self::assignment_ids($row, $number);
        foreach ($ids as $id) {
            if (isset($skipped_ids[ $id ])) {
                continue;
            }
            // Recheck after tag writes in case hooks or another request removed a post.
            $post  = get_post($id);
            $error = null;
            if (! $post || ! is_object_in_taxonomy($post->post_type, 'post_tag')) {
                if ($skip_missing_posts) {
                    self::record_skipped_post($id, $result);
                    continue;
                }
                $error = __('Post disappeared or no longer supports post_tag.', 'simple-tags');
            } else {
                $has_tag = is_object_in_term($id, 'post_tag', $term_id);
                if (is_wp_error($has_tag)) {
                    $error = $has_tag->get_error_message();
                } elseif ($has_tag) {
                    ++$result['relationships_unchanged'];
                    continue;
                } else {
                    $written = wp_set_object_terms($id, array( $term_id ), 'post_tag', true);
                    if (is_wp_error($written)) {
                        $error = $written->get_error_message();
                    } else {
                        $verified = is_object_in_term($id, 'post_tag', $term_id);
                        if (is_wp_error($verified) || ! $verified) {
                            $error = __('The assignment could not be verified after saving.', 'simple-tags');
                        } else {
                            ++$result['relationships_added'];
                        }
                    }
                }
            }
            if (null !== $error) {
                ++$result['errors'];
                /* translators: 1: Post ID, 2: Runtime assignment failure. */
                $result['error_messages'][] = self::row_error($number, sprintf(__('Assignment to post ID %1$d failed: %2$s', 'simple-tags'), $id, $error))->get_error_message();
            }
        }
    }

    /**
     * Write all current post_tag terms, including unused tags, in paginated batches.
     * Does not close the caller's stream or emit HTTP headers. Export has no import cap.
     *
     * @param resource $stream Writable destination positioned where CSV should begin.
     * @param bool     $check_permissions Require read_post for every exported post ID.
     * @return int|WP_Error Number of exported tags or an error (stream may be partial).
     */
    public static function write_csv($stream, $check_permissions = false)
    {
        if (! is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            return new WP_Error('taxopress_tag_csv_stream', __('A writable CSV stream is required.', 'simple-tags'));
        }
        if (! taxonomy_exists('post_tag')) {
            return new WP_Error('taxopress_tag_csv_taxonomy', __('The standard post_tag taxonomy is unavailable.', 'simple-tags'));
        }
        if (! self::write_bytes($stream, "\xEF\xBB\xBFslug,name,description,post_ids\r\n")) {
            return new WP_Error('taxopress_tag_csv_write', __('The CSV output could not be written.', 'simple-tags'));
        }
        $count    = 0;
        $readable = array();
        do {
            $terms = get_terms(
                array(
                    'taxonomy'               => 'post_tag',
                    'hide_empty'             => false,
                    'orderby'                => 'term_id',
                    'order'                  => 'ASC',
                    'number'                 => self::BATCH_SIZE,
                    'offset'                 => $count,
                    'update_term_meta_cache' => false,
                )
            );
            if (is_wp_error($terms)) {
                return $terms;
            }
            $batch_count = count($terms);
            foreach ($terms as $term) {
                $term = get_term($term, 'post_tag', OBJECT, 'raw');
                if (is_wp_error($term) || ! $term) {
                    return new WP_Error('taxopress_tag_csv_term', __('A tag could not be read during export. Please try again.', 'simple-tags'));
                }
                $objects = get_objects_in_term((int) $term->term_id, 'post_tag', array( 'order' => 'ASC' ));
                if (is_wp_error($objects)) {
                    return $objects;
                }
                $post_ids = array();
                foreach ($objects as $object_id) {
                    $post = get_post($object_id);
                    if ($post && is_object_in_taxonomy($post->post_type, 'post_tag')) {
                        if ($check_permissions && ! isset($readable[ $post->ID ])) {
                            if (! current_user_can('read_post', (int) $post->ID)) {
                                return new WP_Error('taxopress_tag_csv_read_permission', __('You cannot read every associated post. Export was cancelled without downloading a partial file.', 'simple-tags'));
                            }
                            $readable[ $post->ID ] = true;
                        }
                        $post_ids[] = (string) $post->ID;
                    }
                }
                $cells = array();
                foreach (array( $term->slug, $term->name, $term->description, implode('|', $post_ids) ) as $value) {
                    $cells[] = '"' . str_replace('"', '""', self::escape_cell($value)) . '"';
                }
                // Explicit RFC quoting avoids PHP 7.2 fputcsv() backslash-escape ambiguity.
                if (! self::write_bytes($stream, implode(',', $cells) . "\r\n")) {
                    return new WP_Error('taxopress_tag_csv_write', __('The CSV output could not be written.', 'simple-tags'));
                }
                ++$count;
            }
        } while (self::BATCH_SIZE === $batch_count);
        return $count;
    }

    /**
     * Handle partial stream writes without losing bytes.
     *
     * @param resource $stream Writable stream.
     * @param string   $bytes Bytes to write.
     * @return bool
     */
    private static function write_bytes($stream, $bytes)
    {
        $length = strlen($bytes);
        $offset = 0;
        while ($offset < $length) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- Caller-owned memory/temp streams, not filesystem paths or whole-file replacement.
            $written = fwrite($stream, substr($bytes, $offset));
            if (false === $written || 0 === $written) {
                return false;
            }
            $offset += $written;
        }
        return true;
    }

    /**
     * Detect precisely the prefixes protected by export.
     *
     * @param string $value Raw cell.
     * @return bool
     */
    private static function dangerous_cell($value)
    {
        return 1 === preg_match("/^(?:'|[\s\p{Z}]*[=+@-])/u", $value);
    }

    /**
     * Add one reversible apostrophe when required.
     *
     * @param string $value Raw cell.
     * @return string
     */
    private static function escape_cell($value)
    {
        return self::dangerous_cell($value) ? "'" . $value : $value;
    }

    /**
     * Remove exactly one export escape, retaining ordinary apostrophe words.
     *
     * @param string $value Encoded cell.
     * @return string
     */
    private static function unescape_cell($value)
    {
        if (isset($value[0]) && "'" === $value[0] && self::dangerous_cell(substr($value, 1))) {
            return substr($value, 1);
        }
        return $value;
    }

    /**
     * Build an error with a logical CSV record number.
     *
     * @param int    $number CSV record number including the header.
     * @param string $message Explanation.
     * @return WP_Error
     */
    private static function row_error($number, $message)
    {
        /* translators: 1: CSV record number (not physical line), 2: Error explanation. */
        return new WP_Error('taxopress_tag_csv_csv_row', sprintf(__('CSV record %1$d: %2$s', 'simple-tags'), $number, $message), array( 'row' => $number ));
    }
}
