<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

use function absint;
use function add_action;
use function function_exists;
use function get_option;
use function is_wp_error;
use function update_option;
use function wc_get_product;
use function wp_insert_post;

/**
 * Manages a single virtual WooCommerce product for all experiences
 */
final class ExperienceProduct implements HookableInterface
{
    private const OPTION_KEY = 'fp_exp_wc_product_id';

    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        add_action('init', [$this, 'ensure_product_exists'], 20);
    }

    /**
     * Ensure virtual WooCommerce product exists for experiences
     */
    public function ensure_product_exists(): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product_id = $this->get_product_id();

        // Check if product still exists
        if ($product_id > 0) {
            $product = wc_get_product($product_id);
            if ($product && $product->get_status() === 'publish') {
                return; // Product exists and is OK
            }
            
            // Product deleted or invalid - log warning
            error_log('[FP-EXP-WC] ⚠️ Virtual product ID ' . $product_id . ' no longer exists or is not published. Recreating...');
        } else {
            error_log('[FP-EXP-WC] ℹ️ No virtual product configured. Creating...');
        }

        // Create virtual product for experiences
        $this->create_product();
    }

    /**
     * Get the WooCommerce product ID for experiences
     */
    public static function get_product_id(): int
    {
        return absint(get_option(self::OPTION_KEY, 0));
    }

    /**
     * Create virtual WooCommerce product for experiences
     */
    private function create_product(): void
    {
        if (!function_exists('wc_get_product')) {
            error_log('[FP-EXP-WC] ❌ Cannot create virtual product: WooCommerce not available');
            return;
        }

        $product_id = wp_insert_post([
            'post_title' => 'Experience Booking',
            'post_name' => 'fp-experience-booking',
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_content' => 'Virtual product for FP Experiences bookings. Do not delete.',
        ]);

        if (!$product_id || is_wp_error($product_id)) {
            error_log('[FP-EXP-WC] ❌ Failed to create virtual product: ' . (is_wp_error($product_id) ? $product_id->get_error_message() : 'Unknown error'));
            return;
        }

        // Setup as virtual product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            error_log('[FP-EXP-WC] ❌ Failed to load virtual product after creation (ID: ' . $product_id . ')');
            return;
        }
        
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_sold_individually(false); // Allow multiple quantity (for multiple people)
        $product->set_catalog_visibility('hidden'); // Hide from catalog
        $product->set_price(0); // Price will be set per cart item
        $product->set_regular_price(0);
        $product->save();

        update_option(self::OPTION_KEY, $product_id);

        error_log('[FP-EXP-WC] ✅ Created virtual product for experiences: ID ' . $product_id);
    }
}

