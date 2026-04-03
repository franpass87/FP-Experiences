<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Core\Hook\HookableInterface;
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
use function sanitize_key;
use function strpos;
use function wp_add_inline_script;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;
use function wp_unslash;

final class AdminMenu implements HookableInterface
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
        
        // Registra gli hook per HelpPage
        if (method_exists($this->help_page, 'register_hooks')) {
            $this->help_page->register_hooks();
        }
    }

    public function register_hooks(): void
    {
        // Priority 5: must run before Onboarding / Meeting Point Importer (default 10), otherwise their
        // first add_submenu_page triggers WP’s auto “parent clone” row titled like the top-level menu
        // (“FP Experiences”) instead of a recognizable Dashboard entry.
        add_action('admin_menu', [$this, 'register_menu'], 5);
        add_action('admin_menu', [$this, 'remove_duplicate_cpt_menus'], 99);
        add_action('admin_bar_menu', [$this, 'register_admin_bar_links'], 80);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_shared_assets']);
        add_action('admin_head', [$this, 'render_submenu_section_enhancements']);
        add_filter('admin_body_class', [$this, 'add_admin_body_class']);
    }

    /**
     * Registra top-level, prima voce “Dashboard” e sottomenu; priorità hook 5 per precedere Onboarding/import.
     */
    public function register_menu(): void
    {
        add_menu_page(
            esc_html__('FP Experiences', 'fp-experiences'),
            esc_html__('FP Experiences', 'fp-experiences'),
            Helpers::guide_capability(),
            'fp_exp_dashboard',
            [$this, 'render_home_page'],
            'dashicons-location',
            '56.3'
        );

        // Explicit first submenu so operators see “Dashboard” (empty callback: add_menu_page already hooked render_home_page).
        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('FP Experiences', 'fp-experiences'),
            esc_html__('Dashboard', 'fp-experiences'),
            Helpers::guide_capability(),
            'fp_exp_dashboard',
            ''
        );

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
            esc_html__('Gift Vouchers', 'fp-experiences'),
            esc_html__('Gift voucher', 'fp-experiences'),
            'fp_exp_manage',
            'edit.php?post_type=fp_exp_gift_voucher'
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Create Gift Voucher', 'fp-experiences'),
            esc_html__('Nuovo voucher', 'fp-experiences'),
            'fp_exp_manage',
            'post-new.php?post_type=fp_exp_gift_voucher'
        );

        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Importer Esperienze', 'fp-experiences'),
            esc_html__('Importer esperienze', 'fp-experiences'),
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
                esc_html__('Crea pagina esperienza', 'fp-experiences'),
                esc_html__('Crea pagina esperienza', 'fp-experiences'),
                Helpers::management_capability(),
                'fp_exp_create_page',
                [$this->page_creator, 'render_page']
            );
        }

        $this->reorder_dashboard_submenus();
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
            || ('' !== $screen_id && 0 === strpos($screen_id, 'fp-exp-dashboard_page_'));
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

    /**
     * Reorder FP Experiences submenu entries for faster operator access.
     */
    private function reorder_dashboard_submenus(): void
    {
        global $submenu;

        if (! isset($submenu['fp_exp_dashboard']) || ! is_array($submenu['fp_exp_dashboard'])) {
            return;
        }

        $items = $submenu['fp_exp_dashboard'];
        $bucketed = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item[2])) {
                continue;
            }
            $slug = (string) $item[2];
            if (! isset($bucketed[$slug])) {
                $bucketed[$slug] = [];
            }
            $bucketed[$slug][] = $item;
        }

        $desired_order = [
            'fp_exp_dashboard',
            'fp_exp_calendar',
            'fp_exp_requests',
            'fp_exp_checkin',
            'fp_exp_orders',
            'edit.php?post_type=fp_experience',
            'post-new.php?post_type=fp_experience',
            'edit.php?post_type=fp_exp_gift_voucher',
            'post-new.php?post_type=fp_exp_gift_voucher',
            'fp_exp_importer',
            'edit.php?post_type=fp_meeting_point',
            'fp_exp_settings',
            'fp_exp_emails',
            'fp_exp_tools',
            'fp_exp_logs',
            'fp_exp_help',
            'fp_exp_create_page',
        ];

        $reordered = [];

        foreach ($desired_order as $slug) {
            if (! isset($bucketed[$slug])) {
                continue;
            }
            foreach ($bucketed[$slug] as $entry) {
                $reordered[] = $entry;
            }
            unset($bucketed[$slug]);
        }

        foreach ($bucketed as $entries) {
            foreach ($entries as $entry) {
                $reordered[] = $entry;
            }
        }

        $submenu['fp_exp_dashboard'] = $reordered;
    }

    /**
     * Render submenu visual section separators (purely cosmetic).
     */
    public function render_submenu_section_enhancements(): void
    {
        if (! Helpers::can_access_guides()) {
            return;
        }

        ?>
        <style>
            #toplevel_page_fp_exp_dashboard .wp-submenu li.fp-exp-submenu-section-start {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid rgba(240, 246, 252, 0.18);
            }

            #toplevel_page_fp_exp_dashboard .wp-submenu li.fp-exp-submenu-section-start::before {
                content: attr(data-section-label);
                display: block;
                margin: 0 10px 6px 10px;
                font-size: 10px;
                line-height: 1.2;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: rgba(240, 246, 252, 0.62);
                font-weight: 600;
                pointer-events: none;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.querySelector('#toplevel_page_fp_exp_dashboard .wp-submenu');
            if (!root) return;

            const markers = [
                { selector: 'a[href*="page=fp_exp_calendar"]', label: 'Operatività' },
                { selector: 'a[href*="post_type=fp_experience"]', label: 'Gestione' },
                { selector: 'a[href*="page=fp_exp_settings"]', label: 'Sistema' },
                { selector: 'a[href*="page=fp_exp_help"]', label: 'Supporto' }
            ];

            markers.forEach(function (marker) {
                const link = root.querySelector(marker.selector);
                if (!link) return;
                const item = link.closest('li');
                if (!item) return;
                item.classList.add('fp-exp-submenu-section-start');
                item.setAttribute('data-section-label', marker.label);
            });
        });
        </script>
        <?php
    }

    public function enqueue_shared_assets(): void
    {
        $screen = get_current_screen();
        $screen_id = $screen ? ($screen->id ?? '') : '';
        
        // Lista di screen ID gestiti
        $managed_screens = [
            'toplevel_page_fp_exp_dashboard',
            'edit-fp_experience',
            'fp_experience',
            'edit-fp_meeting_point',
            'fp_meeting_point',
            'edit-fp_exp_gift_voucher',
            'fp_exp_gift_voucher',
        ];

        // Verifica se lo screen ID corrisponde o inizia con il prefisso
        $is_managed = in_array($screen_id, $managed_screens, true) || 0 === strpos($screen_id, 'fp-exp-dashboard_page_');
        
        // Fallback: verifica anche il parametro page nella query string
        if (! $is_managed && isset($_GET['page'])) {
            $page = sanitize_key((string) wp_unslash($_GET['page']));
            $fp_exp_pages = [
                'fp_exp_dashboard',
                'fp_exp_settings',
                'fp_exp_calendar',
                'fp_exp_requests',
                'fp_exp_checkin',
                'fp_exp_orders',
                'fp_exp_logs',
                'fp_exp_tools',
                'fp_exp_emails',
                'fp_exp_importer',
                'fp_exp_help',
                'fp_exp_create_page',
                'fp-exp-meeting-points-import',
            ];
            $is_managed = in_array($page, $fp_exp_pages, true);
        }
        
        // Fallback: verifica anche il post_type nella query string per i custom post types
        if (! $is_managed && isset($_GET['post_type'])) {
            $post_type = sanitize_key((string) wp_unslash($_GET['post_type']));
            $fp_exp_post_types = [
                'fp_experience',
                'fp_meeting_point',
                'fp_exp_gift_voucher',
            ];
            $is_managed = in_array($post_type, $fp_exp_post_types, true);
        }
        
        if (! $is_managed) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);

        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            Helpers::admin_style_dependencies(),
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);

        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'],
            Helpers::asset_version($admin_js),
            true
        );

        // Config base per fpExpAdmin
        wp_localize_script('fp-exp-admin', 'fpExpAdmin', [
            'restUrl' => rest_url('fp-exp/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => FP_EXP_PLUGIN_URL,
            'strings' => [],
        ]);

        $strings = [
            'ticketWarning' => esc_html__('Aggiungi almeno un tipo di biglietto con un prezzo valido.', 'fp-experiences'),
        ];

        $inline = 'window.fpExpAdmin = window.fpExpAdmin || {};' .
            'window.fpExpAdmin.strings = Object.assign({}, window.fpExpAdmin.strings || {}, ' . wp_json_encode($strings) . ');';

        wp_add_inline_script('fp-exp-admin', $inline, 'before');
    }

    /**
     * Aggiunge `fp-exp-admin-shell` sulle schermate gestite dal plugin (CSS bottoni/tab/token DMS coerenti).
     *
     * Include l’editor singolo `post.php` per i CPT FP (`fp_experience`, ecc.) quando `screen->base` è `post`.
     */
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
            'edit-fp_exp_language',
            'edit-fp_exp_gift_voucher',
            'fp_exp_gift_voucher',
        ];

        $screen_base = $screen->base ?? '';
        $screen_post_type = $screen->post_type ?? '';
        // CPT FP con editor post.php: assicura fp-exp-admin-shell (bottoni/token DMS in meta box).
        $fp_shell_post_types = ['fp_experience', 'fp_meeting_point', 'fp_exp_gift_voucher'];

        $is_managed = in_array($screen_id, $managed_screens, true)
            || 0 === strpos($screen_id, 'fp-exp-dashboard_page_')
            || ('post' === $screen_base && in_array($screen_post_type, $fp_shell_post_types, true));

        if ($is_managed && false === strpos($classes, 'fp-exp-admin-shell')) {
            $classes .= ' fp-exp-admin-shell';
        }

        if ('fp-exp-dashboard_page_fp_exp_emails' === $screen_id) {
            $classes .= ' fp-exp-emails-body';
        }

        return $classes;
    }
}
