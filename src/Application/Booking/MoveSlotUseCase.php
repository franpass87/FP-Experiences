<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Move a slot to a new time.
 */
final class MoveSlotUseCase
{
    private SlotRepositoryInterface $slotRepository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        SlotRepositoryInterface $slotRepository,
        ValidatorInterface $validator
    ) {
        $this->slotRepository = $slotRepository;
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
     * Move a slot to a new time.
     *
     * @param int $slot_id Slot ID
     * @param string $start_iso Start datetime in ISO format
     * @param string $end_iso End datetime in ISO format
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(int $slot_id, string $start_iso, string $end_iso)
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
            'start_iso' => $start_iso,
            'end_iso' => $end_iso,
        ]);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_slot_validation_failed',
                'Slot validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        // Validate datetime format
        try {
            $start = new \DateTimeImmutable($start_iso);
            $end = new \DateTimeImmutable($end_iso);
        } catch (\Throwable $e) {
            return new WP_Error(
                'fp_exp_slot_invalid_datetime',
                'Invalid datetime format',
                ['error' => $e->getMessage()]
            );
        }

        // Validate end is after start
        if ($end <= $start) {
            return new WP_Error(
                'fp_exp_slot_invalid_range',
                'End time must be after start time'
            );
        }

        // Update slot
        $success = $this->slotRepository->update($slot_id, [
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            return new WP_Error(
                'fp_exp_slot_move_failed',
                'Failed to move slot'
            );
        }

        // Log operation
        if ($this->logger !== null) {
            $this->logger->log('calendar', 'Slot moved', [
                'slot_id' => $slot_id,
                'start' => $start_iso,
                'end' => $end_iso,
            ]);
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
            'start_iso' => 'required|string',
            'end_iso' => 'required|string',
        ];

        return $this->validator->validate($data, $rules);
    }
}










