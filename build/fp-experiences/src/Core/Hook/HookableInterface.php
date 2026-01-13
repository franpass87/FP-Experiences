<?php

declare(strict_types=1);

namespace FP_Exp\Core\Hook;

/**
 * Interface for classes that register WordPress hooks.
 */
interface HookableInterface
{
    /**
     * Register WordPress hooks (actions and filters).
     */
    public function register_hooks(): void;
}
