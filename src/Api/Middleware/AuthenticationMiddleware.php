<?php

declare(strict_types=1);

namespace FP_Exp\Api\Middleware;

use FP_Exp\Utils\Helpers;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Middleware for handling REST API authentication.
 *
 * Provides permission callbacks for different access levels.
 */
final class AuthenticationMiddleware
{
    /**
     * Public endpoint permission (with light verification).
     */
    public static function publicEndpoint(WP_REST_Request $request): bool
    {
        return Helpers::verify_public_rest_request($request);
    }

    /**
     * Operator permission (can operate FP).
     */
    public static function operatorPermission(): bool
    {
        return Helpers::can_operate_fp();
    }

    /**
     * Manager permission (can manage FP).
     */
    public static function managerPermission(): bool
    {
        return Helpers::can_manage_fp();
    }

    /**
     * Admin permission (WordPress admin).
     */
    public static function adminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Check if user is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Get current user ID.
     */
    public static function getCurrentUserId(): int
    {
        return get_current_user_id();
    }
}















