<?php

declare(strict_types=1);

namespace FP_Exp\Shortcodes;

use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Theme;

use function admin_url;
use function function_exists;
use function get_woocommerce_currency;
use function get_option;
use function is_string;
use function trailingslashit;
use function wp_add_inline_style;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_style_is;
use function rest_url;

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

        $style_url = trailingslashit(FP_EXP_PLUGIN_URL) . 'assets/css/front.css';
        $front_js = trailingslashit(FP_EXP_PLUGIN_URL) . 'assets/js/front.js';
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
            Helpers::asset_version('assets/css/front.css')
        );

        wp_register_script(
            'fp-exp-front',
            $front_js,
            ['wp-i18n'],
            Helpers::asset_version('assets/js/front.js'),
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

        wp_localize_script(
            'fp-exp-front',
            'fpExpConfig',
            [
                'restUrl' => rest_url('fp-exp/v1/'),
                'restNonce' => Helpers::rest_nonce(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'currency' => $currency,
                'tracking' => Helpers::tracking_config(),
            ]
        );
    }

}
