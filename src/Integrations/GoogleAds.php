<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

/**
 * Google Ads integration — disabled.
 * Tracking is now handled by FP-Marketing-Tracking-Layer via GTM.
 */
final class GoogleAds implements HookableInterface
{
    public function register_hooks(): void
    {
        // No-op: tracking delegated to FP-Marketing-Tracking-Layer
    }
}
