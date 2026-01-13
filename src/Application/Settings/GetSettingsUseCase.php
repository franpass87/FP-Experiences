<?php

declare(strict_types=1);

namespace FP_Exp\Application\Settings;

use FP_Exp\Services\Options\OptionsInterface;

/**
 * Use case: Get settings values.
 */
final class GetSettingsUseCase
{
    private OptionsInterface $options;

    public function __construct(OptionsInterface $options)
    {
        $this->options = $options;
    }

    /**
     * Get a setting value.
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value or default
     */
    public function get(string $key, $default = null)
    {
        return $this->options->get($key, $default);
    }

    /**
     * Check if a setting exists.
     *
     * @param string $key Setting key
     * @return bool True if setting exists
     */
    public function has(string $key): bool
    {
        return $this->options->has($key);
    }

    /**
     * Execute the use case to get settings.
     *
     * @param string|null $key Optional setting key. If null, returns all settings.
     * @return mixed|array<string, mixed> Setting value(s) or all settings
     */
    public function execute(?string $key = null)
    {
        if ($key !== null) {
            return $this->get($key);
        }

        // Return all settings (this is a simplified version - in production,
        // you might want to return all plugin settings)
        return $this->options->get('fp_exp_settings', []);
    }
}










