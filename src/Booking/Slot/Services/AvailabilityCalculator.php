<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Services;

use FP_Exp\Booking\Slot\Repository\SlotRepository;
use FP_Exp\Booking\Slot\ValueObjects\TimeRange;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

use function absint;
use function is_array;
use function max;
use function wp_timezone;

/**
 * Service for calculating availability.
 */
final class AvailabilityCalculator
{
    private SlotRepository $repository;

    public function __construct(?SlotRepository $repository = null)
    {
        $this->repository = $repository ?? new SlotRepository();
    }

    /**
     * Get upcoming slots for experience.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingForExperience(int $experience_id, int $limit = 20): array
    {
        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return [];
        }

        $now = new DateTimeImmutable('now', wp_timezone());
        $future = $now->modify('+1 year');

        $time_range = new TimeRange($now, $future);
        $slots = $this->repository->findByTimeRange($time_range, [
            'experience_id' => $experience_id,
            'status' => 'open',
        ]);

        // Sort by start time and limit
        usort($slots, static function ($a, $b) {
            return $a->getTimeRange()->getStart() <=> $b->getTimeRange()->getStart();
        });

        $limited = array_slice($slots, 0, $limit);

        // Convert to array format for backward compatibility
        return array_map(static function ($slot) {
            return $slot->toArray();
        }, $limited);
    }

    /**
     * Get slots in range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlotsInRange(string $start, string $end, array $args = []): array
    {
        try {
            $start_dt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            $start_dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }

        try {
            $end_dt = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            $end_dt = $start_dt;
        }

        if ($end_dt < $start_dt) {
            $end_dt = $start_dt;
        }

        $time_range = new TimeRange($start_dt, $end_dt);

        $statuses = isset($args['statuses']) && is_array($args['statuses']) ? $args['statuses'] : ['open', 'closed'];
        $experience_id = isset($args['experience_id']) ? absint((int) $args['experience_id']) : 0;

        $slots = $this->repository->findByTimeRange($time_range, [
            'experience_id' => $experience_id,
        ]);

        // Filter by status if needed
        if (! empty($statuses)) {
            $slots = array_filter($slots, static function ($slot) use ($statuses) {
                return in_array($slot->getStatus(), $statuses, true);
            });
        }

        // Convert to array format for backward compatibility
        $result = [];

        foreach ($slots as $slot) {
            $array = $slot->toArray();
            // Add additional fields for backward compatibility
            $array['duration'] = $this->calculateDurationMinutes(
                $slot->getTimeRange()->getStartUtc(),
                $slot->getTimeRange()->getEndUtc()
            );
            $result[] = $array;
        }

        return $result;
    }

    /**
     * Calculate duration in minutes.
     */
    private function calculateDurationMinutes(string $start, string $end): int
    {
        try {
            $start_dt = new DateTimeImmutable($start);
            $end_dt = new DateTimeImmutable($end);
        } catch (Exception $exception) {
            return 0;
        }

        $diff = $end_dt->diff($start_dt);

        return (int) ($diff->days * 24 * 60 + $diff->h * 60 + $diff->i);
    }

    /**
     * Check if slot passes lead time requirement.
     */
    public function passesLeadTime(int $slot_id, int $lead_time_hours): bool
    {
        $slot = $this->repository->findById($slot_id);

        if (! $slot) {
            return false;
        }

        $now = new DateTimeImmutable('now', wp_timezone());
        $slot_start = $slot->getTimeRange()->getStart();
        $required_time = $now->modify("+{$lead_time_hours} hours");

        return $slot_start >= $required_time;
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
        $experience_id = absint($experience_id);

        if ($experience_id <= 0) {
            return false;
        }

        try {
            $start = new DateTimeImmutable($start_utc, new DateTimeZone('UTC'));
            $end = new DateTimeImmutable($end_utc, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return false;
        }

        // Apply buffers
        $check_start = $buffer_before_minutes > 0
            ? $start->modify("-{$buffer_before_minutes} minutes")
            : $start;

        $check_end = $buffer_after_minutes > 0
            ? $end->modify("+{$buffer_after_minutes} minutes")
            : $end;

        $time_range = new TimeRange($check_start, $check_end);
        $conflicting_slots = $this->repository->findByTimeRange($time_range, [
            'experience_id' => $experience_id,
        ]);

        // Filter out the excluded slot
        if ($exclude_slot_id > 0) {
            $conflicting_slots = array_filter($conflicting_slots, static function ($slot) use ($exclude_slot_id) {
                return $slot->getId() !== $exclude_slot_id;
            });
        }

        return ! empty($conflicting_slots);
    }
}















