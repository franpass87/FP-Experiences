<?php

declare(strict_types=1);

namespace FP_Exp\Domain\Gift\Repositories;

use FP_Exp\Gift\ValueObjects\VoucherCode;
use FP_Exp\Gift\ValueObjects\VoucherStatus;
use WP_Post;

/**
 * Voucher repository interface.
 */
interface VoucherRepositoryInterface
{
    /**
     * Find voucher by code.
     *
     * @param VoucherCode $code Voucher code
     * @return WP_Post|null Voucher post or null if not found
     */
    public function findByCode(VoucherCode $code): ?WP_Post;

    /**
     * Find voucher by ID.
     *
     * @param int $id Voucher ID
     * @return WP_Post|null Voucher post or null if not found
     */
    public function findById(int $id): ?WP_Post;

    /**
     * Get vouchers by order ID.
     *
     * @param int $order_id Order ID
     * @return array<int, int> Array of voucher IDs
     */
    public function getVoucherIdsByOrder(int $order_id): array;

    /**
     * Get voucher status.
     *
     * @param int $voucher_id Voucher ID
     * @return VoucherStatus Voucher status
     */
    public function getStatus(int $voucher_id): VoucherStatus;

    /**
     * Update voucher status.
     *
     * @param int $voucher_id Voucher ID
     * @param VoucherStatus $status New status
     */
    public function updateStatus(int $voucher_id, VoucherStatus $status): void;

    /**
     * Get voucher code.
     *
     * @param int $voucher_id Voucher ID
     * @return VoucherCode|null Voucher code or null if not found
     */
    public function getCode(int $voucher_id): ?VoucherCode;

    /**
     * Get experience ID associated with voucher.
     *
     * @param int $voucher_id Voucher ID
     * @return int Experience ID or 0 if not found
     */
    public function getExperienceId(int $voucher_id): int;

    /**
     * Get voucher quantity.
     *
     * @param int $voucher_id Voucher ID
     * @return int Quantity
     */
    public function getQuantity(int $voucher_id): int;

    /**
     * Get voucher value.
     *
     * @param int $voucher_id Voucher ID
     * @return float Value
     */
    public function getValue(int $voucher_id): float;

    /**
     * Get voucher currency.
     *
     * @param int $voucher_id Voucher ID
     * @return string Currency code
     */
    public function getCurrency(int $voucher_id): string;

    /**
     * Get voucher valid until timestamp.
     *
     * @param int $voucher_id Voucher ID
     * @return int Unix timestamp or 0 if not set
     */
    public function getValidUntil(int $voucher_id): int;

    /**
     * Check if voucher is expired.
     *
     * @param int $voucher_id Voucher ID
     * @return bool True if expired, false otherwise
     */
    public function isExpired(int $voucher_id): bool;
}







