<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

use function sanitize_email;
use function trim;

/**
 * Sanitizer for email input fields.
 */
final class EmailSanitizer implements Sanitizer
{
    public function supports(string $type): bool
    {
        return $type === 'email';
    }

    public function sanitize(mixed $value, array $options = []): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $email = trim((string) $value);
        
        if (empty($email)) {
            return '';
        }

        // Support multiple emails (comma or semicolon separated)
        if (!empty($options['multiple'])) {
            $emails = preg_split('/[,;]+/', $email);
            $sanitized = [];
            
            foreach ($emails as $single_email) {
                $single_email = trim($single_email);
                if (!empty($single_email)) {
                    $sanitized_email = sanitize_email($single_email);
                    if (!empty($sanitized_email)) {
                        $sanitized[] = $sanitized_email;
                    }
                }
            }
            
            return implode(', ', $sanitized);
        }

        return sanitize_email($email);
    }
}
















