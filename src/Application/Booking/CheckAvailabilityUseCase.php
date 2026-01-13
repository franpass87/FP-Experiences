<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use DateTimeInterface;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;

/**
 * Use case: Check availability for an experience.
 */
final class CheckAvailabilityUseCase
{
    private ExperienceRepositoryInterface $experienceRepository;
    private SlotRepositoryInterface $slotRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        ExperienceRepositoryInterface $experienceRepository,
        SlotRepositoryInterface $slotRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->experienceRepository = $experienceRepository;
        $this->slotRepository = $slotRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Check availability for an experience in a date range.
     *
     * @param int $experience_id Experience ID
     * @param DateTimeInterface $start Start date
     * @param DateTimeInterface $end End date
     * @return array<int, array<string, mixed>> Array of available slots with capacity info
     */
    public function execute(int $experience_id, DateTimeInterface $start, DateTimeInterface $end): array
    {
        // Get experience
        $experience = $this->experienceRepository->findById($experience_id);
        if ($experience === null) {
            return [];
        }

        // Get slots in date range
        $slots = $this->slotRepository->findByExperienceAndDateRange($experience_id, $start, $end);

        // Enrich slots with availability data
        $result = [];
        foreach ($slots as $slot) {
            $slot_id = (int) ($slot['id'] ?? 0);
            if ($slot_id <= 0) {
                continue;
            }

            // Get reservations for this slot
            $reservations = $this->reservationRepository->findBySlotId($slot_id);
            
            // Calculate booked capacity
            $booked = 0;
            foreach ($reservations as $reservation) {
                $pax = $reservation['pax'] ?? [];
                if (is_array($pax)) {
                    $booked += array_sum($pax);
                } else {
                    $booked += 1; // Default to 1 if pax is not an array
                }
            }

            $capacity_total = (int) ($slot['capacity_total'] ?? 0);
            $remaining = max(0, $capacity_total - $booked);

            $result[] = [
                'id' => $slot_id,
                'start_datetime' => $slot['start_datetime'] ?? '',
                'end_datetime' => $slot['end_datetime'] ?? '',
                'capacity_total' => $capacity_total,
                'booked' => $booked,
                'remaining' => $remaining,
                'available' => $remaining > 0,
            ];
        }

        return $result;
    }
}










