<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Api\RestRoutes;
use FP_Exp\Api\Webhooks;
use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Checkout as BookingCheckout;
use FP_Exp\Admin\AdminMenu;
use FP_Exp\Admin\CalendarAdmin;
use FP_Exp\Admin\RequestsPage;
use FP_Exp\Admin\ExperienceMetaBoxes;
use FP_Exp\Admin\SettingsPage;
use FP_Exp\Admin\LanguageAdmin;
use FP_Exp\Admin\LogsPage;
use FP_Exp\Admin\ToolsPage;
use FP_Exp\Admin\EmailsPage;
use FP_Exp\Admin\CheckinPage;
use FP_Exp\Admin\OrdersPage;
use FP_Exp\Admin\HelpPage;
use FP_Exp\Admin\ImporterPage;
use FP_Exp\Admin\ImporterStats;
use FP_Exp\Admin\ExperiencePageCreator;
use FP_Exp\Admin\Onboarding;
use FP_Exp\Localization\AutoTranslator;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\Elementor\WidgetsRegistrar as ElementorWidgetsRegistrar;
use FP_Exp\MeetingPoints\Manager as MeetingPointsManager;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Integrations\Clarity;
use FP_Exp\Integrations\GA4;
use FP_Exp\Integrations\GoogleCalendar;
use FP_Exp\Integrations\GoogleAds;
use FP_Exp\Integrations\MetaPixel;
use FP_Exp\PostTypes\ExperienceCPT;
use FP_Exp\Migrations\Runner as MigrationRunner;
use FP_Exp\Shortcodes\Registrar as ShortcodeRegistrar;
use FP_Exp\Front\SingleExperienceRenderer;
use FP_Exp\Utils\Helpers;
use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Gift\VoucherTable;
use Throwable;
use WP_User;

use function add_action;
use function __;
use function esc_html;
use function esc_html__;
use function load_plugin_textdomain;
use function plugin_basename;
use function sanitize_text_field;
use function get_option;
use function get_role;
use function update_option;
use function is_admin;
use function is_multisite;
use function in_array;
use function wp_get_current_user;

final class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    private ExperienceCPT $experience_cpt;

    private ShortcodeRegistrar $shortcodes;

    private Cart $cart;

    private Orders $orders;

    private BookingCheckout $checkout;

    private Emails $emails;

    private Brevo $brevo;

    private RequestToBook $request_to_book;

    private GoogleCalendar $google_calendar;

    private GA4 $ga4;

    private GoogleAds $google_ads;

    private MetaPixel $meta_pixel;

    private Clarity $clarity;

    private ?SettingsPage $settings_page = null;

    private ?CalendarAdmin $calendar_admin = null;

    private ?LogsPage $logs_page = null;

    private ?RequestsPage $requests_page = null;

    private ?ExperienceMetaBoxes $experience_meta_boxes = null;

    private ?ToolsPage $tools_page = null;

    private ?EmailsPage $emails_page = null;

    private ?CheckinPage $checkin_page = null;

    private ?OrdersPage $orders_page = null;

    private ?HelpPage $help_page = null;

    private ?ImporterPage $importer_page = null;

    private ?ExperiencePageCreator $page_creator = null;

    private ?AdminMenu $admin_menu = null;

    private ?LanguageAdmin $language_admin = null;

    private ?AutoTranslator $auto_translator = null;

    private ?ElementorWidgetsRegistrar $elementor_widgets = null;

    private ?RestRoutes $rest_routes = null;

    private ?Webhooks $webhooks = null;

    private ?MeetingPointsManager $meeting_points = null;

    private ?Onboarding $onboarding = null;

    private ?VoucherCPT $gift_cpt = null;

    private ?VoucherManager $gift_manager = null;

    private ?MigrationRunner $migrations = null;

    private ?SingleExperienceRenderer $single_experience_renderer = null;

    /**
     * @var array<int, array{component: string, action: string, message: string}>
     */
    private array $boot_errors = [];

    private bool $boot_notice_hooked = false;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->experience_cpt = new ExperienceCPT();
        $this->shortcodes = new ShortcodeRegistrar();
        $this->cart = Cart::instance();
        $this->orders = new Orders($this->cart);
        $this->checkout = new BookingCheckout($this->cart, $this->orders);
        $this->brevo = new Brevo();
        $this->emails = new Emails($this->brevo);
        $this->brevo->set_email_service($this->emails);
        $this->google_calendar = new GoogleCalendar();
        $this->google_calendar->set_email_service($this->emails);
        $this->request_to_book = new RequestToBook($this->brevo);
        $this->ga4 = new GA4();
        $this->google_ads = new GoogleAds();
        $this->meta_pixel = new MetaPixel();
        $this->clarity = new Clarity();
        $this->elementor_widgets = new ElementorWidgetsRegistrar();
        $this->gift_cpt = new VoucherCPT();
        $this->gift_manager = new VoucherManager();
        $this->rest_routes = new RestRoutes($this->gift_manager);
        $this->webhooks = new Webhooks();
        $this->meeting_points = new MeetingPointsManager();
        $this->migrations = new MigrationRunner();
        $this->single_experience_renderer = new SingleExperienceRenderer();
        $this->auto_translator = new AutoTranslator();
        
        // Integrazione con plugin cache/performance - esclude REST API dalla cache
        $performance_integration = new Integrations\PerformanceIntegration();
        $performance_integration->register();
        
        // Integrazione con WooCommerce - prodotto virtuale per esperienze
        $wc_experience_product = new Integrations\ExperienceProduct();
        $wc_experience_product->register();
        
        // Integrazione con WooCommerce - display personalizzato cart/checkout
        $wc_product_integration = new Integrations\WooCommerceProduct();
        $wc_product_integration->register();
        
        // Integrazione con WooCommerce checkout - validazione slot
        $wc_checkout_integration = new Integrations\WooCommerceCheckout();
        $wc_checkout_integration->register();

        if (is_admin()) {
            $this->settings_page = new SettingsPage();
            $this->calendar_admin = new CalendarAdmin($this->orders);
            $this->logs_page = new LogsPage();
            $this->requests_page = new RequestsPage($this->request_to_book);
            $this->experience_meta_boxes = new ExperienceMetaBoxes();
            $this->tools_page = new ToolsPage($this->settings_page);
            $this->emails_page = new EmailsPage($this->settings_page);
            $this->checkin_page = new CheckinPage();
            $this->orders_page = new OrdersPage();
            $this->help_page = new HelpPage();
            $this->importer_page = new ImporterPage();
            $this->page_creator = new ExperiencePageCreator();
            $this->onboarding = new Onboarding();
            $this->language_admin = new LanguageAdmin();
            $this->admin_menu = new AdminMenu(
                $this->settings_page,
                $this->calendar_admin,
                $this->logs_page,
                $this->requests_page,
                $this->tools_page,
                $this->emails_page,
                $this->checkin_page,
                $this->orders_page,
                $this->help_page,
                $this->importer_page,
                $this->page_creator
            );
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Load translations on init to comply with WP 6.7 timing requirements
        add_action('init', [$this, 'load_textdomain']);

        add_action('plugins_loaded', [$this, 'maybe_update_roles'], 5);
        add_action('plugins_loaded', [$this, 'register_database_tables']);
        add_action('plugins_loaded', [Helpers::class, 'ensure_admin_capabilities'], 1);
        add_action('admin_init', [$this, 'maybe_update_roles']);
        add_action('admin_init', [Helpers::class, 'ensure_admin_capabilities'], 1);
        add_filter('map_meta_cap', [$this, 'map_meta_cap_fallback'], 10, 4);

        $hookables = [
            $this->experience_cpt,
            $this->shortcodes,
            $this->cart,
            $this->orders,
            $this->checkout,
            $this->emails,
            $this->brevo,
            $this->request_to_book,
            $this->google_calendar,
            $this->ga4,
            $this->google_ads,
            $this->meta_pixel,
            $this->clarity,
            $this->elementor_widgets,
            $this->gift_cpt,
            $this->gift_manager,
            $this->rest_routes,
            $this->webhooks,
            $this->meeting_points,
            $this->migrations,
            $this->single_experience_renderer,
            $this->auto_translator,
        ];

        if (is_admin()) {
            $hookables = array_merge(
                $hookables,
                [
                    $this->settings_page,
                    $this->calendar_admin,
                    $this->logs_page,
                    $this->requests_page,
                    $this->experience_meta_boxes,
                    $this->tools_page,
                    $this->emails_page,
                    $this->checkin_page,
                    $this->orders_page,
                    $this->help_page,
                    $this->importer_page,
                    $this->page_creator,
                    $this->onboarding,
                    $this->language_admin,
                    $this->admin_menu,
                ]
            );
        }

        foreach ($hookables as $hookable) {
            if (! is_object($hookable) || ! method_exists($hookable, 'register_hooks')) {
                continue;
            }

            $this->guard(
                static function () use ($hookable): void {
                    $hookable->register_hooks();
                },
                get_class($hookable),
                'register_hooks'
            );
        }

        if (class_exists(ImporterStats::class)) {
            $this->guard(
                static function (): void {
                    ImporterStats::register_hooks();
                },
                ImporterStats::class,
                'register_hooks'
            );
        }
    }

    public function maybe_update_roles(): void
    {
        if (! is_admin()) {
            return;
        }

        $current_version = Activation::roles_version();
        $stored_version = get_option('fp_exp_roles_version');

        $administrator = get_role('administrator');
        $administrator_missing_caps = false;
        /** @var array<string, bool> $manager_capabilities */
        $manager_capabilities = Activation::manager_capabilities();
        $current_user = wp_get_current_user();
        $current_user_missing_caps = false;

        if ($administrator) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($administrator->capabilities[$capability])) {
                    continue;
                }

                $administrator_missing_caps = true;
                break;
            }
        }

        if ($current_user instanceof WP_User && in_array('administrator', $current_user->roles, true)) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($current_user->allcaps[$capability])) {
                    continue;
                }

                $current_user_missing_caps = true;
                break;
            }
        }

        if ($stored_version === $current_version && ! $administrator_missing_caps && ! $current_user_missing_caps) {
            return;
        }

        Activation::register_roles();
        update_option('fp_exp_roles_version', $current_version);

        if ($current_user_missing_caps && $current_user instanceof WP_User) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if (! empty($current_user->allcaps[$capability])) {
                    continue;
                }

                $current_user->add_cap($capability);
            }
        }
    }

    public function map_meta_cap_fallback(array $caps, string $cap, int $user_id, array $args)
    {
        if (! in_array($cap, ['delete_post', 'edit_post', 'read_post'], true)) {
            return $caps;
        }

        if (! empty($args)) {
            return $caps;
        }

        $caps = ['fp_exp_admin_access'];

        return $caps;
    }

    /**
     * @param callable $callback
     */
    private function guard(callable $callback, string $component, string $action): void
    {
        try {
            $callback();

            return;
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());
            if ('' === $message) {
                $message = get_class($exception);
            }

            $message = sanitize_text_field($message);

            if (strlen($message) > 160) {
                $message = substr($message, 0, 157) . '…';
            }

            $this->boot_errors[] = [
                'component' => $component,
                'action' => $action,
                'message' => $message,
            ];

            Helpers::log_debug('plugin_boot', 'Component bootstrap failed', [
                'component' => $component,
                'action' => $action,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            if (! $this->boot_notice_hooked) {
                add_action('admin_notices', [$this, 'render_boot_errors']);

                if (is_multisite()) {
                    add_action('network_admin_notices', [$this, 'render_boot_errors']);
                }

                $this->boot_notice_hooked = true;
            }
        }
    }

    public function render_boot_errors(): void
    {
        if (empty($this->boot_errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__(
            'FP Experiences could not finish loading some modules. Check the logs for more details.',
            'fp-experiences'
        ) . '</p>';
        echo '<ul>';

        foreach ($this->boot_errors as $error) {
            $summary = sprintf(
                /* translators: 1: module name, 2: method, 3: error message */
                __('%1$s::%2$s — %3$s', 'fp-experiences'),
                $error['component'],
                $error['action'],
                $error['message']
            );

            echo '<li>' . esc_html($summary) . '</li>';
        }

        echo '</ul></div>';
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'fp-experiences',
            false,
            dirname(plugin_basename(FP_EXP_PLUGIN_FILE ?? __FILE__)) . '/languages'
        );
    }

    public function register_database_tables(): void
    {
        global $wpdb;

        $wpdb->fp_exp_slots = Slots::table_name();
        $wpdb->fp_exp_reservations = Reservations::table_name();
        $wpdb->fp_exp_resources = Resources::table_name();
        $wpdb->fp_exp_gift_vouchers = VoucherTable::table_name();

        if (! is_array($wpdb->tables)) {
            $wpdb->tables = [];
        }

        foreach (['fp_exp_slots', 'fp_exp_reservations', 'fp_exp_resources', 'fp_exp_gift_vouchers'] as $table) {
            if (! in_array($table, $wpdb->tables, true)) {
                $wpdb->tables[] = $table;
            }
        }
    }

    public function experience_cpt(): ExperienceCPT
    {
        return $this->experience_cpt;
    }
}
