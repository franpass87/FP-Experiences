<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Integration\WooCommerce;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Gift\Delivery\VoucherDeliveryService;
use FP_Exp\Gift\Email\VoucherEmailSender;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use WC_Order;

use function absint;
use function array_map;
use function array_values;
use function current_time;
use function explode;
use function is_array;
use function sanitize_email;
use function update_post_meta;
use function wc_get_order;

use const MINUTE_IN_SECONDS;

/**
 * Handles WooCommerce order events for gift vouchers.
 *
 * Processes payment complete, order cancelled, and checkout events.
 */
final class GiftOrderHandler implements HookableInterface
{
    private VoucherRepository $repository;
    private VoucherDeliveryService $delivery_service;
    private VoucherEmailSender $email_sender;

    public function __construct(
        ?VoucherRepository $repository = null,
        ?VoucherDeliveryService $delivery_service = null,
        ?VoucherEmailSender $email_sender = null
    ) {
        $this->repository = $repository ?? new VoucherRepository();
        $this->delivery_service = $delivery_service ?? new VoucherDeliveryService();
        $this->email_sender = $email_sender ?? new VoucherEmailSender();
    }

    public function register_hooks(): void
    {
        $this->register();
    }

    /**
     * Register WooCommerce hooks.
     */
    public function register(): void
    {
        add_action('woocommerce_payment_complete', [$this, 'handlePaymentComplete'], 20);
        add_action('woocommerce_order_status_cancelled', [$this, 'handleOrderCancelled'], 20);
        add_action('woocommerce_order_fully_refunded', [$this, 'handleOrderCancelled'], 20);
    }

    /**
     * Handle payment complete event.
     */
    public function handlePaymentComplete(int $order_id): void
    {
        $voucher_ids = $this->getVoucherIdsFromOrder($order_id);

        if (! $voucher_ids) {
            return;
        }

        foreach ($voucher_ids as $voucher_id) {
            $status = $this->repository->getStatus($voucher_id);

            if (! $status->isPending()) {
                continue;
            }

            // Activate voucher
            $this->repository->updateStatus($voucher_id, VoucherStatus::active());
            $this->repository->appendLog($voucher_id, 'activated', $order_id);

            // Handle delivery
            $delivery = $this->repository->getDelivery($voucher_id);
            $send_at = (int) ($delivery['send_at'] ?? 0);
            $now = current_time('timestamp', true);

            if ($send_at > ($now + MINUTE_IN_SECONDS)) {
                $this->delivery_service->scheduleDelivery($voucher_id, $send_at);
                $this->repository->appendLog($voucher_id, 'scheduled', $order_id);

                continue;
            }

            // Deliver immediately
            $this->delivery_service->clearSchedule($voucher_id);
            $this->email_sender->sendVoucherEmail($voucher_id);
        }
    }

    /**
     * Handle order cancelled event.
     */
    public function handleOrderCancelled(int $order_id): void
    {
        $voucher_ids = $this->getVoucherIdsFromOrder($order_id);

        if (! $voucher_ids) {
            return;
        }

        foreach ($voucher_ids as $voucher_id) {
            $status = $this->repository->getStatus($voucher_id);

            if ($status->isFinal()) {
                continue;
            }

            $this->delivery_service->clearSchedule($voucher_id);

            $delivery = $this->repository->getDelivery($voucher_id);
            $delivery['send_at'] = 0;
            unset($delivery['scheduled_at']);
            $this->repository->updateDelivery($voucher_id, $delivery);

            $this->repository->updateStatus($voucher_id, VoucherStatus::cancelled());
            $this->repository->appendLog($voucher_id, 'cancelled', $order_id);
        }
    }

    /**
     * Process gift order after checkout.
     *
     * @param array<string, mixed> $gift_data
     */
    public function processGiftOrder(int $order_id, WC_Order $order, array $gift_data): void
    {
        // Update billing data from purchaser
        $prefill_data = $gift_data['prefill_data'] ?? [];

        if (is_array($prefill_data) && ! empty($prefill_data)) {
            if (! empty($prefill_data['billing_email'])) {
                $order->set_billing_email($prefill_data['billing_email']);
            }

            if (! empty($prefill_data['billing_first_name'])) {
                $full_name = $prefill_data['billing_first_name'];
                $parts = explode(' ', $full_name, 2);
                $order->set_billing_first_name($parts[0]);

                if (isset($parts[1])) {
                    $order->set_billing_last_name($parts[1]);
                }
            }

            if (! empty($prefill_data['billing_phone'])) {
                $order->set_billing_phone($prefill_data['billing_phone']);
            }

            $order->save();
        }

        // Add gift metadata
        $order->update_meta_data('_fp_exp_is_gift_order', 'yes');
        $order->update_meta_data('_fp_exp_gift_purchase', [
            'experience_id' => $gift_data['experience_id'] ?? 0,
            'quantity' => $gift_data['quantity'] ?? 1,
            'value' => $gift_data['total'] ?? 0.0,
            'currency' => $gift_data['currency'] ?? 'EUR',
        ]);
        $order->update_meta_data('_fp_exp_gift_code', $gift_data['code'] ?? '');
        $order->set_created_via('fp-exp-gift');
        $order->save();
    }

    /**
     * Create voucher post from order.
     *
     * @param array<string, mixed> $gift_data
     */
    public function createVoucherPost(int $order_id, array $gift_data): ?int
    {
        $voucher_id = wp_insert_post([
            'post_type' => 'fp_exp_gift_voucher',
            'post_title' => 'Gift Voucher - ' . ($gift_data['code'] ?? ''),
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1,
        ]);

        if (is_wp_error($voucher_id)) {
            error_log('FP Experiences Gift: Failed to create voucher post: ' . $voucher_id->get_error_message());

            return null;
        }

        // Save metadata
        update_post_meta($voucher_id, '_fp_exp_gift_code', $gift_data['code'] ?? '');
        update_post_meta($voucher_id, '_fp_exp_gift_experience_id', $gift_data['experience_id'] ?? 0);
        update_post_meta($voucher_id, '_fp_exp_gift_quantity', $gift_data['quantity'] ?? 1);
        update_post_meta($voucher_id, '_fp_exp_gift_value', $gift_data['total'] ?? 0.0);
        update_post_meta($voucher_id, '_fp_exp_gift_currency', $gift_data['currency'] ?? 'EUR');
        update_post_meta($voucher_id, '_fp_exp_gift_valid_until', $gift_data['valid_until'] ?? 0);
        update_post_meta($voucher_id, '_fp_exp_gift_purchaser', $gift_data['purchaser'] ?? []);
        update_post_meta($voucher_id, '_fp_exp_gift_recipient', $gift_data['recipient'] ?? []);
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $gift_data['delivery'] ?? []);
        update_post_meta($voucher_id, '_fp_exp_gift_status', 'pending');
        update_post_meta($voucher_id, '_fp_exp_gift_order_id', $order_id);

        if (! empty($gift_data['addons'])) {
            update_post_meta($voucher_id, '_fp_exp_gift_addons', $gift_data['addons']);
        }

        // Link voucher to order
        $order = wc_get_order($order_id);

        if ($order) {
            $existing_ids = $order->get_meta('_fp_exp_gift_voucher_ids');
            $voucher_ids = is_array($existing_ids) ? $existing_ids : [];
            $voucher_ids[] = $voucher_id;
            $order->update_meta_data('_fp_exp_gift_voucher_ids', $voucher_ids);
            $order->save();
        }

        return $voucher_id;
    }

    /**
     * Get voucher IDs from order.
     *
     * @return array<int, int>
     */
    private function getVoucherIdsFromOrder(int $order_id): array
    {
        $order = wc_get_order($order_id);

        if (! $order) {
            return [];
        }

        $ids = $order->get_meta('_fp_exp_gift_voucher_ids');

        if (is_array($ids)) {
            return array_values(array_map('absint', $ids));
        }

        return [];
    }
}















