<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

use Exception;
use WC_Product;
use WC_Product_Simple;
use WP_Error;

use function absint;
use function array_map;
use function array_unique;
use function esc_html__;
use function get_option;
use function get_post_status;
use function get_posts;
use function update_option;
use function update_post_meta;
use function wc_get_product;
use function wp_insert_post;
use function wp_set_object_terms;

/**
 * Manages the WooCommerce gift product.
 *
 * Handles creation, preparation, and maintenance of the gift product.
 */
final class GiftProductManager
{
    private const META_KEY = '_fp_exp_is_gift_product';
    private const OPTION_KEY = 'fp_exp_gift_product_id';

    /**
     * Ensure gift product exists and is ready.
     */
    public function ensureGiftProduct(): int
    {
        if (! function_exists('wc_get_product')) {
            return 0;
        }

        $candidates = [];
        $saved_id = (int) get_option(self::OPTION_KEY, 0);

        if ($saved_id > 0) {
            $candidates[] = $saved_id;
        }

        $existing = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'meta_key' => self::META_KEY,
            'meta_value' => 'yes',
            'fields' => 'ids',
            'numberposts' => 5,
        ]);

        if ($existing) {
            $candidates = array_merge($candidates, array_map('absint', $existing));
        }

        foreach (array_unique($candidates) as $candidate_id) {
            if ($candidate_id <= 0) {
                continue;
            }

            if ($this->prepareGiftProduct($candidate_id)) {
                return $candidate_id;
            }
        }

        $product_id = $this->createGiftProduct();

        if ($product_id > 0 && $this->prepareGiftProduct($product_id)) {
            return $product_id;
        }

        return 0;
    }

    /**
     * Prepare gift product (configure settings).
     */
    public function prepareGiftProduct(int $product_id): bool
    {
        $product = wc_get_product($product_id);

        if (! $product) {
            return false;
        }

        $status = get_post_status($product_id);

        if ('trash' === $status) {
            return false;
        }

        if (! $product->is_type('simple') && class_exists(WC_Product_Simple::class)) {
            $product = new WC_Product_Simple($product_id);
        }

        if (! $product instanceof WC_Product) {
            return false;
        }

        $name = $product->get_name();

        if (! $name) {
            $name = esc_html__('Voucher regalo FP Experiences', 'fp-experiences');
        }

        $product->set_name($name);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        $product->set_sold_individually(true);
        $product->set_price(0);
        $product->set_regular_price(0);

        try {
            $product->save();
        } catch (Exception $exception) {
            error_log('FP Experiences Gift: failed saving gift product #' . $product_id . ' - ' . $exception->getMessage());

            return false;
        }

        wp_set_object_terms($product_id, 'simple', 'product_type', false);
        wp_set_object_terms($product_id, ['exclude-from-catalog', 'exclude-from-search'], 'product_visibility', false);

        update_post_meta($product_id, self::META_KEY, 'yes');
        update_option(self::OPTION_KEY, $product_id);

        return true;
    }

    /**
     * Create new gift product post.
     */
    private function createGiftProduct(): int
    {
        $product_id = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => esc_html__('Voucher regalo FP Experiences', 'fp-experiences'),
            'post_content' => esc_html__('Voucher digitale utilizzato dal plugin FP Experiences. Non eliminare.', 'fp-experiences'),
            'meta_input' => [
                self::META_KEY => 'yes',
            ],
        ]);

        if (is_wp_error($product_id)) {
            error_log('FP Experiences Gift: failed creating gift product - ' . $product_id->get_error_message());

            return 0;
        }

        if (! $product_id) {
            return 0;
        }

        wp_set_object_terms($product_id, 'simple', 'product_type', false);

        return (int) $product_id;
    }

    /**
     * Get gift product ID.
     */
    public function getGiftProductId(): int
    {
        return (int) get_option(self::OPTION_KEY, 0);
    }

    /**
     * Check if product is gift product.
     */
    public function isGiftProduct(int $product_id): bool
    {
        return 'yes' === get_post_meta($product_id, self::META_KEY, true);
    }
}















