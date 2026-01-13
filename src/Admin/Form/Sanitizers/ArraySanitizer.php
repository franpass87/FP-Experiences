<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;

/**
 * Sanitizer for array fields.
 */
final class ArraySanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return $type === 'array';
    }

    public function sanitize(mixed $value, array $options = []): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitize_as_keys = !empty($options['sanitize_as_keys']);
        $recursive = !empty($options['recursive']);

        if ($sanitize_as_keys) {
            // Sanitize array values as keys (for taxonomy terms, etc.)
            $sanitized = array_map('sanitize_key', $value);
            $sanitized = array_values(array_unique(array_filter($sanitized)));
        } elseif ($recursive) {
            // Recursive sanitization for nested arrays
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : $key;
                
                if (is_array($item)) {
                    $sanitized[$sanitized_key] = $this->sanitize($item, $options);
                } else {
                    $sanitized[$sanitized_key] = sanitize_text_field((string) $item);
                }
            }
        } else {
            // Simple array sanitization
            $sanitized = array_map('sanitize_text_field', $value);
            $sanitized = array_values(array_filter($sanitized));
        }

        return $sanitized;
    }
}
















