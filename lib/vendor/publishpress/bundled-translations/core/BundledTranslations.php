<?php

namespace PublishPress\BundledTranslations;

/**
 * Redirects translation loads to the plugin's bundled languages directory when
 * WordPress would load from {@see WP_LANG_DIR}/plugins/ (language packs), or when
 * it resolves a different path for this domain than the canonical bundled file.
 *
 * Generic translation files are redirected through {@see load_translation_file}
 * for .mo and performant translations (.l10n.php). Script translations keep using
 * {@see load_script_translation_file} for .json files.
 *
 * Paths under {@see WP_LANG_DIR}/loco/plugins/ (Loco Translate custom overrides) are
 * never redirected, so site-specific translations from Loco keep working.
 */
class BundledTranslations
{
    /**
     * The plugin text domain.
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
     * Absolute path to the main plugin file.
     *
     * @var string
     */
    private $pluginFile;

    /**
     * @param string $domain       The plugin text domain.
     * @param string $languagesDir Absolute path to the plugin's bundled languages directory.
     * @param string $pluginFile   Absolute path to the main plugin file.
     */
    public function __construct(string $domain, string $languagesDir, string $pluginFile)
    {
        $this->domain = $domain;
        $this->languagesDir = rtrim($languagesDir, '/\\');
        $this->pluginFile = $pluginFile;
    }

    /**
     * Initialize the translation override and the Hub translation notice.
     *
     * @return void
     */
    public function init(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_filter('load_translation_file', [$this, 'filterTranslationFile'], 10, 3);
        add_filter('load_script_translation_file', [$this, 'filterScriptTranslationFile'], 10, 3);

        $notice = new HubTranslationNotice($this->domain, $this->languagesDir);
        $notice->register();
    }

    /**
     * Filter generic translation file paths (.mo and .l10n.php): when WordPress
     * targets {@see WP_LANG_DIR}/plugins/, replace it with the plugin's bundled
     * translation file in the bundled languages directory if present.
     * Other resolved paths are returned unchanged.
     *
     * @param string $file Path to the translation file.
     * @param string $domain Text domain.
     * @param string $locale Locale being loaded.
     * @return string Path to the bundled translation file when redirected, otherwise the original $file.
     */
    public function filterTranslationFile(string $file, string $domain, string $locale): string
    {
        $translationSuffix = $this->getBundledTranslationSuffix($file);
        if (null === $translationSuffix) {
            return $file;
        }

        return $this->redirectToBundledTranslationFile($file, $domain, $translationSuffix, $locale);
    }

    /**
     * Filter the script translation file path: when WordPress targets {@see WP_LANG_DIR}/plugins/,
     * replace it with the plugin's bundled JSON in the bundled languages directory if present.
     * Other resolved paths are returned unchanged.
     *
     * WordPress passes false when it cannot locate a translation file. In that case, if the domain
     * matches and a bundled locale JSON exists, the bundled path is returned; otherwise false is
     * returned so WordPress skips loading gracefully.
     *
     * @param string|false $file Path to the script translation file, or false when not found.
     * @param string $handle Script handle.
     * @param string $domain Text domain.
     * @return string|false Path to the bundled JSON when redirected, false when no file is available,
     *                      otherwise the original $file.
     */
    public function filterScriptTranslationFile($file, string $handle, string $domain)
    {
        $filePath = (false === $file) ? '' : $file;
        $result = $this->redirectToBundledTranslationFile($filePath, $domain, '.json');

        if ($result === '' && false === $file) {
            return false;
        }

        return $result;
    }

    /**
     * Check whether bundled translations are enabled.
     * @return bool
     */
    private function isEnabled(): bool
    {
        $enabled = defined('PUBLISHPRESS_BUNDLED_TRANSLATIONS_ENABLED')
            ? constant('PUBLISHPRESS_BUNDLED_TRANSLATIONS_ENABLED')
            : true;

        $enabled = apply_filters(
            'publishpress_bundled_translations_enabled',
            $enabled,
            $this->domain,
            $this->pluginFile
        );

        return (bool) $enabled;
    }

    /**
     * Check whether the domain is the plugin's domain.
     * @param string $domain The domain to check.
     * @return bool
     */
    private function isPluginDomain(string $domain): bool
    {
        return $domain === $this->domain;
    }

    /**
     * Check whether the file path is resolved under the global WP languages plugins directory.
     *
     * @param string $filePath Absolute path to the translation file (.mo, .l10n.php or .json).
     * @return bool True when the path is under {@see WP_LANG_DIR}/plugins/.
     */
    private function isFromGlobalLanguagesDirectory(string $filePath): bool
    {
        return false !== strpos($filePath, WP_LANG_DIR . '/plugins/');
    }

    /**
     * Check whether the file path is under Loco Translate's plugin override directory.
     *
     * @param string $filePath Absolute path to the translation file (.mo, .l10n.php or .json).
     * @return bool True when the path is under {@see WP_LANG_DIR}/loco/plugins/.
     */
    private function isFromLocoPluginsLanguagesDirectory(string $filePath): bool
    {
        return false !== strpos($filePath, WP_LANG_DIR . '/loco/plugins/');
    }

    /**
     * Get the locale.
     * @return string
     */
    private function getLocale(): string
    {
        return determine_locale();
    }

    /**
     * Resolve the locale for bundled translation file names.
     *
     * Uses the provided locale when present, and falls back to {@see determine_locale()}
     * when empty.
     *
     * @param string $locale Locale provided by WordPress.
     * @return string
     */
    private function resolveLocale(string $locale): string
    {
        if (! empty($locale)) {
            return $locale;
        }

        return $this->getLocale();
    }

    /**
     * Get the bundled translation suffix for the provided translation file path.
     *
     * @param string $filePath Absolute path to the translation file.
     * @return string|null ".mo" or ".l10n.php" when supported; null otherwise.
     */
    private function getBundledTranslationSuffix(string $filePath): ?string
    {
        if (preg_match('/\.l10n\.php$/', $filePath)) {
            return '.l10n.php';
        }

        if (preg_match('/\.mo$/', $filePath)) {
            return '.mo';
        }

        return null;
    }

    /**
     * Resolve and return the bundled translation path when redirection should happen.
     *
     * Returns the original file path when no redirection is needed or when the
     * bundled translation file does not exist.
     *
     * @param string $filePath Original path WordPress resolved.
     * @param string $textDomain Text domain.
     * @param string $suffix Bundled filename suffix, e.g. ".mo", ".l10n.php" or ".json".
     * @param string $locale Optional locale provided by WordPress.
     * @return string
     */
    private function redirectToBundledTranslationFile(
        string $filePath,
        string $textDomain,
        string $suffix,
        string $locale = ''
    ): string {
        $resolvedLocale = $this->resolveLocale($locale);

        if (! $this->shouldForceUseOfBundledTranslations($filePath, $textDomain, $suffix, $resolvedLocale)) {
            return $filePath;
        }

        $bundledTranslationFile = $this->languagesDir . '/' . $this->domain . '-' . $resolvedLocale . $suffix;

        if (! file_exists($bundledTranslationFile)) {
            return $filePath;
        }

        return $bundledTranslationFile;
    }

    /**
     * Check whether a file path already points to the canonical bundled file.
     *
     * @param string $filePath Absolute path WordPress resolved.
     * @param string $suffix Bundled filename suffix, e.g. ".mo", ".l10n.php" or ".json".
     * @param string $locale Locale used to build the canonical bundled file name.
     * @return bool True when $filePath matches the canonical bundled path.
     */
    private function isCanonicalBundledPath(string $filePath, string $suffix, string $locale): bool
    {
        $bundledPath = $this->languagesDir . '/' . $this->domain . '-' . $locale . $suffix;

        return wp_normalize_path($filePath) === wp_normalize_path($bundledPath);
    }

    /**
     * Check whether to redirect the load to the plugin's bundled translations.
     *
     * True when the domain matches and either (1) WordPress chose a file under the
     * global plugin language packs directory, or (2) the resolved path is not already
     * the bundled file for the current locale (covers JIT paths under the plugin).
     * False when the path is under Loco Translate's {@see WP_LANG_DIR}/loco/plugins/ tree.
     *
     * @param string $filePath Absolute path to the translation file WordPress resolved.
     * @param string $textDomain Text domain.
     * @param string $suffix Bundled filename suffix, e.g. ".mo", ".l10n.php" or ".json".
     * @param string $locale Locale used to resolve canonical bundled paths.
     * @return bool Whether to replace $filePath with the path under the bundled languages directory.
     */
    private function shouldForceUseOfBundledTranslations(string $filePath, $textDomain, string $suffix, string $locale = ''): bool
    {
        if (! $this->isPluginDomain($textDomain)) {
            return false;
        }

        if ($this->isFromLocoPluginsLanguagesDirectory($filePath)) {
            return false;
        }

        if ($this->isFromGlobalLanguagesDirectory($filePath)) {
            return true;
        }

        return ! $this->isCanonicalBundledPath($filePath, $suffix, $this->resolveLocale($locale));
    }
}
