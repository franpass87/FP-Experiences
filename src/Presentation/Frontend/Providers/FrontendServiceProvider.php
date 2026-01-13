<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\Frontend\Providers;

use FP_Exp\Elementor\WidgetsRegistrar;
use FP_Exp\Front\SingleExperienceRenderer;
use FP_Exp\Shortcodes\Registrar;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;

/**
 * Frontend service provider - registers shortcodes and frontend functionality.
 * Only loads on frontend (not in admin).
 */
final class FrontendServiceProvider extends AbstractServiceProvider
{
    /**
     * Register frontend services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register shortcode registrar
        $container->singleton(Registrar::class, Registrar::class);
        
        // ExperienceCPT is now registered in UtilityServiceProvider (available in both admin and frontend)
        // No need to register it here anymore
        
        // Register Elementor widgets registrar
        $container->singleton(WidgetsRegistrar::class, WidgetsRegistrar::class);
        
        // Register single experience renderer
        $container->singleton(SingleExperienceRenderer::class, SingleExperienceRenderer::class);
    }

    /**
     * Boot frontend services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // List of frontend services that implement HookableInterface
        // ExperienceCPT is booted in UtilityServiceProvider
        $hookables = [
            Registrar::class,
            WidgetsRegistrar::class,
            SingleExperienceRenderer::class,
        ];

        // Register hooks for all frontend services
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
                        'FP Experiences: Failed to boot frontend service %s: %s',
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
            Registrar::class,
            WidgetsRegistrar::class,
            SingleExperienceRenderer::class,
        ];
    }
}

