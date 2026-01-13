<?php

declare(strict_types=1);

namespace FP_Exp\Core\Bootstrap;

use FP_Exp\Core\Container\Container;
use FP_Exp\Core\Kernel\Kernel;
use FP_Exp\Core\Kernel\KernelInterface;
use FP_Exp\Core\Bootstrap\LifecycleManager;
use FP_Exp\Integrations\Providers\IntegrationServiceProvider;
use FP_Exp\Presentation\Admin\Providers\AdminServiceProvider;
use FP_Exp\Presentation\Frontend\Providers\FrontendServiceProvider;
use FP_Exp\Presentation\REST\Providers\RESTServiceProvider;
use FP_Exp\Providers\ApplicationServiceProvider;
use FP_Exp\Providers\BookingServiceProvider;
use FP_Exp\Providers\CoreServiceProvider;
use FP_Exp\Providers\DomainServiceProvider;
use FP_Exp\Providers\GiftServiceProvider;
use FP_Exp\Providers\LegacyServiceProvider;
use FP_Exp\Providers\UtilityServiceProvider;

/**
 * Plugin bootstrap class.
 * 
 * This is the main entry point for the new Kernel-based architecture.
 * Use Bootstrap::kernel() to access the Kernel and its container.
 */
final class Bootstrap
{
    private static ?KernelInterface $kernel = null;

    /**
     * Initialize the plugin.
     */
    public static function init(): void
    {
        if (self::$kernel !== null) {
            return;
        }

        $container = new Container();
        $kernel = new Kernel($container);

        // Register service providers (order matters: Core -> Domain -> Application -> Booking -> Gift -> Utility -> Presentation -> Integrations -> Legacy)
        $kernel->registerProvider(new CoreServiceProvider());
        $kernel->registerProvider(new DomainServiceProvider());
        $kernel->registerProvider(new ApplicationServiceProvider());
        $kernel->registerProvider(new BookingServiceProvider()); // Booking services (Cart, Orders, Checkout, Emails, etc.)
        $kernel->registerProvider(new GiftServiceProvider()); // Gift voucher services
        $kernel->registerProvider(new UtilityServiceProvider()); // Utility services (MeetingPoints, Migrations, Webhooks, etc.)
        $kernel->registerProvider(new LegacyServiceProvider()); // Legacy support for backward compatibility
        
        // Context-aware providers (only register if applicable)
        if (is_admin()) {
            $kernel->registerProvider(new AdminServiceProvider());
        } else {
            $kernel->registerProvider(new FrontendServiceProvider());
        }
        
        // REST provider (registers on rest_api_init)
        $kernel->registerProvider(new RESTServiceProvider());
        
        // Integration provider
        $kernel->registerProvider(new IntegrationServiceProvider());

        self::$kernel = $kernel;

        // Boot kernel - timing depends on context
        // In admin, boot early on plugins_loaded to ensure admin_menu hooks are registered in time
        // On frontend/REST, boot on wp_loaded
        if (is_admin()) {
            // Boot kernel on plugins_loaded in admin to ensure admin_menu hooks are registered
            // admin_menu fires during admin_init, so we need to boot before that
            if (did_action('plugins_loaded')) {
                // If plugins_loaded already fired, boot immediately
                self::$kernel->boot();
            } else {
                add_action('plugins_loaded', [self::$kernel, 'boot'], 0);
            }
        } else {
            // Boot kernel on wp_loaded (after legacy Plugin boot)
            // This ensures new architecture boots after legacy system
            if (did_action('wp_loaded')) {
                // If wp_loaded already fired, boot immediately but after legacy
                add_action('wp_loaded', [self::$kernel, 'boot'], 1);
            } else {
                add_action('wp_loaded', [self::$kernel, 'boot'], 1);
            }
        }
    }

    /**
     * Plugin activation handler.
     */
    public static function activate(): void
    {
        // Use LifecycleManager for activation
        // It will delegate to Activation class for backward compatibility
        $lifecycle = new LifecycleManager();
        $lifecycle->activate();
    }

    /**
     * Plugin deactivation handler.
     */
    public static function deactivate(): void
    {
        // Use LifecycleManager for deactivation
        // It will delegate to Activation class for backward compatibility
        $lifecycle = new LifecycleManager();
        $lifecycle->deactivate();
    }

    /**
     * Get the kernel instance.
     * 
     * @return KernelInterface|null The kernel instance, or null if not initialized
     */
    public static function kernel(): ?KernelInterface
    {
        return self::$kernel;
    }

    /**
     * Get a service from the container.
     * 
     * Helper method to easily access services without manually getting the kernel and container.
     * 
     * @template T
     * @param class-string<T> $service Service class or interface name
     * @return T|null The service instance, or null if not found or kernel not initialized
     * 
     * @example
     * $cart = Bootstrap::get(Cart::class);
     * $logger = Bootstrap::get(LoggerInterface::class);
     */
    public static function get(string $service): ?object
    {
        $kernel = self::kernel();
        if ($kernel === null) {
            return null;
        }

        $container = $kernel->container();
        if (!$container->has($service)) {
            return null;
        }

        try {
            return $container->make($service);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('FP Experiences: Failed to get service %s: %s', $service, $e->getMessage()));
            }
            return null;
        }
    }

    /**
     * Check if a service is available in the container.
     * 
     * @param string $service Service class or interface name
     * @return bool True if service is available, false otherwise
     */
    public static function has(string $service): bool
    {
        $kernel = self::kernel();
        if ($kernel === null) {
            return false;
        }

        return $kernel->container()->has($service);
    }
}
