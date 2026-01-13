<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\Interfaces;

use FP_Exp\Core\Hook\HookableInterface;

/**
 * Integration interface for external service integrations.
 */
interface IntegrationInterface extends HookableInterface
{
    /**
     * Check if integration is enabled.
     *
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(): bool;

    /**
     * Check if integration is configured.
     *
     * @return bool True if configured, false otherwise
     */
    public function isConfigured(): bool;

    /**
     * Get integration name.
     *
     * @return string Integration name
     */
    public function getName(): string;

    /**
     * Get integration status.
     *
     * @return array<string, mixed> Status information
     */
    public function getStatus(): array;
}







