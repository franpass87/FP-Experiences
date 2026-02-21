<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use DateTimeInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;

use function array_sum;

/**
 * Use case: Get slots in a date range.
 */
final class GetSlotsUseCase
{
    private SlotRepositoryInterface $slotRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        SlotRepositoryInterface $slotRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->slotRepository = $slotRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Get slots in a date range with availability information.
     *
     * @param DateTimeInterface $start Start date
     * @param DateTimeInterface $end End date
     * @param array<string, mixed> $filters Optional filters (experience_id, etc.)
     * @return array<int, array<string, mixed>> Array of slots with availability data
     */
    public function execute(DateTimeInterface $start, DateTimeInterface $end, array $filters = []): array
    {
        $experience_id = isset($filters['experience_id']) ? (int) $filters['experience_id'] : 0;

        if ($experience_id > 0) {
            $slots = $this->slotRepository->findByExperienceAndDateRange($experience_id, $start, $end);
        } else {
            $slots = $this->slotRepository->findByDateRange($start, $end);
        }

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

            $result[] = array_merge($slot, [
                'remaining' => $remaining,
                'reserved_total' => $booked,
                'reservations' => $reservations,
            ]);
        }

        return $result;
    }
}

