<?php

namespace PublishPress\BundledTranslations;

/**
 * Warns admins who use a shipped non-English locale to install PublishPress Hub
 * so those translations can be updated.
 *
 * One notice is shown per request even when several plugins register the library.
 */
class HubTranslationNotice
{
    /**
     * Plugin page where Hub can be downloaded.
     */
    private const HUB_URL = 'https://publishpress.com/hub';

    /**
     * Notices registered during this request, keyed by domain and languages directory.
     *
     * @var array<string, self>
     */
    private static $notices = [];

    /**
     * Whether the admin_notices callback is already attached.
     *
     * @var bool
     */
    private static $hooked = false;

    /**
     * Plugin text domain used to recognise bundled translation filenames.
     *
     * @var string
     */
    private $domain;

    /**
     * Absolute path to the plugin's bundled languages directory.
     *
     * @var string
     */
    private $languagesDir;

    /**
     * Locales found in the bundled languages directory, keyed by locale.
     *
     * @var array<string, true>|null
     */
    private $providedLocales;

    /**
     * @param string $domain       Plugin text domain.
     * @param string $languagesDir Absolute path to the bundled languages directory.
     */
    public function __construct(string $domain, string $languagesDir)
    {
        $this->domain = $domain;
        $this->languagesDir = rtrim($languagesDir, '/\\');
        $this->providedLocales = null;
    }

    /**
     * Register this plugin's languages directory and attach the admin notice once.
     *
     * @return void
     */
    public function register(): void
    {
        self::$notices[$this->domain . '|' . $this->languagesDir] = $this;

        if (self::$hooked) {
            return;
        }

        self::$hooked = true;
        add_action('admin_notices', [self::class, 'onAdminNotices']);
    }

    /**
     * Drop registered notices and detach the admin hook.
     *
     * Used by tests so one case cannot leak into the next.
     *
     * @return void
     */
    public static function resetRegisteredNotices(): void
    {
        self::$notices = [];
        self::$hooked = false;
        remove_action('admin_notices', [self::class, 'onAdminNotices']);
    }

    /**
     * Print at most one Hub notice for the registered plugins.
     *
     * @return void
     */
    public static function onAdminNotices(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        foreach (self::$notices as $notice) {
            if ($notice->shouldDisplay()) {
                $notice->render();
                return;
            }
        }
    }

    /**
     * Whether the current request should see the Hub translation notice.
     *
     * True when the site locale or the current user's locale is a non-English
     * locale shipped in the bundled languages directory, Hub is not active, and
     * the user can install plugins.
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        if (! $this->isNoticeEnabled()) {
            return false;
        }

        if ($this->isHubPluginActive()) {
            return false;
        }

        if (! current_user_can('install_plugins')) {
            return false;
        }

        return $this->usesProvidedNonEnglishLocale();
    }

    /**
     * Print the admin warning.
     *
     * @return void
     */
    public function render(): void
    {
        $hubLink = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url(self::HUB_URL),
            esc_html__('PublishPress Hub', 'bundled-translations')
        );

        $message = sprintf(
            /* translators: %s: "PublishPress Hub" link (https://publishpress.com/hub). */
            esc_html__(
                'Install the %s plugin to receive translations and updates for your language.',
                'bundled-translations'
            ),
            $hubLink
        );

        echo '<div class="notice notice-warning publishpress-bundled-translations-hub-notice"><p>';
        echo wp_kses(
            $message,
            [
                'a' => [
                    'href'   => true,
                    'target' => true,
                    'rel'    => true,
                ],
            ]
        );
        echo '</p></div>';
    }

    /**
     * Check whether the notice is allowed to display.
     *
     * @return bool
     */
    private function isNoticeEnabled(): bool
    {
        return (bool) apply_filters(
            'publishpress_bundled_translations_hub_notice_enabled',
            true,
            $this->domain
        );
    }

    /**
     * Check whether PublishPress Hub is already running.
     *
     * @return bool
     */
    private function isHubPluginActive(): bool
    {
        $active = defined('PUBLISHPRESS_HUB_VERSION');

        return (bool) apply_filters('publishpress_bundled_translations_hub_plugin_active', $active);
    }

    /**
     * Check whether the site or the current user uses a shipped non-English locale.
     *
     * @return bool
     */
    private function usesProvidedNonEnglishLocale(): bool
    {
        $provided = $this->getProvidedLocales();

        if ([] === $provided) {
            return false;
        }

        foreach ($this->getRelevantLocales() as $locale) {
            if ($this->isEnglishLocale($locale)) {
                continue;
            }

            if (isset($provided[$locale])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configured site language, effective locale, and current user locale.
     *
     * The site language is the Settings → General value (`WPLANG`). `get_locale()`
     * is included because a locale switch can change the effective locale without
     * changing that option. An empty `WPLANG` means English (`en_US`).
     *
     * @return string[]
     */
    private function getRelevantLocales(): array
    {
        $siteLocale = get_option('WPLANG');
        if (! is_string($siteLocale) || '' === $siteLocale) {
            $siteLocale = 'en_US';
        }

        $locales = [$siteLocale, get_locale(), get_user_locale()];

        $locales = array_filter(
            $locales,
            static function ($locale): bool {
                return is_string($locale) && '' !== $locale;
            }
        );

        return array_values(array_unique($locales));
    }

    /**
     * Locales that have a bundled translation file, keyed by locale code.
     *
     * English locales are omitted. Filenames may be `{domain}-{locale}.mo`,
     * `.po`, `.json`, or `.l10n.php`. WordPress Jed JSON files may also append
     * `-{md5}` before `.json`.
     *
     * @return array<string, true>
     */
    private function getProvidedLocales(): array
    {
        if (null !== $this->providedLocales) {
            return $this->providedLocales;
        }

        $this->providedLocales = [];

        if (! is_dir($this->languagesDir)) {
            return $this->providedLocales;
        }

        $entries = scandir($this->languagesDir);
        if (false === $entries) {
            return $this->providedLocales;
        }

        foreach ($entries as $filename) {
            $locale = $this->localeFromTranslationFilename($filename);
            if ('' === $locale || $this->isEnglishLocale($locale)) {
                continue;
            }

            $this->providedLocales[$locale] = true;
        }

        return $this->providedLocales;
    }

    /**
     * Extract a locale code from a bundled translation filename.
     *
     * @param string $filename Basename of a file in the languages directory.
     * @return string Locale code, or an empty string when the name does not match.
     */
    private function localeFromTranslationFilename(string $filename): string
    {
        $prefix = $this->domain . '-';
        if (0 !== strpos($filename, $prefix)) {
            return '';
        }

        $remainder = substr($filename, strlen($prefix));

        if (1 === preg_match('/^(.+)\.l10n\.php$/', $remainder, $matches)) {
            $locale = $matches[1];
        } elseif (1 === preg_match('/^(.+)\.(?:mo|po|json)$/', $remainder, $matches)) {
            $locale = $matches[1];
        } else {
            return '';
        }

        $locale = preg_replace('/-[a-f0-9]{32}$/', '', $locale);
        if (! is_string($locale) || '' === $locale) {
            return '';
        }

        if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/', $locale)) {
            return '';
        }

        return $locale;
    }

    /**
     * Check whether a locale is English (`en` or `en_*`).
     *
     * @param string $locale Locale code.
     * @return bool
     */
    private function isEnglishLocale(string $locale): bool
    {
        $locale = strtolower($locale);

        return 'en' === $locale || 0 === strpos($locale, 'en_');
    }
}
