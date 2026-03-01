<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Cron\RtbHoldExpiryCron;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Checkout as BookingCheckout;
use FP_Exp\Booking\Email\Mailer;
use FP_Exp\Booking\Email\Senders\CustomerEmailSender;
use FP_Exp\Booking\Email\Senders\StaffEmailSender;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Services\Options\OptionsInterface;

final class BookingServiceProvider extends AbstractServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(Cart::class, Cart::class);

        $container->singleton(Orders::class, static function (ContainerInterface $container): Orders {
            $cart = $container->make(Cart::class);
            return new Orders($cart);
        });

        $container->singleton(BookingCheckout::class, static function (ContainerInterface $container): BookingCheckout {
            $cart = $container->make(Cart::class);
            $orders = $container->make(Orders::class);
            return new BookingCheckout($cart, $orders);
        });

        // Mailer — centralised email dispatch
        $container->singleton(Mailer::class, static function (ContainerInterface $container): Mailer {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // fall through
                }
            }
            $options = $options ?? new \FP_Exp\Services\Options\Options();

            return new Mailer($options);
        });

        // Senders — depend on Mailer
        $container->singleton(CustomerEmailSender::class, static function (ContainerInterface $container): CustomerEmailSender {
            return new CustomerEmailSender($container->make(Mailer::class));
        });

        $container->singleton(StaffEmailSender::class, static function (ContainerInterface $container): StaffEmailSender {
            return new StaffEmailSender($container->make(Mailer::class));
        });

        // Brevo — depends on OptionsInterface; Emails set via setter later
        $container->singleton(Brevo::class, static function (ContainerInterface $container): Brevo {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // fall through
                }
            }
            return new Brevo(null, $options);
        });

        // Emails — depends on Options, CustomerEmailSender, StaffEmailSender
        $container->singleton(Emails::class, static function (ContainerInterface $container): Emails {
            $options = null;
            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // fall through
                }
            }
            $options = $options ?? new \FP_Exp\Services\Options\Options();

            $customer_sender = $container->make(CustomerEmailSender::class);
            $staff_sender = $container->make(StaffEmailSender::class);

            $emails = new Emails($options, $customer_sender, $staff_sender);

            // Resolve circular dependency: set Emails in Brevo
            try {
                $brevo = $container->make(Brevo::class);
                if ($brevo !== null && method_exists($brevo, 'set_email_service')) {
                    $brevo->set_email_service($emails);
                }
            } catch (\Throwable $e) {
                // Brevo not available
            }

            return $emails;
        });

        // RequestToBook — depends on Brevo, Mailer, OptionsInterface
        $container->singleton(RequestToBook::class, static function (ContainerInterface $container): RequestToBook {
            $brevo = $container->make(Brevo::class);
            $mailer = $container->make(Mailer::class);
            $options = null;

            if ($container->has(OptionsInterface::class)) {
                try {
                    $options = $container->make(OptionsInterface::class);
                } catch (\Throwable $e) {
                    // fall through
                }
            }

            return new RequestToBook($brevo, $mailer, $options);
        });
    }

    public function boot(ContainerInterface $container): void
    {
        $rtb_cron = new RtbHoldExpiryCron();
        $rtb_cron->register_hooks();

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

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            Cart::class,
            Orders::class,
            BookingCheckout::class,
            Mailer::class,
            CustomerEmailSender::class,
            StaffEmailSender::class,
            Emails::class,
            Brevo::class,
            RequestToBook::class,
        ];
    }
}
