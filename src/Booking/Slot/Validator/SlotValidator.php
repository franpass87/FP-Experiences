<?php

declare(strict_types=1);

namespace FP_Exp\Booking\Slot\Validator;

use WP_Error;

use function absint;
use function is_array;
use function is_numeric;
use function is_string;
use function sanitize_key;

/**
 * Validator for slot data.
 */
final class SlotValidator
{
    /**
     * Validate slot data.
     *
     * @param array<string, mixed> $data
     *
     * @return true|WP_Error
     */
    public function validate(array $data): bool|WP_Error
    {
        $experience_id = isset($data['experience_id']) ? absint((int) $data['experience_id']) : 0;

        if ($experience_id <= 0) {
            return new WP_Error(
                'fp_exp_slot_invalid_experience',
                'Invalid experience ID',
                ['status' => 400]
            );
        }

        $start_utc = isset($data['start_datetime']) && is_string($data['start_datetime']) ? $data['start_datetime'] : '';
        $end_utc = isset($data['end_datetime']) && is_string($data['end_datetime']) ? $data['end_datetime'] : '';

        if ('' === $start_utc || '' === $end_utc) {
            return new WP_Error(
                'fp_exp_slot_invalid_time',
                'Start and end datetime are required',
                ['status' => 400]
            );
        }

        // Validate datetime format
        $start_timestamp = strtotime($start_utc);
        $end_timestamp = strtotime($end_utc);

        if ($start_timestamp === false || $end_timestamp === false) {
            return new WP_Error(
                'fp_exp_slot_invalid_time_format',
                'Invalid datetime format',
                ['status' => 400]
            );
        }

        if ($end_timestamp <= $start_timestamp) {
            return new WP_Error(
                'fp_exp_slot_invalid_time_range',
                'End time must be after start time',
                ['status' => 400]
            );
        }

        // Validate capacity
        $capacity_total = isset($data['capacity_total']) && is_numeric($data['capacity_total']) ? absint((int) $data['capacity_total']) : 0;

        if ($capacity_total < 0) {
            return new WP_Error(
                'fp_exp_slot_invalid_capacity',
                'Capacity cannot be negative',
                ['status' => 400]
            );
        }

        // Validate per_type capacity
        $capacity_per_type = isset($data['capacity_per_type']) && is_array($data['capacity_per_type']) ? $data['capacity_per_type'] : [];

        if (! empty($capacity_per_type)) {
            $per_type_sum = 0;

            foreach ($capacity_per_type as $type => $value) {
                if (! is_numeric($value)) {
                    continue;
                }

                $per_type_sum += absint((int) $value);
            }

            if ($capacity_total > 0 && $per_type_sum > $capacity_total) {
                return new WP_Error(
                    'fp_exp_slot_invalid_capacity',
                    'Per-type capacity sum cannot exceed total capacity',
                    ['status' => 400]
                );
            }
        }

        // Validate status
        $status = isset($data['status']) ? sanitize_key((string) $data['status']) : 'open';

        if (! in_array($status, ['open', 'closed', 'cancelled'], true)) {
            return new WP_Error(
                'fp_exp_slot_invalid_status',
                'Invalid status',
                ['status' => 400]
            );
        }

        return true;
    }
}















