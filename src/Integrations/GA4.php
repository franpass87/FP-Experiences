<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;
use WC_Order;
use WC_Order_Item;

use function add_action;
use function do_action;
use function get_bloginfo;
use function implode;
use function uniqid;
use function wc_get_order;

/**
 * GA4 integration — tracking delegated to FP-Marketing-Tracking-Layer.
 * This class fires do_action('fp_tracking_event') on purchase.
 * The actual GTM/GA4 snippet injection is handled by the tracking layer.
 */
final class GA4 implements HookableInterface
{
    public function register_hooks(): void
    {
        add_action('woocommerce_thankyou', [$this, 'fire_purchase_event'], 20, 1);
    }

    public function fire_purchase_event(int|string $order_id): void
    {
        $order = wc_get_order($order_id);

        if (! $order instanceof WC_Order) {
            return;
        }

        $items = $this->build_experience_line_items($order);

        if ($items === []) {
            return;
        }

        // Con FP Marketing Tracking Layer: `fp_exp_reservation_paid` → `experience_paid` (bridge) già invia value + items — evita doppio Purchase server-side.
        if (\defined('FP_TRACKING_VERSION')) {
            return;
        }

        if ($order->get_meta('_fp_exp_thankyou_purchase_tracked') === '1') {
            return;
        }

        $params = [
            'transaction_id' => (string) $order->get_id(),
            'value'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'items'          => $items,
            'event_id'       => uniqid('fp_exp_purchase_' . $order->get_id() . '_', true),
            'affiliation'    => (string) get_bloginfo('name'),
            'fp_source'      => 'experiences',
            'page_url'       => $order->get_checkout_order_received_url(),
        ];

        $coupons = $order->get_coupon_codes();
        if ($coupons !== []) {
            $params['coupon'] = implode(',', $coupons);
        }

        /**
         * Allows modifying the purchase event payload before it is sent to the tracking layer.
         * Maintains backward compatibility with the fp_exp_datalayer_purchase filter.
         */
        $params = apply_filters('fp_exp_datalayer_purchase', $params, $order);

        do_action('fp_tracking_event', 'purchase', $params);

        $order->update_meta_data('_fp_exp_thankyou_purchase_tracked', '1');
        $order->save();
    }

    /**
     * Righe `items` GA4 per ordini con linee `fp_experience_item` (prezzo unitario = totale riga / quantità).
     *
     * @return list<array<string, mixed>>
     */
    private function build_experience_line_items(WC_Order $order): array
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            if (! $item instanceof WC_Order_Item) {
                continue;
            }

            if ($item->get_type() !== 'fp_experience_item') {
                continue;
            }

            $qty = (int) $item->get_meta('quantity');
            if ($qty < 1) {
                $qty = 1;
            }

            $lineTotal = (float) $item->get_total();
            $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;

            $items[] = [
                'item_id'       => (string) $item->get_meta('experience_id'),
                'item_name'     => (string) $item->get_meta('experience_title'),
                'item_category' => 'experience',
                'price'         => $unitPrice,
                'quantity'      => $qty,
            ];
        }

        return $items;
    }
}
