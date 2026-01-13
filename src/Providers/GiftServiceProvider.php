<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Gift service provider - registers gift voucher services.
 * 
 * This provider handles:
 * - Voucher CPT registration
 * - Voucher management
 */
final class GiftServiceProvider extends AbstractServiceProvider
{
    /**
     * Register gift services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register VoucherCPT - no dependencies
        $container->singleton(VoucherCPT::class, VoucherCPT::class);
        
        // Register VoucherManager - depends on OptionsInterface (optional)
        $container->singleton(VoucherManager::class, static function (ContainerInterface $container): VoucherManager {
            $options = null;
            
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            
            return new VoucherManager($options);
        });
    }

    /**
     * Boot gift services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Register hooks for gift services that implement HookableInterface
        $hookables = [
            VoucherCPT::class,
            VoucherManager::class,
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
                        'FP Experiences: Failed to boot gift service %s: %s',
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
            VoucherCPT::class,
            VoucherManager::class,
        ];
    }
}



