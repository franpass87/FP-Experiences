<?php

declare(strict_types=1);

namespace FP_Exp\Core\ServiceProvider;

use FP_Exp\Core\Container\ContainerInterface;

/**
 * Registry for managing service providers.
 */
final class ServiceProviderRegistry
{
    /**
     * @var array<int, ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * @var array<int, string>
     */
    private array $registered = [];

    /**
     * Register a service provider.
     *
     * @param ServiceProviderInterface $provider Service provider instance
     */
    public function register(ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Register all providers with the container.
     *
     * @param ContainerInterface $container Container instance
     */
    public function registerAll(ContainerInterface $container): void
    {
        foreach ($this->providers as $provider) {
            $provider->register($container);
            $this->registered[] = get_class($provider);
        }
    }

    /**
     * Boot all registered providers.
     *
     * @param ContainerInterface $container Container instance
     */
    public function bootAll(ContainerInterface $container): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot($container);
        }
    }

    /**
     * Get list of registered provider class names.
     *
     * @return array<int, string>
     */
    public function getRegistered(): array
    {
        return $this->registered;
    }

    /**
     * Get all providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function getAll(): array
    {
        return $this->providers;
    }
}



