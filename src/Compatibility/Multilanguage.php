<?php

declare(strict_types=1);

namespace FP_Exp\Compatibility;

use FP_Exp\Core\Hook\HookableInterface;

use function add_action;
use function add_filter;
use function apply_filters;
use function class_exists;
use function defined;
use function function_exists;
use function get_locale;
use function get_option;
use function get_post;
use function get_post_meta;
use function in_array;
use function sanitize_text_field;
use function substr;

/**
 * Unified multilanguage compatibility layer.
 *
 * Provides a consistent API for working with different multilanguage plugins:
 * - FP-Multilanguage (proprietary)
 * - WPML
 * - Polylang
 * - TranslatePress
 * - Weglot
 *
 * @package FP_Exp\Compatibility
 * @since 1.2.0
 */
final class Multilanguage implements HookableInterface
{
    /**
     * Detected multilanguage plugin.
     *
     * @var string|null
     */
    private static ?string $detected_plugin = null;

    /**
     * Cached current language.
     *
     * @var string|null
     */
    private static ?string $current_language = null;

    /**
     * Supported plugins in priority order.
     */
    private const SUPPORTED_PLUGINS = [
        'fp-multilanguage',
        'wpml',
        'polylang',
        'translatepress',
        'weglot',
    ];

    /**
     * Default locale mappings for common language codes.
     */
    private const LOCALE_MAP = [
        'it' => 'it_IT',
        'en' => 'en_US',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
        'pt' => 'pt_PT',
        'nl' => 'nl_NL',
        'ru' => 'ru_RU',
        'zh' => 'zh_CN',
        'ja' => 'ja',
        'ko' => 'ko_KR',
    ];

    /**
     * Register WordPress hooks.
     */
    public function register_hooks(): void
    {
        // Initialize on plugins_loaded to ensure all multilanguage plugins are available
        add_action('plugins_loaded', [$this, 'init'], 5);

        // Filter for experience queries to get translated versions
        add_filter('fp_exp_get_experience_id', [$this, 'get_translated_experience_id'], 10, 2);

        // Filter for getting translated content
        add_filter('fp_exp_get_translated_field', [$this, 'get_translated_field'], 10, 4);

        // Add WPML hooks for automatic ID conversion
        add_filter('fp_exp_shortcode_experience_id', [$this, 'translate_experience_id_for_frontend']);

        // Register string translation domains for WPML/Polylang String Translation
        add_action('init', [$this, 'register_string_translations'], 20);
    }

    /**
     * Initialize multilanguage detection.
     */
    public function init(): void
    {
        self::detect_plugin();
    }

    /**
     * Detect which multilanguage plugin is active.
     *
     * @return string|null Plugin identifier or null if none detected
     */
    public static function detect_plugin(): ?string
    {
        if (self::$detected_plugin !== null) {
            return self::$detected_plugin;
        }

        // 1. FP-Multilanguage (check both old and new class structures)
        if (
            class_exists('\FP\Multilanguage\Language') ||
            class_exists('\FP\Multilanguage\Kernel\Plugin') ||
            function_exists('fpml_get_current_language')
        ) {
            self::$detected_plugin = 'fp-multilanguage';
            return self::$detected_plugin;
        }

        // 2. WPML
        if (defined('ICL_SITEPRESS_VERSION') || defined('ICL_LANGUAGE_CODE')) {
            self::$detected_plugin = 'wpml';
            return self::$detected_plugin;
        }

        // 3. Polylang
        if (function_exists('pll_current_language') || defined('POLYLANG_VERSION')) {
            self::$detected_plugin = 'polylang';
            return self::$detected_plugin;
        }

        // 4. TranslatePress
        if (class_exists('TRP_Translate_Press') || function_exists('trp_get_current_language')) {
            self::$detected_plugin = 'translatepress';
            return self::$detected_plugin;
        }

        // 5. Weglot
        if (function_exists('weglot_get_current_language') || defined('WEGLOT_VERSION')) {
            self::$detected_plugin = 'weglot';
            return self::$detected_plugin;
        }

        self::$detected_plugin = '';
        return null;
    }

    /**
     * Check if any multilanguage plugin is active.
     *
     * @return bool
     */
    public static function is_multilanguage_active(): bool
    {
        return !empty(self::detect_plugin());
    }

    /**
     * Get the currently detected multilanguage plugin.
     *
     * @return string|null
     */
    public static function get_active_plugin(): ?string
    {
        $plugin = self::detect_plugin();
        return $plugin ?: null;
    }

    /**
     * Get current language code.
     *
     * @param bool $force_refresh Force re-detection of current language
     * @return string Two-letter language code (e.g., 'it', 'en')
     */
    public static function get_current_language(bool $force_refresh = false): string
    {
        if (self::$current_language !== null && !$force_refresh) {
            return self::$current_language;
        }

        $lang = '';
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'fp-multilanguage':
                $lang = self::get_fp_multilanguage_language();
                break;

            case 'wpml':
                $lang = self::get_wpml_language();
                break;

            case 'polylang':
                $lang = self::get_polylang_language();
                break;

            case 'translatepress':
                $lang = self::get_translatepress_language();
                break;

            case 'weglot':
                $lang = self::get_weglot_language();
                break;
        }

        // Fallback to WordPress locale
        if (empty($lang)) {
            $locale = get_locale();
            $lang = substr($locale, 0, 2);
        }

        // Final fallback
        if (empty($lang)) {
            $lang = 'it';
        }

        self::$current_language = sanitize_text_field($lang);
        return self::$current_language;
    }

    /**
     * Get full locale from language code.
     *
     * @param string|null $lang_code Optional language code, uses current if not provided
     * @return string Full locale (e.g., 'it_IT', 'en_US')
     */
    public static function get_current_locale(?string $lang_code = null): string
    {
        if ($lang_code === null) {
            $lang_code = self::get_current_language();
        }

        // Check if it's already a full locale
        if (strpos($lang_code, '_') !== false) {
            return $lang_code;
        }

        // Map short code to full locale
        return self::LOCALE_MAP[$lang_code] ?? $lang_code . '_' . strtoupper($lang_code);
    }

    /**
     * Check if current language is the default/primary language.
     *
     * @return bool
     */
    public static function is_default_language(): bool
    {
        $plugin = self::detect_plugin();
        $current = self::get_current_language();

        switch ($plugin) {
            case 'fp-multilanguage':
                // FP-Multilanguage: Italian is default
                return $current === 'it';

            case 'wpml':
                $default = (string) apply_filters('wpml_default_language', 'it');
                return $current === $default;

            case 'polylang':
                if (function_exists('pll_default_language')) {
                    return $current === pll_default_language();
                }
                return true;

            case 'translatepress':
                $settings = get_option('trp_settings', []);
                $default = $settings['default-language'] ?? 'it_IT';
                return self::get_current_locale($current) === $default;

            case 'weglot':
                if (function_exists('weglot_get_original_language')) {
                    return $current === weglot_get_original_language();
                }
                return true;
        }

        return true;
    }

    /**
     * Get translated post ID for a given post.
     *
     * @param int         $post_id     Original post ID
     * @param string|null $target_lang Target language code (null = current language)
     * @return int Translated post ID or original if no translation exists
     */
    public static function get_translated_post_id(int $post_id, ?string $target_lang = null): int
    {
        if ($post_id <= 0) {
            return $post_id;
        }

        if ($target_lang === null) {
            $target_lang = self::get_current_language();
        }

        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'fp-multilanguage':
                return self::get_fp_multilanguage_translation($post_id, $target_lang);

            case 'wpml':
                return self::get_wpml_translation($post_id, $target_lang);

            case 'polylang':
                return self::get_polylang_translation($post_id, $target_lang);

            case 'translatepress':
                // TranslatePress doesn't duplicate posts
                return $post_id;

            case 'weglot':
                // Weglot doesn't duplicate posts
                return $post_id;
        }

        return $post_id;
    }

    /**
     * Get all available translations for a post.
     *
     * @param int $post_id Post ID
     * @return array<string, int> Array of [lang_code => post_id]
     */
    public static function get_all_translations(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'fp-multilanguage':
                if (function_exists('fpml_get_all_translations')) {
                    return fpml_get_all_translations($post_id);
                }
                // Fallback: check _fpml_pair_id meta
                $translations = ['it' => $post_id];
                $pair_id = (int) get_post_meta($post_id, '_fpml_pair_id', true);
                if ($pair_id > 0) {
                    $translations['en'] = $pair_id;
                }
                return $translations;

            case 'wpml':
                $translations = [];
                $trid = (int) apply_filters('wpml_element_trid', null, $post_id);
                if ($trid) {
                    $all = apply_filters('wpml_get_element_translations', [], $trid);
                    foreach ($all as $lang => $data) {
                        if (isset($data->element_id)) {
                            $translations[$lang] = (int) $data->element_id;
                        }
                    }
                }
                return $translations;

            case 'polylang':
                if (function_exists('pll_get_post_translations')) {
                    return pll_get_post_translations($post_id);
                }
                return [];
        }

        return [$post_id];
    }

    /**
     * Get enabled languages.
     *
     * @return array<int, string> Array of language codes
     */
    public static function get_enabled_languages(): array
    {
        $plugin = self::detect_plugin();

        switch ($plugin) {
            case 'fp-multilanguage':
                if (function_exists('fpml_get_enabled_languages')) {
                    return fpml_get_enabled_languages();
                }
                return ['it', 'en'];

            case 'wpml':
                $languages = apply_filters('wpml_active_languages', [], ['skip_missing' => 0]);
                return array_keys($languages);

            case 'polylang':
                if (function_exists('pll_languages_list')) {
                    return pll_languages_list(['fields' => 'slug']);
                }
                return [];

            case 'translatepress':
                $settings = get_option('trp_settings', []);
                $languages = [];
                if (!empty($settings['publish-languages'])) {
                    foreach ($settings['publish-languages'] as $locale) {
                        $languages[] = substr($locale, 0, 2);
                    }
                }
                return $languages;

            case 'weglot':
                if (function_exists('weglot_get_destination_languages')) {
                    $dest = weglot_get_destination_languages();
                    $languages = [weglot_get_original_language()];
                    foreach ($dest as $lang) {
                        $languages[] = $lang->getIso639();
                    }
                    return $languages;
                }
                return [];
        }

        return ['it'];
    }

    /**
     * Filter callback to translate experience ID for frontend display.
     *
     * @param int $experience_id Experience post ID
     * @return int Translated experience ID
     */
    public function translate_experience_id_for_frontend(int $experience_id): int
    {
        return self::get_translated_post_id($experience_id);
    }

    /**
     * Filter callback to get translated experience ID.
     *
     * @param int         $experience_id Original experience ID
     * @param string|null $target_lang   Target language
     * @return int Translated ID
     */
    public function get_translated_experience_id(int $experience_id, ?string $target_lang = null): int
    {
        return self::get_translated_post_id($experience_id, $target_lang);
    }

    /**
     * Filter callback to get translated field value.
     *
     * @param mixed       $value       Original value
     * @param int         $post_id     Post ID
     * @param string      $field_key   Meta field key
     * @param string|null $target_lang Target language
     * @return mixed Translated value or original
     */
    public function get_translated_field($value, int $post_id, string $field_key, ?string $target_lang = null)
    {
        $translated_id = self::get_translated_post_id($post_id, $target_lang);

        if ($translated_id !== $post_id) {
            $translated_value = get_post_meta($translated_id, $field_key, true);
            if (!empty($translated_value)) {
                return $translated_value;
            }
        }

        return $value;
    }

    /**
     * Register plugin strings for translation.
     */
    public function register_string_translations(): void
    {
        $plugin = self::detect_plugin();

        if ($plugin === 'wpml' && function_exists('icl_register_string')) {
            $this->register_wpml_strings();
        } elseif ($plugin === 'polylang' && function_exists('pll_register_string')) {
            $this->register_polylang_strings();
        }
    }

    /**
     * Register strings for WPML String Translation.
     */
    private function register_wpml_strings(): void
    {
        $strings = $this->get_translatable_strings();

        foreach ($strings as $name => $value) {
            if (is_string($value) && !empty($value)) {
                icl_register_string('FP Experiences', $name, $value);
            }
        }
    }

    /**
     * Register strings for Polylang.
     */
    private function register_polylang_strings(): void
    {
        $strings = $this->get_translatable_strings();

        foreach ($strings as $name => $value) {
            if (is_string($value) && !empty($value)) {
                pll_register_string($name, $value, 'FP Experiences');
            }
        }
    }

    /**
     * Get list of plugin option strings that should be translatable.
     *
     * @return array<string, string>
     */
    private function get_translatable_strings(): array
    {
        $strings = [];

        // Branding strings
        $branding = get_option('fp_exp_branding', []);
        if (is_array($branding)) {
            foreach (['company_name', 'tagline', 'support_text'] as $key) {
                if (!empty($branding[$key])) {
                    $strings['branding_' . $key] = $branding[$key];
                }
            }
        }

        // Email strings
        $emails = get_option('fp_exp_emails', []);
        if (is_array($emails)) {
            foreach ($emails as $key => $value) {
                if (is_string($value) && !empty($value)) {
                    $strings['email_' . $key] = $value;
                }
            }
        }

        // Listing strings
        $listing = get_option('fp_exp_listing', []);
        if (is_array($listing)) {
            foreach (['page_title', 'no_experiences_text', 'filter_label', 'cta_text'] as $key) {
                if (!empty($listing[$key])) {
                    $strings['listing_' . $key] = $listing[$key];
                }
            }
        }

        return $strings;
    }

    // =========================================================================
    // PRIVATE HELPER METHODS FOR EACH PLUGIN
    // =========================================================================

    /**
     * Get language from FP-Multilanguage.
     */
    private static function get_fp_multilanguage_language(): string
    {
        // Try new helper function first (recommended)
        if (function_exists('fpml_get_current_language')) {
            return fpml_get_current_language();
        }

        // Try new Kernel structure
        if (class_exists('\FP\Multilanguage\Language')) {
            return \FP\Multilanguage\Language::instance()->get_current_language();
        }

        // Try legacy class (deprecated)
        if (class_exists('\FPML_Language')) {
            return \FPML_Language::instance()->get_current_language();
        }

        return '';
    }

    /**
     * Get language from WPML.
     */
    private static function get_wpml_language(): string
    {
        if (defined('ICL_LANGUAGE_CODE')) {
            return ICL_LANGUAGE_CODE;
        }

        $lang = apply_filters('wpml_current_language', null);
        return is_string($lang) ? $lang : '';
    }

    /**
     * Get language from Polylang.
     */
    private static function get_polylang_language(): string
    {
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language('slug');
            return is_string($lang) ? $lang : '';
        }
        return '';
    }

    /**
     * Get language from TranslatePress.
     */
    private static function get_translatepress_language(): string
    {
        if (function_exists('trp_get_current_language')) {
            $locale = trp_get_current_language();
            if ($locale) {
                return substr($locale, 0, 2);
            }
        }
        return '';
    }

    /**
     * Get language from Weglot.
     */
    private static function get_weglot_language(): string
    {
        if (function_exists('weglot_get_current_language')) {
            return weglot_get_current_language();
        }
        return '';
    }

    /**
     * Get translation from FP-Multilanguage.
     */
    private static function get_fp_multilanguage_translation(int $post_id, string $target_lang): int
    {
        // Use helper function if available
        if (function_exists('fpml_get_translation_id')) {
            $translated = fpml_get_translation_id($post_id, $target_lang);
            return $translated ?: $post_id;
        }

        // Fallback: check _fpml_pair_id meta
        $post = get_post($post_id);
        if (!$post) {
            return $post_id;
        }

        $post_lang = (string) get_post_meta($post_id, '_fpml_language', true);

        // If target is same as current post language, return original
        if ($post_lang === $target_lang) {
            return $post_id;
        }

        // Get the paired translation
        $pair_id = (int) get_post_meta($post_id, '_fpml_pair_id', true);
        if ($pair_id > 0) {
            $pair_post = get_post($pair_id);
            if ($pair_post && $pair_post->post_status !== 'trash') {
                return $pair_id;
            }
        }

        return $post_id;
    }

    /**
     * Get translation from WPML.
     */
    private static function get_wpml_translation(int $post_id, string $target_lang): int
    {
        $post = get_post($post_id);
        if (!$post) {
            return $post_id;
        }

        $translated_id = (int) apply_filters(
            'wpml_object_id',
            $post_id,
            $post->post_type,
            true, // Return original if translation not found
            $target_lang
        );

        return $translated_id ?: $post_id;
    }

    /**
     * Get translation from Polylang.
     */
    private static function get_polylang_translation(int $post_id, string $target_lang): int
    {
        if (function_exists('pll_get_post')) {
            $translated_id = pll_get_post($post_id, $target_lang);
            return $translated_id ?: $post_id;
        }
        return $post_id;
    }

    /**
     * Clear cached values (useful for testing or after language switch).
     */
    public static function clear_cache(): void
    {
        self::$detected_plugin = null;
        self::$current_language = null;
    }
}
