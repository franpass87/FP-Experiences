<?php

declare(strict_types=1);

namespace FP_Exp\Core\Kernel;

use FP_Exp\Core\Container\ContainerInterface;

/**
 * Plugin kernel interface.
 */
interface KernelInterface
{
    /**
     * Boot the plugin kernel.
     */
    public function boot(): void;

    /**
     * Get the container instance.
     */
    public function container(): ContainerInterface;

    /**
     * Check if kernel is booted.
     */
    public function isBooted(): bool;
}



