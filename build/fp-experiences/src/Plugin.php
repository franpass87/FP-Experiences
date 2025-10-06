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

        add_action('plugins_loaded', [$this, 'maybe_update_roles'], 5);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'register_database_tables']);
        add_action('admin_init', [$this, 'maybe_update_roles']);

        $this->guard([$this->experience_cpt, 'register_hooks'], ExperienceCPT::class, 'register_hooks');
        $this->guard([$this->shortcodes, 'register'], ShortcodeRegistrar::class, 'register');
        $this->guard([$this->cart, 'register_hooks'], Cart::class, 'register_hooks');
        $this->guard([$this->orders, 'register_hooks'], Orders::class, 'register_hooks');
        $this->guard([$this->checkout, 'register_hooks'], BookingCheckout::class, 'register_hooks');
        $this->guard([$this->emails, 'register_hooks'], Emails::class, 'register_hooks');
        $this->guard([$this->brevo, 'register_hooks'], Brevo::class, 'register_hooks');
        $this->guard([$this->request_to_book, 'register_hooks'], RequestToBook::class, 'register_hooks');
        $this->guard([$this->google_calendar, 'register_hooks'], GoogleCalendar::class, 'register_hooks');
        $this->guard([$this->ga4, 'register_hooks'], GA4::class, 'register_hooks');
        $this->guard([$this->google_ads, 'register_hooks'], GoogleAds::class, 'register_hooks');
        $this->guard([$this->meta_pixel, 'register_hooks'], MetaPixel::class, 'register_hooks');
        $this->guard([$this->clarity, 'register_hooks'], Clarity::class, 'register_hooks');

        if ($this->rest_routes instanceof RestRoutes) {
            $this->guard([$this->rest_routes, 'register_hooks'], RestRoutes::class, 'register_hooks');
        }

        if ($this->migrations instanceof MigrationRunner) {
            $this->guard([$this->migrations, 'register_hooks'], MigrationRunner::class, 'register_hooks');
        }

        if ($this->meeting_points instanceof MeetingPointsManager) {
            $this->guard([$this->meeting_points, 'register_hooks'], MeetingPointsManager::class, 'register_hooks');
        }

        if ($this->webhooks instanceof Webhooks) {
            $this->guard([$this->webhooks, 'register_hooks'], Webhooks::class, 'register_hooks');
        }

        if ($this->single_experience_renderer instanceof SingleExperienceRenderer) {
            $this->guard([$this->single_experience_renderer, 'register_hooks'], SingleExperienceRenderer::class, 'register_hooks');
        }

        if ($this->settings_page instanceof SettingsPage) {
            $this->guard([$this->settings_page, 'register_hooks'], SettingsPage::class, 'register_hooks');
        }

        if ($this->calendar_admin instanceof CalendarAdmin) {
            $this->guard([$this->calendar_admin, 'register_hooks'], CalendarAdmin::class, 'register_hooks');
        }

        if ($this->logs_page instanceof LogsPage) {
            $this->guard([$this->logs_page, 'register_hooks'], LogsPage::class, 'register_hooks');
        }

        if ($this->language_admin instanceof LanguageAdmin) {
            $this->guard([$this->language_admin, 'register_hooks'], LanguageAdmin::class, 'register_hooks');
        }

        if ($this->requests_page instanceof RequestsPage) {
            $this->guard([$this->requests_page, 'register_hooks'], RequestsPage::class, 'register_hooks');
        }

        if ($this->experience_meta_boxes instanceof ExperienceMetaBoxes) {
            $this->guard([$this->experience_meta_boxes, 'register_hooks'], ExperienceMetaBoxes::class, 'register_hooks');
        }

        if ($this->tools_page instanceof ToolsPage) {
            $this->guard([$this->tools_page, 'register_hooks'], ToolsPage::class, 'register_hooks');
        }

        if ($this->emails_page instanceof EmailsPage) {
            $this->guard([$this->emails_page, 'register_hooks'], EmailsPage::class, 'register_hooks');
        }

        if ($this->checkin_page instanceof CheckinPage) {
            $this->guard([$this->checkin_page, 'register_hooks'], CheckinPage::class, 'register_hooks');
        }

        if ($this->importer_page instanceof ImporterPage) {
            $this->guard([$this->importer_page, 'register_hooks'], ImporterPage::class, 'register_hooks');
        }

        $this->guard([ImporterStats::class, 'register_hooks'], ImporterStats::class, 'register_hooks');

        if ($this->page_creator instanceof ExperiencePageCreator) {
            $this->guard([$this->page_creator, 'register_hooks'], ExperiencePageCreator::class, 'register_hooks');
        }

        if ($this->orders_page instanceof OrdersPage) {
            $this->guard([$this->orders_page, 'register_hooks'], OrdersPage::class, 'register_hooks');
        }

        if ($this->admin_menu instanceof AdminMenu) {
            $this->guard([$this->admin_menu, 'register_hooks'], AdminMenu::class, 'register_hooks');
        }

        if ($this->onboarding instanceof Onboarding) {
            $this->guard([$this->onboarding, 'register'], Onboarding::class, 'register');
        }

        if ($this->elementor_widgets instanceof ElementorWidgetsRegistrar) {
            $this->guard([$this->elementor_widgets, 'register'], ElementorWidgetsRegistrar::class, 'register');
        }

        if ($this->gift_cpt instanceof VoucherCPT) {
            $this->guard([$this->gift_cpt, 'register_hooks'], VoucherCPT::class, 'register_hooks');
        }

        if ($this->gift_manager instanceof VoucherManager) {
            $this->guard([$this->gift_manager, 'register_hooks'], VoucherManager::class, 'register_hooks');
        }

        if ($this->auto_translator instanceof AutoTranslator) {
            $this->guard([$this->auto_translator, 'register_hooks'], AutoTranslator::class, 'register_hooks');
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
                if ($administrator->has_cap($capability)) {
                    continue;
                }

                $administrator_missing_caps = true;
                break;
            }
        }

        if ($current_user instanceof WP_User && in_array('administrator', $current_user->roles, true)) {
            foreach (array_keys($manager_capabilities) as $capability) {
                if ($current_user->has_cap($capability)) {
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
                if ($current_user->has_cap($capability)) {
                    continue;
                }

                $current_user->add_cap($capability);
            }
        }
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
