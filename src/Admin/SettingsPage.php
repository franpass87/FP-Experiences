<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;
use FP_Exp\Utils\Logger;
use FP_Exp\Utils\Theme;

use const MINUTE_IN_SECONDS;

use function absint;
use function add_action;
use function add_query_arg;
use function add_settings_error;
use function add_settings_field;
use function add_settings_section;
use function admin_url;
use function array_filter;
use function array_map;
use function array_merge;
use function checked;
use function current_user_can;
use function delete_transient;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_textarea;
use function esc_url_raw;
use function get_option;
use function get_transient;
use function in_array;
use function is_admin;
use function is_array;
use function is_bool;
use function is_numeric;
use function rest_url;
use function sanitize_email;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function settings_errors;
use function settings_fields;
use function set_transient;
use function submit_button;
use function trim;
use function strtolower;
use function time;
use function update_option;
use function wp_generate_password;
use function get_current_screen;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_strip_all_tags;
use function wp_nonce_url;
use function wp_redirect;
use function wp_remote_get;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_safe_redirect;
use function wp_unslash;
use function wp_create_nonce;
use function wp_verify_nonce;

final class SettingsPage
{
    private string $menu_slug = 'fp_exp_settings';

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_handle_calendar_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_settings(): void
    {
        $this->register_general_settings();
        $this->register_gift_settings();
        $this->register_branding_settings();
        $this->register_listing_settings();
        $this->register_tracking_settings();
        $this->register_rtb_settings();
        $this->register_brevo_settings();
        $this->register_calendar_settings();
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to manage FP Experiences settings.', 'fp-experiences'));
        }

        $tabs = $this->get_tabs();
        $active_tab = $this->get_active_tab($tabs);

        echo '<div class="wrap fp-exp-settings">';
        echo '<h1>' . esc_html__('FP Experiences Settings', 'fp-experiences') . '</h1>';

        settings_errors('fp_exp_settings');

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg([
                'page' => $this->menu_slug,
                'tab' => $slug,
            ], admin_url('admin.php'));
            $classes = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ('calendar' === $active_tab) {
            $this->render_calendar_status();
        }

        if ('tools' === $active_tab) {
            $this->render_tools_panel();
            echo '</div>';

            return;
        }

        if ('booking' === $active_tab) {
            $this->render_booking_rules_panel();
            echo '</div>';

            return;
        }

        if ('logs' === $active_tab) {
            $this->render_logs_overview();
            echo '</div>';

            return;
        }

        echo '<form action="options.php" method="post" class="fp-exp-settings__form">';

        if ('branding' === $active_tab) {
            settings_fields('fp_exp_settings_branding');
            do_settings_sections('fp_exp_settings_branding');
            $this->render_branding_contrast();
        } elseif ('gift' === $active_tab) {
            settings_fields('fp_exp_settings_gift');
            do_settings_sections('fp_exp_settings_gift');
        } elseif ('listing' === $active_tab) {
            settings_fields('fp_exp_settings_listing');
            do_settings_sections('fp_exp_settings_listing');
        } elseif ('tracking' === $active_tab) {
            settings_fields('fp_exp_settings_tracking');
            do_settings_sections('fp_exp_settings_tracking');
        } elseif ('rtb' === $active_tab) {
            settings_fields('fp_exp_settings_rtb');
            do_settings_sections('fp_exp_settings_rtb');
        } elseif ('brevo' === $active_tab) {
            settings_fields('fp_exp_settings_brevo');
            do_settings_sections('fp_exp_settings_brevo');
        } elseif ('calendar' === $active_tab) {
            settings_fields('fp_exp_settings_calendar');
            do_settings_sections('fp_exp_settings_calendar');
        } else {
            settings_fields('fp_exp_settings_general');
            do_settings_sections('fp_exp_settings_general');
        }

        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function enqueue_assets(string $hook = ''): void
    {
        $screen = get_current_screen();
        if (! $screen || 'fp-exp-dashboard_page_' . $this->menu_slug !== $screen->id) {
            return;
        }

        $tabs = $this->get_tabs();
        $active_tab = $this->get_active_tab($tabs);

        if ('tools' === $active_tab) {
            $this->enqueue_tools_assets();
        }
    }

    public function enqueue_tools_assets(): void
    {
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            Helpers::asset_version('assets/css/admin.css')
        );

        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . 'assets/js/admin.js',
            ['wp-api-fetch', 'wp-i18n'],
            Helpers::asset_version('assets/js/admin.js'),
            true
        );

        wp_localize_script('fp-exp-admin', 'fpExpTools', [
            'nonce' => wp_create_nonce('wp_rest'),
            'actions' => $this->get_tool_actions_localised(),
            'i18n' => [
                'running' => esc_html__('Running action…', 'fp-experiences'),
                'success' => esc_html__('Action completed successfully.', 'fp-experiences'),
                'error' => esc_html__('Action failed. Check the logs for details.', 'fp-experiences'),
            ],
        ]);
    }

    private function register_general_settings(): void
    {
        register_setting('fp_exp_settings_general', 'fp_exp_structure_email', [
            'type' => 'string',
            'sanitize_callback' => static fn ($value) => sanitize_email((string) $value),
            'default' => '',
        ]);

        register_setting('fp_exp_settings_general', 'fp_exp_webmaster_email', [
            'type' => 'string',
            'sanitize_callback' => static fn ($value) => sanitize_email((string) $value),
            'default' => '',
        ]);

        register_setting('fp_exp_settings_general', 'fp_exp_enable_meeting_points', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_toggle'],
            'default' => 'yes',
        ]);

        register_setting('fp_exp_settings_general', 'fp_exp_enable_meeting_point_import', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_toggle'],
            'default' => 'no',
        ]);

        register_setting('fp_exp_settings_general', 'fp_exp_experience_layout', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_experience_layout'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_general',
            esc_html__('General', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_general'
        );

        add_settings_field(
            'fp_exp_structure_email',
            esc_html__('Structure email', 'fp-experiences'),
            [$this, 'render_email_field'],
            'fp_exp_settings_general',
            'fp_exp_section_general',
            [
                'option' => 'fp_exp_structure_email',
                'description' => esc_html__('Primary address for booking confirmations and staff alerts.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_webmaster_email',
            esc_html__('Webmaster email', 'fp-experiences'),
            [$this, 'render_email_field'],
            'fp_exp_settings_general',
            'fp_exp_section_general',
            [
                'option' => 'fp_exp_webmaster_email',
                'description' => esc_html__('Secondary address to receive staff notifications.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_enable_meeting_points',
            esc_html__('Meeting points module', 'fp-experiences'),
            [$this, 'render_toggle_field'],
            'fp_exp_settings_general',
            'fp_exp_section_general',
            [
                'option' => 'fp_exp_enable_meeting_points',
                'label' => esc_html__('Enable meeting points management and widgets.', 'fp-experiences'),
                'default' => 'yes',
            ]
        );

        add_settings_field(
            'fp_exp_enable_meeting_point_import',
            esc_html__('Meeting point import', 'fp-experiences'),
            [$this, 'render_toggle_field'],
            'fp_exp_settings_general',
            'fp_exp_section_general',
            [
                'option' => 'fp_exp_enable_meeting_point_import',
                'label' => esc_html__('Enable meeting point import (advanced).', 'fp-experiences'),
                'description' => esc_html__('Keep disabled unless an operator needs to paste CSV data manually. CLI/REST tools remain available.', 'fp-experiences'),
                'default' => 'no',
            ]
        );

        add_settings_section(
            'fp_exp_section_experience_layout',
            esc_html__('Experience Page Layout', 'fp-experiences'),
            [$this, 'render_experience_layout_help'],
            'fp_exp_settings_general'
        );

        add_settings_field(
            'fp_exp_experience_layout_container',
            esc_html__('Container mode', 'fp-experiences'),
            [$this, 'render_experience_layout_field'],
            'fp_exp_settings_general',
            'fp_exp_section_experience_layout',
            [
                'key' => 'container',
                'type' => 'select',
                'options' => [
                    'boxed' => esc_html__('Boxed (respect theme container)', 'fp-experiences'),
                    'full' => esc_html__('Full width (edge to edge)', 'fp-experiences'),
                ],
                'description' => esc_html__('Choose whether the experience layout stays inside the theme container or spans the full viewport width.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_experience_layout_max_width',
            esc_html__('Maximum width', 'fp-experiences'),
            [$this, 'render_experience_layout_field'],
            'fp_exp_settings_general',
            'fp_exp_section_experience_layout',
            [
                'key' => 'max_width',
                'type' => 'number',
                'min' => 0,
                'description' => esc_html__('Desktop max-width in pixels (set 0 to keep the default theme width).', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_experience_layout_gutter',
            esc_html__('Side padding', 'fp-experiences'),
            [$this, 'render_experience_layout_field'],
            'fp_exp_settings_general',
            'fp_exp_section_experience_layout',
            [
                'key' => 'gutter',
                'type' => 'number',
                'min' => 0,
                'description' => esc_html__('Horizontal padding (gutter) in pixels applied on desktop layouts.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_experience_layout_sidebar',
            esc_html__('Sidebar position', 'fp-experiences'),
            [$this, 'render_experience_layout_field'],
            'fp_exp_settings_general',
            'fp_exp_section_experience_layout',
            [
                'key' => 'sidebar',
                'type' => 'select',
                'options' => [
                    'right' => esc_html__('Right column', 'fp-experiences'),
                    'left' => esc_html__('Left column', 'fp-experiences'),
                    'none' => esc_html__('No sidebar (single column)', 'fp-experiences'),
                ],
                'description' => esc_html__('Default position for the booking widget on desktop.', 'fp-experiences'),
            ]
        );
    }

    private function register_gift_settings(): void
    {
        register_setting('fp_exp_settings_gift', 'fp_exp_gift', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_gift_settings'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_settings_gift_main',
            esc_html__('Gift Your Experience', 'fp-experiences'),
            [$this, 'render_gift_settings_intro'],
            'fp_exp_settings_gift'
        );

        add_settings_field(
            'fp_exp_gift_enabled',
            esc_html__('Enable gift vouchers', 'fp-experiences'),
            [$this, 'render_gift_toggle'],
            'fp_exp_settings_gift',
            'fp_exp_settings_gift_main'
        );

        add_settings_field(
            'fp_exp_gift_validity',
            esc_html__('Default validity (days)', 'fp-experiences'),
            [$this, 'render_gift_validity_field'],
            'fp_exp_settings_gift',
            'fp_exp_settings_gift_main'
        );

        add_settings_field(
            'fp_exp_gift_reminders',
            esc_html__('Reminder schedule (days before expiry)', 'fp-experiences'),
            [$this, 'render_gift_reminder_field'],
            'fp_exp_settings_gift',
            'fp_exp_settings_gift_main'
        );

        add_settings_field(
            'fp_exp_gift_reminder_time',
            esc_html__('Reminder send time', 'fp-experiences'),
            [$this, 'render_gift_reminder_time_field'],
            'fp_exp_settings_gift',
            'fp_exp_settings_gift_main'
        );

        add_settings_field(
            'fp_exp_gift_redeem_page',
            esc_html__('Redemption page URL', 'fp-experiences'),
            [$this, 'render_gift_redeem_page_field'],
            'fp_exp_settings_gift',
            'fp_exp_settings_gift_main'
        );
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    public function sanitize_gift_settings($value): array
    {
        $value = is_array($value) ? $value : [];

        $enabled = ! empty($value['enabled']) ? 'yes' : 'no';
        $validity = isset($value['validity_days']) ? absint((string) $value['validity_days']) : Helpers::gift_validity_days();
        if ($validity <= 0) {
            $validity = Helpers::gift_validity_days();
        }

        $reminders = $value['reminders'] ?? Helpers::gift_reminder_offsets();
        if (is_string($reminders)) {
            $reminders = array_map('trim', explode(',', $reminders));
        }

        $reminders = is_array($reminders) ? $reminders : [];
        $normalized_reminders = [];
        foreach ($reminders as $reminder) {
            if (is_numeric($reminder)) {
                $days = absint((string) $reminder);
                if ($days > 0) {
                    $normalized_reminders[] = $days;
                }
            }
        }

        if (empty($normalized_reminders)) {
            $normalized_reminders = Helpers::gift_reminder_offsets();
        }

        sort($normalized_reminders);

        $time = isset($value['reminder_time']) ? sanitize_text_field((string) $value['reminder_time']) : Helpers::gift_reminder_time();
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = Helpers::gift_reminder_time();
        }

        $redeem_page = isset($value['redeem_page']) ? esc_url_raw((string) $value['redeem_page']) : '';

        return [
            'enabled' => $enabled,
            'validity_days' => $validity,
            'reminders' => $normalized_reminders,
            'reminder_time' => $time,
            'redeem_page' => $redeem_page,
        ];
    }

    public function render_gift_settings_intro(): void
    {
        echo '<p>' . esc_html__('Configure the “Gift Your Experience” workflow, default validity, and reminder cadence.', 'fp-experiences') . '</p>';
    }

    public function render_gift_toggle(): void
    {
        $settings = Helpers::gift_settings();
        $enabled = ! empty($settings['enabled']);

        echo '<label for="fp-exp-gift-enabled">';
        echo '<input type="checkbox" id="fp-exp-gift-enabled" name="fp_exp_gift[enabled]" value="1" ' . checked($enabled, true, false) . ' /> ';
        esc_html_e('Allow customers to purchase gift vouchers and send them via email.', 'fp-experiences');
        echo '</label>';
    }

    public function render_gift_validity_field(): void
    {
        $settings = Helpers::gift_settings();
        $validity = (int) ($settings['validity_days'] ?? 365);

        echo '<input type="number" min="1" step="1" class="small-text" id="fp-exp-gift-validity" name="fp_exp_gift[validity_days]" value="' . esc_attr((string) $validity) . '" />';
        echo '<p class="description">' . esc_html__('Number of days the voucher remains valid from purchase.', 'fp-experiences') . '</p>';
    }

    public function render_gift_reminder_field(): void
    {
        $settings = Helpers::gift_settings();
        $reminders = implode(', ', array_map('absint', $settings['reminders'] ?? []));

        echo '<input type="text" id="fp-exp-gift-reminders" name="fp_exp_gift[reminders]" value="' . esc_attr($reminders) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Comma-separated list of reminder offsets in days (e.g. 30,7,1).', 'fp-experiences') . '</p>';
    }

    public function render_gift_reminder_time_field(): void
    {
        $settings = Helpers::gift_settings();
        $time = (string) ($settings['reminder_time'] ?? '09:00');

        echo '<input type="time" id="fp-exp-gift-reminder-time" name="fp_exp_gift[reminder_time]" value="' . esc_attr($time) . '" />';
        echo '<p class="description">' . esc_html__('Local time used when dispatching scheduled reminder emails.', 'fp-experiences') . '</p>';
    }

    public function render_gift_redeem_page_field(): void
    {
        $settings = Helpers::gift_settings();
        $redeem_page = (string) ($settings['redeem_page'] ?? '');

        echo '<input type="url" id="fp-exp-gift-redeem-page" name="fp_exp_gift[redeem_page]" value="' . esc_attr($redeem_page) . '" class="regular-text" placeholder="' . esc_attr(Helpers::gift_redeem_page()) . '" />';
        echo '<p class="description">' . esc_html__('Optional custom URL that hosts the [fp_exp_gift_redeem] shortcode.', 'fp-experiences') . '</p>';
    }

    public function render_experience_layout_help(): void
    {
        echo '<p>' . esc_html__('Set the default container width, gutter, and sidebar placement used by the Experience Page shortcode and widget.', 'fp-experiences') . '</p>';
    }

    public function render_experience_layout_field(array $args): void
    {
        $layout = $this->get_experience_layout_option();
        $key = isset($args['key']) ? (string) $args['key'] : '';

        if ('' === $key) {
            return;
        }

        $type = $args['type'] ?? 'text';
        $id = 'fp-exp-experience-layout-' . $key;
        $name = 'fp_exp_experience_layout[' . $key . ']';
        $value = $layout[$key] ?? '';

        if ('select' === $type) {
            $options = is_array($args['options'] ?? null) ? $args['options'] : [];
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
            foreach ($options as $option_value => $label) {
                $selected = (string) $option_value === (string) $value ? ' selected' : '';
                echo '<option value="' . esc_attr((string) $option_value) . '"' . $selected . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
        } elseif ('number' === $type) {
            $min = isset($args['min']) ? (int) $args['min'] : 0;
            echo '<input type="number" class="small-text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) $min) . '" step="1" />';
        } else {
            echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
        }

        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    /**
     * @return array{container: string, max_width: int, gutter: int, sidebar: string}
     */
    private function get_experience_layout_option(): array
    {
        $defaults = [
            'container' => 'boxed',
            'max_width' => 1200,
            'gutter' => 24,
            'sidebar' => 'right',
        ];

        $option = get_option('fp_exp_experience_layout', []);

        if (! is_array($option)) {
            return $defaults;
        }

        return array_merge($defaults, $option);
    }

    private function register_branding_settings(): void
    {
        register_setting('fp_exp_settings_branding', 'fp_exp_branding', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_branding'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_branding',
            esc_html__('Branding & Theme', 'fp-experiences'),
            [$this, 'render_branding_help'],
            'fp_exp_settings_branding'
        );

        foreach ($this->get_branding_fields() as $field) {
            add_settings_field(
                'fp_exp_branding_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_branding_field'],
                'fp_exp_settings_branding',
                'fp_exp_section_branding',
                $field
            );
        }
    }

    private function register_listing_settings(): void
    {
        register_setting('fp_exp_settings_listing', 'fp_exp_listing', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_listing'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_listing',
            esc_html__('Showcase defaults', 'fp-experiences'),
            [$this, 'render_listing_help'],
            'fp_exp_settings_listing'
        );

        foreach ($this->get_listing_fields() as $field) {
            add_settings_field(
                'fp_exp_listing_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_listing_field'],
                'fp_exp_settings_listing',
                'fp_exp_section_listing',
                $field
            );
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public function render_toggle_field(array $args): void
    {
        $option = $args['option'] ?? '';
        $label = $args['label'] ?? '';

        if (! $option) {
            return;
        }

        $default = isset($args['default']) ? (string) $args['default'] : 'yes';
        $value = get_option($option, $default);
        $checked = in_array($value, ['yes', '1', 'true', 1, true], true);

        echo '<input type="hidden" name="' . esc_attr($option) . '" value="no" />';
        echo '<label class="fp-exp-settings__toggle">';
        echo '<input type="checkbox" name="' . esc_attr($option) . '" value="yes" ' . checked(true, $checked, false) . ' />';
        echo ' <span>' . esc_html($label) . '</span>';
        echo '</label>';

        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    /**
     * @param mixed $value
     */
    public function sanitize_toggle($value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? 'yes' : 'no';
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ('' === $normalized) {
                return 'no';
            }

            return in_array($normalized, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
        }

        return $value ? 'yes' : 'no';
    }

    private function register_tracking_settings(): void
    {
        register_setting('fp_exp_settings_tracking', 'fp_exp_tracking', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_tracking'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_tracking_channels',
            esc_html__('Marketing channels', 'fp-experiences'),
            [$this, 'render_tracking_help'],
            'fp_exp_settings_tracking'
        );

        foreach ($this->get_tracking_channel_fields() as $field) {
            add_settings_field(
                'fp_exp_tracking_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_tracking_field'],
                'fp_exp_settings_tracking',
                'fp_exp_section_tracking_channels',
                $field
            );
        }

        add_settings_section(
            'fp_exp_section_tracking_consent',
            esc_html__('Consent defaults', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_tracking'
        );

        foreach ($this->get_tracking_consent_fields() as $field) {
            add_settings_field(
                'fp_exp_tracking_consent_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_tracking_field'],
                'fp_exp_settings_tracking',
                'fp_exp_section_tracking_consent',
                $field
            );
        }
    }

    private function register_rtb_settings(): void
    {
        register_setting('fp_exp_settings_rtb', 'fp_exp_rtb', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_rtb'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_rtb_flow',
            esc_html__('Workflow', 'fp-experiences'),
            [$this, 'render_rtb_help'],
            'fp_exp_settings_rtb'
        );

        foreach ($this->get_rtb_mode_fields() as $field) {
            add_settings_field(
                'fp_exp_rtb_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_rtb_field'],
                'fp_exp_settings_rtb',
                'fp_exp_section_rtb_flow',
                $field
            );
        }

        add_settings_section(
            'fp_exp_section_rtb_templates',
            esc_html__('Notifications', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_rtb'
        );

        foreach ($this->get_rtb_template_fields() as $field) {
            add_settings_field(
                'fp_exp_rtb_tpl_' . sanitize_key($field['key']),
                esc_html($field['label']),
                [$this, 'render_rtb_field'],
                'fp_exp_settings_rtb',
                'fp_exp_section_rtb_templates',
                $field
            );
        }
    }

    private function register_brevo_settings(): void
    {
        register_setting('fp_exp_settings_brevo', 'fp_exp_brevo', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_brevo'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_brevo_connection',
            esc_html__('Connection', 'fp-experiences'),
            [$this, 'render_brevo_help'],
            'fp_exp_settings_brevo'
        );

        foreach ($this->get_brevo_connection_fields() as $field) {
            add_settings_field(
                'fp_exp_brevo_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_brevo_field'],
                'fp_exp_settings_brevo',
                'fp_exp_section_brevo_connection',
                $field
            );
        }

        add_settings_section(
            'fp_exp_section_brevo_mapping',
            esc_html__('Attribute mapping', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_brevo'
        );

        foreach ($this->get_brevo_mapping_fields() as $field) {
            add_settings_field(
                'fp_exp_brevo_map_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_brevo_field'],
                'fp_exp_settings_brevo',
                'fp_exp_section_brevo_mapping',
                $field
            );
        }

        add_settings_section(
            'fp_exp_section_brevo_templates',
            esc_html__('Transactional templates', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_brevo'
        );

        foreach ($this->get_brevo_template_fields() as $field) {
            add_settings_field(
                'fp_exp_brevo_tpl_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_brevo_field'],
                'fp_exp_settings_brevo',
                'fp_exp_section_brevo_templates',
                $field
            );
        }
    }

    private function register_calendar_settings(): void
    {
        register_setting('fp_exp_settings_calendar', 'fp_exp_google_calendar', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_calendar'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_calendar_credentials',
            esc_html__('OAuth credentials', 'fp-experiences'),
            [$this, 'render_calendar_help'],
            'fp_exp_settings_calendar'
        );

        foreach ($this->get_calendar_credential_fields() as $field) {
            add_settings_field(
                'fp_exp_calendar_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_calendar_field'],
                'fp_exp_settings_calendar',
                'fp_exp_section_calendar_credentials',
                $field
            );
        }

        add_settings_section(
            'fp_exp_section_calendar_selection',
            esc_html__('Calendar selection', 'fp-experiences'),
            '__return_false',
            'fp_exp_settings_calendar'
        );

        foreach ($this->get_calendar_selection_fields() as $field) {
            add_settings_field(
                'fp_exp_calendar_sel_' . $field['key'],
                esc_html($field['label']),
                [$this, 'render_calendar_field'],
                'fp_exp_settings_calendar',
                'fp_exp_section_calendar_selection',
                $field
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function get_tabs(): array
    {
        return [
            'general' => esc_html__('General', 'fp-experiences'),
            'gift' => esc_html__('Gift', 'fp-experiences'),
            'branding' => esc_html__('Branding', 'fp-experiences'),
            'booking' => esc_html__('Booking Rules', 'fp-experiences'),
            'brevo' => esc_html__('Brevo', 'fp-experiences'),
            'calendar' => esc_html__('Calendar', 'fp-experiences'),
            'tracking' => esc_html__('Tracking', 'fp-experiences'),
            'rtb' => esc_html__('RTB', 'fp-experiences'),
            'listing' => esc_html__('Vetrina', 'fp-experiences'),
            'tools' => esc_html__('Tools', 'fp-experiences'),
            'logs' => esc_html__('Logs', 'fp-experiences'),
        ];
    }

    public function render_tools_panel(): void
    {
        echo '<div class="fp-exp-tools" data-fp-exp-tools="1">';
        echo '<p>' . esc_html__('Run diagnostic and recovery actions. These operations execute immediately and their results are logged for auditing.', 'fp-experiences') . '</p>';
        echo '<div class="fp-exp-tools__grid">';
        foreach ($this->get_tool_actions() as $action) {
            echo '<div class="fp-exp-tools__card">';
            echo '<h3>' . esc_html($action['label']) . '</h3>';
            echo '<p>' . esc_html($action['description']) . '</p>';
            echo '<button type="button" class="button button-primary" data-action="' . esc_attr($action['slug']) . '">' . esc_html($action['button']) . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="fp-exp-tools__output" aria-live="polite"></div>';
        echo '</div>';
    }

    private function render_booking_rules_panel(): void
    {
        $meeting_points_enabled = Helpers::meeting_points_enabled();
        $meeting_point_import_enabled = Helpers::meeting_points_import_enabled();
        $rtb_mode = Helpers::rtb_mode();
        $rtb_timeout = Helpers::rtb_hold_timeout();

        $rtb_label = match ($rtb_mode) {
            'confirm' => esc_html__('Richiede conferma manuale', 'fp-experiences'),
            'pay_later' => esc_html__('Pagamento differito', 'fp-experiences'),
            default => esc_html__('Disattivato', 'fp-experiences'),
        };

        echo '<div class="fp-exp-settings__panel fp-exp-settings__panel--booking">';
        echo '<h2>' . esc_html__('Regole attive', 'fp-experiences') . '</h2>';
        echo '<ul class="fp-exp-settings__list">';
        echo '<li>' . esc_html__('Meeting point: ', 'fp-experiences') . ($meeting_points_enabled ? esc_html__('abilitati', 'fp-experiences') : esc_html__('disabilitati', 'fp-experiences')) . '</li>';
        echo '<li>' . esc_html__('Import meeting point: ', 'fp-experiences') . ($meeting_point_import_enabled ? esc_html__('abilitato (avanzato)', 'fp-experiences') : esc_html__('disabilitato', 'fp-experiences')) . '</li>';
        echo '<li>' . esc_html__('Modalità richiesta di prenotazione: ', 'fp-experiences') . $rtb_label . '</li>';
        echo '<li>' . sprintf(
            /* translators: %s: minutes. */
            esc_html__('Tempo massimo di risposta: %s minuti', 'fp-experiences'),
            number_format_i18n($rtb_timeout)
        ) . '</li>';
        echo '</ul>';

        $general_link = esc_url(add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'general',
        ], admin_url('admin.php')));
        $rtb_link = esc_url(add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'rtb',
        ], admin_url('admin.php')));
        $calendar_link = esc_url(add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'calendar',
        ], admin_url('admin.php')));

        echo '<p>' . esc_html__('Aggiorna le regole intervenendo sulle seguenti schede:', 'fp-experiences') . '</p>';
        echo '<ul class="fp-exp-settings__links">';
        echo '<li><a href="' . $general_link . '">' . esc_html__('Generale', 'fp-experiences') . '</a></li>';
        echo '<li><a href="' . $rtb_link . '">' . esc_html__('RTB', 'fp-experiences') . '</a></li>';
        echo '<li><a href="' . $calendar_link . '">' . esc_html__('Calendar', 'fp-experiences') . '</a></li>';
        echo '</ul>';
        echo '</div>';
    }

    private function render_logs_overview(): void
    {
        $logs = Logger::query([
            'limit' => 10,
        ]);

        echo '<div class="fp-exp-settings__panel fp-exp-settings__panel--logs">';
        echo '<h2>' . esc_html__('Ultime attività', 'fp-experiences') . '</h2>';

        if (! $logs) {
            echo '<p>' . esc_html__('Nessun evento registrato nei log.', 'fp-experiences') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Data', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Canale', 'fp-experiences') . '</th>';
            echo '<th>' . esc_html__('Messaggio', 'fp-experiences') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($logs as $entry) {
                $timestamp = esc_html((string) ($entry['timestamp'] ?? ''));
                $channel = esc_html((string) ($entry['channel'] ?? ''));
                $message = esc_html(wp_strip_all_tags((string) ($entry['message'] ?? '')));
                echo '<tr>';
                echo '<td>' . $timestamp . '</td>';
                echo '<td>' . $channel . '</td>';
                echo '<td>' . $message . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        $logs_url = esc_url(admin_url('admin.php?page=fp_exp_logs'));
        echo '<p><a class="button" href="' . $logs_url . '">' . esc_html__('Apri registro completo', 'fp-experiences') . '</a></p>';
        echo '</div>';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function get_tool_actions(): array
    {
        return [
            [
                'slug' => 'resync-brevo',
                'label' => esc_html__('Resynchronise Brevo', 'fp-experiences'),
                'description' => esc_html__('Push pending reservations, marketing attributes, and tags to Brevo.', 'fp-experiences'),
                'button' => esc_html__('Run Brevo sync', 'fp-experiences'),
            ],
            [
                'slug' => 'replay-events',
                'label' => esc_html__('Replay lifecycle events', 'fp-experiences'),
                'description' => esc_html__('Requeue reservation events for integrations that may have missed them.', 'fp-experiences'),
                'button' => esc_html__('Replay events', 'fp-experiences'),
            ],
            [
                'slug' => 'resync-pages',
                'label' => esc_html__('Resynchronise experience pages', 'fp-experiences'),
                'description' => esc_html__('Create or relink WordPress pages for experiences missing the `[fp_exp_page]` shortcode.', 'fp-experiences'),
                'button' => esc_html__('Run page resync', 'fp-experiences'),
            ],
            [
                'slug' => 'ping',
                'label' => esc_html__('Ping site REST API', 'fp-experiences'),
                'description' => esc_html__('Verify public availability of the WordPress REST API endpoint.', 'fp-experiences'),
                'button' => esc_html__('Run ping test', 'fp-experiences'),
            ],
            [
                'slug' => 'clear-cache',
                'label' => esc_html__('Clear caches & logs', 'fp-experiences'),
                'description' => esc_html__('Purge plugin transients and truncate the internal log buffer.', 'fp-experiences'),
                'button' => esc_html__('Clear caches', 'fp-experiences'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function get_tool_actions_localised(): array
    {
        $actions = [];
        foreach ($this->get_tool_actions() as $action) {
            $actions[] = [
                'slug' => $action['slug'],
                'endpoint' => rest_url('fp-exp/v1/tools/' . $action['slug']),
            ];
        }

        return $actions;
    }

    /**
     * @param array<string, string> $tabs
     */
    private function get_active_tab(array $tabs): string
    {
        $requested = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'general';

        if (! isset($tabs[$requested])) {
            return 'general';
        }

        return $requested;
    }

    public function render_email_field(array $args): void
    {
        $option = $args['option'];
        $value = get_option($option, '');
        echo '<input type="email" class="regular-text" name="' . esc_attr($option) . '" value="' . esc_attr((string) $value) . '" />';
        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_branding_help(): void
    {
        echo '<p>' . esc_html__('Tune the booking UI to match your visual identity. Choose a preset, then refine individual colors, borders, and fonts.', 'fp-experiences') . '</p>';
        echo '<p>' . esc_html__('Dark mode can be applied automatically or when wrapping the shortcode output with the .fp-theme-dark class.', 'fp-experiences') . '</p>';
    }

    public function render_listing_help(): void
    {
        echo '<p>' . esc_html__('Set the default filters, ordering, and card badges used by the experiences listing shortcode and Elementor widget.', 'fp-experiences') . '</p>';
    }

    public function render_tracking_help(): void
    {
        echo '<p>' . esc_html__('Provide tracking IDs for each enabled channel. Scripts only load when consent is granted and the channel toggle is enabled.', 'fp-experiences') . '</p>';
    }

    public function render_rtb_help(): void
    {
        echo '<p>' . esc_html__('Request-to-book holds slots for a short time while staff review the inquiry. Configure the global workflow here and enable it per experience.', 'fp-experiences') . '</p>';
    }

    public function render_brevo_help(): void
    {
        echo '<p>' . esc_html__('Connect your Brevo account to deliver transactional emails and sync contacts with marketing attributes and tags.', 'fp-experiences') . '</p>';

        $settings = get_option('fp_exp_brevo', []);
        $settings = is_array($settings) ? $settings : [];

        $enabled = ! empty($settings['enabled']);
        $api_key = isset($settings['api_key']) ? (string) $settings['api_key'] : '';
        $connected = $enabled && '' !== $api_key;

        if (! $enabled) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Brevo is currently disabled. WooCommerce templates will be used for customer emails.', 'fp-experiences') . '</p></div>';
        } elseif ($connected) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Brevo connection active. Transactional emails will use the configured templates when available.', 'fp-experiences') . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Brevo is enabled but the API key is missing. Add the API key to send transactional emails via Brevo.', 'fp-experiences') . '</p></div>';
        }

        $templates = isset($settings['templates']) && is_array($settings['templates']) ? $settings['templates'] : [];
        $configured_templates = array_filter($templates, static fn ($value) => absint($value) > 0);

        if ($configured_templates) {
            echo '<div class="notice notice-info inline"><p>' . sprintf(
                /* translators: %d: number of configured templates. */
                esc_html__('%d Brevo templates configured for confirmations and RTB stages.', 'fp-experiences'),
                count($configured_templates)
            ) . '</p></div>';
        } else {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Transactional templates are optional; the plugin falls back to WooCommerce emails when none are provided.', 'fp-experiences') . '</p></div>';
        }

        $notices = get_transient('fp_exp_brevo_notices');

        if (is_array($notices) && $notices) {
            $classes = [
                'error' => 'notice-error',
                'warning' => 'notice-warning',
                'success' => 'notice-success',
                'info' => 'notice-info',
            ];

            foreach ($notices as $notice) {
                if (empty($notice['message'])) {
                    continue;
                }

                $type = isset($notice['type']) ? (string) $notice['type'] : 'info';
                $class = $classes[$type] ?? 'notice-info';

                echo '<div class="notice ' . esc_attr($class . ' inline') . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            }
        }
    }

    public function render_calendar_help(): void
    {
        echo '<p>' . esc_html__('Create an OAuth client in Google Cloud and add the redirect URI below. After saving credentials, connect the account to sync reservations.', 'fp-experiences') . '</p>';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_branding_fields(): array
    {
        $presets = Theme::presets();
        $preset_options = ['' => esc_html__('Custom', 'fp-experiences')];
        foreach ($presets as $key => $data) {
            $preset_options[$key] = esc_html($data['label']);
        }

        return [
            [
                'key' => 'preset',
                'type' => 'select',
                'label' => esc_html__('Preset', 'fp-experiences'),
                'options' => $preset_options,
                'description' => esc_html__('Start from a curated palette, then adjust specific values as needed.', 'fp-experiences'),
            ],
            [
                'key' => 'mode',
                'type' => 'select',
                'label' => esc_html__('Color mode', 'fp-experiences'),
                'options' => [
                    'light' => esc_html__('Light', 'fp-experiences'),
                    'dark' => esc_html__('Dark (requires .fp-theme-dark wrapper)', 'fp-experiences'),
                    'auto' => esc_html__('Automatic (prefers-color-scheme + .fp-theme-dark)', 'fp-experiences'),
                ],
            ],
            ['key' => 'primary', 'label' => esc_html__('Primary color', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'secondary', 'label' => esc_html__('Secondary color', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'accent', 'label' => esc_html__('Accent color', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'background', 'label' => esc_html__('Background', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'surface', 'label' => esc_html__('Surface', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'text', 'label' => esc_html__('Text color', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'muted', 'label' => esc_html__('Muted text', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'success', 'label' => esc_html__('Success', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'warning', 'label' => esc_html__('Warning', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'danger', 'label' => esc_html__('Danger', 'fp-experiences'), 'type' => 'color'],
            ['key' => 'radius', 'label' => esc_html__('Border radius', 'fp-experiences'), 'type' => 'text', 'placeholder' => '12px'],
            ['key' => 'shadow', 'label' => esc_html__('Shadow', 'fp-experiences'), 'type' => 'text', 'placeholder' => '0 10px 30px rgba(0,0,0,0.08)'],
            ['key' => 'font', 'label' => esc_html__('Preferred font family', 'fp-experiences'), 'type' => 'text', 'placeholder' => '"Red Hat Display", sans-serif'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_listing_fields(): array
    {
        return [
            [
                'key' => 'per_page',
                'type' => 'number',
                'label' => esc_html__('Experiences per page', 'fp-experiences'),
                'min' => 3,
                'description' => esc_html__('Default number of cards displayed before pagination.', 'fp-experiences'),
            ],
            [
                'key' => 'filters',
                'type' => 'multicheck',
                'label' => esc_html__('Enabled filters', 'fp-experiences'),
                'options' => $this->get_listing_filter_options(),
                'description' => esc_html__('Choose which filters appear in the showcase search form.', 'fp-experiences'),
            ],
            [
                'key' => 'orderby',
                'type' => 'select',
                'label' => esc_html__('Default sort order', 'fp-experiences'),
                'options' => [
                    'menu_order' => esc_html__('Manual order', 'fp-experiences'),
                    'date' => esc_html__('Publish date', 'fp-experiences'),
                    'title' => esc_html__('Title', 'fp-experiences'),
                    'price' => esc_html__('Price (lowest first)', 'fp-experiences'),
                ],
            ],
            [
                'key' => 'order',
                'type' => 'select',
                'label' => esc_html__('Default direction', 'fp-experiences'),
                'options' => [
                    'ASC' => esc_html__('Ascending', 'fp-experiences'),
                    'DESC' => esc_html__('Descending', 'fp-experiences'),
                ],
            ],
            [
                'key' => 'show_price_from',
                'type' => 'toggle',
                'label' => esc_html__('Display “price from” badge', 'fp-experiences'),
                'description' => esc_html__('Show the lowest available ticket price on each card by default.', 'fp-experiences'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function get_listing_filter_options(): array
    {
        return [
            'search' => esc_html__('Search', 'fp-experiences'),
            'theme' => esc_html__('Theme', 'fp-experiences'),
            'language' => esc_html__('Language', 'fp-experiences'),
            'duration' => esc_html__('Duration', 'fp-experiences'),
            'price' => esc_html__('Price range', 'fp-experiences'),
            'family' => esc_html__('Family-friendly', 'fp-experiences'),
            'date' => esc_html__('Date picker', 'fp-experiences'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_tracking_channel_fields(): array
    {
        return [
            [
                'key' => 'ga4[enabled]',
                'label' => esc_html__('Enable Google Analytics 4', 'fp-experiences'),
                'type' => 'checkbox',
                'description' => esc_html__('Loads Google Tag Manager or the GA4 gtag snippet when consent allows.', 'fp-experiences'),
            ],
            [
                'key' => 'ga4[gtm_id]',
                'label' => esc_html__('Google Tag Manager ID', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'GTM-XXXXXXX',
            ],
            [
                'key' => 'ga4[measurement_id]',
                'label' => esc_html__('GA4 Measurement ID', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'G-XXXXXXX',
            ],
            [
                'key' => 'google_ads[enabled]',
                'label' => esc_html__('Enable Google Ads conversions', 'fp-experiences'),
                'type' => 'checkbox',
            ],
            [
                'key' => 'google_ads[conversion_id]',
                'label' => esc_html__('Conversion ID', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'AW-XXXXXXX',
            ],
            [
                'key' => 'google_ads[conversion_label]',
                'label' => esc_html__('Conversion label', 'fp-experiences'),
                'type' => 'text',
            ],
            [
                'key' => 'google_ads[enhanced_conversions]',
                'label' => esc_html__('Enable enhanced conversions hashing', 'fp-experiences'),
                'type' => 'checkbox',
            ],
            [
                'key' => 'meta_pixel[enabled]',
                'label' => esc_html__('Enable Meta Pixel', 'fp-experiences'),
                'type' => 'checkbox',
            ],
            [
                'key' => 'meta_pixel[pixel_id]',
                'label' => esc_html__('Pixel ID', 'fp-experiences'),
                'type' => 'text',
            ],
            [
                'key' => 'meta_pixel[capi_token]',
                'label' => esc_html__('CAPI token (optional)', 'fp-experiences'),
                'type' => 'text',
            ],
            [
                'key' => 'clarity[enabled]',
                'label' => esc_html__('Enable Microsoft Clarity', 'fp-experiences'),
                'type' => 'checkbox',
            ],
            [
                'key' => 'clarity[project_id]',
                'label' => esc_html__('Clarity project ID', 'fp-experiences'),
                'type' => 'text',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_tracking_consent_fields(): array
    {
        $channels = [
            'ga4' => esc_html__('Google Analytics 4', 'fp-experiences'),
            'google_ads' => esc_html__('Google Ads', 'fp-experiences'),
            'meta_pixel' => esc_html__('Meta Pixel', 'fp-experiences'),
            'clarity' => esc_html__('Microsoft Clarity', 'fp-experiences'),
        ];

        $fields = [];
        foreach ($channels as $key => $label) {
            $fields[] = [
                'key' => 'consent_defaults[' . $key . ']',
                'label' => sprintf(
                    /* translators: %s: channel name. */
                    esc_html__('%s consent default', 'fp-experiences'),
                    $label
                ),
                'type' => 'checkbox',
                'description' => esc_html__('Used when no CMP override is provided.', 'fp-experiences'),
            ];
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_rtb_mode_fields(): array
    {
        return [
            [
                'key' => 'mode',
                'label' => esc_html__('Mode', 'fp-experiences'),
                'type' => 'select',
                'options' => [
                    'off' => esc_html__('Disabled', 'fp-experiences'),
                    'confirm' => esc_html__('Confirm without payment', 'fp-experiences'),
                    'pay_later' => esc_html__('Confirm and request payment later', 'fp-experiences'),
                ],
                'description' => esc_html__('Choose how request-to-book approvals are processed globally.', 'fp-experiences'),
            ],
            [
                'key' => 'timeout',
                'label' => esc_html__('Hold timeout (minutes)', 'fp-experiences'),
                'type' => 'number',
                'description' => esc_html__('How long to reserve capacity for a pending request before it is released automatically.', 'fp-experiences'),
            ],
            [
                'key' => 'block_capacity',
                'label' => esc_html__('Block capacity during hold', 'fp-experiences'),
                'type' => 'checkbox',
                'description' => esc_html__('When enabled, pending requests reduce availability until their hold expires.', 'fp-experiences'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_rtb_template_fields(): array
    {
        $events = [
            'request' => esc_html__('Request received (customer)', 'fp-experiences'),
            'approved' => esc_html__('Request approved', 'fp-experiences'),
            'declined' => esc_html__('Request declined', 'fp-experiences'),
            'payment' => esc_html__('Payment required', 'fp-experiences'),
        ];

        $fields = [];

        foreach ($events as $slug => $label) {
            $fields[] = [
                'key' => 'templates[' . $slug . ']',
                'label' => sprintf(/* translators: %s: notification label. */ esc_html__('Brevo template ID — %s', 'fp-experiences'), $label),
                'type' => 'text',
                'placeholder' => '123',
            ];
            $fields[] = [
                'key' => 'fallback[' . $slug . '][subject]',
                'label' => sprintf(/* translators: %s: notification label. */ esc_html__('Fallback subject — %s', 'fp-experiences'), $label),
                'type' => 'text',
            ];
            $fields[] = [
                'key' => 'fallback[' . $slug . '][body]',
                'label' => sprintf(/* translators: %s: notification label. */ esc_html__('Fallback body — %s', 'fp-experiences'), $label),
                'type' => 'textarea',
                'description' => esc_html__('Available placeholders: {customer_name}, {experience}, {date}, {time}, {guests}, {payment_url}, {total}, {notes}.', 'fp-experiences'),
            ];
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_brevo_connection_fields(): array
    {
        return [
            [
                'key' => 'enabled',
                'label' => esc_html__('Enable Brevo', 'fp-experiences'),
                'type' => 'checkbox',
            ],
            [
                'key' => 'api_key',
                'label' => esc_html__('API key', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => esc_html__('xkeysib-...', 'fp-experiences'),
            ],
            [
                'key' => 'webhook_secret',
                'label' => esc_html__('Webhook secret', 'fp-experiences'),
                'type' => 'text',
                'description' => esc_html__('Used to validate Brevo webhook signatures.', 'fp-experiences'),
            ],
            [
                'key' => 'list_id',
                'label' => esc_html__('Default list ID', 'fp-experiences'),
                'type' => 'number',
                'description' => esc_html__('Optional: contacts will be subscribed to this list on sync.', 'fp-experiences'),
            ],
            [
                'key' => 'subscribe_to_list',
                'label' => esc_html__('Subscribe contact to list on sync', 'fp-experiences'),
                'type' => 'checkbox',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_brevo_mapping_fields(): array
    {
        return [
            [
                'key' => 'attribute_mapping[first_name]',
                'label' => esc_html__('First name attribute', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'FIRSTNAME',
            ],
            [
                'key' => 'attribute_mapping[last_name]',
                'label' => esc_html__('Last name attribute', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'LASTNAME',
            ],
            [
                'key' => 'attribute_mapping[phone]',
                'label' => esc_html__('Phone attribute', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'SMS',
            ],
            [
                'key' => 'attribute_mapping[language]',
                'label' => esc_html__('Language attribute', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'LANG',
            ],
            [
                'key' => 'attribute_mapping[marketing_consent]',
                'label' => esc_html__('Marketing consent attribute', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => 'CONSENT',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_brevo_template_fields(): array
    {
        return [
            [
                'key' => 'templates[confirmation]',
                'label' => esc_html__('Confirmation template ID', 'fp-experiences'),
                'type' => 'number',
            ],
            [
                'key' => 'templates[reminder]',
                'label' => esc_html__('Reminder template ID', 'fp-experiences'),
                'type' => 'number',
            ],
            [
                'key' => 'templates[post_experience]',
                'label' => esc_html__('Post-experience template ID', 'fp-experiences'),
                'type' => 'number',
            ],
            [
                'key' => 'templates[cancel]',
                'label' => esc_html__('Cancellation template ID', 'fp-experiences'),
                'type' => 'number',
            ],
            [
                'key' => 'templates[abandoned]',
                'label' => esc_html__('Abandoned checkout template ID', 'fp-experiences'),
                'type' => 'number',
                'description' => esc_html__('Optional automation triggered via custom code or cron.', 'fp-experiences'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_calendar_credential_fields(): array
    {
        $default_redirect = add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'calendar',
            'fp_exp_calendar_action' => 'oauth',
        ], admin_url('admin.php'));

        return [
            [
                'key' => 'client_id',
                'label' => esc_html__('Client ID', 'fp-experiences'),
                'type' => 'text',
            ],
            [
                'key' => 'client_secret',
                'label' => esc_html__('Client secret', 'fp-experiences'),
                'type' => 'text',
            ],
            [
                'key' => 'redirect_uri',
                'label' => esc_html__('Authorised redirect URI', 'fp-experiences'),
                'type' => 'text',
                'placeholder' => $default_redirect,
                'description' => esc_html__('Copy this value into the Google Cloud console OAuth configuration.', 'fp-experiences'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get_calendar_selection_fields(): array
    {
        return [
            [
                'key' => 'calendar_id',
                'label' => esc_html__('Calendar', 'fp-experiences'),
                'type' => 'calendar_select',
                'description' => esc_html__('Select the target calendar for new reservations. Save credentials and connect to load calendars.', 'fp-experiences'),
            ],
        ];
    }

    public function render_branding_field(array $field): void
    {
        $branding = get_option('fp_exp_branding', []);
        $branding = is_array($branding) ? $branding : [];
        $key = $field['key'];
        $value = $branding[$key] ?? '';

        if ('select' === $field['type']) {
            echo '<select name="fp_exp_branding[' . esc_attr($key) . ']">';
            foreach ($field['options'] as $option_key => $label) {
                $selected = ((string) $value === (string) $option_key) ? 'selected' : '';
                echo '<option value="' . esc_attr((string) $option_key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } else {
            $input_type = 'color' === $field['type'] ? 'color' : ('number' === ($field['type'] ?? '') ? 'number' : 'text');
            $placeholder = $field['placeholder'] ?? '';
            echo '<input type="' . esc_attr($input_type) . '" class="regular-text" name="fp_exp_branding[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '" />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_listing_field(array $field): void
    {
        $settings = get_option('fp_exp_listing', []);
        $settings = is_array($settings) ? $settings : [];
        $defaults = Helpers::listing_settings();

        $key = $field['key'];
        $value = $settings[$key] ?? ($defaults[$key] ?? '');

        if ('number' === $field['type']) {
            $min = isset($field['min']) ? absint((int) $field['min']) : 1;
            echo '<input type="number" class="small-text" name="fp_exp_listing[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) $min) . '" />';
        } elseif ('select' === $field['type']) {
            echo '<select name="fp_exp_listing[' . esc_attr($key) . ']">';
            foreach ($field['options'] as $option_value => $label) {
                $selected = ((string) $value === (string) $option_value) ? 'selected' : '';
                echo '<option value="' . esc_attr((string) $option_value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } elseif ('multicheck' === $field['type']) {
            $active = is_array($value) ? $value : [];
            echo '<div class="fp-exp-settings__checkbox-group">';
            foreach ($field['options'] as $option_value => $label) {
                $checked = in_array($option_value, $active, true);
                echo '<label class="fp-exp-settings__checkbox">';
                echo '<input type="checkbox" name="fp_exp_listing[' . esc_attr($key) . '][]" value="' . esc_attr((string) $option_value) . '" ' . checked($checked, true, false) . ' /> ';
                echo '<span>' . esc_html($label) . '</span>';
                echo '</label>';
            }
            echo '</div>';
        } elseif ('toggle' === $field['type']) {
            $checked = ! empty($value);
            echo '<label class="fp-exp-settings__toggle">';
            echo '<input type="checkbox" name="fp_exp_listing[' . esc_attr($key) . ']" value="1" ' . checked($checked, true, false) . ' />';
            echo ' <span>' . esc_html__('Enabled', 'fp-experiences') . '</span>';
            echo '</label>';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_rtb_field(array $field): void
    {
        $settings = get_option('fp_exp_rtb', []);
        $settings = is_array($settings) ? $settings : [];
        $value = $this->extract_nested_value($settings, $field['key']);
        $name = $this->build_input_name('fp_exp_rtb', $field['key']);

        $type = $field['type'] ?? 'text';

        if ('checkbox' === $type) {
            $checked = ! empty($value);
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '/> ' . esc_html__('Enabled', 'fp-experiences') . '</label>';
        } elseif ('select' === $type) {
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($field['options'] as $option_value => $label) {
                $selected = ((string) $value === (string) $option_value) ? 'selected' : '';
                echo '<option value="' . esc_attr((string) $option_value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } elseif ('number' === $type) {
            echo '<input type="number" class="small-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" min="0" />';
        } elseif ('textarea' === $type) {
            echo '<textarea name="' . esc_attr($name) . '" rows="4" class="large-text">' . esc_textarea((string) $value) . '</textarea>';
        } else {
            $placeholder = $field['placeholder'] ?? '';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '" />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_tracking_field(array $field): void
    {
        $settings = get_option('fp_exp_tracking', []);
        $settings = is_array($settings) ? $settings : [];
        $value = $this->extract_nested_value($settings, $field['key']);
        $name = $this->build_input_name('fp_exp_tracking', $field['key']);

        if ('checkbox' === $field['type']) {
            $checked = ! empty($value);
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html__('Enabled', 'fp-experiences') . '</label>';
        } elseif ('number' === $field['type']) {
            echo '<input type="number" class="small-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
        } else {
            $placeholder = $field['placeholder'] ?? '';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '" />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_brevo_field(array $field): void
    {
        $settings = get_option('fp_exp_brevo', []);
        $settings = is_array($settings) ? $settings : [];
        $value = $this->extract_nested_value($settings, $field['key']);
        $name = $this->build_input_name('fp_exp_brevo', $field['key']);

        if ('checkbox' === $field['type']) {
            $checked = ! empty($value);
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . ' /> ' . esc_html__('Enabled', 'fp-experiences') . '</label>';
        } elseif ('number' === $field['type']) {
            echo '<input type="number" class="small-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" />';
        } else {
            $placeholder = $field['placeholder'] ?? '';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '" />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_calendar_field(array $field): void
    {
        $settings = $this->get_calendar_settings();
        $value = $this->extract_nested_value($settings, $field['key']);
        $name = $this->build_input_name('fp_exp_google_calendar', $field['key']);

        if ('calendar_select' === $field['type']) {
            $choices = $this->get_calendar_choices();
            if ($choices) {
                echo '<select name="' . esc_attr($name) . '">';
                echo '<option value="">' . esc_html__('Select a calendar…', 'fp-experiences') . '</option>';
                foreach ($choices as $id => $label) {
                    $selected = ((string) $value === (string) $id) ? 'selected' : '';
                    echo '<option value="' . esc_attr($id) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            } else {
                echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="primary" />';
            }
        } else {
            $placeholder = $field['placeholder'] ?? '';
            echo '<input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '" />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    private function render_branding_contrast(): void
    {
        $branding = get_option('fp_exp_branding', []);
        $branding = is_array($branding) ? $branding : [];
        $palette = Theme::resolve_palette($branding);
        $report = Theme::contrast_report($palette);

        $classes = ['notice', 'fp-exp-contrast-notice'];
        $classes[] = empty($report) ? 'notice-success' : 'notice-warning';

        $attributes = [
            'data-fp-contrast-report' => '1',
            'data-title' => esc_attr__('Accessibility checks', 'fp-experiences'),
            'data-pass-message' => esc_attr__('Current palette passes AA contrast checks.', 'fp-experiences'),
            'data-warning-primary' => esc_attr__('Primary color contrast against the background is %s:1. Consider adjusting colors for AA compliance.', 'fp-experiences'),
            'data-warning-text' => esc_attr__('Body text contrast is %s:1. Increase contrast for readability.', 'fp-experiences'),
            'data-default-primary' => esc_attr($palette['primary'] ?? '#8B1E3F'),
            'data-default-background' => esc_attr($palette['background'] ?? '#FFFFFF'),
            'data-default-text' => esc_attr($palette['text'] ?? '#1F1F1F'),
        ];

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '"';
        foreach ($attributes as $key => $value) {
            echo ' ' . $key . '="' . $value . '"';
        }
        echo '>';
        echo '<p class="fp-exp-contrast-notice__title"><strong>' . esc_html__('Accessibility checks', 'fp-experiences') . '</strong></p>';
        echo '<ul class="fp-exp-contrast-notice__list">';
        foreach ($report as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul>';
        $message = empty($report) ? esc_html__('Current palette passes AA contrast checks.', 'fp-experiences') : '';
        echo '<p class="fp-exp-contrast-notice__message">' . $message . '</p>';
        echo '</div>';
    }

    private function render_calendar_status(): void
    {
        $settings = $this->get_calendar_settings();
        $connected = ! empty($settings['access_token']) && ! empty($settings['calendar_id']);
        $status = $connected
            ? esc_html__('Google Calendar is connected.', 'fp-experiences')
            : esc_html__('Google Calendar is not connected.', 'fp-experiences');

        echo '<div class="fp-exp-calendar-status">';
        echo '<p><strong>' . $status . '</strong></p>';

        if ($connected) {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Reservations will create or update events on the linked calendar.', 'fp-experiences') . '</p></div>';
        } else {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Connect a Google account to synchronise confirmed reservations with your calendar.', 'fp-experiences') . '</p></div>';
        }

        if ($connected && empty($settings['refresh_token'])) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Refresh token missing. Reconnect Google Calendar to keep the integration active.', 'fp-experiences') . '</p></div>';
        }

        if (! empty($settings['access_token']) && empty($settings['calendar_id'])) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Calendar ID is empty. Events cannot be pushed until a calendar is selected.', 'fp-experiences') . '</p></div>';
        }

        $notices = get_transient('fp_exp_calendar_notices');

        if (is_array($notices) && $notices) {
            $classes = [
                'error' => 'notice-error',
                'warning' => 'notice-warning',
                'success' => 'notice-success',
                'info' => 'notice-info',
            ];

            foreach ($notices as $notice) {
                if (empty($notice['message'])) {
                    continue;
                }

                $type = isset($notice['type']) ? (string) $notice['type'] : 'info';
                $class = $classes[$type] ?? 'notice-info';

                echo '<div class="notice ' . esc_attr($class . ' inline') . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
            }
        }

        $connect_url = wp_nonce_url(
            add_query_arg([
                'page' => $this->menu_slug,
                'tab' => 'calendar',
                'fp_exp_calendar_action' => 'connect',
            ], admin_url('admin.php')),
            'fp_exp_calendar_connect'
        );

        $disconnect_url = wp_nonce_url(
            add_query_arg([
                'page' => $this->menu_slug,
                'tab' => 'calendar',
                'fp_exp_calendar_action' => 'disconnect',
            ], admin_url('admin.php')),
            'fp_exp_calendar_disconnect'
        );

        if ($connected) {
            echo '<p><a class="button" href="' . esc_attr($disconnect_url) . '">' . esc_html__('Disconnect Google account', 'fp-experiences') . '</a></p>';
        } else {
            echo '<p><a class="button button-primary" href="' . esc_attr($connect_url) . '">' . esc_html__('Connect Google account', 'fp-experiences') . '</a></p>';
        }
        echo '</div>';
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_experience_layout($value): array
    {
        $defaults = [
            'container' => 'boxed',
            'max_width' => 1200,
            'gutter' => 24,
            'sidebar' => 'right',
        ];

        if (! is_array($value)) {
            return $defaults;
        }

        $container = isset($value['container']) ? strtolower(trim((string) $value['container'])) : $defaults['container'];
        if (! in_array($container, ['boxed', 'full'], true)) {
            $container = $defaults['container'];
        }

        $max_width = isset($value['max_width']) ? (int) $value['max_width'] : $defaults['max_width'];
        if ($max_width < 0) {
            $max_width = $defaults['max_width'];
        }

        $gutter = isset($value['gutter']) ? (int) $value['gutter'] : $defaults['gutter'];
        if ($gutter < 0) {
            $gutter = $defaults['gutter'];
        }

        $sidebar = isset($value['sidebar']) ? strtolower(trim((string) $value['sidebar'])) : $defaults['sidebar'];
        if (! in_array($sidebar, ['right', 'left', 'none'], true)) {
            $sidebar = $defaults['sidebar'];
        }

        return [
            'container' => $container,
            'max_width' => $max_width,
            'gutter' => $gutter,
            'sidebar' => $sidebar,
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_branding($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $presets = Theme::presets();
        $sanitised = [];

        if (! empty($value['preset']) && isset($presets[$value['preset']])) {
            $sanitised['preset'] = sanitize_key((string) $value['preset']);
        }

        $mode = isset($value['mode']) ? sanitize_key((string) $value['mode']) : 'light';
        if (! in_array($mode, ['light', 'dark', 'auto'], true)) {
            $mode = 'light';
        }
        $sanitised['mode'] = $mode;

        $color_keys = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'success', 'warning', 'danger'];

        foreach ($color_keys as $key) {
            if (empty($value[$key])) {
                continue;
            }
            $color = sanitize_hex_color((string) $value[$key]);
            if ($color) {
                $sanitised[$key] = $color;
            }
        }

        foreach (['radius', 'shadow', 'font'] as $key) {
            if (isset($value[$key]) && '' !== $value[$key]) {
                $sanitised[$key] = sanitize_text_field((string) $value[$key]);
            }
        }

        return $sanitised;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_tracking($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitised = [];

        if (isset($value['consent_defaults']) && is_array($value['consent_defaults'])) {
            $sanitised['consent_defaults'] = [];
            foreach ($value['consent_defaults'] as $channel => $flag) {
                $channel_key = sanitize_key((string) $channel);
                $sanitised['consent_defaults'][$channel_key] = ! empty($flag);
            }
            unset($value['consent_defaults']);
        }

        foreach ($value as $channel => $config) {
            if (! is_array($config)) {
                continue;
            }

            $channel_key = sanitize_key((string) $channel);
            $sanitised[$channel_key] = [];

            foreach ($config as $key => $raw) {
                $key_name = sanitize_key((string) $key);
                if (in_array($key_name, ['enabled', 'enhanced_conversions'], true)) {
                    $sanitised[$channel_key][$key_name] = ! empty($raw);
                    continue;
                }

                $sanitised[$channel_key][$key_name] = sanitize_text_field((string) $raw);
            }
        }

        return $sanitised;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_listing($value): array
    {
        $defaults = Helpers::listing_settings();

        if (! is_array($value)) {
            return $defaults;
        }

        $allowed_filters = array_keys($this->get_listing_filter_options());
        $filters = [];

        if (! empty($value['filters']) && is_array($value['filters'])) {
            foreach ($value['filters'] as $filter) {
                $filter_key = sanitize_key((string) $filter);
                if (in_array($filter_key, $allowed_filters, true)) {
                    $filters[] = $filter_key;
                }
            }
        }

        if (empty($filters)) {
            $filters = $defaults['filters'];
        }

        $per_page = absint((int) ($value['per_page'] ?? $defaults['per_page']));
        if ($per_page <= 0) {
            $per_page = $defaults['per_page'];
        }

        $order = isset($value['order']) ? strtoupper(sanitize_key((string) $value['order'])) : $defaults['order'];
        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = $defaults['order'];
        }

        $orderby = isset($value['orderby']) ? sanitize_key((string) $value['orderby']) : $defaults['orderby'];
        if (! in_array($orderby, ['menu_order', 'date', 'title', 'price'], true)) {
            $orderby = $defaults['orderby'];
        }

        $show_price_from = ! empty($value['show_price_from']);

        return [
            'filters' => array_values(array_unique($filters)),
            'per_page' => $per_page,
            'order' => $order,
            'orderby' => $orderby,
            'show_price_from' => $show_price_from,
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_brevo($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitised = [];
        $sanitised['enabled'] = ! empty($value['enabled']);
        $sanitised['api_key'] = isset($value['api_key']) ? sanitize_text_field((string) $value['api_key']) : '';
        $sanitised['webhook_secret'] = isset($value['webhook_secret']) ? sanitize_text_field((string) $value['webhook_secret']) : '';
        $sanitised['list_id'] = isset($value['list_id']) ? absint($value['list_id']) : 0;
        $sanitised['subscribe_to_list'] = ! empty($value['subscribe_to_list']);

        $mapping = [];
        if (isset($value['attribute_mapping']) && is_array($value['attribute_mapping'])) {
            foreach ($value['attribute_mapping'] as $key => $attribute) {
                $mapping[sanitize_key((string) $key)] = sanitize_text_field((string) $attribute);
            }
        }
        $sanitised['attribute_mapping'] = $mapping;

        $templates = [];
        if (isset($value['templates']) && is_array($value['templates'])) {
            foreach ($value['templates'] as $key => $template_id) {
                $key_name = sanitize_key((string) $key);
                $templates[$key_name] = absint($template_id);
            }
        }
        $sanitised['templates'] = $templates;

        return $sanitised;
    }

    public function sanitize_rtb($value): array
    {
        $value = is_array($value) ? $value : [];

        $mode = isset($value['mode']) ? sanitize_key((string) $value['mode']) : 'off';
        if (! in_array($mode, ['off', 'confirm', 'pay_later'], true)) {
            $mode = 'off';
        }

        $clean = [
            'mode' => $mode,
            'timeout' => max(5, absint($value['timeout'] ?? 30)),
            'block_capacity' => ! empty($value['block_capacity']),
            'templates' => [],
            'fallback' => [],
        ];

        $events = ['request', 'approved', 'declined', 'payment'];

        if (isset($value['templates']) && is_array($value['templates'])) {
            foreach ($events as $event) {
                if (! empty($value['templates'][$event])) {
                    $clean['templates'][$event] = absint($value['templates'][$event]);
                }
            }
        }

        if (isset($value['fallback']) && is_array($value['fallback'])) {
            foreach ($events as $event) {
                $subject = $value['fallback'][$event]['subject'] ?? '';
                $body = $value['fallback'][$event]['body'] ?? '';

                if ('' === $subject && '' === $body) {
                    continue;
                }

                $clean['fallback'][$event] = [
                    'subject' => sanitize_text_field((string) $subject),
                    'body' => sanitize_textarea_field((string) $body),
                ];
            }
        }

        return $clean;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function sanitize_calendar($value): array
    {
        $value = is_array($value) ? $value : [];
        $existing = $this->get_calendar_settings();

        $sanitised = $existing;
        $sanitised['client_id'] = isset($value['client_id']) ? sanitize_text_field((string) $value['client_id']) : ($existing['client_id'] ?? '');
        $sanitised['client_secret'] = isset($value['client_secret']) ? sanitize_text_field((string) $value['client_secret']) : ($existing['client_secret'] ?? '');
        $sanitised['redirect_uri'] = isset($value['redirect_uri']) ? sanitize_text_field((string) $value['redirect_uri']) : ($existing['redirect_uri'] ?? '');
        $sanitised['calendar_id'] = isset($value['calendar_id']) ? sanitize_text_field((string) $value['calendar_id']) : ($existing['calendar_id'] ?? '');

        return $sanitised;
    }

    public function maybe_handle_calendar_actions(): void
    {
        if (! is_admin()) {
            return;
        }

        if (! Helpers::can_manage_fp()) {
            return;
        }

        if (! isset($_GET['page']) || 'fp_exp_settings' !== sanitize_key((string) wp_unslash($_GET['page']))) {
            return;
        }

        $action = isset($_GET['fp_exp_calendar_action']) ? sanitize_key((string) wp_unslash($_GET['fp_exp_calendar_action'])) : '';

        $nonce = isset($_GET['_wpnonce']) ? (string) wp_unslash($_GET['_wpnonce']) : '';

        if ('connect' === $action && wp_verify_nonce($nonce, 'fp_exp_calendar_action')) {
            $this->initiate_calendar_connect();
        } elseif ('disconnect' === $action && wp_verify_nonce($nonce, 'fp_exp_calendar_action')) {
            $this->disconnect_calendar();
        } elseif ('oauth' === $action) {
            $this->handle_calendar_oauth();
        }
    }

    private function initiate_calendar_connect(): void
    {
        $settings = $this->get_calendar_settings();
        $client_id = (string) ($settings['client_id'] ?? '');
        $client_secret = (string) ($settings['client_secret'] ?? '');
        $redirect_uri = (string) ($settings['redirect_uri'] ?? '');

        if (! $client_id || ! $client_secret) {
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_missing_credentials', esc_html__('Save your client ID and secret before connecting.', 'fp-experiences'));

            return;
        }

        if (! $redirect_uri) {
            $redirect_uri = add_query_arg([
                'page' => $this->menu_slug,
                'tab' => 'calendar',
                'fp_exp_calendar_action' => 'oauth',
            ], admin_url('admin.php'));
            $settings['redirect_uri'] = $redirect_uri;
            update_option('fp_exp_google_calendar', $settings, false);
        }

        $state = wp_generate_password(16, false, false);
        set_transient('fp_exp_calendar_state_' . $state, [
            'created' => time(),
        ], 10 * MINUTE_IN_SECONDS);

        $auth_url = add_query_arg([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], 'https://accounts.google.com/o/oauth2/v2/auth');

        wp_redirect($auth_url);
        exit;
    }

    private function disconnect_calendar(): void
    {
        $settings = $this->get_calendar_settings();
        unset($settings['access_token'], $settings['refresh_token'], $settings['expires_at']);
        update_option('fp_exp_google_calendar', $settings, false);
        add_settings_error('fp_exp_settings', 'fp_exp_calendar_disconnected', esc_html__('Google Calendar disconnected.', 'fp-experiences'), 'updated');
        wp_safe_redirect(add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'calendar',
        ], admin_url('admin.php')));
        exit;
    }

    private function handle_calendar_oauth(): void
    {
        $settings = $this->get_calendar_settings();
        $state = isset($_GET['state']) ? sanitize_text_field((string) wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field((string) wp_unslash($_GET['code'])) : '';

        if (! $state || ! $code) {
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_invalid_state', esc_html__('OAuth response missing state or code.', 'fp-experiences'));
            return;
        }

        $cached_state = get_transient('fp_exp_calendar_state_' . $state);
        delete_transient('fp_exp_calendar_state_' . $state);

        if (! $cached_state) {
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_state_expired', esc_html__('OAuth session expired. Please try again.', 'fp-experiences'));
            return;
        }

        $client_id = (string) ($settings['client_id'] ?? '');
        $client_secret = (string) ($settings['client_secret'] ?? '');
        $redirect_uri = (string) ($settings['redirect_uri'] ?? '');

        if (! $client_id || ! $client_secret || ! $redirect_uri) {
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_missing_credentials', esc_html__('OAuth credentials missing. Save the settings and try again.', 'fp-experiences'));
            return;
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 15,
        ]);

        $status = wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            Logger::log('google_calendar', 'OAuth exchange failed', [
                'status' => $status,
                'body' => wp_remote_retrieve_body($response),
            ]);
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_oauth_error', esc_html__('Could not complete the Google OAuth flow. See logs for details.', 'fp-experiences'));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['access_token'])) {
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_oauth_invalid', esc_html__('Unexpected OAuth response from Google.', 'fp-experiences'));
            return;
        }

        $settings['access_token'] = (string) $body['access_token'];
        $settings['refresh_token'] = (string) ($body['refresh_token'] ?? ($settings['refresh_token'] ?? ''));
        $settings['expires_at'] = time() + (int) ($body['expires_in'] ?? 3600);
        update_option('fp_exp_google_calendar', $settings, false);

        add_settings_error('fp_exp_settings', 'fp_exp_calendar_connected', esc_html__('Google Calendar connected successfully.', 'fp-experiences'), 'updated');
        wp_safe_redirect(add_query_arg([
            'page' => $this->menu_slug,
            'tab' => 'calendar',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function extract_nested_value(array $settings, string $key)
    {
        $segments = $this->parse_key_segments($key);
        if (! $segments) {
            return '';
        }

        $root = array_shift($segments);
        $value = $settings[$root] ?? '';

        foreach ($segments as $segment) {
            if (! is_array($value) || ! isset($value[$segment])) {
                return '';
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function build_input_name(string $option, string $key): string
    {
        return $option . '[' . implode('][', $this->parse_key_segments($key)) . ']';
    }

    /**
     * @return array<int, string>
     */
    private function parse_key_segments(string $key): array
    {
        $key = (string) $key;
        $segments = [];
        $buffer = '';
        $length = strlen($key);

        for ($i = 0; $i < $length; $i++) {
            $char = $key[$i];
            if ('[' === $char) {
                if ('' !== $buffer) {
                    $segments[] = $buffer;
                }
                $buffer = '';
                continue;
            }

            if (']' === $char) {
                if ('' !== $buffer) {
                    $segments[] = $buffer;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ('' !== $buffer) {
            $segments[] = $buffer;
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function get_calendar_settings(): array
    {
        $settings = get_option('fp_exp_google_calendar', []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<string, string>
     */
    private function get_calendar_choices(): array
    {
        $settings = $this->get_calendar_settings();
        $token = $this->ensure_calendar_token($settings);

        if (! $token) {
            return [];
        }

        $cache = get_transient('fp_exp_calendar_choices');
        if (is_array($cache) && isset($cache['items'])) {
            return $cache['items'];
        }

        $response = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 15,
        ]);

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            Logger::log('google_calendar', 'Failed to list calendars', [
                'status' => $status,
                'body' => wp_remote_retrieve_body($response),
            ]);
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['items'])) {
            return [];
        }

        $choices = [];
        foreach ($body['items'] as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $summary = (string) ($item['summary'] ?? $item['id']);
            $choices[(string) $item['id']] = $summary;
        }

        set_transient('fp_exp_calendar_choices', ['items' => $choices], 5 * MINUTE_IN_SECONDS);

        return $choices;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function ensure_calendar_token(array $settings): string
    {
        $access_token = (string) ($settings['access_token'] ?? '');
        $expires_at = (int) ($settings['expires_at'] ?? 0);

        if ($access_token && $expires_at > (time() + 60)) {
            return $access_token;
        }

        $refresh_token = (string) ($settings['refresh_token'] ?? '');
        $client_id = (string) ($settings['client_id'] ?? '');
        $client_secret = (string) ($settings['client_secret'] ?? '');

        if (! $refresh_token || ! $client_id || ! $client_secret) {
            return '';
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
            'timeout' => 15,
        ]);

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            Logger::log('google_calendar', 'Failed to refresh access token', [
                'status' => $status,
                'body' => wp_remote_retrieve_body($response),
            ]);
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($body) || empty($body['access_token'])) {
            return '';
        }

        $settings['access_token'] = (string) $body['access_token'];
        $settings['expires_at'] = time() + (int) ($body['expires_in'] ?? 3600);
        update_option('fp_exp_google_calendar', $settings, false);

        return $settings['access_token'];
    }
}
