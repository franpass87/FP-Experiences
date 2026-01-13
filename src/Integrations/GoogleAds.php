<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Consent;
use FP_Exp\Utils\Helpers;
use WC_Order;

use function add_action;
use function esc_html;
use function is_array;
use function wc_get_order;

final class GoogleAds implements HookableInterface
{
    public function register_hooks(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        add_action('woocommerce_thankyou', [$this, 'render_conversion'], 25, 1);
    }

    public function render_conversion(int $order_id): void
    {
        if (! Consent::granted(Consent::CHANNEL_GOOGLE_ADS)) {
            return;
        }

        $settings = Helpers::tracking_settings();
        $config = isset($settings['google_ads']) && is_array($settings['google_ads']) ? $settings['google_ads'] : [];

        if (empty($config['enabled'])) {
            return;
        }
        $conversion_id = (string) ($config['conversion_id'] ?? '');
        $conversion_label = (string) ($config['conversion_label'] ?? '');

        if (! $conversion_id || ! $conversion_label) {
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $value = (float) $order->get_total();
        $currency = $order->get_currency();

        echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_html($conversion_id) . "'></script>";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());</script>";
        echo "<script>gtag('event','conversion',{send_to:'" . esc_html($conversion_id) . "/" . esc_html($conversion_label) . "',value:" . $value . ",currency:'" . esc_html($currency) . "'});</script>";
    }

    private function is_enabled(): bool
    {
        $settings = Helpers::tracking_settings();
        $config = isset($settings['google_ads']) && is_array($settings['google_ads']) ? $settings['google_ads'] : [];

        if (empty($config['enabled'])) {
            return false;
        }

        $conversion_id = (string) ($config['conversion_id'] ?? '');
        $conversion_label = (string) ($config['conversion_label'] ?? '');

        return $conversion_id !== '' && $conversion_label !== '';
    }
}
