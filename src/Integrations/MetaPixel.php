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

final class MetaPixel implements HookableInterface
{
    public function register_hooks(): void
    {
        if (! $this->is_enabled()) {
            return;
        }

        add_action('wp_head', [$this, 'output_snippet'], 8);
        add_action('woocommerce_thankyou', [$this, 'render_purchase'], 30, 1);
    }

    public function output_snippet(): void
    {
        if (! Consent::granted(Consent::CHANNEL_META)) {
            return;
        }

        $settings = Helpers::tracking_settings();
        $config = isset($settings['meta_pixel']) && is_array($settings['meta_pixel']) ? $settings['meta_pixel'] : [];

        if (empty($config['enabled'])) {
            return;
        }
        $pixel_id = (string) ($config['pixel_id'] ?? '');

        if (! $pixel_id) {
            return;
        }

        echo "<!-- FP Experiences Meta Pixel -->\n";
        echo "<script>!function(f,b,e,v,n,t,s)" .
            "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?" .
            "n.callMethod.apply(n,arguments):n.queue.push(arguments)};" .
            "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';" .
            "n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;" .
            "s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');" .
            "fbq('init','" . esc_html($pixel_id) . "');fbq('track','PageView');</script>";
        echo "<noscript><img height='1' width='1' style='display:none' src='https://www.facebook.com/tr?id=" . esc_html($pixel_id) . "&ev=PageView&noscript=1'/></noscript>";
    }

    public function render_purchase(int $order_id): void
    {
        if (! Consent::granted(Consent::CHANNEL_META)) {
            return;
        }

        $settings = Helpers::tracking_settings();
        $config = isset($settings['meta_pixel']) && is_array($settings['meta_pixel']) ? $settings['meta_pixel'] : [];

        if (empty($config['enabled'])) {
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $value = (float) $order->get_total();
        $currency = $order->get_currency();

        echo "<script>if(window.fbq){fbq('track','Purchase',{value:" . $value . ",currency:'" . esc_html($currency) . "'});}</script>";
    }

    private function is_enabled(): bool
    {
        $settings = Helpers::tracking_settings();
        $config = isset($settings['meta_pixel']) && is_array($settings['meta_pixel']) ? $settings['meta_pixel'] : [];

        if (empty($config['enabled'])) {
            return false;
        }

        $pixel_id = (string) ($config['pixel_id'] ?? '');

        return $pixel_id !== '';
    }
}
