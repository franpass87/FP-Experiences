<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

use function array_sum;

/**
 * Use case: Update slot capacity.
 */
final class UpdateSlotCapacityUseCase
{
    private SlotRepositoryInterface $slotRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        SlotRepositoryInterface $slotRepository,
        ReservationRepositoryInterface $reservationRepository,
        ValidatorInterface $validator
    ) {
        $this->slotRepository = $slotRepository;
        $this->reservationRepository = $reservationRepository;
        $this->validator = $validator;
    }

    /**
     * Set logger (optional, for logging operations).
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Update slot capacity.
     *
     * @param int $slot_id Slot ID
     * @param int $total Total capacity
     * @param array<string, int> $per_type Capacity per type
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(int $slot_id, int $total, array $per_type = [])
    {
        // Validate slot exists
        $slot = $this->slotRepository->findById($slot_id);
        if ($slot === null) {
            return new WP_Error(
                'fp_exp_slot_not_found',
                'Slot not found',
                ['slot_id' => $slot_id]
            );
        }

        // Validate input
        $validation = $this->validateInput([
            'total' => $total,
            'per_type' => $per_type,
        ]);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_slot_validation_failed',
                'Slot validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        // Check existing reservations to ensure we don't set capacity below booked
        $reservations = $this->reservationRepository->findBySlotId($slot_id);
        $booked = 0;
        foreach ($reservations as $reservation) {
            $pax = $reservation['pax'] ?? [];
            if (is_array($pax)) {
                $booked += array_sum($pax);
            } else {
                $booked += 1;
            }
        }

        if ($total < $booked) {
            return new WP_Error(
                'fp_exp_slot_capacity_too_low',
                'Cannot set capacity below existing reservations',
                [
                    'total' => $total,
                    'booked' => $booked,
                ]
            );
        }

        // Update slot
        $success = $this->slotRepository->update($slot_id, [
            'capacity_total' => $total,
            'capacity_per_type' => $per_type,
        ]);

        if (!$success) {
            return new WP_Error(
                'fp_exp_slot_capacity_update_failed',
                'Failed to update slot capacity'
            );
        }

        // Log operation
        if ($this->logger !== null) {
            $this->logger->log('calendar', 'Slot capacity updated', [
                'slot_id' => $slot_id,
                'capacity_total' => $total,
                'capacity_per_type' => $per_type,
            ]);
        }

        return true;
    }

    /**
     * Validate slot capacity input data.
     *
     * @param array<string, mixed> $data Slot data
     * @return ValidationResult Validation result
     */
    private function validateInput(array $data): ValidationResult
    {
        $rules = [
            'total' => 'required|integer|min:0',
            'per_type' => 'array',
        ];

        return $this->validator->validate($data, $rules);
    }
}

