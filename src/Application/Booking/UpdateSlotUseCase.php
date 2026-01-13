<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Update a slot.
 */
final class UpdateSlotUseCase
{
    private SlotRepositoryInterface $slotRepository;
    private ValidatorInterface $validator;

    public function __construct(
        SlotRepositoryInterface $slotRepository,
        ValidatorInterface $validator
    ) {
        $this->slotRepository = $slotRepository;
        $this->validator = $validator;
    }

    /**
     * Update a slot.
     *
     * @param int $slot_id Slot ID
     * @param array<string, mixed> $data Slot data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(int $slot_id, array $data)
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
        $validation = $this->validateInput($data);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_slot_validation_failed',
                'Slot validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        // Update slot
        $success = $this->slotRepository->update($slot_id, $data);

        if (!$success) {
            return new WP_Error(
                'fp_exp_slot_update_failed',
                'Failed to update slot'
            );
        }

        return true;
    }

    /**
     * Validate slot input data.
     *
     * @param array<string, mixed> $data Slot data
     * @return ValidationResult Validation result
     */
    private function validateInput(array $data): ValidationResult
    {
        $rules = [
            'start_datetime' => 'required|string',
            'end_datetime' => 'required|string',
            'capacity_total' => 'integer|min:0',
        ];

        return $this->validator->validate($data, $rules);
    }
}










