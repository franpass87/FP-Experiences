<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Localization\AutoTranslator;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Theme;

use function admin_url;
use function home_url;
use function function_exists;
use function get_woocommerce_currency;
use function get_option;
use function is_string;
use function is_singular;
use function trailingslashit;
use function wc_get_checkout_url;
use function wp_add_inline_style;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_style_is;
use function rest_url;
use function str_contains;
use function apply_filters;
use function unload_textdomain;
use function load_textdomain;
use function file_exists;
use function plugin_basename;
use function dirname;

use const WP_PLUGIN_DIR;

final class Assets
{
    private static ?Assets $instance = null;

    private bool $registered = false;

    private bool $tokens_injected = false;

    private function __construct()
    {
    }

    public static function instance(): Assets
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array<string, string> $theme
     */
    public function enqueue_front(array $theme, string $scope_class): void
    {
        $this->register_assets();

        wp_enqueue_style('fp-exp-front');
        wp_enqueue_script('fp-exp-front');

        if (! $this->tokens_injected) {
            wp_add_inline_style('fp-exp-front', Theme::design_tokens_css());
            $this->tokens_injected = true;
        }

        if (! empty($theme)) {
            wp_add_inline_style('fp-exp-front', Theme::build_scope_css($theme, $scope_class));
        }

        if (is_singular('fp_experience')) {
            wp_add_inline_style('fp-exp-front', 'body.single-fp_experience .nectar-social.fixed.visible{display:none!important;}');
        }
    }

    /**
     * @param array<string, string> $theme
     */
    public function enqueue_checkout(array $theme, string $scope_class): void
    {
        $this->enqueue_front($theme, $scope_class);

        wp_enqueue_script('fp-exp-checkout');
    }

    private function register_assets(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // TEMP FIX: Usa sempre CSS non-minificato perché il minificato è incompleto
        // TODO: Ricompilare il CSS minificato con tutti i moduli
        $css_rel = 'assets/css/front.css';
        
        /* 
        // Scegli in modo resiliente: usa i file minificati se presenti, altrimenti fallback ai non minificati
        $css_rel = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-frontend.min.css',
            'assets/css/front.css',
        ]);
        */

        $js_rel = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-frontend.min.js',
            'assets/js/front.js',
        ]);

        $use_minified = str_contains($js_rel, 'assets/js/dist/');

        $style_url = trailingslashit(FP_EXP_PLUGIN_URL) . $css_rel;
        $front_js = trailingslashit(FP_EXP_PLUGIN_URL) . $js_rel;
        $checkout_js = trailingslashit(FP_EXP_PLUGIN_URL) . 'assets/js/checkout.js';

        wp_register_style(
            'fp-exp-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
            [],
            '6.5.2'
        );

        wp_register_style(
            'fp-exp-front',
            $style_url,
            ['fp-exp-fontawesome'],
            Helpers::asset_version($css_rel)
        );

        // Se non usiamo il file minificato, registra i moduli separati
        $front_deps = []; // Non serve wp-i18n, usiamo già fpExpConfig.autoLocale
        if (! $use_minified) {
            $modules = [
                'fp-exp-availability' => 'assets/js/front/availability.js',
                'fp-exp-slots' => 'assets/js/front/slots.js',
                'fp-exp-quantity' => 'assets/js/front/quantity.js',
                'fp-exp-summary-rtb' => 'assets/js/front/summary-rtb.js',
                'fp-exp-summary-woo' => 'assets/js/front/summary-woo.js',
                'fp-exp-calendar' => 'assets/js/front/calendar.js',
                'fp-exp-calendar-standalone' => 'assets/js/front/calendar-standalone.js',
            ];
            
            foreach ($modules as $handle => $path) {
                wp_register_script(
                    $handle,
                    trailingslashit(FP_EXP_PLUGIN_URL) . $path,
                    [], // Nessuna dipendenza da WordPress core (usiamo vanilla JS)
                    Helpers::asset_version($path),
                    true
                );
                $front_deps[] = $handle;
            }
        }

        wp_register_script(
            'fp-exp-front',
            $front_js,
            $front_deps,
            Helpers::asset_version($js_rel),
            true
        );

        wp_register_script(
            'fp-exp-checkout',
            $checkout_js,
            ['fp-exp-front'],
            Helpers::asset_version('assets/js/checkout.js'),
            true
        );

        if (! wp_style_is('fp-exp-front', 'registered')) {
            return;
        }

        $currency = 'EUR';

        if (function_exists('get_woocommerce_currency')) {
            $currency = (string) get_woocommerce_currency();
        } else {
            $currency_option = get_option('woocommerce_currency');
            if (is_string($currency_option) && $currency_option) {
                $currency = $currency_option;
            }
        }

        // Get RTB settings
        $rtb_settings = get_option('fp_exp_rtb', []);
        $rtb_enabled = isset($rtb_settings['enabled']) && $rtb_settings['enabled'] === 'yes';
        
        wp_localize_script(
            'fp-exp-front',
            'fpExpConfig',
            [
                'restUrl' => rest_url('fp-exp/v1/'),
                'restNonce' => Helpers::rest_nonce(),
                // NON includiamo più checkoutNonce qui - sarà richiesto via AJAX
                // per evitare problemi di cache e user ID mismatch
                'checkoutNonce' => '', // Verrà richiesto via /checkout/nonce
                // Nonce specifico per il request-to-book via REST
                'rtbNonce' => wp_create_nonce('fp-exp-rtb'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'checkoutUrl' => function_exists('wc_get_checkout_url') ? (string) wc_get_checkout_url() : trailingslashit(home_url('/checkout')),
                'currency' => $currency,
                'tracking' => Helpers::tracking_config(),
                'pluginUrl' => trailingslashit(FP_EXP_PLUGIN_URL),
                'rtb' => [
                    'enabled' => $rtb_enabled,
                    'settings' => $rtb_settings,
                ],
                'autoLocale' => [
                    'strings' => AutoTranslator::strings(),
                    'plurals' => AutoTranslator::plurals(),
                ],
                'i18n' => $this->get_i18n_strings(),
            ]
        );
    }

    /**
     * Get i18n strings for JavaScript, ensuring the correct textdomain is loaded for WPML.
     *
     * @return array<string, mixed>
     */
    private function get_i18n_strings(): array
    {
        // Force load the correct textdomain for WPML
        $this->ensure_textdomain_loaded();

        return [
            'months' => [
                __('Gennaio', 'fp-experiences'),
                __('Febbraio', 'fp-experiences'),
                __('Marzo', 'fp-experiences'),
                __('Aprile', 'fp-experiences'),
                __('Maggio', 'fp-experiences'),
                __('Giugno', 'fp-experiences'),
                __('Luglio', 'fp-experiences'),
                __('Agosto', 'fp-experiences'),
                __('Settembre', 'fp-experiences'),
                __('Ottobre', 'fp-experiences'),
                __('Novembre', 'fp-experiences'),
                __('Dicembre', 'fp-experiences'),
            ],
            'loading' => __('Caricamento...', 'fp-experiences'),
            'selectAtLeast1Ticket' => __('Seleziona almeno 1 biglietto', 'fp-experiences'),
            'selectDateTime' => __('Seleziona data e orario', 'fp-experiences'),
            'proceedToPayment' => __('Procedi al pagamento', 'fp-experiences'),
        ];
    }

    /**
     * Ensure the plugin textdomain is loaded for the current WPML language.
     */
    private function ensure_textdomain_loaded(): void
    {
        $domain = 'fp-experiences';
        
        // Get WPML current language
        $wpml_lang = apply_filters('wpml_current_language', null);
        
        // Skip if Italian (default) or no WPML
        if (!$wpml_lang || $wpml_lang === 'all' || $wpml_lang === 'it') {
            return;
        }

        // Build path to the language file
        $plugin_dir = dirname(plugin_basename(FP_EXP_PLUGIN_FILE ?? __FILE__));
        $languages_path = WP_PLUGIN_DIR . '/' . $plugin_dir . '/languages';

        // Unload existing textdomain
        unload_textdomain($domain);

        // Try short locale file first (fp-experiences-en.mo)
        $short_mo = $languages_path . '/' . $domain . '-' . $wpml_lang . '.mo';
        if (file_exists($short_mo)) {
            load_textdomain($domain, $short_mo);
            return;
        }

        // Try full locale (fp-experiences-en_US.mo)
        $locale_map = ['en' => 'en_US', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES'];
        $full_locale = $locale_map[$wpml_lang] ?? null;
        if ($full_locale) {
            $full_mo = $languages_path . '/' . $domain . '-' . $full_locale . '.mo';
            if (file_exists($full_mo)) {
                load_textdomain($domain, $full_mo);
            }
        }
    }
}
