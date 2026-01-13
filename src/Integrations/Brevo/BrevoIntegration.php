<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\Brevo;

use FP_Exp\Integrations\Brevo as LegacyBrevo;
use FP_Exp\Integrations\Interfaces\IntegrationInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Brevo integration wrapper implementing IntegrationInterface.
 *
 * This class wraps the legacy Brevo integration to implement the new interface
 * while maintaining backward compatibility.
 */
final class BrevoIntegration implements IntegrationInterface
{
    private LegacyBrevo $legacy;
    private OptionsInterface $options;
    private ?LoggerInterface $logger = null;

    public function __construct(
        LegacyBrevo $legacy,
        OptionsInterface $options
    ) {
        $this->legacy = $legacy;
        $this->options = $options;
    }

    /**
     * Set logger (optional).
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Check if integration is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->legacy->is_enabled();
    }

    /**
     * Check if integration is configured.
     */
    public function isConfigured(): bool
    {
        $settings = $this->legacy->get_settings();
        return !empty($settings['api_key']) && !empty($settings['enabled']);
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
        return 'Brevo';
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

    /**
     * Get legacy instance (for backward compatibility).
     */
    public function getLegacy(): LegacyBrevo
    {
        return $this->legacy;
    }
}







