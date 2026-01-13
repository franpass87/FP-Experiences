<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Traits;

use FP_Exp\Admin\Form\Sanitizers\SanitizerFactory;

/**
 * Trait for sanitizing form fields using the Sanitizer Strategy pattern.
 * 
 * Provides helper methods to easily sanitize form field values.
 */
trait FormSanitizer
{
    private ?SanitizerFactory $sanitizer_factory = null;

    /**
     * Get sanitizer factory (lazy initialization).
     */
    protected function getSanitizerFactory(): SanitizerFactory
    {
        if ($this->sanitizer_factory === null) {
            $this->sanitizer_factory = new SanitizerFactory();
        }

        return $this->sanitizer_factory;
    }

    /**
     * Sanitize a form field value using the sanitizer strategy.
     * 
     * @param string $type Field type
     * @param mixed $value Raw input value
     * @param array<string, mixed> $options Additional sanitization options
     * @return mixed Sanitized value
     */
    protected function sanitize_form_field(string $type, mixed $value, array $options = []): mixed
    {
        $sanitizer = $this->getSanitizerFactory()->getSanitizer($type);
        return $sanitizer->sanitize($value, $options);
    }

    /**
     * Sanitize multiple fields at once.
     * 
     * @param array<string, array{type: string, value: mixed, options?: array<string, mixed>}> $fields
     * @return array<string, mixed>
     */
    protected function sanitize_form_fields(array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $name => $field_config) {
            $type = $field_config['type'] ?? 'text';
            $value = $field_config['value'] ?? null;
            $options = $field_config['options'] ?? [];

            $sanitized[$name] = $this->sanitize_form_field($type, $value, $options);
        }

        return $sanitized;
    }
}
















