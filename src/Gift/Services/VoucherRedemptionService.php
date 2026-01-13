<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Services;

use Exception;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Slots;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;
use WP_Post;

use function absint;
use function current_time;
use function do_action;
use function esc_html__;
use function get_locale;
use function get_option;
use function get_permalink;
use function get_post;
use function is_wp_error;
use function wc_create_order;

/**
 * Service for redeeming gift vouchers.
 *
 * Handles voucher redemption, order creation, and reservation booking.
 */
final class VoucherRedemptionService
{
    private VoucherRepository $repository;
    private VoucherValidationService $validation_service;

    public function __construct(
        ?VoucherRepository $repository = null,
        ?VoucherValidationService $validation_service = null
    ) {
        $this->repository = $repository ?? new VoucherRepository();
        $this->validation_service = $validation_service ?? new VoucherValidationService();
    }

    /**
     * Redeem a voucher.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    public function redeem(VoucherCode $code, array $payload)
    {
        // Validate code exists
        $voucher = $this->validation_service->validateCode($code);

        if (is_wp_error($voucher)) {
            return $voucher;
        }

        if (! $voucher instanceof WP_Post) {
            return new WP_Error(
                'fp_exp_gift_not_found',
                esc_html__('Voucher not found.', 'fp-experiences')
            );
        }

        $voucher_id = $voucher->ID;

        // Validate redemption
        $validation = $this->validation_service->validateRedemption($voucher_id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Validate slot
        $slot_validation = $this->validateSlot($voucher_id, $payload);

        if (is_wp_error($slot_validation)) {
            return $slot_validation;
        }

        $slot = $slot_validation['slot'];
        $slot_id = $slot_validation['slot_id'];
        $experience_id = $slot_validation['experience_id'];

        // Create redemption order
        $order = $this->createRedemptionOrder($voucher_id, $experience_id, $slot_id, $slot);

        if (is_wp_error($order)) {
            return $order;
        }

        if (! $order instanceof WC_Order) {
            return new WP_Error(
                'fp_exp_gift_redeem_order',
                esc_html__('Unable to create the redemption order.', 'fp-experiences')
            );
        }

        // Create reservation
        $reservation_id = $this->createReservation($voucher_id, $order->get_id(), $experience_id, $slot_id);

        if (is_wp_error($reservation_id)) {
            $order->delete(true);

            return $reservation_id;
        }

        // Update voucher status
        $this->repository->updateStatus($voucher_id, VoucherStatus::redeemed());
        update_post_meta($voucher_id, '_fp_exp_gift_redeemed_order_id', $order->get_id());
        update_post_meta($voucher_id, '_fp_exp_gift_redeemed_reservation_id', $reservation_id);
        $this->repository->appendLog($voucher_id, 'redeemed', $order->get_id());

        // Invalidate coupon (delegated to WooCommerce integration)
        // $this->invalidateCoupon($voucher_id);

        // Send email (delegated to Email service)
        // $this->sendRedeemedEmail($voucher_id, $order->get_id(), $slot);

        // Fire action
        do_action('fp_exp_gift_voucher_redeemed', $voucher_id, $order->get_id(), $reservation_id);

        // Build response
        $experience = get_post($experience_id);
        $experience_permalink = $experience instanceof WP_Post ? get_permalink($experience) : '';

        return [
            'order_id' => $order->get_id(),
            'reservation_id' => $reservation_id,
            'experience' => [
                'id' => $experience_id,
                'title' => $experience instanceof WP_Post ? $experience->post_title : '',
                'permalink' => $experience_permalink,
            ],
        ];
    }

    /**
     * Validate slot for redemption.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|WP_Error
     */
    private function validateSlot(int $voucher_id, array $payload)
    {
        $slot_id = absint((string) ($payload['slot_id'] ?? 0));

        if ($slot_id <= 0) {
            return new WP_Error(
                'fp_exp_gift_slot',
                esc_html__('Select a timeslot to redeem the voucher.', 'fp-experiences')
            );
        }

        $experience_id = $this->repository->getExperienceId($voucher_id);
        $slot = Slots::get_slot($slot_id);

        if (! $slot || (int) ($slot['experience_id'] ?? 0) !== $experience_id) {
            return new WP_Error(
                'fp_exp_gift_invalid_slot',
                esc_html__('The selected slot is no longer available.', 'fp-experiences')
            );
        }

        if (Slots::STATUS_CANCELLED === ($slot['status'] ?? '')) {
            return new WP_Error(
                'fp_exp_gift_cancelled_slot',
                esc_html__('The selected slot is no longer available.', 'fp-experiences')
            );
        }

        // Check capacity
        $snapshot = Slots::get_capacity_snapshot($slot_id);
        $capacity_total = absint((string) ($slot['capacity_total'] ?? 0));
        $quantity = $this->repository->getQuantity($voucher_id);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        if ($capacity_total > 0 && ($snapshot['total'] + $quantity) > $capacity_total) {
            return new WP_Error(
                'fp_exp_gift_capacity',
                esc_html__('The selected slot cannot accommodate the voucher quantity.', 'fp-experiences')
            );
        }

        return [
            'slot' => $slot,
            'slot_id' => $slot_id,
            'experience_id' => $experience_id,
        ];
    }

    /**
     * Create redemption order.
     *
     * @param array<string, mixed> $slot
     *
     * @return WC_Order|WP_Error
     */
    private function createRedemptionOrder(int $voucher_id, int $experience_id, int $slot_id, array $slot)
    {
        if (! function_exists('wc_create_order')) {
            return new WP_Error(
                'fp_exp_gift_wc',
                esc_html__('WooCommerce is required to redeem vouchers.', 'fp-experiences')
            );
        }

        try {
            $order = wc_create_order([
                'status' => 'completed',
            ]);
        } catch (Exception $exception) {
            return new WP_Error(
                'fp_exp_gift_redeem_order',
                esc_html__('Unable to create the redemption order.', 'fp-experiences')
            );
        }

        if (is_wp_error($order)) {
            return new WP_Error(
                'fp_exp_gift_redeem_order',
                esc_html__('Unable to create the redemption order.', 'fp-experiences')
            );
        }

        $experience = get_post($experience_id);
        $title = $experience instanceof WP_Post ? $experience->post_title : esc_html__('Experience', 'fp-experiences');

        // Configure order
        $order->set_created_via('fp-exp-gift');
        $order->set_currency(get_option('woocommerce_currency', 'EUR'));
        $order->set_prices_include_tax(false);
        $order->set_shipping_total(0.0);
        $order->set_shipping_tax(0.0);

        // Set billing from recipient
        $recipient = $this->repository->getRecipient($voucher_id);
        $order->set_billing_first_name($recipient['name'] ?? '');
        $order->set_billing_email($recipient['email'] ?? '');

        // Create order item
        $item = new WC_Order_Item_Product();
        $item->set_product_id(0);
        $item->set_type('fp_experience_item');
        $item->set_name(
            sprintf(
                /* translators: %s: experience title. */
                esc_html__('Gift redemption – %s', 'fp-experiences'),
                $title
            )
        );
        $item->set_quantity(1);
        $item->set_total(0.0);
        $item->add_meta_data('experience_id', $experience_id, true);
        $item->add_meta_data('experience_title', $title, true);
        $item->add_meta_data('gift_redemption', 'yes', true);
        $item->add_meta_data('slot_id', $slot_id, true);
        $item->add_meta_data('slot_start', $slot['start_datetime'] ?? '', true);
        $item->add_meta_data('slot_end', $slot['end_datetime'] ?? '', true);
        $item->add_meta_data('gift_quantity', $this->repository->getQuantity($voucher_id), true);

        // Add addons metadata
        $addons = $this->repository->getAddons($voucher_id);
        $addons_payload = $this->normalizeVoucherAddons($experience_id, $addons);
        $item->add_meta_data('gift_addons', $addons_payload, true);

        $order->add_item($item);
        $order->set_total(0.0);
        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    /**
     * Create reservation for redeemed voucher.
     *
     * @return int|WP_Error
     */
    private function createReservation(int $voucher_id, int $order_id, int $experience_id, int $slot_id)
    {
        $quantity = $this->repository->getQuantity($voucher_id);

        if ($quantity <= 0) {
            $quantity = 1;
        }

        $addons_selected = $this->repository->getAddons($voucher_id);
        $addons_selected = array_map('absint', $addons_selected);

        $code = $this->repository->getCode($voucher_id);

        $reservation_id = Reservations::create([
            'order_id' => $order_id,
            'experience_id' => $experience_id,
            'slot_id' => $slot_id,
            'status' => Reservations::STATUS_PAID,
            'pax' => [
                'gift' => [
                    'quantity' => $quantity,
                ],
            ],
            'addons' => $addons_selected,
            'meta' => [
                'gift_code' => $code ? $code->toString() : '',
                'redeemed_via' => 'gift',
            ],
            'locale' => get_locale(),
        ]);

        if ($reservation_id <= 0) {
            return new WP_Error(
                'fp_exp_gift_reservation',
                esc_html__('Impossibile registrare il riscatto del voucher. Riprova.', 'fp-experiences')
            );
        }

        return $reservation_id;
    }

    /**
     * Normalize voucher addons for order item.
     *
     * @param array<string, int> $addons
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeVoucherAddons(int $experience_id, array $addons): array
    {
        if (! $addons) {
            return [];
        }

        // This will use Pricing service in future
        // For now, return simplified structure
        $normalized = [];

        foreach ($addons as $slug => $quantity) {
            $normalized[] = [
                'slug' => sanitize_key((string) $slug),
                'quantity' => absint((string) $quantity),
            ];
        }

        return $normalized;
    }
}















