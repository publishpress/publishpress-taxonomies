<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Admin_Manage
{
    public const MENU_SLUG = 'st_options';

    // class instance
    public static $instance;

    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {
        add_filter('set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3);
        // Admin menu
        add_action('admin_menu', array( $this, 'admin_menu' ));

        // Register taxo, parent method...
        SimpleTags_Admin::register_taxonomy();

        // Javascript
        add_action('admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ), 11);

        //load ajax
        add_action('wp_ajax_taxopress_check_delete_terms', array( $this, 'handle_taxopress_check_delete_terms_ajax'));
        add_action('wp_ajax_taxopress_autocomplete_terms', [ $this, 'handle_taxopress_autocomplete_terms']);
        add_action('wp_ajax_taxopress_merge_terms_preflight', [$this, 'taxopress_merge_terms_preflight']);
        add_action('wp_ajax_taxopress_merge_terms_batch', [$this, 'taxopress_merge_terms_batch']);
        add_action('wp_ajax_taxopress_merge_suggestions', [$this, 'handle_taxopress_merge_suggestions']);
    }

    /**
     * Init somes JS and CSS need for this feature
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function admin_enqueue_scripts()
    {
        wp_register_script('st-helper-manage', STAGS_URL . '/assets/js/helper-manage.js', array( 'jquery' ), STAGS_VERSION);

        // add JS for manage click tags
        if (isset($_GET['page']) && $_GET['page'] == 'st_manage') {
            wp_enqueue_script('st-helper-manage');
        }
    }

    public static function set_screen($status, $option, $value)
    {
        return $value;
    }

    /**
     * Add WP admin menu for Tags
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function admin_menu()
    {
        $hook = add_submenu_page(
            self::MENU_SLUG,
            __('PublishPress Taxonomies: Manage Terms', 'simple-tags'),
            __('Manage Terms', 'simple-tags'),
            'simple_tags',
            'st_manage',
            array(
                $this,
                'page_manage_tags',
            )
        );
    }

    /**
     * Method for build the page HTML manage tags
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function page_manage_tags()
    {
        $default_tab = '';
        // Control Post data
        if (isset($_POST['term_action'])) {
            if (!current_user_can('simple_tags')) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Permission denied!', 'simple-tags'), 'error taxopress-notice');
            } elseif (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['term_nonce'])), 'simpletags_admin')) { // Origination and intention
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Security problem. Try again.', 'simple-tags'), 'error taxopress-notice');
            } elseif (! isset(SimpleTags_Admin::$taxonomy) || ! taxonomy_exists(SimpleTags_Admin::$taxonomy)) { // Valid taxo ?
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Missing valid taxonomy for work. Try again.', 'simple-tags'), 'error taxopress-notice');
            } elseif ($_POST['term_action'] == 'renameterm') {
                $taxonomy = isset($_POST['current_taxo']) ? sanitize_text_field(wp_unslash($_POST['current_taxo'])) : 'post_tag';
                $post_type = isset($_POST['current_cpt']) ? sanitize_text_field(wp_unslash($_POST['current_cpt'])) : 'post';

                SimpleTags_Admin::$taxonomy = $taxonomy;
                SimpleTags_Admin::$post_type = $post_type;

                $oldtag = isset($_POST['renameterm_old']) ? sanitize_text_field(wp_unslash($_POST['renameterm_old'])) : '';
                $newtag = isset($_POST['renameterm_new']) ? sanitize_text_field(wp_unslash($_POST['renameterm_new'])) : '';
                self::renameTerms($taxonomy, $oldtag, $newtag);
                $default_tab = '.st-rename-terms';
            } elseif ($_POST['term_action'] == 'mergeterm') {
                $taxonomy = isset($_POST['current_taxo']) ? sanitize_text_field(wp_unslash($_POST['current_taxo'])) : 'post_tag';
                $post_type = isset($_POST['current_cpt']) ? sanitize_text_field(wp_unslash($_POST['current_cpt'])) : 'post';

                SimpleTags_Admin::$taxonomy = $taxonomy;
                SimpleTags_Admin::$post_type = $post_type;

                $oldtag = isset($_POST['renameterm_old']) ? sanitize_text_field(wp_unslash($_POST['renameterm_old'])) : '';
                $newtag = isset($_POST['renameterm_new']) ? sanitize_text_field(wp_unslash($_POST['renameterm_new'])) : '';
                $merge_type = isset($_POST['mergeterm_type']) ? sanitize_text_field(wp_unslash($_POST['mergeterm_type'])) : '';
                self::mergeTerms($taxonomy, $oldtag, $newtag, $merge_type);
                $default_tab = '.st-merge-terms';
            } elseif ($_POST['term_action'] == 'addterm') {
                $taxonomy = isset($_POST['current_taxo']) ? sanitize_text_field(wp_unslash($_POST['current_taxo'])) : 'post_tag';
                $post_type = isset($_POST['current_cpt']) ? sanitize_text_field(wp_unslash($_POST['current_cpt'])) : 'post';

                SimpleTags_Admin::$taxonomy = $taxonomy;
                SimpleTags_Admin::$post_type = $post_type;

                $oldtag = isset($_POST['addterm_match']) ? sanitize_text_field(wp_unslash($_POST['addterm_match'])) : '';
                $newtag = isset($_POST['addterm_new']) ? sanitize_text_field(wp_unslash($_POST['addterm_new'])) : '';
                self::addMatchTerms($taxonomy, $oldtag, $newtag);
                $default_tab = '.st-add-terms';
            } elseif ($_POST['term_action'] == 'removeterm') {
                $taxonomy = isset($_POST['current_taxo']) ? sanitize_text_field(wp_unslash($_POST['current_taxo'])) : 'post_tag';
                $post_type = isset($_POST['current_cpt']) ? sanitize_text_field(wp_unslash($_POST['current_cpt'])) : 'post';

                SimpleTags_Admin::$taxonomy = $taxonomy;
                SimpleTags_Admin::$post_type = $post_type;

                $matchtag = isset($_POST['removeterm_match']) ? sanitize_text_field(wp_unslash($_POST['removeterm_match'])) : '';
                $removetag = isset($_POST['remove_term']) ? sanitize_text_field(wp_unslash($_POST['remove_term'])) : '';
                self::removeMatchTerms($taxonomy, $matchtag, $removetag);
                $default_tab = '.st-remove-terms';
            } elseif ($_POST['term_action'] == 'remove-rarelyterms') {
                $taxonomy = isset($_POST['current_taxo']) ? sanitize_text_field(wp_unslash($_POST['current_taxo'])) : 'post_tag';
                $post_type = isset($_POST['current_cpt']) ? sanitize_text_field(wp_unslash($_POST['current_cpt'])) : 'post';

                SimpleTags_Admin::$taxonomy = $taxonomy;
                SimpleTags_Admin::$post_type = $post_type;

                self::removeRarelyUsed($taxonomy, (int) $_POST['number-rarely']);
                $default_tab = '.st-delete-unuused-terms';
            } /* elseif ( $_POST['term_action'] == 'editslug'  ) {

                $matchtag = (isset($_POST['tagname_match'])) ? $_POST['tagname_match'] : '';
                $newslug  = (isset($_POST['tagslug_new'])) ? $_POST['tagslug_new'] : '';
                self::editTermSlug( SimpleTags_Admin::$taxonomy, $matchtag, $newslug );

            }*/
        }

        if ($default_tab && !empty($default_tab)) {
            //trigger default tab click on load
            echo '<div class="load-st-default-tab" data-page="' . esc_attr($default_tab) . '"></div>';
        }
        $active_tab_slug = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'add-terms';

        // Default order
        if (! isset($_GET['order'])) {
            $_GET['order'] = 'name-asc';
        }

        settings_errors(__CLASS__); ?>
<div class="clear"></div>
<div class="taxopress-block-wrap">
<div class="wrap st_wrap tagcloudui st-manage-terms-page admin-settings wrap st_wrap">

    <h1><?php _e('Manage Terms', 'simple-tags'); ?>
    </h1>
    <div class="taxopress-description"><?php esc_html_e('This feature allows you to manage your content terms by adding, renaming, merging and deleting unused terms.', 'simple-tags'); ?></div>

    <div class="clear"></div>

        <hr class="wp-header-end">
        <div class="clear"></div>

        <div id="col-container" class="wp-clearfix">
            <div class="col-wrap">
                <div class="form-wrap">
                    <ul class="simple-tags-nav-tab-wrapper">
                        <li class="nav-tab <?php echo $active_tab_slug === 'add-terms' ? 'nav-tab-active' : ''; ?>" data-page=".st-add-terms">
                            <?php echo esc_html__('Add terms', 'simple-tags'); ?>
                        </li>
                        <li class="nav-tab <?php echo $active_tab_slug === 'remove-terms' ? 'nav-tab-active' : ''; ?>" data-page=".st-remove-terms">
                            <?php echo esc_html__('Remove terms', 'simple-tags'); ?>
                        </li>
                        <li class="nav-tab <?php echo $active_tab_slug === 'rename-terms' ? 'nav-tab-active' : ''; ?>" data-page=".st-rename-terms">
                            <?php echo esc_html__('Rename terms', 'simple-tags'); ?>
                        </li>
                        <li class="nav-tab <?php echo $active_tab_slug === 'merge-terms' ? 'nav-tab-active' : ''; ?>" data-page=".st-merge-terms">
                            <?php echo esc_html__('Merge terms', 'simple-tags'); ?>
                        </li>
                        <li class="nav-tab <?php echo $active_tab_slug === 'delete-unuused-terms' ? 'nav-tab-active' : ''; ?>" data-page=".st-delete-unuused-terms">
                            <?php echo esc_html__('Delete unused terms', 'simple-tags'); ?>
                        </li>
                    </ul>
                    <div class="clear"></div>



                    <table class="form-table">

                    <tr valign="top" class="auto-terms-content st-add-terms" style="<?php echo $active_tab_slug === 'add-terms' ? '' : 'display:none;'; ?>">
                            <td>
                            <?php SimpleTags_Admin::tabSelectorTaxonomy('add-terms', 'st_manage'); ?>
                                <h2><?php _e('Add Terms', 'simple-tags'); ?></h2>
                                <p><?php printf(esc_html__('This feature lets you add one or more new terms to all %s which match any of the terms given.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?></p>
                                <p><?php printf(esc_html__('Terms will be added to all %s If no "Term(s) to match" is specified.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?></p>

                                <fieldset>
                                    <form action="" method="post">
                                    <input type="hidden" name="term_action" value="addterm" />
                                    <input type="hidden" name="term_nonce" value="<?php echo esc_attr(wp_create_nonce('simpletags_admin')); ?>" />
                                    <input type="hidden" name="current_tab" value="add-terms" />
                                    <input type="hidden" name="current_taxo" value="<?php echo esc_attr(get_option('add-terms_taxo')); ?>" />
                                    <input type="hidden" name="current_cpt" value="<?php echo esc_attr(get_option('add-terms_cpt')); ?>" />

                                        <p class="terms-type-options">
                                            <label>
                                                <input type="radio" id="addterm_type" class="addterm_type_all_posts" name="addterm_type" value="all_posts" checked="checked">
                                                <?php printf(esc_html__('Add terms to all %s.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?>
                                            </label><br>
                                            <label>
                                                <input type="radio" id="addterm_type" class="addterm_type_matched_only" name="addterm_type" value="matched_only">
                                                <?php _e('Add terms only to posts with specific terms attached.', 'simple-tags'); ?>
                                            </label>
                                        </p>

                                        <p class="terms-to-maatch-input" style="display: none;">
                                            <label for="addterm_match"><?php _e('Term(s) to match:', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input tag-cloud-input taxopress-expandable-textarea add-terms-autocomplete" id="addterm_match" name="addterm_match" size="80" data-tab="add-terms"
                                            data-taxo="<?php echo esc_attr(get_option('add-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <p>
                                            <label for="addterm_new"><?php _e('Term(s) to add:', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input taxopress-expandable-textarea add-terms-autocomplete" id="addterm_new" name="addterm_new" size="80" data-tab="add-terms"
                                            data-taxo="<?php echo esc_attr(get_option('add-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <input class="button-primary" type="submit" name="Add" value="<?php _e('Add', 'simple-tags'); ?>" />
                                    </form>
                                </fieldset>
                            </td>
                        </tr>

                        <tr valign="top" class="auto-terms-content st-remove-terms" style="<?php echo $active_tab_slug === 'remove-terms' ? '' : 'display:none;'; ?>">
                            <td>
                            <?php SimpleTags_Admin::tabSelectorTaxonomy('remove-terms', 'st_manage'); ?>
                                <h2><?php _e('Remove Terms', 'simple-tags'); ?></h2>
                                <p><?php printf(esc_html__('This feature lets you remove one or more terms from all %s which match any of the terms given.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?></p>
                                <p><?php printf(esc_html__('Terms will be removed from all %s If no "Term(s) to match" is specified.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?></p>

                                <fieldset>
                                    <form action="" method="post">
                                        <input type="hidden" name="term_action" value="removeterm" />
                                        <input type="hidden" name="term_nonce" value="<?php echo esc_attr(wp_create_nonce('simpletags_admin')); ?>" />
                                        <input type="hidden" name="current_tab" value="remove-terms" />
                                        <input type="hidden" name="current_taxo" value="<?php echo esc_attr(get_option('remove-terms_taxo')); ?>" />
                                        <input type="hidden" name="current_cpt" value="<?php echo esc_attr(get_option('remove-terms_cpt')); ?>" />

                                        <p class="terms-type-options">
                                            <label>
                                                <input type="radio" id="removeterm_type" class="removeterm_type_all_posts" name="removeterm_type" value="all_posts" checked="checked">
                                                <?php printf(esc_html__('Remove terms from all %s.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?>
                                            </label><br>
                                            <label>
                                                <input type="radio" id="removeterm_type" class="removeterm_type_matched_only" name="removeterm_type" value="matched_only">
                                                <?php _e('Remove terms only from posts with specific terms attached.', 'simple-tags'); ?>
                                            </label>
                                        </p>

                                        <p class="removeterms-to-match-input" style="display: none;">
                                            <label for="removeterm_match"><?php _e('Term(s) to match:', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input tag-cloud-input taxopress-expandable-textarea remove-terms-autocomplete" id="removeterm_match" name="removeterm_match" size="80" data-tab="remove-terms"
                                            data-taxo="<?php echo esc_attr(get_option('remove-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <p>
                                            <label for="remove_term"><?php _e('Term(s) to remove:', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input taxopress-expandable-textarea remove-terms-autocomplete" id="remove_term" name="remove_term" size="80" data-tab="remove-terms"
                                            data-taxo="<?php echo esc_attr(get_option('remove-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <input class="button-primary" type="submit" name="Remove" value="<?php _e('Remove', 'simple-tags'); ?>" />
                                    </form>
                                </fieldset>
                            </td>
                        </tr>

                        <tr valign="top" class="auto-terms-content st-rename-terms" style="<?php echo $active_tab_slug === 'rename-terms' ? '' : 'display:none;'; ?>">
                            <td>
                            <?php SimpleTags_Admin::tabSelectorTaxonomy('rename-terms', 'st_manage'); ?>
                                <h2><?php _e('Rename Terms', 'simple-tags'); ?></h2>
                                <p><?php _e('Enter the terms to rename and their new names.', 'simple-tags'); ?></p>

                                <fieldset>
                                    <form action="" method="post">
                                        <input type="hidden" name="term_action" value="renameterm" />
                                        <input type="hidden" name="term_nonce" value="<?php echo esc_attr(wp_create_nonce('simpletags_admin')); ?>" />
                                        <input type="hidden" name="current_tab" value="rename-terms" />
                                        <input type="hidden" name="current_taxo" value="<?php echo esc_attr(get_option('rename-terms_taxo')); ?>" />
                                        <input type="hidden" name="current_cpt" value="<?php echo esc_attr(get_option('rename-terms_cpt')); ?>" />
                                        <p>
                                            <label for="renameterm_old"><?php _e('Term(s) to rename:', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input tag-cloud-input taxopress-expandable-textarea rename-terms-autocomplete" id="renameterm_old" name="renameterm_old" size="80" data-taxo="<?php echo esc_attr(get_option('rename-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <p>
                                            <label for="renameterm_new"><?php _e('New term name(s):', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input taxopress-expandable-textarea rename-terms-autocomplete" id="renameterm_new" name="renameterm_new" size="80" data-taxo="<?php echo esc_attr(get_option('rename-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <input class="button-primary" type="submit" name="rename" value="<?php _e('Rename', 'simple-tags'); ?>" />
                                    </form>
                                </fieldset>
                            </td>
                        </tr>

                        <tr valign="top" class="auto-terms-content st-merge-terms" style="<?php echo $active_tab_slug === 'merge-terms' ? '' : 'display:none;'; ?>">
                            <td>
                            <?php SimpleTags_Admin::tabSelectorTaxonomy('merge-terms', 'st_manage'); ?>
                                <h2><?php _e('Merge Terms', 'simple-tags'); ?></h2>
                                <p><?php esc_html_e('This feature will delete existing terms and replace them with another term.', 'simple-tags'); ?></p>

                                <fieldset>
                                    <form action="" method="post" class="merge-terms-form">
                                        <input type="hidden" name="term_action" value="mergeterm" />
                                        <input type="hidden" name="term_nonce" value="<?php echo esc_attr(wp_create_nonce('simpletags_admin')); ?>" />
                                        <input type="hidden" name="current_tab" value="merge-terms" />
                                        <input type="hidden" name="current_taxo" value="<?php echo esc_attr(get_option('merge-terms_taxo')); ?>" />
                                        <input type="hidden" name="current_cpt" value="<?php echo esc_attr(get_option('merge-terms_cpt')); ?>" />

                                        <p class="terms-type-options">
                                        <label><input type="radio" id="mergeterm_type" class="mergeterm_type_different_name" name="mergeterm_type" value="different_name" checked="checked"><?php _e('Merge terms with different name.', 'simple-tags'); ?></label><br>
                                        <label><input type="radio" id="mergeterm_type" class="mergeterm_type_same_name" name="mergeterm_type" value="same_name"><?php esc_html_e('Merge terms with same name.', 'simple-tags'); ?></label>
                                        </p>

                                        <p>
                                            <label for="renameterm_old"><?php esc_html_e('Existing term(s) to merge.', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input tag-cloud-input taxopress-expandable-textarea merge-feature-autocomplete" id="mergeterm_old" name="renameterm_old" size="80" data-taxo="<?php echo esc_attr(get_option('merge-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <p class="new_name_input">
                                            <label for="renameterm_new"><?php esc_html_e('New term. The old terms will be deleted and any posts assigned to the old terms will be re-assigned to this term.', 'simple-tags'); ?></label><br />
                                            <textarea type="text" class="autocomplete-input taxopress-expandable-textarea merge-feature-autocomplete" id="mergeterm_new" name="renameterm_new" size="80" data-taxo="<?php echo esc_attr(get_option('merge-terms_taxo')); ?>"></textarea>
                                        </p>

                                        <input class="suggest-merge-terms" type="button" id="suggest-merge-terms" value="<?php _e('Suggest Terms to Merge', 'simple-tags'); ?>" />
                                        <input class="button-primary" type="submit" name="merge" id="merge-terms" value="<?php _e('Merge Terms', 'simple-tags'); ?>" />
                                        <div id="merge-progress" style="margin-top: 10px;"></div>
                                    </form>
                                </fieldset>
                            </td>
                        </tr>

                        <tr valign="top" class="auto-terms-content st-delete-unuused-terms" style="<?php echo $active_tab_slug === 'delete-unuused-terms' ? '' : 'display:none;'; ?>">
                            <td>
                            <?php SimpleTags_Admin::tabSelectorTaxonomy('delete-unuused-terms', 'st_manage'); ?>
                                <h2><?php esc_html_e('Remove rarely used terms', 'simple-tags'); ?></h2>
                                <p><?php esc_html_e('This feature allows you to remove rarely used terms.', 'simple-tags'); ?></p>
                                <p><?php printf(esc_html__('If you choose 5, PublishPress Taxonomies will delete all terms attached to less than 5 %s.', 'simple-tags'), esc_html(SimpleTags_Admin::$post_type_name)); ?></p>

                                <fieldset>
                                    <form action="" method="post">
                                        <input type="hidden" name="term_action" value="remove-rarelyterms" />
                                        <input type="hidden" name="term_nonce" value="<?php echo esc_attr(wp_create_nonce('simpletags_admin')); ?>" />
                                        <input type="hidden" name="current_tab" value="delete-unuused-terms" />
                                        <input type="hidden" name="current_taxo" value="<?php echo esc_attr(get_option('delete-unuused-terms_taxo')); ?>" />
                                        <input type="hidden" name="current_cpt" value="<?php echo esc_attr(get_option('delete-unuused-terms_cpt')); ?>" />

                                        <p>
                                            <label for="number-delete"><?php _e('Minimum number of uses for each term:', 'simple-tags'); ?></label><br />
                                            <select name="number-rarely" id="number-delete">
                                                <?php for ($i = 1; $i <= 100; $i++) : ?>
                                                    <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </p>

                                        <label for="check-terms"><?php _e('Check how many terms will be deleted:', 'simple-tags'); ?></label><br />
                                        <input style="margin-top: 2px;" id="check-terms-btn" class="button-primary" type="submit" name="Check" value="<?php esc_attr_e('Check Terms', 'simple-tags'); ?>" />
                                        <div id="terms-feedback"></div>

                                        <label for="terms-delete"><?php _e('Delete rarely used terms:', 'simple-tags'); ?></label><br />
                                        <input style="margin-top: 2px;" class="button-secondary delete-unused-term" type="submit" name="Delete" value="<?php esc_attr_e('Delete Terms', 'simple-tags'); ?>" />
                                    </form>
                                </fieldset>
                            </td>
                        </tr>

                        <?php /*
                <tr valign="top">
                    <th scope="row"><strong><?php _e('Edit Term Slug', 'simple-tags'); ?></strong></th>
                    <td>
                        <p><?php _e('Enter the term name to edit and its new slug. <a href="http://codex.wordpress.org/Glossary#Slug">Slug definition</a>', 'simple-tags'); ?></p>

                        <fieldset>
                            <form action="" method="post">
                                <input type="hidden" name="taxo" value="<?php echo esc_attr(SimpleTags_Admin::$taxonomy); ?>" />
                                <input type="hidden" name="cpt" value="<?php echo esc_attr(SimpleTags_Admin::$post_type); ?>" />

                                <input type="hidden" name="term_action" value="editslug" />
                                <input type="hidden" name="term_nonce" value="<?php echo wp_create_nonce('simpletags_admin'); ?>" />

                                <p>
                                    <label for="tagname_match"><?php _e('Term(s) to match:', 'simple-tags'); ?></label>
                                    <br />
                                    <input type="text" class="autocomplete-input" id="tagname_match" name="tagname_match" size="80" />
                                </p>

                                <p>
                                    <label for="tagslug_new"><?php _e('Slug(s) to set:', 'simple-tags'); ?></label>
                                    <br />
                                    <input type="text" class="autocomplete-input" id="tagslug_new" name="tagslug_new" size="80" />
                                </p>

                                <input class="button-primary" type="submit" name="edit" value="<?php _e('Edit', 'simple-tags'); ?>" />
                            </form>
                        </fieldset>
                    </td>
                </tr>
                */
                        ?>

                    </table>

                    <div class="remodal" data-remodal-id="taxopress-modal-merge-warning"
                        data-remodal-options="hashTracking: false, closeOnOutsideClick: false">
                        <div class="taxopress-merge-warning-title">
                            <?php echo esc_html__('Multiple merge suggestions selected', 'simple-tags'); ?>
                        </div>
                        <div id="taxopress-modal-merge-warning-content">
                            <?php echo esc_html__(
                                'You have selected multiple merge suggestions. All selected terms will be merged into a single new term.',
                                'simple-tags'
                            ); ?>
                        </div>
                        <br>
                        <button id="taxopress-merge-warning-cancel" data-remodal-action="cancel" class="button button-secondary remodal-cancel">
                            <?php echo esc_html__('Cancel', 'simple-tags'); ?>
                        </button>
                        <button id="taxopress-merge-warning-confirm" class="button button-primary">
                            <?php echo esc_html__('Continue', 'simple-tags'); ?>
                        </button>
                    </div>



                </div>
            </div>


            <div class="clear"></div>

        </div>
</div>
<div class="taxopress-right-sidebar admin-settings-sidebar">
        <?php do_action('taxopress_admin_after_sidebar'); ?>
</div>

</div>


        <?php SimpleTags_Admin::printAdminFooter(); ?>



        <?php
        do_action('simpletags-manage_terms', SimpleTags_Admin::$taxonomy);
    }

    /**
     * Method to merge tags
     *
     * @param string $taxonomy
     * @param string $old
     * @param string $new
     *
     * @return boolean
     * @author olatechpro
     */
    public static function mergeTerms($taxonomy = 'post_tag', $old = '', $new = '', $merge_type = '')
    {
        $taxonomy = sanitize_key($taxonomy);
        if (
            !taxopress_current_user_can_for_taxonomy(
                $taxonomy,
                ['edit_terms', 'delete_terms', 'assign_terms']
            )
        ) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('Permission denied for this taxonomy.', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        // Helper function to extract term name (ignoring slug in brackets)
        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        if ($merge_type === 'same_name') {
            $old_terms = explode(',', $old);
            $old_terms = array_map($extractTermName, $old_terms);
            $old_terms = array_filter($old_terms, '_delete_empty_element');
            $old_terms = array_values(array_unique(array_map('sanitize_text_field', $old_terms)));
            $retained_slug_param = isset($_POST['retained_slug']) ? sanitize_title(wp_unslash($_POST['retained_slug'])) : '';

            if (empty($old_terms)) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('No terms provided for merging!', 'simple-tags'), 'error taxopress-notice');
                return false;
            }

            $terms = [];
            $terms_by_id = [];
            foreach ($old_terms as $term_name) {
                $term_objects = get_terms([
                    'taxonomy' => $taxonomy,
                    'name' => sanitize_text_field($term_name),
                    'hide_empty' => false,
                ]);

                if (is_array($term_objects) && count($term_objects) > 1) {
                    foreach ($term_objects as $term_object) {
                        $terms_by_id[(int) $term_object->term_id] = $term_object;
                    }
                }
            }

            $terms = array_values($terms_by_id);

            if (empty($terms)) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('No terms with the same name found.', 'simple-tags'), 'error taxopress-notice');
                return false;
            }

            $sort_term_name = reset($old_terms);
            usort($terms, function ($a, $b) use ($sort_term_name, $retained_slug_param) {
                // If a retained slug is provided, sort that term to the top
                if ($retained_slug_param) {
                    if ($a->slug === $retained_slug_param) {
                        return -1;
                    }
                    if ($b->slug === $retained_slug_param) {
                        return 1;
                    }
                }
                // Score based on similarity to term name
                similar_text($sort_term_name, $a->slug, $similarity_a);
                similar_text($sort_term_name, $b->slug, $similarity_b);

                if ($similarity_a === $similarity_b) {
                    // If similarity is the same, sort by length (shortest first)
                    return strlen($a->slug) - strlen($b->slug);
                }

                // Otherwise, sort by similarity (higher first)
                return $similarity_b - $similarity_a;
            });

            // Retain the term with the highest similarity and shortest slug (first in sorted array)
            $retained_term = $terms[0];
            $retained_slug = $retained_term->slug;
            $retained_id = $retained_term->term_id;

            // Collect terms to delete
            $terms_to_delete = array_filter($terms, function ($term) use ($retained_id) {
                return $term->term_id !== $retained_id;
            });

            // Reassign objects and delete old terms (existing logic)
            $terms_id = array_map(function ($term) {
                return $term->term_id;
            }, $terms_to_delete);

            $objects_id = get_objects_in_term($terms_id, $taxonomy, ['fields' => 'ids']);
            foreach ($objects_id as $object_id) {
                if (!current_user_can('edit_post', $object_id)) {
                    continue;
                }
                // Check if the object already has the term assigned
                $current_terms = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'ids']);
                if (!in_array($retained_id, $current_terms)) {
                    wp_set_object_terms($object_id, $retained_slug, $taxonomy, true);
                }
            }

            foreach ($terms_to_delete as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }

            // Clean caches
            clean_object_term_cache($objects_id, $taxonomy);
            clean_term_cache($terms_id, $taxonomy);

            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Merged term(s) with the same name "%s". %d posts updated.', 'simple-tags'), $retained_term->name, count($objects_id)), 'updated taxopress-notice');
            return [
                'success' => true,
                'retained_slug' => $retained_slug,
                'posts_updated' => count($objects_id),
                'post_ids' => $objects_id,
                'terms_merged' => count($terms_to_delete) + 1,
            ];
        } else {
            if (trim(str_replace(',', '', stripslashes($new))) == '' && $merge_type !== 'same_name') {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('No new term specified!', 'simple-tags'), 'error taxopress-notice');

                return false;
            }

            // String to array
            $old_term_inputs = explode(',', $old);
            $new_term_inputs = explode(',', $new);

            // Remove empty element and trim
            $old_term_inputs = array_filter(array_map('trim', $old_term_inputs), '_delete_empty_element');
            $new_term_inputs = array_filter(array_map('trim', $new_term_inputs), '_delete_empty_element');
            $old_terms = array_map($extractTermName, $old_term_inputs);
            $new_terms = array_map($extractTermName, $new_term_inputs);
            $old_terms = array_values(array_unique(array_map('sanitize_text_field', $old_terms)));
            $new_terms = array_values(array_unique(array_map('sanitize_text_field', $new_terms)));
            $old_term_inputs = array_values(array_unique(array_map('sanitize_text_field', $old_term_inputs)));
            $new_term_inputs = array_values(array_unique(array_map('sanitize_text_field', $new_term_inputs)));
            $common_elements = array_intersect($old_terms, $new_terms);

            // If old/new tag are empty => exit !
            if (empty($old_terms) || empty($new_terms)) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('No new/old valid term specified!', 'simple-tags'), 'error taxopress-notice');

                return false;
            }

            if (!empty($common_elements) && $merge_type !== 'different_name') {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Term to merge and New Term must not contain same term.', 'simple-tags'), 'error taxopress-notice');

                return false;
            }

            $counter = 0;
            $unique_objects = [];
            $terms_id = [];
            if (count($new_terms) == 1) { // Merge
                // Set new tag
                $new_tag = sanitize_text_field($new_terms[0]);
                if (empty($new_tag)) {
                    add_settings_error(__CLASS__, __CLASS__, esc_html__('No valid new term.', 'simple-tags'), 'error taxopress-notice');

                    return false;
                }

                // Resolve and validate all source terms before processing the destination.
                $source_terms = [];
                $missing_terms = [];
                foreach ((array) $old_term_inputs as $old_term_input) {
                    $term = self::getExistingMergeTerm($taxonomy, $old_term_input);
                    if ($term) {
                        $source_terms[(int) $term->term_id] = $term;
                    } else {
                        $missing_term = sanitize_text_field($extractTermName($old_term_input));
                        if (!empty($missing_term)) {
                            $missing_terms[] = $missing_term;
                        }
                    }
                }

                if (!empty($missing_terms)) {
                    add_settings_error(
                        __CLASS__,
                        __CLASS__,
                        sprintf(
                            /* translators: %s: comma-separated term names. */
                            esc_html__('These terms do not exist and cannot be merged: %s', 'simple-tags'),
                            esc_html(implode(', ', $missing_terms))
                        ),
                        'error taxopress-notice'
                    );
                    return false;
                }

                if (empty($source_terms)) {
                    add_settings_error(__CLASS__, __CLASS__, esc_html__('No matching terms found to merge.', 'simple-tags'), 'error taxopress-notice');
                    return false;
                }

                // Resolve or create the destination only after validating all source terms.
                $new_term_input = !empty($new_term_inputs[0]) ? $new_term_inputs[0] : $new_tag;
                $new_term = self::getExistingMergeTerm($taxonomy, $new_term_input);

                if (!$new_term) {
                    $new_term_info = wp_insert_term($new_tag, $taxonomy);
                    if (is_wp_error($new_term_info)) {
                        add_settings_error(__CLASS__, __CLASS__, esc_html__('Failed to create the new term.', 'simple-tags'), 'error taxopress-notice');
                        return false;
                    }
                    $new_term_id = (int) $new_term_info['term_id'];
                } else {
                    $new_term_id = (int) $new_term->term_id;
                }

                // Never delete the destination when it was also selected as a source term.
                unset($source_terms[$new_term_id]);

                if (empty($source_terms)) {
                    add_settings_error(__CLASS__, __CLASS__, esc_html__('No other matching terms found to merge.', 'simple-tags'), 'error taxopress-notice');
                    return false;
                }

                $terms_id = array_keys($source_terms);
                $found_terms = wp_list_pluck(array_values($source_terms), 'name');

                // Get objects from terms ID
                $objects_id = get_objects_in_term($terms_id, $taxonomy, ['fields' => 'ids']);

                // Use a set to track unique post IDs
                $unique_objects = [];

                // Assign the new term to all posts associated with the old terms
                foreach ((array) $objects_id as $object_id) {
                    if (!current_user_can('edit_post', $object_id)) {
                        continue;
                    }
                    // Check if the object already has the term assigned
                    $current_terms = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'ids']);
                    if (!in_array($new_term_id, $current_terms)) {
                        wp_set_object_terms($object_id, $new_term_id, $taxonomy, true);
                    }
                    $unique_objects[$object_id] = true; // Add to unique set
                }

                // Count unique posts
                $counter = count($unique_objects);

                // Delete old terms
                foreach ((array) $terms_id as $term_id) {
                    wp_delete_term($term_id, $taxonomy);
                }

                // Test if term is also a category
                /*
                if ( is_term($new_tag, 'category') ) {
                    // Edit the slug to use the new term
                    self::editTermSlug( $new_tag, sanitize_title($new_tag) );
                }
                */

                // Clean cache
                clean_object_term_cache($objects_id, $taxonomy);
                clean_term_cache($terms_id, $taxonomy);


                if (empty($found_terms)) {
                    add_settings_error(__CLASS__, __CLASS__, esc_html__('No terms were merged (terms may not exist).', 'simple-tags'), 'error taxopress-notice');
                } else {
                    $message = sprintf(
                        esc_html__('Successfully merged %1$d term(s) "%2$s" to "%3$s". %4$s posts updated.', 'simple-tags'),
                        count($found_terms),
                        implode(', ', $found_terms),
                        rtrim($new, ','),
                        $counter
                    );
                    add_settings_error(__CLASS__, __CLASS__, $message, 'updated taxopress-notice');
                }
            } else { // Error
                add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Error. You need to enter a single term to merge to in new term name !', 'simple-tags'), $old), 'error taxopress-notice');
                return false;
            }

            return [
                'success' => true,
                'merged_into' => $new_tag,
                'posts_updated' => $counter,
                'post_ids' => array_keys($unique_objects),
                'terms_merged' => count($terms_id) + 1,
            ];
        }
    }

    /**
     * Resolve a merge term input to an existing term.
     *
     * Supports autocomplete values formatted as "Name (slug)" and plain names.
     *
     * @param string $taxonomy Taxonomy slug.
     * @param string $term_input Term name or autocomplete label.
     *
     * @return WP_Term|false
     */
    private static function getExistingMergeTerm($taxonomy, $term_input)
    {
        $term_parts = self::parseMergeTermInput($term_input);

        if (!empty($term_parts['slug'])) {
            $term = get_term_by('slug', sanitize_title($term_parts['slug']), $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        if (empty($term_parts['name'])) {
            return false;
        }

        $term = get_term_by('name', sanitize_text_field($term_parts['name']), $taxonomy);
        if (!$term || is_wp_error($term)) {
            $term = get_term_by('slug', sanitize_title($term_parts['name']), $taxonomy);
        }

        return ($term && !is_wp_error($term)) ? $term : false;
    }

    /**
     * Parse a merge term input string.
     *
     * @param string $term_input Term name or "Name (slug)" autocomplete label.
     *
     * @return array{name:string,slug:string}
     */
    private static function parseMergeTermInput($term_input)
    {
        $term_input = trim((string) $term_input);
        $term_parts = [
            'name' => $term_input,
            'slug' => '',
        ];

        if (preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/', $term_input, $matches)) {
            $term_parts['name'] = trim($matches[1]);
            $term_parts['slug'] = trim($matches[2]);
        }

        return $term_parts;
    }

    /**
     * Find terms with same names but different slugs
     */
    public static function getSameNameMergeSuggestions($taxonomy = 'post_tag')
    {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT t.name, COUNT(*) as count, GROUP_CONCAT(CONCAT(t.name, ' (', t.slug, ')') SEPARATOR ', ') as terms
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = %s
            GROUP BY t.name
            HAVING COUNT(*) > 1
            ORDER BY count DESC, t.name ASC
        ", $taxonomy);

        $results = $wpdb->get_results($query);

        $show_slug = (int) SimpleTags_Plugin::get_option_value('enable_merge_terms_slug');

        if (!$show_slug) {
            foreach ($results as &$result) {
                $result->terms = preg_replace('/\s*\([^)]+\)/', '', $result->terms);
            }
        }

        return $results;
    }

    /**
     * Find terms that might be candidates for merging based on similarity
     */
    public static function getDifferentNameMergeSuggestions($taxonomy = 'post_tag', $limit = 50)
    {
        global $wpdb;

        $limit = min(max((int) $limit, 1), 50);

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => $limit * 2,
            'update_term_meta_cache' => false,
        ]);

        $show_slug = (int) SimpleTags_Plugin::get_option_value('enable_merge_terms_slug');

        $suggestions = [];

        foreach ($terms as $i => $term1) {
            foreach ($terms as $j => $term2) {
                if ($i >= $j) {
                    continue;
                }

                $similarity_score = 0;
                $reasons = [];

                // 1. Levenshtein distance (for typos)
                $term1_name = strtolower($term1->name);
                $term2_name = strtolower($term2->name);
                if (abs(strlen($term1_name) - strlen($term2_name)) <= 2 && max(strlen($term1_name), strlen($term2_name)) <= 100) {
                    $levenshtein = levenshtein($term1_name, $term2_name);
                    if ($levenshtein <= 2 && $levenshtein > 0) {
                        $similarity_score += 40;
                        $reasons[] = esc_html__('Similar spelling', 'simple-tags');
                    }
                }

                // 2. Soundex (phonetic similarity)
                if (soundex($term1->name) === soundex($term2->name)) {
                    $similarity_score += 30;
                    $reasons[] = esc_html__('Sounds similar', 'simple-tags');
                }

                // 3. Common words/partial matches
                $words1 = explode(' ', strtolower($term1->name));
                $words2 = explode(' ', strtolower($term2->name));
                $common_words = array_intersect($words1, $words2);
                if (!empty($common_words)) {
                    $similarity_score += count($common_words) * 15;
                    $reasons[] = sprintf(esc_html__('Shared words: %s', 'simple-tags'), implode(', ', $common_words));
                }

                // 4. Prefix/suffix similarity
                if (strpos($term1->name, $term2->name) !== false || strpos($term2->name, $term1->name) !== false) {
                    $similarity_score += 25;
                    $reasons[] = esc_html__('One contains the other', 'simple-tags');
                }

                if ($similarity_score >= 30) {
                    $suggested_name = '';

                    // Get clean term names for comparison
                    $term1_clean = strtolower(trim($term1->name));
                    $term2_clean = strtolower(trim($term2->name));

                    if (!empty($common_words)) {
                        $suggested_name = ucfirst(implode(' ', $common_words));
                    } else {
                        // Use the shorter term as base
                        $suggested_name = strlen($term1->name) <= strlen($term2->name) ? $term1->name : $term2->name;
                    }

                    // let's make sure suggested name is different from both original terms
                    $suggested_clean = strtolower(trim($suggested_name));
                    if ($suggested_clean === $term1_clean || $suggested_clean === $term2_clean) {
                        if (!empty($common_words)) {
                            $suggested_name = ucfirst($common_words[0]);
                        } else {
                            $popular_term = ($term1->count >= $term2->count) ? $term1 : $term2;
                            $suggested_name = $popular_term->name;
                        }

                        $suggested_clean = strtolower(trim($suggested_name));
                    }

                    $term1_display = $show_slug ? $term1->name . ' (' . $term1->slug . ')' : $term1->name;
                    $term2_display = $show_slug ? $term2->name . ' (' . $term2->slug . ')' : $term2->name;

                    $suggestions[] = [
                        'term1' => $term1_display,
                        'term2' => $term2_display,
                        'score' => $similarity_score,
                        'reasons' => implode(', ', $reasons),
                        'suggested_name' => $suggested_name
                    ];
                }
            }
        }

        usort($suggestions, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * AJAX handler for merge suggestions
     */
    public function handle_taxopress_merge_suggestions()
    {
        if (!current_user_can('simple_tags')) {
            wp_send_json_error('Permission denied');
        }

        if (!wp_verify_nonce(wp_unslash($_POST['nonce']), 'simpletags_admin')) {
            wp_send_json_error('Invalid nonce');
        }

        $taxonomy = get_option('merge-terms_taxo', 'post_tag');
        if (!taxopress_current_user_can_for_taxonomy($taxonomy, 'manage_terms')) {
            wp_send_json_error('Permission denied', 403);
        }
        $merge_type = sanitize_text_field(wp_unslash($_POST['merge_type']));

        if ($merge_type === 'same_name') {
            $suggestions = self::getSameNameMergeSuggestions($taxonomy);
            wp_send_json_success([
                'type' => 'same_name',
                'suggestions' => $suggestions
            ]);
        } else {
            $suggestions = self::getDifferentNameMergeSuggestions($taxonomy);
            wp_send_json_success([
                'type' => 'different_name',
                'suggestions' => $suggestions
            ]);
        }
    }

    private static function get_merge_preflight_term_ids($taxonomy, $old_terms_input, $merge_type, &$missing_terms = [])
    {
        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        $term_ids = [];

        foreach ($old_terms_input as $term_name) {
            $term_name_clean = sanitize_text_field($extractTermName($term_name));

            if (empty($term_name_clean)) {
                continue;
            }

            if ($merge_type === 'same_name') {
                $matching_terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'name' => $term_name_clean,
                    'hide_empty' => false,
                    'fields' => 'ids',
                    'update_term_meta_cache' => false,
                ]);

                if (!is_wp_error($matching_terms) && !empty($matching_terms)) {
                    foreach ($matching_terms as $term_id) {
                        $term_ids[] = (int) $term_id;
                    }

                    continue;
                }
            }

            $term = self::getExistingMergeTerm($taxonomy, $term_name);

            if ($term) {
                $term_ids[] = (int) $term->term_id;
            } else {
                $missing_terms[] = $term_name_clean;
            }
        }

        $missing_terms = array_values(array_unique(array_filter($missing_terms)));

        return array_values(array_unique(array_filter($term_ids)));
    }

    private static function count_objects_for_merge_terms($taxonomy, $term_ids)
    {
        global $wpdb;

        if (empty($term_ids)) {
            return 0;
        }

        $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
        $cache_key = 'merge_preflight_' . md5($taxonomy . ':' . implode(',', $term_ids));
        $cached_count = wp_cache_get($cache_key, 'taxopress_terms');

        if ($cached_count !== false) {
            return (int) $cached_count;
        }

        $term_id_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $term_taxonomy_query = $wpdb->prepare(
            "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_id IN ({$term_id_placeholders})",
            array_merge([$taxonomy], $term_ids)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $term_taxonomy_ids = $wpdb->get_col($term_taxonomy_query);

        if (empty($term_taxonomy_ids)) {
            wp_cache_set($cache_key, 0, 'taxopress_terms', MINUTE_IN_SECONDS);
            return 0;
        }

        $term_taxonomy_ids = array_values(array_unique(array_map('intval', $term_taxonomy_ids)));
        $term_taxonomy_id_placeholders = implode(',', array_fill(0, count($term_taxonomy_ids), '%d'));
        $object_count_query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            "SELECT COUNT(DISTINCT object_id) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$term_taxonomy_id_placeholders})",
            $term_taxonomy_ids
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $object_count = (int) $wpdb->get_var($object_count_query);

        wp_cache_set($cache_key, $object_count, 'taxopress_terms', MINUTE_IN_SECONDS);

        return $object_count;
    }

    public static function taxopress_merge_terms_preflight()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'st-admin-js')) {
            wp_send_json_error(['message' => __('Security check failed.', 'simple-tags')], 403);
            wp_die();
        }

        if (!current_user_can('simple_tags')) {
            wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')], 403);
            wp_die();
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(['message' => __('Invalid taxonomy.', 'simple-tags')], 400);
            wp_die();
        }

        if (
            !taxopress_current_user_can_for_taxonomy(
                $taxonomy,
                ['edit_terms', 'delete_terms', 'assign_terms']
            )
        ) {
            wp_send_json_error(['message' => __('Permission denied for this taxonomy.', 'simple-tags')], 403);
            wp_die();
        }

        $merge_type = isset($_POST['merge_type']) ? sanitize_text_field(wp_unslash($_POST['merge_type'])) : 'different_name';
        $old_terms_input = isset($_POST['old_terms']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['old_terms'])) : [];

        $missing_terms = [];
        $term_ids = self::get_merge_preflight_term_ids($taxonomy, $old_terms_input, $merge_type, $missing_terms);
        if (!empty($missing_terms)) {
            wp_send_json_error([
                'code' => 'missing_terms',
                'missing_terms' => $missing_terms,
                'message' => sprintf(
                    /* translators: %s: comma-separated term names. */
                    __('These terms do not exist and cannot be merged: %s', 'simple-tags'),
                    implode(', ', $missing_terms)
                )
            ]);
            wp_die();
        }

        $affected_posts_count = self::count_objects_for_merge_terms($taxonomy, $term_ids);
        $workload = count($term_ids) * $affected_posts_count;
        $ajax_workload_threshold = (int) apply_filters('taxopress_merge_terms_ajax_workload_threshold', 20);

        wp_send_json_success([
            'terms_count' => count($term_ids),
            'affected_posts_count' => $affected_posts_count,
            'workload' => $workload,
            'use_ajax' => $workload > $ajax_workload_threshold,
        ]);
    }

    public static function taxopress_merge_terms_batch()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'st-admin-js')) {
            wp_send_json_error(['message' => __('Security check failed.', 'simple-tags')], 403);
            wp_die();
        }

        if (!current_user_can('simple_tags')) {
            wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')], 403);
            wp_die();
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        $new_term = isset($_POST['new_term']) ? sanitize_text_field(wp_unslash($_POST['new_term'])) : '';
        $merge_type = isset($_POST['merge_type']) ? sanitize_text_field(wp_unslash($_POST['merge_type'])) : 'different_name';
        $old_terms_input = isset($_POST['old_terms']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['old_terms'])) : [];

        if (
            !taxopress_current_user_can_for_taxonomy(
                $taxonomy,
                ['edit_terms', 'delete_terms', 'assign_terms']
            )
        ) {
            wp_send_json_error(['message' => __('Permission denied for this taxonomy.', 'simple-tags')], 403);
            wp_die();
        }

        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        $old_terms = [];
        $missing_terms = [];

        foreach ($old_terms_input as $term_name) {
            $term_name_clean = $extractTermName($term_name);
            $term = self::getExistingMergeTerm($taxonomy, $term_name);
            if ($term && !is_wp_error($term)) {
                $old_terms[] = $term_name;
            } elseif (!empty($term_name_clean)) {
                $missing_terms[] = $term_name_clean;
            }
        }

        $old_terms = array_values(array_unique($old_terms));
        $missing_terms = array_values(array_unique($missing_terms));

        if (!empty($missing_terms)) {
            wp_send_json_error([
                'code' => 'missing_terms',
                'missing_terms' => $missing_terms,
                'message' => sprintf(
                    /* translators: %s: comma-separated term names. */
                    __('These terms do not exist and cannot be merged: %s', 'simple-tags'),
                    implode(', ', $missing_terms)
                )
            ]);
            wp_die();
        }

        if (empty($old_terms)) {
            wp_send_json_error([
                'message' => __('Please enter a valid term. Merge cancelled.', 'simple-tags')
            ]);
            wp_die();
        }

        // Execute the merge
        $result = SimpleTags_Admin_Manage::mergeTerms($taxonomy, implode(',', $old_terms), $new_term, $merge_type);

        if ($result === true || (is_array($result) && !empty($result['success']))) {
            $response = ['message' => __('Merge successful', 'simple-tags')];

            if (is_array($result)) {
                $response = array_merge($response, $result);
            }

            wp_send_json_success($response);
        } else {
            global $wp_settings_errors;
            $errors = [];

            if (!empty($wp_settings_errors)) {
                foreach ($wp_settings_errors as $error) {
                    if (!empty($error['message'])) {
                        $errors[] = $error['message'];
                    }
                }
            }

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } elseif (is_array($result) && isset($result['message'])) {
                $errors[] = $result['message'];
            }

            wp_send_json_error(['message' => implode(' ', $errors)]);
        }

        wp_die();
    }

    /**
     * Method for remove tags
     *
     * @param string $taxonomy
     * @param string $post_type
     * @param string $tag
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function removeTerms($taxonomy = 'post_tag', $post_type = 'posts', $new = '')
    {
        $taxonomy = sanitize_key($taxonomy);
        if (!taxopress_current_user_can_for_taxonomy($taxonomy, 'assign_terms')) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('Permission denied for this taxonomy.', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        if (trim(str_replace(',', '', stripslashes($new))) == '') {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        // String to array
        $new_terms = explode(',', $new);

        // Remove empty element and trim
        $new_terms = array_filter($new_terms, '_delete_empty_element');

        // If new tag are empty => exit !
        if (empty($new_terms)) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No valid term specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        $counter = 0;
        if (count($new_terms) > 0) {
            foreach ((array) $new_terms as $term) {
                $term = get_term_by('name', sanitize_text_field($term), $taxonomy);
                if (empty($term) || !is_object($term)) {
                    continue;
                }

                $term = $term->term_id;

                $args = array(
                    'post_type'      => $post_type,
                    'posts_per_page' => 100,
                    'fields'         => 'ids',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => $taxonomy,
                            'field'    => 'id',
                            'terms'    => $term,
                        ),
                    ),
                );

                $post_ids = array();
                $page     = 1;
                do {
                    $args['paged'] = $page;
                    $batch         = get_posts($args);
                    $post_ids      = array_merge($post_ids, $batch);
                    $page++;
                } while (count($batch) === $args['posts_per_page']);

                foreach ($post_ids as $post_id) {
                    if (!current_user_can('edit_post', $post_id)) {
                        continue;
                    }
                    $remove = wp_remove_object_terms($post_id, $term, $taxonomy);
                    if ($remove) {
                        clean_object_term_cache($post_id, $taxonomy);
                        clean_term_cache($term, $taxonomy);
                        $counter++;
                    }
                }
            }

            if ($counter == 0) {
                add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('This term is not associated with any %1$s.', 'simple-tags'), SimpleTags_Admin::$post_type_name), 'error taxopress-notice');
            } else {
                add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Removed term(s) "%1$s" from %2$s %3$s', 'simple-tags'), $new, $counter, SimpleTags_Admin::$post_type_name), 'updated taxopress-notice');
            }
        } else { // Error
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Error. No enough terms specified.', 'simple-tags'), $old), 'error taxopress-notice');
        }

        return true;
    }

    /**
     * Method for rename tags
     *
     * @param string $taxonomy
     * @param string $old
     * @param string $new
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function renameTerms($taxonomy = 'post_tag', $old = '', $new = '')
    {
        $taxonomy = sanitize_key($taxonomy);
        if (
            !taxopress_current_user_can_for_taxonomy(
                $taxonomy,
                ['edit_terms', 'delete_terms', 'assign_terms']
            )
        ) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('Permission denied for this taxonomy.', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        // Helper function to extract term name (ignoring slug in brackets)
        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        if (trim(str_replace(',', '', stripslashes($new))) == '') {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No new term specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        // String to array
        $old_terms = explode(',', $old);
        $new_terms = explode(',', $new);

        // Clean up terms by removing slugs
        $old_terms = array_map($extractTermName, $old_terms);
        $new_terms = array_map($extractTermName, $new_terms);

        // Remove empty element and trim
        $old_terms = array_filter($old_terms, '_delete_empty_element');
        $new_terms = array_filter($new_terms, '_delete_empty_element');

        // If old/new tag are empty => exit !
        if (empty($old_terms) || empty($new_terms)) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No new/old valid term specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        $counter = 0;
        if (count($old_terms) === count($new_terms)) { // Rename only
            foreach ((array) $old_terms as $i => $old_tag) {
                $new_name = sanitize_text_field($new_terms[ $i ]);

                // Get term by name
                $term = get_term_by('name', sanitize_text_field($old_tag), $taxonomy);
                if (! $term) {
                    continue;
                }

                // Get objects from term ID
                $objects_id = get_objects_in_term($term->term_id, $taxonomy, array( 'fields' => 'all_with_object_id' ));

                // Create the new term
                if (! $term_info = term_exists($new_name, $taxonomy)) {
                    $term_info = wp_insert_term($new_name, $taxonomy);
                }

                // If default category, update the ID for new term...
                if ('category' == $taxonomy && $term->term_id == get_option('default_category')) {
                    update_option('default_category', $term_info['term_id']);
                    clean_term_cache($term_info['term_id'], $taxonomy);
                }

                // Delete old term
                wp_delete_term($term->term_id, $taxonomy);

                // Set objects to new term ! (Append no replace)
                foreach ((array) $objects_id as $object_id) {
                    if (!current_user_can('edit_post', $object_id)) {
                        continue;
                    }
                    wp_set_object_terms($object_id, $new_name, $taxonomy, true);
                }

                // Clean cache
                clean_object_term_cache($objects_id, $taxonomy);
                clean_term_cache($term->term_id, $taxonomy);

                // Increment
                $counter++;
            }

            if ($counter == 0) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('No term renamed.', 'simple-tags'), 'updated taxopress-notice');
            } else {
                add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Renamed term(s) "%1$s" to "%2$s"', 'simple-tags'), rtrim($old, ','), rtrim($new, ',')), 'updated taxopress-notice');
            }
        } else { // Error
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Error. No enough terms for rename.', 'simple-tags'), $old), 'error taxopress-notice');
        }

        return true;
    }

    /**
     * Method for delete a list of terms
     *
     * @param string $taxonomy
     * @param string $delete
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function deleteTermsByTermList($taxonomy = 'post_tag', $delete = '')
    {
        $taxonomy = sanitize_key($taxonomy);
        if (!taxopress_current_user_can_for_taxonomy($taxonomy, 'delete_terms')) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('Permission denied for this taxonomy.', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        if (trim(str_replace(',', '', stripslashes($delete))) == '') {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        // In array + filter
        $delete_terms = explode(',', $delete);
        $delete_terms = array_filter($delete_terms, '_delete_empty_element');

        // Delete tags
        $counter = 0;
        foreach ((array) $delete_terms as $term) {
            $term    = get_term_by('name', sanitize_text_field($term), $taxonomy);
            $term_id = (int) $term->term_id;

            if ($term_id != 0) {
                wp_delete_term($term_id, $taxonomy);
                clean_term_cache($term_id, $taxonomy);
                $counter++;
            }
        }

        if ($counter == 0) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term deleted.', 'simple-tags'), 'updated taxopress-notice');
        } else {
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('%1s term(s) deleted.', 'simple-tags'), $counter), 'updated taxopress-notice');
        }

        return true;
    }

    /**
     * Method for add terms for all or specified posts
     *
     * @param string $taxonomy
     * @param string $match
     * @param string $new
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function addMatchTerms($taxonomy = 'post_tag', $match = '', $new = '')
    {
        // Helper function to extract term name (ignoring slug in brackets)
        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        if (trim(str_replace(',', '', stripslashes($new))) == '') {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No new term(s) specified!', 'simple-tags'), 'error taxopress-notice');

            return false;
        }

        $match_terms = explode(',', $match);
        $new_terms   = explode(',', $new);

        // Clean up terms by removing slugs
        $match_terms = array_map($extractTermName, $match_terms);
        $new_terms   = array_map($extractTermName, $new_terms);

        $match_terms = array_filter($match_terms, '_delete_empty_element');
        $new_terms   = array_filter($new_terms, '_delete_empty_element');

        $counter = 0;
        if (! empty($match_terms)) { // Match and add
            // Get terms ID from old match names
            $terms_id = array();
            foreach ((array) $match_terms as $match_term) {
                $term       = get_term_by('name', sanitize_text_field($match_term), $taxonomy);
                $terms_id[] = (int) $term->term_id;
            }

            // Get object ID with terms ID
            $objects_id = get_objects_in_term($terms_id, $taxonomy, array( 'fields' => 'all_with_object_id' ));

            // Add new tags for specified post
            foreach ((array) $objects_id as $object_id) {
                wp_set_object_terms($object_id, $new_terms, $taxonomy, true); // Append terms
                $counter++;
            }

            // Clean cache
            clean_object_term_cache($objects_id, $taxonomy);
            clean_term_cache($terms_id, $taxonomy);
        } else { // Add for all posts
            // Get all posts ID
            global $wpdb;
            $post_type = sanitize_key(SimpleTags_Admin::$post_type);
            if (!post_type_exists($post_type)) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Invalid post type.', 'simple-tags'), 'error taxopress-notice');
                return false;
            }
            SimpleTags_Admin::$post_type = $post_type;
            $objects_id = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE (post_status = 'publish' OR post_status = 'inherit') AND post_type = %s",
                    $post_type
                )
            );

            // Add new tags for all posts
            foreach ((array) $objects_id as $object_id) {
                wp_set_object_terms($object_id, $new_terms, $taxonomy, true); // Append terms
                clean_object_term_cache($object_id, $taxonomy);
                clean_term_cache($new_terms, $taxonomy);
                $counter++;
            }

            // Clean cache
            clean_object_term_cache($objects_id, $taxonomy);
        }

        if ($counter == 0) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term added.', 'simple-tags'), 'updated taxopress-notice');
        } else {
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Term(s) added to %1s %2s.', 'simple-tags'), $counter, SimpleTags_Admin::$post_type_name), 'updated taxopress-notice');
        }

        return true;
    }

    /**
     * Delete terms when counter if inferior to a specific number
     *
     * @param string $taxonomy
     * @param integer $number
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function removeRarelyUsed($taxonomy = 'post_tag', $number = 0)
    {
        global $wpdb;

        if ((int) $number > 100) {
            wp_die('Tcheater ?');
        }

        // Get terms with counter inferior to...
        $terms_id = $wpdb->get_col($wpdb->prepare("SELECT term_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s AND count < %d", $taxonomy, (int) $number));

        // Delete terms
        $counter = 0;
        foreach ((array) $terms_id as $term_id) {
            if ($term_id != 0) {
                wp_delete_term($term_id, $taxonomy);
                clean_term_cache($term_id, $taxonomy);
                $counter++;
            }
        }

        if ($counter == 0) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term deleted.', 'simple-tags'), 'updated taxopress-notice');
        } else {
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('%1s term(s) deleted.', 'simple-tags'), $counter), 'updated taxopress-notice');
        }

        return true;
    }

    public function handle_taxopress_check_delete_terms_ajax()
    {

        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'st-admin-js')) {
            wp_send_json_error(array('message' => __('Nonce verification failed.', 'simple-tags')));
            wp_die();
        }

        global $wpdb;

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : 'post_tag';
        $number = isset($_POST['number']) ? intval($_POST['number']) : 0;

        $taxonomy_object = get_taxonomy($taxonomy);
        if (
            !current_user_can('simple_tags')
            || !$taxonomy_object
            || empty($taxonomy_object->cap->manage_terms)
            || !current_user_can($taxonomy_object->cap->manage_terms)
        ) {
            wp_send_json_error(array('message' => __('Permission denied.', 'simple-tags')), 403);
        }

        if ((int) $number > 100) {
            wp_die('Tcheater ?');
        }

        if ($number > 0) {
            $terms = $wpdb->get_col($wpdb->prepare(
                "
                SELECT term_id FROM $wpdb->term_taxonomy
                WHERE taxonomy = %s AND count < %d",
                $taxonomy,
                (int) $number
            ));

            $term_count = count($terms);

            if ($term_count > 0) {
                wp_send_json_success(array('message' => sprintf(__('%d terms will be deleted.', 'simple-tags'), $term_count)));
            } else {
                wp_send_json_error(array('message' => __('No terms will be deleted.', 'simple-tags')));
            }
        } else {
            wp_send_json_error(array('message' => __('Invalid number specified.', 'simple-tags')));
        }

        wp_die();
    }

    /**
     * Method for removing terms from all or specified posts
     *
     * @param string $taxonomy
     * @param string $match
     * @param string $remove
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    public static function removeMatchTerms($taxonomy = 'post_tag', $match = '', $remove = '')
    {
        // Helper function to extract term name (ignoring slug in brackets)
        $extractTermName = function ($term) {
            return trim(preg_replace('/\s*\(.*?\)$/', '', $term));
        };

        if (trim(str_replace(',', '', stripslashes($remove))) == '') {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No term(s) specified for removal!', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        $match_terms  = explode(',', $match);
        $remove_terms = explode(',', $remove);

        // Clean up terms by removing slugs
        $match_terms  = array_map($extractTermName, $match_terms);
        $remove_terms = array_map($extractTermName, $remove_terms);

        $match_terms  = array_filter($match_terms, '_delete_empty_element');
        $remove_terms = array_filter($remove_terms, '_delete_empty_element');

        // Arrays to track if terms entered is valid
        $valid_remove_terms = array();
        $invalid_remove_terms = array();

        foreach ((array) $remove_terms as $remove_term) {
            $term = get_term_by('name', sanitize_text_field($remove_term), $taxonomy);
            if ($term) {
                $valid_remove_terms[] = $remove_term; // Add to valid list if the term exists
            } else {
                $invalid_remove_terms[] = $remove_term; // Collect invalid remove terms
            }
        }

        if (empty($valid_remove_terms)) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('Term(s) does not exist.', 'simple-tags'), 'error taxopress-notice');
            return false;
        }

        $counter = 0;
        if (!empty($match_terms)) {
            // Get terms ID from match terms
            $terms_id = array();
            foreach ((array) $match_terms as $match_term) {
                $term = get_term_by('name', sanitize_text_field($match_term), $taxonomy);
                if ($term) {
                    $terms_id[] = (int) $term->term_id;
                }
            }

            // Get object ID with terms ID
            $objects_id = get_objects_in_term($terms_id, $taxonomy, array('fields' => 'all_with_object_id'));

            // Remove specified terms from matched posts
            foreach ((array) $objects_id as $object_id) {
                wp_remove_object_terms($object_id, $valid_remove_terms, $taxonomy);
                $counter++;
            }

            clean_object_term_cache($objects_id, $taxonomy);
            clean_term_cache($terms_id, $taxonomy);
        } else {
            // Get all posts if no match terms were provided
            global $wpdb;
            $post_type = sanitize_key(SimpleTags_Admin::$post_type);
            if (!post_type_exists($post_type)) {
                add_settings_error(__CLASS__, __CLASS__, esc_html__('Invalid post type.', 'simple-tags'), 'error taxopress-notice');
                return false;
            }
            SimpleTags_Admin::$post_type = $post_type;
            $objects_id = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE (post_status = 'publish' OR post_status = 'inherit') AND post_type = %s",
                    $post_type
                )
            );

            // Remove valid terms for all posts
            foreach ((array) $objects_id as $object_id) {
                wp_remove_object_terms($object_id, $valid_remove_terms, $taxonomy);
                clean_object_term_cache($object_id, $taxonomy);
                clean_term_cache($valid_remove_terms, $taxonomy);
                $counter++;
            }

            clean_object_term_cache($objects_id, $taxonomy);
        }

        if ($counter == 0) {
            add_settings_error(__CLASS__, __CLASS__, esc_html__('No matching term found.', 'simple-tags'), 'updated taxopress-notice');
        } else {
            add_settings_error(__CLASS__, __CLASS__, sprintf(esc_html__('Term(s) removed from %1s %2s.', 'simple-tags'), $counter, SimpleTags_Admin::$post_type_name), 'updated taxopress-notice');
        }

        return true;
    }

    public static function handle_taxopress_autocomplete_terms()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'st-admin-js')) {
            wp_send_json_error(['message' => __('Nonce verification failed.', 'simple-tags')]);
            wp_die();
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : 'post_tag';
        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';

        $taxonomy_object = get_taxonomy($taxonomy);
        if (
            !current_user_can('simple_tags')
            || !$taxonomy_object
            || empty($taxonomy_object->cap->manage_terms)
            || !current_user_can($taxonomy_object->cap->manage_terms)
        ) {
            wp_send_json_error(['message' => __('Permission denied.', 'simple-tags')], 403);
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'name__like' => $term,
            'hide_empty' => false,
            'number' => (int) apply_filters('taxopress_autocomplete_terms_limit', 50),
            'update_term_meta_cache' => false,
        ]);

        $results = [];
        foreach ($terms as $term) {
            $results[] = array(
                'name' => html_entity_decode(
                    $term->name,
                    ENT_QUOTES,
                    get_bloginfo('charset')
                ),
                'slug' => $term->slug,
            );
        }

        wp_send_json($results);
        wp_die();
    }


    /**
     * Method for edit one or more terms slug
     *
     * @param string $taxonomy
     * @param string $names
     * @param string $slugs
     *
     * @return boolean
     * @author WebFactory Ltd
     */
    /*
    public static function editTermSlug( $taxonomy = 'post_tag', $names = '', $slugs = '') {
        if ( trim( str_replace(',', '', stripslashes($slugs)) ) == '' ) {
            add_settings_error( __CLASS__, __CLASS__, esc_html__('No new slug(s) specified!', 'simple-tags'), 'error' );
            return false;
        }

        $match_names = explode(',', $names);
        $new_slugs = explode(',', $slugs);

        $match_names = array_filter($match_names, '_delete_empty_element');
        $new_slugs = array_filter($new_slugs, '_delete_empty_element');

        if ( count($match_names) != count($new_slugs) ) {
            add_settings_error( __CLASS__, __CLASS__, esc_html__('Terms number and slugs number isn\'t the same!', 'simple-tags'), 'error' );
            return false;
        } else {
            $counter = 0;
            foreach ( (array) $match_names as $i => $match_name ) {
                // Sanitize slug + Escape
                $new_slug = sanitize_title($new_slugs[$i]);

                // Get term by name
                $term = get_term_by('name', $match_name, $taxonomy);
                if ( !$term ) {
                    continue;
                }

                // Increment
                $counter++;

                // Update term
                wp_update_term($term->term_id, $taxonomy, array('slug' => $new_slug));

                // Clean cache
                clean_term_cache($term->term_id, $taxonomy);
            }
        }

        if ( $counter == 0  ) {
            add_settings_error( __CLASS__, __CLASS__, esc_html__('No slug edited.', 'simple-tags'), 'updated' );
        } else {
            add_settings_error( __CLASS__, __CLASS__, sprintf(esc_html__('%s slug(s) edited.', 'simple-tags'), $counter), 'updated' );
        }

        return true;
    }
    */


    /** Singleton instance */
    public static function get_instance()
    {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
?>
