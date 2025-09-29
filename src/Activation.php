<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\PostTypes\ExperienceCPT;

use function __;
use function add_role;
use function flush_rewrite_rules;
use function get_role;

final class Activation
{
    public static function activate(): void
    {
        $cpt = new ExperienceCPT();
        $cpt->register_immediately();

        Slots::create_table();
        Reservations::create_table();
        Resources::create_table();

        self::register_roles();

        flush_rewrite_rules();

        do_action('fp_exp_plugin_activated');
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();

        do_action('fp_exp_plugin_deactivated');
    }

    private static function register_roles(): void
    {
        $manager_caps = [
            'read' => true,
            'fp_exp_manage_settings' => true,
            'fp_exp_manage_bookings' => true,
            'fp_exp_manage_calendar' => true,
            'fp_exp_manual_bookings' => true,
            'fp_exp_manage_tools' => true,
            'fp_exp_manage_requests' => true,
            'fp_exp_view_assignments' => true,
            'fp_exp_handle_checkins' => true,
        ];

        add_role(
            'fp_exp_manager',
            __('FP Experiences Manager', 'fp-experiences'),
            $manager_caps
        );

        $operator_caps = [
            'read' => true,
            'fp_exp_manage_bookings' => true,
            'fp_exp_manage_calendar' => true,
            'fp_exp_manual_bookings' => true,
            'fp_exp_manage_requests' => true,
            'fp_exp_handle_checkins' => true,
        ];

        add_role(
            'fp_exp_operator',
            __('FP Experiences Operator', 'fp-experiences'),
            $operator_caps
        );

        $guide_caps = [
            'read' => true,
            'fp_exp_view_assignments' => true,
        ];

        add_role(
            'fp_exp_guide',
            __('FP Experiences Guide', 'fp-experiences'),
            $guide_caps
        );

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (array_keys($manager_caps) as $capability) {
                if (! $administrator->has_cap($capability)) {
                    $administrator->add_cap($capability);
                }
            }
        }
    }
}
