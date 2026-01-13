<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\REST\Providers;

use FP_Exp\Api\Controllers\AvailabilityController;
use FP_Exp\Api\Controllers\CalendarController;
use FP_Exp\Api\Controllers\DiagnosticController;
use FP_Exp\Api\Controllers\GiftController;
use FP_Exp\Api\Controllers\SettingsController;
use FP_Exp\Api\Controllers\ToolsController;
use FP_Exp\Api\RestRoutes;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Gift\VoucherManager;

/**
 * REST API service provider - registers REST routes and controllers.
 */
final class RESTServiceProvider extends AbstractServiceProvider
{
    /**
     * Register REST services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register controllers (non-singleton, created per request)
        $container->bind(AvailabilityController::class, AvailabilityController::class);
        $container->bind(CalendarController::class, CalendarController::class);
        $container->bind(ToolsController::class, ToolsController::class);
        $container->bind(DiagnosticController::class, DiagnosticController::class);
        $container->bind(SettingsController::class, SettingsController::class);
        
        // GiftController requires VoucherManager
        $container->bind(GiftController::class, static function (ContainerInterface $container): GiftController {
            $voucherManager = null;
            if ($container->has(VoucherManager::class)) {
                try {
                    $voucherManager = $container->make(VoucherManager::class);
                } catch (\Throwable $e) {
                    // VoucherManager not available
                }
            }
            return new GiftController($voucherManager);
        });

        // Register RestRoutes with VoucherManager dependency
        // Controllers can be injected via constructor (preferred) or will be lazy-loaded
        $container->singleton(RestRoutes::class, static function (ContainerInterface $container): RestRoutes {
            $voucherManager = null;
            
            // Try to get VoucherManager from container if available
            if ($container->has(VoucherManager::class)) {
                try {
                    $voucherManager = $container->make(VoucherManager::class);
                } catch (\Throwable $e) {
                    // VoucherManager not available, use null
                }
            }
            
            // Try to get controllers from container (preferred - constructor injection)
            // If not available, they will be lazy-loaded when needed
            try {
                $availabilityController = $container->has(AvailabilityController::class) 
                    ? $container->make(AvailabilityController::class) 
                    : null;
                $calendarController = $container->has(CalendarController::class)
                    ? $container->make(CalendarController::class)
                    : null;
                $toolsController = $container->has(ToolsController::class)
                    ? $container->make(ToolsController::class)
                    : null;
                $diagnosticController = $container->has(DiagnosticController::class)
                    ? $container->make(DiagnosticController::class)
                    : null;
                $giftController = null;
                if ($container->has(GiftController::class)) {
                    try {
                        $giftController = $container->make(GiftController::class);
                    } catch (\Throwable $e) {
                        // GiftController optional
                    }
                }
                $settingsController = null;
                if ($container->has(SettingsController::class)) {
                    try {
                        $settingsController = $container->make(SettingsController::class);
                    } catch (\Throwable $e) {
                        // SettingsController optional
                    }
                }
                
                // Create RestRoutes with controllers injected (if available)
                return new RestRoutes(
                    $voucherManager,
                    $availabilityController,
                    $calendarController,
                    $toolsController,
                    $diagnosticController,
                    $giftController,
                    $settingsController
                );
            } catch (\Throwable $e) {
                // Fallback: create RestRoutes without controllers (will be lazy-loaded)
                return new RestRoutes($voucherManager);
            }
        });
    }

    /**
     * Boot REST services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // RestRoutes registers its own hooks via register_hooks()
        // This is handled by the legacy Plugin class for now
        // In Phase 5, we'll move hook registration here
    }

    /**
     * Get list of services provided.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RestRoutes::class,
            AvailabilityController::class,
            CalendarController::class,
            GiftController::class,
            ToolsController::class,
            DiagnosticController::class,
            SettingsController::class,
        ];
    }
}

