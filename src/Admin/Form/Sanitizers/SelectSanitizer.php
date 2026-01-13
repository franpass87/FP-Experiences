<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function in_array;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;

/**
 * Sanitizer for select dropdown fields.
 */
final class SelectSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return $type === 'select';
    }

    public function sanitize(mixed $value, array $options = []): string|array
    {
        $choices = $options['choices'] ?? [];
        
        // Handle multiple select
        if (!empty($options['multiple']) && is_array($value)) {
            $sanitized = [];
            foreach ($value as $item) {
                $item = sanitize_text_field((string) $item);
                if (!empty($choices) && !in_array($item, array_keys($choices), true)) {
                    continue; // Skip invalid choices
                }
                $sanitized[] = $item;
            }
            return $sanitized;
        }

        // Single select
        $sanitized = sanitize_text_field((string) $value);
        
        // Validate against choices if provided
        if (!empty($choices) && !in_array($sanitized, array_keys($choices), true)) {
            // Return default or first choice if invalid
            return $options['default'] ?? (array_keys($choices)[0] ?? '');
        }

        return $sanitized;
    }
}
















