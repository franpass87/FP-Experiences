<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Utils\Helpers;
use FP_Exp\Services\Security\RoleManager;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Legacy service provider - handles backward compatibility with Plugin class.
 * 
 * This provider registers hooks and services that were previously handled
 * by the Plugin::boot() method. It ensures backward compatibility while
 * migrating to the new Kernel architecture.
 */
final class LegacyServiceProvider extends AbstractServiceProvider
{
    /**
     * Register legacy services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register Plugin class as singleton for backward compatibility
        // This allows existing code that uses Plugin::instance() to continue working
        // while we migrate to Kernel architecture
        if (class_exists(\FP_Exp\Plugin::class)) {
            $container->singleton(\FP_Exp\Plugin::class, static function (ContainerInterface $container): \FP_Exp\Plugin {
                // Create instance with suppressed deprecation warning
                return \FP_Exp\Plugin::instance(true);
            });
        }
    }

    /**
     * Boot legacy services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Get RoleManager - try from container first, fallback to new instance
        $role_manager = null;
        if ($container->has(RoleManager::class)) {
            $role_manager = $container->make(RoleManager::class);
        } else {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            $role_manager = new RoleManager($options);
        }

        // Register role and capability hooks
        if ($role_manager !== null) {
            add_action('plugins_loaded', [$role_manager, 'maybeUpdateRoles'], 5);
            add_action('admin_init', [$role_manager, 'maybeUpdateRoles']);
            add_filter('map_meta_cap', [$role_manager, 'mapMetaCapFallback'], 10, 4);
        }

        // Register admin capabilities helper
        add_action('plugins_loaded', [Helpers::class, 'ensure_admin_capabilities'], 1);
        add_action('admin_init', [Helpers::class, 'ensure_admin_capabilities'], 1);

        // Plugin is now a facade - no need to boot it
        // All services are booted by their respective Service Providers
        // The Plugin::boot() method is now a no-op for backward compatibility
    }

    /**
     * Get list of services provided.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            \FP_Exp\Plugin::class,
        ];
    }
}

