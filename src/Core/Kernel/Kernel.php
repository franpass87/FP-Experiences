<?php

declare(strict_types=1);

namespace FP_Exp\Core\Kernel;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\Hook\HookRegistry;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Core\ServiceProvider\ServiceProviderRegistry;

/**
 * Plugin kernel - manages service providers and bootstrapping.
 */
final class Kernel implements KernelInterface
{
    private ContainerInterface $container;
    private ServiceProviderRegistry $providers;
    private HookRegistry $hooks;
    private bool $booted = false;

    /**
     * @var array<int, array{component: string, action: string, message: string}>
     */
    private array $boot_errors = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->providers = new ServiceProviderRegistry();
        $this->hooks = new HookRegistry();

        // Register container and kernel in itself
        $this->container->instance(ContainerInterface::class, $container);
        $this->container->instance(KernelInterface::class, $this);
        $this->container->instance(HookRegistry::class, $this->hooks);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Register all service providers
        $this->registerProviders();

        // Register services from providers
        $this->providers->registerAll($this->container);

        // Boot all providers
        $this->providers->bootAll($this->container);

        // Register hooks from hookable services
        $this->registerHooks();
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Register a service provider.
     *
     * @param \FP_Exp\Core\ServiceProvider\ServiceProviderInterface $provider Service provider
     */
    public function registerProvider(\FP_Exp\Core\ServiceProvider\ServiceProviderInterface $provider): void
    {
        $this->providers->register($provider);
    }

    /**
     * Get hook registry.
     */
    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }

    /**
     * Get boot errors.
     *
     * @return array<int, array{component: string, action: string, message: string}>
     */
    public function getBootErrors(): array
    {
        return $this->boot_errors;
    }

    /**
     * Register service providers.
     */
    private function registerProviders(): void
    {
        // Providers will be registered via registerProvider() method
        // This is called by Bootstrap class
    }

    /**
     * Register hooks from hookable services.
     */
    private function registerHooks(): void
    {
        // Get all services that implement HookableInterface
        // For now, we'll rely on services registering themselves
        // This will be enhanced in later phases
    }

    /**
     * Guard a callback execution and catch errors.
     *
     * @param callable $callback Callback to execute
     * @param string $component Component name
     * @param string $action Action name
     */
    public function guard(callable $callback, string $component, string $action): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());
            if ('' === $message) {
                $message = get_class($exception);
            }

            $this->boot_errors[] = [
                'component' => $component,
                'action' => $action,
                'message' => $message,
            ];
        }
    }
}



