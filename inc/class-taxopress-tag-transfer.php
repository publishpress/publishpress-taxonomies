<?php

/**
 * Manage Terms tag transfer screen and authenticated endpoints.
 *
 * @package TaxoPress
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Native temporary/upload streams need resource handles, not credential-based WP_Filesystem file management.
if (! defined('ABSPATH')) {
    exit;
}

/** HTTP authorization, temporary results, and the Manage Terms tab. */
class TaxoPress_Tag_Transfer
{
    /** Register endpoints only when the Manage Terms feature is enabled. */
    public static function register()
    {
        if (! self::enabled()) {
            return;
        }
        add_action('admin_post_taxopress_export_tags', array( __CLASS__, 'export' ));
        add_action('admin_post_taxopress_import_tags', array( __CLASS__, 'import' ));
    }

    /**
     * Check the real feature setting, never the dashboard preview condition.
     *
     * @return bool
     */
    private static function enabled()
    {
        return 1 === (int) SimpleTags_Plugin::get_option_value('active_manage');
    }

    /**
     * Check the current taxonomy's capabilities.
     *
     * @param bool $import Require edit_terms as well as manage_terms.
     * @return bool
     */
    private static function allowed($import = false)
    {
        $taxonomy = get_taxonomy('post_tag');
        return self::enabled() && current_user_can('simple_tags') && $taxonomy && current_user_can($taxonomy->cap->manage_terms) && ( ! $import || current_user_can($taxonomy->cap->edit_terms) );
    }

    /**
     * Require permission and POST before an endpoint checks its nonce.
     *
     * @param bool $import Whether this is a write endpoint.
     */
    private static function authorize($import = false)
    {
        if (! self::allowed($import)) {
            wp_die(esc_html__('You do not have permission to transfer standard post tags.', 'simple-tags'), '', array( 'response' => 403 ));
        }
        if (! isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            wp_die(esc_html__('A POST request is required.', 'simple-tags'), '', array( 'response' => 405 ));
        }
    }

    /**
     * Return the tool URL.
     *
     * @return string
     */
    private static function page_url()
    {
        return admin_url('admin.php?page=st_manage&tab=import-export-tags');
    }

    /**
     * Return a site-scoped, user-specific transient key.
     *
     * @return string
     */
    private static function result_key()
    {
        return 'taxopress_tags_result_' . get_current_user_id();
    }

    /**
     * Store a short-lived summary and redirect without output.
     *
     * @param array $result Counts and error messages.
     */
    private static function finish($result)
    {
        // Keep summaries bounded; detailed failures never enter URLs or global notices.
        if (isset($result['error_messages'])) {
            $result['error_messages'] = array_slice($result['error_messages'], 0, 100);
        }
        set_transient(self::result_key(), $result, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(self::page_url(), 303);
        exit;
    }

    /**
     * Redirect a failure to inline results.
     *
     * @param string $message Failure explanation.
     */
    private static function fail($message)
    {
        self::finish(
            array(
                'errors'         => 1,
                'error_messages' => array( $message ),
            )
        );
    }

    /** Send a complete CSV attachment after authorization and staging. */
    public static function export()
    {
        self::authorize();
        check_admin_referer('taxopress_export_tags', 'taxopress_tags_nonce');
        // Stage on disk before headers: a failed export never downloads partial CSV/HTML.
        $stream = tmpfile();
        if (false === $stream) {
            self::fail(__('A temporary export file could not be created.', 'simple-tags'));
        }
        $written = TaxoPress_Tag_CSV_Service::write_csv($stream, true);
        if (is_wp_error($written)) {
            fclose($stream);
            self::fail($written->get_error_message());
        }
        if (! rewind($stream)) {
            fclose($stream);
            self::fail(__('The export file could not be rewound.', 'simple-tags'));
        }
        while (ob_get_level() > 0) {
            $level = ob_get_level();
            ob_end_clean();
            if (ob_get_level() === $level) {
                fclose($stream);
                self::fail(__('An output buffer prevented a clean CSV download.', 'simple-tags'));
            }
        }
        if (headers_sent()) {
            fclose($stream);
            self::fail(__('Output was sent before the download. Check other plugins and retry.', 'simple-tags'));
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="post-tags.csv"');
        header('X-Content-Type-Options: nosniff');
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV attachment, not HTML; cells are formula-escaped and CSV-quoted.
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    /** Validate a genuine CSV upload, import it, and redirect to inline results. */
    public static function import()
    {
        self::authorize(true);
        check_admin_referer('taxopress_import_tags', 'taxopress_tags_nonce');
        // Upload paths are server-created; never sanitize/transform the temporary path.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape and trusted upload provenance checked below before opening.
        $file = isset($_FILES['taxopress_tags_csv']) ? $_FILES['taxopress_tags_csv'] : null;
        if (! is_array($file) || ! isset($file['error'], $file['name'], $file['tmp_name']) || ! is_int($file['error']) || UPLOAD_ERR_OK !== $file['error'] || ! is_string($file['name']) || ! is_string($file['tmp_name'])) {
            self::fail(__('Upload failed. Choose a CSV file and check the server upload limit.', 'simple-tags'));
        }
        if ('csv' !== strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION)) || ! is_uploaded_file($file['tmp_name'])) {
            self::fail(__('Choose a genuine uploaded file with a .csv extension.', 'simple-tags'));
        }
        $size = filesize($file['tmp_name']);
        if (false === $size || $size > TaxoPress_Tag_CSV_Service::MAX_BYTES) {
            self::fail(__('The CSV file could not be read or exceeds the 5 MiB import limit.', 'simple-tags'));
        }
        $stream = fopen($file['tmp_name'], 'rb');
        if (false === $stream) {
            self::fail(__('The uploaded CSV file could not be opened.', 'simple-tags'));
        }
        $skip_missing = isset($_POST['taxopress_tags_skip_missing_posts']) && is_string($_POST['taxopress_tags_skip_missing_posts']) && '1' === sanitize_text_field(wp_unslash($_POST['taxopress_tags_skip_missing_posts']));
        $rows         = TaxoPress_Tag_CSV_Service::parse_csv($stream, $skip_missing);
        fclose($stream);
        if (is_wp_error($rows)) {
            self::fail($rows->get_error_message());
        }
        $assignments = TaxoPress_Tag_CSV_Service::validate_assignments($rows, true, $skip_missing);
        if (is_wp_error($assignments)) {
            self::fail($assignments->get_error_message());
        }
        $update = isset($_POST['taxopress_tags_update_existing']) && is_string($_POST['taxopress_tags_update_existing']) && '1' === sanitize_text_field(wp_unslash($_POST['taxopress_tags_update_existing']));
        self::finish(TaxoPress_Tag_CSV_Service::import_rows($rows, $update, $skip_missing));
    }

    /**
     * Render forms and consume results only when this tab is active.
     *
     * @param bool $show_results Whether to display and consume the inline summary.
     */
    public static function render($show_results = true)
    {
        if (! self::allowed()) {
            echo '<p>' . esc_html__('You do not have permission to transfer standard post tags.', 'simple-tags') . '</p>';
            return;
        }
        $result = false;
        if ($show_results) {
            $result = get_transient(self::result_key());
            delete_transient(self::result_key());
        }
        ?>
            <p><?php esc_html_e('Transfer standard tags (post_tag) and their post associations only, including unused tags. No categories, product tags, Tag Groups, metadata, or post content; no posts are created.', 'simple-tags'); ?></p>
            <p><strong><?php esc_html_e('Back up first. Post IDs must identify the same intended posts on both sites. Associations are additive; unrelated tags remain. There is no automatic rollback.', 'simple-tags'); ?></strong></p>
            <?php if (is_array($result)) : ?>
            <section aria-labelledby="taxopress-tags-results">
                <h2 id="taxopress-tags-results"><?php esc_html_e('Transfer results', 'simple-tags'); ?></h2>
                <?php if (isset($result['created'], $result['updated'], $result['skipped'], $result['errors'])) : ?>
                <p>
                    <?php
                    /* translators: 1: Created count, 2: Updated count, 3: Skipped count, 4: Error count. */
                    echo esc_html(sprintf(__('Created: %1$d. Updated: %2$d. Skipped: %3$d. Errors: %4$d.', 'simple-tags'), $result['created'], $result['updated'], $result['skipped'], $result['errors']));
                    ?>
                </p>
                <?php endif; ?>
                <?php if (isset($result['relationships_added'], $result['relationships_unchanged'])) : ?>
                <p>
                    <?php
                    /* translators: 1: Added relationships, 2: Unchanged relationships. */
                    echo esc_html(sprintf(__('Relationships added: %1$d. Relationships unchanged: %2$d.', 'simple-tags'), $result['relationships_added'], $result['relationships_unchanged']));
                    ?>
                </p>
                <?php endif; ?>
                <?php if (isset($result['relationships_skipped'], $result['skipped_post_ids'])) : ?>
                <p>
                    <?php
                    /* translators: 1: Skipped relationship count, 2: Unique skipped post ID count. */
                    echo esc_html(sprintf(__('Relationships skipped (missing or unsupported posts): %1$d. Unique skipped post IDs: %2$d.', 'simple-tags'), $result['relationships_skipped'], count($result['skipped_post_ids'])));
                    ?>
                </p>
                    <?php if (! empty($result['skipped_post_ids'])) : ?>
                <p>
                        <?php
                        /* translators: %s: Pipe-delimited unique skipped post IDs. */
                        echo esc_html(sprintf(__('Skipped post IDs: %s', 'simple-tags'), implode('|', $result['skipped_post_ids'])));
                        ?>
                </p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (! empty($result['error_messages'])) : ?>
                <p><?php esc_html_e('Error details (up to the first 100). Validation errors cause no writes; runtime failures may leave a partial import.', 'simple-tags'); ?></p>
                <ul><?php foreach ($result['error_messages'] as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                    <?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <h2><?php esc_html_e('Export Tags', 'simple-tags'); ?></h2>
            <p><?php esc_html_e('Download all tags as UTF-8 CSV: slug,name,description,post_ids. Export requires read access to every associated compatible post and has no size limit.', 'simple-tags'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="taxopress_export_tags">
                <?php wp_nonce_field('taxopress_export_tags', 'taxopress_tags_nonce'); ?>
                <?php submit_button(__('Download tags CSV', 'simple-tags'), 'secondary'); ?>
            </form>
            <h2><?php esc_html_e('Import Tags', 'simple-tags'); ?></h2>
            <p><?php esc_html_e('Use the exported CSV or legacy slug,name,description CSV for tags only. Post IDs use 12|34 format. Existing names/descriptions are skipped by default, but valid associations are still added.', 'simple-tags'); ?></p>
            <p><?php esc_html_e('Limits: 5 MiB, 5,000 tags, 25,000 associations (including skipped targets); name/slug 200 bytes each, description 10,000 bytes. Entire-file validation and permissions are checked before writes. Split larger exports with a CSV-aware tool.', 'simple-tags'); ?></p>
            <?php if (self::allowed(true)) : ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="taxopress_import_tags">
                <?php wp_nonce_field('taxopress_import_tags', 'taxopress_tags_nonce'); ?>
                <p><label for="taxopress-tags-csv"><?php esc_html_e('Tags CSV file', 'simple-tags'); ?></label><br>
                <input type="file" id="taxopress-tags-csv" name="taxopress_tags_csv" accept=".csv,text/csv" required></p>
                <p><label><input type="checkbox" name="taxopress_tags_update_existing" value="1"> <?php esc_html_e('Update existing tags by slug (name and description only).', 'simple-tags'); ?></label></p>
                <p><label><input type="checkbox" name="taxopress_tags_skip_missing_posts" value="1"> <?php esc_html_e('Skip missing posts or unsupported post types.', 'simple-tags'); ?></label></p>
                <p><?php esc_html_e('Unchecked means strict import. Skip mode never bypasses malformed IDs, limits, or permission denials.', 'simple-tags'); ?></p>
                <?php submit_button(__('Validate and import tags', 'simple-tags')); ?>
            </form>
            <?php else : ?>
            <p><?php esc_html_e('Import requires manage_terms and edit_terms permissions. Files with assignments additionally require assign_terms and edit_post for every listed post ID.', 'simple-tags'); ?></p>
            <?php endif; ?>
        <?php
    }
}
