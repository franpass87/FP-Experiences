<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use function absint;
use function add_filter;
use function add_action;
use function get_post_meta;
use function get_the_post_thumbnail;
use function get_the_title;
use function has_post_thumbnail;
use function is_admin;
use function is_array;
use function is_numeric;
use function sanitize_text_field;
use function __;
use function esc_html__;
use function ucfirst;
use function wc_price;
use function wp_doing_ajax;

/**
 * Customizes WooCommerce cart/checkout display for experience items
 */
final class WooCommerceProduct
{
    public function register(): void
    {
        // Set dynamic price for experience items (CRITICAL for checkout)
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price'], 10, 1);
        
        // STORE API: Set price when item is added to cart (for Blocks compatibility)
        add_filter('woocommerce_add_cart_item', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // STORE API: Set price when cart is loaded from session (for Blocks compatibility)
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'set_price_on_add_to_cart'], 10, 2);
        
        // Customize cart item display
        add_filter('woocommerce_cart_item_name', [$this, 'customize_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'customize_cart_item_price'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Use experience image instead of virtual product placeholder
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'customize_cart_item_thumbnail'], 10, 3);
        
        // Save order item meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        
        // Customize order item display
        add_filter('woocommerce_order_item_name', [$this, 'customize_order_item_name'], 10, 2);
    }

    /**
     * Set dynamic price for experience items in cart
     * This is CRITICAL - without this, the cart total will be 0
     */
    public function set_cart_item_price($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Only for experience items
            if (empty($cart_item['fp_exp_item'])) {
                continue;
            }

            $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
            
            if ($experience_id <= 0) {
                continue;
            }

            // Get experience price
            $exp_price = get_post_meta($experience_id, '_fp_price', true);
            
            if (!is_numeric($exp_price) || $exp_price <= 0) {
                error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price: ' . $exp_price);
                continue;
            }

            // Set price per person
            // _fp_price è il prezzo per persona
            // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
            $price_per_person = (float) $exp_price;

            // Set the price per person on the product
            $cart_item['data']->set_price($price_per_person);
            
            // Get quantity for logging
            $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
            $total = $price_per_person * $quantity;
            
            error_log('[FP-EXP-WC] ✅ Set price for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person x ' . $quantity . ' = ' . $total . ' EUR total');
        }
    }

    /**
     * Set price when item is added to cart or loaded from session
     * This ensures Store API (WooCommerce Blocks) gets correct price
     * 
     * @param array $cart_item Cart item data
     * @param mixed $session_values Session data or cart item key
     * @return array Modified cart item
     */
    public function set_price_on_add_to_cart(array $cart_item, $session_values = null): array
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $cart_item;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $cart_item;
        }

        // Get experience price
        $exp_price = get_post_meta($experience_id, '_fp_price', true);
        
        if (!is_numeric($exp_price) || $exp_price <= 0) {
            error_log('[FP-EXP-WC] ⚠️ Experience ID ' . $experience_id . ' has invalid price on add_to_cart: ' . $exp_price);
            return $cart_item;
        }

        // Set price per person
        // _fp_price è il prezzo per persona
        // WooCommerce moltiplicherà automaticamente per la quantità (numero di persone)
        $price_per_person = (float) $exp_price;

        // Set the price per person on the product
        if (isset($cart_item['data']) && is_object($cart_item['data'])) {
            $cart_item['data']->set_price($price_per_person);
            error_log('[FP-EXP-WC-STOREAPI] ✅ Set price on add_to_cart for experience ' . $experience_id . ': ' . $price_per_person . ' EUR per person');
        }

        return $cart_item;
    }

    /**
     * Use experience featured image instead of virtual product placeholder
     * 
     * @param string $thumbnail Product thumbnail HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified thumbnail HTML
     */
    public function customize_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string
    {
        // Only for experience items
        if (empty($cart_item['fp_exp_item'])) {
            return $thumbnail;
        }

        $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
        
        if ($experience_id <= 0) {
            return $thumbnail;
        }

        // Get experience featured image
        if (!has_post_thumbnail($experience_id)) {
            return $thumbnail;
        }

        // Get the thumbnail with appropriate size
        $image = get_the_post_thumbnail(
            $experience_id,
            'woocommerce_thumbnail',
            [
                'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
                'alt' => get_the_title($experience_id),
            ]
        );

        return $image ?: $thumbnail;
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

