<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Admin\Traits\FormFieldRenderer;
use FP_Exp\Admin\Form\FieldDefinition;
use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Admin\Traits\FormSanitizer;
use FP_Exp\Application\Settings\GetSettingsUseCase;
use FP_Exp\Application\Settings\UpdateSettingsUseCase;
use FP_Exp\Booking\EmailTranslator;
use FP_Exp\Booking\Emails;
use FP_Exp\Gift\Email\Templates\VoucherEmailTemplate;
use FP_Exp\Services\Options\OptionsInterface;
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
use function array_key_exists;
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

final class SettingsPage implements HookableInterface
{
    use FormFieldRenderer;
    use FormSanitizer;

    private string $menu_slug = 'fp_exp_settings';
    private ?GetSettingsUseCase $getSettingsUseCase = null;
    private ?UpdateSettingsUseCase $updateSettingsUseCase = null;
    private ?OptionsInterface $options = null;

    /**
     * SettingsPage constructor.
     *
     * @param OptionsInterface|null $options Optional OptionsInterface (will try to get from container if not provided)
     */
    public function __construct(?OptionsInterface $options = null)
    {
        $this->options = $options;
    }

    /**
     * Get OptionsInterface instance.
     * Tries container first, falls back to direct instantiation for backward compatibility.
     */
    private function getOptions(): OptionsInterface
    {
        if ($this->options !== null) {
            return $this->options;
        }

        // Try to get from container
        $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
        if ($kernel !== null) {
            $container = $kernel->container();
            if ($container->has(OptionsInterface::class)) {
                try {
                    $this->options = $container->make(OptionsInterface::class);
                    return $this->options;
                } catch (\Throwable $e) {
                    // Fall through to direct instantiation
                }
            }
        }

        // Fallback to direct instantiation
        $this->options = new \FP_Exp\Services\Options\Options();
        return $this->options;
    }

    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_handle_calendar_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_settings(): void
    {
        $this->register_email_settings();
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
            wp_die(esc_html__('Non hai i permessi per gestire le impostazioni di FP Experiences.', 'fp-experiences'));
        }

        $tabs = $this->get_tabs();
        $active_tab = $this->get_active_tab($tabs);

        echo '<div class="wrap">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-settings">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Impostazioni', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Impostazioni FP Experiences', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Configura preferenze, integrazioni e regole operative delle esperienze.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_settings');

        echo '<div class="fp-exp-tabs nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg([
                'page' => $this->menu_slug,
                'tab' => $slug,
            ], admin_url('admin.php'));
            $classes = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
            // Allow dashicons spans in tab labels
            $allowed_html = ['span' => ['class' => []]];
            echo '<a class="' . esc_attr($classes) . '" href="' . esc_attr($url) . '">' . wp_kses($label, $allowed_html) . '</a>';
        }
        echo '</div>';

        if ('tools' === $active_tab) {
            $this->render_tools_panel();
        } elseif ('booking' === $active_tab) {
            $this->render_booking_rules_panel();
        } elseif ('logs' === $active_tab) {
            $this->render_logs_overview();
        } else {
            if ('calendar' === $active_tab) {
                $this->render_calendar_status();
            }

            echo '<form action="options.php" method="post" class="fp-exp-settings__form">';

            if ('branding' === $active_tab) {
                settings_fields('fp_exp_settings_branding');
                do_settings_sections('fp_exp_settings_branding');
                $this->render_branding_contrast();
                $this->render_preview_notice();
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
            } elseif ('calendar' === $active_tab) {
                settings_fields('fp_exp_settings_calendar');
                do_settings_sections('fp_exp_settings_calendar');
            } else {
                settings_fields('fp_exp_settings_general');
                do_settings_sections('fp_exp_settings_general');
            }

            submit_button();
            echo '</form>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function enqueue_assets(string $hook = ''): void
    {
        $screen = get_current_screen();
        // Verifica anche il hook e il page parameter per maggiore sicurezza
        $is_settings_page = $screen && (
            'fp-exp-dashboard_page_' . $this->menu_slug === $screen->id ||
            (isset($_GET['page']) && $_GET['page'] === $this->menu_slug)
        );
        
        if (! $is_settings_page) {
            return;
        }

        // Carica sempre gli stili admin per tutte le pagine settings
        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'], // Usa solo jQuery, non servono wp-api-fetch o wp-i18n
            Helpers::asset_version($admin_js),
            true
        );

        // Toast notifications system
        wp_enqueue_script(
            'fp-exp-toast',
            FP_EXP_PLUGIN_URL . 'assets/js/admin/toast.js',
            [],
            Helpers::asset_version('assets/js/admin/toast.js'),
            true
        );

        $tabs = $this->get_tabs();
        $active_tab = $this->get_active_tab($tabs);

        // Carica assets specifici per tools
        if ('tools' === $active_tab) {
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
    }

    public function enqueue_tools_assets(): void
    {
        error_log('[FP-EXP-SETTINGS] enqueue_tools_assets() START');
        
        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        
        error_log('[FP-EXP-SETTINGS] CSS file: ' . $admin_css);
        
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        
        error_log('[FP-EXP-SETTINGS] JS file: ' . $admin_js);
        
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'], // Usa solo jQuery, non servono wp-api-fetch o wp-i18n
            Helpers::asset_version($admin_js),
            true
        );
        
        error_log('[FP-EXP-SETTINGS] Script enqueued: fp-exp-admin');

        // Config base per fpExpAdmin (richiesto da admin.js)
        wp_localize_script('fp-exp-admin', 'fpExpAdmin', [
            'restUrl' => rest_url('fp-exp/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => FP_EXP_PLUGIN_URL,
            'strings' => [],
        ]);

        // Config specifico per tools
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

    private function register_email_settings(): void
    {
        register_setting('fp_exp_settings_emails', 'fp_exp_emails', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_emails_settings'],
            'default' => [],
        ]);

        add_settings_section(
            'fp_exp_section_emails_addresses',
            esc_html__('Sender & recipients', 'fp-experiences'),
            [$this, 'render_email_addresses_help'],
            'fp_exp_emails_senders'
        );

        add_settings_field(
            'fp_exp_emails_sender_structure',
            esc_html__('Structure email', 'fp-experiences'),
            [$this, 'render_email_nested_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_emails_addresses',
            [
                'path' => ['sender', 'structure'],
                'description' => esc_html__('Primary address for booking confirmations and staff alerts.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_sender_webmaster',
            esc_html__('Webmaster email', 'fp-experiences'),
            [$this, 'render_email_nested_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_emails_addresses',
            [
                'path' => ['sender', 'webmaster'],
                'description' => esc_html__('Secondary address to receive staff notifications.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_recipients_staff_extra',
            esc_html__('Destinatari staff aggiuntivi', 'fp-experiences'),
            [$this, 'render_email_recipients_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_emails_addresses',
            [
                'path' => ['recipients', 'staff_extra'],
                'placeholder' => 'staff1@example.com, staff2@example.com',
                'description' => esc_html__('Inserisci una o più email separate da virgola. Verranno aggiunte alle notifiche staff.', 'fp-experiences'),
            ]
        );

        // --- Mail provider & SMTP ---
        add_settings_section(
            'fp_exp_section_email_provider',
            esc_html__('Provider email', 'fp-experiences'),
            [$this, 'render_email_provider_help'],
            'fp_exp_emails_senders'
        );

        add_settings_field(
            'fp_exp_emails_provider',
            esc_html__('Provider invio', 'fp-experiences'),
            [$this, 'render_email_provider_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider'
        );

        add_settings_field(
            'fp_exp_emails_from_email',
            esc_html__('Email mittente (From)', 'fp-experiences'),
            [$this, 'render_email_nested_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['from_email'],
                'description' => esc_html__('Indirizzo email usato come mittente. Se vuoto, usa l\'email struttura.', 'fp-experiences'),
                'placeholder' => 'noreply@example.com',
            ]
        );

        add_settings_field(
            'fp_exp_emails_from_name',
            esc_html__('Nome mittente (From)', 'fp-experiences'),
            [$this, 'render_email_text_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['from_name'],
                'description' => esc_html__('Nome visualizzato come mittente. Se vuoto, usa il nome del sito.', 'fp-experiences'),
                'placeholder' => 'La Mia Struttura',
            ]
        );

        add_settings_field(
            'fp_exp_emails_smtp_host',
            esc_html__('SMTP Host', 'fp-experiences'),
            [$this, 'render_email_text_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['smtp', 'host'],
                'placeholder' => 'smtp.gmail.com',
                'description' => esc_html__('Visibile solo se il provider è "SMTP".', 'fp-experiences'),
                'class' => 'fp-exp-smtp-field',
            ]
        );

        add_settings_field(
            'fp_exp_emails_smtp_port',
            esc_html__('SMTP Porta', 'fp-experiences'),
            [$this, 'render_email_number_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['smtp', 'port'],
                'min' => 1,
                'max' => 65535,
                'step' => 1,
                'placeholder' => '587',
                'class' => 'fp-exp-smtp-field',
            ]
        );

        add_settings_field(
            'fp_exp_emails_smtp_encryption',
            esc_html__('Crittografia SMTP', 'fp-experiences'),
            [$this, 'render_email_smtp_encryption_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            ['class' => 'fp-exp-smtp-field']
        );

        add_settings_field(
            'fp_exp_emails_smtp_username',
            esc_html__('SMTP Username', 'fp-experiences'),
            [$this, 'render_email_text_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['smtp', 'username'],
                'placeholder' => '',
                'class' => 'fp-exp-smtp-field',
            ]
        );

        add_settings_field(
            'fp_exp_emails_smtp_password',
            esc_html__('SMTP Password', 'fp-experiences'),
            [$this, 'render_email_password_field'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider',
            [
                'path' => ['smtp', 'password'],
                'class' => 'fp-exp-smtp-field',
            ]
        );

        add_settings_field(
            'fp_exp_emails_test',
            esc_html__('Test invio', 'fp-experiences'),
            [$this, 'render_email_test_button'],
            'fp_exp_emails_senders',
            'fp_exp_section_email_provider'
        );

        add_settings_section(
            'fp_exp_section_email_branding',
            esc_html__('Email branding', 'fp-experiences'),
            [$this, 'render_email_branding_help'],
            'fp_exp_emails_look'
        );

        add_settings_field(
            'fp_exp_emails_branding_logo',
            esc_html__('Logo URL', 'fp-experiences'),
            [$this, 'render_emails_branding_field'],
            'fp_exp_emails_look',
            'fp_exp_section_email_branding',
            [
                'key' => 'logo',
                'type' => 'text',
                'placeholder' => 'https://example.com/logo.png',
                'description' => esc_html__('Absolute URL to the logo shown in the email header. Leave empty to display only the title.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_branding_logo_width',
            esc_html__('Larghezza logo (px)', 'fp-experiences'),
            [$this, 'render_emails_branding_field'],
            'fp_exp_emails_look',
            'fp_exp_section_email_branding',
            [
                'key' => 'logo_width',
                'type' => 'number',
                'placeholder' => '180',
                'description' => esc_html__('Larghezza massima del logo in pixel. Default: 180px.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_branding_logo_height',
            esc_html__('Altezza logo (px)', 'fp-experiences'),
            [$this, 'render_emails_branding_field'],
            'fp_exp_emails_look',
            'fp_exp_section_email_branding',
            [
                'key' => 'logo_height',
                'type' => 'number',
                'placeholder' => '',
                'description' => esc_html__('Altezza massima del logo in pixel. Lascia vuoto per calcolo automatico proporzionale.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_branding_header',
            esc_html__('Header title', 'fp-experiences'),
            [$this, 'render_emails_branding_field'],
            'fp_exp_emails_look',
            'fp_exp_section_email_branding',
            [
                'key' => 'header_text',
                'type' => 'text',
                'placeholder' => esc_html__('Es. Benvenuto a bordo', 'fp-experiences'),
                'description' => esc_html__('Appears alongside the logo in the coloured header. Defaults to the site name.', 'fp-experiences'),
            ]
        );

        add_settings_field(
            'fp_exp_emails_branding_footer',
            esc_html__('Footer note', 'fp-experiences'),
            [$this, 'render_emails_branding_field'],
            'fp_exp_emails_look',
            'fp_exp_section_email_branding',
            [
                'key' => 'footer_text',
                'type' => 'textarea',
                'placeholder' => esc_html__('Es. Seguici sui social o rispondi a questa email per assistenza.', 'fp-experiences'),
                'description' => esc_html__('Closing message displayed in the email footer. Supports multiple lines.', 'fp-experiences'),
            ]
        );

        // Sezione: Tipi di email (toggle)
        add_settings_section(
            'fp_exp_section_email_types',
            esc_html__('Tipi di email', 'fp-experiences'),
            [$this, 'render_email_types_help'],
            'fp_exp_emails_config'
        );

        $email_types = [
            ['key' => 'customer_confirmation', 'label' => esc_html__('Conferma cliente', 'fp-experiences')],
            ['key' => 'staff_notification', 'label' => esc_html__('Notifica staff', 'fp-experiences')],
            ['key' => 'customer_reminder', 'label' => esc_html__('Promemoria cliente (pre-esperienza)', 'fp-experiences')],
            ['key' => 'customer_post_experience', 'label' => esc_html__('Follow-up cliente (post-esperienza)', 'fp-experiences')],
        ];
        foreach ($email_types as $type) {
            add_settings_field(
                'fp_exp_emails_type_' . $type['key'],
                $type['label'],
                [$this, 'render_email_toggle_field'],
                'fp_exp_emails_config',
                'fp_exp_section_email_types',
                [
                    'path' => ['types', $type['key']],
                ]
            );
        }

        // Sezione: Pianificazione (offset)
        add_settings_section(
            'fp_exp_section_email_schedule',
            esc_html__('Pianificazione', 'fp-experiences'),
            [$this, 'render_email_schedule_help'],
            'fp_exp_emails_config'
        );

        add_settings_field(
            'fp_exp_emails_schedule_reminder_offset_hours',
            esc_html__('Offset promemoria (ore prima dell\'inizio)', 'fp-experiences'),
            [$this, 'render_email_number_field'],
            'fp_exp_emails_config',
            'fp_exp_section_email_schedule',
            [
                'path' => ['schedule', 'reminder_offset_hours'],
                'min' => 0,
                'max' => 240,
                'step' => 1,
                'placeholder' => '24',
            ]
        );

        add_settings_field(
            'fp_exp_emails_schedule_followup_offset_hours',
            esc_html__('Offset follow-up (ore dopo la fine)', 'fp-experiences'),
            [$this, 'render_email_number_field'],
            'fp_exp_emails_config',
            'fp_exp_section_email_schedule',
            [
                'path' => ['schedule', 'followup_offset_hours'],
                'min' => 0,
                'max' => 240,
                'step' => 1,
                'placeholder' => '24',
            ]
        );

        // Sezione: Soggetti personalizzati
        add_settings_section(
            'fp_exp_section_email_subjects',
            esc_html__('Soggetti email', 'fp-experiences'),
            [$this, 'render_email_subjects_help'],
            'fp_exp_emails_config'
        );

        $subjects = [
            ['key' => 'customer_confirmation', 'label' => esc_html__('Conferma cliente', 'fp-experiences')],
            ['key' => 'customer_reminder', 'label' => esc_html__('Promemoria cliente', 'fp-experiences')],
            ['key' => 'customer_post_experience', 'label' => esc_html__('Follow-up cliente', 'fp-experiences')],
            ['key' => 'staff_notification_new', 'label' => esc_html__('Notifica staff (nuova prenotazione)', 'fp-experiences')],
            ['key' => 'staff_notification_cancelled', 'label' => esc_html__('Notifica staff (prenotazione annullata)', 'fp-experiences')],
        ];
        foreach ($subjects as $subject) {
            add_settings_field(
                'fp_exp_emails_subject_' . $subject['key'],
                $subject['label'],
                [$this, 'render_email_subject_field'],
                'fp_exp_emails_config',
                'fp_exp_section_email_subjects',
                [
                    'path' => ['subjects', $subject['key']],
                    'placeholder' => '',
                ]
            );
        }

        // Sezione: Anteprime email
        add_settings_section(
            'fp_exp_section_email_previews',
            esc_html__('Anteprime email', 'fp-experiences'),
            [$this, 'render_email_previews_section'],
            'fp_exp_emails_previews'
        );
    }

    private function register_general_settings(): void
    {
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
                    'right' => esc_html__('Colonna destra', 'fp-experiences'),
                    'left' => esc_html__('Colonna sinistra', 'fp-experiences'),
                    'none' => esc_html__('Nessuna sidebar (colonna singola)', 'fp-experiences'),
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

        // Handle both 'yes'/'no' from toggle and 1/0 from legacy checkbox
        $enabled_raw = $value['enabled'] ?? 'no';
        $enabled = in_array($enabled_raw, ['yes', '1', 1, true, 'true'], true) ? 'yes' : 'no';
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
        // Handle both 'yes'/'no' and legacy 1/0 values
        $enabled_raw = $settings['enabled'] ?? 'no';
        $enabled = in_array($enabled_raw, ['yes', '1', 1, true, 'true'], true);
        $toggle_value = $enabled ? 'yes' : 'no';

        // Use Field Renderer Strategy for consistent toggle styling
        $field = new FieldDefinition(
            name: 'fp_exp_gift[enabled]',
            type: 'toggle',
            label: '', // Empty label - WordPress shows it via add_settings_field
            value: $toggle_value,
            options: [
                'toggle_class' => 'fp-exp-settings__toggle',
                'add_hidden_input' => true, // Ensure value is sent even when unchecked
                'label_text' => esc_html__('Allow customers to purchase gift vouchers and send them via email.', 'fp-experiences'),
            ],
            description: null,
            required: false,
            attributes: []
        );

        $renderer = $this->getFieldRendererFactory()->getRenderer('toggle');
        echo $renderer->render($field, '');
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

        $option = $this->getOptions()->get('fp_exp_experience_layout', []);

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
            'default' => ['preset' => Theme::default_preset()],
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
        
        // Try to use new use case if available
        $useCase = $this->getGetSettingsUseCase();
        if ($useCase !== null) {
            $value = $useCase->get($option, $default);
        } else {
            // Fallback to OptionsInterface for backward compatibility
            $value = $this->getOptions()->get($option, $default);
        }
        
        // Convert to toggle format (yes/no)
        $toggle_value = in_array($value, ['yes', '1', 'true', 1, true], true) ? 'yes' : 'no';

        // Use Field Renderer Strategy
        // Note: For toggles, we skip the label in <th> and put it in the toggle itself
        // to avoid double labels (one in <th> and one in toggle)
        $field = new FieldDefinition(
            name: $option,
            type: 'toggle',
            label: '', // Empty label - we'll put it in the toggle
            value: $toggle_value,
            options: [
                'description' => $args['description'] ?? null,
                'toggle_class' => 'fp-exp-settings__toggle',
                'add_hidden_input' => true, // Tell renderer to add hidden input
                'label_text' => $label, // Put label text in the toggle
            ],
            description: $args['description'] ?? null,
            required: false,
            attributes: []
        );

        $renderer = $this->getFieldRendererFactory()->getRenderer('toggle');
        
        // Output the toggle field directly - WordPress Settings API already handles <tr><th><td> wrapper
        echo $renderer->render($field, '');
    }

    /**
     * @param mixed $value
     */
    public function sanitize_toggle($value): string
    {
        // Use Sanitizer Strategy
        return (string) $this->sanitize_form_field('toggle', $value);
    }

    /**
     * @param mixed $value
     *
     * @return array<string, string>
     */
    public function sanitize_email_branding($value): array
    {
        if (! is_array($value)) {
            $value = [];
        }

        // Use Sanitizer Strategy for nested fields
        $logo_width  = (int) ($value['logo_width'] ?? 0);
        $logo_height = (int) ($value['logo_height'] ?? 0);

        return [
            'logo'        => esc_url_raw((string) ($value['logo'] ?? '')),
            'logo_width'  => $logo_width > 0 ? min($logo_width, 600) : 0,
            'logo_height' => $logo_height > 0 ? min($logo_height, 600) : 0,
            'header_text' => $this->sanitize_form_field('text', $value['header_text'] ?? ''),
            'footer_text' => $this->sanitize_form_field('textarea', $value['footer_text'] ?? ''),
        ];
    }

    /**
     * @param mixed $value
     * @return array{
     *   sender: array{structure:string,webmaster:string},
     *   branding: array{logo:string,logo_width:int,logo_height:int,header_text:string,footer_text:string}
     * }
     */
    public function sanitize_emails_settings($value): array
    {
        // Debug logging per tracciare i valori ricevuti dal form
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp Emails] sanitize_emails_settings() called with value: ' . print_r($value, true));
        }

        $value = is_array($value) ? $value : [];

        // mittenti
        $sender = isset($value['sender']) && is_array($value['sender']) ? $value['sender'] : [];
        $structure = sanitize_email((string) ($sender['structure'] ?? $this->getOptions()->get('fp_exp_structure_email', '')));
        $webmaster = sanitize_email((string) ($sender['webmaster'] ?? $this->getOptions()->get('fp_exp_webmaster_email', '')));

        // branding
        $branding = isset($value['branding']) && is_array($value['branding']) ? $value['branding'] : [];
        $branding = $this->sanitize_email_branding($branding);

        // destinatari aggiuntivi staff
        $recipients = isset($value['recipients']) && is_array($value['recipients']) ? $value['recipients'] : [];
        $staff_extra_raw = (string) ($recipients['staff_extra'] ?? '');
        $staff_extra = array_values(array_filter(array_map('sanitize_email', array_map('trim', preg_split('/[,;]+/', $staff_extra_raw) ?: []))));

        // tipi (toggle)
        $types = isset($value['types']) && is_array($value['types']) ? $value['types'] : [];
        $types_sanitized = [];
        foreach (['customer_confirmation', 'staff_notification', 'customer_reminder', 'customer_post_experience'] as $key) {
            // Get raw value - if not set or empty, default to 'no' (toggle off)
            $raw = $types[$key] ?? '';
            // If empty string or not set, treat as 'no' (disabled)
            if ($raw === '' || $raw === null) {
                $raw = 'no';
            }
            $types_sanitized[$key] = $this->sanitize_yes_no($raw);
        }

        // schedule (offset ore)
        $schedule = isset($value['schedule']) && is_array($value['schedule']) ? $value['schedule'] : [];
        $reminder_hours = isset($schedule['reminder_offset_hours']) ? (int) $schedule['reminder_offset_hours'] : 24;
        $followup_hours = isset($schedule['followup_offset_hours']) ? (int) $schedule['followup_offset_hours'] : 24;
        $reminder_hours = max(0, min(240, $reminder_hours));
        $followup_hours = max(0, min(240, $followup_hours));

        $schedule_sanitized = [
            'reminder_offset_hours' => $reminder_hours,
            'followup_offset_hours' => $followup_hours,
        ];

        // subjects (override opzionali)
        $subjects = isset($value['subjects']) && is_array($value['subjects']) ? $value['subjects'] : [];
        $subjects_sanitized = [];
        foreach (['customer_confirmation', 'customer_reminder', 'customer_post_experience', 'staff_notification_new', 'staff_notification_cancelled'] as $key) {
            $subjects_sanitized[$key] = sanitize_text_field((string) ($subjects[$key] ?? ''));
        }

        // provider & from
        $provider = sanitize_key((string) ($value['provider'] ?? 'wordpress'));
        if (! in_array($provider, ['wordpress', 'smtp', 'brevo'], true)) {
            $provider = 'wordpress';
        }
        $from_email = sanitize_email((string) ($value['from_email'] ?? ''));
        $from_name  = sanitize_text_field((string) ($value['from_name'] ?? ''));

        // SMTP settings
        $smtp_raw = isset($value['smtp']) && is_array($value['smtp']) ? $value['smtp'] : [];
        $smtp = [
            'host'       => sanitize_text_field((string) ($smtp_raw['host'] ?? '')),
            'port'       => max(1, min(65535, (int) ($smtp_raw['port'] ?? 587))),
            'encryption' => in_array(($smtp_raw['encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true)
                ? (string) $smtp_raw['encryption']
                : 'tls',
            'username'   => sanitize_text_field((string) ($smtp_raw['username'] ?? '')),
            'password'   => (string) ($smtp_raw['password'] ?? ''),
        ];

        $result = [
            'sender' => [
                'structure' => $structure,
                'webmaster' => $webmaster,
            ],
            'branding' => $branding,
            'recipients' => [
                'staff_extra' => $staff_extra,
            ],
            'types' => $types_sanitized,
            'schedule' => $schedule_sanitized,
            'subjects' => $subjects_sanitized,
            'provider'   => $provider,
            'from_email' => $from_email,
            'from_name'  => $from_name,
            'smtp'       => $smtp,
        ];

        return $result;
    }

    private function sanitize_yes_no($value): string
    {
        // Use Sanitizer Strategy
        return (string) $this->sanitize_form_field('toggle', $value);
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
        $tabs = [
            'general' => '<span class="dashicons dashicons-admin-settings"></span> ' . esc_html__('General', 'fp-experiences'),
            'gift' => '<span class="dashicons dashicons-tickets-alt"></span> ' . esc_html__('Gift', 'fp-experiences'),
            'branding' => '<span class="dashicons dashicons-art"></span> ' . esc_html__('Branding', 'fp-experiences'),
            'booking' => '<span class="dashicons dashicons-calendar-alt"></span> ' . esc_html__('Booking Rules', 'fp-experiences'),
            'calendar' => '<span class="dashicons dashicons-calendar"></span> ' . esc_html__('Calendar', 'fp-experiences'),
            'tracking' => '<span class="dashicons dashicons-chart-line"></span> ' . esc_html__('Tracking', 'fp-experiences'),
            'rtb' => '<span class="dashicons dashicons-email"></span> ' . esc_html__('Request to Book', 'fp-experiences'),
            'listing' => '<span class="dashicons dashicons-list-view"></span> ' . esc_html__('Vetrina', 'fp-experiences'),
            'tools' => '<span class="dashicons dashicons-admin-tools"></span> ' . esc_html__('Tools', 'fp-experiences'),
            'logs' => '<span class="dashicons dashicons-media-text"></span> ' . esc_html__('Logs', 'fp-experiences'),
        ];

        /**
         * Filter the settings tabs.
         *
         * @param array<string, string> $tabs Array of tab slugs and labels.
         * @return array<string, string> Filtered tabs.
         */
        return apply_filters('fp_exp_settings_tabs', $tabs);
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
                'slug' => 'resync-roles',
                'label' => esc_html__('Resynchronise FP roles', 'fp-experiences'),
                'description' => esc_html__('Restore the FP Experiences capabilities for administrators and custom roles.', 'fp-experiences'),
                'button' => esc_html__('Run role sync', 'fp-experiences'),
            ],
            [
                'slug' => 'fix-corrupted-arrays',
                'label' => esc_html__('Ripara dati corrotti', 'fp-experiences'),
                'description' => esc_html__('Pulisce i campi array corrotti (highlights, inclusions, exclusions, what_to_bring, notes) rimuovendo le stringhe "Array" non valide.', 'fp-experiences'),
                'button' => esc_html__('Ripara dati', 'fp-experiences'),
            ],
            [
                'slug' => 'cleanup-duplicate-page-ids',
                'label' => esc_html__('Pulisci Page ID duplicati', 'fp-experiences'),
                'description' => esc_html__('Rimuove i _fp_exp_page_id dalle esperienze che condividono lo stesso page_id. Questo garantisce che ogni esperienza abbia un link univoco nella lista.', 'fp-experiences'),
                'button' => esc_html__('Pulisci duplicati', 'fp-experiences'),
            ],
            [
                'slug' => 'rebuild-availability-meta',
                'label' => esc_html__('Ricostruisci Availability Meta', 'fp-experiences'),
                'description' => esc_html__('Ricostruisce _fp_exp_availability per tutte le esperienze usando i meta esistenti (_fp_capacity_slot, _fp_lead_time_hours, ecc). Utile dopo import CSV o per sistemare availability incomplete.', 'fp-experiences'),
                'button' => esc_html__('Ricostruisci meta', 'fp-experiences'),
            ],
            [
                'slug' => 'repair-slot-capacities',
                'label' => esc_html__('Ripara Capacity Slot', 'fp-experiences'),
                'description' => esc_html__('Aggiorna la capacity di tutti gli slot esistenti con capacity=0, usando la configurazione corrente dall\'esperienza. Utile per slot creati prima dei fix recenti.', 'fp-experiences'),
                'button' => esc_html__('Ripara slot', 'fp-experiences'),
            ],
            [
                'slug' => 'cleanup-old-slots',
                'label' => esc_html__('Pulisci Slot Vecchi', 'fp-experiences'),
                'description' => esc_html__('Cancella slot passati (oltre 30 giorni) che non hanno prenotazioni. Aiuta a mantenere il database pulito.', 'fp-experiences'),
                'button' => esc_html__('Pulisci slot', 'fp-experiences'),
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
                'slug' => 'backup-branding',
                'label' => esc_html__('Backup impostazioni branding', 'fp-experiences'),
                'description' => esc_html__('Crea un backup delle impostazioni di branding (colori, temi) per preservarle durante aggiornamenti o reinstallazioni.', 'fp-experiences'),
                'button' => esc_html__('Crea backup', 'fp-experiences'),
            ],
            [
                'slug' => 'restore-branding',
                'label' => esc_html__('Ripristina impostazioni branding', 'fp-experiences'),
                'description' => esc_html__('Ripristina le impostazioni di branding da un backup precedente.', 'fp-experiences'),
                'button' => esc_html__('Ripristina backup', 'fp-experiences'),
            ],
            [
                'slug' => 'clear-cache',
                'label' => esc_html__('Pulisci cache e log', 'fp-experiences'),
                'description' => esc_html__('Elimina i transient del plugin e svuota il buffer dei log interni.', 'fp-experiences'),
                'button' => esc_html__('Pulisci cache', 'fp-experiences'),
            ],
            [
                'slug' => 'recreate-virtual-product',
                'label' => esc_html__('Ricrea prodotto virtuale WooCommerce', 'fp-experiences'),
                'description' => esc_html__('Ricrea il prodotto virtuale WooCommerce necessario per il checkout. Usa questo se il checkout restituisce errori di "configurazione mancante".', 'fp-experiences'),
                'button' => esc_html__('Ricrea prodotto', 'fp-experiences'),
            ],
            [
                'slug' => 'fix-virtual-product-quantity',
                'label' => esc_html__('Fix quantità prodotto virtuale', 'fp-experiences'),
                'description' => esc_html__('Disabilita "Venduto singolarmente" sul prodotto virtuale per permettere quantità multiple (numero di persone). Usa questo se il checkout mostra sempre "Quantità: 1".', 'fp-experiences'),
                'button' => esc_html__('Fix quantità', 'fp-experiences'),
            ],
            [
                'slug' => 'fix-experience-prices',
                'label' => esc_html__('Fix prezzi esperienze', 'fp-experiences'),
                'description' => esc_html__('Imposta automaticamente _fp_price per tutte le esperienze prendendo il prezzo dal primo ticket type. Usa questo se il checkout mostra "Prezzo: 0€".', 'fp-experiences'),
                'button' => esc_html__('Fix prezzi', 'fp-experiences'),
            ],
            [
                'slug' => 'migrate-reservations',
                'label' => esc_html__('Migra prenotazioni da ordini WC', 'fp-experiences'),
                'description' => esc_html__('Crea record nella tabella prenotazioni leggendo i meta degli ordini WooCommerce esistenti. Utile se gli ordini hanno dati esperienza ma la tabella prenotazioni è vuota.', 'fp-experiences'),
                'button' => esc_html__('Migra prenotazioni', 'fp-experiences'),
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
        $option = $args['option'] ?? '';
        if (! $option) {
            return;
        }

        $value = $this->getOptions()->get($option, '');
        
        // Use Field Renderer Strategy
        echo $this->render_form_field(
            name: $option,
            type: 'email',
            label: $args['label'] ?? '',
            value: $value,
            options: [
                'description' => $args['description'] ?? null,
                'attributes' => ['class' => 'regular-text'],
            ]
        );
    }

    public function render_email_nested_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        $settings = $this->getOptions()->get('fp_exp_emails', []);
        $settings = is_array($settings) ? $settings : [];

        // retrocompat: fallback a opzioni legacy
        if (! isset($settings['sender']['structure'])) {
            $legacy = sanitize_email((string) $this->getOptions()->get('fp_exp_structure_email', ''));
            if ($legacy) {
                $settings['sender']['structure'] = $legacy;
            }
        }
        if (! isset($settings['sender']['webmaster'])) {
            $legacy = sanitize_email((string) $this->getOptions()->get('fp_exp_webmaster_email', ''));
            if ($legacy) {
                $settings['sender']['webmaster'] = $legacy;
            }
        }

        $ref = $settings;
        foreach ($path as $segment) {
            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = $ref[$segment];
        }

        $value = '';
        // recupero valore finale se path punta a chiave scalare
        $cursor = $settings;
        foreach ($path as $segment) {
            if (! isset($cursor[$segment])) {
                $cursor = null;
                break;
            }
            $cursor = $cursor[$segment];
        }
        if (is_string($cursor)) {
            $value = $cursor;
        }

        $name = 'fp_exp_emails';
        foreach ($path as $segment) {
            $name .= '[' . esc_attr($segment) . ']';
        }

        echo '<input type="email" class="regular-text" name="' . $name . '" value="' . esc_attr((string) $value) . '" />';
        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    public function render_email_addresses_help(): void
    {
        echo '<p>' . esc_html__('Define the default sender and additional recipients for transactional emails.', 'fp-experiences') . '</p>';
    }

    public function render_email_branding_help(): void
    {
        echo '<p>' . esc_html__('Personalizza intestazione e footer delle email transazionali con logo e messaggi di cortesia.', 'fp-experiences') . '</p>';
    }

    /**
     * Render email previews section with all email templates grouped by category.
     */
    public function render_email_previews_section(): void
    {
        echo '<p>' . esc_html__('Panoramica di tutte le email inviate dal plugin, raggruppate per flusso. Clicca su ogni tipo per espandere l\'anteprima.', 'fp-experiences') . '</p>';

        if (! defined('FP_EXP_PLUGIN_DIR')) {
            echo '<p class="fp-exp-email-previews__error">';
            echo esc_html__('Errore: FP_EXP_PLUGIN_DIR non definita. Le anteprime non possono essere caricate.', 'fp-experiences');
            echo '</p>';
            return;
        }

        $emails = null;
        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel !== null) {
                $emails = $kernel->container()->make(Emails::class);
            }
        } catch (\Throwable $e) {
            // fall through
        }
        if ($emails === null) {
            $options = $this->getOptions();
            $mailer = new \FP_Exp\Booking\Email\Mailer($options);
            $emails = new Emails(
                $options,
                new \FP_Exp\Booking\Email\Senders\CustomerEmailSender($mailer),
                new \FP_Exp\Booking\Email\Senders\StaffEmailSender($mailer)
            );
        }

        $groups = $this->get_email_preview_groups();
        $languages = [
            EmailTranslator::LANGUAGE_IT => esc_html__('Italiano', 'fp-experiences'),
            EmailTranslator::LANGUAGE_EN => esc_html__('English', 'fp-experiences'),
        ];

        echo '<div class="fp-exp-email-previews">';

        foreach ($groups as $group) {
            echo '<div class="fp-exp-email-previews__group">';
            echo '<h3 class="fp-exp-email-previews__group-title">';
            echo '<span class="dashicons ' . esc_attr($group['icon']) . '"></span> ';
            echo esc_html($group['title']);
            echo '</h3>';

            foreach ($group['templates'] as $tpl) {
                $slug    = $tpl['slug'];
                $label   = $tpl['label'];
                $trigger = $tpl['trigger'];
                $dest    = $tpl['dest'];
                $is_gift = 'gift-voucher' === $slug;

                echo '<details class="fp-exp-email-previews__item">';
                echo '<summary class="fp-exp-email-previews__summary">';
                echo '<span class="fp-exp-email-previews__summary-text">' . esc_html($label) . '</span>';
                echo '<span class="fp-exp-email-previews__dest">' . esc_html($dest) . '</span>';
                echo '</summary>';

                echo '<div class="fp-exp-email-previews__trigger">';
                echo '<span class="dashicons dashicons-arrow-right-alt"></span> ';
                echo esc_html($trigger);
                echo '</div>';

                echo '<div class="fp-exp-email-previews__body">';

                $has_previews = false;

                if ($is_gift) {
                    $this->render_gift_voucher_preview($emails, $languages, $has_previews);
                } else {
                    foreach ($languages as $code => $language_label) {
                        $preview = $emails->render_preview($slug, $code);

                        if ('' === trim($preview)) {
                            continue;
                        }

                        $has_previews = true;
                        echo '<div class="fp-exp-email-previews__preview" data-language="' . esc_attr($code) . '">';
                        echo '<h4 class="fp-exp-email-previews__label">' . esc_html($language_label) . '</h4>';
                        echo '<div class="fp-exp-email-previews__frame">';
                        echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '</div>';
                        echo '</div>';
                    }
                }

                if (! $has_previews) {
                    $template_path = FP_EXP_PLUGIN_DIR . 'templates/emails/' . $slug . '.php';
                    $template_exists = file_exists($template_path);
                    echo '<p class="fp-exp-email-previews__empty">';
                    if (! $is_gift && ! $template_exists) {
                        echo esc_html__('Template non trovato: ', 'fp-experiences') . esc_html($template_path);
                    } else {
                        echo esc_html__('Nessuna anteprima disponibile per questo template.', 'fp-experiences');
                    }
                    echo '</p>';
                }

                echo '</div>'; // __body
                echo '</details>';
            }

            echo '</div>'; // __group
        }

        echo '</div>';
    }

    /**
     * @return list<array{title: string, icon: string, templates: list<array{slug: string, label: string, trigger: string, dest: string}>}>
     */
    private function get_email_preview_groups(): array
    {
        return [
            [
                'title' => __('Prenotazione diretta', 'fp-experiences'),
                'icon'  => 'dashicons-calendar-alt',
                'templates' => [
                    [
                        'slug'    => 'customer-confirmation',
                        'label'   => __('Conferma prenotazione', 'fp-experiences'),
                        'trigger' => __('Inviata al completamento del pagamento dell\'ordine WooCommerce.', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'customer-reminder',
                        'label'   => __('Promemoria pre-esperienza', 'fp-experiences'),
                        'trigger' => __('Inviata automaticamente X ore prima dell\'inizio dell\'esperienza (cron).', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'customer-post-experience',
                        'label'   => __('Follow-up post-esperienza', 'fp-experiences'),
                        'trigger' => __('Inviata automaticamente X ore dopo la fine dell\'esperienza (cron).', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'staff-notification',
                        'label'   => __('Notifica staff', 'fp-experiences'),
                        'trigger' => __('Inviata alla creazione, al pagamento confermato o alla cancellazione di una prenotazione.', 'fp-experiences'),
                        'dest'    => __('Struttura / Webmaster', 'fp-experiences'),
                    ],
                ],
            ],
            [
                'title' => __('Request To Book (RTB)', 'fp-experiences'),
                'icon'  => 'dashicons-clipboard',
                'templates' => [
                    [
                        'slug'    => 'rtb-request-received',
                        'label'   => __('Richiesta ricevuta', 'fp-experiences'),
                        'trigger' => __('Inviata quando il cliente invia una richiesta di prenotazione RTB.', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'rtb-approved',
                        'label'   => __('Richiesta approvata', 'fp-experiences'),
                        'trigger' => __('Inviata quando lo staff approva la richiesta con conferma diretta.', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'rtb-declined',
                        'label'   => __('Richiesta rifiutata', 'fp-experiences'),
                        'trigger' => __('Inviata quando lo staff rifiuta la richiesta RTB.', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                    [
                        'slug'    => 'rtb-payment-request',
                        'label'   => __('Richiesta di pagamento', 'fp-experiences'),
                        'trigger' => __('Inviata quando lo staff approva la richiesta con modalita "paga dopo".', 'fp-experiences'),
                        'dest'    => __('Cliente', 'fp-experiences'),
                    ],
                ],
            ],
            [
                'title' => __('Gift Voucher', 'fp-experiences'),
                'icon'  => 'dashicons-tickets-alt',
                'templates' => [
                    [
                        'slug'    => 'gift-voucher',
                        'label'   => __('Voucher regalo', 'fp-experiences'),
                        'trigger' => __('Inviata alla consegna del voucher, immediata o alla data programmata.', 'fp-experiences'),
                        'dest'    => __('Destinatario regalo', 'fp-experiences'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Render gift voucher preview using VoucherEmailTemplate class.
     *
     * @param array<string, string> $languages
     */
    private function render_gift_voucher_preview(Emails $emails, array $languages, bool &$has_previews): void
    {
        $template = new VoucherEmailTemplate();
        $demo_data = [
            'code'                 => 'GIFT-DEMO-2025',
            'experience_title'     => 'Degustazione in vigna',
            'experience_permalink' => 'https://example.com/experience/demo',
            'value'                => 120.0,
            'currency'             => 'EUR',
            'valid_until'          => strtotime('+6 months'),
        ];

        $body = $template->getBody($demo_data);

        if ('' === trim($body)) {
            return;
        }

        $has_previews = true;

        $branded = $emails->apply_branding($body, EmailTranslator::LANGUAGE_IT);
        echo '<div class="fp-exp-email-previews__preview" data-language="' . esc_attr(EmailTranslator::LANGUAGE_IT) . '">';
        echo '<h4 class="fp-exp-email-previews__label">' . esc_html__('Anteprima', 'fp-experiences') . '</h4>';
        echo '<div class="fp-exp-email-previews__frame">';
        echo $branded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        echo '</div>';
    }

    public function render_emails_branding_field(array $args): void
    {
        $emails = $this->getOptions()->get('fp_exp_emails', []);
        $emails = is_array($emails) ? $emails : [];
        $branding = isset($emails['branding']) && is_array($emails['branding']) ? $emails['branding'] : [];

        // retrocompat: pre-popolare da opzione legacy
        if (! $branding) {
            $legacy = $this->getOptions()->get('fp_exp_email_branding', []);
            $legacy = is_array($legacy) ? $legacy : [];
            $branding = $legacy;
        }

        $key = $args['key'] ?? '';
        if (! $key) {
            return;
        }

        $value = $branding[$key] ?? '';
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        $type = $args['type'] ?? 'text';

        $name = 'fp_exp_emails[branding][' . esc_attr($key) . ']';

        if ('textarea' === $type) {
            echo '<textarea name="' . $name . '" rows="4" class="large-text" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea((string) $value) . '</textarea>';
        } elseif ('number' === $type) {
            echo '<input type="number" class="small-text" name="' . $name . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" min="0" max="600" step="1" />';
        } else {
            echo '<input type="text" class="regular-text" name="' . $name . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" />';
        }

        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    public function render_email_recipients_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        $emails = $this->getOptions()->get('fp_exp_emails', []);
        $emails = is_array($emails) ? $emails : [];
        $cursor = $emails;
        foreach ($path as $segment) {
            if (! isset($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = $cursor[$segment];
        }

        $value = '';
        if (is_array($cursor)) {
            $value = implode(', ', array_filter(array_map('sanitize_email', $cursor)));
        } elseif (is_string($cursor)) {
            $value = $cursor;
        }

        $name = 'fp_exp_emails';
        foreach ($path as $segment) {
            $name .= '[' . esc_attr($segment) . ']';
        }

        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        echo '<input type="text" class="regular-text" name="' . $name . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" />';
        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    public function render_email_types_help(): void
    {
        echo '<p>' . esc_html__('Abilita o disabilita i singoli invii automatici.', 'fp-experiences') . '</p>';
    }

    public function render_email_schedule_help(): void
    {
        echo '<p>' . esc_html__('Configura gli orari di invio di promemoria e follow-up rispetto all\'esperienza.', 'fp-experiences') . '</p>';
    }

    public function render_email_subjects_help(): void
    {
        echo '<p>' . esc_html__('Imposta soggetti personalizzati. Lascia vuoto per usare i testi predefiniti.', 'fp-experiences') . '</p>';
    }

    public function render_email_toggle_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        echo $this->render_field_inline(
            name: implode('_', $path),
            type: 'nested_toggle',
            options: [
                'path' => $path,
                'base_type' => 'toggle',
                'option_name' => 'fp_exp_emails',
                'description' => $args['description'] ?? null,
                'label_text' => esc_html__('Abilitato', 'fp-experiences'),
            ]
        );
    }

    public function render_email_number_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        $min = isset($args['min']) ? (int) $args['min'] : 0;
        $max = isset($args['max']) ? (int) $args['max'] : 240;
        $step = isset($args['step']) ? (int) $args['step'] : 1;
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';

        echo $this->render_field_inline(
            name: implode('_', $path),
            type: 'nested_number',
            options: [
                'path' => $path,
                'base_type' => 'number',
                'option_name' => 'fp_exp_emails',
                'description' => $args['description'] ?? null,
                'attributes' => ['class' => 'small-text'],
                'min' => $min,
                'max' => $max,
                'step' => $step,
                'placeholder' => $placeholder,
            ]
        );
    }

    public function render_email_subject_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        echo $this->render_field_inline(
            name: implode('_', $path),
            type: 'nested_text',
            options: [
                'path' => $path,
                'base_type' => 'text',
                'option_name' => 'fp_exp_emails',
                'description' => $args['description'] ?? null,
                'attributes' => ['class' => 'regular-text'],
            ]
        );
        echo '<p class="description">' . esc_html__('Puoi usare segnaposto come {experience_title}.', 'fp-experiences') . '</p>';
    }

    public function render_email_provider_help(): void
    {
        echo '<p>' . esc_html__('Scegli come inviare le email del plugin: tramite WordPress (wp_mail), un server SMTP personalizzato o Brevo API.', 'fp-experiences') . '</p>';
    }

    public function render_email_provider_field(): void
    {
        $settings = $this->getOptions()->get('fp_exp_emails', []);
        $settings = is_array($settings) ? $settings : [];
        $current = $settings['provider'] ?? 'wordpress';

        $choices = [
            'wordpress' => esc_html__('WordPress (wp_mail)', 'fp-experiences'),
            'smtp'      => esc_html__('SMTP personalizzato', 'fp-experiences'),
            'brevo'     => esc_html__('Brevo (API)', 'fp-experiences'),
        ];

        echo '<select name="fp_exp_emails[provider]" id="fp_exp_emails_provider">';
        foreach ($choices as $value => $label) {
            $selected = selected($current, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Se scegli "SMTP personalizzato", compila i campi sottostanti. Se scegli "Brevo", configura la API key nel tab Brevo.', 'fp-experiences') . '</p>';
    }

    public function render_email_text_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        $settings = $this->getOptions()->get('fp_exp_emails', []);
        $settings = is_array($settings) ? $settings : [];

        $cursor = $settings;
        foreach ($path as $segment) {
            if (! isset($cursor[$segment])) {
                $cursor = null;
                break;
            }
            $cursor = $cursor[$segment];
        }
        $value = is_string($cursor) ? $cursor : '';

        $name = 'fp_exp_emails';
        foreach ($path as $segment) {
            $name .= '[' . esc_attr($segment) . ']';
        }

        $placeholder = $args['placeholder'] ?? '';
        $css_class = $args['class'] ?? '';

        echo '<input type="text" class="regular-text ' . esc_attr($css_class) . '" name="' . $name . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" />';
        if (! empty($args['description'])) {
            echo '<p class="description">' . esc_html((string) $args['description']) . '</p>';
        }
    }

    public function render_email_password_field(array $args): void
    {
        $path = isset($args['path']) && is_array($args['path']) ? $args['path'] : [];
        if (! $path) {
            return;
        }

        $settings = $this->getOptions()->get('fp_exp_emails', []);
        $settings = is_array($settings) ? $settings : [];

        $cursor = $settings;
        foreach ($path as $segment) {
            if (! isset($cursor[$segment])) {
                $cursor = null;
                break;
            }
            $cursor = $cursor[$segment];
        }
        $value = is_string($cursor) ? $cursor : '';

        $name = 'fp_exp_emails';
        foreach ($path as $segment) {
            $name .= '[' . esc_attr($segment) . ']';
        }

        $css_class = $args['class'] ?? '';

        echo '<input type="password" class="regular-text ' . esc_attr($css_class) . '" name="' . $name . '" value="' . esc_attr($value) . '" autocomplete="new-password" />';
    }

    public function render_email_smtp_encryption_field(array $args): void
    {
        $settings = $this->getOptions()->get('fp_exp_emails', []);
        $settings = is_array($settings) ? $settings : [];
        $current = $settings['smtp']['encryption'] ?? 'tls';
        $css_class = $args['class'] ?? '';

        $choices = [
            'tls'  => 'TLS',
            'ssl'  => 'SSL',
            'none' => esc_html__('Nessuna', 'fp-experiences'),
        ];

        echo '<select name="fp_exp_emails[smtp][encryption]" class="' . esc_attr($css_class) . '">';
        foreach ($choices as $value => $label) {
            $selected = selected($current, $value, false);
            echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
    }

    public function render_email_test_button(): void
    {
        $nonce = wp_create_nonce('fp_exp_test_email');
        echo '<div class="fp-exp-test-email-wrap">';
        echo '<button type="button" class="button button-secondary" id="fp-exp-test-email-btn" data-nonce="' . esc_attr($nonce) . '">';
        echo esc_html__('Invia email di test', 'fp-experiences');
        echo '</button>';
        echo '<span class="spinner" id="fp-exp-test-email-spinner"></span>';
        echo '</div>';
        echo '<div class="fp-exp-test-email-result" id="fp-exp-test-email-result"></div>';
        echo '<p class="description">' . esc_html__('Invia una email di prova all\'indirizzo del tuo account per verificare che il provider configurato funzioni correttamente. Salva le impostazioni prima di testare.', 'fp-experiences') . '</p>';
    }

    public function render_branding_help(): void
    {
        echo '<p>' . esc_html__('Adjust the colors and styling tokens used by FP Experiences widgets to match your branding.', 'fp-experiences') . '</p>';
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
        $requests_url = admin_url('admin.php?page=fp_exp_requests');
        $link         = '<a href="' . esc_url($requests_url) . '">' . esc_html__('Richieste', 'fp-experiences') . '</a>';
        echo '<p><strong>' . esc_html__('Conferma manuale prima dell\'addebito', 'fp-experiences') . '</strong> &mdash; ';
        echo sprintf(
            /* translators: %s: link to Requests page. */
            esc_html__('Con la modalità "Conferma manuale, poi richiedi pagamento" potete approvare o rifiutare ogni richiesta dalla pagina %s prima che il cliente paghi. L\'addebito avviene solo dopo la vostra approvazione e il completamento del pagamento da parte del cliente. Potete così non accettare richieste in momenti particolari (es. sovraccarico, chiusure).', 'fp-experiences'),
            $link
        );
        echo '</p>';
    }

    public function render_brevo_help(): void
    {
        echo '<p>' . esc_html__('Connect your Brevo account to deliver transactional emails and sync contacts with marketing attributes and tags.', 'fp-experiences') . '</p>';

        $settings = $this->getOptions()->get('fp_exp_brevo', []);
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
        $defaults = Theme::default_palette();

        return [
            [
                'key' => 'preset',
                'type' => 'fixed',
                'label' => esc_html__('Color palette', 'fp-experiences'),
            ],
            [
                'key' => 'mode',
                'type' => 'select',
                'label' => esc_html__('Color mode', 'fp-experiences'),
                'options' => [
                    'light' => esc_html__('Light', 'fp-experiences'),
                    'dark' => esc_html__('Dark', 'fp-experiences'),
                    'auto' => esc_html__('Match system preference', 'fp-experiences'),
                ],
                'default' => $defaults['mode'] ?? 'light',
                'description' => esc_html__('Choose how widgets adapt to dark themes.', 'fp-experiences'),
            ],
            [
                'key' => 'primary',
                'type' => 'color',
                'label' => esc_html__('Primary color', 'fp-experiences'),
                'default' => $defaults['primary'] ?? '#0B6EFD',
                'description' => esc_html__('Main call-to-action buttons and highlights.', 'fp-experiences'),
            ],
            [
                'key' => 'secondary',
                'type' => 'color',
                'label' => esc_html__('Secondary color', 'fp-experiences'),
                'default' => $defaults['secondary'] ?? '#1857C4',
                'description' => esc_html__('Secondary buttons and hover states.', 'fp-experiences'),
            ],
            [
                'key' => 'accent',
                'type' => 'color',
                'label' => esc_html__('Accent color', 'fp-experiences'),
                'default' => $defaults['accent'] ?? '#00A37A',
                'description' => esc_html__('Used for tags, badges, and decorative elements.', 'fp-experiences'),
            ],
            [
                'key' => 'section_icon_background',
                'type' => 'color',
                'label' => esc_html__('Section icon background', 'fp-experiences'),
                'default' => $defaults['section_icon_background'] ?? '#0B6EFD',
                'description' => esc_html__('Background fill used behind section icons.', 'fp-experiences'),
            ],
            [
                'key' => 'hero_card_gradient_start',
                'type' => 'color',
                'label' => esc_html__('Hero card gradient start', 'fp-experiences'),
                'default' => $defaults['hero_card_gradient_start'] ?? '#8B1E3F',
                'description' => esc_html__('Starting color of the hero card background gradient.', 'fp-experiences'),
            ],
            [
                'key' => 'hero_card_gradient_end',
                'type' => 'color',
                'label' => esc_html__('Hero card gradient end', 'fp-experiences'),
                'default' => $defaults['hero_card_gradient_end'] ?? '#0F172A',
                'description' => esc_html__('Ending color of the hero card background gradient.', 'fp-experiences'),
            ],
            [
                'key' => 'hero_card_gradient_opacity_start',
                'type' => 'number',
                'label' => esc_html__('Hero card gradient start opacity', 'fp-experiences'),
                'default' => $defaults['hero_card_gradient_opacity_start'] ?? 0.08,
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'description' => esc_html__('Opacity of the starting color (0-1).', 'fp-experiences'),
            ],
            [
                'key' => 'hero_card_gradient_opacity_end',
                'type' => 'number',
                'label' => esc_html__('Hero card gradient end opacity', 'fp-experiences'),
                'default' => $defaults['hero_card_gradient_opacity_end'] ?? 0.02,
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'description' => esc_html__('Opacity of the ending color (0-1).', 'fp-experiences'),
            ],
            [
                'key' => 'section_icon_color',
                'type' => 'color',
                'label' => esc_html__('Section icon color', 'fp-experiences'),
                'default' => $defaults['section_icon_color'] ?? '#FFFFFF',
                'description' => esc_html__('Icon color displayed within section icon circles.', 'fp-experiences'),
            ],
            [
                'key' => 'background',
                'type' => 'color',
                'label' => esc_html__('Background color', 'fp-experiences'),
                'default' => $defaults['background'] ?? '#F7F8FA',
                'description' => esc_html__('Default page background.', 'fp-experiences'),
            ],
            [
                'key' => 'surface',
                'type' => 'color',
                'label' => esc_html__('Surface color', 'fp-experiences'),
                'default' => $defaults['surface'] ?? '#FFFFFF',
                'description' => esc_html__('Cards and widget panels.', 'fp-experiences'),
            ],
            [
                'key' => 'text',
                'type' => 'color',
                'label' => esc_html__('Text color', 'fp-experiences'),
                'default' => $defaults['text'] ?? '#0F172A',
                'description' => esc_html__('Primary body text.', 'fp-experiences'),
            ],
            [
                'key' => 'muted',
                'type' => 'color',
                'label' => esc_html__('Muted text color', 'fp-experiences'),
                'default' => $defaults['muted'] ?? '#64748B',
                'description' => esc_html__('Captions and subtle labels.', 'fp-experiences'),
            ],
            [
                'key' => 'success',
                'type' => 'color',
                'label' => esc_html__('Success color', 'fp-experiences'),
                'default' => $defaults['success'] ?? '#1B998B',
                'description' => esc_html__('Confirmation states and success badges.', 'fp-experiences'),
            ],
            [
                'key' => 'warning',
                'type' => 'color',
                'label' => esc_html__('Warning color', 'fp-experiences'),
                'default' => $defaults['warning'] ?? '#F4A261',
                'description' => esc_html__('Warnings and pending notices.', 'fp-experiences'),
            ],
            [
                'key' => 'danger',
                'type' => 'color',
                'label' => esc_html__('Danger color', 'fp-experiences'),
                'default' => $defaults['danger'] ?? '#C44536',
                'description' => esc_html__('Errors and destructive actions.', 'fp-experiences'),
            ],
            [
                'key' => 'radius',
                'type' => 'text',
                'label' => esc_html__('Border radius', 'fp-experiences'),
                'default' => $defaults['radius'] ?? '16px',
                'placeholder' => '16px',
                'description' => esc_html__('Rounding applied to buttons and cards.', 'fp-experiences'),
            ],
            [
                'key' => 'shadow',
                'type' => 'text',
                'label' => esc_html__('Shadow', 'fp-experiences'),
                'default' => $defaults['shadow'] ?? '0 8px 24px rgba(15,23,42,0.08)',
                'placeholder' => '0 8px 24px rgba(15,23,42,0.08)',
                'description' => esc_html__('Box shadow used on floating elements.', 'fp-experiences'),
            ],
            [
                'key' => 'gap',
                'type' => 'text',
                'label' => esc_html__('Layout gap', 'fp-experiences'),
                'default' => $defaults['gap'] ?? '24px',
                'placeholder' => '24px',
                'description' => esc_html__('Default vertical spacing between blocks.', 'fp-experiences'),
            ],
            [
                'key' => 'font',
                'type' => 'text',
                'label' => esc_html__('Font family', 'fp-experiences'),
                'default' => $defaults['font'] ?? '',
                'placeholder' => 'Inter, sans-serif',
                'description' => esc_html__('Custom font stack for widgets. Leave empty to inherit site fonts.', 'fp-experiences'),
            ],
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
            [
                'key' => 'experience_badges',
                'type' => 'experience_badges',
                'label' => esc_html__('Experience badges', 'fp-experiences'),
                'description' => esc_html__('Customize the preset badge labels and descriptions or add new options for editors to assign.', 'fp-experiences'),
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
                    'pay_later' => esc_html__('Conferma manuale, poi richiedi pagamento', 'fp-experiences'),
                ],
                'description' => esc_html__('Con "Conferma manuale, poi richiedi pagamento" le richieste restano in attesa nella pagina Richieste: voi approvate o rifiutate. Solo dopo l\'approvazione il cliente riceve il link di pagamento; l\'addebito avviene al pagamento. Potete rifiutare richieste quando preferite.', 'fp-experiences'),
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
            'request' => esc_html__('Richiesta ricevuta (cliente)', 'fp-experiences'),
            'approved' => esc_html__('Richiesta approvata', 'fp-experiences'),
            'declined' => esc_html__('Richiesta rifiutata', 'fp-experiences'),
            'payment' => esc_html__('Pagamento richiesto', 'fp-experiences'),
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
                'min' => 1,
                'description' => esc_html__('Optional: contacts will be subscribed to this list on sync.', 'fp-experiences'),
            ],
            [
                'key' => 'lists[it]',
                'label' => esc_html__('Italian list ID', 'fp-experiences'),
                'type' => 'number',
                'min' => 1,
                'description' => esc_html__('Used when the reservation prefix starts with “ita”. Falls back to the default list when empty.', 'fp-experiences'),
            ],
            [
                'key' => 'lists[en]',
                'label' => esc_html__('English list ID', 'fp-experiences'),
                'type' => 'number',
                'min' => 1,
                'description' => esc_html__('Used for all other reservations. Falls back to the default list when empty.', 'fp-experiences'),
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
        $branding = $this->getOptions()->get('fp_exp_branding', []);
        $branding = is_array($branding) ? $branding : [];
        $key = $field['key'];
        $value = array_key_exists($key, $branding)
            ? $branding[$key]
            : ($field['default'] ?? '');

        if ('preset' === $key) {
            $value = Theme::default_preset();
        }

        if ('fixed' === ($field['type'] ?? '')) {
            echo '<input type="hidden" name="fp_exp_branding[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" />';
        } elseif ('select' === $field['type']) {
            $select_class = $field['input_class'] ?? '';
            $class_attribute = $select_class ? ' class="' . esc_attr($select_class) . '"' : '';
            echo '<select name="fp_exp_branding[' . esc_attr($key) . ']"' . $class_attribute . '>';
            foreach ($field['options'] as $option_key => $label) {
                $selected = ((string) $value === (string) $option_key) ? 'selected' : '';
                echo '<option value="' . esc_attr((string) $option_key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } else {
            $input_type = 'color' === $field['type'] ? 'color' : ('number' === ($field['type'] ?? '') ? 'number' : 'text');
            $default_class = 'color' === $input_type ? 'fp-exp-settings__color-field' : ('number' === $input_type ? 'small-text' : 'regular-text');
            $input_class = $field['input_class'] ?? $default_class;
            $placeholder = $field['placeholder'] ?? '';
            $default_value = isset($field['default']) ? ' data-default="' . esc_attr((string) $field['default']) . '"' : '';
            
            $attributes = 'type="' . esc_attr($input_type) . '" class="' . esc_attr($input_class) . '" name="fp_exp_branding[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr((string) $placeholder) . '"' . $default_value;
            
            // Add min, max, step attributes for number inputs
            if ('number' === $input_type) {
                if (isset($field['min'])) {
                    $attributes .= ' min="' . esc_attr((string) $field['min']) . '"';
                }
                if (isset($field['max'])) {
                    $attributes .= ' max="' . esc_attr((string) $field['max']) . '"';
                }
                if (isset($field['step'])) {
                    $attributes .= ' step="' . esc_attr((string) $field['step']) . '"';
                }
            }
            
            echo '<input ' . $attributes . ' />';
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    public function render_listing_field(array $field): void
    {
        $settings = $this->getOptions()->get('fp_exp_listing', []);
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
        } elseif ('experience_badges' === $field['type']) {
            $badge_settings = is_array($value) ? $value : [];
            $badge_overrides = isset($badge_settings['overrides']) && is_array($badge_settings['overrides'])
                ? $badge_settings['overrides']
                : [];
            $badge_custom = isset($badge_settings['custom']) && is_array($badge_settings['custom'])
                ? $badge_settings['custom']
                : [];

            $default_badges = Helpers::default_experience_badge_choices();

            echo '<div class="fp-exp-badge-settings">';

            if (! empty($default_badges)) {
                echo '<div class="fp-exp-badge-settings__group">';
                echo '<h4 class="fp-exp-badge-settings__title">' . esc_html__('Predefined badges', 'fp-experiences') . '</h4>';
                echo '<div class="fp-exp-badge-settings__rows">';
                foreach ($default_badges as $default_badge) {
                    if (! is_array($default_badge)) {
                        continue;
                    }

                    $badge_id = isset($default_badge['id']) ? sanitize_key((string) $default_badge['id']) : '';
                    if ('' === $badge_id) {
                        continue;
                    }

                    $default_label = isset($default_badge['label']) ? (string) $default_badge['label'] : '';
                    $default_description = isset($default_badge['description']) ? (string) $default_badge['description'] : '';

                    $override = isset($badge_overrides[$badge_id]) && is_array($badge_overrides[$badge_id])
                        ? $badge_overrides[$badge_id]
                        : [];

                    $label_value = isset($override['label']) && '' !== (string) $override['label']
                        ? (string) $override['label']
                        : $default_label;

                    $description_value = array_key_exists('description', $override)
                        ? (string) $override['description']
                        : $default_description;

                    $id_text = sprintf(
                        /* translators: %s: badge identifier slug. */
                        esc_html__('ID: %s', 'fp-experiences'),
                        esc_html($badge_id)
                    );

                    echo '<div class="fp-exp-repeater-row fp-exp-badge-settings__row">';
                    echo '<div class="fp-exp-repeater-row__fields">';
                    echo '<label>';
                    echo '<span class="fp-exp-field__label">' . esc_html__('Name', 'fp-experiences') . ' ';
                    echo '<span class="fp-exp-field__description">' . $id_text . '</span>';
                    echo '</span>';
                    echo '<input type="text" name="fp_exp_listing[experience_badges][overrides][' . esc_attr($badge_id) . '][label]" value="' . esc_attr($label_value) . '" />';
                    echo '</label>';
                    echo '<label>';
                    echo '<span class="fp-exp-field__label">' . esc_html__('Short description', 'fp-experiences') . '</span>';
                    echo '<input type="text" name="fp_exp_listing[experience_badges][overrides][' . esc_attr($badge_id) . '][description]" value="' . esc_attr($description_value) . '" placeholder="' . esc_attr__('Optional summary shown under the badge name.', 'fp-experiences') . '" />';
                    echo '</label>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }

            $custom_badges = [];
            if (! empty($badge_custom)) {
                foreach ($badge_custom as $custom_badge) {
                    if (is_array($custom_badge)) {
                        $custom_badges[] = $custom_badge;
                    }
                }
            }

            $next_index = count($custom_badges);

            echo '<div class="fp-exp-badge-settings__group">';
            echo '<h4 class="fp-exp-badge-settings__title">' . esc_html__('Custom badges', 'fp-experiences') . '</h4>';
            echo '<div class="fp-exp-repeater" data-repeater="experience_badges_custom" data-repeater-next-index="' . esc_attr((string) $next_index) . '">';
            echo '<div class="fp-exp-repeater__items">';
            foreach ($custom_badges as $index => $custom_badge) {
                $this->render_experience_badge_custom_row((string) $index, $custom_badge, false);
            }
            echo '</div>';
            echo '<p class="fp-exp-repeater__actions"><button type="button" class="button" data-repeater-add>' . esc_html__('Add badge', 'fp-experiences') . '</button></p>';
            echo '<template data-repeater-template>';
            $this->render_experience_badge_custom_row('__INDEX__', [
                'id' => '',
                'label' => '',
                'description' => '',
            ], true);
            echo '</template>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        } elseif ('toggle' === $field['type']) {
            // Use Field Renderer Strategy for consistent toggle styling
            // Handle both boolean and 'yes'/'no' values
            $toggle_value = in_array($value, [true, 1, '1', 'yes', 'true'], true) ? 'yes' : 'no';
            $field_name = 'fp_exp_listing[' . esc_attr($key) . ']';
            
            $field_def = new FieldDefinition(
                name: $field_name,
                type: 'toggle',
                label: '', // Empty label - WordPress shows it via add_settings_field
                value: $toggle_value,
                options: [
                    'toggle_class' => 'fp-exp-settings__toggle',
                    'add_hidden_input' => true, // Ensure value is sent even when unchecked
                    'label_text' => esc_html__('Enabled', 'fp-experiences'),
                ],
                description: null,
                required: false,
                attributes: []
            );

            $renderer = $this->getFieldRendererFactory()->getRenderer('toggle');
            echo $renderer->render($field_def, '');
        }

        if (! empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    private function render_experience_badge_custom_row(string $index, array $badge, bool $is_template = false): void
    {
        $name_prefix = 'fp_exp_listing[experience_badges][custom][' . $index . ']';
        $id_name = $is_template
            ? 'fp_exp_listing[experience_badges][custom][__INDEX__][id]'
            : $name_prefix . '[id]';
        $label_name = $is_template
            ? 'fp_exp_listing[experience_badges][custom][__INDEX__][label]'
            : $name_prefix . '[label]';
        $description_name = $is_template
            ? 'fp_exp_listing[experience_badges][custom][__INDEX__][description]'
            : $name_prefix . '[description]';

        $badge_id = isset($badge['id']) ? (string) $badge['id'] : '';
        $badge_label = isset($badge['label']) ? (string) $badge['label'] : '';
        $badge_description = isset($badge['description']) ? (string) $badge['description'] : '';

        ?>
        <div class="fp-exp-repeater-row" data-repeater-item draggable="true">
            <div class="fp-exp-repeater-row__fields">
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('ID', 'fp-experiences'); ?></span>
                    <input
                        type="text"
                        <?php echo $this->experience_badge_field_attribute($id_name, $is_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        value="<?php echo esc_attr($badge_id); ?>"
                        placeholder="<?php echo esc_attr__('e.g. degustazione-serale', 'fp-experiences'); ?>"
                    />
                    <span class="fp-exp-field__description"><?php esc_html_e('Lowercase letters, numbers, and dashes only.', 'fp-experiences'); ?></span>
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Name', 'fp-experiences'); ?></span>
                    <input
                        type="text"
                        <?php echo $this->experience_badge_field_attribute($label_name, $is_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        value="<?php echo esc_attr($badge_label); ?>"
                        placeholder="<?php echo esc_attr__('e.g. Degustazione serale', 'fp-experiences'); ?>"
                    />
                </label>
                <label>
                    <span class="fp-exp-field__label"><?php esc_html_e('Short description', 'fp-experiences'); ?></span>
                    <input
                        type="text"
                        <?php echo $this->experience_badge_field_attribute($description_name, $is_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        value="<?php echo esc_attr($badge_description); ?>"
                        placeholder="<?php echo esc_attr__('Optional summary shown under the badge name.', 'fp-experiences'); ?>"
                    />
                </label>
            </div>
            <p class="fp-exp-repeater-row__remove">
                <button type="button" class="button-link-delete" data-repeater-remove>&times;</button>
            </p>
        </div>
        <?php
    }

    private function experience_badge_field_attribute(string $name, bool $is_template): string
    {
        if ($is_template) {
            return 'data-name="' . esc_attr($name) . '"';
        }

        return 'name="' . esc_attr($name) . '"';
    }

    public function render_rtb_field(array $field): void
    {
        $settings = $this->getOptions()->get('fp_exp_rtb', []);
        $settings = is_array($settings) ? $settings : [];
        $key = $field['key'] ?? '';
        $type = $field['type'] ?? 'text';
        $description = $field['description'] ?? '';
        
        // Get nested value
        $value = $this->extract_nested_value($settings, $key);
        
        // Build field name for nested option
        $path = $this->parse_key_segments($key);
        if (empty($path)) {
            return;
        }
        
        $field_name = 'fp_exp_rtb';
        foreach ($path as $segment) {
            $field_name .= '[' . esc_attr($segment) . ']';
        }
        
        $field_id = 'fp_exp_rtb_' . implode('_', $path);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp render_rtb_field] key=' . $key . ', field_name=' . $field_name . ', value=' . print_r($value, true));
        }
        
        // Render based on type
        switch ($type) {
            case 'select':
                $options = $field['options'] ?? [];
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" class="regular-text">';
                foreach ($options as $opt_value => $opt_label) {
                    $selected = ($value === $opt_value) ? ' selected="selected"' : '';
                    echo '<option value="' . esc_attr($opt_value) . '"' . $selected . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'number':
                $min = $field['min'] ?? 0;
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr((string) $value) . '" min="' . esc_attr((string) $min) . '" class="small-text" />';
                break;
                
            case 'checkbox':
                // Hidden field to ensure value is always sent
                echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="0" />';
                $checked = ($value === 'yes' || $value === '1' || $value === true || $value === 1) ? ' checked="checked"' : '';
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="yes"' . $checked . ' />';
                break;
                
            default:
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr((string) $value) . '" class="regular-text" />';
        }
        
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Get HTML attributes for field type.
     * 
     * @return array<string, string>
     */
    private function get_field_attributes(string $type): array
    {
        return match($type) {
            'number' => ['class' => 'small-text'],
            'textarea' => ['class' => 'large-text', 'rows' => '4'],
            default => ['class' => 'regular-text'],
        };
    }

    public function render_tracking_field(array $field): void
    {
        $settings = $this->getOptions()->get('fp_exp_tracking', []);
        $settings = is_array($settings) ? $settings : [];
        $value = $this->extract_nested_value($settings, $field['key']);
        $path = $this->parse_key_segments($field['key'] ?? '');
        
        if (empty($path)) {
            return;
        }

        $type = $field['type'] ?? 'text';
        $base_type = match($type) {
            'checkbox' => 'toggle',
            'number' => 'number',
            default => 'text',
        };

        // Show status badge for key tracking fields
        $status_badge = '';
        if (in_array($field['key'], ['ga4[enabled]', 'meta_pixel[enabled]', 'google_ads[enabled]', 'clarity[enabled]'], true)) {
            $is_enabled = !empty($value);
            $status_class = $is_enabled ? 'active' : 'inactive';
            $status_text = $is_enabled ? esc_html__('Attivo', 'fp-experiences') : esc_html__('Non configurato', 'fp-experiences');
            $status_badge = '<span class="fp-exp-integration-status fp-exp-integration-status--' . esc_attr($status_class) . '">' . $status_text . '</span> ';
        }

        $field_html = $this->render_field_inline(
            name: implode('_', $path),
            type: 'nested_' . $base_type,
            options: [
                'path' => $path,
                'base_type' => $base_type,
                'option_name' => 'fp_exp_tracking',
                'description' => $field['description'] ?? null,
                'attributes' => array_merge(
                    $this->get_field_attributes($type),
                    $type === 'number' ? ['inputmode' => 'numeric', 'pattern' => '[0-9]*', 'step' => '1'] : []
                ),
                'min' => isset($field['min']) ? max(0, (int) $field['min']) : 0,
                'placeholder' => $field['placeholder'] ?? '',
            ]
        );

        echo $status_badge . $field_html;
    }

    public function render_brevo_field(array $field): void
    {
        $settings = $this->getOptions()->get('fp_exp_brevo', []);
        $settings = is_array($settings) ? $settings : [];
        $value = $this->extract_nested_value($settings, $field['key']);
        $path = $this->parse_key_segments($field['key'] ?? '');
        
        if (empty($path)) {
            return;
        }

        $type = $field['type'] ?? 'text';
        $base_type = match($type) {
            'checkbox' => 'toggle',
            'select' => 'select',
            'number' => 'number',
            'textarea' => 'textarea',
            default => 'text',
        };

        // Show status badge for Brevo enabled field
        $status_badge = '';
        if ($field['key'] === 'enabled') {
            $is_enabled = !empty($value);
            $has_api_key = !empty($settings['api_key'] ?? '');
            
            if ($is_enabled && $has_api_key) {
                $status_badge = '<span class="fp-exp-integration-status fp-exp-integration-status--active">' . esc_html__('Attivo', 'fp-experiences') . '</span> ';
            } elseif ($is_enabled && !$has_api_key) {
                $status_badge = '<span class="fp-exp-integration-status fp-exp-integration-status--warning">' . esc_html__('API key mancante', 'fp-experiences') . '</span> ';
            } else {
                $status_badge = '<span class="fp-exp-integration-status fp-exp-integration-status--inactive">' . esc_html__('Non configurato', 'fp-experiences') . '</span> ';
            }
        }

        $field_html = $this->render_field_inline(
            name: implode('_', $path),
            type: 'nested_' . $base_type,
            options: [
                'path' => $path,
                'base_type' => $base_type,
                'option_name' => 'fp_exp_brevo',
                'description' => $field['description'] ?? null,
                'attributes' => array_merge(
                    $this->get_field_attributes($type),
                    $type === 'number' ? ['inputmode' => 'numeric', 'pattern' => '[0-9]*', 'step' => '1'] : []
                ),
                'choices' => $field['options'] ?? [],
                'min' => isset($field['min']) ? max(0, (int) $field['min']) : 0,
                'placeholder' => $field['placeholder'] ?? '',
            ]
        );

        echo $status_badge . $field_html;
    }

    public function render_calendar_field(array $field): void
    {
        $settings = $this->get_calendar_settings();
        $value = $this->extract_nested_value($settings, $field['key']);
        $name = $this->build_input_name('fp_exp_google_calendar', $field['key']);

        // Show status badge for Google Calendar enabled
        if ($field['key'] === 'enabled') {
            $is_enabled = !empty($value);
            $has_credentials = !empty($settings['credentials_json'] ?? '');
            
            if ($is_enabled && $has_credentials) {
                echo '<span class="fp-exp-integration-status fp-exp-integration-status--active">' . esc_html__('Connesso', 'fp-experiences') . '</span> ';
            } elseif ($is_enabled && !$has_credentials) {
                echo '<span class="fp-exp-integration-status fp-exp-integration-status--warning">' . esc_html__('Credenziali mancanti', 'fp-experiences') . '</span> ';
            } else {
                echo '<span class="fp-exp-integration-status fp-exp-integration-status--inactive">' . esc_html__('Disabilitato', 'fp-experiences') . '</span> ';
            }
        }

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
        $branding = $this->getOptions()->get('fp_exp_branding', []);
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
            'data-default-primary' => esc_attr($palette['primary'] ?? '#0B6EFD'),
            'data-default-background' => esc_attr($palette['background'] ?? '#F7F8FA'),
            'data-default-text' => esc_attr($palette['text'] ?? '#0F172A'),
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
        $presets = Theme::presets();
        $fields = $this->get_branding_fields();
        $sanitised = [
            'preset' => Theme::default_preset(),
        ];

        if (! is_array($value)) {
            return $sanitised;
        }

        if (! empty($value['preset']) && isset($presets[$value['preset']])) {
            $sanitised['preset'] = sanitize_key((string) $value['preset']);
        }

        foreach ($fields as $field) {
            $key = $field['key'];

            if ('preset' === $key || ! array_key_exists($key, $value)) {
                continue;
            }

            $raw = $value[$key];
            $type = $field['type'] ?? 'text';

            if ('select' === $type) {
                $options = $field['options'] ?? [];
                $option_key = (string) $raw;

                if (! isset($options[$option_key])) {
                    continue;
                }

                if (isset($field['default']) && (string) $field['default'] === $option_key) {
                    continue;
                }

                $sanitised[$key] = sanitize_key($option_key);

                continue;
            }

            if ('color' === $type) {
                $color = sanitize_hex_color((string) $raw);

                if (! $color) {
                    continue;
                }

                if (isset($field['default']) && strtolower($color) === strtolower((string) $field['default'])) {
                    continue;
                }

                $sanitised[$key] = $color;

                continue;
            }

            if ('number' === $type) {
                $number = floatval($raw);
                $min = $field['min'] ?? null;
                $max = $field['max'] ?? null;

                if ($min !== null && $number < $min) {
                    $number = $min;
                }

                if ($max !== null && $number > $max) {
                    $number = $max;
                }

                if (isset($field['default']) && $number === (float) $field['default']) {
                    continue;
                }

                $sanitised[$key] = $number;

                continue;
            }

            if ('' === (string) $raw) {
                continue;
            }

            $text = sanitize_text_field((string) $raw);

            if ('' === $text) {
                continue;
            }

            if (isset($field['default']) && $text === (string) $field['default']) {
                continue;
            }

            $sanitised[$key] = $text;
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

        // Handle both 'yes'/'no' from toggle and 1/0 from legacy checkbox
        $show_price_from_raw = $value['show_price_from'] ?? false;
        $show_price_from = in_array($show_price_from_raw, ['yes', '1', 1, true, 'true'], true);

        $experience_badges = $this->sanitize_experience_badge_settings($value['experience_badges'] ?? []);

        return [
            'filters' => array_values(array_unique($filters)),
            'per_page' => $per_page,
            'order' => $order,
            'orderby' => $orderby,
            'show_price_from' => $show_price_from,
            'experience_badges' => $experience_badges,
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function sanitize_experience_badge_settings($value): array
    {
        $result = [
            'overrides' => [],
            'custom' => [],
        ];

        if (! is_array($value)) {
            return $result;
        }

        $default_badges = Helpers::default_experience_badge_choices();
        $default_lookup = [];

        foreach ($default_badges as $badge) {
            if (! is_array($badge)) {
                continue;
            }

            $badge_id = isset($badge['id']) ? sanitize_key((string) $badge['id']) : '';
            if ('' === $badge_id) {
                continue;
            }

            $default_lookup[$badge_id] = [
                'label' => isset($badge['label']) ? (string) $badge['label'] : '',
                'description' => isset($badge['description']) ? (string) $badge['description'] : '',
            ];
        }

        if (isset($value['overrides']) && is_array($value['overrides'])) {
            foreach ($value['overrides'] as $id => $override) {
                $badge_id = sanitize_key((string) $id);
                if ('' === $badge_id || ! isset($default_lookup[$badge_id])) {
                    continue;
                }

                $override = is_array($override) ? $override : [];

                $default_label = $default_lookup[$badge_id]['label'] ?? '';
                $default_description = $default_lookup[$badge_id]['description'] ?? '';

                $entry = [];

                if (array_key_exists('label', $override)) {
                    $label_value = sanitize_text_field((string) $override['label']);
                    if ('' !== $label_value && $label_value !== $default_label) {
                        $entry['label'] = $label_value;
                    }
                }

                if (array_key_exists('description', $override)) {
                    $description_value = sanitize_text_field((string) $override['description']);
                    if ($description_value !== $default_description) {
                        $entry['description'] = $description_value;
                    }
                }

                if ([] !== $entry) {
                    $result['overrides'][$badge_id] = $entry;
                }
            }
        }

        $seen_custom = [];
        if (isset($value['custom']) && is_array($value['custom'])) {
            foreach ($value['custom'] as $custom_badge) {
                if (! is_array($custom_badge)) {
                    continue;
                }

                $badge_id = isset($custom_badge['id']) ? sanitize_key((string) $custom_badge['id']) : '';
                $label_value = isset($custom_badge['label']) ? sanitize_text_field((string) $custom_badge['label']) : '';

                if ('' === $badge_id || '' === $label_value) {
                    continue;
                }

                $description_value = isset($custom_badge['description'])
                    ? sanitize_text_field((string) $custom_badge['description'])
                    : '';

                if (isset($default_lookup[$badge_id])) {
                    $entry = [];
                    $default_label = $default_lookup[$badge_id]['label'] ?? '';
                    $default_description = $default_lookup[$badge_id]['description'] ?? '';

                    if ($label_value !== $default_label) {
                        $entry['label'] = $label_value;
                    }

                    if ($description_value !== $default_description) {
                        $entry['description'] = $description_value;
                    }

                    if ([] !== $entry && ! isset($result['overrides'][$badge_id])) {
                        $result['overrides'][$badge_id] = $entry;
                    }

                    continue;
                }

                if (isset($result['overrides'][$badge_id]) || isset($seen_custom[$badge_id])) {
                    continue;
                }

                $seen_custom[$badge_id] = true;

                $result['custom'][] = [
                    'id' => $badge_id,
                    'label' => $label_value,
                    'description' => $description_value,
                ];
            }
        }

        return $result;
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

        $lists = [];
        if (isset($value['lists']) && is_array($value['lists'])) {
            foreach ($value['lists'] as $language => $list_id) {
                $language_key = sanitize_key((string) $language);
                $list_value = absint($list_id);

                if ($list_value > 0) {
                    $lists[$language_key] = $list_value;
                }
            }
        }
        $sanitised['lists'] = $lists;

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
        // Debug logging dettagliato per diagnosticare il problema di salvataggio
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp RTB] ========== SANITIZE_RTB CHIAMATO ==========');
            error_log('[FP-Exp RTB] Valore ricevuto: ' . print_r($value, true));
            error_log('[FP-Exp RTB] $_POST[fp_exp_rtb] presente: ' . (isset($_POST['fp_exp_rtb']) ? 'SI' : 'NO'));
            if (isset($_POST['fp_exp_rtb'])) {
                error_log('[FP-Exp RTB] $_POST[fp_exp_rtb]: ' . print_r($_POST['fp_exp_rtb'], true));
            }
            error_log('[FP-Exp RTB] Valore attuale in DB: ' . print_r(get_option('fp_exp_rtb', []), true));
        }

        $value = is_array($value) ? $value : [];

        $mode = isset($value['mode']) ? sanitize_key((string) $value['mode']) : 'off';
        
        // Debug logging per il mode ricevuto
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp RTB] Mode received: ' . ($value['mode'] ?? 'NOT SET') . ' -> sanitized to: ' . $mode);
        }

        if (! in_array($mode, ['off', 'confirm', 'pay_later'], true)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP-Exp RTB] Mode "' . $mode . '" not in allowed values, defaulting to "off"');
            }
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

        // Debug logging per il valore finale che viene salvato
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP-Exp RTB] Final sanitized value to save: ' . print_r($clean, true));
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
            $this->getOptions()->set('fp_exp_google_calendar', $settings, false);
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
        $this->getOptions()->set('fp_exp_google_calendar', $settings, false);
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
            add_settings_error('fp_exp_settings', 'fp_exp_calendar_state_expired', esc_html__('La sessione OAuth è scaduta. Riprova.', 'fp-experiences'));
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
        $this->getOptions()->set('fp_exp_google_calendar', $settings, false);

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
        $settings = $this->getOptions()->get('fp_exp_google_calendar', []);

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
        $this->getOptions()->set('fp_exp_google_calendar', $settings, false);

        return $settings['access_token'];
    }

    /**
     * Renders preview notice for branding settings
     */
    private function render_preview_notice(): void
    {
        // Get a published experience for preview
        $experiences = get_posts([
            'post_type' => 'fp_experience',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($experiences)) {
            echo '<div class="fp-exp-preview-notice">';
            echo '<p>';
            echo '<span class="dashicons dashicons-info"></span> ';
            echo esc_html__('Crea un\'esperienza per vedere l\'anteprima del branding.', 'fp-experiences');
            echo ' <a href="' . esc_url(admin_url('post-new.php?post_type=fp_experience')) . '">' . esc_html__('Crea ora', 'fp-experiences') . ' →</a>';
            echo '</p>';
            echo '</div>';
            return;
        }

        $experience = $experiences[0];
        $preview_url = get_permalink($experience);

        echo '<div class="fp-exp-preview-notice fp-exp-preview-notice--success">';
        echo '<p>';
        echo '<span class="dashicons dashicons-visibility"></span> ';
        echo esc_html__('Visualizza le modifiche al branding:', 'fp-experiences');
        echo ' <a href="' . esc_url($preview_url) . '" target="_blank" class="fp-exp-preview-link">';
        echo esc_html__('Anteprima Esperienza', 'fp-experiences');
        echo ' <span class="dashicons dashicons-external"></span>';
        echo '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Get GetSettingsUseCase from container if available.
     */
    private function getGetSettingsUseCase(): ?GetSettingsUseCase
    {
        if ($this->getSettingsUseCase !== null) {
            return $this->getSettingsUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(GetSettingsUseCase::class)) {
                return null;
            }

            $this->getSettingsUseCase = $container->make(GetSettingsUseCase::class);
            return $this->getSettingsUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get UpdateSettingsUseCase from container if available.
     */
    private function getUpdateSettingsUseCase(): ?UpdateSettingsUseCase
    {
        if ($this->updateSettingsUseCase !== null) {
            return $this->updateSettingsUseCase;
        }

        try {
            $kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
            if ($kernel === null) {
                return null;
            }

            $container = $kernel->container();
            if (!$container->has(UpdateSettingsUseCase::class)) {
                return null;
            }

            $this->updateSettingsUseCase = $container->make(UpdateSettingsUseCase::class);
            return $this->updateSettingsUseCase;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
