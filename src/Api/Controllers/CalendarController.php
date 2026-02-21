<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use DateTimeImmutable;
use FP_Exp\Api\Middleware\ErrorHandlingMiddleware;
use FP_Exp\Application\Booking\GetSlotsUseCase;
use FP_Exp\Application\Booking\MoveSlotUseCase;
use FP_Exp\Application\Booking\UpdateSlotCapacityUseCase;
use FP_Exp\Booking\Recurrence;
use FP_Exp\Booking\Slots;
use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function absint;
use function array_map;
use function get_current_user_id;
use function is_array;
use function rest_ensure_response;
use function sanitize_key;
use function get_the_title;
use function sanitize_text_field;

use const MINUTE_IN_SECONDS;

/**
 * Controller for calendar and slots REST API endpoints.
 */
final class CalendarController
{
    private ?GetSlotsUseCase $getSlotsUseCase = null;
    private ?MoveSlotUseCase $moveSlotUseCase = null;
    private ?UpdateSlotCapacityUseCase $updateSlotCapacityUseCase = null;

    /**
     * Get calendar slots.
     * 
     * Uses new GetSlotsUseCase if available, falls back to legacy Slots class.
     */
    public function getSlots(WP_REST_Request $request): WP_REST_Response
    {
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));
        $experience = absint((string) $request->get_param('experience'));

        if (! $start || ! $end) {
            return ErrorHandlingMiddleware::badRequest(
                __('Provide a valid date range.', 'fp-experiences')
            );
        }

        // Try to use new use case if available
        $useCase = $this->getGetSlotsUseCase();
        if ($useCase !== null) {
            try {
                $startDateTime = new DateTimeImmutable($start . ' 00:00:00');
                $endDateTime = new DateTimeImmutable($end . ' 23:59:59');
                $filters = $experience > 0 ? ['experience_id' => $experience] : [];
                $slots = $useCase->execute($startDateTime, $endDateTime, $filters);
            } catch (\Throwable $e) {
                // Fall through to legacy implementation
                $slots = Slots::get_slots_in_range($start, $end, [
                    'experience_id' => $experience,
                ]);
            }
        } else {
            // Fallback to legacy Slots class
            $slots = Slots::get_slots_in_range($start, $end, [
                'experience_id' => $experience,
            ]);
        }

        $payload = array_map(
            static function (array $slot): array {
                $per_type = [];

                if (isset($slot['capacity_per_type']) && is_array($slot['capacity_per_type'])) {
                    foreach ($slot['capacity_per_type'] as $type => $amount) {
                        $per_type[sanitize_key((string) $type)] = (int) $amount;
                    }
                }

                $title = (string) ($slot['experience_title'] ?? '');
                $exp_id = (int) ($slot['experience_id'] ?? 0);
                if ($title === '' && $exp_id > 0) {
                    $title = get_the_title($exp_id);
                }

                return [
                    'id' => (int) ($slot['id'] ?? 0),
                    'experience_id' => $exp_id,
                    'experience_title' => sanitize_text_field($title),
                    'start' => sanitize_text_field((string) ($slot['start_datetime'] ?? '')),
                    'end' => sanitize_text_field((string) ($slot['end_datetime'] ?? '')),
                    'capacity_total' => (int) ($slot['capacity_total'] ?? 0),
                    'capacity_per_type' => $per_type,
                    'remaining' => (int) ($slot['remaining'] ?? 0),
                    'reserved' => (int) ($slot['reserved_total'] ?? 0),
                    'duration' => sanitize_text_field((string) ($slot['duration'] ?? '')),
                ];
            },
            $slots
        );

        return ErrorHandlingMiddleware::success([
            'slots' => $payload,
        ]);
    }

    /**
     * Get GetSlotsUseCase from container if available.
     */
    private function getGetSlotsUseCase(): ?GetSlotsUseCase
    {
        if ($this->getSlotsUseCase !== null) {
            return $this->getSlotsUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(GetSlotsUseCase::class)) {
                return null;
            }

            $this->getSlotsUseCase = $container->make(GetSlotsUseCase::class);
            return $this->getSlotsUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Move calendar slot.
     */
    public function moveSlot(WP_REST_Request $request): WP_REST_Response
    {
        $slot_id = absint((string) $request->get_param('id'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));

        if ($slot_id <= 0 || ! $start || ! $end) {
            return ErrorHandlingMiddleware::badRequest(
                __('Missing slot data.', 'fp-experiences')
            );
        }

        if (Helpers::hit_rate_limit('calendar_move_' . get_current_user_id(), 10, MINUTE_IN_SECONDS)) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_rate_limited',
                    __('Troppe modifiche al calendario in poco tempo. Riprova tra qualche istante.', 'fp-experiences'),
                    ['status' => 429]
                )
            );
        }

        // Try to use new use case if available
        $useCase = $this->getMoveSlotUseCase();
        if ($useCase !== null) {
            try {
                $result = $useCase->execute($slot_id, $start, $end);
                if (is_wp_error($result)) {
                    return ErrorHandlingMiddleware::handleError($result);
                }
                return ErrorHandlingMiddleware::success(['success' => true]);
            } catch (\Throwable $e) {
                // Fall through to legacy implementation
            }
        }

        // Fallback to legacy Slots class
        $moved = Slots::move_slot($slot_id, $start, $end);

        if (! $moved) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_calendar_move_failed',
                    __('Unable to move the slot to the requested time.', 'fp-experiences'),
                    ['status' => 409]
                )
            );
        }

        Logger::log('calendar', 'Slot moved', [
            'slot_id' => $slot_id,
            'start' => $start,
            'end' => $end,
        ]);

        return ErrorHandlingMiddleware::success(['success' => true]);
    }

    /**
     * Update slot capacity.
     */
    public function updateCapacity(WP_REST_Request $request): WP_REST_Response
    {
        $slot_id = absint((string) $request->get_param('id'));
        $total = absint((string) $request->get_param('capacity_total'));
        $per_type = $request->get_param('capacity_per_type');

        if ($slot_id <= 0) {
            return ErrorHandlingMiddleware::badRequest(
                __('Invalid slot identifier.', 'fp-experiences')
            );
        }

        if (! is_array($per_type)) {
            $per_type = [];
        }

        if (Helpers::hit_rate_limit('calendar_capacity_' . get_current_user_id(), 10, MINUTE_IN_SECONDS)) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_rate_limited',
                    __('Attendi prima di modificare nuovamente la capacità.', 'fp-experiences'),
                    ['status' => 429]
                )
            );
        }

        // Try to use new use case if available
        $useCase = $this->getUpdateSlotCapacityUseCase();
        if ($useCase !== null) {
            try {
                $result = $useCase->execute($slot_id, $total, $per_type);
                if (is_wp_error($result)) {
                    return ErrorHandlingMiddleware::handleError($result);
                }
                return ErrorHandlingMiddleware::success(['success' => true]);
            } catch (\Throwable $e) {
                // Fall through to legacy implementation
            }
        }

        // Fallback to legacy Slots class
        $updated = Slots::update_capacity($slot_id, $total, $per_type);

        if (! $updated) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_calendar_capacity_failed',
                    __('Unable to update capacity. Check reservations before lowering limits.', 'fp-experiences'),
                    ['status' => 409]
                )
            );
        }

        Logger::log('calendar', 'Slot capacity updated', [
            'slot_id' => $slot_id,
            'capacity_total' => $total,
            'capacity_per_type' => $per_type,
        ]);

        return ErrorHandlingMiddleware::success(['success' => true]);
    }

    /**
     * Preview recurrence slots.
     */
    public function previewRecurrence(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return ErrorHandlingMiddleware::badRequest(
                __('Invalid recurrence payload.', 'fp-experiences')
            );
        }

        $experience_id = isset($body['experience_id']) ? absint((string) $body['experience_id']) : 0;
        $recurrence_raw = isset($body['recurrence']) && is_array($body['recurrence']) ? $body['recurrence'] : [];
        $availability = isset($body['availability']) && is_array($body['availability']) ? $body['availability'] : [];

        $recurrence = Recurrence::sanitize($recurrence_raw);

        if (! Recurrence::is_actionable($recurrence)) {
            return ErrorHandlingMiddleware::success([
                'preview' => [],
            ]);
        }

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => absint((string) ($availability['slot_capacity'] ?? 0)),
            'buffer_before_minutes' => absint((string) ($availability['buffer_before_minutes'] ?? 0)),
            'buffer_after_minutes' => absint((string) ($availability['buffer_after_minutes'] ?? 0)),
        ]);

        if (empty($rules)) {
            return ErrorHandlingMiddleware::success([
                'preview' => [],
            ]);
        }

        $default_capacity = absint((string) ($availability['slot_capacity'] ?? 0));
        $default_buffer_before = absint((string) ($availability['buffer_before_minutes'] ?? 0));
        $default_buffer_after = absint((string) ($availability['buffer_after_minutes'] ?? 0));

        foreach ($rules as $rule) {
            if (0 === $default_capacity && isset($rule['capacity_total'])) {
                $default_capacity = absint((string) $rule['capacity_total']);
            }

            if (0 === $default_buffer_before && isset($rule['buffer_before'])) {
                $default_buffer_before = absint((string) $rule['buffer_before']);
            }

            if (0 === $default_buffer_after && isset($rule['buffer_after'])) {
                $default_buffer_after = absint((string) $rule['buffer_after']);
            }
        }

        $options = [
            'default_duration' => absint((string) ($recurrence['duration'] ?? 60)),
            'default_capacity' => $default_capacity,
            'buffer_before' => $default_buffer_before,
            'buffer_after' => $default_buffer_after,
        ];

        $preview = Slots::preview_recurring_slots($experience_id, $rules, [], $options, 12);

        return ErrorHandlingMiddleware::success([
            'preview' => $preview,
        ]);
    }

    /**
     * Get MoveSlotUseCase from container if available.
     */
    private function getMoveSlotUseCase(): ?MoveSlotUseCase
    {
        if ($this->moveSlotUseCase !== null) {
            return $this->moveSlotUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(MoveSlotUseCase::class)) {
                return null;
            }

            $this->moveSlotUseCase = $container->make(MoveSlotUseCase::class);
            return $this->moveSlotUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get UpdateSlotCapacityUseCase from container if available.
     */
    private function getUpdateSlotCapacityUseCase(): ?UpdateSlotCapacityUseCase
    {
        if ($this->updateSlotCapacityUseCase !== null) {
            return $this->updateSlotCapacityUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(UpdateSlotCapacityUseCase::class)) {
                return null;
            }

            $this->updateSlotCapacityUseCase = $container->make(UpdateSlotCapacityUseCase::class);
            return $this->updateSlotCapacityUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Generate recurrence slots.
     */
    public function generateRecurrence(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return ErrorHandlingMiddleware::badRequest(
                __('Invalid recurrence payload.', 'fp-experiences')
            );
        }

        $experience_id = isset($body['experience_id']) ? absint((string) $body['experience_id']) : 0;

        if ($experience_id <= 0) {
            return ErrorHandlingMiddleware::badRequest(
                __('Select a valid experience before generating slots.', 'fp-experiences')
            );
        }

        $recurrence_raw = isset($body['recurrence']) && is_array($body['recurrence']) ? $body['recurrence'] : [];
        $availability = isset($body['availability']) && is_array($body['availability']) ? $body['availability'] : [];

        $recurrence = Recurrence::sanitize($recurrence_raw);

        if (! Recurrence::is_actionable($recurrence)) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_recurrence_invalid',
                    __('Configure at least one valid time slot for the recurrence.', 'fp-experiences'),
                    ['status' => 422]
                )
            );
        }

        $rules = Recurrence::build_rules($recurrence, [
            'slot_capacity' => absint((string) ($availability['slot_capacity'] ?? 0)),
            'buffer_before_minutes' => absint((string) ($availability['buffer_before_minutes'] ?? 0)),
            'buffer_after_minutes' => absint((string) ($availability['buffer_after_minutes'] ?? 0)),
        ]);

        if (empty($rules)) {
            return ErrorHandlingMiddleware::handleError(
                new WP_Error(
                    'fp_exp_recurrence_rules',
                    __('Unable to build recurrence rules from the provided data.', 'fp-experiences'),
                    ['status' => 422]
                )
            );
        }

        $default_capacity = absint((string) ($availability['slot_capacity'] ?? 0));
        $default_buffer_before = absint((string) ($availability['buffer_before_minutes'] ?? 0));
        $default_buffer_after = absint((string) ($availability['buffer_after_minutes'] ?? 0));

        foreach ($rules as $rule) {
            if (0 === $default_capacity && isset($rule['capacity_total'])) {
                $default_capacity = absint((string) $rule['capacity_total']);
            }

            if (0 === $default_buffer_before && isset($rule['buffer_before'])) {
                $default_buffer_before = absint((string) $rule['buffer_before']);
            }

            if (0 === $default_buffer_after && isset($rule['buffer_after'])) {
                $default_buffer_after = absint((string) $rule['buffer_after']);
            }
        }

        $options = [
            'default_duration' => absint((string) ($recurrence['duration'] ?? 60)),
            'default_capacity' => $default_capacity,
            'buffer_before' => $default_buffer_before,
            'buffer_after' => $default_buffer_after,
            'replace_existing' => ! empty($body['replace_existing']),
        ];

        $created = Slots::generate_recurring_slots($experience_id, $rules, [], $options);
        $preview = Slots::preview_recurring_slots($experience_id, $rules, [], $options, 12);

        Logger::log('calendar', 'Recurrence slots generated', [
            'experience_id' => $experience_id,
            'created' => $created,
        ]);

        return ErrorHandlingMiddleware::success([
            'created' => $created,
            'preview' => $preview,
        ]);
    }
}






