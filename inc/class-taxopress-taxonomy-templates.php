<?php

defined('ABSPATH') || exit;

/** Ready-made taxonomy definitions without sample terms or external downloads. */
class TaxoPress_Taxonomy_Templates
{
    /** Return the bundled taxonomy templates. */
    public static function templates()
    {
        return [
            'people' => [
                'label' => __('People', 'simple-tags'),
                'singular_label' => __('Person', 'simple-tags'),
                'hierarchical' => 0,
            ],
            'organizations' => [
                'label' => __('Organizations', 'simple-tags'),
                'singular_label' => __('Organization', 'simple-tags'),
                'hierarchical' => 0,
            ],
            'locations' => [
                'label' => __('Geographic Locations', 'simple-tags'),
                'singular_label' => __('Geographic Location', 'simple-tags'),
                'hierarchical' => 1,
            ],
            'public_companies' => [
                'label' => __('Public Companies', 'simple-tags'),
                'singular_label' => __('Public Company', 'simple-tags'),
                'hierarchical' => 0,
            ],
            'subjects' => [
                'label' => __('Subjects', 'simple-tags'),
                'singular_label' => __('Subject', 'simple-tags'),
                'hierarchical' => 1,
            ],
            'events' => [
                'label' => __('Events', 'simple-tags'),
                'singular_label' => __('Event', 'simple-tags'),
                'hierarchical' => 0,
            ],
            'industries' => [
                'label' => __('Industries', 'simple-tags'),
                'singular_label' => __('Industry', 'simple-tags'),
                'hierarchical' => 1,
            ],
            'product_categories' => [
                'label' => __('Product Categories', 'simple-tags'),
                'singular_label' => __('Product Category', 'simple-tags'),
                'hierarchical' => 1,
            ],
            'content_categories' => [
                'label' => __('Content Categories', 'simple-tags'),
                'singular_label' => __('Content Category', 'simple-tags'),
                'hierarchical' => 1,
            ],
        ];
    }

    /** Include saved and deactivated definitions when checking for conflicts. */
    private static function exists($name)
    {
        return taxonomy_exists($name)
            || isset(taxopress_get_all_edited_taxonomy_data()[$name])
            || in_array($name, (array) taxopress_get_deactivated_taxonomy_data(), true);
    }

    /** Create a selected template through the existing taxonomy save service. */
    public static function create($name, $post_types, $description = '', $visibility = 'public')
    {
        if (!current_user_can('simple_tags') || !current_user_can('manage_categories')) {
            return new WP_Error('forbidden', __('You do not have permission to add taxonomies.', 'simple-tags'));
        }
        if (1 !== (int) SimpleTags_Plugin::get_option_value('active_taxonomies')) {
            return new WP_Error('disabled', __('Enable Taxonomies before adding a template.', 'simple-tags'));
        }

        $templates = self::templates();
        if (!is_string($name) || !isset($templates[$name])) {
            return new WP_Error('invalid_template', __('Choose a taxonomy template.', 'simple-tags'));
        }
        if (self::exists($name)) {
            return new WP_Error('already_exists', __('A taxonomy with this slug already exists. Your existing taxonomy has not been changed.', 'simple-tags'));
        }
        if (!in_array($visibility, ['public', 'private'], true)) {
            return new WP_Error('invalid_visibility', __('Choose Public or Private for the taxonomy visibility.', 'simple-tags'));
        }
        if (!is_array($post_types) || empty($post_types)) {
            return new WP_Error('invalid_post_types', __('Select at least one post type.', 'simple-tags'));
        }
        foreach ($post_types as $post_type) {
            $type = is_string($post_type) ? get_post_type_object($post_type) : null;
            if (!$type || !$type->show_ui || !current_user_can($type->cap->edit_posts)) {
                return new WP_Error('invalid_post_types', __('Choose post types you have permission to edit.', 'simple-tags'));
            }
        }

        $public = 'public' === $visibility ? 1 : 0;
        $definition = array_merge($templates[$name], [
            'name' => $name,
            'description' => is_string($description) ? sanitize_textarea_field($description) : '',
            'public' => $public,
            'publicly_queryable' => $public,
            'show_ui' => 1,
            'show_in_menu' => 1,
            'show_in_nav_menus' => $public,
            'show_admin_column' => 1,
            'show_in_rest' => $public,
            'show_in_quick_edit' => 1,
            'query_var' => $public,
            'query_var_slug' => '',
            'rewrite' => $public,
            'rewrite_slug' => str_replace('_', '-', $name),
            'rewrite_withfront' => 1,
            'rest_base' => '',
            'rest_controller_class' => '',
            'meta_box_cb' => '',
            'default_term' => '',
        ]);
        $result = taxopress_update_taxonomy([
            'cpt_custom_tax' => $definition,
            'cpt_tax_labels' => [],
            'cpt_post_types' => array_values(array_unique($post_types)),
            'cpt_tax_status' => 'new',
        ]);
        $saved = taxopress_get_taxonomy_data();
        if ('add_success' !== $result || !isset($saved[$name])) {
            return new WP_Error('save_failed', __('The taxonomy could not be added. Please try again.', 'simple-tags'));
        }
        return $name;
    }

    /** Handle the nonce-protected template form and redirect after saving. */
    public static function handle_create()
    {
        if (!current_user_can('simple_tags') || !current_user_can('manage_categories')) {
            wp_die(esc_html__('You do not have permission to add taxonomies.', 'simple-tags'), '', ['response' => 403]);
        }
        check_admin_referer('taxopress_add_taxonomy_template');
        $name = isset($_POST['taxonomy_template']) ? sanitize_key(wp_unslash($_POST['taxonomy_template'])) : '';
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_key', (array) wp_unslash($_POST['post_types'])) : [];
        $descriptions = isset($_POST['template_descriptions']) ? array_map('sanitize_textarea_field', (array) wp_unslash($_POST['template_descriptions'])) : [];
        $visibility = isset($_POST['template_visibility']) ? array_map('sanitize_key', (array) wp_unslash($_POST['template_visibility'])) : [];
        $result = self::create($name, $post_types, $descriptions[$name] ?? '', $visibility[$name] ?? 'public');
        wp_safe_redirect(add_query_arg([
            'page' => 'st_taxonomies',
            'templates' => 1,
            'template_result' => is_wp_error($result) ? $result->get_error_code() : 'added',
            'taxonomy_template' => $name,
            'post_types' => array_map('sanitize_key', array_filter($post_types, 'is_string')),
        ], admin_url('admin.php')));
        exit;
    }

    /** Display the template catalog and post type selection. */
    public static function render()
    {
        $templates = self::templates();
        $managed = taxopress_get_taxonomy_data();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection retained after redirect.
        $selected = isset($_GET['post_types']) ? array_map('sanitize_key', (array) wp_unslash($_GET['post_types'])) : ['post'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice code; messages are selected from a fixed list.
        $result = isset($_GET['template_result']) ? sanitize_key(wp_unslash($_GET['template_result'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only identification of the card added before redirect.
        $requested_template = isset($_GET['taxonomy_template']) ? sanitize_key(wp_unslash($_GET['taxonomy_template'])) : '';
        $added_template = 'added' === $result && isset($templates[$requested_template], $managed[$requested_template]) ? $requested_template : '';
        $messages = [
            'added' => __('Taxonomy added. You can add terms and change its settings from the Taxonomies screen.', 'simple-tags'),
            'already_exists' => __('A taxonomy with this slug already exists. Your existing taxonomy has not been changed.', 'simple-tags'),
            'invalid_post_types' => __('Select at least one post type you have permission to edit.', 'simple-tags'),
            'invalid_template' => __('Choose a taxonomy template.', 'simple-tags'),
            'invalid_visibility' => __('Choose Public or Private for the taxonomy visibility.', 'simple-tags'),
            'disabled' => __('Enable Taxonomies before adding a template.', 'simple-tags'),
            'save_failed' => __('The taxonomy could not be added. Please try again.', 'simple-tags'),
        ];
        if ('' !== $added_template) {
            /* translators: %s: taxonomy name. */
            $messages['added'] = sprintf(__('%s taxonomy added successfully. You can now edit its settings or add terms.', 'simple-tags'), $templates[$added_template]['label']);
        }
        ?>
        <div class="wrap st_wrap st-taxonomy-templates-page">
            <h1 class="wp-heading-inline"><?php esc_html_e('Taxonomy Templates', 'simple-tags'); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url(admin_url('admin.php?page=st_taxonomies')); ?>"><?php esc_html_e('Back to Taxonomies', 'simple-tags'); ?></a>
            <hr class="wp-header-end">
            <p><?php esc_html_e('Choose where to use your taxonomy, then add a template. Each template starts without terms and can be customized later.', 'simple-tags'); ?></p>
            <?php if (isset($messages[$result])) : ?>
                <div class="notice taxopress-notice <?php echo 'added' === $result ? 'notice-success' : 'notice-error'; ?> is-dismissible" role="<?php echo 'added' === $result ? 'status' : 'alert'; ?>"><p><?php echo esc_html($messages[$result]); ?></p></div>
            <?php endif; ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="taxopress_add_taxonomy_template">
                <?php wp_nonce_field('taxopress_add_taxonomy_template'); ?>
                <fieldset class="taxopress-template-post-types">
                    <legend><strong><?php esc_html_e('Post Types', 'simple-tags'); ?></strong></legend>
                    <?php foreach (get_post_types(['show_ui' => true], 'objects') as $type) : ?>
                        <?php if (!current_user_can($type->cap->edit_posts)) {
                            continue;
                        } ?>
                        <label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $selected, true)); ?>> <?php echo esc_html($type->labels->name); ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description"><?php esc_html_e('Public taxonomies have archive pages. Private taxonomies are available only in the admin area.', 'simple-tags'); ?></p>
                <div class="taxopress-taxonomy-template-grid">
                    <?php foreach ($templates as $name => $template) : ?>
                        <?php $exists = self::exists($name); ?>
                        <section class="card taxopress-taxonomy-template<?php echo $name === $added_template ? ' is-added' : ''; ?>">
                            <h2><?php echo esc_html($template['label']); ?></h2>
                            <?php if ($name === $added_template) : ?>
                                <p class="taxopress-template-added-status"><?php esc_html_e('Added successfully', 'simple-tags'); ?></p>
                            <?php endif; ?>
                            <?php if (!$exists) : ?>
                                <div class="taxopress-template-visibility">
                                    <label for="template-visibility-<?php echo esc_attr($name); ?>"><?php esc_html_e('Visibility', 'simple-tags'); ?></label>
                                    <select id="template-visibility-<?php echo esc_attr($name); ?>" name="template_visibility[<?php echo esc_attr($name); ?>]">
                                        <option value="public"><?php esc_html_e('Public', 'simple-tags'); ?></option>
                                        <option value="private"><?php esc_html_e('Private', 'simple-tags'); ?></option>
                                    </select>
                                </div>
                                <div class="taxopress-template-description">
                                    <label for="template-description-<?php echo esc_attr($name); ?>"><?php esc_html_e('Description (optional)', 'simple-tags'); ?></label>
                                    <textarea id="template-description-<?php echo esc_attr($name); ?>" name="template_descriptions[<?php echo esc_attr($name); ?>]" rows="3"></textarea>
                                </div>
                            <?php endif; ?>
                            <p><?php esc_html_e('Slug:', 'simple-tags'); ?> <code><?php echo esc_html($name); ?></code></p>
                            <div class="taxopress-template-actions">
                                <?php if ($exists) : ?>
                                    <span><?php esc_html_e('Already exists', 'simple-tags'); ?></span>
                                    <?php if (isset($managed[$name])) : ?>
                                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'st_taxonomies', 'add' => 'taxonomy', 'action' => 'edit', 'taxopress_taxonomy' => $name], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit Taxonomy', 'simple-tags'); ?></a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php /* translators: %s: taxonomy name. */ ?>
                                    <button class="button button-primary" type="submit" name="taxonomy_template" value="<?php echo esc_attr($name); ?>" aria-label="<?php echo esc_attr(sprintf(__('Add %s taxonomy', 'simple-tags'), $template['label'])); ?>"><?php esc_html_e('Add Taxonomy', 'simple-tags'); ?></button>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        <?php
    }
}
