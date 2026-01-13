<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Services\Cache\CacheInterface;
use WP_Error;

/**
 * Use case: Get reservation details.
 */
final class GetReservationUseCase
{
    private ReservationRepositoryInterface $reservationRepository;
    private ExperienceRepositoryInterface $experienceRepository;
    private ?CacheInterface $cache = null;

    public function __construct(
        ReservationRepositoryInterface $reservationRepository,
        ExperienceRepositoryInterface $experienceRepository
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->experienceRepository = $experienceRepository;
    }

    /**
     * Set cache service (optional).
     */
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get reservation by ID.
     *
     * @param int $reservation_id Reservation ID
     * @return array<string, mixed>|WP_Error Reservation data or error
     */
    public function execute(int $reservation_id)
    {
        if ($reservation_id <= 0) {
            return new WP_Error(
                'fp_exp_invalid_reservation_id',
                'Invalid reservation ID'
            );
        }

        // Try cache first
        if ($this->cache !== null) {
            $cache_key = 'fp_exp_reservation_' . $reservation_id;
            $cached = $this->cache->get($cache_key);
            
            if ($cached !== null) {
                return $cached;
            }
        }

        // Get from repository
        $reservation = $this->reservationRepository->findById($reservation_id);

        if ($reservation === null) {
            return new WP_Error(
                'fp_exp_reservation_not_found',
                'Reservation not found',
                ['reservation_id' => $reservation_id]
            );
        }

        // Enrich with related data
        $reservation = $this->enrichReservationData($reservation);

        // Cache result
        if ($this->cache !== null) {
            $cache_key = 'fp_exp_reservation_' . $reservation_id;
            $this->cache->set($cache_key, $reservation, 1800); // 30 minutes
        }

        return $reservation;
    }

    /**
     * Enrich reservation data with related information.
     *
     * @param array<string, mixed> $reservation Base reservation data
     * @return array<string, mixed> Enriched reservation data
     */
    private function enrichReservationData(array $reservation): array
    {
        // Add experience data if available
        if (isset($reservation['experience_id'])) {
            $experience = $this->experienceRepository->findById((int) $reservation['experience_id']);
            if ($experience !== null) {
                $reservation['experience'] = $experience;
            }
        }

        // Add slot data if available
        // This would require SlotRepository - add if needed

        return $reservation;
    }
}







