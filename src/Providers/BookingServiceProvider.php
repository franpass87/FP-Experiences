<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Checkout as BookingCheckout;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Services\Options\OptionsInterface;

/**
 * Booking service provider - registers all booking-related services.
 * 
 * This provider handles:
 * - Cart management
 * - Orders processing
 * - Checkout flow
 * - Email notifications
 * - Request to book functionality
 */
final class BookingServiceProvider extends AbstractServiceProvider
{
    /**
     * Register booking services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Cart - no dependencies
        $container->singleton(Cart::class, Cart::class);

        // Orders - depends on Cart
        $container->singleton(Orders::class, static function (ContainerInterface $container): Orders {
            $cart = $container->make(Cart::class);
            return new Orders($cart);
        });

        // Checkout - depends on Cart and Orders
        $container->singleton(BookingCheckout::class, static function (ContainerInterface $container): BookingCheckout {
            $cart = $container->make(Cart::class);
            $orders = $container->make(Orders::class);
            return new BookingCheckout($cart, $orders);
        });

        // Brevo - depends on OptionsInterface (optional), Emails set via setter
        $container->singleton(Brevo::class, static function (ContainerInterface $container): Brevo {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            // Emails will be injected via setter to resolve circular dependency
            return new Brevo(null, $options);
        });

        // Emails - depends on Brevo (optional) and OptionsInterface (optional)
        $container->singleton(Emails::class, static function (ContainerInterface $container): Emails {
            $brevo = null;
            $options = null;
            
            // Try to get Brevo from container
            if ($container->has(Brevo::class)) {
                try {
                    $brevo = $container->make(Brevo::class);
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
            
            $emails = new Emails($brevo, $options);
            
            // Resolve circular dependency: set Emails in Brevo
            if ($brevo !== null && method_exists($brevo, 'set_email_service')) {
                $brevo->set_email_service($emails);
            }
            
            return $emails;
        });

        // RequestToBook - depends on Brevo and OptionsInterface (optional)
        $container->singleton(RequestToBook::class, static function (ContainerInterface $container): RequestToBook {
            $brevo = $container->make(Brevo::class);
            $options = null;
            
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            
            return new RequestToBook($brevo, $options);
        });
    }

    /**
     * Boot booking services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Register hooks for all booking services that implement HookableInterface
        $hookables = [
            Cart::class,
            Orders::class,
            BookingCheckout::class,
            Emails::class,
            Brevo::class,
            RequestToBook::class,
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
                        'FP Experiences: Failed to boot %s: %s',
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
            Cart::class,
            Orders::class,
            BookingCheckout::class,
            Emails::class,
            Brevo::class,
            RequestToBook::class,
        ];
    }
}



