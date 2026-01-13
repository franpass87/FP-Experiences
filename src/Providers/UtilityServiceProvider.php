<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Api\RestRoutes;
use FP_Exp\Api\Webhooks;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Localization\AutoTranslator;
use FP_Exp\MeetingPoints\Manager as MeetingPointsManager;
use FP_Exp\Migrations\Runner as MigrationRunner;
use FP_Exp\PostTypes\ExperienceCPT;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Utility service provider - registers utility services.
 * 
 * This provider handles:
 * - Meeting points management
 * - Database migrations
 * - REST API routes
 * - Webhooks
 * - Auto translation
 */
final class UtilityServiceProvider extends AbstractServiceProvider
{
    /**
     * Register utility services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register ExperienceCPT - must be available in both admin and frontend
        $container->singleton(ExperienceCPT::class, ExperienceCPT::class);
        
        // Register MeetingPointsManager - no dependencies
        $container->singleton(MeetingPointsManager::class, MeetingPointsManager::class);
        
        // Register MigrationRunner - no dependencies
        $container->singleton(MigrationRunner::class, MigrationRunner::class);
        
        // Register AutoTranslator - no dependencies
        $container->singleton(AutoTranslator::class, AutoTranslator::class);
        
        // Register Webhooks - depends on OptionsInterface (optional)
        $container->singleton(Webhooks::class, static function (ContainerInterface $container): Webhooks {
            $options = null;
            
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            
            return new Webhooks($options);
        });
        
        // Register RestRoutes - depends on VoucherManager (optional)
        $container->singleton(RestRoutes::class, static function (ContainerInterface $container): RestRoutes {
            $voucher_manager = null;
            
            if ($container->has(VoucherManager::class)) {
                try {
                    $voucher_manager = $container->make(VoucherManager::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            
            return new RestRoutes($voucher_manager);
        });
    }

    /**
     * Boot utility services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Register hooks for utility services that implement HookableInterface
        $hookables = [
            ExperienceCPT::class,
            MeetingPointsManager::class,
            MigrationRunner::class,
            AutoTranslator::class,
            Webhooks::class,
            RestRoutes::class,
        ];

        foreach ($hookables as $serviceClass) {
            try {
                if ($container->has($serviceClass)) {
                    $service = $container->make($serviceClass);
                    if (is_object($service) && method_exists($service, 'register_hooks')) {
                        $service->register_hooks();
                    }
                }
            } catch (\Throwable $e) {
                // Log error but don't break the site
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'FP Experiences: Failed to boot utility service %s: %s',
                        $serviceClass,
                        $e->getMessage()
                    ));
                }
            }
        }
    }

    /**
     * Get list of services provided.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ExperienceCPT::class,
            MeetingPointsManager::class,
            MigrationRunner::class,
            AutoTranslator::class,
            Webhooks::class,
            RestRoutes::class,
        ];
    }
}



