<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Legacy TaxoPress file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Admin_ClickTags
{
    /**
     * Constructor
     *
     * @return void
     * @author WebFactory Ltd
     */
    public function __construct()
    {
        // Ajax action, JS Helper and admin action
        add_action('wp_ajax_simpletags', array( __CLASS__, 'ajax_check' ));

        if (1 === (int) SimpleTags_Plugin::get_option_value('active_suggest_terms')) {
            // Box for post/page
            //add_action( 'admin_head', array( __CLASS__, 'admin_head' ), 1 );
            // Javascript
            //add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ), 11 );
        }
    }

    /**
     * Init somes JS and CSS need for this feature
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function admin_enqueue_scripts()
    {
        global $pagenow;

        $click_terms = taxopress_current_post_suggest_terms('existing_terms');

        if (!is_array($click_terms)) {
            return;
        }

        wp_register_script(
            'st-helper-click-tags',
            STAGS_URL . '/assets/js/helper-click-tags.js',
            array(
                'jquery',
                'st-helper-add-tags',
            ),
            STAGS_VERSION,
            true
        );

        $post_type_data = get_post_type_object(get_post_type());
        $post_type_name = is_object($post_type_data) && isset($post_type_data->labels->singular_name) ? strtolower($post_type_data->labels->singular_name) : 'post';
        $post_taxonomies = get_object_taxonomies(get_post_type());

        //add taxonomy
        $click_tags_taxonomy = '
        <div class="option">
        <label>' . esc_html__('Taxonomy', 'simple-tags') . '</label><br />
        <select class="st-post-taxonomy-select click_tags_taxonomy" name="click_tags_taxonomy">';
        foreach ($post_taxonomies as $_taxonomy) {
            $_taxonomy = get_taxonomy($_taxonomy);
            if ($_taxonomy->name === 'author') {
                continue;
            }
            if (!isset($_taxonomy->public) || (isset($_taxonomy->public) && (int)$_taxonomy->public === 0)) {
                continue;
            }

            if (is_array($click_terms['taxonomy']) && in_array($_taxonomy->name, $click_terms['taxonomy'])) {
                $click_tags_taxonomy .= '<option value="' . $_taxonomy->name . '" selected="selected">' . $_taxonomy->labels->name . '</option>';
            } elseif (!is_array($click_terms['taxonomy']) && $_taxonomy->name === $click_terms['taxonomy']) { // backward compatibility
                $click_tags_taxonomy .= '<option value="' . $_taxonomy->name . '" selected="selected">' . $_taxonomy->labels->name . '</option>';
            } else {
                $click_tags_taxonomy .= '<option value="' . $_taxonomy->name . '">' . $_taxonomy->labels->name . '</option>';
            }
        }
        $click_tags_taxonomy .= '</select>
        </div>';

        //add method
        $click_tags_methods = ['name' => esc_html__('Name', 'simple-tags'), 'count' => esc_html__('Counter', 'simple-tags'), 'random' => esc_html__('Random', 'simple-tags')];
        $click_tags_method = '
        <div class="option">
        <label>' . __('Method for choosing terms', 'simple-tags') . '</label><br />
        <select class="click_tags_method" name="click_tags_method">';
        foreach ($click_tags_methods as $option => $label) {
            $selected = ($option === $click_terms['orderby']) ? 'selected="selected"' : '';
            $click_tags_method .= '<option value="' . $option . '" ' . $selected . '>' . $label . '</option>';
        }
        $click_tags_method .= '</select>
        </div>';

        //add order
        $click_tags_orders = ['asc' =>  esc_html__('Ascending', 'simple-tags'), 'desc' => esc_html__('Descending', 'simple-tags')];
        $click_tags_order = '
        <div class="option">
        <label>' . __('Ordering for choosing terms', 'simple-tags') . '</label><br />
        <select class="click_tags_order" name="click_tags_order">';
        foreach ($click_tags_orders as $option => $label) {
            $selected = ($option === $click_terms['order']) ? 'selected="selected"' : '';
            $click_tags_order .= '<option value="' . $option . '" ' . $selected . '>' . $label . '</option>';
        }
        $click_tags_order .= '</select>
        </div>';

        //add limit
        $click_tags_limit = '
        <div class="option">
        <label for="click_tags_limit">' . __('Maximum terms', 'simple-tags') . '</label><br />
        <input type="number" class="click_tags_limit" id="click_tags_limit" name="click_tags_limit" value="' . $click_terms['number'] . '">
        </div>';

        //add searchbox
        $click_tags_search = '
        <div class="option">
        <label for="click_tags_search">Search</label><br />
        <input name="click_tags_search" id="click_tags_search" type="text" class="click-tag-search-box" placeholder="' . __('Start typing to search', 'simple-tags') . '" size="26" autocomplete="off">
        </div>';

        //create tags search data
        $click_tags_options = '<div class="clicktags-search-wrapper">' . $click_tags_search . ' ' . $click_tags_taxonomy . ' ' . $click_tags_method . ' ' . $click_tags_order . ' ' . $click_tags_limit . '</div>';

        //metabox edit line
        if (current_user_can('admin_simple_tags')) {
            $click_term_edit = '<span class="edit-suggest-term-metabox">
			' . sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    [
                        'page'                   => 'st_suggestterms',
                        'add'                    => 'new_item',
                        'action'                 => 'edit',
                        'taxopress_suggestterms' => $click_terms['ID'],
                    ],
                    admin_url('admin.php')
                ),
                __('Edit this metabox', 'simple-tags')
            )
            . '
			</span>';
        } else {
            $click_term_edit = '';
        }
        wp_localize_script(
            'st-helper-click-tags',
            'stHelperClickTagsL10n',
            array(
                'show_txt'    => esc_html__('Click to display tags', 'simple-tags'),
                'arial_label' => esc_html__('suggested terms', 'simple-tags'),
                'hide_txt'    => sprintf(esc_html__('Click terms to add them to this %s', 'simple-tags'), $post_type_name),
                'state'       => 'show',
                'search_icon' => STAGS_URL . '/assets/images/indicator.gif',
                'nonce'       => wp_create_nonce('st-admin-js'),
                'search_box'  => '<input type="text" class="click-tag-search-box" placeholder="' . __('Start typing to search', 'simple-tags') . '" size="26" autocomplete="off">',
                'click_tags_options'  => $click_tags_options,
                'edit_metabox_link'   => $click_term_edit,
            )
        );

        // Helper for post type
        wp_enqueue_script('st-helper-click-tags');
    }

    /**
     * Register metabox
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function admin_head()
    {

        $click_terms = taxopress_current_post_suggest_terms('existing_terms');

        if (!is_array($click_terms)) {
            return;
        }
        add_meta_box(
            'st-clicks-tags',
            __('Show existing terms', 'simple-tags'),
            array(
                __CLASS__,
                'metabox',
            ),
            get_post_type(),
            'advanced',
            'core'
        );
    }

    /**
     * Put default HTML for people without JS
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function metabox()
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo SimpleTags_Admin::getDefaultContentBox();
    }

    /**
     * Ajax Dispatcher
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function ajax_check()
    {

        if (isset($_GET['stags_action']) && 'click_tags' === $_GET['stags_action']) {
            self::ajax_click_tags();
        }
    }

    /**
     * Display a span list for click tags
     *
     * @return void
     * @author WebFactory Ltd
     */
    public static function ajax_click_tags()
    {
        if (!check_ajax_referer('st-admin-js', 'nonce', false)) {
            wp_die(esc_html__('Security check failed.', 'simple-tags'), '', ['response' => 403]);
        }

        $taxonomy = isset($_GET['click_tags_taxonomy']) ? sanitize_key(wp_unslash($_GET['click_tags_taxonomy'])) : 'post_tag';
        $taxonomy_object = get_taxonomy($taxonomy);
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (
            !$taxonomy_object
            || empty($taxonomy_object->cap->assign_terms)
            || !current_user_can($taxonomy_object->cap->assign_terms)
            || ($post_id > 0 && !current_user_can('edit_post', $post_id))
        ) {
            wp_die(esc_html__('Permission denied.', 'simple-tags'), '', ['response' => 403]);
        }

        status_header(200); // Send good header HTTP
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        if (0 === (int) wp_count_terms(array( 'taxonomy' => $taxonomy, 'hide_empty' => false ))) { // No tags to suggest
            echo '<p>' . esc_html__('No terms in your WordPress database.', 'simple-tags') . '</p>';
            exit();
        }

        // Prepare search
        $search  = (isset($_GET['q'])) ? trim(stripslashes(sanitize_text_field(wp_unslash($_GET['q'])))) : '';
        if (isset($_GET['click_tags_method']) && !empty($_GET['click_tags_method'])) {
            $order_click_tags = (sanitize_text_field(wp_unslash($_GET['click_tags_method'])) === 'random') ? sanitize_text_field(wp_unslash($_GET['click_tags_method'])) : sanitize_text_field(wp_unslash($_GET['click_tags_method'])) . '-' . sanitize_text_field(wp_unslash($_GET['click_tags_order']));
        } else {
            // Order tags before selection (count-asc/count-desc/name-asc/name-desc/random)
            $order_click_tags = 'random';
        }
        switch ($order_click_tags) {
            case 'count-asc':
                $order_by = 'tt.count';
                $order    = 'ASC';
                break;
            case 'random':
                $order_by = 'RAND()';
                $order    = '';
                break;
            case 'count-desc':
                $order_by = 'tt.count';
                $order    = 'DESC';
                break;
            case 'name-desc':
                $order_by = 't.name';
                $order    = 'DESC';
                break;
            default: // name-asc
                $order_by = 't.name';
                $order    = 'ASC';
                break;
        }

        $term_limit =  isset($_GET['click_tags_limit']) ? (int)$_GET['click_tags_limit'] : 100;

        if ($term_limit > 0) {
            $limit = 'LIMIT 0, ' . $term_limit;
        } else {
            $limit = '';
        }

        // Get all terms, or filter with search
        $terms = SimpleTags_Admin::getTermsForAjax($taxonomy, $search, $order_by, $order, $limit);
        if (empty($terms)) {
            echo '<p>' . esc_html__('No results from your WordPress database.', 'simple-tags') . '</p>';
            exit();
        }

        // Get terms for current post
        $post_terms = array();
        if ($post_id > 0) {
            $post_terms = wp_get_post_terms($post_id, $taxonomy, array( 'fields' => 'ids' ));
        }

        foreach ((array) $terms as $term) {
            $class_current = in_array($term->term_id, $post_terms) ? 'used_term' : '';
            echo '<span data-term_id="' . esc_attr($term->term_id) . '" data-taxonomy="' . esc_attr($taxonomy) . '" class="local ' . esc_attr($taxonomy) . ' ' . esc_attr($class_current) . '" tabindex="0" role="button" aria-pressed="false">' . esc_html(stripslashes($term->name)) . '</span>' . "\n";
        }
        echo '<div class="clear"></div>';

        exit();
    }
}
