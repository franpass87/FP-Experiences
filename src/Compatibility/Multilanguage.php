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
        
        // WPML: Register post type as translatable and show translation columns
        add_action('wpml_loaded', [$this, 'register_wpml_post_type_translation']);
        add_filter('wpml_is_translated_post_type', [$this, 'wpml_is_translated_post_type'], 10, 2);
        
        // WPML: Force load correct textdomain for frontend translations
        add_action('wp', [$this, 'force_load_textdomain_for_wpml'], 1);
        
        // WPML: Copy meta fields when creating/saving a translation
        add_action('save_post_fp_experience', [$this, 'maybe_copy_meta_from_original'], 5, 3);
        add_action('wpml_after_save_post', [$this, 'wpml_copy_experience_meta'], 10, 4);
        add_action('icl_make_duplicate', [$this, 'wpml_copy_meta_on_duplicate'], 10, 4);
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

    /**
     * Register fp_experience as translatable post type in WPML.
     * This ensures WPML shows translation columns in the admin list.
     */
    public function register_wpml_post_type_translation(): void
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }

        global $sitepress;
        
        if (!$sitepress || !method_exists($sitepress, 'get_setting')) {
            return;
        }

        // Get current custom post type settings
        $custom_posts_sync = $sitepress->get_setting('custom_posts_sync_option', []);
        
        // Set fp_experience as translatable (1 = translate)
        // Options: 0 = not translatable, 1 = translate, 2 = translate (use translation editor)
        $post_types_to_translate = [
            'fp_experience' => 1,
            'fp_meeting_point' => 1,
        ];
        
        $updated = false;
        foreach ($post_types_to_translate as $post_type => $mode) {
            if (!isset($custom_posts_sync[$post_type]) || $custom_posts_sync[$post_type] !== $mode) {
                $custom_posts_sync[$post_type] = $mode;
                $updated = true;
            }
        }
        
        if ($updated) {
            $sitepress->set_setting('custom_posts_sync_option', $custom_posts_sync, true);
        }
    }

    /**
     * Force load the correct textdomain for WPML frontend translations.
     * This runs on 'wp' hook when WPML has fully initialized the language.
     */
    public function force_load_textdomain_for_wpml(): void
    {
        if (!is_admin() && defined('ICL_SITEPRESS_VERSION')) {
            $wpml_lang = apply_filters('wpml_current_language', null);
            
            // Only reload for non-default languages
            if ($wpml_lang && $wpml_lang !== 'all' && $wpml_lang !== 'it') {
                $domain = 'fp-experiences';
                $plugin_dir = defined('FP_EXP_PLUGIN_FILE') 
                    ? dirname(FP_EXP_PLUGIN_FILE) 
                    : WP_PLUGIN_DIR . '/FP-Experiences';
                $languages_path = $plugin_dir . '/languages';
                
                // Unload existing textdomain
                unload_textdomain($domain);
                
                // Try short locale (fp-experiences-en.mo)
                $mo_file = $languages_path . '/' . $domain . '-' . $wpml_lang . '.mo';
                if (file_exists($mo_file)) {
                    load_textdomain($domain, $mo_file);
                    return;
                }
                
                // Try full locale (fp-experiences-en_US.mo)
                $locale_map = ['en' => 'en_US', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES'];
                if (isset($locale_map[$wpml_lang])) {
                    $mo_file = $languages_path . '/' . $domain . '-' . $locale_map[$wpml_lang] . '.mo';
                    if (file_exists($mo_file)) {
                        load_textdomain($domain, $mo_file);
                    }
                }
            }
        }
    }

    /**
     * Filter to tell WPML that fp_experience is a translated post type.
     *
     * @param bool   $is_translated Whether the post type is translated
     * @param string $post_type     Post type name
     * @return bool
     */
    public function wpml_is_translated_post_type(bool $is_translated, string $post_type): bool
    {
        if (in_array($post_type, ['fp_experience', 'fp_meeting_point'], true)) {
            return true;
        }
        return $is_translated;
    }

    /**
     * Automatically copy meta from original experience when saving a translation.
     * This is called on every save_post_fp_experience action.
     *
     * @param int      $post_id Post ID
     * @param \WP_Post $post    Post object
     * @param bool     $update  Whether this is an update
     */
    public function maybe_copy_meta_from_original(int $post_id, \WP_Post $post, bool $update): void
    {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Only for fp_experience
        if ($post->post_type !== 'fp_experience') {
            return;
        }

        // Check if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }

        global $sitepress;
        if (!$sitepress) {
            return;
        }

        // Get the default/original language
        $default_lang = $sitepress->get_default_language();
        $post_lang = $sitepress->get_language_for_element($post_id, 'post_fp_experience');

        // If this is the original language, no need to copy
        if ($post_lang === $default_lang) {
            return;
        }

        // Check if this translation has empty key meta (meaning it needs copying)
        $has_pricing = get_post_meta($post_id, '_fp_base_price', true);
        $has_duration = get_post_meta($post_id, '_fp_duration_minutes', true);
        
        // If both are empty, this is likely a new translation that needs meta copied
        if ($has_pricing === '' && $has_duration === '') {
            // Get original post ID
            $trid = $sitepress->get_element_trid($post_id, 'post_fp_experience');
            if (!$trid) {
                return;
            }

            $translations = $sitepress->get_element_translations($trid, 'post_fp_experience');
            
            if (isset($translations[$default_lang])) {
                $original_id = (int) $translations[$default_lang]->element_id;
                
                if ($original_id && $original_id !== $post_id) {
                    $this->copy_experience_meta($original_id, $post_id);
                    
                    // Log for debugging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'FP Experiences: Auto-copied meta from #%d to translation #%d (lang: %s)',
                            $original_id,
                            $post_id,
                            $post_lang
                        ));
                    }
                }
            }
        }
    }

    /**
     * Copy experience meta fields when WPML creates a translation.
     * Hook: wpml_after_save_post
     *
     * @param mixed $new_post_id      The new translated post ID
     * @param mixed $fields           Translation fields
     * @param mixed $job              Translation job
     * @param mixed $translator       Translator info
     */
    public function wpml_copy_experience_meta($new_post_id, $fields = null, $job = null, $translator = null): void
    {
        $new_post_id = (int) $new_post_id;
        if ($new_post_id <= 0) {
            return;
        }

        $post = get_post($new_post_id);
        if (!$post || $post->post_type !== 'fp_experience') {
            return;
        }

        // Get original post ID
        $original_id = $this->get_original_experience_id($new_post_id);
        if (!$original_id || $original_id === $new_post_id) {
            return;
        }

        $this->copy_experience_meta($original_id, $new_post_id);
    }

    /**
     * Copy meta fields when WPML duplicates a post.
     * Hook: icl_make_duplicate
     * Note: Parameter order may vary between WPML versions
     *
     * @param mixed $master_post_id Master post ID
     * @param mixed $lang           Target language
     * @param mixed $post_array     Post data array
     * @param mixed $id             New post ID
     */
    public function wpml_copy_meta_on_duplicate($master_post_id, $lang = null, $post_array = null, $id = null): void
    {
        // icl_make_duplicate passes: $master_post_id, $lang, $post_array, $id
        $original_id = (int) $master_post_id;
        $new_post_id = (int) $id;

        if ($original_id <= 0 || $new_post_id <= 0) {
            return;
        }

        $post = get_post($original_id);
        if (!$post || $post->post_type !== 'fp_experience') {
            return;
        }

        $this->copy_experience_meta($original_id, $new_post_id);
    }

    /**
     * Get the original experience ID from a translation.
     *
     * @param int $post_id Post ID
     * @return int|null Original post ID or null
     */
    private function get_original_experience_id(int $post_id): ?int
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return null;
        }

        global $sitepress;
        if (!$sitepress) {
            return null;
        }

        $trid = $sitepress->get_element_trid($post_id, 'post_fp_experience');
        if (!$trid) {
            return null;
        }

        $translations = $sitepress->get_element_translations($trid, 'post_fp_experience');
        $default_lang = $sitepress->get_default_language();

        if (isset($translations[$default_lang])) {
            return (int) $translations[$default_lang]->element_id;
        }

        return null;
    }

    /**
     * Copy all experience meta fields from one post to another.
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function copy_experience_meta(int $source_id, int $target_id): void
    {
        // Meta fields to copy (these should have the same value across translations)
        // Note: _fp_ticket_types and _fp_addons are NOT copied because they contain
        // translatable labels that should be edited independently per translation
        $meta_keys_to_copy = [
            // Pricing (base price and rules, but NOT ticket_types/addons which have labels)
            '_fp_base_price',
            '_fp_pricing_rules',
            '_fp_exp_pricing',
            // Availability & Schedule
            '_fp_exp_availability',
            '_fp_schedule_rules',
            '_fp_schedule_exceptions',
            '_fp_duration_minutes',
            '_fp_lead_time_hours',
            '_fp_buffer_before_minutes',
            '_fp_buffer_after_minutes',
            // Capacity
            '_fp_min_party',
            '_fp_capacity_slot',
            '_fp_resources',
            // Age restrictions
            '_fp_age_min',
            '_fp_age_max',
            // Meeting points
            '_fp_meeting_point_id',
            '_fp_meeting_point_alt',
            // Media
            '_fp_gallery_ids',
            '_fp_gallery_video_url',
            '_fp_hero_image_id',
            '_thumbnail_id',
            // Settings
            '_fp_use_rtb',
            '_fp_languages',
            '_fp_exp_page_id',
        ];

        foreach ($meta_keys_to_copy as $key) {
            $value = get_post_meta($source_id, $key, true);
            if ($value !== '' && $value !== null && $value !== false) {
                update_post_meta($target_id, $key, $value);
            }
        }

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP Experiences: Copied meta from experience #%d to translation #%d',
                $source_id,
                $target_id
            ));
        }
    }
}
