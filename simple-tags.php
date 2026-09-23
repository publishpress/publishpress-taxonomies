<?php

/**
 * Plugin Name: PublishPress Taxonomies Free
 * Plugin URI: https://wordpress.org/plugins/simple-tags/
 * Description: TaxoPress allows you to create and manage Tags, Categories, and all your WordPress taxonomy terms.
 * Version: 3.54.0
 * Author: TaxoPress
 * Author URI: https://taxopress.com
 * Text Domain: simple-tags
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * License: GPLv3
 *
 * Copyright (c) 2022 PublishPress
 *
 * @package     simple-tags
 * @author      PublishPress
 * @copyright   Copyright (c) 2022 PublishPress
 * @license     GNU General Public License version 2
 * @link        https://publishpress.com/
 */

######################################
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

Contributors to the PublishPress Taxonomies code include:
    - Kevin Drouvin (kevin.drouvin@gmail.com - http://inside-dev.net)
    - Martin Modler (modler@webformatik.com - http://www.webformatik.com)
    - Vladimir Kolesnikov (vladimir@extrememember.com - http://blog.sjinks.pro)

Sections of the PublishPress Taxonomies code are based on Custom Post Type UI by WebDevStudios.

Credits Icons :
    - famfamfam - http://www.famfamfam.com/lab/icons/silk/

*/

// don't load directly
if (!defined('ABSPATH')) {
    die('-1');
}

if (!defined('STAGS_VERSION')) {
    define('STAGS_VERSION', '3.54.0');
}

if (! defined('PUBLISHPRESS_TAXONOMIES_VERSION')) {
    define('PUBLISHPRESS_TAXONOMIES_VERSION', STAGS_VERSION);
}

// Preserve the legacy TaxoPress version alias for existing integrations.
if (! defined('TAXOPRESS_VERSION')) {
    define('TAXOPRESS_VERSION', PUBLISHPRESS_TAXONOMIES_VERSION);
}

if (! function_exists('taxopress_free_is_pro_active')) {
    function taxopress_free_is_pro_active()
    {
        // Recognize PublishPress Taxonomies Pro and legacy TaxoPress Pro identifiers.
        if (
            defined('PUBLISHPRESS_TAXONOMIES_PRO_FILE')
            || defined('PUBLISHPRESS_TAXONOMIES_PRO_VERSION')
            || defined('TAXOPRESS_PRO_FILE')
            || defined('TAXOPRESS_PRO_VERSION')
        ) {
            return true;
        }

        if (! function_exists('get_option')) {
            return false;
        }

        $pro_active = false;

        foreach ((array) get_option('active_plugins') as $plugin_file) {
            // Support the legacy entry point and both renamed-plugin transition paths.
            if (
                false !== strpos($plugin_file, 'taxopress-pro/taxopress-pro.php')
                || false !== strpos($plugin_file, 'publishpress-taxonomies-pro/taxopress-pro.php')
                || false !== strpos($plugin_file, 'publishpress-taxonomies-pro/publishpress-taxonomies-pro.php')
            ) {
                $pro_active = true;
                break;
            }
        }

        if (! $pro_active && function_exists('is_multisite') && is_multisite()) {
            foreach (array_keys((array) get_site_option('active_sitewide_plugins')) as $plugin_file) {
                if (
                    false !== strpos($plugin_file, 'taxopress-pro/taxopress-pro.php')
                    || false !== strpos($plugin_file, 'publishpress-taxonomies-pro/taxopress-pro.php')
                    || false !== strpos($plugin_file, 'publishpress-taxonomies-pro/publishpress-taxonomies-pro.php')
                ) {
                    $pro_active = true;
                    break;
                }
            }
        }

        return $pro_active;
    }
}

if (taxopress_free_is_pro_active()) {
    if (is_admin()) {
        add_filter(
            'plugin_row_meta',
            function ($links, $file) {
                if ($file === plugin_basename(__FILE__)) {
                    $links[] = '<strong>' . esc_html__('This plugin can be deleted.', 'simple-tags') . '</strong>';
                }

                return $links;
            },
            10,
            2
        );

        add_action('admin_init', function () {
            if (! function_exists('deactivate_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if (function_exists('is_plugin_active') && is_plugin_active(plugin_basename(__FILE__))) {
                deactivate_plugins(plugin_basename(__FILE__));
                set_transient('taxopress_free_deactivated_due_to_pro', 1, 30);
            }
        });

        add_action('admin_notices', function () {
            if (! get_transient('taxopress_free_deactivated_due_to_pro')) {
                return;
            }

            delete_transient('taxopress_free_deactivated_due_to_pro');

            echo '<div class="notice notice-warning is-dismissible"><p>'
                . esc_html__('PublishPress Taxonomies was not activated because PublishPress Taxonomies Pro is already active. Please keep only PublishPress Taxonomies Pro enabled.', 'simple-tags')
                . '</p></div>';
        });
    }

    return;
}

$includeFileRelativePath = '/publishpress/instance-protection/include.php';

if (file_exists(__DIR__ . '/lib/vendor' . $includeFileRelativePath)) {
    require_once __DIR__ . '/lib/vendor' . $includeFileRelativePath;
} elseif (defined('STAGS_LIB_VENDOR_PATH') && file_exists(STAGS_LIB_VENDOR_PATH . $includeFileRelativePath)) {
    require_once STAGS_LIB_VENDOR_PATH . $includeFileRelativePath;
}

if (class_exists('PublishPressInstanceProtection\\Config')) {
    $pluginCheckerConfig = new PublishPressInstanceProtection\Config();
    $pluginCheckerConfig->pluginSlug = 'simple-tags';
    $pluginCheckerConfig->pluginName = 'PublishPress Taxonomies';

    $pluginChecker = new PublishPressInstanceProtection\InstanceChecker($pluginCheckerConfig);
}

$bundledTranslationsPath = '/publishpress/bundled-translations/core/include.php';

if (file_exists(__DIR__ . '/lib/vendor' . $bundledTranslationsPath)) {
    require_once __DIR__ . '/lib/vendor' . $bundledTranslationsPath;
} elseif (defined('STAGS_LIB_VENDOR_PATH') && file_exists(STAGS_LIB_VENDOR_PATH . $bundledTranslationsPath)) {
    require_once STAGS_LIB_VENDOR_PATH . $bundledTranslationsPath;
}

$autoloadPath = __DIR__ . '/lib/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
}
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

add_action('plugins_loaded', function () {
    if (class_exists('PublishPress\BundledTranslations\BundledTranslations')) {
        $bundledTranslations = new PublishPress\BundledTranslations\BundledTranslations(
            'simple-tags',
            __DIR__ . '/languages',
            __FILE__
        );
        $bundledTranslations->init();
    }
}, 10);

$wordpressVersionNoticesPath = '/publishpress/wordpress-version-notices/src/include.php';

if (file_exists(__DIR__ . '/lib/vendor' . $wordpressVersionNoticesPath)) {
    require_once __DIR__ . '/lib/vendor' . $wordpressVersionNoticesPath;
} elseif (defined('STAGS_LIB_VENDOR_PATH') && file_exists(STAGS_LIB_VENDOR_PATH . $wordpressVersionNoticesPath)) {
    require_once STAGS_LIB_VENDOR_PATH . $wordpressVersionNoticesPath;
}


if (! defined('TAXOPRESS_FILE')) {
    define('TAXOPRESS_FILE', __FILE__);
}

if (! defined('STAGS_MIN_PHP_VERSION')) {
    define('STAGS_MIN_PHP_VERSION', '7.4');
}

if (! defined('STAGS_OPTIONS_NAME')) {
    define('STAGS_OPTIONS_NAME', 'simpletags'); // Option name for save settings
}

if (! defined('STAGS_OPTIONS_NAME_AUTO')) {
    define('STAGS_OPTIONS_NAME_AUTO', 'simpletags-auto'); // Option name for save settings auto terms
}

if (! defined('STAGS_URL')) {
    define('STAGS_URL', plugins_url('', __FILE__));
}

if (! defined('STAGS_DIR')) {
    define('STAGS_DIR', rtrim(plugin_dir_path(__FILE__), '/'));
}

if (! defined('TAXOPRESS_ABSPATH')) {
    define('TAXOPRESS_ABSPATH', __DIR__);
}

if (! defined('STAGS_LIB_VENDOR_PATH')) {
    define('STAGS_LIB_VENDOR_PATH', __DIR__ . '/lib/vendor');
}

// Check PHP min version
if (version_compare(PHP_VERSION, STAGS_MIN_PHP_VERSION, '<')) {
    require STAGS_DIR . '/inc/class.compatibility.php';

    // possibly display a notice, trigger error
    add_action('admin_init', array('SimpleTags_Compatibility', 'admin_init'));

    // stop execution of this file
    return;
}

require STAGS_DIR . '/inc/loads.php';

// Init PublishPress Taxonomies
function init_free_simple_tags()
{
    if (is_admin() && ! defined('PUBLISHPRESS_TAXONOMIES_PRO_VERSION') && ! defined('TAXOPRESS_PRO_VERSION')) {
        require_once(TAXOPRESS_ABSPATH . '/includes-core/TaxopressCoreAdmin.php');
        new \PublishPress\Taxopress\TaxopressCoreAdmin();
    }
}
add_action('init', 'init_free_simple_tags', 0);

// Activation, uninstall
register_activation_hook(__FILE__, array('SimpleTags_Plugin', 'activation'));
register_deactivation_hook(__FILE__, array('SimpleTags_Plugin', 'deactivation'));

add_action('init', 'init_simple_tags', 0);
