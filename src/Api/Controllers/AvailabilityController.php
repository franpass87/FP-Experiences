<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use FP_Exp\Api\Middleware\ErrorHandlingMiddleware;
use FP_Exp\Application\Booking\CheckAvailabilityUseCase;
use FP_Exp\Booking\AvailabilityService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function absint;
use function preg_match;
use function sanitize_text_field;
use function strtotime;

/**
 * Controller for availability REST API endpoints.
 */
final class AvailabilityController
{
    private ?CheckAvailabilityUseCase $checkAvailabilityUseCase = null;

    /**
     * Get virtual availability.
     * 
     * Uses new CheckAvailabilityUseCase if available, falls back to legacy AvailabilityService.
     */
    public function getVirtualAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $experience_id = absint((string) $request->get_param('experience'));
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end = sanitize_text_field((string) $request->get_param('end'));

        // Validation
        if ($experience_id <= 0 || ! $start || ! $end) {
            return ErrorHandlingMiddleware::badRequest(
                __('Parametri non validi.', 'fp-experiences')
            );
        }

        // Date format validation
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return ErrorHandlingMiddleware::badRequest(
                __('Formato data non valido. Usa YYYY-MM-DD.', 'fp-experiences')
            );
        }

        // Date range validation
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);

        if (false === $start_ts || false === $end_ts) {
            return ErrorHandlingMiddleware::badRequest(
                __('Le date fornite non sono valide.', 'fp-experiences')
            );
        }

        if ($end_ts < $start_ts) {
            return ErrorHandlingMiddleware::badRequest(
                __('La data di fine deve essere successiva alla data di inizio.', 'fp-experiences')
            );
        }

        // Try to use new use case if available
        $useCase = $this->getCheckAvailabilityUseCase();
        if ($useCase !== null) {
            try {
                $tz = new DateTimeZone('UTC');
                $startDateTime = new DateTimeImmutable($start . ' 00:00:00', $tz);
                $endDateTime = new DateTimeImmutable($end . ' 23:59:59', $tz);
                $availability = $useCase->execute($experience_id, $startDateTime, $endDateTime);
                
                // Normalizza il formato per compatibilità con il frontend
                // Il frontend si aspetta un array di slot con 'start' e 'end' (non 'start_datetime'/'end_datetime')
                if (is_array($availability)) {
                    $normalized = [];
                    foreach ($availability as $slot) {
                        // Se è già nel formato corretto, usa direttamente
                        if (isset($slot['start']) && isset($slot['end'])) {
                            $normalized[] = $slot;
                        }
                        // Altrimenti converte da start_datetime/end_datetime a start/end
                        elseif (isset($slot['start_datetime']) && isset($slot['end_datetime'])) {
                            $normalized[] = [
                                'date' => substr($slot['start_datetime'], 0, 10),
                                'start' => $slot['start_datetime'],
                                'end' => $slot['end_datetime'],
                                'capacity_total' => (int) ($slot['capacity_total'] ?? 0),
                                'capacity_remaining' => (int) ($slot['remaining'] ?? $slot['capacity_remaining'] ?? 0),
                                'available' => (bool) ($slot['available'] ?? (($slot['remaining'] ?? $slot['capacity_remaining'] ?? 0) > 0)),
                            ];
                        }
                    }
                    // Il frontend si aspetta { slots: [...] } non un array diretto
                    return ErrorHandlingMiddleware::success(['slots' => $normalized]);
                }
                
                return ErrorHandlingMiddleware::success($availability);
            } catch (\Throwable $e) {
                // Fall through to legacy implementation
            }
        }

        // Fallback to legacy AvailabilityService
        try {
            $availability = AvailabilityService::get_virtual_availability($experience_id, $start, $end);

            if (is_wp_error($availability)) {
                return ErrorHandlingMiddleware::handleError($availability);
            }

            // Ensure we return an array even if empty
            if (!is_array($availability)) {
                $availability = [];
            }

            // Il frontend si aspetta { slots: [...] } non un array diretto
            return ErrorHandlingMiddleware::success(['slots' => $availability]);
        } catch (\Throwable $e) {
            return ErrorHandlingMiddleware::handleError($e);
        }
    }

    /**
     * Get CheckAvailabilityUseCase from container if available.
     */
    private function getCheckAvailabilityUseCase(): ?CheckAvailabilityUseCase
    {
        if ($this->checkAvailabilityUseCase !== null) {
            return $this->checkAvailabilityUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(CheckAvailabilityUseCase::class)) {
                return null;
            }

            $this->checkAvailabilityUseCase = $container->make(CheckAvailabilityUseCase::class);
            return $this->checkAvailabilityUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }
}






