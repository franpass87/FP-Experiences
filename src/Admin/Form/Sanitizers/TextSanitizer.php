<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function sanitize_text_field;
use function trim;

/**
 * Sanitizer for text input fields.
 */
final class TextSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return in_array($type, ['text', 'url', 'tel'], true);
    }

    public function sanitize(mixed $value, array $options = []): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $sanitized = sanitize_text_field((string) $value);
        
        // Apply maxlength if specified
        if (isset($options['maxlength']) && is_numeric($options['maxlength'])) {
            $maxlength = (int) $options['maxlength'];
            if (mb_strlen($sanitized) > $maxlength) {
                $sanitized = mb_substr($sanitized, 0, $maxlength);
            }
        }

        return trim($sanitized);
    }
}
















