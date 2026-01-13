<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

/**
 * Main orchestrator for WooCommerce integration.
 *
 * Registers all WooCommerce handlers and coordinates their interactions.
 */
final class WooCommerceIntegration
{
    private GiftProductManager $product_manager;
    private GiftOrderHandler $order_handler;
    private GiftCartHandler $cart_handler;
    private GiftCouponManager $coupon_manager;
    private GiftCheckoutHandler $checkout_handler;

    public function __construct(
        ?GiftProductManager $product_manager = null,
        ?GiftOrderHandler $order_handler = null,
        ?GiftCartHandler $cart_handler = null,
        ?GiftCouponManager $coupon_manager = null,
        ?GiftCheckoutHandler $checkout_handler = null
    ) {
        $this->product_manager = $product_manager ?? new GiftProductManager();
        $this->order_handler = $order_handler ?? new GiftOrderHandler();
        $this->cart_handler = $cart_handler ?? new GiftCartHandler();
        $this->coupon_manager = $coupon_manager ?? new GiftCouponManager();
        $this->checkout_handler = $checkout_handler ?? new GiftCheckoutHandler($this->order_handler);
    }

    /**
     * Register all WooCommerce hooks.
     */
    public function register(): void
    {
        $this->order_handler->register();
        $this->cart_handler->register();
        $this->coupon_manager->register();
        $this->checkout_handler->register();

        // Product page blocking
        add_action('template_redirect', [$this, 'blockGiftProductPage']);
        add_action('pre_get_posts', [$this, 'excludeGiftProductFromQueries']);
        add_filter('woocommerce_product_query_meta_query', [$this, 'excludeGiftFromWcQueries'], 10, 2);
        add_filter('woocommerce_locate_template', [$this, 'locateGiftTemplate'], 10, 3);
    }

    /**
     * Get product manager.
     */
    public function getProductManager(): GiftProductManager
    {
        return $this->product_manager;
    }

    /**
     * Get order handler.
     */
    public function getOrderHandler(): GiftOrderHandler
    {
        return $this->order_handler;
    }

    /**
     * Get cart handler.
     */
    public function getCartHandler(): GiftCartHandler
    {
        return $this->cart_handler;
    }

    /**
     * Get coupon manager.
     */
    public function getCouponManager(): GiftCouponManager
    {
        return $this->coupon_manager;
    }

    /**
     * Get checkout handler.
     */
    public function getCheckoutHandler(): GiftCheckoutHandler
    {
        return $this->checkout_handler;
    }

    /**
     * Block direct access to gift product page.
     */
    public function blockGiftProductPage(): void
    {
        if (! is_singular('product')) {
            return;
        }

        $gift_product_id = $this->product_manager->getGiftProductId();

        if (get_the_ID() === $gift_product_id) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }

    /**
     * Exclude gift product from queries.
     */
    public function excludeGiftProductFromQueries($query): void
    {
        if (is_admin() || ! isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== 'product') {
            return;
        }

        $gift_product_id = $this->product_manager->getGiftProductId();

        if ($gift_product_id > 0) {
            $existing_excludes = (array) $query->get('post__not_in');
            $query->set('post__not_in', array_merge($existing_excludes, [$gift_product_id]));
        }
    }

    /**
     * Exclude gift product from WooCommerce queries.
     *
     * @param array<string, mixed> $meta_query
     *
     * @return array<string, mixed>
     */
    public function excludeGiftFromWcQueries($meta_query, $query): array
    {
        if (is_admin()) {
            return $meta_query;
        }

        $gift_product_id = $this->product_manager->getGiftProductId();

        if ($gift_product_id > 0) {
            $meta_query[] = [
                'key' => '_fp_exp_is_gift_product',
                'compare' => 'NOT EXISTS',
            ];
        }

        return $meta_query;
    }

    /**
     * Locate custom WooCommerce template for gift vouchers.
     */
    public function locateGiftTemplate(string $template, string $template_name, string $template_path): string
    {
        if ($template_name !== 'checkout/review-order.php') {
            return $template;
        }

        if (! function_exists('WC') || ! WC()->cart) {
            return $template;
        }

        $has_gift = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (($cart_item['_fp_exp_item_type'] ?? '') === 'gift') {
                $has_gift = true;
                break;
            }
        }

        if (! $has_gift) {
            return $template;
        }

        // Load custom template if exists
        $plugin_template = FP_EXP_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;

        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }
}















