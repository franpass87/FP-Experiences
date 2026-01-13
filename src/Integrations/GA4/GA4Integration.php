<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\GA4;

use FP_Exp\Integrations\GA4 as LegacyGA4;
use FP_Exp\Integrations\Interfaces\IntegrationInterface;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * GA4 integration wrapper implementing IntegrationInterface.
 */
final class GA4Integration implements IntegrationInterface
{
    private LegacyGA4 $legacy;
    private OptionsInterface $options;

    public function __construct(
        LegacyGA4 $legacy,
        OptionsInterface $options
    ) {
        $this->legacy = $legacy;
        $this->options = $options;
    }

    /**
     * Check if integration is enabled.
     */
    public function isEnabled(): bool
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->legacy);
        $method = $reflection->getMethod('is_enabled');
        $method->setAccessible(true);
        return $method->invoke($this->legacy);
    }

    /**
     * Check if integration is configured.
     */
    public function isConfigured(): bool
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->legacy);
        $method = $reflection->getMethod('is_enabled');
        $method->setAccessible(true);
        return $method->invoke($this->legacy);
    }

    /**
     * Register WordPress hooks for the integration.
     */
    public function register_hooks(): void
    {
        $this->legacy->register_hooks();
    }

    /**
     * Get integration name.
     */
    public function getName(): string
    {
        return 'Google Analytics 4';
    }

    /**
     * Get integration status.
     *
     * @return array<string, mixed> Status information
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'name' => $this->getName(),
        ];
    }
}

