<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

use FP_Exp\Core\Hook\HookableInterface;

use function esc_html__;
use function is_array;
use function sprintf;
use function wc_price;

/**
 * Handles WooCommerce cart customization for gift vouchers.
 *
 * Customizes cart item names, prices, and links.
 */
final class GiftCartHandler implements HookableInterface
{
    private const ITEM_TYPE_KEY = '_fp_exp_item_type';

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register WooCommerce cart hooks.
     */
    public function register(): void
    {
        add_filter('woocommerce_cart_item_name', [$this, 'customizeCartName'], 99, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'setCartPrice'], 10, 3);
        add_filter('woocommerce_cart_item_permalink', '__return_null', 999);
        add_filter('woocommerce_order_item_permalink', '__return_null', 999);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addGiftPriceToCartData'], 10, 3);
        add_filter('woocommerce_add_cart_item', [$this, 'setGiftPriceOnAdd'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'setGiftPriceFromSession'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'setDynamicGiftPrice'], 10, 1);
    }

    /**
     * Customize cart item name for gift vouchers.
     */
    public function customizeCartName(string $name, array $cart_item, string $cart_item_key): string
    {
        if (($cart_item[self::ITEM_TYPE_KEY] ?? '') === 'gift') {
            $title = $cart_item['experience_title'] ?? '';

            if ($title) {
                return sprintf(
                    esc_html__('Gift voucher – %s', 'fp-experiences'),
                    $title
                );
            }
        }

        return $name;
    }

    /**
     * Set cart item price for gift vouchers.
     */
    public function setCartPrice(string $price_html, array $cart_item, string $cart_item_key): string
    {
        if (($cart_item[self::ITEM_TYPE_KEY] ?? '') !== 'gift') {
            return $price_html;
        }

        // Try to get price from cart item data first
        if (isset($cart_item['_fp_exp_gift_price'])) {
            $price = (float) $cart_item['_fp_exp_gift_price'];

            if ($price > 0) {
                return wc_price($price);
            }
        }

        // Fallback to session
        if (WC()->session) {
            $gift_data = WC()->session->get('fp_exp_gift_pending');

            if (is_array($gift_data) && isset($gift_data['total'])) {
                return wc_price($gift_data['total']);
            }
        }

        return $price_html;
    }

    /**
     * Add gift price to cart item data.
     *
     * @param array<string, mixed> $cart_item_data
     *
     * @return array<string, mixed>
     */
    public function addGiftPriceToCartData($cart_item_data, $product_id, $variation_id): array
    {
        if (! is_array($cart_item_data)) {
            return $cart_item_data;
        }

        if (($cart_item_data[self::ITEM_TYPE_KEY] ?? '') === 'gift') {
            if (WC()->session) {
                $gift_data = WC()->session->get('fp_exp_gift_pending');

                if (is_array($gift_data) && ! empty($gift_data['total'])) {
                    $cart_item_data['_fp_exp_gift_price'] = (float) $gift_data['total'];
                }
            }
        }

        return $cart_item_data;
    }

    /**
     * Set gift price when item is added to cart.
     *
     * @param array<string, mixed> $cart_item
     *
     * @return array<string, mixed>
     */
    public function setGiftPriceOnAdd($cart_item, $cart_item_key): array
    {
        if (! is_array($cart_item)) {
            return $cart_item;
        }

        if (($cart_item[self::ITEM_TYPE_KEY] ?? '') === 'gift' && isset($cart_item['_fp_exp_gift_price'])) {
            $price = (float) $cart_item['_fp_exp_gift_price'];

            if ($price > 0 && isset($cart_item['data'])) {
                $cart_item['data']->set_price($price);
            }
        }

        return $cart_item;
    }

    /**
     * Set gift price when item is loaded from session.
     *
     * @param array<string, mixed> $cart_item
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function setGiftPriceFromSession($cart_item, $values, $key): array
    {
        if (! is_array($cart_item) || ! is_array($values)) {
            return $cart_item;
        }

        if (($values[self::ITEM_TYPE_KEY] ?? '') === 'gift' && isset($values['_fp_exp_gift_price'])) {
            $price = (float) $values['_fp_exp_gift_price'];

            if ($price > 0 && isset($cart_item['data'])) {
                $cart_item['data']->set_price($price);
            }
        }

        return $cart_item;
    }

    /**
     * Set dynamic gift price before totals calculation.
     */
    public function setDynamicGiftPrice($cart): void
    {
        if (! WC()->session) {
            return;
        }

        $gift_data = WC()->session->get('fp_exp_gift_pending');

        if (! is_array($gift_data) || empty($gift_data)) {
            return;
        }

        $price = (float) ($gift_data['total'] ?? 0);

        if ($price <= 0) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (($cart_item[self::ITEM_TYPE_KEY] ?? '') === 'gift' && isset($cart_item['data'])) {
                $cart_item['data']->set_price($price);
                $cart_item['data']->set_regular_price($price);
            }
        }
    }
}















