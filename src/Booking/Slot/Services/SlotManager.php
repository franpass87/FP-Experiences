<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Services;

use FP_Exp\Booking\Slot\Repository\SlotRepository;
use FP_Exp\Booking\Slot\Services\AvailabilityCalculator;
use FP_Exp\Booking\Slot\ValueObjects\Slot;
use FP_Exp\Booking\Slot\ValueObjects\SlotCapacity;
use FP_Exp\Booking\Slot\ValueObjects\TimeRange;
use FP_Exp\Utils\Helpers;
use WP_Error;

use function absint;
use function get_post_meta;
use function max;

/**
 * Service for managing slot operations.
 */
final class SlotManager
{
    private SlotRepository $repository;
    private CapacityManager $capacity_manager;
    private AvailabilityCalculator $availability_calculator;

    public function __construct(
        ?SlotRepository $repository = null,
        ?CapacityManager $capacity_manager = null,
        ?AvailabilityCalculator $availability_calculator = null
    ) {
        $this->repository = $repository ?? new SlotRepository();
        $this->capacity_manager = $capacity_manager ?? new CapacityManager();
        $this->availability_calculator = $availability_calculator ?? new AvailabilityCalculator($this->repository);
    }

    /**
     * Get slot by ID.
     */
    public function getSlot(int $slot_id): ?Slot
    {
        return $this->repository->findById($slot_id);
    }

    /**
     * Get slots in time range.
     *
     * @return array<int, Slot>
     */
    public function getSlotsInRange(TimeRange $time_range, array $args = []): array
    {
        return $this->repository->findByTimeRange($time_range, $args);
    }

    /**
     * Ensure slot exists for occurrence (create if missing).
     *
     * @return int|WP_Error Slot ID or WP_Error on failure
     */
    public function ensureSlotForOccurrence(int $experience_id, TimeRange $time_range): int|WP_Error
    {
        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return new WP_Error(
                'fp_exp_slot_invalid',
                'Invalid experience ID',
                ['experience_id' => $experience_id]
            );
        }

        // Check if slot already exists
        $existing = $this->repository->findByExperienceAndTime($experience_id, $time_range);

        if ($existing instanceof Slot) {
            Helpers::log_debug('slots', 'Slot already exists for occurrence', [
                'experience_id' => $experience_id,
                'slot_id' => $existing->getId(),
            ]);

            return $existing->getId();
        }

        // Get defaults from experience availability meta
        $availability = get_post_meta($experience_id, '_fp_exp_availability', true);
        $capacity_total = 0;
        $capacity_per_type = [];

        if (is_array($availability)) {
            $capacity_total = absint((int) ($availability['slot_capacity'] ?? 0));
            $capacity_per_type = $availability['capacity_per_type'] ?? [];
        }

        // Create slot
        $capacity = new SlotCapacity($capacity_total, $capacity_per_type);

        $slot_data = [
            'experience_id' => $experience_id,
            'start_datetime' => $time_range->getStartUtc(),
            'end_datetime' => $time_range->getEndUtc(),
            'capacity_total' => $capacity->getTotal(),
            'capacity_per_type' => $capacity->getPerType(),
            'status' => 'open',
        ];

        $slot = $this->repository->create($slot_data);

        if (! $slot instanceof Slot) {
            return new WP_Error(
                'fp_exp_slot_create_failed',
                'Failed to create slot',
                ['experience_id' => $experience_id]
            );
        }

        Helpers::log_debug('slots', 'Slot created for occurrence', [
            'experience_id' => $experience_id,
            'slot_id' => $slot->getId(),
        ]);

        return $slot->getId();
    }

    /**
     * Move slot to new time range.
     */
    public function moveSlot(int $slot_id, TimeRange $new_time_range): bool|WP_Error
    {
        $slot = $this->repository->findById($slot_id);

        if (! $slot instanceof Slot) {
            return new WP_Error(
                'fp_exp_slot_not_found',
                'Slot not found',
                ['slot_id' => $slot_id]
            );
        }

        // Create new slot with updated time range
        // Note: This is a simplified version - full implementation would need to handle reservations
        $updated_slot = new Slot(
            $slot->getId(),
            $slot->getExperienceId(),
            $new_time_range,
            $slot->getCapacity(),
            $slot->getStatus(),
            $slot->getMetadata()
        );

        return $this->repository->update($updated_slot);
    }

    /**
     * Update slot capacity.
     */
    public function updateCapacity(int $slot_id, int $total, array $per_type): bool|WP_Error
    {
        $slot = $this->repository->findById($slot_id);

        if (! $slot instanceof Slot) {
            return new WP_Error(
                'fp_exp_slot_not_found',
                'Slot not found',
                ['slot_id' => $slot_id]
            );
        }

        $new_capacity = $this->capacity_manager->updateCapacity($total, $per_type);

        if ($new_capacity instanceof WP_Error) {
            return $new_capacity;
        }

        // Create updated slot
        $updated_slot = new Slot(
            $slot->getId(),
            $slot->getExperienceId(),
            $slot->getTimeRange(),
            $new_capacity,
            $slot->getStatus(),
            $slot->getMetadata()
        );

        return $this->repository->update($updated_slot);
    }

    /**
     * Get upcoming slots for experience.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingForExperience(int $experience_id, int $limit = 20): array
    {
        return $this->availability_calculator->getUpcomingForExperience($experience_id, $limit);
    }

    /**
     * Get slots in range (legacy string format).
     *
     * @param string $start Start datetime
     * @param string $end End datetime
     * @param array $args Additional arguments
     * @return array<int, array<string, mixed>>
     * @deprecated Use getSlotsInRange(TimeRange $time_range) instead
     */
    public function getSlotsInRangeByStrings(string $start, string $end, array $args = []): array
    {
        return $this->availability_calculator->getSlotsInRange($start, $end, $args);
    }

    /**
     * Check if slot passes lead time.
     */
    public function passesLeadTime(int $slot_id, int $lead_time_hours): bool
    {
        return $this->availability_calculator->passesLeadTime($slot_id, $lead_time_hours);
    }

    /**
     * Check for buffer conflicts.
     */
    public function hasBufferConflict(
        int $experience_id,
        string $start_utc,
        string $end_utc,
        int $buffer_before_minutes = 0,
        int $buffer_after_minutes = 0,
        int $exclude_slot_id = 0
    ): bool {
        return $this->availability_calculator->hasBufferConflict(
            $experience_id,
            $start_utc,
            $end_utc,
            $buffer_before_minutes,
            $buffer_after_minutes,
            $exclude_slot_id
        );
    }
}

