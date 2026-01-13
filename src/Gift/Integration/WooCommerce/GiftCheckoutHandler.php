<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Gift\Integration\WooCommerce\GiftOrderHandler;
use WC_Order;

use function delete_transient;
use function explode;
use function get_transient;
use function is_array;
use function is_checkout;
use function is_wc_endpoint_url;
use function wp_json_encode;

/**
 * Handles WooCommerce checkout for gift vouchers.
 *
 * Manages field prefilling, checkout processing, and JavaScript injection.
 */
final class GiftCheckoutHandler implements HookableInterface
{
    private const ITEM_TYPE_KEY = '_fp_exp_item_type';
    private GiftOrderHandler $order_handler;

    public function __construct(?GiftOrderHandler $order_handler = null)
    {
        $this->order_handler = $order_handler ?? new GiftOrderHandler();
    }

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register WooCommerce checkout hooks.
     */
    public function register(): void
    {
        add_filter('woocommerce_checkout_get_value', [$this, 'prefillCheckoutFields'], 999, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'processCheckout'], 10, 3);
        add_action('woocommerce_thankyou', [$this, 'processThankYou'], 5, 1);
        add_action('wp_footer', [$this, 'outputCheckoutScript'], 999);
    }

    /**
     * Prefill checkout fields with gift purchaser data.
     */
    public function prefillCheckoutFields($value, string $input)
    {
        if (! function_exists('WC') || ! WC()->session) {
            return $value;
        }

        $gift_data = WC()->session->get('fp_exp_gift_prefill');

        if (! is_array($gift_data) || empty($gift_data)) {
            return $value;
        }

        // Force override even for logged-in users
        if (isset($gift_data[$input]) && ! empty($gift_data[$input])) {
            return $gift_data[$input];
        }

        return $value;
    }

    /**
     * Process gift order after checkout.
     */
    public function processCheckout(int $order_id, $posted_data, $order): void
    {
        if (! WC()->cart) {
            return;
        }

        $gift_data = null;
        $prefill_data = null;

        // Get gift data from cart
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (($cart_item[self::ITEM_TYPE_KEY] ?? '') === 'gift') {
                $gift_data = $cart_item['_fp_exp_gift_full_data'] ?? null;
                $prefill_data = $cart_item['_fp_exp_gift_prefill_data'] ?? null;
                break;
            }
        }

        if (! is_array($gift_data) || empty($gift_data)) {
            return;
        }

        // Prepare gift data with prefill
        $gift_data['prefill_data'] = $prefill_data;

        // Process order (updates billing data and adds metadata)
        $this->order_handler->processGiftOrder($order_id, $order, $gift_data);

        // Create voucher post
        $voucher_id = $this->order_handler->createVoucherPost($order_id, $gift_data);

        // Create coupon if needed
        if ($voucher_id) {
            $coupon_manager = $this->order_handler->getCouponManager();
            if ($coupon_manager) {
                $coupon_id = $coupon_manager->createCoupon($voucher_id, $gift_data);

                if ($coupon_id) {
                    update_post_meta($voucher_id, '_fp_exp_wc_coupon_id', $coupon_id);
                }
            }
        }

        // Clear session
        if (WC()->session) {
            WC()->session->set('fp_exp_gift_pending', null);
            WC()->session->set('fp_exp_gift_prefill', null);
        }
    }

    /**
     * Process gift order on thankyou page (backup hook).
     */
    public function processThankYou(int $order_id): void
    {
        if (! $order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        if (! $order) {
            return;
        }

        // Check if already processed
        if ($order->get_meta('_fp_exp_is_gift_order') === 'yes') {
            return;
        }

        // Try to get data from transient
        $gift_data = null;
        $prefill_data = null;

        if (WC()->session) {
            $session_id = WC()->session->get_customer_id();

            if ($session_id) {
                $transient_key = 'fp_exp_gift_' . $session_id;
                $transient_data = get_transient($transient_key);

                if (is_array($transient_data)) {
                    $gift_data = $transient_data['pending'] ?? null;
                    $prefill_data = $transient_data['prefill'] ?? null;
                    delete_transient($transient_key);
                }
            }
        }

        if (! is_array($gift_data) || empty($gift_data)) {
            return;
        }

        // Process order
        $this->order_handler->processGiftOrder($order_id, $order, [
            'gift_data' => $gift_data,
            'prefill_data' => $prefill_data,
        ]);

        // Create voucher post
        $voucher_id = $this->order_handler->createVoucherPost($order_id, $gift_data);

        // Create coupon if needed
        if ($voucher_id && function_exists('GiftCouponManager')) {
            $coupon_manager = new GiftCouponManager();
            $coupon_id = $coupon_manager->createCoupon($voucher_id, $gift_data);

            if ($coupon_id) {
                update_post_meta($voucher_id, '_fp_exp_wc_coupon_id', $coupon_id);
            }
        }

        // Clear session
        if (WC()->session) {
            WC()->session->set('fp_exp_gift_pending', null);
            WC()->session->set('fp_exp_gift_prefill', null);
        }
    }

    /**
     * Output JavaScript for checkout field prefilling.
     */
    public function outputCheckoutScript(): void
    {
        // Only on checkout page
        if (! is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }

        if (! WC()->session) {
            return;
        }

        $gift_prefill = WC()->session->get('fp_exp_gift_prefill');

        if (! is_array($gift_prefill) || empty($gift_prefill)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var giftData = <?php echo wp_json_encode($gift_prefill); ?>;
            
            function setGiftCheckoutFields() {
                var changed = false;
                
                if (giftData.billing_first_name) {
                    var $field = $('#billing_first_name');
                    if ($field.length && $field.val() !== giftData.billing_first_name) {
                        $field.val(giftData.billing_first_name).trigger('change');
                        changed = true;
                    }
                }
                
                if (giftData.billing_email) {
                    var $email = $('#billing_email');
                    if ($email.length && $email.val() !== giftData.billing_email) {
                        $email.val(giftData.billing_email).trigger('change');
                        changed = true;
                    }
                }
                
                if (giftData.billing_phone) {
                    var $phone = $('#billing_phone');
                    if ($phone.length && $phone.val() !== giftData.billing_phone) {
                        $phone.val(giftData.billing_phone).trigger('change');
                        changed = true;
                    }
                }
                
                return changed;
            }
            
            // Set immediately
            setTimeout(function() {
                setGiftCheckoutFields();
            }, 100);
            
            // Re-set after checkout update
            $(document.body).on('updated_checkout', function() {
                setGiftCheckoutFields();
            });
            
            // Re-set periodically (for custom themes)
            var checkCount = 0;
            var checkInterval = setInterval(function() {
                if (setGiftCheckoutFields()) {
                    checkCount++;
                }
                if (checkCount >= 10) {
                    clearInterval(checkInterval);
                }
            }, 500);
        });
        </script>
        <?php
    }
}

