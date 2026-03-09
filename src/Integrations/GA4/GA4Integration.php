<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\GA4;

use FP_Exp\Integrations\Interfaces\IntegrationInterface;

/**
 * GA4 integration wrapper — disabled.
 * Tracking is now handled by FP-Marketing-Tracking-Layer.
 * The purchase event is fired via do_action('fp_tracking_event') in GA4.php.
 */
final class GA4Integration implements IntegrationInterface
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function register_hooks(): void
    {
        // No-op: tracking delegated to FP-Marketing-Tracking-Layer
    }

    public function getName(): string
    {
        return 'Google Analytics 4';
    }

    public function getStatus(): array
    {
        return [
            'enabled'    => false,
            'configured' => false,
            'name'       => $this->getName(),
        ];
    }
}
