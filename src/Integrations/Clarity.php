<?php

declare(strict_types=1);

namespace FP_Exp\Integrations;

use FP_Exp\Core\Hook\HookableInterface;

/**
 * Microsoft Clarity integration — disabled.
 * Clarity snippet is now injected by FP-Marketing-Tracking-Layer.
 */
final class Clarity implements HookableInterface
{
    public function register_hooks(): void
    {
        // No-op: tracking delegated to FP-Marketing-Tracking-Layer
    }
}
