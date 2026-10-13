<?php

/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2021 PublishPress
 *
 * WordPressReviews is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * WordPressReviews is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 * It includes:
 * - Multiple trigger groups which can be ordered by priority.
 * - Multiple triggers per group.
 * - Customizable messaging per trigger.
 * - Link to review page.
 * - Request reviews on a per-user basis rather than per site.
 * - Allows each user to dismiss it until later or permanently seamlessly via AJAX.
 * - Integrates with attached tracking server to keep anonymous records of each trigger's effectiveness.
 *   - Tracking Server API: https://gist.github.com/danieliser/0d997532e023c46d38e1bdfd50f38801
 *
 * Original Author: danieliser
 * Original Author URL: https://danieliser.com
 * URL: https://github.com/danieliser/WP-Product-In-Dash-Review-Requests
 */

namespace PublishPress\WordPressReviews;

use Exception;

if (class_exists('PublishPress\\WordPressReviews\\ReviewsController')) {
    return;
}

/**
 * Class ReviewsController
 *
 * @package PublishPress\WordPressReviews
 */
class ReviewsController
{
    const VERSION = '1.2.1';

    /**
     * @var string
     */
    private $pluginSlug;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @var array
     */
    private $metaMap;

    /**
     * @var string
     */
    private $iconUrl;

    /**
     * @var bool
     */
    private static $textDomainLoaded = false;

    /**
     * @param string $pluginSlug
     * @param string $pluginName
     * @param string $iconUrl
     */
    public function __construct($pluginSlug, $pluginName, $iconUrl = '')
    {
        $this->pluginSlug = $pluginSlug;
        $this->pluginName = $pluginName;
        $this->iconUrl = esc_url_raw($iconUrl);

        /**
         * Filter to replace the meta map with options, filters and actions names.
         *
         * @param array
         *
         * @return array
         */
        $this->metaMap = apply_filters(
            "{$pluginSlug}_wp_reviews_meta_map",
            [
                'action_ajax_handler' => "{$this->pluginSlug}_action",
                'option_installed_on' => "{$this->pluginSlug}_wp_reviews_installed_on",
                'nonce_action' => "{$this->pluginSlug}_wp_reviews_action",
                'user_meta_dismissed_triggers' => "_{$this->pluginSlug}_wp_reviews_dismissed_triggers",
                'user_meta_last_dismissed' => "_{$this->pluginSlug}_wp_reviews_last_dismissed",
                'user_meta_already_did' => "_{$this->pluginSlug}_wp_reviews_already_did",
                'filter_triggers' => "{$this->pluginSlug}_wp_reviews_triggers",
            ]
        );

        /**
         * Legacy filter to replace the meta map with options, filters and actions names.
         *
         * @param array
         * @return array
         * @deprecated 1.1.9
         *
         */
        $this->metaMap = apply_filters(
            "publishpress_wp_reviews_meta_map_{$this->pluginSlug}",
            $this->metaMap
        );

        add_action('admin_enqueue_scripts', [$this, 'enqueueStyle']);
    }

    /**
     * Load the text domain for translations.
     */
    private function loadTextDomain()
    {
        if (self::$textDomainLoaded) {
            return;
        }

        $mofile = __DIR__ . '/languages/publishpress-wordpress-reviews-' . determine_locale() . '.mo';

        load_textdomain('publishpress-wordpress-reviews', $mofile);
        self::$textDomainLoaded = true;
    }

    /**
     * Initialize the library.
     */
    public function init()
    {
        $this->loadTextDomain();
        $this->addHooks();
    }

    /**
     * Hook into relevant WP actions.
     */
    private function addHooks()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action("wp_ajax_{$this->metaMap['action_ajax_handler']}", [$this, 'ajaxHandler']);
        }

        if ($this->screenIsAllowedToDisplayNotice()) {
            $this->installationPath();
            add_action('admin_notices', [$this, 'renderAdminNotices']);
            add_action('network_admin_notices', [$this, 'renderAdminNotices']);
            add_action('user_admin_notices', [$this, 'renderAdminNotices']);
        }
    }

    /**
     * @return bool
     */
    private function screenIsAllowedToDisplayNotice()
    {
        $displayNotice = is_admin();

        /**
         * Deprecated filter to specify a custom conditional to display or not the notice.
         *
         * @param bool
         * @return bool
         * @deprecated 1.1.9
         *
         */
        $displayNotice = apply_filters(
            "publishpress_wp_reviews_display_banner_{$this->pluginSlug}",
            $displayNotice
        );

        /**
         * Filter to specify a custom conditional to display or not the notice.
         *
         * @param bool
         *
         * @return bool
         */
        $displayNotice = apply_filters("{$this->pluginSlug}_wp_reviews_allow_display_notice", $displayNotice);

        if (! $this->currentUserIsAdministrator()) {
            $displayNotice = false;
        }

        return $displayNotice;
    }

    private function currentUserIsAdministrator()
    {
        $currentUser = get_current_user_id();
        $currentUser = get_user_by('ID', $currentUser);

        if (empty($currentUser) || ! is_object($currentUser) && is_wp_error($currentUser)) {
            return false;
        }

        return in_array('administrator', $currentUser->roles);
    }

    /**
     * Get the installation date for comparisons. Sets the date to now if none is found.
     *
     * @return false|string
     */
    public function installationPath()
    {
        $installationPath = get_option($this->metaMap['option_installed_on'], false);

        if (! $installationPath) {
            $installationPath = current_time('mysql');
            update_option($this->metaMap['option_installed_on'], $installationPath);
        }

        return $installationPath;
    }

    /**
     * The function called by the ajax request.
     */
    public function ajaxHandler()
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_key($_REQUEST['nonce']) : '';

        if (! wp_verify_nonce($nonce, $this->metaMap['nonce_action'])) {
            wp_send_json_error();
        }

        $args = [
            'group' => isset($_REQUEST['group']) ? sanitize_text_field($_REQUEST['group']) : $this->getTriggerGroup(),
            'code' => isset($_REQUEST['code']) ? sanitize_text_field($_REQUEST['code']) : $this->getTriggerCode(),
            'priority' => isset($_REQUEST['priority']) ? intval($_REQUEST['priority']) : $this->getCurrentTrigger('priority'),
            'reason' => isset($_REQUEST['reason']) ? sanitize_key($_REQUEST['reason']) : 'maybe_later',
        ];

        try {
            $userId = get_current_user_id();

            $dismissedTriggers = $this->getDismissedTriggerGroups();
            $dismissedTriggers[$args['group']] = (int)$args['priority'];

            update_user_meta($userId, $this->metaMap['user_meta_dismissed_triggers'], $dismissedTriggers);
            update_user_meta($userId, $this->metaMap['user_meta_last_dismissed'], current_time('mysql'));

            switch ($args['reason']) {
                case 'maybe_later':
                    update_user_meta($userId, $this->metaMap['user_meta_last_dismissed'], current_time('mysql'));
                    break;
                case 'am_now':
                case 'already_did':
                    $this->setUserAlreadyDid($userId);
                    break;
            }

            wp_send_json_success();
        } catch (Exception $e) {
            wp_send_json_error($e);
        }
    }

    /**
     * Get the trigger group.
     *
     * @return int|string
     */
    private function getTriggerGroup()
    {
        static $selected;

        if (! isset($selected)) {
            $selected = [];
        }

        if (! array_key_exists($this->pluginSlug, $selected)) {
            $dismissedTriggers = $this->getDismissedTriggerGroups();

            $triggers = $this->getTriggers();

            foreach ($triggers as $g => $group) {
                foreach ($group['triggers'] as $trigger) {
                    if (
                        ! in_array(
                            false,
                            $trigger['conditions']
                        ) && (empty($dismissedTriggers[$g]) || $dismissedTriggers[$g] < $trigger['priority'])
                    ) {
                        $selected[$this->pluginSlug] = $g;
                        break;
                    }
                }

                if (array_key_exists($this->pluginSlug, $selected)) {
                    break;
                }
            }
        }

        return $selected[$this->pluginSlug];
    }

    /**
     * Returns an array of dismissed trigger groups.
     *
     * Array contains the group key and highest priority trigger that has been shown previously for each group.
     *
     * $return = array(
     *   'group1' => 20
     * );
     *
     * @return array|mixed
     */
    private function getDismissedTriggerGroups()
    {
        $userId = get_current_user_id();

        $dismissedTriggers = get_user_meta($userId, $this->metaMap['user_meta_dismissed_triggers'], true);

        if (! $dismissedTriggers) {
            $dismissedTriggers = [];
        }

        return $dismissedTriggers;
    }

    /**
     * Gets a list of triggers.
     *
     * @param null $group
     * @param null $code
     *
     * @return bool|mixed|void
     */
    private function getTriggers($group = null, $code = null)
    {
        static $triggers;

        if (! isset($triggers)) {
            $triggers = [];
        }

        if (! array_key_exists($this->pluginSlug, $triggers)) {
            $timeMessage = __(
                'Hey, you\'ve been using %1$s for %2$s on your site. We hope the plugin has been useful. Please could you quickly leave a review on WordPress.org? It really does help to keep %1$s growing.',
                'publishpress-wordpress-reviews'
            );

            $triggers[$this->pluginSlug] = apply_filters(
                $this->metaMap['filter_triggers'],
                [
                    'time_installed' => [
                        'triggers' => [
                            'one_week' => [
                                'message' => sprintf($timeMessage, $this->pluginName, __('1 week', 'publishpress-wordpress-reviews')),
                                'conditions' => [
                                    strtotime($this->installationPath() . ' +1 week') < time(),
                                ],
                                'link' => "https://wordpress.org/support/plugin/{$this->pluginSlug}/reviews/",
                                'priority' => 10,
                            ],
                            'one_month' => [
                                'message' => sprintf($timeMessage, $this->pluginName, __('1 month', 'publishpress-wordpress-reviews')),
                                'conditions' => [
                                    strtotime($this->installationPath() . ' +1 month') < time(),
                                ],
                                'link' => "https://wordpress.org/support/plugin/{$this->pluginSlug}/reviews/",
                                'priority' => 20,
                            ],
                            'three_months' => [
                                'message' => sprintf(
                                    $timeMessage,
                                    $this->pluginName,
                                    __('3 months', 'publishpress-wordpress-reviews')
                                ),
                                'conditions' => [
                                    strtotime($this->installationPath() . ' +3 months') < time(),
                                ],
                                'link' => "https://wordpress.org/support/plugin/{$this->pluginSlug}/reviews/",
                                'priority' => 30,
                            ],
                        ],
                        'priority' => 10,
                    ],
                ]
            );

            // Sort Groups
            uasort($triggers[$this->pluginSlug], [$this, 'rsortByPriority']);

            // Sort each groups triggers.
            foreach ($triggers[$this->pluginSlug] as $v) {
                uasort($v['triggers'], [$this, 'rsortByPriority']);
            }
        }

        if (isset($group)) {
            if (! array_key_exists($this->pluginSlug, $triggers)
                || ! array_key_exists($group, $triggers[$this->pluginSlug])) {
                return false;
            }

            if (! isset($code)) {
                $return = $triggers[$this->pluginSlug][$group];
            } elseif (array_key_exists($code, $triggers[$this->pluginSlug][$group]['triggers'])) {
                $return = $triggers[$this->pluginSlug][$group]['triggers'][$code];
            } else {
                $return = false;
            }

            return $return;
        }

        return $triggers[$this->pluginSlug];
    }

    /**
     * @return int|string
     */
    private function getTriggerCode()
    {
        static $selected;

        if (! isset($selected)) {
            $selected = [];
        }

        if (! array_key_exists($this->pluginSlug, $selected)) {
            $dismissedTriggers = $this->getDismissedTriggerGroups();

            foreach ($this->getTriggers() as $g => $group) {
                foreach ($group['triggers'] as $t => $trigger) {
                    if (
                        ! in_array(
                            false,
                            $trigger['conditions']
                        ) && (empty($dismissedTriggers[$g]) || $dismissedTriggers[$g] < $trigger['priority'])
                    ) {
                        $selected[$this->pluginSlug] = $t;
                        break;
                    }
                }

                if (array_key_exists($this->pluginSlug, $selected)) {
                    break;
                }
            }
        }

        if (! array_key_exists($this->pluginSlug, $selected)) {
            return false;
        }

        return $selected[$this->pluginSlug];
    }

    /**
     * @param null $key
     *
     * @return bool|mixed|void
     */
    private function getCurrentTrigger($key = null)
    {
        $group = $this->getTriggerGroup();
        $code = $this->getTriggerCode();

        if (! $group || ! $code) {
            return false;
        }

        $trigger = $this->getTriggers($group, $code);

        if (empty($key)) {
            $return = $trigger;
        } elseif (array_key_exists($key, $trigger)) {
            $return = $trigger[$key];
        } else {
            $return = false;
        }

        return $return;
    }

    /**
     * @param $userId
     */
    private function setUserAlreadyDid($userId)
    {
        update_user_meta($userId, $this->metaMap['user_meta_already_did'], true);
    }

    public function enqueueStyle()
    {
        if (! $this->screenIsAllowedToDisplayNotice()) {
            return;
        }

        wp_register_style('publishpress_wordpress_reviews_style', false);
        wp_enqueue_style('publishpress_wordpress_reviews_style');
        wp_add_inline_style(
            'publishpress_wordpress_reviews_style',
            "
            .{$this->pluginSlug}-wp-reviews-notice .button,
            .{$this->pluginSlug}-wp-reviews-notice p {
                font-size: 15px;
            }

            .{$this->pluginSlug}-wp-reviews-notice .button:not(.notice-dismiss) {
                border-width: 1px;
            }

            .{$this->pluginSlug}-wp-reviews-notice .button.button-primary {
                background-color: #655897;
                border-color: #3d355c;
                color: #fff;
            }

            .{$this->pluginSlug}-wp-reviews-notice .notice-icon {
                float: right;
                height: 110px;
                margin-top: 10px;
                margin-left: 10px;
            }

            @media (min-width:1000px) {
                .{$this->pluginSlug}-wp-reviews-notice .notice-icon {
                    height: 90px;
                }
            }

            @media (min-width:1700px) {
                .{$this->pluginSlug}-wp-reviews-notice .notice-icon {
                    height: 70px;
                }
            }
            "
        );
    }

    private function enqueueScript()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

        wp_enqueue_script(
            'publishpress-wordpress-reviews',
            plugins_url("assets/js/reviews{$suffix}.js", __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );
    }

    /**
     * Render admin notices if available.
     */
    public function renderAdminNotices()
    {
        if ($this->hideNotices()) {
            return;
        }

        $group = $this->getTriggerGroup();
        $code = $this->getTriggerCode();
        $priority = $this->getCurrentTrigger('priority');
        $trigger = $this->getCurrentTrigger();

        $this->enqueueScript();
        ?>
        <div class="notice notice-success is-dismissible pp-wordpress-review-config <?php
        echo esc_attr("$this->pluginSlug-wp-reviews-notice"); ?>"
            data-action="<?php echo esc_attr($this->metaMap['action_ajax_handler']); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce($this->metaMap['nonce_action'])); ?>"
            data-group="<?php echo esc_attr($group); ?>"
            data-code="<?php echo esc_attr($code); ?>"
            data-priority="<?php echo esc_attr($priority); ?>">
            <?php
            if (! empty($this->iconUrl)) : ?>
                <img src="<?php
                echo $this->iconUrl; ?>" class="notice-icon" alt="<?php
                echo $this->pluginName; ?> logo"/>
            <?php
            endif; ?>

            <p><?php
                echo $trigger['message']; ?></p>
            <p>
                <a class="button button-primary pp-wordpress-review-dismiss <?php
                echo "$this->pluginSlug-dismiss"; ?>"
                   target="_blank"
                   href="<?php
                   echo $trigger['link']; ?>"
                   data-reason="am_now"
                >
                    <strong><?php
                        $message = __('Click here to add your rating for %s', 'publishpress-wordpress-reviews');
                        echo sprintf($message, $this->pluginName); ?></strong>
                </a>
                <a href="#" class="button pp-wordpress-review-dismiss <?php
                echo "$this->pluginSlug-dismiss"; ?>" data-reason="maybe_later">
                    <?php
                    _e('Maybe later', 'publishpress-wordpress-reviews'); ?>
                </a>
                <a href="#" class="button pp-wordpress-review-dismiss <?php
                echo "$this->pluginSlug-dismiss"; ?>" data-reason="already_did">
                    <?php
                    _e('I already did', 'publishpress-wordpress-reviews'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Checks if notices should be shown.
     *
     * @return bool
     */
    private function hideNotices()
    {
        $conditions = [
            $this->userSelectedAlreadyDid(),
            $this->lastDismissedDate() && strtotime($this->lastDismissedDate() . ' +2 weeks') > time(),
            empty($this->getTriggerCode()),
        ];

        return in_array(true, $conditions);
    }

    /**
     * Returns true if the user has opted to never see this again.
     *
     * @return bool
     */
    private function userSelectedAlreadyDid()
    {
        $userId = get_current_user_id();

        return (bool)get_user_meta($userId, $this->metaMap['user_meta_already_did'], true);
    }

    /**
     * Gets the last dismissed date.
     *
     * @return false|string
     */
    private function lastDismissedDate()
    {
        $userId = get_current_user_id();

        return get_user_meta($userId, $this->metaMap['user_meta_last_dismissed'], true);
    }

    /**
     * Sort array by priority value
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    public function sortByPriority($a, $b)
    {
        if (! isset($a['priority']) || ! isset($b['priority']) || $a['priority'] === $b['priority']) {
            return 0;
        }

        return ($a['priority'] < $b['priority']) ? -1 : 1;
    }

    /**
     * Sort array in reverse by priority value
     *
     * @param $a
     * @param $b
     *
     * @return int
     */
    public function rsortByPriority($a, $b)
    {
        if (! isset($a['priority']) || ! isset($b['priority']) || $a['priority'] === $b['priority']) {
            return 0;
        }

        return ($a['priority'] < $b['priority']) ? 1 : -1;
    }
}
