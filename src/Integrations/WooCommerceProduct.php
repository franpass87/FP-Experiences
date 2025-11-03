<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use function absint;
use function add_filter;
use function add_action;
use function get_post_meta;
use function get_the_title;
use function is_array;
use function is_numeric;
use function sanitize_text_field;
use function __;
use function esc_html__;
use function ucfirst;
use function wc_price;

/**
 * Customizes WooCommerce cart/checkout display for experience items
 */
final class WooCommerceProduct
{
    public function register(): void
    {
        // Customize cart item display
        add_filter('woocommerce_cart_item_name', [$this, 'customize_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'customize_cart_item_price'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Save order item meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        
        // Customize order item display
        add_filter('woocommerce_order_item_name', [$this, 'customize_order_item_name'], 10, 2);
    }

    /**
     * Customize cart item name to show experience title
     */
    public function customize_cart_item_name(string $name, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $name;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Customize cart item price to show experience price
     */
    public function customize_cart_item_price(string $price, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $price;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id > 0) {
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (is_numeric($exp_price) && $exp_price > 0) {
                return wc_price($exp_price);
            }
        }

        return $price;
    }

    /**
     * Customize order item name
     */
    public function customize_order_item_name(string $name, $item): string
    {
        $experience_id = absint($item->get_meta('fp_exp_experience_id'));
        
        if ($experience_id > 0) {
            return get_the_title($experience_id);
        }

        return $name;
    }

    /**
     * Display custom cart item data in cart/checkout
     */
    public function display_cart_item_data(array $item_data, array $cart_item): array
    {
        // Check if this is an experience item
        if (empty($cart_item['fp_exp_item'])) {
            return $item_data;
        }

        // Display slot date/time
        if (!empty($cart_item['fp_exp_slot_start'])) {
            $item_data[] = [
                'key' => __('Data e ora', 'fp-experiences'),
                'value' => $cart_item['fp_exp_slot_start'],
            ];
        }

        // Display tickets
        if (!empty($cart_item['fp_exp_tickets']) && is_array($cart_item['fp_exp_tickets'])) {
            foreach ($cart_item['fp_exp_tickets'] as $type => $qty) {
                if ($qty > 0) {
                    $item_data[] = [
                        'key' => ucfirst(sanitize_text_field($type)),
                        'value' => absint($qty),
                    ];
                }
            }
        }

        return $item_data;
    }

    /**
     * Save experience meta to order item when order is created
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order): void
    {
        // Check if this is an experience item
        if (empty($values['fp_exp_item'])) {
            return;
        }

        // Save all fp_exp_* meta
        $meta_keys = [
            'fp_exp_experience_id',
            'fp_exp_slot_id',
            'fp_exp_slot_start',
            'fp_exp_slot_end',
            'fp_exp_tickets',
            'fp_exp_addons',
        ];

        foreach ($meta_keys as $key) {
            if (isset($values[$key])) {
                $item->add_meta_data($key, $values[$key], true);
            }
        }

        error_log('[FP-EXP-WC] Saved order item meta for experience: ' . ($values['fp_exp_experience_id'] ?? 'unknown'));
    }
}

