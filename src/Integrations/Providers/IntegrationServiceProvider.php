<?php

declare(strict_types=1);

namespace FP_Exp\Integrations\Providers;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Booking\Emails;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Integrations\Brevo\BrevoIntegration;
use FP_Exp\Integrations\Clarity;
use FP_Exp\Integrations\ExperienceProduct;
use FP_Exp\Integrations\GA4;
use FP_Exp\Integrations\GA4\GA4Integration;
use FP_Exp\Integrations\GoogleAds;
use FP_Exp\Integrations\GoogleCalendar;
use FP_Exp\Integrations\GoogleCalendar\GoogleCalendarIntegration;
use FP_Exp\Integrations\Interfaces\IntegrationInterface;
use FP_Exp\Integrations\MetaPixel;
use FP_Exp\Integrations\PerformanceIntegration;
use FP_Exp\Integrations\WooCommerceCheckout;
use FP_Exp\Integrations\WooCommerceProduct;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Integration service provider - registers external integrations.
 */
final class IntegrationServiceProvider extends AbstractServiceProvider
{
    /**
     * Register integration services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register Brevo integration
        // Use factory to avoid circular dependency with Emails (Brevo can be created without Emails)
        $container->singleton(Brevo::class, static function (ContainerInterface $container): Brevo {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through
                }
            }
            // Create Brevo without Emails to avoid circular dependency
            // Emails will be injected later via setter if needed
            return new Brevo(null, $options);
        });
        $container->bind(BrevoIntegration::class, static function (ContainerInterface $container): BrevoIntegration {
            $legacy = $container->make(Brevo::class);
            $options = $container->make(OptionsInterface::class);
            return new BrevoIntegration($legacy, $options);
        });
        $container->bind(IntegrationInterface::class . '#brevo', BrevoIntegration::class);

        // Register Google Calendar integration - depends on Emails (optional) and OptionsInterface (optional)
        $container->singleton(GoogleCalendar::class, static function (ContainerInterface $container): GoogleCalendar {
            $emails = null;
            $options = null;
            
            // Try to get Emails from container
            if ($container->has(Emails::class)) {
                try {
                    $emails = $container->make(Emails::class);
                } catch (\Throwable $e) {
                    // Fall through
                }
            }
            
            // Try to get OptionsInterface from container
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through
                }
            }
            
            return new GoogleCalendar($emails, $options);
        });
        $container->bind(GoogleCalendarIntegration::class, static function (ContainerInterface $container): GoogleCalendarIntegration {
            $legacy = $container->make(GoogleCalendar::class);
            $options = $container->make(OptionsInterface::class);
            return new GoogleCalendarIntegration($legacy, $options);
        });
        $container->bind(IntegrationInterface::class . '#googlecalendar', GoogleCalendarIntegration::class);

        // Register GA4 integration
        $container->singleton(GA4::class, GA4::class);
        $container->bind(GA4Integration::class, static function (ContainerInterface $container): GA4Integration {
            $legacy = $container->make(GA4::class);
            $options = $container->make(OptionsInterface::class);
            return new GA4Integration($legacy, $options);
        });
        $container->bind(IntegrationInterface::class . '#ga4', GA4Integration::class);
        
        // Register other tracking integrations (no dependencies)
        $container->singleton(GoogleAds::class, GoogleAds::class);
        $container->singleton(MetaPixel::class, MetaPixel::class);
        $container->singleton(Clarity::class, Clarity::class);
        
        // Register WooCommerce integrations (no dependencies)
        $container->singleton(ExperienceProduct::class, ExperienceProduct::class);
        $container->singleton(WooCommerceProduct::class, WooCommerceProduct::class);
        $container->singleton(WooCommerceCheckout::class, WooCommerceCheckout::class);
        
        // Register Performance integration (no dependencies)
        $container->singleton(PerformanceIntegration::class, PerformanceIntegration::class);
    }

    /**
     * Boot integration services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Register hooks for all enabled integrations (new architecture)
        $integrations = [
            BrevoIntegration::class,
            GoogleCalendarIntegration::class,
            GA4Integration::class,
        ];

        foreach ($integrations as $integrationClass) {
            if ($container->has($integrationClass)) {
                try {
                    $integration = $container->make($integrationClass);
                    if ($integration instanceof IntegrationInterface && $integration->isEnabled()) {
                        $integration->register_hooks();
                    }
                } catch (\Throwable $e) {
                    // Log error but don't break the site
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'FP Experiences: Failed to boot integration %s: %s',
                            $integrationClass,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }
        
        // Register hooks for legacy integrations (HookableInterface)
        $legacyIntegrations = [
            GoogleCalendar::class,
            GA4::class,
            GoogleAds::class,
            MetaPixel::class,
            Clarity::class,
            ExperienceProduct::class,
            WooCommerceProduct::class,
            WooCommerceCheckout::class,
            PerformanceIntegration::class,
        ];
        
        foreach ($legacyIntegrations as $integrationClass) {
            try {
                if ($container->has($integrationClass)) {
                    $integration = $container->make($integrationClass);
                    if (is_object($integration) && method_exists($integration, 'register_hooks')) {
                        $integration->register_hooks();
                    }
                }
            } catch (\Throwable $e) {
                // Log error but don't break the site
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'FP Experiences: Failed to boot legacy integration %s: %s',
                        $integrationClass,
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
            Brevo::class,
            BrevoIntegration::class,
            GoogleCalendar::class,
            GoogleCalendarIntegration::class,
            GA4::class,
            GA4Integration::class,
            GoogleAds::class,
            MetaPixel::class,
            Clarity::class,
            ExperienceProduct::class,
            WooCommerceProduct::class,
            WooCommerceCheckout::class,
            PerformanceIntegration::class,
        ];
    }
}










