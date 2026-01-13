<?php

declare(strict_types=1);

namespace FP_Exp\Core\ServiceProvider;

use FP_Exp\Core\Container\ContainerInterface;

/**
 * Service provider interface.
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void;

    /**
     * Boot services after all providers are registered.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void;

    /**
     * Get list of services provided by this provider.
     *
     * @return array<int, string> Array of class/interface names
     */
    public function provides(): array;
}



