<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\PostTypes\ExperienceCPT;
use FP_Exp\Gift\VoucherTable;

use function __;
use function add_role;
use function current_time;
use function flush_rewrite_rules;
use function get_option;
use function get_role;
use function home_url;
use function update_option;
use function wp_json_encode;
use function wp_roles;

final class Activation
{
    public static function activate(): void
    {
        $cpt = new ExperienceCPT();
        $cpt->register_immediately();

        Slots::create_table();
        Reservations::create_table();
        Resources::create_table();
        VoucherTable::create_table();

        self::register_roles();
        update_option('fp_exp_roles_version', self::roles_version());

        flush_rewrite_rules();

        // Crea automaticamente un backup delle impostazioni di branding se non esiste già
        self::ensure_branding_backup();

        do_action('fp_exp_plugin_activated');
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();

        do_action('fp_exp_plugin_deactivated');
    }

    public static function register_roles(): void
    {
        $roles = self::roles_definition();

        foreach ($roles as $role_name => $role) {
            self::ensure_role_caps($role_name, $role['label'], $role['capabilities']);
        }

        self::propagate_custom_role_caps($roles);

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (array_keys($roles['fp_exp_manager']['capabilities']) as $capability) {
                if (! $administrator->has_cap($capability)) {
                    $administrator->add_cap($capability);
                }
            }
        }
    }

    /**
     * Return the capabilities required by FP Experiences managers.
     *
     * @return array<string, bool>
     */
    public static function manager_capabilities(): array
    {
        $roles = self::roles_definition();

        return $roles['fp_exp_manager']['capabilities'];
    }

    /**
     * Expose the role blueprint so other components can inspect the expected setup.
     *
     * @return array<string, array{label: string, primary_capability: string, capabilities: array<string, bool>}>
     */
    public static function roles_blueprint(): array
    {
        return self::roles_definition();
    }

    /**
     * @return array<string, array{label: string, primary_capability: string, capabilities: array<string, bool>}>
     */
    private static function roles_definition(): array
    {
        $experience_caps = [
            'edit_fp_experience' => true,
            'read_fp_experience' => true,
            'delete_fp_experience' => true,
            'edit_fp_experiences' => true,
            'edit_others_fp_experiences' => true,
            'publish_fp_experiences' => true,
            'read_private_fp_experiences' => true,
            'delete_fp_experiences' => true,
            'delete_others_fp_experiences' => true,
            'delete_private_fp_experiences' => true,
            'delete_published_fp_experiences' => true,
            'edit_private_fp_experiences' => true,
            'edit_published_fp_experiences' => true,
        ];

        $manager_caps = array_merge(
            [
                'read' => true,
                'edit_posts' => true,
                'upload_files' => true,
                'fp_exp_manage' => true,
                'fp_exp_operate' => true,
                'fp_exp_guide' => true,
            ],
            $experience_caps
        );

        $operator_caps = array_merge(
            [
                'read' => true,
                'edit_posts' => true,
                'fp_exp_operate' => true,
                'fp_exp_guide' => true,
            ],
            $experience_caps
        );

        $guide_caps = [
            'read' => true,
            'fp_exp_guide' => true,
        ];

        return [
            'fp_exp_manager' => [
                'label' => __('FP Experiences Manager', 'fp-experiences'),
                'primary_capability' => 'fp_exp_manage',
                'capabilities' => $manager_caps,
            ],
            'fp_exp_operator' => [
                'label' => __('FP Experiences Operator', 'fp-experiences'),
                'primary_capability' => 'fp_exp_operate',
                'capabilities' => $operator_caps,
            ],
            'fp_exp_guide' => [
                'label' => __('FP Experiences Guide', 'fp-experiences'),
                'primary_capability' => 'fp_exp_guide',
                'capabilities' => $guide_caps,
            ],
        ];
    }

    public static function roles_version(): string
    {
        $roles = self::roles_definition();
        $signature = [];

        foreach ($roles as $role_name => $role) {
            $capabilities = $role['capabilities'];
            ksort($capabilities);
            $signature[$role_name] = $capabilities;
        }

        ksort($signature);

        $payload = wp_json_encode($signature);

        return 'cap:' . md5($payload ?: '');
    }

    /**
     * @param array<string, bool> $capabilities
     */
    private static function ensure_role_caps(string $role_name, string $display_name, array $capabilities): void
    {
        $role = get_role($role_name);

        if (! $role) {
            add_role($role_name, $display_name, $capabilities);

            return;
        }

        foreach ($capabilities as $capability => $granted) {
            if (! $granted) {
                continue;
            }

            if (! $role->has_cap($capability)) {
                $role->add_cap($capability);
            }
        }
    }

    /**
     * @param array<string, array{label: string, primary_capability: string, capabilities: array<string, bool>}> $roles
     */
    private static function propagate_custom_role_caps(array $roles): void
    {
        $wp_roles = wp_roles();

        if (! $wp_roles) {
            return;
        }

        foreach ($wp_roles->role_objects as $role_name => $role) {
            if (isset($roles[$role_name])) {
                continue;
            }

            if ($role->has_cap('manage_woocommerce')) {
                foreach (['fp_exp_manage', 'fp_exp_operate', 'fp_exp_guide'] as $capability) {
                    if (! $role->has_cap($capability)) {
                        $role->add_cap($capability);
                    }
                }
            }

            foreach ($roles as $role_definition) {
                $primary_capability = $role_definition['primary_capability'];

                if (! $role->has_cap($primary_capability)) {
                    continue;
                }

                foreach ($role_definition['capabilities'] as $capability => $granted) {
                    if (! $granted || $role->has_cap($capability)) {
                        continue;
                    }

                    $role->add_cap($capability);
                }
            }
        }
    }

    /**
     * Crea automaticamente un backup delle impostazioni di branding se non esiste già.
     * Questo metodo viene chiamato durante l'attivazione del plugin per preservare
     * le impostazioni esistenti durante gli aggiornamenti.
     */
    private static function ensure_branding_backup(): void
    {
        // Controlla se esiste già un backup
        $existing_backup = get_option('fp_exp_branding_backup', null);
        
        if ($existing_backup && is_array($existing_backup)) {
            // Backup già esistente, non sovrascrivere
            return;
        }

        // Raccoglie tutte le impostazioni di branding esistenti
        $branding_settings = [
            'fp_exp_branding' => get_option('fp_exp_branding', []),
            'fp_exp_email_branding' => get_option('fp_exp_email_branding', []),
            'fp_exp_emails' => get_option('fp_exp_emails', []),
            'fp_exp_tracking' => get_option('fp_exp_tracking', []),
            'fp_exp_brevo' => get_option('fp_exp_brevo', []),
            'fp_exp_google_calendar' => get_option('fp_exp_google_calendar', []),
            'fp_exp_experience_layout' => get_option('fp_exp_experience_layout', []),
            'fp_exp_listing' => get_option('fp_exp_listing', []),
            'fp_exp_gift' => get_option('fp_exp_gift', []),
            'fp_exp_rtb' => get_option('fp_exp_rtb', []),
            'fp_exp_enable_meeting_points' => get_option('fp_exp_enable_meeting_points', 'no'),
            'fp_exp_enable_meeting_point_import' => get_option('fp_exp_enable_meeting_point_import', 'no'),
            'fp_exp_structure_email' => get_option('fp_exp_structure_email', ''),
            'fp_exp_webmaster_email' => get_option('fp_exp_webmaster_email', ''),
            'fp_exp_debug_logging' => get_option('fp_exp_debug_logging', 'no'),
        ];

        // Verifica se ci sono impostazioni da salvare
        $has_settings = false;
        foreach ($branding_settings as $value) {
            if (!empty($value) && (!is_array($value) || !empty(array_filter($value)))) {
                $has_settings = true;
                break;
            }
        }

        if (!$has_settings) {
            // Nessuna impostazione da salvare, non creare backup
            return;
        }

        // Crea il backup
        $backup_data = [
            'timestamp' => current_time('mysql'),
            'version' => get_option('fp_exp_version', 'unknown'),
            'site_url' => home_url(),
            'settings' => $branding_settings,
            'auto_created' => true,
        ];

        // Salva il backup
        update_option('fp_exp_branding_backup', $backup_data);
    }
}
