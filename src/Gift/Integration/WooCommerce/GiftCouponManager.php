<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

use Exception;
use FP_Exp\Core\Hook\HookableInterface;
use WC_Coupon;

use function absint;
use function esc_html__;
use function get_post;
use function get_post_meta;
use function gmdate;
use function sprintf;
use function strtoupper;
use function update_post_meta;
use function wp_get_current_user;

use const FP_EXP_PLUGIN_DIR;

/**
 * Manages WooCommerce coupons for gift vouchers.
 *
 * Handles creation, validation, and invalidation of gift coupons.
 */
final class GiftCouponManager implements HookableInterface
{
    private const META_GIFT_VOUCHER_ID = '_fp_exp_gift_voucher_id';
    private const META_EXPERIENCE_ID = '_fp_exp_experience_id';
    private const META_IS_GIFT_COUPON = '_fp_exp_is_gift_coupon';
    private const META_WC_COUPON_ID = '_fp_exp_wc_coupon_id';

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register WooCommerce coupon hooks.
     */
    public function register(): void
    {
        add_filter('woocommerce_coupon_is_valid', [$this, 'validateGiftCoupon'], 10, 3);
        add_filter('woocommerce_coupon_error', [$this, 'customGiftCouponError'], 10, 3);
    }

    /**
     * Create WooCommerce coupon for gift voucher.
     *
     * @param array<string, mixed> $gift_data
     */
    public function createCoupon(int $voucher_id, array $gift_data): ?int
    {
        if (! class_exists('WC_Coupon')) {
            error_log('FP Experiences: WC_Coupon class not found');

            return null;
        }

        $code = strtoupper($gift_data['code'] ?? '');
        $amount = (float) ($gift_data['total'] ?? 0.0);
        $experience_id = (int) ($gift_data['experience_id'] ?? 0);
        $valid_until = (int) ($gift_data['valid_until'] ?? 0);

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($amount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_limit_usage_to_x_items(0);

        // Set expiry date
        if ($valid_until > 0) {
            $expiry_date = gmdate('Y-m-d', $valid_until);
            $coupon->set_date_expires($expiry_date);
        }

        // Set description
        $experience = get_post($experience_id);
        $experience_title = $experience instanceof WP_Post ? $experience->post_title : 'Experience';
        $coupon->set_description(
            sprintf(
                'Gift voucher per: %s (ID: %d)',
                $experience_title,
                $voucher_id
            )
        );

        // Email restriction
        $recipient_email = $gift_data['recipient']['email'] ?? '';

        if (! empty($recipient_email)) {
            $coupon->set_email_restrictions([$recipient_email]);
        }

        // Meta data
        $coupon->update_meta_data(self::META_GIFT_VOUCHER_ID, $voucher_id);
        $coupon->update_meta_data(self::META_EXPERIENCE_ID, $experience_id);
        $coupon->update_meta_data(self::META_IS_GIFT_COUPON, 'yes');

        try {
            $coupon_id = $coupon->save();

            if ($coupon_id) {
                update_post_meta($voucher_id, self::META_WC_COUPON_ID, $coupon_id);
            }

            return $coupon_id;
        } catch (Exception $exception) {
            error_log('FP Experiences: Failed to create coupon: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Validate gift coupon usage.
     */
    public function validateGiftCoupon(bool $valid, $coupon, $discount_obj): bool
    {
        if (! $coupon || ! $coupon->get_id()) {
            return $valid;
        }

        $is_gift_coupon = $coupon->get_meta(self::META_IS_GIFT_COUPON);

        if ($is_gift_coupon !== 'yes') {
            return $valid;
        }

        $required_experience_id = (int) $coupon->get_meta(self::META_EXPERIENCE_ID);

        if (! $required_experience_id) {
            return $valid;
        }

        // Check if cart has correct experience
        if (! WC()->cart) {
            return false;
        }

        $has_valid_experience = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $item_experience_id = 0;

            // Check cart item data
            if (isset($cart_item['experience_id'])) {
                $item_experience_id = (int) $cart_item['experience_id'];
            }

            // Check product meta
            if (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_meta')) {
                $meta_exp_id = $cart_item['data']->get_meta('experience_id');

                if ($meta_exp_id) {
                    $item_experience_id = (int) $meta_exp_id;
                }
            }

            if ($item_experience_id === $required_experience_id) {
                $has_valid_experience = true;
                break;
            }
        }

        if (! $has_valid_experience) {
            // Add custom error message
            add_filter('woocommerce_coupon_error', function ($err, $err_code, $coupon_obj) use ($coupon, $required_experience_id) {
                if ($coupon_obj && $coupon_obj->get_id() === $coupon->get_id()) {
                    $experience = get_post($required_experience_id);
                    $exp_title = $experience instanceof WP_Post ? $experience->post_title : 'l\'esperienza corretta';

                    return sprintf(
                        esc_html__('Questo coupon gift può essere usato solo per "%s".', 'fp-experiences'),
                        $exp_title
                    );
                }

                return $err;
            }, 10, 3);

            return false;
        }

        return $valid;
    }

    /**
     * Custom error message for gift coupons.
     */
    public function customGiftCouponError($err, $err_code, $coupon)
    {
        // Error message is handled in validateGiftCoupon
        return $err;
    }

    /**
     * Invalidate coupon when voucher is redeemed.
     */
    public function invalidateCoupon(int $voucher_id): void
    {
        $coupon_id = (int) get_post_meta($voucher_id, self::META_WC_COUPON_ID, true);

        if (! $coupon_id) {
            return;
        }

        $coupon = new WC_Coupon($coupon_id);

        if (! $coupon->get_id()) {
            return;
        }

        // Set usage count to limit to make it unusable
        $coupon->set_usage_count($coupon->get_usage_limit());
        $coupon->save();

        error_log("FP Experiences: Invalidated coupon #{$coupon_id} for redeemed voucher #{$voucher_id}");
    }
}















