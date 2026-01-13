<?php

declare(strict_types=1);

namespace FP_Exp\Presentation\Admin\Providers;

use FP_Exp\Admin\AdminMenu;
use FP_Exp\Admin\CalendarAdmin;
use FP_Exp\Admin\CheckinPage;
use FP_Exp\Admin\EmailsPage;
use FP_Exp\Admin\ExperienceMetaBoxes;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\CalendarMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\DetailsMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\ExtrasMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\MeetingPointMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\PolicyMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\PricingMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Handlers\SEOMetaBoxHandler;
use FP_Exp\Admin\ExperiencePageCreator;
use FP_Exp\Admin\HelpPage;
use FP_Exp\Admin\ImporterPage;
use FP_Exp\Admin\LanguageAdmin;
use FP_Exp\Admin\LogsPage;
use FP_Exp\Admin\Onboarding;
use FP_Exp\Admin\OrdersPage;
use FP_Exp\Admin\RequestsPage;
use FP_Exp\Admin\SettingsPage;
use FP_Exp\Admin\ToolsPage;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;

/**
 * Admin service provider - registers admin pages and functionality.
 * Only loads in admin context.
 */
final class AdminServiceProvider extends AbstractServiceProvider
{
    /**
     * Register admin services.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Register admin pages
        $container->singleton(SettingsPage::class, static function (ContainerInterface $container): SettingsPage {
            // Try to inject OptionsInterface if available
            $options = null;
            if ($container->has(\FP_Exp\Services\Options\OptionsInterface::class)) {
                try {
                    $options = $container->make(\FP_Exp\Services\Options\OptionsInterface::class);
                } catch (\Throwable $e) {
                    // Fall through to default
                }
            }
            return new SettingsPage($options);
        });
        
        // Register ExperienceMetaBoxes handlers (for DI)
        $container->singleton(CalendarMetaBoxHandler::class, CalendarMetaBoxHandler::class);
        $container->singleton(DetailsMetaBoxHandler::class, DetailsMetaBoxHandler::class);
        $container->singleton(PolicyMetaBoxHandler::class, PolicyMetaBoxHandler::class);
        $container->singleton(PricingMetaBoxHandler::class, PricingMetaBoxHandler::class);
        $container->singleton(SEOMetaBoxHandler::class, SEOMetaBoxHandler::class);
        $container->singleton(ExtrasMetaBoxHandler::class, ExtrasMetaBoxHandler::class);
        $container->singleton(MeetingPointMetaBoxHandler::class, MeetingPointMetaBoxHandler::class);
        
        // Register ExperienceMetaBoxes with handlers from container
        $container->singleton(ExperienceMetaBoxes::class, static function (ContainerInterface $container): ExperienceMetaBoxes {
            $calendar = $container->has(CalendarMetaBoxHandler::class) 
                ? $container->make(CalendarMetaBoxHandler::class) 
                : null;
            $details = $container->has(DetailsMetaBoxHandler::class) 
                ? $container->make(DetailsMetaBoxHandler::class) 
                : null;
            $policy = $container->has(PolicyMetaBoxHandler::class) 
                ? $container->make(PolicyMetaBoxHandler::class) 
                : null;
            $pricing = $container->has(PricingMetaBoxHandler::class) 
                ? $container->make(PricingMetaBoxHandler::class) 
                : null;
            $seo = $container->has(SEOMetaBoxHandler::class) 
                ? $container->make(SEOMetaBoxHandler::class) 
                : null;
            $extras = $container->has(ExtrasMetaBoxHandler::class) 
                ? $container->make(ExtrasMetaBoxHandler::class) 
                : null;
            $meeting_point = $container->has(MeetingPointMetaBoxHandler::class) 
                ? $container->make(MeetingPointMetaBoxHandler::class) 
                : null;
            
            return new ExperienceMetaBoxes($calendar, $details, $policy, $pricing, $seo, $extras, $meeting_point);
        });
        
        // Register CalendarAdmin - depends on Orders
        $container->singleton(CalendarAdmin::class, static function (ContainerInterface $container): CalendarAdmin {
            $orders = $container->make(Orders::class);
            return new CalendarAdmin($orders);
        });
        
        // Register RequestsPage - depends on RequestToBook
        $container->singleton(RequestsPage::class, static function (ContainerInterface $container): RequestsPage {
            $request_to_book = $container->make(RequestToBook::class);
            return new RequestsPage($request_to_book);
        });
        
        // Register ToolsPage - depends on SettingsPage
        $container->singleton(ToolsPage::class, static function (ContainerInterface $container): ToolsPage {
            $settings_page = $container->make(SettingsPage::class);
            return new ToolsPage($settings_page);
        });
        
        // Register EmailsPage - depends on SettingsPage
        $container->singleton(EmailsPage::class, static function (ContainerInterface $container): EmailsPage {
            $settings_page = $container->make(SettingsPage::class);
            return new EmailsPage($settings_page);
        });
        
        // Register admin pages without dependencies
        $container->singleton(LogsPage::class, LogsPage::class);
        $container->singleton(CheckinPage::class, CheckinPage::class);
        $container->singleton(OrdersPage::class, OrdersPage::class);
        $container->singleton(HelpPage::class, HelpPage::class);
        $container->singleton(ImporterPage::class, ImporterPage::class);
        $container->singleton(ExperiencePageCreator::class, ExperiencePageCreator::class);
        $container->singleton(Onboarding::class, Onboarding::class);
        $container->singleton(LanguageAdmin::class, LanguageAdmin::class);
        
        // Register AdminMenu - depends on all admin pages
        $container->singleton(AdminMenu::class, static function (ContainerInterface $container): AdminMenu {
            $settings_page = $container->make(SettingsPage::class);
            $calendar_admin = $container->make(CalendarAdmin::class);
            $logs_page = $container->make(LogsPage::class);
            $requests_page = $container->make(RequestsPage::class);
            $tools_page = $container->make(ToolsPage::class);
            $emails_page = $container->make(EmailsPage::class);
            $checkin_page = $container->make(CheckinPage::class);
            $orders_page = $container->make(OrdersPage::class);
            $help_page = $container->make(HelpPage::class);
            $importer_page = $container->make(ImporterPage::class);
            
            // ExperiencePageCreator is optional
            $page_creator = null;
            if ($container->has(ExperiencePageCreator::class)) {
                try {
                    $page_creator = $container->make(ExperiencePageCreator::class);
                } catch (\Throwable $e) {
                    // Optional, continue without it
                }
            }
            
            return new AdminMenu(
                $settings_page,
                $calendar_admin,
                $logs_page,
                $requests_page,
                $tools_page,
                $emails_page,
                $checkin_page,
                $orders_page,
                $help_page,
                $importer_page,
                $page_creator
            );
        });
    }

    /**
     * Boot admin services and register hooks.
     *
     * @param ContainerInterface $container Container instance
     */
    public function boot(ContainerInterface $container): void
    {
        // Only boot in admin context
        if (!is_admin()) {
            return;
        }

        // List of admin services that implement HookableInterface
        $hookables = [
            SettingsPage::class,
            ExperienceMetaBoxes::class,
            CalendarAdmin::class,
            RequestsPage::class,
            ToolsPage::class,
            EmailsPage::class,
            LogsPage::class,
            CheckinPage::class,
            OrdersPage::class,
            HelpPage::class,
            ImporterPage::class,
            ExperiencePageCreator::class,
            Onboarding::class,
            LanguageAdmin::class,
            AdminMenu::class,
        ];

        // Register hooks for all admin services
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
                        'FP Experiences: Failed to boot admin service %s: %s',
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
            SettingsPage::class,
            ExperienceMetaBoxes::class,
            CalendarMetaBoxHandler::class,
            DetailsMetaBoxHandler::class,
            PolicyMetaBoxHandler::class,
            PricingMetaBoxHandler::class,
            SEOMetaBoxHandler::class,
            ExtrasMetaBoxHandler::class,
            MeetingPointMetaBoxHandler::class,
            CalendarAdmin::class,
            RequestsPage::class,
            ToolsPage::class,
            EmailsPage::class,
            LogsPage::class,
            CheckinPage::class,
            OrdersPage::class,
            HelpPage::class,
            ImporterPage::class,
            ExperiencePageCreator::class,
            Onboarding::class,
            LanguageAdmin::class,
            AdminMenu::class,
        ];
    }
}

