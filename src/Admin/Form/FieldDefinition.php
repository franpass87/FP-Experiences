<?php

declare(strict_types=1);

namespace FP_Exp\Admin\Form;

/**
 * Value object for form field definition.
 * 
 * Encapsulates all information needed to render and sanitize a form field.
 */
final class FieldDefinition
{
    /**
     * @param string $name Field name/ID
     * @param string $type Field type (text, email, select, etc.)
     * @param string $label Field label
     * @param mixed $value Current field value
     * @param array<string, mixed> $options Additional options (choices, attributes, etc.)
     * @param string|null $description Field description/help text
     * @param bool $required Whether field is required
     * @param string|null $sanitize_callback Custom sanitize callback
     * @param array<string, mixed> $attributes HTML attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly mixed $value = null,
        public readonly array $options = [],
        public readonly ?string $description = null,
        public readonly bool $required = false,
        public readonly ?string $sanitize_callback = null,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * Get field name attribute for form input.
     */
    public function getInputName(string $option_group = ''): string
    {
        if (empty($option_group)) {
            return $this->name;
        }

        return $option_group . '[' . $this->name . ']';
    }

    /**
     * Get field ID attribute.
     * Sanitizes the name to ensure valid HTML ID (replaces [ and ] with _).
     */
    public function getFieldId(string $option_group = ''): string
    {
        $prefix = empty($option_group) ? '' : $option_group . '_';
        // Sanitize name for HTML ID: replace [ and ] with _ to make it valid
        $sanitized_name = str_replace(['[', ']'], '_', $this->name);
        // Remove trailing underscores from nested arrays (e.g., "array_key_" -> "array_key")
        $sanitized_name = rtrim($sanitized_name, '_');
        return $prefix . $sanitized_name;
    }

    /**
     * Check if field has a specific option.
     */
    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    /**
     * Get option value or default.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Get HTML attributes as string.
     */
    public function getAttributesString(): string
    {
        $attrs = array_merge($this->attributes, [
            'id' => $this->getFieldId(),
            'name' => $this->getInputName(),
        ]);

        if ($this->required) {
            $attrs['required'] = 'required';
        }

        $parts = [];
        foreach ($attrs as $key => $value) {
            if (is_bool($value) && $value) {
                $parts[] = esc_attr($key);
            } elseif (!is_bool($value)) {
                $parts[] = esc_attr($key) . '="' . esc_attr((string) $value) . '"';
            }
        }

        return implode(' ', $parts);
    }
}
















