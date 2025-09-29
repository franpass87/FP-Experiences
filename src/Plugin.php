<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Api\RestRoutes;
use FP_Exp\Api\Webhooks;
use FP_Exp\Booking\Cart;
use FP_Exp\Booking\Checkout as BookingCheckout;
use FP_Exp\Admin\CalendarAdmin;
use FP_Exp\Admin\RequestsPage;
use FP_Exp\Admin\SettingsPage;
use FP_Exp\Admin\LogsPage;
use FP_Exp\Booking\Emails;
use FP_Exp\Booking\Orders;
use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\RequestToBook;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\Elementor\WidgetsRegistrar as ElementorWidgetsRegistrar;
use FP_Exp\Integrations\Brevo;
use FP_Exp\Integrations\Clarity;
use FP_Exp\Integrations\GA4;
use FP_Exp\Integrations\GoogleCalendar;
use FP_Exp\Integrations\GoogleAds;
use FP_Exp\Integrations\MetaPixel;
use FP_Exp\PostTypes\ExperienceCPT;
use FP_Exp\Shortcodes\Registrar as ShortcodeRegistrar;

use function add_action;
use function load_plugin_textdomain;
use function plugin_basename;
use function is_admin;

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

    private ?ElementorWidgetsRegistrar $elementor_widgets = null;

    private ?RestRoutes $rest_routes = null;

    private ?Webhooks $webhooks = null;

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
        $this->rest_routes = new RestRoutes();
        $this->webhooks = new Webhooks();

        if (is_admin()) {
            $this->settings_page = new SettingsPage();
            $this->calendar_admin = new CalendarAdmin($this->orders);
            $this->logs_page = new LogsPage();
            $this->requests_page = new RequestsPage($this->request_to_book);
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'register_database_tables']);

        $this->experience_cpt->register_hooks();
        $this->shortcodes->register();
        $this->cart->register_hooks();
        $this->orders->register_hooks();
        $this->checkout->register_hooks();
        $this->emails->register_hooks();
        $this->brevo->register_hooks();
        $this->request_to_book->register_hooks();
        $this->google_calendar->register_hooks();
        $this->ga4->register_hooks();
        $this->google_ads->register_hooks();
        $this->meta_pixel->register_hooks();
        $this->clarity->register_hooks();

        if ($this->rest_routes instanceof RestRoutes) {
            $this->rest_routes->register_hooks();
        }

        if ($this->webhooks instanceof Webhooks) {
            $this->webhooks->register_hooks();
        }

        if ($this->settings_page instanceof SettingsPage) {
            $this->settings_page->register_hooks();
        }

        if ($this->calendar_admin instanceof CalendarAdmin) {
            $this->calendar_admin->register_hooks();
        }

        if ($this->logs_page instanceof LogsPage) {
            $this->logs_page->register_hooks();
        }

        if ($this->requests_page instanceof RequestsPage) {
            $this->requests_page->register_hooks();
        }

        if ($this->elementor_widgets instanceof ElementorWidgetsRegistrar) {
            $this->elementor_widgets->register();
        }
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

        if (! is_array($wpdb->tables)) {
            $wpdb->tables = [];
        }

        foreach (['fp_exp_slots', 'fp_exp_reservations', 'fp_exp_resources'] as $table) {
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
