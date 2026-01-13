<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Services;

use FP_Exp\Booking\Slot\ValueObjects\SlotCapacity;
use WP_Error;

use function absint;
use function array_sum;
use function is_array;
use function max;

/**
 * Service for managing slot capacity.
 */
final class CapacityManager
{
    /**
     * Check if requested capacity is available.
     *
     * @param array<string, int> $requested Requested capacity per type
     *
     * @return array{available: bool, remaining: array<string, int>, errors: array<string>}
     */
    public function checkCapacity(SlotCapacity $capacity, array $requested): array
    {
        $available = true;
        $errors = [];
        $remaining = [];

        $total_requested = array_sum($requested);

        // Check total capacity
        if ($total_requested > $capacity->getTotal()) {
            $available = false;
            $errors[] = sprintf(
                'Requested total capacity (%d) exceeds available (%d)',
                $total_requested,
                $capacity->getTotal()
            );
        }

        // Check per-type capacity
        foreach ($requested as $type => $quantity) {
            $type_capacity = $capacity->getForType($type);
            $remaining[$type] = max(0, $type_capacity - $quantity);

            if ($quantity > $type_capacity) {
                $available = false;
                $errors[] = sprintf(
                    'Requested capacity for type "%s" (%d) exceeds available (%d)',
                    $type,
                    $quantity,
                    $type_capacity
                );
            }
        }

        return [
            'available' => $available,
            'remaining' => $remaining,
            'errors' => $errors,
        ];
    }

    /**
     * Update capacity for slot.
     *
     * @param array<string, int> $per_type
     *
     * @return SlotCapacity|WP_Error
     */
    public function updateCapacity(int $total, array $per_type): SlotCapacity|WP_Error
    {
        $total = absint($total);

        if ($total < 0) {
            return new WP_Error(
                'fp_exp_capacity_invalid',
                'Total capacity cannot be negative.',
                ['status' => 400]
            );
        }

        // Validate per_type sum doesn't exceed total
        $per_type_sum = array_sum($per_type);

        if ($per_type_sum > $total) {
            return new WP_Error(
                'fp_exp_capacity_invalid',
                'Per-type capacity sum cannot exceed total capacity.',
                ['status' => 400]
            );
        }

        try {
            return new SlotCapacity($total, $per_type);
        } catch (\Throwable $exception) {
            return new WP_Error(
                'fp_exp_capacity_error',
                $exception->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get capacity snapshot (reserved/remaining).
     *
     * Delegates to Slots::get_capacity_snapshot() which aggregates
     * reservations for the given slot, counting total and per-type bookings.
     *
     * @return array{total: int, per_type: array<string, int>}
     */
    public function getCapacitySnapshot(int $slot_id): array
    {
        if ($slot_id <= 0) {
            return [
                'total' => 0,
                'per_type' => [],
            ];
        }

        // Use the existing implementation from Slots class
        // which handles reservation aggregation, hold expiration, and RTB blocks
        return \FP_Exp\Booking\Slots::get_capacity_snapshot($slot_id);
    }
}


