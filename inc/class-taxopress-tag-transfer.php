<?php

/**
 * Manage Terms import/export screen and authenticated endpoints.
 *
 * @package TaxoPress
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Native temporary/upload streams need resource handles, not credential-based WP_Filesystem file management.
defined('ABSPATH') || exit;

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
     * Check the current taxonomy, post type, and capabilities.
     *
     * @param string $taxonomy Taxonomy name.
     * @param string $post_type Post type name.
     * @param bool   $import Require edit_terms as well as manage_terms.
     * @return bool
     */
    private static function allowed($taxonomy = 'post_tag', $post_type = 'post', $import = false)
    {
        $taxonomy_object = get_taxonomy($taxonomy);
        return self::enabled()
            && current_user_can('simple_tags')
            && $taxonomy_object
            && post_type_exists($post_type)
            && is_object_in_taxonomy($post_type, $taxonomy)
            && current_user_can($taxonomy_object->cap->manage_terms)
            && ( ! $import || current_user_can($taxonomy_object->cap->edit_terms) );
    }

    /**
     * Require permission and POST before an endpoint checks its nonce.
     *
     * @param string $taxonomy Taxonomy name.
     * @param string $post_type Post type name.
     * @param bool   $import Whether this is a write endpoint.
     */
    private static function authorize($taxonomy, $post_type, $import = false)
    {
        if (! self::allowed($taxonomy, $post_type, $import)) {
            wp_die(esc_html__('You do not have permission to transfer terms for this taxonomy and post type.', 'simple-tags'), '', array( 'response' => 403 ));
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
    private static function page_url($taxonomy = 'post_tag', $post_type = 'post')
    {
        return add_query_arg(
            array(
                'page'                    => 'st_manage',
                'tab'                     => 'import-export-terms',
                'import-export-terms_taxo' => $taxonomy,
                'import-export-terms_cpt'  => $post_type,
            ),
            admin_url('admin.php')
        );
    }

    /**
     * Read the selected transfer context from a submitted form.
     *
     * @return array Taxonomy and post type.
     */
    private static function request_context()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sanitized context is needed for capability checks before endpoint nonce verification.
        $taxonomy  = isset($_POST['taxopress_terms_taxonomy']) && is_string($_POST['taxopress_terms_taxonomy']) ? sanitize_key(wp_unslash($_POST['taxopress_terms_taxonomy'])) : 'post_tag';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sanitized context is needed for capability checks before endpoint nonce verification.
        $post_type = isset($_POST['taxopress_terms_post_type']) && is_string($_POST['taxopress_terms_post_type']) ? sanitize_key(wp_unslash($_POST['taxopress_terms_post_type'])) : 'post';
        return array( $taxonomy, $post_type );
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
     * @param array  $result Counts and error messages.
     * @param string $taxonomy Taxonomy name.
     * @param string $post_type Post type name.
     */
    private static function finish($result, $taxonomy = 'post_tag', $post_type = 'post')
    {
        // Keep summaries bounded; detailed failures never enter URLs or global notices.
        if (isset($result['error_messages'])) {
            $result['error_messages'] = array_slice($result['error_messages'], 0, 100);
        }
        set_transient(self::result_key(), $result, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(self::page_url($taxonomy, $post_type), 303);
        exit;
    }

    /**
     * Redirect a failure to inline results.
     *
     * @param string $message Failure explanation.
     * @param string $taxonomy Taxonomy name.
     * @param string $post_type Post type name.
     * @param string $operation Transfer operation.
     */
    private static function fail($message, $taxonomy = 'post_tag', $post_type = 'post', $operation = 'import')
    {
        self::finish(
            array(
                'errors'         => 1,
                'error_messages' => array( $message ),
                'operation'      => $operation,
            ),
            $taxonomy,
            $post_type
        );
    }

    /** Send a complete CSV attachment after authorization and staging. */
    public static function export()
    {
        list($taxonomy, $post_type) = self::request_context();
        self::authorize($taxonomy, $post_type);
        check_admin_referer('taxopress_export_tags', 'taxopress_tags_nonce');
        // Stage on disk before headers: a failed export never downloads partial CSV/HTML.
        $stream = tmpfile();
        if (false === $stream) {
            self::fail(__('A temporary export file could not be created.', 'simple-tags'), $taxonomy, $post_type, 'export');
        }
        $written = TaxoPress_Tag_CSV_Service::write_csv($stream, true, $taxonomy, $post_type);
        if (is_wp_error($written)) {
            fclose($stream);
            self::fail($written->get_error_message(), $taxonomy, $post_type, 'export');
        }
        if (! rewind($stream)) {
            fclose($stream);
            self::fail(__('The export file could not be rewound.', 'simple-tags'), $taxonomy, $post_type, 'export');
        }
        while (ob_get_level() > 0) {
            $level = ob_get_level();
            ob_end_clean();
            if (ob_get_level() === $level) {
                fclose($stream);
                self::fail(__('An output buffer prevented a clean CSV download.', 'simple-tags'), $taxonomy, $post_type, 'export');
            }
        }
        if (headers_sent()) {
            fclose($stream);
            self::fail(__('Output was sent before the download. Check other plugins and retry.', 'simple-tags'), $taxonomy, $post_type, 'export');
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name('publishpress-taxonomies-' . $taxonomy . '-' . $post_type . '.csv') . '"');
        header('X-Content-Type-Options: nosniff');
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV attachment, not HTML; cells are formula-escaped and CSV-quoted.
        fpassthru($stream);
        fclose($stream);
        exit;
    }

    /** Validate a genuine CSV upload, import it, and redirect to inline results. */
    public static function import()
    {
        list($taxonomy, $post_type) = self::request_context();
        self::authorize($taxonomy, $post_type, true);
        check_admin_referer('taxopress_import_tags', 'taxopress_tags_nonce');
        // Upload paths are server-created; never sanitize/transform the temporary path.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shape and trusted upload provenance checked below before opening.
        $file = isset($_FILES['taxopress_tags_csv']) ? $_FILES['taxopress_tags_csv'] : null;
        if (! is_array($file) || ! isset($file['error'], $file['name'], $file['tmp_name']) || ! is_int($file['error']) || UPLOAD_ERR_OK !== $file['error'] || ! is_string($file['name']) || ! is_string($file['tmp_name'])) {
            self::fail(__('Upload failed. Choose a CSV file and check the server upload limit.', 'simple-tags'), $taxonomy, $post_type);
        }
        if ('csv' !== strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION)) || ! is_uploaded_file($file['tmp_name'])) {
            self::fail(__('Choose a genuine uploaded file with a .csv extension.', 'simple-tags'), $taxonomy, $post_type);
        }
        $size = filesize($file['tmp_name']);
        if (false === $size || $size > TaxoPress_Tag_CSV_Service::MAX_BYTES) {
            self::fail(__('The CSV file could not be read or exceeds the 5 MiB import limit.', 'simple-tags'), $taxonomy, $post_type);
        }
        $stream = fopen($file['tmp_name'], 'rb');
        if (false === $stream) {
            self::fail(__('The uploaded CSV file could not be opened.', 'simple-tags'), $taxonomy, $post_type);
        }
        $skip_missing = isset($_POST['taxopress_tags_skip_missing_posts']) && is_string($_POST['taxopress_tags_skip_missing_posts']) && '1' === sanitize_text_field(wp_unslash($_POST['taxopress_tags_skip_missing_posts']));
        $rows         = TaxoPress_Tag_CSV_Service::parse_csv($stream, $skip_missing, $taxonomy, $post_type);
        fclose($stream);
        if (is_wp_error($rows)) {
            self::fail($rows->get_error_message(), $taxonomy, $post_type);
        }
        $assignments = TaxoPress_Tag_CSV_Service::validate_assignments($rows, true, $skip_missing, $taxonomy, $post_type);
        if (is_wp_error($assignments)) {
            self::fail($assignments->get_error_message(), $taxonomy, $post_type);
        }
        $update = isset($_POST['taxopress_tags_update_existing']) && is_string($_POST['taxopress_tags_update_existing']) && '1' === sanitize_text_field(wp_unslash($_POST['taxopress_tags_update_existing']));
        self::finish(TaxoPress_Tag_CSV_Service::import_rows($rows, $update, $skip_missing, $taxonomy, $post_type), $taxonomy, $post_type);
    }

    /**
     * Render and consume transfer feedback with the page-level admin notices.
     *
     * @param bool $show_results Whether to display and consume the summary.
     */
    public static function render_notice($show_results = true)
    {
        if (! $show_results) {
            return;
        }
        $result = get_transient(self::result_key());
        if (! is_array($result)) {
            return;
        }
        delete_transient(self::result_key());
        $has_errors  = ! empty($result['errors']);
        $has_skipped = ! empty($result['relationships_skipped']);
        $notice_type = $has_errors ? 'notice-error' : ( $has_skipped ? 'notice-warning' : 'notice-success' );
        ?>
        <div id="taxopress-tags-results" class="notice <?php echo esc_attr($notice_type); ?> is-dismissible taxopress-notice" role="status">
            <p><strong>
                <?php
                if ($has_errors && isset($result['operation']) && 'export' === $result['operation']) {
                    esc_html_e('The term export failed.', 'simple-tags');
                } elseif ($has_errors && ! isset($result['created'])) {
                    esc_html_e('The term import failed. No changes were made.', 'simple-tags');
                } elseif ($has_errors) {
                    esc_html_e('The term import finished with errors. Some changes may have been applied.', 'simple-tags');
                } elseif ($has_skipped) {
                    esc_html_e('The terms were imported, with some content assignments skipped.', 'simple-tags');
                } else {
                    esc_html_e('The terms were imported successfully.', 'simple-tags');
                }
                ?>
            </strong></p>
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
                /* translators: 1: Added assignments, 2: Unchanged assignments. */
                echo esc_html(sprintf(__('Assignments added: %1$d. Assignments unchanged: %2$d.', 'simple-tags'), $result['relationships_added'], $result['relationships_unchanged']));
                ?>
            </p>
            <?php endif; ?>
            <?php if (! empty($result['relationships_skipped']) && isset($result['skipped_post_ids'])) : ?>
            <p>
                <?php
                /* translators: 1: Skipped assignment count, 2: Missing or incompatible content item count. */
                echo esc_html(sprintf(__('Content assignments skipped: %1$d because %2$d content items were missing or incompatible.', 'simple-tags'), $result['relationships_skipped'], count($result['skipped_post_ids'])));
                ?>
            </p>
            <?php endif; ?>
            <?php if (! empty($result['error_messages'])) : ?>
            <p><?php esc_html_e('Error details (up to the first 100):', 'simple-tags'); ?></p>
            <ul><?php foreach ($result['error_messages'] as $message) : ?>
                <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render forms and consume results only when this tab is active.
     *
     * @param bool   $show_results Whether to display and consume the inline summary.
     * @param string $taxonomy Taxonomy name.
     * @param string $post_type Post type name.
     */
    public static function render($show_results = true, $taxonomy = 'post_tag', $post_type = 'post')
    {
        $taxonomy_object = get_taxonomy($taxonomy);
        $post_type_object = get_post_type_object($post_type);
        if (! self::allowed($taxonomy, $post_type) || ! $taxonomy_object || ! $post_type_object) {
            echo '<div class="notice notice-error inline taxopress-notice"><p>' . esc_html__('You do not have permission to transfer terms for this taxonomy and post type.', 'simple-tags') . '</p></div>';
            return;
        }
        $taxonomy_label = $taxonomy_object->labels->name;
        $post_type_label = $post_type_object->labels->name;
        if ($show_results) {
            self::render_notice();
        }
        ?>
            <h2><?php esc_html_e('Export Terms', 'simple-tags'); ?></h2>
            <p>
                <?php
                /* translators: 1: Taxonomy label, 2: Post type label. */
                echo esc_html(sprintf(__('Export all %1$s terms and their %2$s assignments to a CSV file. Unused terms and term hierarchy are included.', 'simple-tags'), $taxonomy_label, $post_type_label));
                ?>
            </p>
            <fieldset>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="taxopress_export_tags">
                    <input type="hidden" name="taxopress_terms_taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
                    <input type="hidden" name="taxopress_terms_post_type" value="<?php echo esc_attr($post_type); ?>">
                    <?php wp_nonce_field('taxopress_export_tags', 'taxopress_tags_nonce'); ?>
                    <input class="button-secondary" type="submit" value="<?php echo esc_attr__('Export Terms', 'simple-tags'); ?>">
                </form>
            </fieldset>

            <h2><?php esc_html_e('Import Terms', 'simple-tags'); ?></h2>
            <p>
                <?php
                /* translators: %s: Taxonomy label. */
                echo esc_html(sprintf(__('Import %s terms from a CSV file created by this tool. Existing terms are matched by slug, and assignments are added without removing other terms.', 'simple-tags'), $taxonomy_label));
                ?>
            </p>
            <p><strong><?php esc_html_e('Back up your site first. Content IDs must identify the same content on both sites. There is no automatic rollback.', 'simple-tags'); ?></strong></p>
            <?php if (self::allowed($taxonomy, $post_type, true)) : ?>
            <fieldset>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="taxopress_import_tags">
                    <input type="hidden" name="taxopress_terms_taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
                    <input type="hidden" name="taxopress_terms_post_type" value="<?php echo esc_attr($post_type); ?>">
                    <?php wp_nonce_field('taxopress_import_tags', 'taxopress_tags_nonce'); ?>
                    <p><label for="taxopress-tags-csv"><?php esc_html_e('CSV file:', 'simple-tags'); ?></label><br>
                    <input type="file" id="taxopress-tags-csv" name="taxopress_tags_csv" accept=".csv,text/csv" required></p>
                    <p><label><input type="checkbox" name="taxopress_tags_update_existing" value="1" checked="checked"> <?php esc_html_e('Update the name, description, and parent of existing terms.', 'simple-tags'); ?></label></p>
                    <p><label><input type="checkbox" name="taxopress_tags_skip_missing_posts" value="1" checked="checked"> <?php esc_html_e('Skip missing or incompatible content assignments.', 'simple-tags'); ?></label></p>
                    <p class="description"><?php esc_html_e('Maximum file size: 5 MiB, 5,000 terms, and 25,000 content assignments.', 'simple-tags'); ?></p>
                    <input class="button-primary" type="submit" value="<?php echo esc_attr__('Import Terms', 'simple-tags'); ?>">
                </form>
            </fieldset>
            <?php else : ?>
            <div class="notice notice-warning inline taxopress-notice"><p><?php esc_html_e('You can export these terms, but you do not have permission to import them.', 'simple-tags'); ?></p></div>
            <?php endif; ?>
        <?php
    }
}
