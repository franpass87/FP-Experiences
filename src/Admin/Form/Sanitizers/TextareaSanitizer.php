<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function sanitize_textarea_field;
use function trim;

/**
 * Sanitizer for textarea fields.
 */
final class TextareaSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return $type === 'textarea';
    }

    public function sanitize(mixed $value, array $options = []): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $sanitized = sanitize_textarea_field((string) $value);
        
        // Apply maxlength if specified
        if (isset($options['maxlength']) && is_numeric($options['maxlength'])) {
            $maxlength = (int) $options['maxlength'];
            if (mb_strlen($sanitized) > $maxlength) {
                $sanitized = mb_substr($sanitized, 0, $maxlength);
            }
        }

        // Allow HTML if specified (uses wp_kses_post)
        if (!empty($options['allow_html'])) {
            $sanitized = \wp_kses_post($sanitized);
        }

        return trim($sanitized);
    }
}

