<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Cancel a reservation.
 */
final class CancelReservationUseCase
{
    private ReservationRepositoryInterface $reservationRepository;
    private SlotRepositoryInterface $slotRepository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        ReservationRepositoryInterface $reservationRepository,
        SlotRepositoryInterface $slotRepository,
        ValidatorInterface $validator
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->slotRepository = $slotRepository;
        $this->validator = $validator;
    }

    /**
     * Set logger (optional).
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Cancel a reservation.
     *
     * @param int $reservation_id Reservation ID
     * @param string $reason Cancellation reason (optional)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(int $reservation_id, string $reason = '')
    {
        // Validate reservation exists
        $reservation = $this->reservationRepository->findById($reservation_id);
        if ($reservation === null) {
            return new WP_Error(
                'fp_exp_reservation_not_found',
                'Reservation not found',
                ['reservation_id' => $reservation_id]
            );
        }

        // Check if already cancelled
        if (isset($reservation['status']) && $reservation['status'] === 'cancelled') {
            return new WP_Error(
                'fp_exp_reservation_already_cancelled',
                'Reservation is already cancelled'
            );
        }

        try {
            // Release slot capacity
            if (isset($reservation['slot_id'])) {
                $slot = $this->slotRepository->findById((int) $reservation['slot_id']);
                if ($slot !== null) {
                    // Update slot capacity (release reserved spots)
                    $quantity = (int) ($reservation['quantity'] ?? 1);
                    $currentCapacity = (int) ($slot['capacity'] ?? 0);
                    
                    $this->slotRepository->update((int) $reservation['slot_id'], [
                        'capacity' => $currentCapacity + $quantity,
                    ]);
                }
            }

            // Update reservation status
            $success = $this->reservationRepository->update($reservation_id, [
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql'),
                'cancellation_reason' => $reason,
            ]);

            if (!$success) {
                return new WP_Error(
                    'fp_exp_reservation_cancel_failed',
                    'Failed to cancel reservation'
                );
            }

            // Log cancellation
            if ($this->logger !== null) {
                $this->logger->log('booking', 'Reservation cancelled', [
                    'reservation_id' => $reservation_id,
                    'reason' => $reason,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->log('booking', 'Reservation cancellation error', [
                    'error' => $e->getMessage(),
                    'reservation_id' => $reservation_id,
                ]);
            }

            return new WP_Error(
                'fp_exp_reservation_cancel_exception',
                'Exception during cancellation: ' . $e->getMessage()
            );
        }
    }
}







