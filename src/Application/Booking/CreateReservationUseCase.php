<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Create a new reservation.
 */
final class CreateReservationUseCase
{
    private ExperienceRepositoryInterface $experienceRepository;
    private SlotRepositoryInterface $slotRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private ValidatorInterface $validator;

    public function __construct(
        ExperienceRepositoryInterface $experienceRepository,
        SlotRepositoryInterface $slotRepository,
        ReservationRepositoryInterface $reservationRepository,
        ValidatorInterface $validator
    ) {
        $this->experienceRepository = $experienceRepository;
        $this->slotRepository = $slotRepository;
        $this->reservationRepository = $reservationRepository;
        $this->validator = $validator;
    }

    /**
     * Create a new reservation.
     *
     * @param array<string, mixed> $data Reservation data
     * @return int|WP_Error Reservation ID on success, WP_Error on failure
     */
    public function execute(array $data)
    {
        // Validate input
        $validation = $this->validateInput($data);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_reservation_validation_failed',
                'Reservation validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        // Verify experience exists
        $experience_id = (int) ($data['experience_id'] ?? 0);
        $experience = $this->experienceRepository->findById($experience_id);
        if ($experience === null) {
            return new WP_Error(
                'fp_exp_experience_not_found',
                'Experience not found',
                ['experience_id' => $experience_id]
            );
        }

        // Verify slot exists
        $slot_id = (int) ($data['slot_id'] ?? 0);
        if ($slot_id > 0) {
            $slot = $this->slotRepository->findById($slot_id);
            if ($slot === null) {
                return new WP_Error(
                    'fp_exp_slot_not_found',
                    'Slot not found',
                    ['slot_id' => $slot_id]
                );
            }

            // Verify slot belongs to experience
            if ((int) ($slot['experience_id'] ?? 0) !== $experience_id) {
                return new WP_Error(
                    'fp_exp_slot_mismatch',
                    'Slot does not belong to experience',
                    ['slot_id' => $slot_id, 'experience_id' => $experience_id]
                );
            }
        }

        // Create reservation
        $reservation_id = $this->reservationRepository->create($data);

        if ($reservation_id <= 0) {
            return new WP_Error(
                'fp_exp_reservation_create_failed',
                'Failed to create reservation'
            );
        }

        return $reservation_id;
    }

    /**
     * Validate reservation input data.
     *
     * @param array<string, mixed> $data Reservation data
     * @return ValidationResult Validation result
     */
    private function validateInput(array $data): ValidationResult
    {
        $rules = [
            'experience_id' => 'required|integer|min:1',
            'slot_id' => 'required|integer|min:1',
            'order_id' => 'integer|min:0',
            'customer_id' => 'integer|min:0',
        ];

        return $this->validator->validate($data, $rules);
    }
}










