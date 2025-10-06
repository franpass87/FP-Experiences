<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;
use WP_Admin_Bar;

use function add_action;
use function add_filter;
use function add_menu_page;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html__;
use function in_array;
use function get_current_screen;
use function remove_menu_page;
use function strpos;
use function wp_add_inline_script;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;

final class AdminMenu
{
    private SettingsPage $settings_page;

    private CalendarAdmin $calendar_admin;

    private LogsPage $logs_page;

    private RequestsPage $requests_page;

    private ToolsPage $tools_page;

    private CheckinPage $checkin_page;

    private OrdersPage $orders_page;

    private HelpPage $help_page;

    private EmailsPage $emails_page;

    private ?ExperiencePageCreator $page_creator;

    private ImporterPage $importer_page;

    public function __construct(
        SettingsPage $settings_page,
        CalendarAdmin $calendar_admin,
        LogsPage $logs_page,
        RequestsPage $requests_page,
        ToolsPage $tools_page,
        EmailsPage $emails_page,
        CheckinPage $checkin_page,
        OrdersPage $orders_page,
        HelpPage $help_page,
        ImporterPage $importer_page,
        ?ExperiencePageCreator $page_creator = null
    ) {
        $this->settings_page = $settings_page;
        $this->calendar_admin = $calendar_admin;
        $this->logs_page = $logs_page;
        $this->requests_page = $requests_page;
        $this->tools_page = $tools_page;
        $this->emails_page = $emails_page;
        $this->checkin_page = $checkin_page;
        $this->orders_page = $orders_page;
        $this->help_page = $help_page;
        $this->importer_page = $importer_page;
        $this->page_creator = $page_creator;
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_menu', [$this, 'remove_duplicate_cpt_menus'], 99);
        add_action('admin_bar_menu', [$this, 'register_admin_bar_links'], 80);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_shared_assets']);
        add_filter('admin_body_class', [$this, 'add_admin_body_class']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            esc_html__('FP Experiences', 'fp-experiences'),
            esc_html__('FP Experiences', 'fp-experiences'),
            Helpers::guide_capability(),
            'fp_exp_dashboard',
            [$this, 'render_home_page'],
            'dashicons-location',
            58
        );

        // The top-level callback already appears as the first submenu item, so we avoid
        // registering an explicit "Dashboard" entry to prevent duplicate menu rows.

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Experiences', 'fp-experiences'),
            esc_html__('Esperienze', 'fp-experiences'),
            'edit_fp_experiences',
            'edit.php?post_type=fp_experience'
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Add New Experience', 'fp-experiences'),
            esc_html__('Nuova esperienza', 'fp-experiences'),
            'edit_fp_experiences',
            'post-new.php?post_type=fp_experience'
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Importer Esperienze', 'fp-experiences'),
            esc_html__('Importer Esperienze', 'fp-experiences'),
            Helpers::management_capability(),
            'fp_exp_importer',
            [$this->importer_page, 'render_page']
        );

        if (Helpers::meeting_points_enabled()) {
            add_submenu_page(
                'fp_exp_dashboard',
                esc_html__('Meeting Points', 'fp-experiences'),
                esc_html__('Meeting point', 'fp-experiences'),
                Helpers::management_capability(),
                'edit.php?post_type=fp_meeting_point'
            );
        }

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Calendar', 'fp-experiences'),
            esc_html__('Calendario', 'fp-experiences'),
            Helpers::operations_capability(),
            'fp_exp_calendar',
            [$this->calendar_admin, 'render_page']
        );

        if (Helpers::rtb_mode() !== 'off') {
            add_submenu_page(
                'fp_exp_dashboard',
                esc_html__('Requests', 'fp-experiences'),
                esc_html__('Richieste', 'fp-experiences'),
                Helpers::operations_capability(),
                'fp_exp_requests',
                [$this->requests_page, 'render_page']
            );
        }

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Check-in', 'fp-experiences'),
            esc_html__('Check-in', 'fp-experiences'),
            Helpers::operations_capability(),
            'fp_exp_checkin',
            [$this->checkin_page, 'render_page']
        );

        if (current_user_can('manage_woocommerce') && Helpers::can_manage_fp()) {
            add_submenu_page(
                'fp_exp_dashboard',
                esc_html__('Orders', 'fp-experiences'),
                esc_html__('Ordini', 'fp-experiences'),
                'manage_woocommerce',
                'fp_exp_orders',
                [$this->orders_page, 'render_page']
            );
        }

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Settings', 'fp-experiences'),
            esc_html__('Impostazioni', 'fp-experiences'),
            Helpers::management_capability(),
            'fp_exp_settings',
            [$this->settings_page, 'render_page']
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Email', 'fp-experiences'),
            esc_html__('Email', 'fp-experiences'),
            Helpers::management_capability(),
            'fp_exp_emails',
            [$this->emails_page, 'render_page']
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Tools', 'fp-experiences'),
            esc_html__('Tools', 'fp-experiences'),
            Helpers::management_capability(),
            'fp_exp_tools',
            [$this->tools_page, 'render_page']
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Logs', 'fp-experiences'),
            esc_html__('Logs', 'fp-experiences'),
            Helpers::management_capability(),
            'fp_exp_logs',
            [$this->logs_page, 'render_page']
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Guide & Shortcodes', 'fp-experiences'),
            esc_html__('Guida & Shortcode', 'fp-experiences'),
            Helpers::guide_capability(),
            'fp_exp_help',
            [$this->help_page, 'render_page']
        );

        if ($this->page_creator instanceof ExperiencePageCreator) {
            add_submenu_page(
                'fp_exp_dashboard',
                esc_html__('Create Experience Page', 'fp-experiences'),
                esc_html__('Crea pagina esperienza', 'fp-experiences'),
                Helpers::management_capability(),
                'fp_exp_create_page',
                [$this->page_creator, 'render_page']
            );
        }
    }

    public function render_home_page(): void
    {
        if (Helpers::can_manage_fp()) {
            Dashboard::render();

            return;
        }

        $this->help_page->render_page();
    }

    public function remove_duplicate_cpt_menus(): void
    {
        remove_menu_page('edit.php?post_type=fp_experience');
        remove_menu_page('edit.php?post_type=fp_meeting_point');
    }

    public function register_admin_bar_links(WP_Admin_Bar $admin_bar): void
    {
        if (! Helpers::can_access_guides()) {
            return;
        }

        $screen = get_current_screen();
        $screen_id = $screen->id ?? '';
        $screen_base = $screen->base ?? '';
        $post_type = $screen->post_type ?? '';

        $is_plugin_screen = 'toplevel_page_fp_exp_dashboard' === $screen_id
            || ('' !== $screen_id && 0 === strpos($screen_id, 'fp-exp-dashboard_page_fp_exp_'));
        $root_meta = $this->admin_bar_meta($is_plugin_screen || 'fp_experience' === $post_type);

        $admin_bar->add_node([
            'id' => 'fp-exp',
            'title' => esc_html__('FP Experiences', 'fp-experiences'),
            'href' => Helpers::can_manage_fp()
                ? admin_url('admin.php?page=fp_exp_dashboard')
                : admin_url('post-new.php?post_type=fp_experience'),
            'meta' => $root_meta,
        ]);

        if (current_user_can('edit_fp_experiences')) {
            $is_new_experience = 'fp_experience' === $post_type && 'post' === $screen_base;
            $admin_bar->add_node([
                'id' => 'fp-exp-new',
                'parent' => 'fp-exp',
                'title' => esc_html__('Nuova esperienza', 'fp-experiences'),
                'href' => admin_url('post-new.php?post_type=fp_experience'),
                'meta' => $this->admin_bar_meta($is_new_experience),
            ]);
        }

        if (Helpers::can_operate_fp()) {
            $admin_bar->add_node([
                'id' => 'fp-exp-calendar',
                'parent' => 'fp-exp',
                'title' => esc_html__('Calendario', 'fp-experiences'),
                'href' => admin_url('admin.php?page=fp_exp_calendar'),
                'meta' => $this->admin_bar_meta('fp-exp-dashboard_page_fp_exp_calendar' === $screen_id),
            ]);

            if (Helpers::rtb_mode() !== 'off') {
                $admin_bar->add_node([
                    'id' => 'fp-exp-requests',
                    'parent' => 'fp-exp',
                    'title' => esc_html__('Richieste', 'fp-experiences'),
                    'href' => admin_url('admin.php?page=fp_exp_requests'),
                    'meta' => $this->admin_bar_meta('fp-exp-dashboard_page_fp_exp_requests' === $screen_id),
                ]);
            }
        }

        if (Helpers::can_manage_fp()) {
            $admin_bar->add_node([
                'id' => 'fp-exp-settings',
                'parent' => 'fp-exp',
                'title' => esc_html__('Impostazioni', 'fp-experiences'),
                'href' => admin_url('admin.php?page=fp_exp_settings'),
                'meta' => $this->admin_bar_meta('fp-exp-dashboard_page_fp_exp_settings' === $screen_id),
            ]);

            $admin_bar->add_node([
                'id' => 'fp-exp-emails',
                'parent' => 'fp-exp',
                'title' => esc_html__('Email', 'fp-experiences'),
                'href' => admin_url('admin.php?page=fp_exp_emails'),
                'meta' => $this->admin_bar_meta('fp-exp-dashboard_page_fp_exp_emails' === $screen_id),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function admin_bar_meta(bool $is_current): array
    {
        return $is_current ? ['aria-current' => 'page'] : [];
    }

    public function enqueue_shared_assets(): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $screen_id = $screen->id ?? '';
        $managed_screens = [
            'toplevel_page_fp_exp_dashboard',
            'edit-fp_experience',
            'fp_experience',
            'edit-fp_meeting_point',
            'fp_meeting_point',
        ];

        $is_managed = in_array($screen_id, $managed_screens, true) || 0 === strpos($screen_id, 'fp-exp-dashboard_page_fp_exp_');
        if (! $is_managed) {
            return;
        }

        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/css/dist/fp-experiences-admin.min.css',
            [],
            Helpers::asset_version('assets/css/dist/fp-experiences-admin.min.css')
        );

        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/js/dist/fp-experiences-admin.min.js',
            ['wp-api-fetch', 'wp-i18n'],
            Helpers::asset_version('assets/js/dist/fp-experiences-admin.min.js'),
            true
        );

        $strings = [
            'ticketWarning' => esc_html__('Aggiungi almeno un tipo di biglietto con un prezzo valido.', 'fp-experiences'),
        ];

        $inline = 'window.fpExpAdmin = window.fpExpAdmin || {};' .
            'window.fpExpAdmin.strings = Object.assign({}, window.fpExpAdmin.strings || {}, ' . wp_json_encode($strings) . ');';

        wp_add_inline_script('fp-exp-admin', $inline, 'before');
    }

    public function add_admin_body_class(string $classes): string
    {
        $screen = get_current_screen();
        if (! $screen) {
            return $classes;
        }

        $screen_id = $screen->id ?? '';
        $managed_screens = [
            'toplevel_page_fp_exp_dashboard',
            'edit-fp_experience',
            'fp_experience',
            'edit-fp_meeting_point',
            'fp_meeting_point',
        ];

        $is_managed = in_array($screen_id, $managed_screens, true) || 0 === strpos($screen_id, 'fp-exp-dashboard_page_fp_exp_');
        if ($is_managed && false === strpos($classes, 'fp-exp-admin-shell')) {
            $classes .= ' fp-exp-admin-shell';
        }

        return $classes;
    }
}
