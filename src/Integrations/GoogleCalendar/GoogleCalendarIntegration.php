<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\GoogleCalendar;

use FP_Exp\Integrations\GoogleCalendar as LegacyGoogleCalendar;
use FP_Exp\Integrations\Interfaces\IntegrationInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Google Calendar integration wrapper implementing IntegrationInterface.
 *
 * This class wraps the legacy GoogleCalendar integration to implement the new interface
 * while maintaining backward compatibility.
 */
final class GoogleCalendarIntegration implements IntegrationInterface
{
    private LegacyGoogleCalendar $legacy;
    private OptionsInterface $options;
    private ?LoggerInterface $logger = null;

    public function __construct(
        LegacyGoogleCalendar $legacy,
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
        return $this->legacy->is_connected();
    }

    /**
     * Check if integration is configured.
     */
    public function isConfigured(): bool
    {
        return $this->legacy->is_connected();
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
        return 'Google Calendar';
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
            'connected' => $this->legacy->is_connected(),
        ];
    }

    /**
     * Get legacy instance (for backward compatibility).
     */
    public function getLegacy(): LegacyGoogleCalendar
    {
        return $this->legacy;
    }
}







