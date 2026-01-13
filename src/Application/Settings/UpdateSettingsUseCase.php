<?php

declare(strict_types=1);

namespace FP_Exp\Application\Settings;

use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Services\Sanitization\SanitizerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Update settings values.
 */
final class UpdateSettingsUseCase
{
    private OptionsInterface $options;
    private SanitizerInterface $sanitizer;
    private ValidatorInterface $validator;

    public function __construct(
        OptionsInterface $options,
        SanitizerInterface $sanitizer,
        ValidatorInterface $validator
    ) {
        $this->options = $options;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
    }

    /**
     * Update a setting value.
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param bool $autoload Whether to autoload the option
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update(string $key, $value, bool $autoload = true)
    {
        // Sanitize value based on type
        $sanitized = $this->sanitizeValue($value);

        // Update option
        $success = $this->options->set($key, $sanitized, $autoload);

        if (!$success) {
            return new WP_Error(
                'fp_exp_settings_update_failed',
                'Failed to update setting',
                ['key' => $key]
            );
        }

        return true;
    }

    /**
     * Update multiple settings at once.
     *
     * @param array<string, mixed> $settings Settings array (key => value)
     * @param bool $autoload Whether to autoload the options
     * @return array<string, bool|WP_Error> Results for each setting
     */
    public function updateMultiple(array $settings, bool $autoload = true): array
    {
        $results = [];

        foreach ($settings as $key => $value) {
            $results[$key] = $this->update($key, $value, $autoload);
        }

        return $results;
    }

    /**
     * Delete a setting.
     *
     * @param string $key Setting key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool
    {
        return $this->options->delete($key);
    }

    /**
     * Execute the use case to update settings.
     *
     * @param array<string, mixed> $settings Settings array (key => value)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(array $settings)
    {
        if (empty($settings)) {
            return new WP_Error(
                'fp_exp_empty_settings',
                'Settings array cannot be empty.'
            );
        }

        $results = $this->updateMultiple($settings);

        // Check if any update failed
        foreach ($results as $key => $result) {
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Sanitize a value based on its type.
     *
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeValue($value)
    {
        if (is_array($value)) {
            return $this->sanitizer->sanitizeArray($value, []);
        }

        if (is_string($value)) {
            return $this->sanitizer->sanitize($value, 'text');
        }

        if (is_int($value)) {
            return $this->sanitizer->sanitize($value, 'int');
        }

        if (is_float($value)) {
            return $this->sanitizer->sanitize($value, 'float');
        }

        if (is_bool($value)) {
            return $this->sanitizer->sanitize($value, 'bool');
        }

        return $value;
    }
}










