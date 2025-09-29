<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Utils\Consent;
use FP_Exp\Utils\Helpers;
use WC_Order;
use WC_Order_Item;

use function add_action;
use function esc_html;
use function is_array;
use function wp_json_encode;
use function wc_get_order;

final class GA4
{
    public function register_hooks(): void
    {
        add_action('wp_head', [$this, 'output_snippet'], 5);
        add_action('woocommerce_thankyou', [$this, 'render_purchase_event'], 20, 1);
    }

    public function output_snippet(): void
    {
        if (! Consent::granted(Consent::CHANNEL_GA4)) {
            return;
        }

        $settings = Helpers::tracking_settings();
        $config = isset($settings['ga4']) && is_array($settings['ga4']) ? $settings['ga4'] : [];

        if (! empty($config['gtm_id'])) {
            $gtm = esc_html((string) $config['gtm_id']);
            echo "<!-- FP Experiences GTM -->\n";
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . $gtm . "');</script>\n";
            echo "<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=" . $gtm . "' height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>";

            return;
        }

        if (! empty($config['measurement_id'])) {
            $measurement_id = esc_html((string) $config['measurement_id']);
            echo "<!-- FP Experiences GA4 -->\n";
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . $measurement_id . "'></script>\n";
            echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $measurement_id . "');</script>";
        }
    }

    public function render_purchase_event(int $order_id): void
    {
        if (! Consent::granted(Consent::CHANNEL_GA4)) {
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $items = [];

        foreach ($order->get_items() as $item) {
            if (! $item instanceof WC_Order_Item) {
                continue;
            }

            if ($item->get_type() !== 'fp_experience_item') {
                continue;
            }

            $items[] = [
                'item_id' => (string) $item->get_meta('experience_id'),
                'item_name' => (string) $item->get_meta('experience_title'),
                'price' => (float) $item->get_total(),
                'quantity' => 1,
            ];
        }

        if (! $items) {
            return;
        }

        $payload = [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string) $order->get_id(),
                'value' => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'items' => $items,
            ],
        ];

        echo '<script>window.dataLayer = window.dataLayer || [];window.dataLayer.push(' . wp_json_encode($payload) . ');</script>';
    }
}
