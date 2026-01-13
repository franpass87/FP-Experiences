<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\REST\Interfaces;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Interface for REST API controllers.
 */
interface ControllerInterface
{
    /**
     * Handle REST request.
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|\WP_Error Response or error
     */
    public function handle(WP_REST_Request $request);

    /**
     * Check permission for request.
     *
     * @param WP_REST_Request $request REST request
     * @return bool True if allowed
     */
    public function checkPermission(WP_REST_Request $request): bool;
}







