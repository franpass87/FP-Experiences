<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form\Sanitizers;

/**
 * Interface for field sanitizers.
 * 
 * Each field type should have its own sanitizer implementing this interface.
 */
interface Sanitizer
{
    /**
     * Sanitize the field value.
     * 
     * @param mixed $value Raw input value
     * @param array<string, mixed> $options Additional sanitization options
     * @return mixed Sanitized value
     */
    public function sanitize(mixed $value, array $options = []): mixed;

    /**
     * Check if this sanitizer supports the given field type.
     */
    public function supports(string $type): bool;
}
















