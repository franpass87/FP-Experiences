<?php

declare(strict_types=1);

namespace FP_Exp\Api\Middleware;

use FP_Exp\Utils\Logger;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function is_wp_error;
use function rest_ensure_response;
use function rest_authorization_required_code;
use function rest_forbidden_code;
use function rest_bad_request_code;
use function rest_invalid_param_code;
use function rest_no_route_code;
use function rest_server_error_code;

/**
 * Middleware for handling REST API errors.
 *
 * Provides consistent error responses and logging.
 */
final class ErrorHandlingMiddleware
{
    /**
     * Handle error response.
     *
     * @param WP_Error|Throwable|string $error
     * @param int $status_code
     *
     * @return WP_REST_Response
     */
    public static function handleError($error, int $status_code = 500): WP_REST_Response
    {
        if (is_wp_error($error)) {
            return self::handleWpError($error);
        }

        if ($error instanceof Throwable) {
            return self::handleException($error);
        }

        return self::handleStringError((string) $error, $status_code);
    }

    /**
     * Handle WP_Error.
     */
    private static function handleWpError(WP_Error $error): WP_REST_Response
    {
        $status_code = $error->get_error_code() === 'rest_forbidden'
            ? rest_forbidden_code()
            : rest_bad_request_code();

        Logger::log('error', 'REST API Error', [
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => $error->get_error_data(),
        ]);

        return rest_ensure_response($error);
    }

    /**
     * Handle Exception.
     */
    private static function handleException(Throwable $exception): WP_REST_Response
    {
        Logger::log('error', 'REST API Exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $error = new WP_Error(
            'rest_server_error',
            'An error occurred while processing your request.',
            ['status' => rest_server_error_code()]
        );

        return rest_ensure_response($error);
    }

    /**
     * Handle string error.
     */
    private static function handleStringError(string $message, int $status_code): WP_REST_Response
    {
        Logger::log('error', 'REST API Error', [
            'message' => $message,
            'status' => $status_code,
        ]);

        $error = new WP_Error(
            'rest_error',
            $message,
            ['status' => $status_code]
        );

        return rest_ensure_response($error);
    }

    /**
     * Create success response.
     *
     * @param mixed $data
     * @param int $status_code
     *
     * @return WP_REST_Response
     */
    public static function success($data, int $status_code = 200): WP_REST_Response
    {
        // Clean any output buffer to prevent unwanted output before JSON response
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        $response = rest_ensure_response($data);
        if (!($response instanceof WP_REST_Response)) {
            $response = new WP_REST_Response($data, $status_code);
        } else {
            $response->set_status($status_code);
        }
        return $response;
    }

    /**
     * Create not found response.
     */
    public static function notFound(string $message = 'Resource not found'): WP_REST_Response
    {
        $error = new WP_Error(
            'rest_no_route',
            $message,
            ['status' => rest_no_route_code()]
        );

        return rest_ensure_response($error);
    }

    /**
     * Create forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden'): WP_REST_Response
    {
        $error = new WP_Error(
            'rest_forbidden',
            $message,
            ['status' => rest_forbidden_code()]
        );

        return rest_ensure_response($error);
    }

    /**
     * Create bad request response.
     */
    public static function badRequest(string $message = 'Bad request'): WP_REST_Response
    {
        $error = new WP_Error(
            'rest_invalid_param',
            $message,
            ['status' => rest_bad_request_code()]
        );

        return rest_ensure_response($error);
    }
}















