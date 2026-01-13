<?php

declare(strict_types=1);

namespace FP_Exp\Services\Security;

use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_email;
use function sanitize_url;
use function sanitize_key;
use function sanitize_title;
use function sanitize_file_name;
use function esc_html;
use function esc_attr;
use function esc_url;
use function wp_unslash;
use function is_array;

/**
 * Input sanitizer service for cleaning user input.
 */
final class InputSanitizer
{
    /**
     * Sanitize text field.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized string
     */
    public function textField($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_text_field(wp_unslash($value));
    }

    /**
     * Sanitize textarea field.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized string
     */
    public function textareaField($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_textarea_field(wp_unslash($value));
    }

    /**
     * Sanitize email address.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized email
     */
    public function email($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_email(wp_unslash($value));
    }

    /**
     * Sanitize URL.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized URL
     */
    public function url($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_url(wp_unslash($value));
    }

    /**
     * Sanitize key.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized key
     */
    public function key($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_key(wp_unslash($value));
    }

    /**
     * Sanitize title.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized title
     */
    public function title($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_title(wp_unslash($value));
    }

    /**
     * Sanitize file name.
     *
     * @param mixed $value Value to sanitize
     * @return string Sanitized file name
     */
    public function fileName($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return sanitize_file_name(wp_unslash($value));
    }

    /**
     * Sanitize array recursively.
     *
     * @param array<mixed> $array Array to sanitize
     * @return array<mixed> Sanitized array
     */
    public function array(array $array): array
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            $key = $this->key($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->array($value);
            } else {
                $sanitized[$key] = $this->textField($value);
            }
        }

        return $sanitized;
    }

    /**
     * Get value from request and sanitize it.
     *
     * @param string $key Request key
     * @param string $type Sanitization type (text, email, url, key, etc.)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Sanitized value or default
     */
    public function fromRequest(string $key, string $type = 'text', $default = '')
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $value = $_REQUEST[$key] ?? $default;

        if ($value === $default) {
            return $default;
        }

        return match ($type) {
            'email' => $this->email($value),
            'url' => $this->url($value),
            'key' => $this->key($value),
            'title' => $this->title($value),
            'textarea' => $this->textareaField($value),
            'array' => is_array($value) ? $this->array($value) : $default,
            default => $this->textField($value),
        };
    }
}







