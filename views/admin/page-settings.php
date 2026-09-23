<div class="taxopress-block-wrap">
<div class="wrap st_wrap tagcloudui admin-settings">
    <div id="icon-themes" class="icon32"><br></div>
    <h2><?php _e('TaxoPress: Options', 'simple-tags'); ?></h2>

    <h2 class="nav-tab-wrapper">
        <?php
        // Get array options/description
        $option_data = (array) include(STAGS_DIR . '/inc/helper.options.admin.php');
        foreach ($option_data as $key => $val) {
            $style = '';

          // Deactive tabs if feature not actived
            if ($key == 'relatedposts' && isset($options['active_related_posts']) && (int) $options['active_related_posts'] == 0) {
                $style = 'style="display:none;"';
            }

            if ($key == 'auto-links' && isset($options['auto_link_tags']) && ((int) $options['auto_link_tags'] == 0 || (int) SimpleTags_Plugin::get_option_value('auto_link_tags') === 0)) {
                 $style = 'style="display:none;"';
            }

            if ($key == 'linked_terms' && (!taxopress_is_pro_version() || (isset($options['active_features_linked_terms']) && (int) $options['active_features_linked_terms'] == 0))) {
                $style = 'style="display:none;"';
            }

            if ($key == 'core_linked_terms' && (taxopress_is_pro_version() || (isset($options['active_features_core_linked_terms']) && (int) $options['active_features_core_linked_terms'] == 0))) {
                  $style = 'style="display:none;"';
            }

            if ($key == 'synonyms' && (!taxopress_is_pro_version() || (isset($options['active_features_synonyms']) && (int) $options['active_features_synonyms'] == 0))) {
                $style = 'style="display:none;"';
            }

            if ($key == 'core_synonyms_terms' && (taxopress_is_pro_version() || (isset($options['active_features_core_synonyms_terms']) && (int) $options['active_features_core_synonyms_terms'] == 0))) {
                $style = 'style="display:none;"';
            }

     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<a id="' . esc_attr(sanitize_title($key)) . '-tab" class="nav-tab" ' . $style . ' href="#' . esc_attr(sanitize_title($key)) . '">' . self::getNiceTitleOptions($key) . '</a>';
        }
        ?>
    </h2>

    <form action="<?php echo esc_url(SimpleTags_Admin::$admin_url); ?>" method="post">
        <?php
  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SimpleTags_Admin::print_options($option_data);
        ?>

        <p>
            <?php wp_nonce_field('updateresetoptions-simpletags'); ?>
            <input class="button-primary" type="submit" name="updateoptions"
                   value="<?php _e('Save Changes', 'simple-tags'); ?>"/>
        </p>
    </form>

    <?php SimpleTags_Admin::printAdminFooter(); ?>
</div>

<div class="taxopress-right-sidebar admin-settings-sidebar">
    <?php do_action('taxopress_admin_after_sidebar'); ?>
</div>
</div>
