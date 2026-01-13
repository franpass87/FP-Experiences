<?php

declare(strict_types=1);

namespace FP_Exp\Api\Middleware;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function absint;
use function is_array;
use function is_numeric;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;

/**
 * Middleware for validating and sanitizing REST API requests.
 */
final class ValidationMiddleware
{
    /**
     * Validate and sanitize request parameters.
     *
     * @param array<string, mixed> $rules Validation rules
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function validate(WP_REST_Request $request, array $rules)
    {
        $validated = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $request->get_param($field);
            $required = $rule['required'] ?? false;
            $type = $rule['type'] ?? 'string';
            $sanitize = $rule['sanitize'] ?? null;

            // Check required
            if ($required && ($value === null || $value === '')) {
                $errors[$field] = sprintf('Field %s is required.', $field);
                continue;
            }

            // Skip if not required and empty
            if (! $required && ($value === null || $value === '')) {
                continue;
            }

            // Type validation
            $validated_value = self::validateType($value, $type);

            if ($validated_value === null) {
                $errors[$field] = sprintf('Field %s must be of type %s.', $field, $type);
                continue;
            }

            // Custom sanitization
            if ($sanitize && is_callable($sanitize)) {
                $validated_value = $sanitize($validated_value);
            } else {
                $validated_value = self::sanitize($validated_value, $type);
            }

            $validated[$field] = $validated_value;
        }

        if (! empty($errors)) {
            return new WP_Error('validation_error', 'Validation failed', ['errors' => $errors]);
        }

        return $validated;
    }

    /**
     * Validate type.
     *
     * @param mixed $value
     */
    private static function validateType($value, string $type): ?string
    {
        switch ($type) {
            case 'integer':
                return is_numeric($value) ? (string) absint($value) : null;

            case 'string':
                return is_string($value) ? $value : (is_scalar($value) ? (string) $value : null);

            case 'array':
                return is_array($value) ? $value : null;

            case 'email':
                return is_string($value) && is_email($value) ? $value : null;

            default:
                return is_scalar($value) ? (string) $value : null;
        }
    }

    /**
     * Sanitize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function sanitize($value, string $type)
    {
        switch ($type) {
            case 'integer':
                return absint($value);

            case 'string':
                return sanitize_text_field((string) $value);

            case 'textarea':
                return sanitize_textarea_field((string) $value);

            case 'email':
                return sanitize_email((string) $value);

            case 'key':
                return sanitize_key((string) $value);

            case 'array':
                return is_array($value) ? $value : [];

            default:
                return $value;
        }
    }
}















