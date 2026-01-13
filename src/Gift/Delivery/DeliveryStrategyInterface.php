<?php

declare(strict_types=1);

namespace FP_Exp\Gift\Delivery;

/**
 * Interface for delivery strategies.
 *
 * Defines the contract for different voucher delivery strategies.
 */
interface DeliveryStrategyInterface
{
    /**
     * Deliver the voucher.
     */
    public function deliver(int $voucher_id): void;

    /**
     * Check if voucher should be delivered.
     */
    public function shouldDeliver(int $voucher_id): bool;

    /**
     * Get strategy name.
     */
    public function getName(): string;
}















