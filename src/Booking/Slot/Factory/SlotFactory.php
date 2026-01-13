<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Factory;

use FP_Exp\Booking\Slot\Repository\SlotRepository;
use FP_Exp\Booking\Slot\ValueObjects\Slot;
use FP_Exp\Booking\Slot\ValueObjects\SlotCapacity;
use FP_Exp\Booking\Slot\ValueObjects\TimeRange;
use FP_Exp\Booking\Slot\Validator\SlotValidator;
use WP_Error;

use function absint;
use function get_post_meta;
use function is_array;

/**
 * Factory for creating slots.
 */
final class SlotFactory
{
    private SlotRepository $repository;
    private SlotValidator $validator;

    public function __construct(
        ?SlotRepository $repository = null,
        ?SlotValidator $validator = null
    ) {
        $this->repository = $repository ?? new SlotRepository();
        $this->validator = $validator ?? new SlotValidator();
    }

    /**
     * Create slot from data.
     *
     * @param array<string, mixed> $data
     *
     * @return Slot|WP_Error
     */
    public function create(array $data): Slot|WP_Error
    {
        $experience_id = absint((int) ($data['experience_id'] ?? 0));
        $start_utc = (string) ($data['start_datetime'] ?? '');
        $end_utc = (string) ($data['end_datetime'] ?? '');

        // Validate
        $validation = $this->validator->validate($data);

        if ($validation instanceof WP_Error) {
            return $validation;
        }

        // Get defaults from experience if not provided
        if (empty($data['capacity_total']) && $experience_id > 0) {
            $availability = get_post_meta($experience_id, '_fp_exp_availability', true);

            if (is_array($availability)) {
                $data['capacity_total'] = absint((int) ($availability['slot_capacity'] ?? 0));
                $data['capacity_per_type'] = $availability['capacity_per_type'] ?? [];
            }
        }

        // Create time range
        try {
            $time_range = TimeRange::fromUtcStrings($start_utc, $end_utc);
        } catch (\Throwable $exception) {
            return new WP_Error(
                'fp_exp_slot_invalid_time',
                'Invalid time range: ' . $exception->getMessage(),
                ['status' => 400]
            );
        }

        // Create capacity
        try {
            $capacity = SlotCapacity::fromArray([
                'total' => absint((int) ($data['capacity_total'] ?? 0)),
                'per_type' => $data['capacity_per_type'] ?? [],
            ]);
        } catch (\Throwable $exception) {
            return new WP_Error(
                'fp_exp_slot_invalid_capacity',
                'Invalid capacity: ' . $exception->getMessage(),
                ['status' => 400]
            );
        }

        // Create slot via repository
        $slot = $this->repository->create([
            'experience_id' => $experience_id,
            'start_datetime' => $time_range->getStartUtc(),
            'end_datetime' => $time_range->getEndUtc(),
            'capacity_total' => $capacity->getTotal(),
            'capacity_per_type' => $capacity->getPerType(),
            'status' => $data['status'] ?? 'open',
            'resource_lock' => $data['resource_lock'] ?? [],
            'price_rules' => $data['price_rules'] ?? [],
        ]);

        if (! $slot instanceof Slot) {
            return new WP_Error(
                'fp_exp_slot_create_failed',
                'Failed to create slot',
                ['status' => 500]
            );
        }

        return $slot;
    }

    /**
     * Ensure slot exists (create if missing).
     *
     * @return Slot|WP_Error
     */
    public function ensureSlot(int $experience_id, TimeRange $time_range): Slot|WP_Error
    {
        // Check if exists
        $existing = $this->repository->findByExperienceAndTime($experience_id, $time_range);

        if ($existing instanceof Slot) {
            return $existing;
        }

        // Create new
        return $this->create([
            'experience_id' => $experience_id,
            'start_datetime' => $time_range->getStartUtc(),
            'end_datetime' => $time_range->getEndUtc(),
        ]);
    }
}















