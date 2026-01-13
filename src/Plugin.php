<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Admin\AdminMenu;
use FP_Exp\Admin\CalendarAdmin;
use FP_Exp\Admin\CheckinPage;
use FP_Exp\Admin\EmailsPage;
use FP_Exp\Admin\ExperienceMetaBoxes;
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
use FP_Exp\Api\RestRoutes;
use FP_Exp\Api\Webhooks;
use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Checkout as BookingCheckout;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Elementor\WidgetsRegistrar as ElementorWidgetsRegistrar;
use FP_Exp\Front\SingleExperienceRenderer;
use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Integrations\Clarity;
use FP_Exp\Integrations\GA4;
use FP_Exp\Integrations\GoogleAds;
use FP_Exp\Integrations\GoogleCalendar;
use FP_Exp\Integrations\MetaPixel;
use FP_Exp\Localization\AutoTranslator;
use FP_Exp\MeetingPoints\Manager as MeetingPointsManager;
use FP_Exp\Migrations\Runner as MigrationRunner;
use FP_Exp\PostTypes\ExperienceCPT;
use FP_Exp\Shortcodes\Registrar as ShortcodeRegistrar;
use FP_Exp\Utils\Helpers;

use function add_action;
use function load_plugin_textdomain;
use function plugin_basename;

/**
 * Plugin facade class - provides backward compatibility access to services.
 * 
 * This class is deprecated and will be removed in v2.0.0.
 * Use Bootstrap::kernel() or Bootstrap::get() instead.
 * 
 * @deprecated 1.2.0 Use Bootstrap::kernel() or Bootstrap::get() instead.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    /**
     * Get kernel instance if available (new architecture).
     *
     * @return \FP_Exp\Core\Kernel\KernelInterface|null
     */
    public static function kernel(): ?\FP_Exp\Core\Kernel\KernelInterface
    {
        if (class_exists(\FP_Exp\Core\Bootstrap\Bootstrap::class)) {
            return \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        }

        return null;
    }

    /**
     * Get plugin instance (singleton pattern).
     *
     * @deprecated 1.2.0 Use Bootstrap::kernel() instead to access the new architecture.
     *                   This method is kept for backward compatibility but will be removed in version 2.0.0.
     * @see \FP_Exp\Core\Bootstrap\Bootstrap::kernel()
     * @param bool $suppress_deprecation Suppress deprecation warning (for internal bootstrap use)
     * @return Plugin
     */
    public static function instance(bool $suppress_deprecation = false): Plugin
    {
        if (!$suppress_deprecation && WP_DEBUG && function_exists('_deprecated_function')) {
            // Check if called from plugin bootstrap to avoid warning during legacy boot
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $is_bootstrap_call = false;
            foreach ($backtrace as $frame) {
                if (isset($frame['file']) && strpos($frame['file'], 'fp-experiences.php') !== false) {
                    $is_bootstrap_call = true;
                    break;
                }
            }
            
            if (!$is_bootstrap_call) {
                _deprecated_function(
                    __METHOD__,
                    '1.2.0',
                    'Bootstrap::kernel()'
                );
            }
        }

        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        // Facade pattern - no initialization needed
        // All services are now managed by Service Providers
    }

    /**
     * Boot plugin services.
     * 
     * This method is now a no-op as all services are booted by Service Providers.
     * Kept for backward compatibility.
     * 
     * @deprecated 1.2.0 Services are now booted automatically by Service Providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Load translations on init to comply with WP 6.7 timing requirements
        // This is now handled by CoreServiceProvider, but kept here for backward compatibility
        add_action('init', [$this, 'load_textdomain']);

        // Database tables registration is now handled by CoreServiceProvider
        // Role manager and capabilities are handled by LegacyServiceProvider
        // All service hooks are registered by their respective Service Providers
        
        // This method is essentially a no-op now, but kept for backward compatibility
    }

    /**
     * Load plugin textdomain.
     * 
     * @deprecated 1.2.0 This is now handled by CoreServiceProvider.
     */
    public function load_textdomain(): void
    {
        $domain = 'fp-experiences';
        $plugin_dir = dirname(plugin_basename(FP_EXP_PLUGIN_FILE ?? __FILE__));
        $languages_path = WP_PLUGIN_DIR . '/' . $plugin_dir . '/languages';
        
        // First, try to load WPML-specific locale file (short code like 'en', 'de')
        $wpml_loaded = false;
        if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
            $wpml_locale = ICL_LANGUAGE_CODE; // e.g., 'en', 'de', 'it'
            $wpml_mo_file = $languages_path . '/' . $domain . '-' . $wpml_locale . '.mo';
            if (file_exists($wpml_mo_file)) {
                $wpml_loaded = load_textdomain($domain, $wpml_mo_file);
            }
        }
        
        // Fallback to standard WordPress locale loading if WPML didn't load
        if (!$wpml_loaded) {
            load_plugin_textdomain($domain, false, $plugin_dir . '/languages');
        }
    }

    /**
     * Register database tables.
     * 
     * @deprecated 1.2.0 Use DatabaseTables::register() instead. This is now handled by CoreServiceProvider.
     */
    public function register_database_tables(): void
    {
        \FP_Exp\Utils\DatabaseTables::register();
    }

    /**
     * Get ExperienceCPT instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ExperienceCPT::class) instead.
     */
    public function experience_cpt(): ExperienceCPT
    {
        $cpt = \FP_Exp\Core\Bootstrap\Bootstrap::get(ExperienceCPT::class);
        if ($cpt === null) {
            throw new \RuntimeException('ExperienceCPT service not available');
        }
        return $cpt;
    }

    // Facade methods for backward compatibility - delegate to container
    
    /**
     * Get Cart instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Cart::class) instead.
     */
    public function cart(): Cart
    {
        $cart = \FP_Exp\Core\Bootstrap\Bootstrap::get(Cart::class);
        if ($cart === null) {
            throw new \RuntimeException('Cart service not available');
        }
        return $cart;
    }

    /**
     * Get Orders instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Orders::class) instead.
     */
    public function orders(): Orders
    {
        $orders = \FP_Exp\Core\Bootstrap\Bootstrap::get(Orders::class);
        if ($orders === null) {
            throw new \RuntimeException('Orders service not available');
        }
        return $orders;
    }

    /**
     * Get Checkout instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(BookingCheckout::class) instead.
     */
    public function checkout(): BookingCheckout
    {
        $checkout = \FP_Exp\Core\Bootstrap\Bootstrap::get(BookingCheckout::class);
        if ($checkout === null) {
            throw new \RuntimeException('Checkout service not available');
        }
        return $checkout;
    }

    /**
     * Get Emails instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Emails::class) instead.
     */
    public function emails(): Emails
    {
        $emails = \FP_Exp\Core\Bootstrap\Bootstrap::get(Emails::class);
        if ($emails === null) {
            throw new \RuntimeException('Emails service not available');
        }
        return $emails;
    }

    /**
     * Get Brevo instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Brevo::class) instead.
     */
    public function brevo(): Brevo
    {
        $brevo = \FP_Exp\Core\Bootstrap\Bootstrap::get(Brevo::class);
        if ($brevo === null) {
            throw new \RuntimeException('Brevo service not available');
        }
        return $brevo;
    }

    /**
     * Get RequestToBook instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(RequestToBook::class) instead.
     */
    public function request_to_book(): RequestToBook
    {
        $rtb = \FP_Exp\Core\Bootstrap\Bootstrap::get(RequestToBook::class);
        if ($rtb === null) {
            throw new \RuntimeException('RequestToBook service not available');
        }
        return $rtb;
    }

    /**
     * Get GoogleCalendar instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(GoogleCalendar::class) instead.
     */
    public function google_calendar(): GoogleCalendar
    {
        $calendar = \FP_Exp\Core\Bootstrap\Bootstrap::get(GoogleCalendar::class);
        if ($calendar === null) {
            throw new \RuntimeException('GoogleCalendar service not available');
        }
        return $calendar;
    }

    /**
     * Get GA4 instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(GA4::class) instead.
     */
    public function ga4(): GA4
    {
        $ga4 = \FP_Exp\Core\Bootstrap\Bootstrap::get(GA4::class);
        if ($ga4 === null) {
            throw new \RuntimeException('GA4 service not available');
        }
        return $ga4;
    }

    /**
     * Get GoogleAds instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(GoogleAds::class) instead.
     */
    public function google_ads(): GoogleAds
    {
        $ads = \FP_Exp\Core\Bootstrap\Bootstrap::get(GoogleAds::class);
        if ($ads === null) {
            throw new \RuntimeException('GoogleAds service not available');
        }
        return $ads;
    }

    /**
     * Get MetaPixel instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(MetaPixel::class) instead.
     */
    public function meta_pixel(): MetaPixel
    {
        $pixel = \FP_Exp\Core\Bootstrap\Bootstrap::get(MetaPixel::class);
        if ($pixel === null) {
            throw new \RuntimeException('MetaPixel service not available');
        }
        return $pixel;
    }

    /**
     * Get Clarity instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Clarity::class) instead.
     */
    public function clarity(): Clarity
    {
        $clarity = \FP_Exp\Core\Bootstrap\Bootstrap::get(Clarity::class);
        if ($clarity === null) {
            throw new \RuntimeException('Clarity service not available');
        }
        return $clarity;
    }

    /**
     * Get ElementorWidgetsRegistrar instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ElementorWidgetsRegistrar::class) instead.
     */
    public function elementor_widgets(): ElementorWidgetsRegistrar
    {
        $widgets = \FP_Exp\Core\Bootstrap\Bootstrap::get(ElementorWidgetsRegistrar::class);
        if ($widgets === null) {
            throw new \RuntimeException('ElementorWidgetsRegistrar service not available');
        }
        return $widgets;
    }

    /**
     * Get VoucherCPT instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(VoucherCPT::class) instead.
     */
    public function gift_cpt(): VoucherCPT
    {
        $cpt = \FP_Exp\Core\Bootstrap\Bootstrap::get(VoucherCPT::class);
        if ($cpt === null) {
            throw new \RuntimeException('VoucherCPT service not available');
        }
        return $cpt;
    }

    /**
     * Get VoucherManager instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(VoucherManager::class) instead.
     */
    public function gift_manager(): VoucherManager
    {
        $manager = \FP_Exp\Core\Bootstrap\Bootstrap::get(VoucherManager::class);
        if ($manager === null) {
            throw new \RuntimeException('VoucherManager service not available');
        }
        return $manager;
    }

    /**
     * Get RestRoutes instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(RestRoutes::class) instead.
     */
    public function rest_routes(): RestRoutes
    {
        $routes = \FP_Exp\Core\Bootstrap\Bootstrap::get(RestRoutes::class);
        if ($routes === null) {
            throw new \RuntimeException('RestRoutes service not available');
        }
        return $routes;
    }

    /**
     * Get Webhooks instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Webhooks::class) instead.
     */
    public function webhooks(): Webhooks
    {
        $webhooks = \FP_Exp\Core\Bootstrap\Bootstrap::get(Webhooks::class);
        if ($webhooks === null) {
            throw new \RuntimeException('Webhooks service not available');
        }
        return $webhooks;
    }

    /**
     * Get MeetingPointsManager instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(MeetingPointsManager::class) instead.
     */
    public function meeting_points(): MeetingPointsManager
    {
        $manager = \FP_Exp\Core\Bootstrap\Bootstrap::get(MeetingPointsManager::class);
        if ($manager === null) {
            throw new \RuntimeException('MeetingPointsManager service not available');
        }
        return $manager;
    }

    /**
     * Get MigrationRunner instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(MigrationRunner::class) instead.
     */
    public function migrations(): MigrationRunner
    {
        $runner = \FP_Exp\Core\Bootstrap\Bootstrap::get(MigrationRunner::class);
        if ($runner === null) {
            throw new \RuntimeException('MigrationRunner service not available');
        }
        return $runner;
    }

    /**
     * Get SingleExperienceRenderer instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(SingleExperienceRenderer::class) instead.
     */
    public function single_experience_renderer(): SingleExperienceRenderer
    {
        $renderer = \FP_Exp\Core\Bootstrap\Bootstrap::get(SingleExperienceRenderer::class);
        if ($renderer === null) {
            throw new \RuntimeException('SingleExperienceRenderer service not available');
        }
        return $renderer;
    }

    /**
     * Get AutoTranslator instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(AutoTranslator::class) instead.
     */
    public function auto_translator(): AutoTranslator
    {
        $translator = \FP_Exp\Core\Bootstrap\Bootstrap::get(AutoTranslator::class);
        if ($translator === null) {
            throw new \RuntimeException('AutoTranslator service not available');
        }
        return $translator;
    }

    /**
     * Get ShortcodeRegistrar instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ShortcodeRegistrar::class) instead.
     */
    public function shortcodes(): ShortcodeRegistrar
    {
        $registrar = \FP_Exp\Core\Bootstrap\Bootstrap::get(ShortcodeRegistrar::class);
        if ($registrar === null) {
            throw new \RuntimeException('ShortcodeRegistrar service not available');
        }
        return $registrar;
    }

    // Admin services (only available in admin context)
    
    /**
     * Get SettingsPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(SettingsPage::class) instead.
     */
    public function settings_page(): ?SettingsPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(SettingsPage::class);
    }

    /**
     * Get CalendarAdmin instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(CalendarAdmin::class) instead.
     */
    public function calendar_admin(): ?CalendarAdmin
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(CalendarAdmin::class);
    }

    /**
     * Get LogsPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(LogsPage::class) instead.
     */
    public function logs_page(): ?LogsPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(LogsPage::class);
    }

    /**
     * Get RequestsPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(RequestsPage::class) instead.
     */
    public function requests_page(): ?RequestsPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(RequestsPage::class);
    }

    /**
     * Get ExperienceMetaBoxes instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ExperienceMetaBoxes::class) instead.
     */
    public function experience_meta_boxes(): ?ExperienceMetaBoxes
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(ExperienceMetaBoxes::class);
    }

    /**
     * Get ToolsPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ToolsPage::class) instead.
     */
    public function tools_page(): ?ToolsPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(ToolsPage::class);
    }

    /**
     * Get EmailsPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(EmailsPage::class) instead.
     */
    public function emails_page(): ?EmailsPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(EmailsPage::class);
    }

    /**
     * Get CheckinPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(CheckinPage::class) instead.
     */
    public function checkin_page(): ?CheckinPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(CheckinPage::class);
    }

    /**
     * Get OrdersPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(OrdersPage::class) instead.
     */
    public function orders_page(): ?OrdersPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(OrdersPage::class);
    }

    /**
     * Get HelpPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(HelpPage::class) instead.
     */
    public function help_page(): ?HelpPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(HelpPage::class);
    }

    /**
     * Get ImporterPage instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ImporterPage::class) instead.
     */
    public function importer_page(): ?ImporterPage
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(ImporterPage::class);
    }

    /**
     * Get ExperiencePageCreator instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(ExperiencePageCreator::class) instead.
     */
    public function page_creator(): ?ExperiencePageCreator
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(ExperiencePageCreator::class);
    }

    /**
     * Get Onboarding instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(Onboarding::class) instead.
     */
    public function onboarding(): ?Onboarding
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(Onboarding::class);
    }

    /**
     * Get LanguageAdmin instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(LanguageAdmin::class) instead.
     */
    public function language_admin(): ?LanguageAdmin
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(LanguageAdmin::class);
    }

    /**
     * Get AdminMenu instance.
     * 
     * @deprecated 1.2.0 Use Bootstrap::get(AdminMenu::class) instead.
     */
    public function admin_menu(): ?AdminMenu
    {
        if (!is_admin()) {
            return null;
        }
        return \FP_Exp\Core\Bootstrap\Bootstrap::get(AdminMenu::class);
    }
}
