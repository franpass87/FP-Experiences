<?php

declare(strict_types=1);

namespace FP_Exp\Services\Sanitization;

/**
 * Sanitizer service interface.
 */
interface SanitizerInterface
{
    /**
     * Sanitize a value by type.
     *
     * @param mixed $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed Sanitized value
     */
    public function sanitize($value, string $type);

    /**
     * Sanitize an array of values.
     *
     * @param array<string, mixed> $data Data to sanitize
     * @param array<string, string> $rules Sanitization rules (field => type)
     * @return array<string, mixed> Sanitized data
     */
    public function sanitizeArray(array $data, array $rules): array;
}



