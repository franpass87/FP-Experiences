<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Repository;

use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Gift\VoucherTable;
use WP_Post;

use function absint;
use function array_map;
use function array_values;
use function current_time;
use function get_current_user_id;
use function get_post;
use function get_post_meta;
use function get_post_modified_time;
use function get_post_time;
use function get_posts;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function time;
use function update_post_meta;
use function wc_get_order;

use FP_Exp\Domain\Gift\Repositories\VoucherRepositoryInterface;

/**
 * Repository for voucher data access.
 *
 * Provides a clean interface for voucher CRUD operations.
 */
final class VoucherRepository implements VoucherRepositoryInterface
{
    /**
     * Find voucher by code.
     */
    public function findByCode(VoucherCode $code): ?WP_Post
    {
        // Try table lookup first (faster)
        $record = VoucherTable::get_by_code($code->toString());

        if (is_array($record) && ! empty($record['voucher_id'])) {
            $voucher = get_post(absint((string) $record['voucher_id']));

            if ($voucher instanceof WP_Post) {
                return $voucher;
            }
        }

        // Fallback to post meta query
        $vouchers = get_posts([
            'post_type' => VoucherCPT::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_fp_exp_gift_code',
            'meta_value' => $code->toString(),
        ]);

        if (! $vouchers) {
            return null;
        }

        return $vouchers[0];
    }

    /**
     * Find voucher by ID.
     */
    public function findById(int $id): ?WP_Post
    {
        if ($id <= 0) {
            return null;
        }

        $voucher = get_post($id);

        if (! $voucher instanceof WP_Post || VoucherCPT::POST_TYPE !== $voucher->post_type) {
            return null;
        }

        return $voucher;
    }

    /**
     * Get vouchers by order ID.
     *
     * @return array<int, int>
     */
    public function getVoucherIdsByOrder(int $order_id): array
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

    /**
     * Get voucher status.
     */
    public function getStatus(int $voucher_id): VoucherStatus
    {
        $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));

        if ('' === $status) {
            return VoucherStatus::pending();
        }

        try {
            return VoucherStatus::fromString($status);
        } catch (\InvalidArgumentException $exception) {
            return VoucherStatus::pending();
        }
    }

    /**
     * Update voucher status.
     */
    public function updateStatus(int $voucher_id, VoucherStatus $status): void
    {
        update_post_meta($voucher_id, '_fp_exp_gift_status', $status->toString());
        $this->syncTable($voucher_id);
    }

    /**
     * Get voucher code.
     */
    public function getCode(int $voucher_id): ?VoucherCode
    {
        $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));

        if ('' === $code) {
            return null;
        }

        try {
            return VoucherCode::fromString($code);
        } catch (\InvalidArgumentException $exception) {
            return null;
        }
    }

    /**
     * Get experience ID associated with voucher.
     */
    public function getExperienceId(int $voucher_id): int
    {
        return absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
    }

    /**
     * Get voucher quantity.
     */
    public function getQuantity(int $voucher_id): int
    {
        return absint((string) get_post_meta($voucher_id, '_fp_exp_gift_quantity', true));
    }

    /**
     * Get voucher value.
     */
    public function getValue(int $voucher_id): float
    {
        return (float) get_post_meta($voucher_id, '_fp_exp_gift_value', true);
    }

    /**
     * Get voucher currency.
     */
    public function getCurrency(int $voucher_id): string
    {
        return sanitize_text_field((string) get_post_meta($voucher_id, '_fp_exp_gift_currency', true));
    }

    /**
     * Get voucher valid until timestamp.
     */
    public function getValidUntil(int $voucher_id): int
    {
        return absint((string) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true));
    }

    /**
     * Get voucher addons.
     *
     * @return array<string, int>
     */
    public function getAddons(int $voucher_id): array
    {
        $addons = get_post_meta($voucher_id, '_fp_exp_gift_addons', true);

        return is_array($addons) ? $addons : [];
    }

    /**
     * Get voucher purchaser data.
     *
     * @return array<string, string>
     */
    public function getPurchaser(int $voucher_id): array
    {
        $purchaser = get_post_meta($voucher_id, '_fp_exp_gift_purchaser', true);

        return is_array($purchaser) ? $purchaser : [];
    }

    /**
     * Get voucher recipient data.
     *
     * @return array<string, string>
     */
    public function getRecipient(int $voucher_id): array
    {
        $recipient = get_post_meta($voucher_id, '_fp_exp_gift_recipient', true);

        return is_array($recipient) ? $recipient : [];
    }

    /**
     * Get voucher delivery data.
     *
     * @return array<string, mixed>
     */
    public function getDelivery(int $voucher_id): array
    {
        $delivery = get_post_meta($voucher_id, '_fp_exp_gift_delivery', true);

        return is_array($delivery) ? $delivery : [];
    }

    /**
     * Update delivery data.
     *
     * @param array<string, mixed> $delivery
     */
    public function updateDelivery(int $voucher_id, array $delivery): void
    {
        update_post_meta($voucher_id, '_fp_exp_gift_delivery', $delivery);
    }

    /**
     * Append log entry to voucher.
     */
    public function appendLog(int $voucher_id, string $event, ?int $order_id = null): void
    {
        $logs = get_post_meta($voucher_id, '_fp_exp_gift_logs', true);
        $logs = is_array($logs) ? $logs : [];

        $logs[] = [
            'event' => $event,
            'timestamp' => time(),
            'user' => get_current_user_id(),
            'order_id' => $order_id,
        ];

        update_post_meta($voucher_id, '_fp_exp_gift_logs', $logs);
    }

    /**
     * Get voucher logs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(int $voucher_id): array
    {
        $logs = get_post_meta($voucher_id, '_fp_exp_gift_logs', true);

        return is_array($logs) ? $logs : [];
    }

    /**
     * Sync voucher data to custom table.
     */
    public function syncTable(int $voucher_id): void
    {
        $voucher_id = absint($voucher_id);

        if ($voucher_id <= 0) {
            return;
        }

        $code = $this->getCode($voucher_id);

        if (! $code) {
            return;
        }

        $status = $this->getStatus($voucher_id);
        $experience_id = $this->getExperienceId($voucher_id);
        $valid_until = $this->getValidUntil($voucher_id);
        $value = $this->getValue($voucher_id);
        $currency = $this->getCurrency($voucher_id);
        $created = (int) get_post_time('U', true, $voucher_id, true);
        $modified = (int) get_post_modified_time('U', true, $voucher_id, true);

        VoucherTable::upsert([
            'voucher_id' => $voucher_id,
            'code' => $code->toString(),
            'status' => $status->toString(),
            'experience_id' => $experience_id,
            'valid_until' => $valid_until,
            'value' => $value,
            'currency' => $currency,
            'created_at' => $created ?: null,
            'updated_at' => $modified ?: time(),
        ]);
    }

    /**
     * Check if voucher is expired.
     */
    public function isExpired(int $voucher_id): bool
    {
        $valid_until = $this->getValidUntil($voucher_id);

        if ($valid_until <= 0) {
            return false;
        }

        $now = current_time('timestamp', true);

        return $valid_until < $now;
    }

    /**
     * Get reminders sent for voucher.
     *
     * @return array<int, int>
     */
    public function getRemindersSent(int $voucher_id): array
    {
        $sent = get_post_meta($voucher_id, '_fp_exp_gift_reminders_sent', true);

        return is_array($sent) ? array_map('absint', $sent) : [];
    }

    /**
     * Update reminders sent.
     *
     * @param array<int, int> $sent
     */
    public function updateRemindersSent(int $voucher_id, array $sent): void
    {
        update_post_meta($voucher_id, '_fp_exp_gift_reminders_sent', array_values(array_map('absint', $sent)));
    }
}















