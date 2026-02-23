<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Core\Hook\HookableInterface;
use WC_Order;

use function __;
use function absint;
use function add_action;
use function do_action;
use function in_array;
use function is_wp_error;
use function sanitize_text_field;
use function wc_add_notice;

/**
 * Integrates experience booking validation with WooCommerce checkout
 */
final class WooCommerceCheckout implements HookableInterface
{
    public function register_hooks(): void
    {
        $this->register();
    }

    public function register(): void
    {
        // Validate slots before creating order
        add_action('woocommerce_checkout_process', [$this, 'validate_experience_slots']);
        
        // Ensure slots exist when order is created
        add_action('woocommerce_checkout_order_created', [$this, 'ensure_slots_for_order'], 10, 1);
    }

    /**
     * Validate experience slots during checkout process (before payment)
     */
    public function validate_experience_slots(): void
    {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        error_log('[FP-EXP-WC-CHECKOUT] Validating experience slots in WooCommerce checkout');

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            // Only process experience items
            if (empty($cart_item['fp_exp_item'])) {
                continue;
            }
            
            // Skip RTB items (Request To Book creates orders programmatically, not via checkout form)
            if (!empty($cart_item['fp_exp_rtb'])) {
                error_log('[FP-EXP-WC-CHECKOUT] Skipping RTB item (handled separately)');
                continue;
            }

            $experience_id = absint($cart_item['fp_exp_experience_id'] ?? 0);
            $slot_start = sanitize_text_field($cart_item['fp_exp_slot_start'] ?? '');
            $slot_end = sanitize_text_field($cart_item['fp_exp_slot_end'] ?? '');

            if ($experience_id <= 0 || !$slot_start || !$slot_end) {
                error_log('[FP-EXP-WC-CHECKOUT] ❌ Experience item missing required data');
                wc_add_notice(
                    __('Dati esperienza mancanti. Rimuovi l\'item dal carrello e riprova.', 'fp-experiences'),
                    'error'
                );
                continue;
            }

            // Check if it's a gift (skip slot validation for gifts)
            $is_gift = !empty($cart_item['fp_exp_is_gift']) || !empty($cart_item['gift_voucher']);
            
            if ($is_gift) {
                error_log('[FP-EXP-WC-CHECKOUT] Skipping slot validation for gift item');
                continue;
            }

            error_log(sprintf(
                '[FP-EXP-WC-CHECKOUT] Validating slot for experience %d: %s → %s',
                $experience_id,
                $slot_start,
                $slot_end
            ));

            // Ensure slot exists or can be created
            $slot_id = Slots::ensure_slot_for_occurrence($experience_id, $slot_start, $slot_end);

            if (is_wp_error($slot_id)) {
                error_log('[FP-EXP-WC-CHECKOUT] ❌ Slot validation failed: ' . $slot_id->get_error_message());
                error_log('[FP-EXP-WC-CHECKOUT] Error data: ' . wp_json_encode($slot_id->get_error_data()));
                
                wc_add_notice(
                    __('Lo slot selezionato non è più disponibile. Rimuovi l\'esperienza dal carrello e seleziona una nuova data.', 'fp-experiences'),
                    'error'
                );
                
                continue;
            }

            if ($slot_id <= 0) {
                error_log('[FP-EXP-WC-CHECKOUT] ❌ Slot validation failed: returned 0');
                
                wc_add_notice(
                    __('Lo slot selezionato non è più disponibile. Rimuovi l\'esperienza dal carrello e seleziona una nuova data.', 'fp-experiences'),
                    'error'
                );
                
                continue;
            }

            error_log('[FP-EXP-WC-CHECKOUT] ✅ Slot validation passed: slot_id=' . $slot_id);

            // Check capacity
            $tickets = is_array($cart_item['fp_exp_tickets'] ?? null) ? $cart_item['fp_exp_tickets'] : [];
            $capacity_check = Slots::check_capacity($slot_id, $tickets);

            if (!$capacity_check['allowed']) {
                error_log('[FP-EXP-WC-CHECKOUT] ❌ Capacity check failed');
                
                $message = $capacity_check['message'] ?? __('Lo slot selezionato è al completo.', 'fp-experiences');
                wc_add_notice($message, 'error');
            } else {
                error_log('[FP-EXP-WC-CHECKOUT] ✅ Capacity check passed');
            }
        }
    }

    /**
     * Ensure slots are created/assigned when order is finalized
     */
    public function ensure_slots_for_order(WC_Order $order): void
    {
        error_log('[FP-EXP-WC-CHECKOUT] Order created: #' . $order->get_id());

        $is_isolated = $order->get_meta('_fp_exp_isolated_checkout');
        if ($is_isolated === 'yes') {
            error_log('[FP-EXP-WC-CHECKOUT] Skipping isolated checkout order (RTB/gift, handled separately)');
            return;
        }

        $existing = Reservations::get_ids_by_order($order->get_id());
        if (! empty($existing)) {
            error_log('[FP-EXP-WC-CHECKOUT] Reservations already exist for order #' . $order->get_id() . ', skipping');
            return;
        }

        $order_id = $order->get_id();

        foreach ($order->get_items() as $item) {
            $experience_id = absint($item->get_meta('fp_exp_experience_id'));
            $slot_start = sanitize_text_field($item->get_meta('fp_exp_slot_start'));
            $slot_end = sanitize_text_field($item->get_meta('fp_exp_slot_end'));

            if ($experience_id <= 0 || !$slot_start || !$slot_end) {
                continue;
            }

            $slot_id = Slots::ensure_slot_for_occurrence($experience_id, $slot_start, $slot_end);

            if (is_wp_error($slot_id)) {
                error_log('[FP-EXP-WC-CHECKOUT] ❌ Failed to ensure slot for order ' . $order_id . ': ' . $slot_id->get_error_message());
                continue;
            }

            if ($slot_id > 0) {
                $item->update_meta_data('fp_exp_slot_id', $slot_id);
                $item->save();

                $tickets = $item->get_meta('fp_exp_tickets');
                $addons = $item->get_meta('fp_exp_addons');
                $tickets = is_array($tickets) ? $tickets : [];
                $addons = is_array($addons) ? $addons : [];

                $order_status = $order->get_status();
                $status = in_array($order_status, ['completed', 'processing'], true)
                    ? Reservations::STATUS_PAID
                    : Reservations::STATUS_PENDING;

                $reservation_id = Reservations::create([
                    'order_id'      => $order_id,
                    'experience_id' => $experience_id,
                    'slot_id'       => $slot_id,
                    'status'        => $status,
                    'pax'           => $tickets,
                    'addons'        => $addons,
                    'customer_id'   => $order->get_customer_id(),
                    'total_gross'   => (float) $item->get_total(),
                    'tax_total'     => (float) $item->get_total_tax(),
                ]);

                if ($reservation_id > 0) {
                    do_action('fp_exp_reservation_created', $reservation_id, $order_id);

                    if ($status === Reservations::STATUS_PAID) {
                        do_action('fp_exp_reservation_paid', $reservation_id, $order_id);
                    }

                    error_log('[FP-EXP-WC-CHECKOUT] ✅ Reservation created: id=' . $reservation_id . ' slot_id=' . $slot_id);
                } else {
                    error_log('[FP-EXP-WC-CHECKOUT] ❌ Failed to create reservation for slot_id=' . $slot_id);
                }
            }
        }
    }
}

