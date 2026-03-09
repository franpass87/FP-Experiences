<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

/**
 * Meta Pixel integration — disabled.
 * Tracking is now handled by FP-Marketing-Tracking-Layer.
 * The purchase event is fired via do_action('fp_tracking_event') in GA4.php.
 */
final class MetaPixel implements HookableInterface
{
    public function register_hooks(): void
    {
        // No-op: tracking delegated to FP-Marketing-Tracking-Layer
    }
}
