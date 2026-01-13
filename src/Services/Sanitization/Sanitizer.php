<?php

declare(strict_types=1);

namespace FP_Exp\Services\Sanitization;

use function absint;
use function esc_html;
use function esc_url;
use function esc_url_raw;
use function intval;
use function is_array;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_kses_post;

/**
 * Sanitizer service implementation.
 */
final class Sanitizer implements SanitizerInterface
{
    /**
     * Sanitize a value by type.
     *
     * @param mixed $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed Sanitized value
     */
    public function sanitize($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'text':
            case 'string':
                return sanitize_text_field((string) $value);

            case 'textarea':
                return sanitize_textarea_field((string) $value);

            case 'email':
                return sanitize_email((string) $value);

            case 'url':
                return esc_url_raw((string) $value);

            case 'int':
            case 'integer':
                return intval($value);

            case 'absint':
                return absint($value);

            case 'key':
            case 'slug':
                return sanitize_key((string) $value);

            case 'html':
            case 'post':
                return wp_kses_post((string) $value);

            case 'array':
                if (!is_array($value)) {
                    return [];
                }
                return array_map('sanitize_text_field', $value);

            case 'raw':
            default:
                return $value;
        }
    }

    /**
     * Sanitize an array of values.
     *
     * @param array<string, mixed> $data Data to sanitize
     * @param array<string, string> $rules Sanitization rules (field => type)
     * @return array<string, mixed> Sanitized data
     */
    public function sanitizeArray(array $data, array $rules): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $type = $rules[$key] ?? 'text';
            $sanitized[$key] = $this->sanitize($value, $type);
        }

        return $sanitized;
    }
}



