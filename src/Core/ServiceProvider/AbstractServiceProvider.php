<?php

declare(strict_types=1);

namespace FP_Exp\Core\ServiceProvider;

use FP_Exp\Core\Container\ContainerInterface;

/**
 * Abstract base class for service providers.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    /**
     * Boot services after all providers are registered.
     *
     * Default implementation does nothing. Override if needed.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Default: no-op, override if needed
    }

    /**
     * Register services in the container.
     *
     * @param ContainerInterface $container Container instance
     */
    abstract public function register(ContainerInterface $container): void;

    /**
     * Get list of services provided by this provider.
     *
     * @return array<int, string> Array of class/interface names
     */
    abstract public function provides(): array;
}



