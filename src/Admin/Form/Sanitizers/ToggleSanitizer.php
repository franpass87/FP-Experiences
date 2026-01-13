<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

/**
 * Sanitizer for toggle/checkbox fields.
 * 
 * Converts various input formats to 'yes' or 'no'.
 */
final class ToggleSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return in_array($type, ['toggle', 'checkbox'], true);
    }

    public function sanitize(mixed $value, array $options = []): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? 'yes' : 'no';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return 'no';
            }

            return in_array($normalized, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
        }

        return $value ? 'yes' : 'no';
    }
}
















